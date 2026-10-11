<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\TeamNameRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function store(TeamNameRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $createTeam->handle($request->user(), $request->validated('name'));

        return to_route('dashboard');
    }

    public function update(TeamNameRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->update(['name' => $request->validated('name')]);

        return back();
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        // Members' current_team_id is set to null by the foreign key,
        // and EnsureCurrentTeam moves them to another team on their next request.
        $team->delete();

        return to_route('dashboard');
    }
}
