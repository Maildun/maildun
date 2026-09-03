import { useState } from 'react';

import { cn } from '@/lib/utils';
import {
    WORLD_MAP_COUNTRIES,
    WORLD_MAP_TINY_COUNTRIES,
    WORLD_MAP_VIEW_BOX,
} from '@/lib/world-map-paths';

import type { CampaignInsightRow } from './campaign-insights';

export type CampaignInsightMetric = 'opens' | 'clicks';

const COUNTRY_PATH_CODES = new Set(
    WORLD_MAP_COUNTRIES.map((country) => country.code),
);

export function CampaignInsightsMap({
    countries,
    metric,
}: {
    countries: CampaignInsightRow[];
    metric: CampaignInsightMetric;
}) {
    const rankedCountries = [...countries].sort(
        (first, second) =>
            metricValue(second, metric) - metricValue(first, metric),
    );
    const [activeCountryCode, setActiveCountryCode] = useState<string | null>(
        rankedCountries[0]?.key.toUpperCase() ?? null,
    );
    const countriesByCode = new Map(
        countries.map((country) => [country.key.toUpperCase(), country]),
    );
    const activeCountry = activeCountryCode
        ? countriesByCode.get(activeCountryCode)
        : undefined;
    const maximumValue = Math.max(
        ...countries.map((country) => metricValue(country, metric)),
        0,
    );
    const tinyCountries = WORLD_MAP_TINY_COUNTRIES.filter(
        (country) =>
            countriesByCode.has(country.code) &&
            !COUNTRY_PATH_CODES.has(country.code),
    );

    const resetActiveCountry = () => {
        setActiveCountryCode(rankedCountries[0]?.key.toUpperCase() ?? null);
    };

    return (
        <div
            className="relative flex h-full min-h-52 flex-1 flex-col overflow-hidden rounded-lg bg-muted/30 p-2 sm:p-3"
            data-test="campaign-insights-world-map"
        >
            <div
                className="relative z-10 mb-2 w-fit min-w-36 rounded-md border bg-background/95 px-3 py-2 shadow-sm backdrop-blur-sm sm:pointer-events-none sm:absolute sm:top-3 sm:left-3 sm:mb-0"
                aria-live="polite"
            >
                {activeCountry ? (
                    <>
                        <p className="font-medium">{activeCountry.label}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {activeCountry.unique_opens.toLocaleString()} unique
                            opens ·{' '}
                            {activeCountry.unique_clicks.toLocaleString()}{' '}
                            unique clicks
                        </p>
                    </>
                ) : (
                    <>
                        <p className="font-medium">No location data</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Human locations appear after engagement.
                        </p>
                    </>
                )}
            </div>

            <svg
                viewBox={WORLD_MAP_VIEW_BOX}
                className="h-full min-h-0 w-full flex-1"
                role="group"
                aria-label={`World map shaded by unique human ${metric}`}
            >
                {WORLD_MAP_COUNTRIES.map((country) => {
                    const row = countriesByCode.get(country.code);
                    const value = row ? metricValue(row, metric) : 0;
                    const isActive = activeCountryCode === country.code;

                    return (
                        <g
                            key={country.code}
                            tabIndex={row ? 0 : undefined}
                            role={row ? 'img' : undefined}
                            aria-hidden={row ? undefined : true}
                            aria-label={
                                row ? countryAriaLabel(row, metric) : undefined
                            }
                            onMouseEnter={() => {
                                if (row) {
                                    setActiveCountryCode(country.code);
                                }
                            }}
                            onMouseLeave={resetActiveCountry}
                            onFocus={() => {
                                if (row) {
                                    setActiveCountryCode(country.code);
                                }
                            }}
                            onBlur={resetActiveCountry}
                            className={row ? 'outline-none' : undefined}
                            data-test={
                                row
                                    ? `campaign-insights-map-country-${country.code}`
                                    : undefined
                            }
                        >
                            {row ? (
                                <title>{countryAriaLabel(row, metric)}</title>
                            ) : null}
                            <path
                                d={country.path}
                                className={cn(
                                    'stroke-background stroke-[0.8] transition-colors duration-200 motion-reduce:transition-none',
                                    fillClass(value, maximumValue),
                                    row &&
                                        'cursor-default hover:stroke-foreground hover:stroke-[1.6]',
                                    isActive &&
                                        'stroke-foreground stroke-[1.8]',
                                )}
                            />
                        </g>
                    );
                })}

                {tinyCountries.map((country) => {
                    const row = countriesByCode.get(country.code);

                    if (!row) {
                        return null;
                    }

                    const value = metricValue(row, metric);
                    const isActive = activeCountryCode === country.code;

                    return (
                        <g
                            key={`${country.code}-${country.x}-${country.y}`}
                            tabIndex={0}
                            role="img"
                            aria-label={countryAriaLabel(row, metric)}
                            onMouseEnter={() =>
                                setActiveCountryCode(country.code)
                            }
                            onMouseLeave={resetActiveCountry}
                            onFocus={() => setActiveCountryCode(country.code)}
                            onBlur={resetActiveCountry}
                            className="outline-none"
                            data-test={`campaign-insights-map-country-${country.code}`}
                        >
                            <title>{countryAriaLabel(row, metric)}</title>
                            <circle
                                cx={country.x}
                                cy={country.y}
                                r={isActive ? 6 : 4.5}
                                className={cn(
                                    'cursor-default stroke-background stroke-[2] transition-all duration-200 hover:stroke-foreground motion-reduce:transition-none',
                                    fillClass(value, maximumValue),
                                    isActive &&
                                        'stroke-foreground stroke-[2.5]',
                                )}
                            />
                        </g>
                    );
                })}
            </svg>

            <div className="flex items-center justify-end gap-2 px-1 text-[0.6875rem] text-muted-foreground">
                <span>Lower</span>
                {[20, 40, 60, 80, 100].map((intensity) => (
                    <span
                        key={intensity}
                        className={cn(
                            'size-2.5 rounded-sm',
                            legendClass(intensity),
                        )}
                        aria-hidden="true"
                    />
                ))}
                <span>Higher</span>
            </div>
        </div>
    );
}

function metricValue(
    row: CampaignInsightRow,
    metric: CampaignInsightMetric,
): number {
    return metric === 'opens' ? row.unique_opens : row.unique_clicks;
}

function countryAriaLabel(
    row: CampaignInsightRow,
    metric: CampaignInsightMetric,
): string {
    return `${row.label}: ${metricValue(row, metric).toLocaleString()} unique human ${metric}`;
}

function fillClass(value: number, maximumValue: number): string {
    if (value === 0 || maximumValue === 0) {
        return 'fill-muted';
    }

    const ratio = value / maximumValue;

    if (ratio <= 0.2) {
        return 'fill-primary/25';
    }

    if (ratio <= 0.4) {
        return 'fill-primary/40';
    }

    if (ratio <= 0.6) {
        return 'fill-primary/60';
    }

    if (ratio <= 0.8) {
        return 'fill-primary/80';
    }

    return 'fill-primary';
}

function legendClass(intensity: number): string {
    if (intensity === 20) {
        return 'bg-primary/25';
    }

    if (intensity === 40) {
        return 'bg-primary/40';
    }

    if (intensity === 60) {
        return 'bg-primary/60';
    }

    if (intensity === 80) {
        return 'bg-primary/80';
    }

    return 'bg-primary';
}
