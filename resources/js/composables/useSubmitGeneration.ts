import type { QuoteInput } from '@/composables/useQuote';
import { errorMessage, postJson, validationErrors } from '@/lib/http';
import { filledReferenceImages, remapReferenceImageErrors } from '@/lib/referenceImages';
import { ref, type Ref } from 'vue';

export type SubmitState =
    | { status: 'idle' }
    | { status: 'submitting' }
    | { status: 'submitted' }
    | { status: 'invalid'; errors: Record<string, string> }
    | { status: 'failed'; message: string };

export interface UseSubmitGeneration {
    state: Ref<SubmitState>;
    submit: (input: QuoteInput) => Promise<void>;
    reset: () => void;
}

/**
 * Sends a generation to be made. The server only saves it and queues it,
 * so this answers quickly; the image itself arrives later.
 */
export function useSubmitGeneration(url: string): UseSubmitGeneration {
    const state = ref<SubmitState>({ status: 'idle' });

    async function submit({ prompt, size, quality, referenceImages }: QuoteInput): Promise<void> {
        // A second click while the first is on its way would queue a second image.
        if (state.value.status === 'submitting') {
            return;
        }

        state.value = { status: 'submitting' };
        const { addresses, rows } = filledReferenceImages(referenceImages);

        try {
            const { status, data } = await postJson(url, { prompt, size, quality, reference_images: addresses });

            if (status === 202) {
                state.value = { status: 'submitted' };
            } else if (status === 422) {
                state.value = { status: 'invalid', errors: remapReferenceImageErrors(validationErrors(data), rows) };
            } else {
                state.value = { status: 'failed', message: errorMessage(data, 'The image could not be queued. Please try again.') };
            }
        } catch {
            state.value = { status: 'failed', message: 'Could not reach the server. Please try again.' };
        }
    }

    function reset(): void {
        if (state.value.status !== 'submitting') {
            state.value = { status: 'idle' };
        }
    }

    return { state, submit, reset };
}
