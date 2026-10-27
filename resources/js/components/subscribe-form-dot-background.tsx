import { Mesh, Program, Renderer, Triangle } from 'ogl';
import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

/** Mark drawn in every matrix cell. */
export type DotBackgroundShape = 'square' | 'round' | 'cross';

/** How the animated field is sampled and drawn. */
export type DotBackgroundPattern = {
    /** Edge length of one matrix cell, in CSS pixels. */
    cellSize: number;
    /** Noise features across the height of the field; higher is busier. */
    scale: number;
    /** Multiplier on the animation clock. */
    speed: number;
    /** Exponent applied to the field; higher leaves fewer, brighter peaks. */
    contrast: number;
    shape: DotBackgroundShape;
    /** Strength (0-1) of a light that circles the middle of the field. */
    orbit?: number;
    /** Strength (0-1) of a soft, unmasked wash of color behind the marks. */
    haze?: number;
};

/** Four `#rrggbb` colors, from the darkest to the brightest part of the field. */
export type DotBackgroundPalette = readonly [string, string, string, string];

type Rgb = [number, number, number];

type DotBackgroundSettings = Required<DotBackgroundPattern> & {
    colors: [Rgb, Rgb, Rgb, Rgb];
};

type DotBackgroundScene = {
    configure: (settings: DotBackgroundSettings) => void;
};

/** About 30 frames a second, with slack so a 30 Hz display is not halved. */
const MIN_FRAME_GAP = 1000 / 30 - 4;
/** Longest clock step after a stalled frame, so motion never jumps. */
const MAX_FRAME_STEP = 100;
const MAX_PIXEL_RATIO = 2;
/** Caps the drawing buffer so a full-screen cover stays cheap to shade. */
const MAX_BUFFER_PIXELS = 2_500_000;
/** Starting clock, so the first and the reduced-motion frame are mid-flow. */
const STILL_FRAME_TIME = 23.7;

const shapeIndexes: Record<DotBackgroundShape, number> = {
    square: 0,
    round: 1,
    cross: 2,
};

const VERTEX_SHADER = /* glsl */ `
attribute vec2 position;

void main() {
    gl_Position = vec4(position, 0.0, 1.0);
}
`;

/**
 * Every pixel finds its matrix cell, samples a drifting value-noise field at
 * the cell center, and draws one anti-aliased mark sized and colored by that
 * level. The lattice hash wraps every 256 cells, so the drift offsets can wrap
 * too and the clock never runs out of float precision.
 */
