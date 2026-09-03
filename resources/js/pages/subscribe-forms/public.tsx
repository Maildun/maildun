import { Head, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { SubscribeFormView } from '@/components/subscribe-form-view';
import { store } from '@/routes/public/subscribe_forms';
import type {
    AudienceAttribute,
    SubscribeFormFieldMode,
    SubscribeFormImageSide,
    SubscribeFormLogoShape,
    SubscribeFormLogoSize,
    SubscribeFormStyle,
    SubscribeFormTextAlignment,
} from '@/types/audiences';
import type { TeamBrandTheme } from '@/types/teams';

type PublicForm = {
    theme: TeamBrandTheme;
    uuid: string;
    headline: string;
    description: string | null;
    text_alignment: SubscribeFormTextAlignment;
    button_label: string;
    success_heading: string;
    success_message: string;
    consent_text: string;
    style: SubscribeFormStyle;
    image_side: SubscribeFormImageSide;
    image_url: string | null;
    logo: string | null;
    logo_shape: SubscribeFormLogoShape;
    logo_size: SubscribeFormLogoSize;
    first_name_mode: SubscribeFormFieldMode;
    last_name_mode: SubscribeFormFieldMode;
    attributes: AudienceAttribute[];
};

type Props = {
    subscribeForm: PublicForm;
    embed: boolean;
};

export default function PublicSubscribeForm({ subscribeForm, embed }: Props) {
    const [completed, setCompleted] = useState(false);
    const form = useHttp<
        {
            email: string;
            first_name: string;
            last_name: string;
            consent: boolean;
            attributes: Record<string, string>;
            website: string;
        },
        { message: string }
    >({
        email: '',
        first_name: '',
        last_name: '',
        consent: false,
        attributes: {},
        website: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(store.url(subscribeForm.uuid), {
            onSuccess: () => setCompleted(true),
        });
    };

    return (
        <>
            <Head title={subscribeForm.headline} />
            <main className={embed ? 'min-h-svh' : 'h-svh'}>
                <SubscribeFormView
                    form={subscribeForm}
                    completed={completed}
                    successMessage={
                        form.response?.message || subscribeForm.success_message
                    }
                    processing={form.processing}
                    errors={form.errors}
                    values={form.data}
                    onChange={(key, value) =>
                        form.setData({
                            ...form.data,
                            [key]: value,
                        })
                    }
                    onAttributeChange={(key, value) =>
                        form.setData({
                            ...form.data,
                            attributes: {
                                ...form.data.attributes,
                                [key]: value,
                            },
                        })
                    }
                    onSubmit={submit}
                    className="min-h-svh"
                />
            </main>
        </>
    );
}

PublicSubscribeForm.layout = null;
