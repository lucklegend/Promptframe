export interface FilledReferenceImages {
    /** The addresses to send, blanks left out. */
    addresses: string[];
    /** For each address sent, the row the user typed it in. */
    rows: number[];
}

/**
 * Blank reference-image rows are left out of a request, so the user can add
 * a row and leave it empty without causing an error.
 */
export function filledReferenceImages(referenceImages: string[]): FilledReferenceImages {
    const filled = referenceImages.map((address, row) => ({ address: address.trim(), row })).filter(({ address }) => address !== '');

    return { addresses: filled.map(({ address }) => address), rows: filled.map(({ row }) => row) };
}

/**
 * Because blanks are left out, the server's error keys ("reference_images.0")
 * count only the filled rows. This maps them back to the row the user sees.
 */
export function remapReferenceImageErrors(errors: Record<string, string>, sentRows: number[]): Record<string, string> {
    const remapped: Record<string, string> = {};

    for (const [field, message] of Object.entries(errors)) {
        const match = /^reference_images\.(\d+)$/.exec(field);
        const row = match === null ? undefined : sentRows[Number(match[1])];

        remapped[row === undefined ? field : `reference_images.${row}`] = message;
    }

    return remapped;
}
