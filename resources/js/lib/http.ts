export interface JsonResponse {
    status: number;
    data: unknown;
}

/**
 * Laravel puts its CSRF token in the XSRF-TOKEN cookie and accepts it back
 * in the X-XSRF-TOKEN header.
 */
function xsrfToken(): string {
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]*)/.exec(document.cookie);

    return match === null ? '' : decodeURIComponent(match[1]);
}

/**
 * POST JSON to the app and return the status with the parsed body. The body
 * is `unknown` on purpose: callers check its shape before using it.
 */
export async function postJson(url: string, body: unknown, signal?: AbortSignal): Promise<JsonResponse> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
        signal,
    });

    const data: unknown = await response.json().catch(() => null);

    return { status: response.status, data };
}

/**
 * Laravel's 422 body is { message, errors: { field: [messages] } }.
 * Returns the first message for each field.
 */
export function validationErrors(data: unknown): Record<string, string> {
    if (typeof data !== 'object' || data === null || !('errors' in data)) {
        return {};
    }

    const { errors } = data;

    if (typeof errors !== 'object' || errors === null) {
        return {};
    }

    const firstMessages: Record<string, string> = {};

    for (const [field, messages] of Object.entries(errors)) {
        if (Array.isArray(messages) && typeof messages[0] === 'string') {
            firstMessages[field] = messages[0];
        }
    }

    return firstMessages;
}

export function errorMessage(data: unknown, fallback: string): string {
    if (typeof data === 'object' && data !== null && 'message' in data && typeof data.message === 'string') {
        return data.message;
    }

    return fallback;
}
