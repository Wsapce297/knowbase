<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcceptTeamInvitationController extends Controller
{
    public function __invoke(Request $request, string $token, AcceptTeamInvitation $accept): RedirectResponse
    {
        $invitation = TeamInvitation::query()->where('token', $token)->firstOrFail();

        abort_if($invitation->isExpired(), 410, 'This invitation has expired. Ask the team to send a new one.');

        abort_unless(
            Str::lower($request->user()->email) === Str::lower($invitation->email),
            403,
            "You're signed in as {$request->user()->email}, but this invitation was sent to a different address. Log out and sign in with the invited account, then open the link again.",
        );

        $accept->handle($invitation, $request->user());

        return to_route('dashboard');
    }
}
