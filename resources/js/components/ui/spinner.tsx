import { cn } from '@/lib/utils';

const trackClassName =
    'fill-none stroke-black/20 in-data-[slot=button]:stroke-current in-data-[slot=button]:opacity-40 dark:stroke-white/35 dark:in-data-[slot=button]:stroke-current';

function Spinner({ className, ...props }: React.ComponentProps<'svg'>) {
    return (
        <svg
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            data-slot="spinner"
            role="status"
            aria-label="Loading"
            className={cn('size-4', className)}
            {...props}
        >
            <circle
                cx="12"
                cy="12"
                r="7.2"
                strokeWidth="2.35"
                strokeLinecap="round"
                strokeDasharray="0.6 5.05"
                className={trackClassName}
            />
            <g className="origin-[12px_12px] [transform-box:view-box] animate-spin [animation-duration:0.75s] motion-reduce:animate-none">
                <path
                    d="M 19.15 12.25 A 7.15 7.15 0 0 1 15.13 18.43"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.7"
                    strokeLinecap="round"
                />
            </g>
        </svg>
    );
}

export { Spinner };
