import {
    BookmarkAdd02Icon,
    Delete02Icon,
    Edit03Icon,
    MoreHorizontalIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { toBlockInput } from '@maildun/email-builder';
import {
    useEditorState,
    useEditorStore,
    useMessages,
} from '@maildun/email-builder/editor';
import type { PaletteGroup } from '@maildun/email-builder/editor';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { SavedSection } from '@/lib/email-builder';
import { destroy, store, update } from '@/routes/saved_sections';

type Method = 'post' | 'patch' | 'delete';

/**
 * Saves a change and reloads only the page's `savedSections` prop, so an
 * open draft keeps its unsaved edits.
 */
function send(
    method: Method,
    url: string,
    data: Record<string, unknown> = {},
): Promise<void> {
    return new Promise((resolve, reject) => {
        const fail = (message: string) => {
            reject(new Error(message));

            return false as const;
        };

        router.visit(url, {
            method,
            // Blocks are plain JSON, which Inertia sends as a JSON body.
            data: data as RequestPayload,
            only: ['savedSections'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => resolve(),
            onError: (errors) =>
                fail(Object.values(errors)[0] ?? 'Something went wrong.'),
            onHttpException: () => fail('Something went wrong. Try again.'),
            onNetworkError: () =>
                fail('Could not reach the server. Check your connection.'),
        });
    });
}

function errorMessage(failure: unknown): string {
    return failure instanceof Error ? failure.message : String(failure);
}

/**
 * "Your sections" in the editor's block palette: the workspace's saved
 * sections, to click or drag into the design like the built-in ones. The
 * heading's button saves the selected block (with everything inside it).
 */
export function useSavedSectionsGroup({
    teamSlug,
    sections,
    canManage,
}: {
    teamSlug: string;
    sections: SavedSection[];
    canManage: boolean;
}): PaletteGroup {
    return useMemo(
        () => ({
            id: 'saved-sections',
            label: 'Your sections',
            position: 'beforeSections',
            actions: canManage ? (
                <SaveSelectionButton teamSlug={teamSlug} />
            ) : undefined,
            empty: (
                <p className="text-xs text-muted-foreground">
                    {canManage
                        ? 'Select a block, such as the container holding your header or footer, and save it to reuse it in every email in this workspace.'
                        : 'No saved sections in this workspace yet.'}
                </p>
            ),
            items: sections.map((section) => ({
                id: section.id,
                label: section.name,
                blocks: section.blocks,
                actions: canManage ? (
                    <SavedSectionActions
                        teamSlug={teamSlug}
                        section={section}
                    />
                ) : undefined,
            })),
        }),
        [canManage, sections, teamSlug],
    );
}

function SaveSelectionButton({ teamSlug }: { teamSlug: string }) {
    const editor = useEditorStore();
    const messages = useMessages();
    const selectedId = useEditorState((state) => state.selectedId);
    const reviewing = useEditorState((state) => state.proposal !== null);
    const selected = useEditorState((state) =>
        state.selectedId ? state.document.blocks[state.selectedId] : undefined,
    );
    const [saving, setSaving] = useState(false);
    const label = selected ? messages.blocks[selected.type].label : null;
    const hint = reviewing
        ? 'Accept or reject the proposal first'
        : label
          ? `Save the selected ${label.toLowerCase()} as a section`
          : 'Select a block to save it as a section';

    return (
        <>
            <Tooltip>
                <TooltipTrigger
                    render={
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            aria-label={hint}
                            data-test="save-section"
                            disabled={!selected || reviewing}
                            onClick={() => setSaving(true)}
                        />
                    }
                >
                    <HugeiconsIcon icon={BookmarkAdd02Icon} />
                </TooltipTrigger>
                <TooltipContent side="bottom">{hint}</TooltipContent>
            </Tooltip>

            {saving && selectedId && label ? (
                <SectionNameDialog
                    title="Save as section"
                    description="Saved sections appear under Your sections in every email in this workspace."
                    action="Save"
                    name={label}
                    onOpenChange={setSaving}
                    onSubmit={async (name) => {
                        try {
                            await send('post', store.url(teamSlug), {
                                name,
                                blocks: [
                                    toBlockInput(
                                        editor.visibleDocument(),
                                        selectedId,
                                        false,
                                    ),
                                ],
                            });
                            toast.add({
                                type: 'success',
                                title: `Saved ${name}.`,
                                description: 'Find it under Your sections.',
                            });
                        } catch (failure) {
                            toast.add({
                                type: 'error',
                                title: 'Could not save the section.',
                                description: errorMessage(failure),
                            });

                            throw failure;
                        }
                    }}
                />
            ) : null}
        </>
    );
}

function SavedSectionActions({
    teamSlug,
    section,
}: {
    teamSlug: string;
    section: SavedSection;
}) {
    const [renaming, setRenaming] = useState(false);
    const route = { current_team: teamSlug, savedSection: section.id };

    const remove = async () => {
        try {
            await send('delete', destroy.url(route));
            toast.add({
                type: 'success',
                title: `Deleted ${section.name}.`,
                actionProps: {
                    children: 'Undo',
                    onClick: () =>
                        void send('post', store.url(teamSlug), {
                            name: section.name,
                            blocks: section.blocks,
                        }).catch((failure) =>
                            toast.add({
                                type: 'error',
                                title: 'Could not restore the section.',
                                description: errorMessage(failure),
                            }),
                        ),
                },
            });
        } catch (failure) {
            toast.add({
                type: 'error',
                title: 'Could not delete the section.',
                description: errorMessage(failure),
            });
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            aria-label={`More for ${section.name}`}
                        />
                    }
                >
                    <HugeiconsIcon icon={MoreHorizontalIcon} />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-40">
                    <DropdownMenuItem onClick={() => setRenaming(true)}>
                        <HugeiconsIcon icon={Edit03Icon} />
                        Rename…
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onClick={() => void remove()}
                    >
                        <HugeiconsIcon icon={Delete02Icon} />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            {renaming ? (
                <SectionNameDialog
                    title="Rename section"
                    action="Rename"
                    name={section.name}
                    onOpenChange={setRenaming}
                    onSubmit={async (name) => {
                        try {
                            await send('patch', update.url(route), { name });
                        } catch (failure) {
                            toast.add({
                                type: 'error',
                                title: 'Could not rename the section.',
                                description: errorMessage(failure),
                            });

                            throw failure;
                        }
                    }}
                />
            ) : null}
        </>
    );
}

function SectionNameDialog({
    title,
    description,
    action,
    name,
    onOpenChange,
    onSubmit,
}: {
    title: string;
    description?: string;
    action: string;
    name: string;
    onOpenChange: (open: boolean) => void;
    onSubmit: (name: string) => Promise<void>;
}) {
    const [value, setValue] = useState(name);
    const [busy, setBusy] = useState(false);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        // The dialog is portaled, but React still bubbles its submit to the
        // page's own form (the campaign draft) unless it stops here.
        event.preventDefault();
        event.stopPropagation();

        if (value.trim() === '' || busy) {
            return;
        }

        setBusy(true);

        try {
            await onSubmit(value.trim());
            onOpenChange(false);
        } catch {
            // The caller showed the error; keep the dialog open.
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    onSubmit={(event) => void submit(event)}
                    className="space-y-6"
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? (
                            <DialogDescription>{description}</DialogDescription>
                        ) : null}
                    </DialogHeader>
                    <Field>
                        <FieldLabel htmlFor="section-name">Name</FieldLabel>
                        <Input
                            id="section-name"
                            autoFocus
                            maxLength={80}
                            placeholder="Header, Footer, Logo …"
                            value={value}
                            onChange={(event) => setValue(event.target.value)}
                            onFocus={(event) => event.target.select()}
                        />
                    </Field>
                    <DialogFooter>
                        <DialogClose
                            render={<Button type="button" variant="outline" />}
                        >
                            Cancel
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={value.trim() === '' || busy}
                        >
                            {busy ? <Spinner data-icon="inline-start" /> : null}
                            {action}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
