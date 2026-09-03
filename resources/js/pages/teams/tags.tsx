import {
    Add01Icon,
    Delete02Icon,
    Edit03Icon,
    MoreHorizontalIcon,
    TagsIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import DeleteTagModal from '@/components/delete-tag-modal';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { TagColorSwatch } from '@/components/tag-color-select';
import TagDialog from '@/components/tag-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Team, TeamPermissions, TeamTag } from '@/types';

type Props = {
    team: Team;
    tags: TeamTag[];
    colors: string[];
    permissions: TeamPermissions;
};

export default function TeamTags({ team, tags, colors, permissions }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [tagToEdit, setTagToEdit] = useState<TeamTag | null>(null);
    const [tagsToDelete, setTagsToDelete] = useState<TeamTag[]>([]);
    const [selected, setSelected] = useState<Set<string>>(new Set());

    const canManage = permissions.canManageTags;
    const pageIds = tags.map((tag) => tag.uuid);
    const selectedTags = tags.filter((tag) => selected.has(tag.uuid));
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const someSelected = pageIds.some((id) => selected.has(id));

    return (
        <>
            <Head title={`Tags · ${team.name}`} />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="Tags" />
                <SettingsPanel
                    variant="inset"
                    title="Tags"
                    description="Labels shared across every audience in this workspace."
                    actions={
                        canManage && tags.length > 0 ? (
                            <Button
                                data-test="create-tag-button"
                                onClick={() => setCreateOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={Add01Icon}
                                    data-icon="inline-start"
                                />
                                Create tag
                            </Button>
                        ) : undefined
                    }
                >
                    {tags.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon icon={TagsIcon} />
                                </EmptyMedia>
                                <EmptyTitle>No tags yet</EmptyTitle>
                                <EmptyDescription>
                                    {canManage
                                        ? 'Create a tag to group contacts across audiences.'
                                        : 'Tags created for this workspace will appear here.'}
                                </EmptyDescription>
                            </EmptyHeader>
                            {canManage && (
                                <EmptyContent>
                                    <Button
                                        data-test="create-tag-button"
                                        onClick={() => setCreateOpen(true)}
                                    >
                                        Create tag
                                    </Button>
                                </EmptyContent>
                            )}
                        </Empty>
                    ) : (
                        <div className="p-3 sm:p-4">
                            {canManage && selectedTags.length > 0 && (
                                <div className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/50 px-5 py-3">
                                    <p className="text-sm text-muted-foreground">
                                        {selectedTags.length} selected
                                    </p>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        data-test="delete-selected-tags"
                                        onClick={() =>
                                            setTagsToDelete(selectedTags)
                                        }
                                    >
                                        Delete
                                    </Button>
                                </div>
                            )}
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {canManage && (
                                            <TableHead className="h-12 w-[1%] px-5">
                                                <Checkbox
                                                    aria-label="Select all"
                                                    data-test="tag-select-all"
                                                    checked={allSelected}
                                                    indeterminate={
                                                        someSelected &&
                                                        !allSelected
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const next = new Set(
                                                            selected,
                                                        );

                                                        if (checked === true) {
                                                            pageIds.forEach(
                                                                (id) =>
                                                                    next.add(
                                                                        id,
                                                                    ),
                                                            );
                                                        } else {
                                                            pageIds.forEach(
                                                                (id) =>
                                                                    next.delete(
                                                                        id,
                                                                    ),
                                                            );
                                                        }

                                                        setSelected(next);
                                                    }}
                                                />
                                            </TableHead>
                                        )}
                                        <TableHead className="h-12 px-5">
                                            Tag
                                        </TableHead>
                                        <TableHead className="h-12 px-5">
                                            Contacts
                                        </TableHead>
                                        {canManage && (
                                            <TableHead className="h-12 w-[1%] px-5 text-right">
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tags.map((tag) => (
                                        <TableRow
                                            key={tag.uuid}
                                            data-test="tag-row"
                                            className="h-16"
                                        >
                                            {canManage && (
                                                <TableCell className="w-[1%] px-5 py-4">
                                                    <Checkbox
                                                        aria-label={`Select ${tag.name}`}
                                                        data-test="tag-row-select"
                                                        checked={selected.has(
                                                            tag.uuid,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) => {
                                                            const next =
                                                                new Set(
                                                                    selected,
                                                                );

                                                            if (
                                                                checked === true
                                                            ) {
                                                                next.add(
                                                                    tag.uuid,
                                                                );
                                                            } else {
                                                                next.delete(
                                                                    tag.uuid,
                                                                );
                                                            }

                                                            setSelected(next);
                                                        }}
                                                    />
                                                </TableCell>
                                            )}
                                            <TableCell className="max-w-0 px-5 py-4">
                                                <span className="flex min-w-56 items-center gap-3 font-medium">
                                                    <TagColorSwatch
                                                        color={tag.color}
                                                    />
                                                    {tag.name}
                                                </span>
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-muted-foreground">
                                                {tag.subscribers_count}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="w-[1%] px-5 py-4 text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            render={
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    data-test="tag-actions"
                                                                    aria-label={`Actions for ${tag.name}`}
                                                                />
                                                            }
                                                        >
                                                            <HugeiconsIcon
                                                                icon={
                                                                    MoreHorizontalIcon
                                                                }
                                                            />
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuGroup>
                                                                <DropdownMenuItem
                                                                    data-test="edit-tag-button"
                                                                    onClick={() =>
                                                                        setTagToEdit(
                                                                            tag,
                                                                        )
                                                                    }
                                                                >
                                                                    <HugeiconsIcon
                                                                        icon={
                                                                            Edit03Icon
                                                                        }
                                                                    />
                                                                    Edit
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    data-test="delete-tag-button"
                                                                    onClick={() =>
                                                                        setTagsToDelete(
                                                                            [
                                                                                tag,
                                                                            ],
                                                                        )
                                                                    }
                                                                >
                                                                    <HugeiconsIcon
                                                                        icon={
                                                                            Delete02Icon
                                                                        }
                                                                    />
                                                                    Delete
                                                                </DropdownMenuItem>
                                                            </DropdownMenuGroup>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </SettingsPanel>
            </div>

            {canManage && (
                <>
                    <TagDialog
                        team={team}
                        colors={colors}
                        open={createOpen}
                        onOpenChange={setCreateOpen}
                    />

                    {tagToEdit && (
                        <TagDialog
                            key={tagToEdit.uuid}
                            team={team}
                            colors={colors}
                            tag={tagToEdit}
                            open
                            onOpenChange={(open) =>
                                setTagToEdit(open ? tagToEdit : null)
                            }
                        />
                    )}

                    <DeleteTagModal
                        team={team}
                        tags={tagsToDelete}
                        open={tagsToDelete.length > 0}
                        onOpenChange={(open) => {
                            if (!open) {
                                setTagsToDelete([]);
                            }
                        }}
                        onDeleted={() => setSelected(new Set())}
                    />
                </>
            )}
        </>
    );
}
