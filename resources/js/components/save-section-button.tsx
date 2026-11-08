import { BookmarkAdd02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { toBlockInput } from '@maildun/email-builder';
import {
    useEditorState,
    useEditorStore,
    useMessages,
} from '@maildun/email-builder/editor';
import { useState } from 'react';
import {
    errorMessage,
    SectionNameDialog,
    send,
} from '@/components/saved-sections-group';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { store } from '@/routes/saved_sections';

/**
 * The "Your sections" heading button: saves the selected block (with
 * everything inside it) as a section. It reads the editor's store, so
 * saved-sections-group loads it lazily alongside the block builder.
 */
export default function SaveSelectionButton({
    teamSlug,
}: {
    teamSlug: string;
}) {
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
