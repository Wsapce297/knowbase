<?php

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Models\Widget;
use Illuminate\Http\Response;

/**
 * The page shown inside the iframe on a team's website.
 *
 * Public: no login, no session, no cookies. The browser enforces where it may
 * appear, using the frame-ancestors header built from the team's allowed websites.
 */
class WidgetFrameController extends Controller
{
    public function __invoke(string $key): Response
    {
        $widget = Widget::query()
            ->where('public_key', $key)
            ->where('enabled', true)
            ->with('team')
            ->first();

        // Unknown, regenerated and switched-off keys all look the same from outside.
        abort_if($widget === null, 404);

        return response()
            ->view('widget.frame', [
                'config' => [
                    'key' => $widget->public_key,
                    'teamName' => $widget->team->name,
                    'greeting' => $widget->greetingText(),
                ],
            ])
            ->withHeaders([
                'Content-Security-Policy' => $widget->frameAncestorsPolicy(),
                // Settings changes (switching off, new origins) must apply on the next load.
                'Cache-Control' => 'no-store',
                // Keep widget pages out of search engines.
                'X-Robots-Tag' => 'noindex, nofollow',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }
}
