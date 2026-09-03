# Third-party notices

Maildun's own work in this repository is licensed under the [GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-only). Dependencies and third-party assets remain subject to their own licenses and terms.

The AGPL governs the combined work. Permissively licensed dependencies (MIT, BSD, Apache-2.0, and similar) are compatible with AGPL-3.0 and keep their own notices; incorporating them does not relicense them, and their copyright holders retain their rights.

## Dependency manifests

The authoritative dependency lists are `composer.lock` and `pnpm-lock.yaml`. Their packages include MIT, BSD, Apache-2.0, Creative Commons, and other permissive licenses. Redis, databases, mail providers, and infrastructure installed separately are not distributed by this repository.

Review dependency licenses as part of every release:

```bash
composer licenses
pnpm licenses list
```

The sections below cover assets and vendored code that a manifest scan does not surface on its own: fonts and artwork emitted into the build, path data copied into the source tree, third-party marks, and data files the application reads at runtime.

## Fonts

Both families are self-hosted: they are written into `public/build` and served from this application, so they are redistributed with any deployment.

| Font | Source | License |
| --- | --- | --- |
| Inter | `@fontsource-variable/inter`, imported by `resources/css/app.css` | SIL Open Font License 1.1 |
| Instrument Sans | Fetched at build time from Bunny Fonts by the Laravel Vite plugin (`vite.config.ts`) | SIL Open Font License 1.1 |

## Icons

Hugeicons supplies the interface icon set. `@hugeicons/core-free-icons` and `@hugeicons/react` are MIT licensed and are the public default. `HUGEICONS_ICON_STYLE` can alias the free package to a Hugeicons Pro package at build time; Pro packages are never installed, committed, or distributed by this repository, and using one requires your own Hugeicons license.

Material Design icons reach the application through `@mui/icons-material` (MIT) as part of the vendored email builder; they are not used elsewhere in the interface.

## Simple Icons brand glyphs

`resources/js/lib/brand-icon-paths.ts` contains path data copied from the [Simple Icons](https://simpleicons.org) project, released under CC0 1.0 Universal. The glyphs themselves are trademarks of their owners and are used only to label mail clients and browsers in campaign reports:

| Glyph | Mark holder |
| --- | --- |
| Firefox | Mozilla Foundation |
| Gmail | Google LLC |
| Thunderbird | MZLA Technologies Corporation |

Their presence does not imply endorsement, and they are not covered by Maildun's AGPL-3.0 license.

## Country flags

Flag artwork comes from the [flag-icons](https://github.com/lipis/flag-icons) project, MIT licensed, Copyright (c) 2013 Panayiotis Lipiridis. `resources/js/lib/country-flags.ts` resolves flags from the package and Vite emits each SVG as a standalone asset into the build output, so the flags are redistributed with the application.

## Map data

`resources/js/lib/world-map-paths.ts` is derived from Natural Earth 1:110m Admin 0 country and tiny-country data. [Natural Earth data is public domain](https://www.naturalearthdata.com/about/terms-of-use/). Natural Earth requests, but does not require, a courtesy credit.

## IP geolocation databases

Campaign insights derive country, city, and ASN from local DB-IP Lite `.mmdb` files. **These databases are not included in this repository**; operators download them separately from [db-ip.com](https://db-ip.com) and are bound by DB-IP's terms for the edition they obtain. The DB-IP Lite databases are distributed under Creative Commons Attribution 4.0 International, which requires visible attribution.

Maildun surfaces that credit in the campaign insights payload (`app/Actions/Emails/BuildCampaignInsights.php`). Keep the DB-IP Lite attribution visible in any interface you build on top of this data.

The databases are read with `maxmind-db/reader` (Apache-2.0).

## DiceBear avatar styles

Maildun generates local fallback avatars using DiceBear. The application currently uses these styles:

| Style | Creator | License |
| --- | --- | --- |
| Critters | DiceBear | CC0 1.0 |
| Loops | DiceBear | CC0 1.0 |
| Shape Grid | DiceBear | CC0 1.0 |
| Micah | Micah Lanier | CC BY 4.0 |

DiceBear and the individual avatar styles retain their respective rights. See the `dicebear/styles` package for complete license and source details.

## Waypoint email builder

The email editor uses packages published by Waypoint (Metaccountant, Inc.) under the MIT License, and also vendors Waypoint source code into `resources/js/email-builder/`. That directory keeps its own `LICENSE` file — MIT License, Copyright (c) 2024 Waypoint (Metaccountant, Inc.) — which must stay with the source. Copyright remains with Waypoint and its contributors.

The vendored editor brings its own dependencies, notably MUI (`@mui/material`, `@mui/icons-material`, MIT), Emotion (MIT) as MUI's style engine, and highlight.js (BSD-3-Clause).

## shadcn/ui components

`resources/js/components/ui` holds components generated by the shadcn CLI (MIT). They are source you copy rather than a runtime dependency, and they remain MIT licensed at their origin. They build on Base UI (MIT) and Tailwind CSS (MIT).

## Maildun's own marks

For the avoidance of doubt, the logo files in `public/assets/img/` (`logo.svg`, `logo-white.svg`, `logo-landscape.svg`, `logo-landscape-white.svg`) are Maildun's own artwork, not third-party assets. The name "Maildun" and the Maildun logo are covered by term 3 of the additional terms in [LICENSE](LICENSE): the AGPL grants no rights under trademark law to them, and modified versions conveyed or offered to the public must be offered under a different name.

That restriction does not touch the "Powered by Maildun" attribution notice required by term 1, and it does not prevent accurate statements that a work is derived from Maildun.

## GitHub marks

Files whose names begin with `GitHub_` contain GitHub marks. GitHub and the Invertocat logo are trademarks of GitHub, Inc. Their presence does not imply endorsement, and their use is subject to GitHub's logo and trademark guidelines. They are not covered by Maildun's AGPL-3.0 license.