const FRAGMENT_SHADER = /* glsl */ `
#ifdef GL_FRAGMENT_PRECISION_HIGH
precision highp float;
#else
precision mediump float;
#endif

uniform vec2 uBuffer;
uniform float uPixelRatio;
uniform float uTime;
uniform float uCellSize;
uniform float uScale;
uniform float uContrast;
uniform float uShape;
uniform float uOrbit;
uniform float uHaze;
uniform vec3 uColor0;
uniform vec3 uColor1;
uniform vec3 uColor2;
uniform vec3 uColor3;

const float LATTICE_PERIOD = 256.0;
const float MIN_CELLS_PER_SIDE = 16.0;
// Turns each octave about half a radian so value-noise lattice lines never align.
const mat2 OCTAVE_TURN = mat2(0.878, 0.479, -0.479, 0.878);

// The sine argument stays below 60 so every GPU evaluates it precisely.
float latticeHash(vec2 lattice) {
    float seed = dot(mod(lattice, LATTICE_PERIOD), vec2(0.0743, 0.1597));

    return fract(sin(seed) * 31871.3 + sin(seed * 1.913) * 1739.37);
}

float valueNoise(vec2 point) {
    vec2 lattice = floor(point);
    vec2 offset = fract(point);
    vec2 blend = offset * offset * (3.0 - 2.0 * offset);
    float lower = mix(
        latticeHash(lattice),
        latticeHash(lattice + vec2(1.0, 0.0)),
        blend.x
    );
    float upper = mix(
        latticeHash(lattice + vec2(0.0, 1.0)),
        latticeHash(lattice + vec2(1.0, 1.0)),
        blend.x
    );

    return mix(lower, upper, blend.y);
}

float flowField(vec2 point, float time) {
    vec2 bend = vec2(
        sin(point.y * 1.3 + time * 0.31),
        cos(point.x * 1.1 - time * 0.27)
    ) * 0.45;
    vec2 warped = point + bend;
    vec2 turned = OCTAVE_TURN * warped;
    float total = 0.5 * valueNoise(
        warped + mod(vec2(0.11, 0.07) * time, LATTICE_PERIOD)
    );

    total += 0.3 * valueNoise(
        turned * 2.1 + mod(vec2(-0.17, 0.13) * time, LATTICE_PERIOD) + 7.3
    );
    total += 0.2 * valueNoise(
        OCTAVE_TURN * turned * 4.3
            + mod(vec2(0.23, -0.19) * time, LATTICE_PERIOD)
            + 13.9
    );

    return total;
}

float fieldLevel(vec2 pixel, vec2 viewport, float time) {
    vec2 point = pixel / viewport.y;
    float level = smoothstep(0.2, 0.8, flowField(point * uScale, time));

    level = pow(level, uContrast);
    level *= 0.7 + 0.3 * point.y;

    if (uOrbit > 0.0) {
        float aspect = viewport.x / viewport.y;
        vec2 middle = vec2(0.5 * aspect, 0.5);
        float radius = 0.3 * min(aspect, 1.0);
        vec2 lamp = middle + radius * vec2(cos(time * 0.45), sin(time * 0.45));
        vec2 toLamp = point - lamp;
        float lampGlow = exp(-dot(toLamp, toLamp) / 0.03);
        float trackOffset = (length(point - middle) - radius) / 0.02;
        float track = exp(-trackOffset * trackOffset);

        level = max(level, uOrbit * max(lampGlow, 0.35 * track));
    }

    return clamp(level, 0.0, 1.0);
}

vec3 paletteAt(float level) {
    float scaled = clamp(level, 0.0, 1.0) * 3.0;
    vec3 color = mix(uColor0, uColor1, clamp(scaled, 0.0, 1.0));

    color = mix(color, uColor2, clamp(scaled - 1.0, 0.0, 1.0));

    return mix(color, uColor3, clamp(scaled - 2.0, 0.0, 1.0));
}

float markDistance(vec2 local, float level, float pixelInCell) {
    if (uShape < 0.5) {
        return max(abs(local.x), abs(local.y)) - mix(0.14, 0.44, level);
    }

    if (uShape < 1.5) {
        return length(local) - mix(0.12, 0.47, level);
    }

    vec2 folded = abs(local);
    float arm = mix(0.1, 0.46, level);
    float stroke = max(0.07, 0.6 * pixelInCell);
    float across = max(folded.x - arm, folded.y - stroke);
    float down = max(folded.x - stroke, folded.y - arm);

    return min(across, down);
}

void main() {
    vec2 viewport = uBuffer / uPixelRatio;
    vec2 pixel = vec2(gl_FragCoord.x, uBuffer.y - gl_FragCoord.y) / uPixelRatio;
    float cellSize = max(
        2.0,
        min(uCellSize, floor(min(viewport.x, viewport.y) / MIN_CELLS_PER_SIDE))
    );
    vec2 cell = floor(pixel / cellSize);
    vec2 local = pixel / cellSize - cell - 0.5;
    float level = fieldLevel((cell + 0.5) * cellSize, viewport, uTime);
    float pixelInCell = 1.0 / (cellSize * uPixelRatio);
    float edge = markDistance(local, level, pixelInCell);
    float coverage = 1.0 - smoothstep(-0.5 * pixelInCell, 0.5 * pixelInCell, edge);
    float markAlpha = coverage * (0.3 + 0.7 * level);
    vec3 color = paletteAt(level) * markAlpha;
    float alpha = markAlpha;

    if (uHaze > 0.0) {
        float wash = fieldLevel(pixel, viewport, uTime);
        float washAlpha = uHaze * wash * (1.0 - markAlpha);

        color += paletteAt(wash) * washAlpha;
        alpha += washAlpha;
    }

    gl_FragColor = vec4(color, alpha);
}
`;

function hexToRgb(hex: string): Rgb {
    const value = Number.parseInt(hex.replace('#', ''), 16);

    return [
        ((value >> 16) & 255) / 255,
        ((value >> 8) & 255) / 255,
        (value & 255) / 255,
    ];
}

/**
 * An animated dot-matrix field drawn with WebGL behind subscribe form
 * artwork. It fills its closest positioned ancestor and stays transparent
 * between marks, so the caller's background shows through. Reduced-motion
 * users get a single still frame, and the loop pauses while off screen.
 * Without WebGL nothing is drawn and the caller's background stands alone.
 */
