import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

/** Brightness buckets; each bucket is filled as one batched path per frame. */
const LEVELS = 16;
const FRAME_INTERVAL = 1000 / 30;
const MAX_PIXEL_RATIO = 2;

export type DotMatrixAnimation =
    'orbit' | 'sweep' | 'ripple' | 'wave' | 'drift' | 'pulse';

/** Dot colors from the darkest to the brightest part of the field. */
const PALETTE: ReadonlyArray<readonly [number, number, number]> = [
    [7, 20, 47],
    [21, 80, 177],
    [131, 186, 255],
    [241, 247, 255],
];

function paletteColor(value: number): string {
    const position = value * (PALETTE.length - 1);
    const index = Math.min(Math.floor(position), PALETTE.length - 2);
    const mix = position - index;
    const [fromRed, fromGreen, fromBlue] = PALETTE[index];
    const [toRed, toGreen, toBlue] = PALETTE[index + 1];

    return `rgb(${Math.round(fromRed + (toRed - fromRed) * mix)} ${Math.round(fromGreen + (toGreen - fromGreen) * mix)} ${Math.round(fromBlue + (toBlue - fromBlue) * mix)})`;
}

function brightnessAt(
    horizontal: number,
    vertical: number,
    aspectRatio: number,
    seconds: number,
    animation: DotMatrixAnimation,
): number {
    const base = 0.3 + 0.32 * vertical;
    let value: number;

    if (animation === 'sweep') {
        const position = ((seconds * 0.16) % 1.8) - 0.4;
        const distance = horizontal + vertical * 0.42 - position;
        const beam = Math.exp(-((distance / 0.11) ** 2));
        const trail = Math.exp(-(((distance + 0.2) / 0.22) ** 2));

        value = base + 0.48 * beam + 0.14 * trail;
    } else if (animation === 'ripple') {
        const deltaX = (horizontal - 0.5) * aspectRatio;
        const deltaY = vertical - 0.5;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        const rings = Math.sin(distance * 22 - seconds * 2) * 0.5 + 0.5;
        const envelope = Math.exp(-distance * 1.25);

        value = base + 0.38 * rings * envelope;
    } else if (animation === 'wave') {
        const firstCenter =
            0.46 + 0.18 * Math.sin(horizontal * 5.5 - seconds * 0.9);
        const secondCenter =
            0.68 + 0.1 * Math.sin(horizontal * 7 + seconds * 0.65);
        const firstWave = Math.exp(-(((vertical - firstCenter) / 0.11) ** 2));
        const secondWave = Math.exp(-(((vertical - secondCenter) / 0.08) ** 2));

        value = base + 0.34 * firstWave + 0.18 * secondWave;
    } else if (animation === 'drift') {
        const firstX = 0.28 + 0.24 * Math.sin(seconds * 0.42);
        const firstY = 0.38 + 0.16 * Math.cos(seconds * 0.31);
        const secondX = 0.72 + 0.2 * Math.cos(seconds * 0.29);
        const secondY = 0.62 + 0.14 * Math.sin(seconds * 0.37);
        const firstDeltaX = (horizontal - firstX) * aspectRatio;
        const firstDeltaY = vertical - firstY;
        const secondDeltaX = (horizontal - secondX) * aspectRatio;
        const secondDeltaY = vertical - secondY;
        const firstGlow = Math.exp(
            -(firstDeltaX * firstDeltaX + firstDeltaY * firstDeltaY) / 0.1,
        );
        const secondGlow = Math.exp(
            -(secondDeltaX * secondDeltaX + secondDeltaY * secondDeltaY) / 0.12,
        );

        value = base + 0.42 * firstGlow + 0.34 * secondGlow;
    } else if (animation === 'pulse') {
        const deltaX = (horizontal - 0.5) * aspectRatio;
        const deltaY = vertical - 0.52;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        const radius = 0.22 + 0.1 * (Math.sin(seconds * 0.9) * 0.5 + 0.5);
        const ring = Math.exp(-(((distance - radius) / 0.09) ** 2));
        const bloom = Math.exp(-((distance / (radius + 0.08)) ** 2));

        value = base + 0.38 * ring + 0.2 * bloom;
    } else {
        const centerX = 0.66 + 0.05 * Math.sin(seconds * 0.23);
        const centerY = 0.4 + 0.06 * Math.cos(seconds * 0.19);
        const deltaX = (horizontal - centerX) * aspectRatio;
        const deltaY = vertical - centerY;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        const radius = 0.36 + 0.03 * Math.sin(seconds * 0.31);
        const ring = Math.exp(-(((distance - radius) / 0.06) ** 2));
        const core = Math.exp(-((distance / 0.3) ** 2));
        const glowX = (horizontal - 0.18) * aspectRatio;
        const glowY = vertical - 0.22;
        const glow = Math.exp(-(glowX * glowX + glowY * glowY) / 0.1);
        const ripple =
            Math.sin(horizontal * 5 + vertical * 3 - seconds * 0.6) * 0.5 + 0.5;

        value =
            0.36 +
            0.42 * vertical +
            0.18 * glow +
            0.06 * ripple +
            0.32 * ring -
            0.4 * core;
    }

    return Math.min(1, Math.max(0, value));
}

