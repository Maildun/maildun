<?php

namespace App\Actions\Emails;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LookupEmailTrackingLocation
{
    public function __construct(private ReadMaxMindDatabase $readDatabase) {}

    /**
     * @return array{
     *     country_code: string|null,
     *     subdivision_code: string|null,
     *     subdivision_name: string|null,
     *     city_name: string|null,
     *     latitude: float|null,
     *     longitude: float|null,
     *     network_asn: int|null,
     *     network_name: string|null,
     *     geolocation_source: string,
     *     geolocated_at: CarbonInterface,
     * }|array{}
     */
    public function handle(?string $ipAddress): array
    {
        if (
            ! (bool) config('tracking.geolocation.enabled')
            || $ipAddress === null
            || filter_var($ipAddress, FILTER_VALIDATE_IP) === false
        ) {
            return [];
        }

        $city = $this->readDatabase->handle(
            $this->configuredPath('tracking.geolocation.city_database'),
            $ipAddress,
        );
        $asn = $this->readDatabase->handle(
            $this->configuredPath('tracking.geolocation.asn_database'),
            $ipAddress,
        );

        if ($city === null && $asn === null) {
            return [];
        }

        return [
            'country_code' => $this->countryCode(Arr::get($city, 'country.iso_code')),
            'subdivision_code' => $this->limitedString(Arr::get($city, 'subdivisions.0.iso_code'), 16),
            'subdivision_name' => $this->limitedString(Arr::get($city, 'subdivisions.0.names.en'), 128),
            'city_name' => $this->limitedString(Arr::get($city, 'city.names.en'), 128),
            'latitude' => $this->coordinate(Arr::get($city, 'location.latitude'), -90, 90),
            'longitude' => $this->coordinate(Arr::get($city, 'location.longitude'), -180, 180),
            'network_asn' => $this->asn(Arr::get($asn, 'autonomous_system_number')),
            'network_name' => $this->limitedString(Arr::get($asn, 'autonomous_system_organization'), 255),
            'geolocation_source' => (string) config('tracking.geolocation.source'),
            'geolocated_at' => now(),
        ];
    }

    private function configuredPath(string $key): ?string
    {
        $path = config($key);

        return is_string($path) ? $path : null;
    }

    private function countryCode(mixed $value): ?string
    {
        if (! is_string($value) || mb_strlen($value) !== 2) {
            return null;
        }

        return Str::upper($value);
    }

    private function limitedString(mixed $value, int $length): ?string
    {
        return is_string($value) && $value !== ''
            ? Str::limit($value, $length, '')
            : null;
    }

    private function coordinate(mixed $value, int $minimum, int $maximum): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        return $coordinate >= $minimum && $coordinate <= $maximum
            ? $coordinate
            : null;
    }

    private function asn(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        $asn = (int) $value;

        return $asn > 0 ? $asn : null;
    }
}
