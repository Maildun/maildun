<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="application-name" content="{{ config('app.name', 'Maildun') }}">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#171717" media="(prefers-color-scheme: dark)">

        <title>@yield('title', 'Error') · {{ config('app.name', 'Maildun') }}</title>

        <link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml" media="(prefers-color-scheme: light)">
        <link rel="icon" href="/assets/img/logo-white.svg" type="image/svg+xml" media="(prefers-color-scheme: dark)">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <style>
            :root {
                color-scheme: light;
                --error-background: #ffffff;
                --error-foreground: #171717;
                --error-muted: #737373;
                --error-primary: #171717;
                --error-primary-foreground: #ffffff;
                --error-focus: #2563eb;
                --grain-opacity: 0.18;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    color-scheme: dark;
                    --error-background: #171717;
                    --error-foreground: #fafafa;
                    --error-muted: #a3a3a3;
                    --error-primary: #fafafa;
                    --error-primary-foreground: #171717;
                    --error-focus: #93c5fd;
                    --grain-opacity: 0.28;
                }
            }

            * {
                box-sizing: border-box;
            }

            html,
            body {
                min-height: 100%;
            }

            body {
                align-items: center;
                background: var(--error-background);
                color: var(--error-foreground);
                display: flex;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                justify-content: center;
                margin: 0;
                min-height: 100vh;
                padding: 2rem 1.5rem;
                isolation: isolate;
                overflow: hidden;
                position: relative;
                text-rendering: optimizeLegibility;
            }

            .grainy::before {
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='grain'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23grain)'/%3E%3C/svg%3E");
                background-repeat: repeat;
                background-size: 180px 180px;
                content: '';
                inset: 0;
                opacity: var(--grain-opacity);
                pointer-events: none;
                position: absolute;
                z-index: 0;
            }

            main {
                max-width: 32rem;
                position: relative;
                text-align: center;
                width: 100%;
                z-index: 1;
            }

            .error-code {
                color: var(--error-muted);
                font-size: clamp(4rem, 16vw, 7rem);
                font-weight: 700;
                letter-spacing: -0.08em;
                line-height: 1;
                margin: 0;
            }

            .error-message {
                font-size: clamp(1.125rem, 3vw, 1.5rem);
                font-weight: 500;
                line-height: 1.4;
                margin: 1rem 0 0;
            }

            .error-action {
                align-items: center;
                background: var(--error-primary);
                border-radius: 0.625rem;
                color: var(--error-primary-foreground);
                display: inline-flex;
                font-size: 0.9375rem;
                font-weight: 600;
                gap: 0.5rem;
                justify-content: center;
                margin-top: 2rem;
                padding: 0.75rem 1rem;
                text-decoration: none;
            }

            .error-action:focus-visible {
                outline: 2px solid var(--error-focus);
                outline-offset: 3px;
            }

            .error-action svg {
                height: 1rem;
                width: 1rem;
            }

            @media (prefers-reduced-motion: no-preference) {
                .error-action {
                    transition: opacity 150ms ease, transform 150ms ease;
                }

                .error-action:hover {
                    opacity: 0.88;
                    transform: translateY(-1px);
                }
            }
        </style>
    </head>
    <body class="grainy">
        <main role="main" aria-labelledby="error-message">
            <p class="error-code">@yield('code')</p>
            <h1 id="error-message" class="error-message">@yield('message')</h1>

            @auth
                <a class="error-action" href="{{ url('/') }}">
                    <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    <span>Back to dashboard</span>
                </a>
            @endauth
        </main>
    </body>
</html>
