import { Add01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/companies';
import type { CompanySummary } from '@/types/companies';

export function CompanyDialog({
    teamSlug,
    company,
    open,
    onOpenChange,
}: {
    teamSlug: string;
    company?: CompanySummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        name: company?.name ?? '',
        domains: company?.domains.join('\n') ?? '',
    });
    const domainError =
        form.errors.domains ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('domains.'),
        )?.[1];

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();
        const data = {
            name: form.data.name,
            domains: form.data.domains.split(/\r?\n/),
        };
        const onSuccess = (): void => {
            onOpenChange(false);

            if (!company) {
                form.reset();
            }
        };

        if (company) {
            form.transform(() => data);
            form.put(update.url([teamSlug, company.uuid]), {
                onSuccess,
            });

            return;
        }

        form.transform(() => data);
        form.post(store.url(teamSlug), { onSuccess });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {!company ? (
                <DialogTrigger render={<Button />}>
                    <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" />
                    Add company
                </DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {company ? 'Edit company' : 'Add company'}
                    </DialogTitle>
                    <DialogDescription>
                        Domains automatically associate matching business
                        contacts.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel
                                htmlFor={`company-name-${company?.uuid ?? 'new'}`}
                            >
                                Company name
                            </FieldLabel>
                            <Input
                                id={`company-name-${company?.uuid ?? 'new'}`}
                                placeholder="Acme Inc."
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(domainError)}>
                            <FieldLabel
                                htmlFor={`company-domains-${company?.uuid ?? 'new'}`}
                            >
                                Domains
                            </FieldLabel>
                            <Textarea
                                id={`company-domains-${company?.uuid ?? 'new'}`}
                                placeholder={'acme.com\nacme.co'}
                                value={form.data.domains}
                                onChange={(event) =>
                                    form.setData('domains', event.target.value)
                                }
                                aria-invalid={Boolean(domainError)}
                            />
                            <FieldDescription>
                                One domain per line. Personal email domains are
                                not accepted.
                            </FieldDescription>
                            <FieldError>{domainError}</FieldError>
                        </Field>
                    </FieldGroup>
                    <DialogFooter className="mt-6">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : null}
                            {company ? 'Save changes' : 'Add company'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
