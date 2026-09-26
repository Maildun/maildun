<?php

namespace App\Http\Controllers;

use App\Enums\EmailDeliveryStatus;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransactionalEmailLogController extends Controller
{
    /**
     * Every message sent from one transactional email, newest first, with
     * who it went to, whether it went out, and why not.
     */
    public function __invoke(Request $request, Team $currentTeam, TransactionalEmail $transactionalEmail): Response
    {
        Gate::authorize('view', $transactionalEmail);

        $filters = $this->indexFilters($request);

        return Inertia::render('transactional/log', [
            'email' => [
                'uuid' => $transactionalEmail->uuid,
                'name' => $transactionalEmail->name,
                'slug' => $transactionalEmail->slug,
            ],
            'filters' => $filters,
            'deliveries' => $transactionalEmail->deliveries()
                ->with('apiKey:id,name')
                ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
                ->when($filters['q'] !== '', fn (Builder $query) => $query
                    ->whereRaw('LOWER(to_address) LIKE ?', ['%'.mb_strtolower($filters['q']).'%']))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (TransactionalEmailDelivery $delivery): array => [
                    'uuid' => $delivery->uuid,
                    'to' => $delivery->to_address,
                    'subject' => $delivery->subject,
                    'status' => $delivery->status->value,
                    'failure_reason' => $delivery->failure_reason,
                    'source' => $delivery->apiKey?->name,
                    'queued_at' => $delivery->created_at?->toISOString(),
                    'sent_at' => $delivery->sent_at?->toISOString(),
                ]),
        ]);
    }

    /**
     * @return array{q: string, status: string}
     */
    private function indexFilters(Request $request): array
    {
        $status = EmailDeliveryStatus::tryFrom($request->string('status')->toString());

        return [
            'q' => Str::limit($request->string('q')->trim()->toString(), 100, ''),
            'status' => $status instanceof EmailDeliveryStatus ? $status->value : '',
        ];
    }
}
