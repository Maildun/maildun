import {
    Add01Icon,
    MinusSignIcon,
    Refresh03Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Map as MapLibreMap, setWorkerUrl } from 'maplibre-gl';
import type {
    ExpressionSpecification,
    FilterSpecification,
    MapLayerMouseEvent,
} from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import countriesUrl from '@/lib/world-map-countries.geojson?url';
import type { CampaignInsightRow } from './campaign-insights';
import type { CampaignInsightMetric } from './campaign-insights-map';
import 'maplibre-gl/dist/maplibre-gl.css';
import './campaign-insights-map.css';

setWorkerUrl(workerUrl);

const COUNTRY_SOURCE = 'engagement-countries';
const COUNTRY_FILL = 'engagement-country-fill';
const COUNTRY_OUTLINE = 'engagement-country-outline';
const COUNTRY_POINTS = 'engagement-country-points';
const WORLD_VIEW = { center: [0, 20] as [number, number], zoom: 0 };
const WORLD_BOUNDS: [[number, number], [number, number]] = [
    [-180, -60],
    [180, 80],
];

export default function CampaignInsightsMapInteractive({
    countries,
    metric,
    onUnavailable,
}: {
    countries: CampaignInsightRow[];
    metric: CampaignInsightMetric;
    onUnavailable: () => void;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<MapLibreMap | null>(null);
    const viewRef = useRef<typeof WORLD_VIEW | null>(null);
    const { resolvedAppearance } = useAppearance();
    const [ready, setReady] = useState(false);
    const [activeCountryCode, setActiveCountryCode] = useState<string | null>(
        null,
    );
    const rankedCountries = [...countries].sort(
        (first, second) =>
            metricValue(second, metric) - metricValue(first, metric),
    );
    const activeCountry =
        countries.find(
            (country) => country.key.toUpperCase() === activeCountryCode,
        ) ?? rankedCountries[0];

    const syncMetrics = useEffectEvent((map: MapLibreMap) => {
        if (!map.getLayer(COUNTRY_FILL)) {
            return;
        }

        const maximumValue = Math.max(
            ...countries.map((row) => metricValue(row, metric)),
            1,
        );
        const valuesByCode = Object.fromEntries(
            countries.map((row) => [
                row.key.toUpperCase(),
                metricValue(row, metric),
            ]),
        );
        const valueExpression: ExpressionSpecification = [
            'to-number',
            ['get', ['get', 'code'], ['literal', valuesByCode]],
            0,
        ];
        const opacity: ExpressionSpecification = [
            'interpolate',
            ['linear'],
            valueExpression,
            0,
            0,
            maximumValue,
            0.7,
        ];
        const filter: FilterSpecification = [
            'in',
            ['get', 'code'],
            [
                'literal',
                countries
                    .filter((row) => metricValue(row, metric) > 0)
                    .map((row) => row.key.toUpperCase()),
            ],
        ];

        map.setPaintProperty(COUNTRY_FILL, 'fill-opacity', opacity);
        map.setFilter(COUNTRY_FILL, filter);
        map.setFilter(COUNTRY_OUTLINE, filter);
        map.setFilter(COUNTRY_POINTS, [
            'all',
            filter,
            ['==', ['geometry-type'], 'Point'],
        ]);
        map.setPaintProperty(COUNTRY_POINTS, 'circle-radius', [
            'interpolate',
            ['linear'],
            valueExpression,
            0,
            4,
            maximumValue,
            9,
        ]);
    });

    useEffect(() => {
        if (!containerRef.current) {
            return;
        }

        let map: MapLibreMap;

        try {
            map = new MapLibreMap({
                container: containerRef.current,
                style: `https://tiles.openfreemap.org/styles/${resolvedAppearance === 'dark' ? 'dark' : 'positron'}`,
                ...(viewRef.current ?? WORLD_VIEW),
                minZoom: -2,
                maxZoom: 6,
                renderWorldCopies: false,
                dragRotate: false,
                pitchWithRotate: false,
                touchPitch: false,
                cooperativeGestures: true,
            });
        } catch {
            onUnavailable();

            return;
        }

        mapRef.current = map;
        map.touchZoomRotate.disableRotation();

        if (!viewRef.current) {
            map.fitBounds(WORLD_BOUNDS, { padding: 16, duration: 0 });
        }

        let loaded = false;
        const timeout = window.setTimeout(() => {
            if (!loaded) {
                onUnavailable();
            }
        }, 15000);

        map.on('style.load', () => {
            for (const layer of map.getStyle().layers) {
                if (layer.type === 'symbol' && layer.id.includes('_country_')) {
                    map.setLayoutProperty(layer.id, 'visibility', 'none');
                }
            }

            map.addSource(COUNTRY_SOURCE, {
                type: 'geojson',
                data: countriesUrl,
                attribution: 'Country boundaries: Natural Earth',
            });
            map.addLayer({
                id: COUNTRY_FILL,
                type: 'fill',
                source: COUNTRY_SOURCE,
                paint: { 'fill-color': '#3b82f6', 'fill-opacity': 0 },
            });
            map.addLayer({
                id: COUNTRY_OUTLINE,
                type: 'line',
                source: COUNTRY_SOURCE,
                paint: {
                    'line-color': '#3b82f6',
                    'line-width': 1,
                    'line-opacity': 0.7,
                },
            });
            map.addLayer({
                id: COUNTRY_POINTS,
                type: 'circle',
                source: COUNTRY_SOURCE,
                paint: {
                    'circle-color': '#3b82f6',
                    'circle-radius': 5,
                    'circle-stroke-color':
                        resolvedAppearance === 'dark' ? '#171717' : '#ffffff',
                    'circle-stroke-width': 2,
                },
            });
            syncMetrics(map);
        });

        map.on('load', () => {
            loaded = true;
            window.clearTimeout(timeout);
            setReady(true);
        });
        map.on('error', () => {
            if (!loaded) {
                onUnavailable();
            }
        });

        const selectCountry = (event: MapLayerMouseEvent) => {
            const code = event.features?.[0]?.properties.code;

            if (typeof code === 'string') {
                setActiveCountryCode(code);
            }
        };

        for (const layer of [COUNTRY_FILL, COUNTRY_POINTS]) {
            map.on('mousemove', layer, selectCountry);
            map.on('click', layer, selectCountry);
            map.on('mouseleave', layer, () => setActiveCountryCode(null));
        }

        const observer = new ResizeObserver(() => map.resize());
        observer.observe(containerRef.current);

        return () => {
            window.clearTimeout(timeout);
            observer.disconnect();
            const center = map.getCenter();
            viewRef.current = {
                center: [center.lng, center.lat],
                zoom: map.getZoom(),
            };
            mapRef.current = null;
            map.remove();
        };
    }, [resolvedAppearance, onUnavailable]);

    useEffect(() => {
        if (mapRef.current) {
            syncMetrics(mapRef.current);
        }
    }, [countries, metric]);

    return (
        <div
            className="campaign-engagement-map relative h-full min-h-72 flex-1 overflow-hidden rounded-lg bg-muted"
            data-test="campaign-insights-world-map"
            aria-label={`World map shaded by unique human ${metric}`}
        >
            <div ref={containerRef} className="absolute inset-0" />
            <div
                className="pointer-events-none absolute top-3 left-3 max-w-[calc(100%-4.5rem)] rounded-lg border bg-background/95 px-3 py-2 shadow-sm backdrop-blur-sm"
                aria-live="polite"
            >
                <p className="font-medium">
                    {activeCountry?.label ?? 'No location data'}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    {activeCountry
                        ? `${activeCountry.unique_opens.toLocaleString()} unique opens · ${activeCountry.unique_clicks.toLocaleString()} unique clicks`
                        : 'Human locations appear after engagement.'}
                </p>
            </div>
            <div className="absolute top-3 right-3 flex flex-col gap-1 rounded-lg border bg-background/95 p-1 shadow-sm">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Zoom in"
                    disabled={!ready}
                    onClick={() => mapRef.current?.zoomIn()}
                >
                    <HugeiconsIcon icon={Add01Icon} aria-hidden />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Zoom out"
                    disabled={!ready}
                    onClick={() => mapRef.current?.zoomOut()}
                >
                    <HugeiconsIcon icon={MinusSignIcon} aria-hidden />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Reset map"
                    disabled={!ready}
                    onClick={() =>
                        mapRef.current?.fitBounds(WORLD_BOUNDS, { padding: 16 })
                    }
                >
                    <HugeiconsIcon icon={Refresh03Icon} aria-hidden />
                </Button>
            </div>
            <div className="pointer-events-none absolute bottom-8 left-3 flex items-center gap-2 rounded-md border bg-background/95 px-2 py-1 text-[0.6875rem] text-muted-foreground">
                <span>Lower</span>
                <span className="h-2 w-20 rounded-full bg-linear-to-r from-[#3b82f6]/10 to-[#3b82f6]/70" />
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