export function SubscribeFormDotBackground({
    pattern,
    palette,
    className,
}: {
    pattern: DotBackgroundPattern;
    palette: DotBackgroundPalette;
    className?: string;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const sceneRef = useRef<DotBackgroundScene | null>(null);
    const {
        cellSize,
        scale,
        speed,
        contrast,
        shape,
        orbit = 0,
        haze = 0,
    } = pattern;
    const [darkest, dark, light, brightest] = palette;

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        let renderer: Renderer;

        try {
            renderer = new Renderer({
                alpha: true,
                premultipliedAlpha: true,
                depth: false,
                powerPreference: 'low-power',
            });
        } catch {
            return;
        }

        const gl = renderer.gl;
        const canvas = gl.canvas;
        const geometry = new Triangle(gl);
        const program = new Program(gl, {
            vertex: VERTEX_SHADER,
            fragment: FRAGMENT_SHADER,
            uniforms: {
                uBuffer: { value: [1, 1] },
                uPixelRatio: { value: 1 },
                uTime: { value: STILL_FRAME_TIME },
                uCellSize: { value: 1 },
                uScale: { value: 1 },
                uContrast: { value: 1 },
                uShape: { value: 0 },
                uOrbit: { value: 0 },
                uHaze: { value: 0 },
                uColor0: { value: [0, 0, 0] },
                uColor1: { value: [0, 0, 0] },
                uColor2: { value: [0, 0, 0] },
                uColor3: { value: [0, 0, 0] },
            },
            depthTest: false,
            depthWrite: false,
        });
        const mesh = new Mesh(gl, { geometry, program });

        const dispose = () => {
            geometry.remove();
            program.remove();
            gl.getExtension('WEBGL_lose_context')?.loseContext();
            canvas.remove();
        };

        if (!gl.getProgramParameter(program.program, gl.LINK_STATUS)) {
            dispose();

            return;
        }

        Object.assign(canvas.style, {
            position: 'absolute',
            inset: '0',
            display: 'block',
        });
        container.appendChild(canvas);

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const motion = { speed: 1 };
        let animationFrame = 0;
        let lastFrameAt = 0;
        let clock = STILL_FRAME_TIME;
        let configured = false;
        let onScreen = true;
        let contextLost = false;

        const draw = () => {
            if (
                !configured ||
                contextLost ||
                renderer.width === 0 ||
                renderer.height === 0
            ) {
                return;
            }

            program.uniforms.uTime.value = clock;
            renderer.render({ scene: mesh });
        };

        const resize = () => {
            const { width, height } = container.getBoundingClientRect();
            const pixelRatio = Math.min(
                window.devicePixelRatio || 1,
                MAX_PIXEL_RATIO,
                Math.sqrt(MAX_BUFFER_PIXELS / Math.max(width * height, 1)),
            );

            renderer.dpr = pixelRatio;
            renderer.setSize(width, height);
            program.uniforms.uBuffer.value = [
                gl.drawingBufferWidth,
                gl.drawingBufferHeight,
            ];
            program.uniforms.uPixelRatio.value = pixelRatio;
        };

        const stop = () => {
            cancelAnimationFrame(animationFrame);
            animationFrame = 0;
            lastFrameAt = 0;
        };

        const tick = (timestamp: number) => {
            animationFrame = requestAnimationFrame(tick);

            if (lastFrameAt !== 0 && timestamp - lastFrameAt < MIN_FRAME_GAP) {
                return;
            }

            const elapsed =
                lastFrameAt === 0
                    ? 0
                    : Math.min(timestamp - lastFrameAt, MAX_FRAME_STEP);

            lastFrameAt = timestamp;
            clock += (elapsed / 1000) * motion.speed;
            draw();
        };

        const start = () => {
            stop();

            if (!onScreen || contextLost) {
                return;
            }

            if (reducedMotion.matches) {
                draw();

                return;
            }

            animationFrame = requestAnimationFrame(tick);
        };

        const resizeObserver = new ResizeObserver(() => {
            resize();
            draw();
        });
        const intersectionObserver = new IntersectionObserver((entries) => {
            onScreen = entries[entries.length - 1].isIntersecting;
            start();
        });
        const handleContextLost = () => {
            contextLost = true;
            stop();
        };

        sceneRef.current = {
            configure: (settings) => {
                const uniforms = program.uniforms;

                motion.speed = settings.speed;
                uniforms.uCellSize.value = settings.cellSize;
                uniforms.uScale.value = settings.scale;
                uniforms.uContrast.value = settings.contrast;
                uniforms.uShape.value = shapeIndexes[settings.shape];
                uniforms.uOrbit.value = settings.orbit;
                uniforms.uHaze.value = settings.haze;
                settings.colors.forEach((color, index) => {
                    uniforms[`uColor${index}`].value = color;
                });
                configured = true;

                if (animationFrame === 0 && onScreen) {
                    draw();
                }
            },
        };

        resize();
        resizeObserver.observe(container);
        intersectionObserver.observe(container);
        reducedMotion.addEventListener('change', start);
        canvas.addEventListener('webglcontextlost', handleContextLost);
        start();

        return () => {
            stop();
            sceneRef.current = null;
            resizeObserver.disconnect();
            intersectionObserver.disconnect();
            reducedMotion.removeEventListener('change', start);
            canvas.removeEventListener('webglcontextlost', handleContextLost);
            dispose();
        };
    }, []);

    useEffect(() => {
        sceneRef.current?.configure({
            cellSize,
            scale,
            speed,
            contrast,
            shape,
            orbit,
            haze,
            colors: [
                hexToRgb(darkest),
                hexToRgb(dark),
                hexToRgb(light),
                hexToRgb(brightest),
            ],
        });
    }, [
        cellSize,
        scale,
        speed,
        contrast,
        shape,
        orbit,
        haze,
        darkest,
        dark,
        light,
        brightest,
    ]);

    return (
        <div
            ref={containerRef}
            aria-hidden="true"
            data-dot-background=""
            className={cn(
                'pointer-events-none absolute inset-0 overflow-hidden',
                className,
            )}
        />
    );
}
