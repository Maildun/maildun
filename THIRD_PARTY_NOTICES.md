# Third-party notices

Maildun's own work in this repository is licensed under the [GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-only), with the additional terms stated at the top of LICENSE. Dependencies and third-party assets remain subject to their own licenses and terms, and so does everything listed below. A commercial license from Maildun's copyright holder never covers them.

The AGPL governs the combined work. Permissively licensed dependencies (MIT, BSD, Apache-2.0, and similar) are compatible with AGPL-3.0 and keep their own notices; incorporating them does not relicense them, and their copyright holders retain their rights.

## Dependency manifests

The authoritative dependency lists are `composer.lock` and `pnpm-lock.yaml`. Their packages include MIT, BSD, Apache-2.0, Creative Commons, and other permissive licenses. Redis, databases, mail providers, and infrastructure installed separately are not distributed by this repository.

PHP packages ship with their own license and NOTICE files under `vendor/`. The frontend build bundles JavaScript and CSS packages into `public/build`, which strips their license comments, so the build also writes `public/build/third-party-licenses.txt`, listing the license of every bundled package. The About page links to it.

Review dependency licenses as part of every release:

```bash
composer licenses
pnpm licenses list
```

The sections below cover assets and vendored code that a manifest scan does not surface on its own: fonts and artwork emitted into the build, code copied into the source tree, third-party marks, and data files the application reads at runtime.

## Docker image