/**
 * An animated halftone dot matrix drawn on a canvas. Cells grow and brighten
 * with the underlying field; reduced-motion users get a single still frame.
 */
export function DotMatrix({
    className,
    cellSize = 6,
    animation = 'orbit',
}: {
    className?: string;
    cellSize?: number;
    animation?: DotMatrixAnimation;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            return;
        }

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const levelColors = Array.from({ length: LEVELS }, (_, level) =>
            paletteColor(level / (LEVELS - 1)),
        );
        let width = 0;
        let height = 0;
        let animationFrame = 0;
        let lastPaintedAt = 0;

        const resize = () => {
            const bounds = canvas.getBoundingClientRect();
            const pixelRatio = Math.min(
                window.devicePixelRatio || 1,
                MAX_PIXEL_RATIO,
            );

            width = bounds.width;
            height = bounds.height;
            canvas.width = Math.round(width * pixelRatio);
            canvas.height = Math.round(height * pixelRatio);
            context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        };

        const paint = (timestamp: number) => {
            if (width === 0 || height === 0) {
                return;
            }

            const columns = Math.ceil(width / cellSize);
            const rows = Math.ceil(height / cellSize);
            const aspectRatio = width / height;
            const seconds = timestamp / 1000;
            const paths = Array.from({ length: LEVELS }, () => new Path2D());

            for (let row = 0; row < rows; row++) {
                const vertical = (row + 0.5) / rows;

                for (let column = 0; column < columns; column++) {
                    const value = brightnessAt(
                        (column + 0.5) / columns,
                        vertical,
                        aspectRatio,
                        seconds,
                        animation,
                    );
                    const size = cellSize * (0.22 + 0.68 * value);
                    const inset = (cellSize - size) / 2;

                    paths[Math.round(value * (LEVELS - 1))].rect(
                        column * cellSize + inset,
                        row * cellSize + inset,
                        size,
                        size,
                    );
                }
            }

            context.clearRect(0, 0, width, height);
            paths.forEach((path, level) => {
                context.fillStyle = levelColors[level];
                context.fill(path);
            });
        };

        const tick = (timestamp: number) => {
            animationFrame = requestAnimationFrame(tick);

            if (timestamp - lastPaintedAt < FRAME_INTERVAL) {
                return;
            }

            lastPaintedAt = timestamp;
            paint(timestamp);
        };

        const start = () => {
            cancelAnimationFrame(animationFrame);

            if (reducedMotion.matches) {
                paint(lastPaintedAt);

                return;
            }

            animationFrame = requestAnimationFrame(tick);
        };

        const resizeObserver = new ResizeObserver(() => {
            resize();
            paint(lastPaintedAt);
        });

        resize();
        resizeObserver.observe(canvas);
        reducedMotion.addEventListener('change', start);
        start();

        return () => {
            cancelAnimationFrame(animationFrame);
            resizeObserver.disconnect();
            reducedMotion.removeEventListener('change', start);
        };
    }, [animation, cellSize]);

    return (
        <canvas
            ref={canvasRef}
            aria-hidden="true"
            className={cn('block size-full', className)}
        />
    );
}
