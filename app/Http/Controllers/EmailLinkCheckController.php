<?php

namespace App\Http\Controllers;

use App\Actions\Emails\CheckCampaignLinks;
use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EmailLinkCheckController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Team $currentTeam, Email $email, CheckCampaignLinks $checkCampaignLinks): JsonResponse
    {
        Gate::authorize('update', $email);
        abort_unless($email->status === EmailStatus::Draft, 409, __('Only draft campaign links can be checked.'));

        return response()->json($checkCampaignLinks->handle($email));
    }
}
