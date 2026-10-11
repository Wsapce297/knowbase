<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\InviteTeamMember;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamInvitationController extends Controller
{
    public function store(Request $request, Team $team, InviteTeamMember $invite): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(TeamRole::class)->only(TeamRole::assignable())],
        ]);

        $invite->handle($team, $request->user(), $validated['email'], TeamRole::from($validated['role']));

        return back();
    }

    public function destroy(Team $team, TeamInvitation $invitation): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        $invitation->delete();

        return back();
    }
}
