import { lazy, Suspense, useCallback, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { useMounted } from '@/hooks/use-mounted';
import type { CampaignInsightRow } from './campaign-insights';

export type CampaignInsightMetric = 'opens' | 'clicks';

const InteractiveMap = lazy(
    () => import('@/components/campaign-insights-map-interactive'),
);

/**
 * Only shown when MapLibre cannot start, so its ~140 KB of country paths
 * load on that path alone.
 */
const FallbackMap = lazy(() =>
    import('@/components/campaign-insights-map-fallback').then((module) => ({
        default: module.CampaignInsightsMapFallback,
    })),
);

export function CampaignInsightsMap({
    countries,
    metric,
}: {
    countries: CampaignInsightRow[];
    metric: CampaignInsightMetric;
}) {
    const mounted = useMounted();
    const [unavailable, setUnavailable] = useState(false);
    const showFallback = useCallback(() => setUnavailable(true), []);
    const placeholder = (
        <Skeleton
            className="h-full min-h-64 flex-1 rounded-lg"
            aria-label="Loading engagement map"
        />
    );

    if (unavailable) {
        return (
            <div className="flex h-full flex-1 flex-col gap-2">
                <p className="text-xs text-muted-foreground" role="status">
                    Interactive map unavailable. Showing the country overview.
                </p>
                <Suspense fallback={placeholder}>
                    <FallbackMap countries={countries} metric={metric} />
                </Suspense>
            </div>
        );
    }

    return mounted ? (
        <Suspense fallback={placeholder}>
            <InteractiveMap
                countries={countries}
                metric={metric}
                onUnavailable={showFallback}
            />
        </Suspense>
    ) : (
        placeholder
    );
}
