import type { ReactNode } from 'react';
import { CheckmarkCircleSolidIcon } from '@/components/icons/toast-status-icons';
import { Button } from '@/components/ui/button';

type Props = {
    done: boolean;
    title: string;
    summary?: ReactNode;
    actionLabel: string;
    onAction: () => void;
    hasError?: boolean;
    disabled?: boolean;
    extra?: ReactNode;
    children?: ReactNode;
    testId: string;
};

export function CampaignSetupRow({
    done,
    title,
    summary,
    actionLabel,
    onAction,
    hasError = false,
    disabled = false,
    extra,
    children,
    testId,
}: Props) {
    return (
        <div data-test={testId}>
            <div className="flex items-center gap-4 px-6 py-5">
                {done ? (
                    <CheckmarkCircleSolidIcon
                        className="size-5 shrink-0 text-success"
                        aria-hidden="true"
                    />
                ) : (
                    <span
                        className="size-5 shrink-0 rounded-full border-2 border-border"
                        aria-hidden="true"
                    />
                )}
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <p className="text-base font-medium">{title}</p>
                        {hasError ? (
                            <span
                                aria-label="has errors"
                                className="size-1.5 rounded-full bg-destructive"
                            />
                        ) : null}
                    </div>
                    {summary ? (
                        <p className="truncate text-sm text-muted-foreground">
                            {summary}
                        </p>
                    ) : null}
                </div>
                {extra}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={disabled}
                    onClick={onAction}
                >
                    {actionLabel}
                </Button>
            </div>
            {children}
        </div>
    );
}
