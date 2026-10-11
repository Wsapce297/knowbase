<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CurrentTeamController extends Controller
{
    /**
     * Switch the signed-in user to another team they belong to.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer'],
        ]);

        $team = Team::findOrFail($validated['team_id']);

        Gate::authorize('view', $team);

        $request->user()->switchTeam($team);

        return to_route('dashboard');
    }
}
