import { lazy, Suspense, useCallback, useState } from 'react';
import { CampaignInsightsMapFallback } from '@/components/campaign-insights-map-fallback';
import { Skeleton } from '@/components/ui/skeleton';
import { useMounted } from '@/hooks/use-mounted';
import type { CampaignInsightRow } from './campaign-insights';

export type CampaignInsightMetric = 'opens' | 'clicks';

const InteractiveMap = lazy(
    () => import('@/components/campaign-insights-map-interactive'),
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

    if (unavailable) {
        return (
            <div className="flex h-full flex-1 flex-col gap-2">
                <p className="text-xs text-muted-foreground" role="status">
                    Interactive map unavailable. Showing the country overview.
                </p>
                <CampaignInsightsMapFallback
                    countries={countries}
                    metric={metric}
                />
            </div>
        );
    }

    const placeholder = (
        <Skeleton
            className="h-full min-h-64 flex-1 rounded-lg"
            aria-label="Loading engagement map"
        />
    );

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
