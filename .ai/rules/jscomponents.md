---
paths:
  - 'resources/js/{components/campaign-insights*.tsx,lib/world-map-paths.ts}'
  - 'resources/js/{components/country-flag.tsx,lib/country-flags.ts,components/campaign-insights*.tsx}'
  - 'resources/js/{components/client-icon.tsx,lib/brand-icon-paths.ts,components/campaign-insights*.tsx}'
---

# Jscomponents

## Keep campaign geography maps local and human-only
Campaign insight maps use the checked-in public-domain Natural Earth SVG paths keyed by ISO alpha-2 country codes. Shade only confidently classified human metrics, keep tiny-country points for markets such as Singapore, and do not add a runtime map dependency for this report.

## Country flags come from flag-icons SVG assets, not emoji
Country flags render through `<CountryFlag code="ID" />` (resources/js/components/country-flag.tsx), which resolves an SVG URL from the MIT-licensed flag-icons package. Do not go back to regional-indicator emoji — they do not render on Windows/Chrome.

`resources/js/lib/country-flags.ts` builds the URL map with `import.meta.glob('/node_modules/flag-icons/flags/4x3/*.svg', { query: '?url', eager: true })`, keyed by uppercase basename. Never import `flag-icons/css/flag-icons.min.css`: it pulls in both 1x1 and 4x3 sets and base64-inlines ~200 small flags into the CSS bundle. `build.assetsInlineLimit` in vite.config.ts returns false for `flag-icons/flags/` so every flag stays a standalone asset and only the rendered ones are fetched. Unknown or non-alpha-2 codes fall back to the Globe02Icon.

## Insight bars ease width, they do not reset
Traffic quality, Geography, and Technology bars use InsightBar with transition-[width] duration-300 ease-out so Opens/Clicks interpolates. Do not grow from zero (starting:w-0), stagger delays, or fade/slide TabsContent — that motion fights the sliding tab pill. Honor motion-reduce.

## Geography and technology cards share a stretched height
Campaign insights Geography and Technology cards sit in `lg:grid-cols-2 lg:items-stretch`. Both Tabs+Card stacks are `h-full min-h-[24rem]` with `CardContent` `flex min-h-0 flex-1 flex-col` so lists and the map fill the same column height. Do not put `items-start` back on that grid.

## Privacy-safe tracking is a three-fact card
The campaign insights privacy notice is a Card of three facts (no raw IPs, retention, aggregates remain), each with a Hugeicons mark and an InformationCircleIcon tooltip. Do not collapse it back to a single Alert paragraph.

## Client rows use ClientIcon, with vendored CC0 brand glyphs
Client rows in the technology card render `<ClientIcon label={row.label} />`, which keyword-matches the closed label list produced by ClassifyEmailTrackingEvent (Chrome, Firefox, Safari, Edge, Outlook, Apple Mail, Thunderbird, the four image proxies, the scanner labels). First match wins, so order the keyword list from specific to generic.

Hugeicons free covers Chrome, Safari, Apple, Microsoft, Mail01, Shield01 and Robot01. Firefox, Thunderbird, and Gmail have no Hugeicons mark, so their Simple Icons glyphs (CC0 1.0) are vendored as path data in resources/js/lib/brand-icon-paths.ts — do not add the simple-icons package for three glyphs. Edge, Outlook, and Yahoo have no CC0 mark at all (Simple Icons removed them); they intentionally use MicrosoftIcon / Mail01Icon / the generic BrowserIcon. Vendored glyphs are solid so they render at size-3.5 against the size-4 stroke icons.
