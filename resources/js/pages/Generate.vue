<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useQuote } from '@/composables/useQuote';
import { useSubmitGeneration } from '@/composables/useSubmitGeneration';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatUsd, formatUsdRange } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ImagePlus, LoaderCircle, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';

interface Option {
    value: string;
    label: string;
}

interface Props {
    sizes: Option[];
    qualities: Option[];
    defaults: { size: string; quality: string };
    limits: { promptCharacters: number; referenceImages: number };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Generate', href: '/generate' }];

const form = reactive({
    prompt: '',
    size: props.defaults.size,
    quality: props.defaults.quality,
    referenceImages: new Array<string>(),
});

const { state: quote, refreshing } = useQuote(route('generate.quote'), () => ({
    prompt: form.prompt,
    size: form.size,
    quality: form.quality,
    referenceImages: [...form.referenceImages],
}));

const { state: submission, submit, reset: resetSubmission } = useSubmitGeneration(route('generations.store'));

// Errors from submitting win, since they are the newer answer.
const errors = computed(() => {
    if (submission.value.status === 'invalid') {
        return submission.value.errors;
    }

    return quote.value.status === 'invalid' ? quote.value.errors : {};
});

// Changing the form clears the last "queued" or error message.
watch(form, resetSubmission, { deep: true });

const canSubmit = computed(() => form.prompt.trim() !== '' && quote.value.status === 'ready' && submission.value.status !== 'submitting');

function onSubmit(): void {
    if (canSubmit.value) {
        void submit({ ...form, referenceImages: [...form.referenceImages] });
    }
}

// Pixelforge counts Unicode characters, so count code points, not UTF-16 units.
const promptLength = computed(() => Array.from(form.prompt).length);

function addReferenceImage(): void {
    form.referenceImages.push('');
}

function removeReferenceImage(row: number): void {
    form.referenceImages.splice(row, 1);
}

const fieldClass =
    'flex w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm';
</script>

<template>
    <Head title="Generate" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="grid gap-8 p-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:p-6">
            <form class="flex flex-col gap-6" @submit.prevent="onSubmit">
                <HeadingSmall title="Generate an image" description="Describe what you want, then pick a size and quality." />

                <div class="grid gap-2">
                    <Label for="prompt">Prompt</Label>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="5"
                        :class="cn(fieldClass, 'resize-y')"
                        placeholder="A lighthouse on a cliff at dawn, oil painting"
                    />
                    <div class="flex justify-between gap-4">
                        <InputError :message="errors.prompt" />
                        <p
                            class="ml-auto text-xs tabular-nums text-muted-foreground"
                            :class="{ 'text-red-600 dark:text-red-500': promptLength > limits.promptCharacters }"
                        >
                            {{ promptLength.toLocaleString() }} / {{ limits.promptCharacters.toLocaleString() }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="size">Size</Label>
                        <select id="size" v-model="form.size" :class="cn(fieldClass, 'h-10 py-0')">
                            <option v-for="size in sizes" :key="size.value" :value="size.value">{{ size.label }}</option>
                        </select>
                        <InputError :message="errors.size" />
                    </div>

                    <fieldset class="grid content-start gap-2">
                        <legend class="mb-2 text-sm font-medium leading-none">Quality</legend>
                        <div class="grid grid-cols-3 rounded-md border border-input p-1">
                            <label
                                v-for="quality in qualities"
                                :key="quality.value"
                                class="cursor-pointer rounded px-3 py-1.5 text-center text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                :class="
                                    form.quality === quality.value
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                            >
                                <input v-model="form.quality" type="radio" name="quality" :value="quality.value" class="sr-only" />
                                {{ quality.label }}
                            </label>
                        </div>
                        <InputError :message="errors.quality" />
                    </fieldset>
                </div>

                <div class="grid gap-3">
                    <div>
                        <p class="text-sm font-medium leading-none">
                            Reference images <span class="font-normal text-muted-foreground">(optional)</span>
                        </p>
                        <p class="mt-1.5 text-sm text-muted-foreground">
                            Links to images that guide the style. Up to {{ limits.referenceImages }}; each one adds to the price.
                        </p>
                    </div>

                    <div v-for="(_, row) in form.referenceImages" :key="row" class="grid gap-1">
                        <div class="flex gap-2">
                            <Input
                                v-model="form.referenceImages[row]"
                                type="url"
                                inputmode="url"
                                placeholder="https://example.com/style.png"
                                :aria-label="`Reference image ${row + 1}`"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove reference image ${row + 1}`"
                                @click="removeReferenceImage(row)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <InputError :message="errors[`reference_images.${row}`]" />
                    </div>

                    <InputError :message="errors.reference_images" />

                    <Button
                        v-if="form.referenceImages.length < limits.referenceImages"
                        type="button"
                        variant="outline"
                        class="w-fit"
                        @click="addReferenceImage"
                    >
                        <ImagePlus class="size-4" />
                        Add reference image
                    </Button>
                </div>

                <div class="grid gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border" aria-live="polite">
                    <Button type="submit" class="w-fit" :disabled="!canSubmit">
                        <LoaderCircle v-if="submission.status === 'submitting'" class="size-4 animate-spin" />
                        <Sparkles v-else class="size-4" />
                        Generate image
                    </Button>

                    <p v-if="submission.status === 'submitted'" class="text-sm text-green-700 dark:text-green-500">
                        Your image is queued. You'll only be charged once it's ready.
                    </p>
                    <p v-else-if="submission.status === 'failed'" class="text-sm text-red-600 dark:text-red-500">{{ submission.message }}</p>
                </div>
            </form>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border" aria-live="polite">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-muted-foreground">Estimated price</p>
                        <LoaderCircle v-if="refreshing" class="size-4 animate-spin text-muted-foreground" aria-label="Updating price" />
                    </div>

                    <template v-if="quote.status === 'ready'">
                        <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight">
                            {{ formatUsdRange(quote.quote.minCharge, quote.quote.maxCharge) }}
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-muted-foreground">
                            <li>
                                You pay the exact price once the image is ready, never more than
                                <span class="font-medium text-foreground">{{ formatUsd(quote.quote.maxCharge, 4, 'up') }}</span
                                >.
                            </li>
                            <li>Part of the price depends on how much work the model does, which is only known when it finishes.</li>
                            <li>If a generation fails, it's free.</li>
                        </ul>
                    </template>

                    <p v-else-if="quote.status === 'invalid'" class="mt-2 text-sm text-muted-foreground">
                        Fix the highlighted fields to see the price.
                    </p>

                    <p v-else-if="quote.status === 'unavailable'" class="mt-2 text-sm text-red-600 dark:text-red-500">{{ quote.message }}</p>

                    <p v-else class="mt-2 h-9 w-40 animate-pulse rounded-md bg-muted" aria-label="Loading price" />
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
