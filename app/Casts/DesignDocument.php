<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use stdClass;

/**
 * A block design as an array that keeps its empty JSON objects. The `array`
 * cast decodes `{}` as `[]`, so a container's `props: {}` would be saved back,
 * and handed to the editor, as a list the design schema rejects.
 *
 * @implements CastsAttributes<array<string, mixed>|null, array<string, mixed>|null>
 */
class DesignDocument implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = self::fromObjects(json_decode((string) $value, flags: JSON_THROW_ON_ERROR));

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * Turn JSON decoded with objects into arrays, keeping each empty object
     * as an empty stdClass so it encodes back to `{}`.
     */
    public static function fromObjects(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);

            return $properties === [] ? $value : array_map(self::fromObjects(...), $properties);
        }

        return is_array($value) ? array_map(self::fromObjects(...), $value) : $value;
    }
}
