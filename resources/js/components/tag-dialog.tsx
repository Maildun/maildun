import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { TagColorSelect } from '@/components/tag-color-select';
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
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/tags';
import type { Team, TeamTag } from '@/types';

type Props = {
    team: Team;
    colors: string[];
    tag?: TeamTag | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function TagDialog({
    team,
    colors,
    tag = null,
    open,
    onOpenChange,
}: Props) {
    const defaultColor = tag?.color ?? colors[0];
    const [color, setColor] = useState(defaultColor);

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setColor(defaultColor);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...(tag
                        ? update.form.patch([team.slug, tag.uuid])
                        : store.form(team.slug))}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {tag ? 'Edit tag' : 'Create tag'}
                                </DialogTitle>
                                <DialogDescription>
                                    {tag
                                        ? 'Renaming a tag updates it everywhere it is used.'
                                        : 'Tags are shared across every audience in this team.'}
                                </DialogDescription>
                            </DialogHeader>

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="tag-name">
                                        Name
                                    </FieldLabel>
                                    <Input
                                        id="tag-name"
                                        name="name"
                                        data-test="tag-name-input"
                                        autoFocus
                                        required
                                        maxLength={50}
                                        placeholder="VIP"
                                        defaultValue={tag?.name ?? ''}
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.color)}>
                                    <FieldLabel htmlFor="tag-color">
                                        Color
                                    </FieldLabel>
                                    <TagColorSelect
                                        id="tag-color"
                                        name="color"
                                        value={color}
                                        onValueChange={setColor}
                                        colors={colors}
                                        aria-invalid={Boolean(errors.color)}
                                    />
                                    <FieldDescription>
                                        Shown as a dot next to the tag wherever
                                        it appears.
                                    </FieldDescription>
                                    <FieldError>{errors.color}</FieldError>
                                </Field>
                            </FieldGroup>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={<Button variant="secondary" />}
                                >
                                    Cancel
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="tag-submit"
                                    disabled={processing}
                                >
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    {tag ? 'Save changes' : 'Create tag'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
