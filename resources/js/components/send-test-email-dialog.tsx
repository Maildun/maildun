import { Form } from '@inertiajs/react';
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
import { test } from '@/routes/emails';

type Props = {
    teamSlug: string;
    emailUuid: string;
    defaultAddress: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function SendTestEmailDialog({
    teamSlug,
    emailUuid,
    defaultAddress,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...test.form([teamSlug, emailUuid])}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Send a test</DialogTitle>
                                <DialogDescription>
                                    Sends one copy of this draft, using the
                                    sender details saved on it.
                                </DialogDescription>
                            </DialogHeader>

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.to)}>
                                    <FieldLabel htmlFor="test-email-to">
                                        Send to
                                    </FieldLabel>
                                    <Input
                                        id="test-email-to"
                                        name="to"
                                        type="email"
                                        data-test="test-email-input"
                                        autoFocus
                                        required
                                        defaultValue={defaultAddress}
                                        placeholder="email@example.com"
                                        aria-invalid={Boolean(errors.to)}
                                    />
                                    <FieldDescription>
                                        The subject is prefixed with [Test] so
                                        it is easy to spot.
                                    </FieldDescription>
                                    <FieldError>{errors.to}</FieldError>
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
                                    data-test="send-test-submit"
                                    disabled={processing}
                                >
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Send test
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
