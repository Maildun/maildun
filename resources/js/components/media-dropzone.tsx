import { CloudUploadIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useRef, useState } from 'react';
import type { DragEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    enabled: boolean;
    clickToPick?: boolean;
    onFiles: (files: FileList | File[]) => void;
    onPick: () => void;
    children: ReactNode;
    className?: string;
};

function isFileDrag(event: DragEvent): boolean {
    return Array.from(event.dataTransfer.types).includes('Files');
}

export default function MediaDropzone({
    enabled,
    clickToPick = false,
    onFiles,
    onPick,
    children,
    className,
}: Props) {
    const dragCount = useRef(0);
    const [dragging, setDragging] = useState(false);

    if (!enabled) {
        return children;
    }

    const onDragEnter = (event: DragEvent<HTMLDivElement>) => {
        if (!isFileDrag(event)) {
            return;
        }

        event.preventDefault();
        dragCount.current += 1;
        setDragging(true);
    };

    const onDragLeave = (event: DragEvent<HTMLDivElement>) => {
        if (!isFileDrag(event)) {
            return;
        }

        event.preventDefault();
        dragCount.current = Math.max(0, dragCount.current - 1);

        if (dragCount.current === 0) {
            setDragging(false);
        }
    };

    const onDragOver = (event: DragEvent<HTMLDivElement>) => {
        if (!isFileDrag(event)) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        if (!isFileDrag(event)) {
            return;
        }

        event.preventDefault();
        dragCount.current = 0;
        setDragging(false);
        onFiles(event.dataTransfer.files);
    };

    return (
        <div
            data-test="media-dropzone"
            className={cn(
                'relative flex min-h-0 flex-1 flex-col overflow-auto p-2',
                className,
            )}
            onDragEnter={onDragEnter}
            onDragLeave={onDragLeave}
            onDragOver={onDragOver}
            onDrop={onDrop}
            onClick={(event) => {
                if (!clickToPick) {
                    return;
                }

                if ((event.target as HTMLElement).closest('button')) {
                    return;
                }

                onPick();
            }}
        >
            {children}

            {dragging && (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-primary bg-primary/30 text-center text-primary">
                    <HugeiconsIcon icon={CloudUploadIcon} />
                    <p className="text-sm font-medium">Drop images to upload</p>
                    <p className="text-xs">Up to 20 images, 2 MB each</p>
                </div>
            )}
        </div>
    );
}
