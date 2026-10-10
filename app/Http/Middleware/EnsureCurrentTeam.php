<?php

namespace App\Http\Middleware;

use App\Actions\Teams\CreateTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees every signed-in user has a valid current team:
 * - no current team, or removed from it -> switch to another team they belong to
 * - no teams at all (e.g. accounts created before teams existed) -> create a personal team
 */
class EnsureCurrentTeam
{
    public function __construct(private CreateTeam $createTeam) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $team = $user->currentTeam;

        if ($team === null || ! $user->belongsToTeam($team)) {
            $fallback = $user->teams()->orderBy('teams.id')->first();

            if ($fallback !== null) {
                $user->switchTeam($fallback);
            } else {
                $this->createTeam->personal($user);
            }
        }

        return $next($request);
    }
}
