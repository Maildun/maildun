---
paths:
  - resources/js/hooks/use-mobile.tsx
  - resources/js/hooks/use-media-upload.ts
  - resources/js/hooks/use-current-url.ts
---

# Hooks

## Keep SSR-safe useIsMobile hook
This app uses resources/js/hooks/use-mobile.tsx (useSyncExternalStore) so SSR and the first client paint match. shadcn apply/add may write a conflicting resources/js/hooks/use-mobile.ts that uses useState+useEffect and returns undefined on the server. Delete the generated .ts file and keep the .tsx hook. Imports stay `@/hooks/use-mobile`.

## Media uploads use toast.promise
Wrap the Inertia upload visit in a Promise and call toast.promise so one toast moves through loading → success/error. Do not also Inertia::flash a success toast on store — that would stack a second toast. Use title objects (not description-only strings) so ToastTitle renders.

## Current-url prefix matching is boundary-aware
isCurrentOrParentUrl / isCurrentPath must not use raw startsWith. `/acme` is not a parent of `/acmecorp`; match exact path or `${href}/` as a segment prefix. Logic lives in resources/js/lib/current-url.ts so the hook and settings/app nav stay consistent.
