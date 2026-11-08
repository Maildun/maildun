<?php

namespace App\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * Uniqueness ignores case: PostgreSQL and SQLite compare text
     * case-sensitively, so a plain unique rule would let a second account
     * claim another spelling of an address that already exists.
     *
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                if (! is_string($value)) {
                    return;
                }

                $taken = User::query()
                    ->whereRaw('LOWER(email) = ?', [Str::lower(trim($value))])
                    ->when($userId !== null, fn (Builder $query) => $query->whereKeyNot($userId))
                    ->exists();

                if ($taken) {
                    $fail('validation.unique')->translate();
                }
            },
        ];
    }
}
