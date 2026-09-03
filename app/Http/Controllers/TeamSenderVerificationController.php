<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamSender;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TeamSenderVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, TeamSender $teamSender): Response
    {
        DB::transaction(function () use ($request, $teamSender): void {
            $sender = TeamSender::query()->whereKey($teamSender->id)->lockForUpdate()->firstOrFail();
            $team = Team::query()->whereKey($sender->team_id)->lockForUpdate()->firstOrFail();
            $integrationId = $request->integer('integration');
            $verificationVersion = $request->integer('version');
            $integration = $team->emailIntegration()
                ->whereKey($integrationId)
                ->where('verification_version', $verificationVersion)
                ->lockForUpdate()
                ->first();

            abort_unless($integration?->isVerified() === true, 409, __('This sender verification link is no longer current.'));

            if (! $sender->isVerifiedFor($integration)) {
                $sender->authorizeFor($integration);
            }

            if ($team->active_sender_id === null) {
                $team->forceFill([
                    'active_sender_id' => $sender->id,
                    'email_from_name' => $sender->name,
                    'email_from_address' => $sender->email,
                    'email_reply_to' => $sender->reply_to,
                ])->save();
            }
        });

        return response()->view('sender-verification');
    }
}
