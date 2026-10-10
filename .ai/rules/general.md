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

## Hugeicons imports are rewritten per icon at build time
`hugeiconsPerIconImports()` in vite.config.ts (build only, dev keeps the barrel) rewrites `import { FooIcon } from '@hugeicons/core-free-icons'` in app code and in `@maildun/email-builder`'s dist into one default import per icon file, using the map in the package's `dist/esm/index.js`. Without it every build parses all ~6,000 icons of each copy (16k modules instead of a few thousand), which is what pushes small VPS and Docker builds out of memory. Keep icon imports as named imports from the package root; other forms still build but skip the rewrite. A build error "has no icon named" means the icon does not exist in the selected style's package.

## Keep editor toolchain on compatible majors
Keep TypeScript on 5.x while typescript-eslint 8 declares <6.1; TypeScript 6 also rejects the current baseUrl config without a deprecation override. Keep ESLint on 9.x while eslint-plugin-import/react do not support ESLint 10.
