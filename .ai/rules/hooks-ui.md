---
paths:
  - 'resources/js/{hooks/use-media-upload.ts,hooks/use-upload-toast.ts,components/ui/toast.tsx}'
---

# Hooks Ui

## Upload progress toasts update one manager item
Use useUploadToast only when a file transfer starts. Create a manager-generated persistent toast, update its data.progress from Inertia onProgress, then update or dismiss that same toast on completion; normal non-file saves must not start it. This supersedes toast.promise for media because the promise API does not expose the toast id needed for determinate progress.
