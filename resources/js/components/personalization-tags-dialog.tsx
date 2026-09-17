import { Copy01Icon, Tick02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from '@/components/ui/toast';
import { useClipboard } from '@/hooks/use-clipboard';
import type { EmailAudienceAttribute } from '@/types';

type TagItem = {
    label: string;
    tag: string;
    sample: string;
};

type Props = {
    attributes: EmailAudienceAttribute[];
    audienceName: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const SUBSCRIBER_TAGS: TagItem[] = [
    { label: 'First name', tag: '{{ first_name }}', sample: 'Ada' },
    { label: 'Last name', tag: '{{ last_name }}', sample: 'Lovelace' },
    { label: 'Name', tag: '{{ name }}', sample: 'Ada Lovelace' },
    { label: 'Email', tag: '{{ email }}', sample: 'ada@example.com' },
];

const DATE_TAGS: TagItem[] = [
    { label: 'Two digit day', tag: '{{ day }}', sample: '17' },
    { label: 'Full day name', tag: '{{ day_name }}', sample: 'Thursday' },
    { label: 'Two digit month', tag: '{{ month }}', sample: '09' },
    { label: 'Full month name', tag: '{{ month_name }}', sample: 'September' },
    { label: 'Four digit year', tag: '{{ year }}', sample: '2026' },
];

const LINK_TAGS: TagItem[] = [
    {
        label: 'View in browser',
        tag: '{{ web_view_url }}',
        sample: 'Signed link when sent',
    },
    {
        label: 'Unsubscribe',
        tag: '{{ unsubscribe_url }}',
        sample: 'Signed link when sent',
    },
    {
        label: 'Subscribe',
        tag: '{{ subscribe_url }}',
        sample: 'Published form, or preview',
    },
];

export default function PersonalizationTagsDialog({
    attributes,
    audienceName,
    open,
    onOpenChange,
}: Props) {
    const [copiedText, copy] = useClipboard();

    const copyTag = (tag: string) => {
        void copy(tag).then((copied) => {
            if (copied) {
                toast.add({ type: 'success', title: `${tag} copied.` });
            }
        });
    };

    const customTags: TagItem[] = attributes.map((attribute) => ({
        label: attribute.name,
        tag: `{{ ${attribute.key} }}`,
        sample: `Subscriber ${attribute.name.toLowerCase()}`,
    }));

    const groups: { title: string; items: TagItem[] }[] = [
        { title: 'Subscriber', items: SUBSCRIBER_TAGS },
        { title: 'Date of send', items: DATE_TAGS },
        { title: 'Links', items: LINK_TAGS },
        ...(customTags.length > 0
            ? [
                  {
                      title: audienceName
                          ? `${audienceName} custom fields`
                          : 'Custom fields',
                      items: customTags,
                  },
              ]
            : []),
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="flex max-h-[calc(100svh-2rem)] w-lg flex-col overflow-hidden"
                data-test="personalization-tags-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Personalization tags</DialogTitle>
                    <DialogDescription>
                        Copy a tag, then paste it into heading or text, a Button
                        URL, markdown{' '}
                        <code>{'[Unsubscribe]({{ unsubscribe_url }})'}</code>,
                        or HTML{' '}
                        <code>
                            {
                                '<a href="{{ unsubscribe_url }}">Unsubscribe here</a>'
                            }
                        </code>
                        . The spelling is unsubscribe_url. Preview and Send
                        makes those links clickable.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto overscroll-contain pr-1">
                    {groups.map((group) => (
                        <section
                            key={group.title}
                            className="flex flex-col gap-2"
                        >
                            <h3 className="text-sm font-medium">
                                {group.title}
                            </h3>
                            <ul className="flex flex-col">
                                {group.items.map((item) => (
                                    <li
                                        key={item.tag}
                                        className="flex items-center gap-3 border-b py-2 last:border-b-0"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm">
                                                {item.label}
                                            </p>
                                            <code className="text-xs text-muted-foreground">
                                                {item.tag}
                                            </code>
                                        </div>
                                        <p className="max-w-28 truncate text-right text-xs text-muted-foreground sm:max-w-40">
                                            {item.sample}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            data-test="copy-personalization-tag"
                                            onClick={() => copyTag(item.tag)}
                                        >
                                            <HugeiconsIcon
                                                icon={
                                                    copiedText === item.tag
                                                        ? Tick02Icon
                                                        : Copy01Icon
                                                }
                                                data-icon="inline-start"
                                            />
                                            {copiedText === item.tag
                                                ? 'Copied'
                                                : 'Copy'}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