The published image (`ghcr.io/maildun/maildun`) is built on `dunglas/frankenphp`, which is based on Debian. It therefore contains Debian packages under their own licenses, including GPL and LGPL software such as bash, coreutils, and the GNU C Library. Their copyright and license files are kept under `/usr/share/doc`. Their source code is available from the Debian archive at [sources.debian.org](https://sources.debian.org) and, for any past release, [snapshot.debian.org](https://snapshot.debian.org).

The image also contains PHP (PHP License 3.01), FrankenPHP and Caddy (MIT and Apache-2.0), the PHP extensions installed by the Dockerfile (each under its own license), and the Node.js runtime (MIT; its license is copied to `/usr/local/share/doc/node/LICENSE`).

## Fonts

| Font | Copyright | Source | License |
| --- | --- | --- | --- |
| Inter | Copyright 2016 The Inter Project Authors (https://github.com/rsms/inter) | `@fontsource-variable/inter`, imported by `resources/css/app.css` | SIL Open Font License 1.1 |
| Instrument Sans | Copyright 2022 The Instrument Sans Project Authors (https://github.com/Instrument/instrument-sans) | Fetched at build time from Bunny Fonts by the Laravel Vite plugin (`vite.config.ts`) | SIL Open Font License 1.1 |
| Familjen Grotesk | Copyright 2021 The Familjen Grotesk Project Authors (https://github.com/Familjen-Sthlm/Familjen-Grotesk) | `@fontsource-variable/familjen-grotesk`, imported by `resources/css/app.css` (headings) | SIL Open Font License 1.1 |

Inter, Familjen Grotesk, and the application's copy of Instrument Sans are written into `public/build` and served from this application, so they are redistributed with any deployment. The MCP authorization page (`resources/views/mcp/authorize.blade.php`) is the exception: it loads Instrument Sans from Bunny Fonts directly. The full license text is under [License texts](#sil-open-font-license-11).

## Icons

Hugeicons supplies the interface icon set. `@hugeicons/core-free-icons` and `@hugeicons/react` are MIT licensed and are the public default.

`HUGEICONS_ICON_STYLE` can alias the free package to a Hugeicons Pro package at build time. Pro packages are never installed, committed, or distributed by this repository, and using one requires your own Hugeicons license. LICENSE grants an additional permission so that a Pro package need not be part of Maildun's Corresponding Source. Hugeicons' own terms still apply, and they forbid sharing Pro icon files with anyone who has not licensed them. Do not publish or hand over a Pro build to people outside your license.

## Simple Icons brand glyphs

`resources/js/lib/brand-icon-paths.ts` contains path data copied from the [Simple Icons](https://simpleicons.org) project, released under CC0 1.0 Universal. The glyphs themselves are trademarks of their owners and are used only to label mail clients and browsers in campaign reports:

| Glyph | Mark holder |
| --- | --- |
| Firefox | Mozilla Foundation |
| Gmail | Google LLC |
| Thunderbird | MZLA Technologies Corporation |

Their presence does not imply endorsement, and they are not covered by Maildun's AGPL-3.0 license.

## Other third-party marks

The interface shows these marks to name the product or service they stand for. Their presence does not imply endorsement, and they remain trademarks of their owners:

| Mark | Mark holder | Where |
| --- | --- | --- |
| Google "G" | Google LLC | "Continue with Google" button (`resources/js/components/icons/google-icon.tsx`), drawn to Google's sign-in branding guidelines |
| Google Gemini | Google LLC | Design assistant model picker (Hugeicons glyph) |
| Google Chrome | Google LLC | Campaign report client labels (Hugeicons glyph) |
| Apple, Safari | Apple Inc. | Campaign report client labels (Hugeicons glyphs) |
| Microsoft | Microsoft Corporation | Campaign report client labels (Hugeicons glyph) |
| ChatGPT | OpenAI | Design assistant model picker (Hugeicons glyph) |
| Claude | Anthropic PBC | Design assistant model picker (Hugeicons glyph) |
| GitHub | GitHub, Inc. | User menu link to the source code (Hugeicons glyph) |

Company logos on contact and company pages are not stored by Maildun. They are favicons loaded from Google's favicon service for the company's own domain, and they belong to those companies.

## Country flags

Flag artwork comes from the [flag-icons](https://github.com/lipis/flag-icons) project, MIT licensed, Copyright (c) 2013 Panayiotis Lipiridis. `resources/js/lib/country-flags.ts` resolves flags from the package and Vite emits each SVG as a standalone asset into the build output, so the flags are redistributed with the application.

## Map data

Campaign geography uses [MapLibre GL JS](https://github.com/maplibre/maplibre-gl-js), distributed under the BSD-3-Clause license, with [OpenFreeMap](https://openfreemap.org/) basemap tiles. OpenFreeMap's software is MIT licensed; its basemap includes OpenMapTiles and OpenStreetMap data. Keep the map's built-in OpenMapTiles and OpenStreetMap attribution visible. Public basemap tiles are fetched from OpenFreeMap; campaign engagement totals remain local to the browser.

`resources/js/lib/world-map-countries.geojson` and the fallback map's `resources/js/lib/world-map-paths.ts` are derived from Natural Earth 1:110m Admin 0 country and tiny-country data. [Natural Earth data is public domain](https://www.naturalearthdata.com/about/terms-of-use/). Natural Earth requests, but does not require, a courtesy credit.

## IP geolocation databases

Campaign insights derive country, city, and ASN from local DB-IP Lite `.mmdb` files. **These databases are not included in this repository**; operators download them separately from [db-ip.com](https://db-ip.com), or opt in to `emails:update-geolocation-databases` (`MAIL_TRACKING_GEOLOCATION_AUTO_UPDATE_ENABLED`), which fetches the Lite editions on their behalf, and are bound by DB-IP's terms for the edition they obtain. The DB-IP Lite databases are distributed under Creative Commons Attribution 4.0 International, which requires visible attribution.

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

DiceBear and the individual avatar styles retain their respective rights. See the `dicebear/styles` package for complete license and source details. Micah avatars carry their CC BY 4.0 credit in the SVG metadata; serve them unmodified so it stays attached.

## Email builder

The block editor and the email renderer are `@maildun/email-builder` (MIT), published from [Maildun/email-builder](https://github.com/Maildun/email-builder). Its own `THIRD_PARTY_NOTICES.md` lists the material it builds on, including the EmailBuilder.js (Waypoint, MIT) importer and font presets.

Earlier versions of this application vendored Waypoint's EmailBuilder.js editor into `resources/js/email-builder/`. That source was removed when the package was adopted. Designs saved in the EmailBuilder.js format are converted when they are opened.

## Laravel React starter kit

The application scaffolding — authentication pages and layouts, the appearance and sidebar hooks, `resources/js/components/ui/placeholder-pattern.tsx`, and the settings pages — began as the [Laravel React starter kit](https://github.com/laravel/react-starter-kit), MIT License, Copyright (c) Taylor Otwell. It has since been modified substantially, and the original notice applies to the portions that remain. The upstream repository declares the MIT License in its `composer.json` and README without shipping a LICENSE file; the standard text is under [License texts](#mit-license).

## shadcn/ui components

`resources/js/components/ui` holds components generated by the shadcn CLI, MIT License, Copyright (c) 2023 shadcn. They are source you copy rather than a runtime dependency, and they remain MIT licensed at their origin. They build on Base UI (MIT) and Tailwind CSS (MIT). The notice does not cover `animated-counter.tsx` (Rare UI, below) or `placeholder-pattern.tsx` (starter kit, above). The full license text is under [License texts](#mit-license).

## Rare UI animated counter

`resources/js/components/ui/animated-counter.tsx` is the "Animated counter" component from [Rare UI](https://rareui.com), MIT License, Copyright (c) 2026 Swami Malode. It was taken from [swamimalode07/rare-ui](https://github.com/swamimalode07/rare-ui) at commit `d716e0b`, while that project was under the plain MIT License, and the full notice is kept in the file header. Rare UI later moved to a license with Commons Clause and attribution conditions; do not update this file from newer upstream versions.

## Subscribe-form background artwork

The animated background presets (Matrix, Grid, Orbit, Glow) are drawn by Maildun's own `resources/js/components/subscribe-form-dot-background.tsx`, which renders through `ogl` (Unlicense). Earlier versions shipped an OriginKit dot-matrix component and a set of preset background images instead. Both were removed: the component's license was incompatible with Maildun's, and the images had no recorded source or license.

## Database backup package

`packages/abduns/laravel-db-backup` is a separate package by Maildun's copyright holder, licensed under the MIT License (see its `LICENSE`), not under Maildun's AGPL terms.

## Developer tooling

These files help AI coding agents work on the repository. They are not part of the application and are excluded from the Docker image:

- `.claude/skills`, `.github/skills`, `AGENTS.md`, `CLAUDE.md`, and `boost.json` contain guidelines and skills generated by [Laravel Boost](https://github.com/laravel/boost), MIT License, Copyright (c) Taylor Otwell.
- The `shadcn` and `migrate-radix-to-base` skills (see `skills-lock.json`) come from [shadcn/ui](https://github.com/shadcn-ui/ui), MIT License, Copyright (c) 2023 shadcn, including its logo images, which belong to shadcn.

## Maildun's own marks

The logo files in `public/assets/img/` (`logo.svg`, `logo-white.svg`) are Maildun's own artwork, not third-party assets. The name "Maildun" and the Maildun logo are covered by term 3 of the additional terms in [LICENSE](LICENSE): the AGPL grants no rights under trademark law to them. [TRADEMARKS.md](TRADEMARKS.md) explains how you may use them.

## License texts

### MIT License

The following copyright notices are each reproduced with the MIT permission notice below:

- Copyright (c) 2023 shadcn (shadcn/ui components and agent skills)
- Copyright (c) Taylor Otwell (Laravel React starter kit, Laravel Boost)
- Copyright (c) 2026 Swami Malode (Rare UI animated counter)

```text
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### SIL Open Font License 1.1

Applies to Inter, Instrument Sans, and Familjen Grotesk, under the copyright notices listed in [Fonts](#fonts).

```text
-----------------------------------------------------------
SIL OPEN FONT LICENSE Version 1.1 - 26 February 2007
-----------------------------------------------------------

PREAMBLE
The goals of the Open Font License (OFL) are to stimulate worldwide
development of collaborative font projects, to support the font creation
efforts of academic and linguistic communities, and to provide a free and
open framework in which fonts may be shared and improved in partnership
with others.

The OFL allows the licensed fonts to be used, studied, modified and
redistributed freely as long as they are not sold by themselves. The
fonts, including any derivative works, can be bundled, embedded,
redistributed and/or sold with any software provided that any reserved
names are not used by derivative works. The fonts and derivatives,
however, cannot be released under any other type of license. The
requirement for fonts to remain under this license does not apply
to any document created using the fonts or their derivatives.

DEFINITIONS
"Font Software" refers to the set of files released by the Copyright
Holder(s) under this license and clearly marked as such. This may
include source files, build scripts and documentation.

"Reserved Font Name" refers to any names specified as such after the
copyright statement(s).

"Original Version" refers to the collection of Font Software components as
distributed by the Copyright Holder(s).

"Modified Version" refers to any derivative made by adding to, deleting,
or substituting -- in part or in whole -- any of the components of the
Original Version, by changing formats or by porting the Font Software to a
new environment.

"Author" refers to any designer, engineer, programmer, technical
writer or other person who contributed to the Font Software.

PERMISSION & CONDITIONS
Permission is hereby granted, free of charge, to any person obtaining
a copy of the Font Software, to use, study, copy, merge, embed, modify,
redistribute, and sell modified and unmodified copies of the Font
Software, subject to the following conditions:

1) Neither the Font Software nor any of its individual components,
in Original or Modified Versions, may be sold by itself.

2) Original or Modified Versions of the Font Software may be bundled,
redistributed and/or sold with any software, provided that each copy
contains the above copyright notice and this license. These can be
included either as stand-alone text files, human-readable headers or
in the appropriate machine-readable metadata fields within text or
binary files as long as those fields can be easily viewed by the user.

3) No Modified Version of the Font Software may use the Reserved Font
Name(s) unless explicit written permission is granted by the corresponding
Copyright Holder. This restriction only applies to the primary font name as
presented to the users.

4) The name(s) of the Copyright Holder(s) or the Author(s) of the Font
Software shall not be used to promote, endorse or advertise any
Modified Version, except to acknowledge the contribution(s) of the
Copyright Holder(s) and the Author(s) or with their explicit written
permission.

5) The Font Software, modified or unmodified, in part or in whole,
must be distributed entirely under this license, and must not be
distributed under any other license. The requirement for fonts to
remain under this license does not apply to any document created
using the Font Software.

TERMINATION
This license becomes null and void if any of the above conditions are
not met.

DISCLAIMER
THE FONT SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO ANY WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT
OF COPYRIGHT, PATENT, TRADEMARK, OR OTHER RIGHT. IN NO EVENT SHALL THE
COPYRIGHT HOLDER BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
INCLUDING ANY GENERAL, SPECIAL, INDIRECT, INCIDENTAL, OR CONSEQUENTIAL
DAMAGES, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF THE USE OR INABILITY TO USE THE FONT SOFTWARE OR FROM
OTHER DEALINGS IN THE FONT SOFTWARE.
```
