<?php

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\UpdateWidgetRequest;
use App\Models\Team;
use App\Models\Widget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WidgetSettingsController extends Controller
{
    /**
     * Show the widget settings for the user's current team.
     * Every member can see them; only owners and admins can change them.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        Gate::authorize('view', $team);

        $widget = $team->widget;

        return Inertia::render('settings/Widget', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'personal' => $team->personal_team,
                'role' => $user->teamRole($team)?->value,
            ],
            'widget' => $widget ? [
                'publicKey' => $widget->public_key,
                'enabled' => $widget->enabled,
                'allowedOrigins' => $widget->allowed_origins,
                'greeting' => $widget->greeting,
                'embedCode' => $widget->embedCode(),
            ] : null,
            'maxOrigins' => UpdateWidgetRequest::MAX_ORIGINS,
            'maxGreetingLength' => UpdateWidgetRequest::MAX_GREETING_LENGTH,
            'permissions' => [
                'canManage' => $user->can('manageWidget', $team),
            ],
        ]);
    }

    /**
     * Create the team's widget on the first save, update it afterwards.
     * The team comes from the URL (not "current team") so a team switch in
     * another browser tab can't make this save land on the wrong team.
     */
    public function update(UpdateWidgetRequest $request, Team $team): RedirectResponse
    {
        $widget = $team->widget ?? $team->widget()->make();

        $widget->fill([
            'enabled' => $request->boolean('enabled'),
            'greeting' => $request->validated('greeting'),
            'allowed_origins' => $request->origins(),
        ])->save();

        return back();
    }

    public function regenerateKey(Team $team): RedirectResponse
    {
        Gate::authorize('manageWidget', $team);

        /** @var Widget|null $widget */
        $widget = $team->widget;

        abort_if($widget === null, 404);

        $widget->regenerateKey();

        return back();
    }
}
