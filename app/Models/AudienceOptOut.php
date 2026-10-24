<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An opt-out kept after its membership row was deleted, so adding the address
 * back to the audience can never quietly resubscribe it.
 *
 * @property int $id
 * @property int $audience_id
 * @property string $email
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Audience $audience
 */
#[Fillable(['audience_id', 'email', 'unsubscribed_at'])]
class AudienceOptOut extends Model
{
    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public static function existsFor(int $audienceId, string $email): bool
    {
        return static::query()
            ->where('audience_id', $audienceId)
            ->where('email', Str::lower(trim($email)))
            ->exists();
    }

    /**
     * Keep the opt-out of every unsubscribed member in the given rows before
     * they are deleted. A later opt-out date never overwrites an earlier one.
     *
     * @param  iterable<int, \stdClass>  $members  rows with audience_id, email and unsubscribed_at
     */
    public static function rememberMembers(iterable $members): void
    {
        $now = now();
        $rows = [];

        foreach ($members as $member) {
            $rows[] = [
                'audience_id' => $member->audience_id,
                'email' => Str::lower($member->email),
                'unsubscribed_at' => $member->unsubscribed_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            static::query()->insertOrIgnore($chunk);
        }
    }

    protected function casts(): array
    {
        return [
            'unsubscribed_at' => 'datetime',
        ];
    }
}
