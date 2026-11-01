---
paths:
  - components.json
  - vite.config.ts
  - '{package.json,pnpm-lock.yaml}'
---

# General

## shadcn style is base-vega
The project uses shadcn preset bIkfFpI: style vega, Base UI (`base-vega`), hugeicons, Inter, default radius, subtle menu, neutral theme. Do not switch back to nova or radix. Custom tokens in resources/css/app.css (--success, --info, --warning) and Laravel @source paths must be kept when CSS is regenerated.

## Hugeicons style selection stays build-time
Keep the public default on free-stroke-rounded. Vite aliases the free core package to an allowlisted Pro package only when HUGEICONS_ICON_STYLE selects it; do not add @hugeicons-pro packages or registry credentials to the public manifest, lockfile, or project .npmrc. Licensed deployments install their chosen Pro package privately and rebuild.

## Keep editor toolchain on compatible majors
Keep TypeScript on 5.x while typescript-eslint 8 declares <6.1; TypeScript 6 also rejects the current baseUrl config without a deprecation override. ESLint is on 10 with eslint-plugin-import-x and @eslint-react, because eslint-plugin-import and eslint-plugin-react do not support ESLint 10 and pull in minimatch 3. Do not add those two back.

## shadcn CLI is not a dependency
Run the CLI with `pnpm dlx shadcn@latest`. Its Tailwind variants live in resources/css/shadcn.css, vendored from the shadcn package. If an add/apply run puts `@import 'shadcn/tailwind.css'` back into app.css or adds shadcn to package.json, revert both and refresh shadcn.css from the new release instead.
