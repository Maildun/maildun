<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\SubscribeToAudience;
use App\Http\Requests\PublicSubscribeRequest;
use App\Models\AudienceAttribute;
use App\Models\SubscribeForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicSubscribeFormController extends Controller
{
    public function show(Request $request, SubscribeForm $subscribeForm): Response
    {
        abort_unless($subscribeForm->isPublished(), 404);

        return Inertia::render('subscribe-forms/public', [
            'subscribeForm' => [
                'uuid' => $subscribeForm->uuid,
                'theme' => [
                    'color' => $subscribeForm->brand_color->value,
                    'font' => $subscribeForm->brand_font->value,
                    'inputStyle' => $subscribeForm->brand_input_style->value,
                ],
                'headline' => $subscribeForm->headline,
                'description' => $subscribeForm->description,
                'text_alignment' => $subscribeForm->text_alignment->value,
                'button_label' => $subscribeForm->button_label,
                'success_heading' => $subscribeForm->success_heading,
                'success_message' => $subscribeForm->success_message,
                'consent_text' => $subscribeForm->consent_text,
                'style' => $subscribeForm->style->value,
                'image_side' => $subscribeForm->image_side->value,
                'image_url' => $subscribeForm->image,
                'logo' => $subscribeForm->logo,
                'logo_shape' => $subscribeForm->logo_shape->value,
                'logo_size' => $subscribeForm->logo_size->value,
                'first_name_mode' => $subscribeForm->audience->first_name_mode->value,
                'last_name_mode' => $subscribeForm->audience->last_name_mode->value,
                'attributes' => $subscribeForm->audience->audienceAttributes()
                    ->orderBy('position')
                    ->get()
                    ->map(fn (AudienceAttribute $attribute): array => [
                        'uuid' => $attribute->uuid,
                        'name' => $attribute->name,
                        'key' => $attribute->key,
                        'type' => $attribute->type->value,
                        'required' => $attribute->required,
                    ])
                    ->values(),
            ],
            'embed' => $request->boolean('embed'),
        ]);
    }

    public function store(PublicSubscribeRequest $request, SubscribeForm $subscribeForm, SubscribeToAudience $subscribe): JsonResponse
    {
        abort_unless($subscribeForm->isPublished(), 404);

        $subscribe->handle($subscribeForm, [
            'email' => $request->string('email')->toString(),
            'first_name' => $request->filled('first_name') ? $request->string('first_name')->toString() : null,
            'last_name' => $request->filled('last_name') ? $request->string('last_name')->toString() : null,
            'attributes' => $request->validated('attributes', []),
        ], $request->ip());

        return response()->json(['message' => $subscribeForm->success_message]);
    }
}
