import { AlertCircleIcon } from '@hugeicons/core-free-icons';
import {
    Callout,
    CalloutContent,
    CalloutHeading,
    CalloutText,
} from '@/components/ui/callout';

export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    return (
        <Callout variant="danger" icon={AlertCircleIcon} role="alert">
            <CalloutContent>
                <CalloutHeading>
                    {title || 'Something went wrong.'}
                </CalloutHeading>
                <CalloutText>
                    <ul className="list-inside list-disc text-sm">
                        {Array.from(new Set(errors)).map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </CalloutText>
            </CalloutContent>
        </Callout>
    );
}
