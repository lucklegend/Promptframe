import { errorMessage, postJson, validationErrors } from '@/lib/http';
import { filledReferenceImages, remapReferenceImageErrors } from '@/lib/referenceImages';
import { watchDebounced } from '@vueuse/core';
import { onMounted, ref, type Ref } from 'vue';

export interface QuoteInput {
    prompt: string;
    size: string;
    quality: string;
    referenceImages: string[];
}

export interface Quote {
    minCharge: string;
    maxCharge: string;
}

export type QuoteState =
    | { status: 'loading' }
    | { status: 'ready'; quote: Quote }
    | { status: 'invalid'; errors: Record<string, string> }
    | { status: 'unavailable'; message: string };

export interface UseQuote {
    state: Ref<QuoteState>;
    refreshing: Ref<boolean>;
    refresh: () => Promise<void>;
}

interface QuoteBody {
    min_charge: string;
    max_charge: string;
}

function isQuoteBody(data: unknown): data is QuoteBody {
    return (
        typeof data === 'object' &&
        data !== null &&
        'min_charge' in data &&
        typeof data.min_charge === 'string' &&
        'max_charge' in data &&
        typeof data.max_charge === 'string'
    );
}

/**
 * Keeps a live price for the options the user is choosing.
 *
 * Requests are debounced while typing, and each new request cancels the
 * previous one, so a slow, out-of-date answer can never replace a newer one.
 */
export function useQuote(url: string, input: () => QuoteInput): UseQuote {
    const state = ref<QuoteState>({ status: 'loading' });
    const refreshing = ref(false);
    let inFlight: AbortController | null = null;

    async function refresh(): Promise<void> {
        inFlight?.abort();
        const request = new AbortController();
        inFlight = request;
        refreshing.value = true;

        const { prompt, size, quality, referenceImages } = input();
        const { addresses, rows } = filledReferenceImages(referenceImages);

        try {
            const { status, data } = await postJson(url, { prompt, size, quality, reference_images: addresses }, request.signal);

            if (request.signal.aborted) {
                return;
            }

            if (status === 200 && isQuoteBody(data)) {
                state.value = { status: 'ready', quote: { minCharge: data.min_charge, maxCharge: data.max_charge } };
            } else if (status === 422) {
                state.value = { status: 'invalid', errors: remapReferenceImageErrors(validationErrors(data), rows) };
            } else {
                state.value = { status: 'unavailable', message: errorMessage(data, 'The price could not be worked out. Please try again.') };
            }
        } catch {
            if (!request.signal.aborted) {
                state.value = { status: 'unavailable', message: 'Could not reach the server to price this image.' };
            }
        } finally {
            if (inFlight === request) {
                inFlight = null;
                refreshing.value = false;
            }
        }
    }

    watchDebounced(input, refresh, { debounce: 300, deep: true });
    onMounted(refresh);

    return { state, refreshing, refresh };
}
