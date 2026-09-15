import type { DotMatrixAnimation } from '@/components/dot-matrix';
import { DotMatrix } from '@/components/dot-matrix';
import { cn } from '@/lib/utils';

export function LayeredDotMatrix({
    className,
    cellSize = 6,
    animation = 'orbit',
}: {
    className?: string;
    cellSize?: number;
    animation?: DotMatrixAnimation;
}) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'pointer-events-none relative size-full overflow-hidden',
                className,
            )}
            style={{
                background:
                    'linear-gradient(145deg, #060d1b 5%, #08235d 40%, #367ed4 72%, #d4e8fc)',
            }}
        >
            <DotMatrix
                className="absolute inset-0"
                cellSize={cellSize}
                animation={animation}
            />
            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(to bottom, #060d1bf2 0%, #06296945 40%, transparent 65%, var(--background) 100%)',
                }}
            />
        </div>
    );
}
