<?php

namespace App\Services;

class AssistantModels
{
    /**
     * The configured models whose provider has an API key, in picker order.
     *
     * @return list<array{id: string, provider: string, model: string, label: string}>
     */
    public function available(): array
    {
        /** @var list<array{id: string, provider: string, model: string, label: string}> $models */
        $models = config('assistant.models', []);

        return array_values(array_filter(
            $models,
            fn (array $model): bool => filled(config("ai.providers.{$model['provider']}.key")),
        ));
    }

    /**
     * @return array{id: string, provider: string, model: string, label: string}|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->available() as $model) {
            if ($model['id'] === $id) {
                return $model;
            }
        }

        return null;
    }

    /**
     * What the editor needs to render the picker.
     *
     * @return list<array{id: string, label: string, model: string, provider: string}>
     */
    public function options(): array
    {
        return array_map(
            fn (array $model): array => [
                'id' => $model['id'],
                'label' => $model['label'],
                'model' => $model['model'],
                'provider' => $model['provider'],
            ],
            $this->available(),
        );
    }
}
