<?php

namespace App\Services;

use Composer\InstalledVersions;
use DiceBear\Avatar;
use DiceBear\Style;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

final class DiceBearAvatarGenerator
{
    public const string CRITTERS = 'critters';

    public const string LOOPS = 'loops';

    public const string MICAH = 'micah';

    public const string SHAPE_GRID = 'shape-grid';

    /** @var list<string> */
    public const array STYLES = [
        self::CRITTERS,
        self::LOOPS,
        self::MICAH,
        self::SHAPE_GRID,
    ];

    private const string STYLES_PACKAGE = 'dicebear/styles';

    public const int CACHE_SECONDS = 86_400;

    /** @var array<string, Style> */
    private static array $styles = [];

    /**
     * Render a DiceBear avatar as SVG.
     */
    public function svg(string $style, string $seed): string
    {
        $options = self::options($style, $seed);

        /** @var string $svg */
        $svg = Cache::remember(
            self::cacheKey($style, $seed),
            self::CACHE_SECONDS,
            fn (): string => (new Avatar(
                self::style($style),
                $options,
            ))->toString(),
        );

        return $svg;
    }

    private static function style(string $name): Style
    {
        return self::$styles[$name] ??= Style::fromJson(self::styleDefinition($name));
    }

    private static function styleDefinition(string $name): string
    {
        $stylesPath = InstalledVersions::getInstallPath(self::STYLES_PACKAGE);

        if (! is_string($stylesPath)) {
            throw new RuntimeException('The DiceBear styles package is not installed.');
        }

        $definition = file_get_contents($stylesPath.'/src/'.$name.'.json');

        if ($definition === false) {
            throw new RuntimeException("Unable to load the DiceBear {$name} style definition.");
        }

        return $definition;
    }

    private static function cacheKey(string $style, string $seed): string
    {
        return 'dicebear-avatar:'.hash('sha256', implode(':', [
            $style,
            $seed,
            (string) InstalledVersions::getVersion('dicebear/core'),
            (string) InstalledVersions::getVersion(self::STYLES_PACKAGE),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function options(string $style, string $seed): array
    {
        return match ($style) {
            self::CRITTERS, self::LOOPS, self::SHAPE_GRID => ['seed' => $seed],
            self::MICAH => [
                'seed' => $seed,
                'backgroundColor' => ['#ffe3ea', '#e3edff', '#e2f5e9', '#fdf1d4', '#efe6ff'],
                'borderRadius' => 50,
                'scale' => 0.9,
            ],
            default => throw new InvalidArgumentException("Unsupported DiceBear style [{$style}]."),
        };
    }
}
