import { useCallback, useRef } from 'react';
import type {
    ToastUploadFile,
    ToastUploadFileStatus,
} from '@/components/ui/toast';
import { toast } from '@/components/ui/toast';

type UploadToastMessage = {
    title: string;
    description?: string;
};

type UploadToastFile = {
    name: string;
    size?: number;
};

type UploadToastStart = UploadToastMessage & {
    /** Files in the transfer, listed row by row inside the toast. */
    files?: readonly UploadToastFile[];
    /** Small print under the progress bar, such as the upload limits. */
    hint?: string;
};

/** The parts of Inertia's progress event the upload toast reads. */
type UploadProgressEvent = {
    percentage?: number;
    loaded?: number;
    total?: number;
};

type UploadSession = {
    files: UploadToastFile[];
    states: ToastUploadFile[];
    hint?: string;
    progress: number;
};

export function firstUploadErrorMessage(
    errors: Record<string, unknown>,
    fallback: string,
    preferredField?: string,
): string {
    const preferredMessage = preferredField ? errors[preferredField] : null;
    const message =
        typeof preferredMessage === 'string'
            ? preferredMessage
            : Object.values(errors).find((value) => typeof value === 'string');

    return typeof message === 'string' ? message : fallback;
}

function normalizedPercentage(percentage: number | undefined): number | null {
    if (percentage === undefined || !Number.isFinite(percentage)) {
        return null;
    }

    return Math.min(100, Math.max(0, Math.round(percentage)));
}

function overallPercentage(
    event: UploadProgressEvent | undefined,
): number | null {
    if (event?.percentage !== undefined) {
        return normalizedPercentage(event.percentage);
    }

    if (event?.loaded !== undefined && event.total !== undefined) {
        return event.total > 0
            ? normalizedPercentage((event.loaded / event.total) * 100)
            : null;
    }

    return null;
}

/**
 * Spread the bytes already sent across the queued files. A multipart body is
 * written in file order, so the running byte total says which file the browser
 * is on. Request bytes are scaled back to payload bytes first, otherwise the
 * multipart boundaries push every file past its own size.
 *
 * A file that reaches 100% here is only 'sent': every file rides on the same
 * request, so none of them are stored until the server answers.
 */
function uploadFileStates(
    files: UploadToastFile[],
    progress: number,
    event: UploadProgressEvent | undefined,
): ToastUploadFile[] {
    const payloadBytes = files.reduce(
        (total, file) => total + (file.size ?? 0),
        0,
    );
    const requestBytes =
        event?.total !== undefined && event.total > 0 ? event.total : null;
    const loadedBytes =
        event?.loaded !== undefined && Number.isFinite(event.loaded)
            ? event.loaded
            : null;
    const sentBytes =
        loadedBytes === null
            ? null
            : requestBytes !== null && payloadBytes > 0
              ? (loadedBytes / requestBytes) * payloadBytes
              : loadedBytes;

    let consumedBytes = 0;

    return files.map((file) => {
        const size = file.size ?? 0;
        const filePercentage =
            sentBytes === null || size <= 0
                ? progress
                : Math.round(
                      Math.min(
                          100,
                          Math.max(
                              0,
                              ((sentBytes - consumedBytes) / size) * 100,
                          ),
                      ),
                  );

        consumedBytes += size;

        return {
            name: file.name,
            size: file.size ?? null,
            progress: filePercentage,
            status: filePercentage >= 100 ? 'sent' : 'uploading',
        };
    });
}

export function useUploadToast() {
    const toastId = useRef<string | null>(null);
    const session = useRef<UploadSession | null>(null);

    const begin = useCallback((message: UploadToastStart): void => {
        if (toastId.current) {
            toast.close(toastId.current);
        }

        const files = (message.files ?? []).map((file) => ({
            name: file.name,
            size: file.size,
        }));
        const states = uploadFileStates(files, 0, undefined);

        session.current = { files, states, hint: message.hint, progress: 0 };

        toastId.current = toast.add({
            type: 'loading',
            title: message.title,
            description: message.description,
            timeout: 0,
            data: {
                kind: 'upload',
                progress: 0,
                hint: message.hint,
                files: states,
            },
        });
    }, []);

    const setProgress = useCallback(
        (event: UploadProgressEvent | undefined): void => {
            const progress = overallPercentage(event);

            if (!toastId.current || !session.current || progress === null) {
                return;
            }

            const states = uploadFileStates(
                session.current.files,
                progress,
                event,
            );

            session.current = { ...session.current, states, progress };

            toast.update(toastId.current, {
                type: 'loading',
                timeout: 0,
                data: {
                    kind: 'upload',
                    progress,
                    hint: session.current.hint,
                    files: states,
                },
            });
        },
        [],
    );

    /**
     * Settle the toast on the outcome of the request. The panel is kept for
     * transfers that listed files, so the finished state still shows what went
     * up; single unnamed uploads fall back to a plain toast.
     */
    const finish = useCallback(
        (
            type: 'success' | 'error',
            message: UploadToastMessage,
            status: ToastUploadFileStatus,
        ): void => {
            if (!toastId.current) {
                return;
            }

            const current = session.current;
            const succeeded = status === 'done';

            toast.update(toastId.current, {
                type,
                title: message.title,
                description: message.description,
                timeout: 5000,
                data:
                    current && current.files.length > 0
                        ? {
                              kind: 'upload',
                              progress: succeeded ? 100 : current.progress,
                              hint: current.hint,
                              files: current.states.map((file) => ({
                                  ...file,
                                  progress: succeeded ? 100 : file.progress,
                                  status,
                              })),
                          }
                        : undefined,
            });

            toastId.current = null;
            session.current = null;
        },
        [],
    );

    const succeed = useCallback(
        (message: UploadToastMessage): void =>
            finish('success', message, 'done'),
        [finish],
    );

    const fail = useCallback(
        (message: UploadToastMessage): void =>
            finish('error', message, 'failed'),
        [finish],
    );

    const dismiss = useCallback((): void => {
        if (!toastId.current) {
            return;
        }

        toast.close(toastId.current);
        toastId.current = null;
        session.current = null;
    }, []);

    return { begin, dismiss, fail, setProgress, succeed };
}
