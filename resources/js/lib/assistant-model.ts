import type { AssistantModel } from '@/components/design-assistant';

/** The model the author last picked in the design assistant. */
export const MODEL_STORAGE_KEY = 'maildun.design-assistant.model';

/** The author's last picked model when it is still offered, otherwise the first. */
export function readStoredModel(models: AssistantModel[]): string {
    try {
        const stored = window.localStorage.getItem(MODEL_STORAGE_KEY);

        if (stored && models.some((model) => model.id === stored)) {
            return stored;
        }
    } catch {
        // Storage may be unavailable (private mode); fall back to the default.
    }

    return models[0]?.id ?? '';
}
