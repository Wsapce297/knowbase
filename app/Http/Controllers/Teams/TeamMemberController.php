<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamMemberController extends Controller
{
    /**
     * Change a member's role (owners only, and never the owner's own role).
     */
    public function update(Request $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMemberRole', $team);

        abort_unless($team->hasMember($user), 404);
        abort_if($user->hasTeamRole($team, TeamRole::Owner), 403, 'The owner role cannot be changed.');

        $validated = $request->validate([
            'role' => ['required', Rule::enum(TeamRole::class)->only(TeamRole::assignable())],
        ]);

        $team->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return back();
    }

    /**
     * Remove a member, or leave the team when removing yourself.
     */
    public function destroy(Request $request, Team $team, User $user): RedirectResponse
    {
        abort_unless($team->hasMember($user), 404);

        Gate::authorize('removeMember', [$team, $user]);

        $team->members()->detach($user);

        // Leaving: EnsureCurrentTeam moves them to another team on the next request.
        if ($user->is($request->user())) {
            return to_route('dashboard');
        }

        return back();
    }
}
