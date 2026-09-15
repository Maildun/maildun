import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { SubscribeFormView } from '@/components/subscribe-form-view';
import { store } from '@/routes/public/subscribe_forms';
import type {
    AudienceAttribute,
    SubscribeFormArtworkPreset,
    SubscribeFormArtworkType,
    SubscribeFormCardPadding,
    SubscribeFormFieldMode,
    SubscribeFormImageSide,
    SubscribeFormHeaderSpacing,
    SubscribeFormLogoPosition,
    SubscribeFormLogoShape,
    SubscribeFormLogoSize,
    SubscribeFormPoweredByPosition,
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
    redirect_enabled: boolean;
    redirect_url: string | null;
    powered_by_enabled: boolean;
    powered_by_form_position: SubscribeFormPoweredByPosition;
    consent_text: string;
    style: SubscribeFormStyle;
    image_side: SubscribeFormImageSide;
    artwork_type: SubscribeFormArtworkType;
    artwork_preset: SubscribeFormArtworkPreset | null;
    image_url: string | null;
    logo: string | null;
    logo_shape: SubscribeFormLogoShape;
    logo_size: SubscribeFormLogoSize;
    logo_position: SubscribeFormLogoPosition;
    header_spacing: SubscribeFormHeaderSpacing;
    card_padding: SubscribeFormCardPadding;
    first_name_mode: SubscribeFormFieldMode;
    last_name_mode: SubscribeFormFieldMode;
    attributes: AudienceAttribute[];
};

type Props = {
    subscribeForm: PublicForm;
    embed: boolean;
};

export default function PublicSubscribeForm({ subscribeForm }: Props) {
    const [completed, setCompleted] = useState(false);
    const [redirectCountdown, setRedirectCountdown] = useState<number | null>(
        null,
    );
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

    useEffect(() => {
        if (
            !completed ||
            redirectCountdown === null ||
            !subscribeForm.redirect_url
        ) {
            return;
        }

        if (redirectCountdown === 0) {
            window.location.assign(subscribeForm.redirect_url);

            return;
        }

        const timeout = window.setTimeout(() => {
            setRedirectCountdown((current) =>
                current === null ? null : current - 1,
            );
        }, 1000);

        return () => window.clearTimeout(timeout);
    }, [completed, redirectCountdown, subscribeForm.redirect_url]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(store.url(subscribeForm.uuid), {
            onSuccess: () => {
                setCompleted(true);

                if (
                    subscribeForm.redirect_enabled &&
                    subscribeForm.redirect_url
                ) {
                    setRedirectCountdown(5);
                }
            },
        });
    };

    return (
        <>
            <Head title={subscribeForm.headline} />
            <main className="min-h-svh">
                <SubscribeFormView
                    form={subscribeForm}
                    completed={completed}
                    successMessage={
                        form.response?.message || subscribeForm.success_message
                    }
                    redirectCountdown={redirectCountdown}
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
