<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Plus, Sparkles, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatUsd, formatUsdApprox } from '@/lib/money';
import { quote as quoteRoute, store } from '@/routes/generations';
import type { Credits, GenerationOptions, Quote } from '@/types';

const props = defineProps<{
    options: GenerationOptions;
    credits: Credits;
}>();

const form = useForm({
    prompt: '',
    size: props.options.sizes[0],
    quality: 'medium',
    reference_images: [] as string[],
});

const referenceImageCount = computed(
    () => form.reference_images.filter((url) => url.trim() !== '').length,
);

// The server also reports errors that aren't form fields.
const errors = computed(() => form.errors as Record<string, string>);

const submitError = computed(
    () => errors.value.credits || errors.value.pricing || errors.value.throttle,
);

const referenceImagesError = computed(() => {
    const field = Object.keys(errors.value).find((key) =>
        key.startsWith('reference_images'),
    );

    return field ? errors.value[field] : undefined;
});

const quote = ref<Quote | null>(null);
const quoteUnavailable = ref(false);
const quoteRequest = useHttp<
    {
        prompt: string;
        size: string;
        quality: string;
        reference_image_count: number;
    },
    Quote
>({
    prompt: '',
    size: form.size,
    quality: form.quality,
    reference_image_count: 0,
});

const referenceImagePlaceholder = 'https://example.com/style.png';

let latestQuoteRequest = 0;

// The server prices the quote, so the number shown is worked out by the same code that charges for it.
async function refreshQuote(): Promise<void> {
    const request = ++latestQuoteRequest;

    quoteRequest.prompt = form.prompt;
    quoteRequest.size = form.size;
    quoteRequest.quality = form.quality;
    quoteRequest.reference_image_count = referenceImageCount.value;

    try {
        const result = await quoteRequest.post(quoteRoute.url());

        if (request === latestQuoteRequest) {
            quote.value = result;
            quoteUnavailable.value = false;
        }
    } catch {
        if (request === latestQuoteRequest) {
            quote.value = null;
            quoteUnavailable.value = true;
        }
    }
}

watchDebounced(
    () => [form.prompt, form.size, form.quality, referenceImageCount.value],
    refreshQuote,
    { debounce: 300, immediate: true },
);

const canAfford = computed(
    () =>
        quote.value === null ||
        props.credits.available >= quote.value.max_price_nanos,
);

function sizeLabel(size: string): string {
    const [width, height] = size.split('x').map(Number);
    const shape =
        width === height ? 'Square' : width > height ? 'Landscape' : 'Portrait';

    return `${shape} ${width}×${height}`;
}

function setReferenceImage(index: number, url: string | number): void {
    form.reference_images[index] = String(url);
}

function removeReferenceImage(index: number): void {
    form.reference_images.splice(index, 1);
}

function submit(): void {
    form.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <form class="space-y-5 text-sm" @submit.prevent="submit">
        <div class="grid gap-1.5">
            <div class="flex items-baseline justify-between">
                <Label for="prompt" class="text-xs text-muted-foreground">
                    Prompt
                </Label>
                <span class="text-xs text-muted-foreground tabular-nums">
                    {{ form.prompt.length.toLocaleString() }} /
                    {{ options.maxPromptLength.toLocaleString() }}
                </span>
            </div>
            <textarea
                id="prompt"
                v-model="form.prompt"
                name="prompt"
                rows="5"
                required
                :maxlength="options.maxPromptLength"
                placeholder="A lighthouse on a cliff at dawn, oil painting"
                class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
            />
            <InputError :message="form.errors.prompt" />
        </div>

        <fieldset class="grid gap-1.5">
            <legend class="mb-1.5 text-xs font-medium text-muted-foreground">
                Size
            </legend>
            <div
                class="grid grid-cols-3 gap-0.5 rounded-md border bg-muted p-0.5"
            >
                <button
                    v-for="size in options.sizes"
                    :key="size"
                    type="button"
                    :aria-pressed="form.size === size"
                    class="rounded-[5px] px-2 py-1 text-center outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                    :class="
                        form.size === size
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="form.size = size"
                >
                    <span class="block font-medium">
                        {{ sizeLabel(size).split(' ')[0] }}
                    </span>
                    <span class="block text-xs opacity-70">
                        {{ sizeLabel(size).split(' ')[1] }}
                    </span>
                </button>
            </div>
            <InputError :message="form.errors.size" />
        </fieldset>

        <fieldset class="grid gap-1.5">
            <legend class="mb-1.5 text-xs font-medium text-muted-foreground">
                Quality
            </legend>
            <div
                class="grid grid-cols-3 gap-0.5 rounded-md border bg-muted p-0.5"
            >
                <button
                    v-for="quality in options.qualities"
                    :key="quality"
                    type="button"
                    :aria-pressed="form.quality === quality"
                    class="rounded-[5px] px-2 py-1 font-medium capitalize outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                    :class="
                        form.quality === quality
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="form.quality = quality"
                >
                    {{ quality }}
                </button>
            </div>
            <InputError :message="form.errors.quality" />
        </fieldset>

        <div class="grid gap-1.5">
            <Label class="text-xs text-muted-foreground">
                Reference images
                <span class="font-normal">(optional)</span>
            </Label>
            <div
                v-for="(url, index) in form.reference_images"
                :key="index"
                class="flex gap-1"
            >
                <Input
                    :model-value="url"
                    type="url"
                    class="h-8 text-sm"
                    :placeholder="referenceImagePlaceholder"
                    :aria-label="`Reference image URL ${index + 1}`"
                    @update:model-value="setReferenceImage(index, $event)"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Remove reference image ${index + 1}`"
                    @click="removeReferenceImage(index)"
                >
                    <X />
                </Button>
            </div>
            <Button
                v-if="form.reference_images.length < options.maxReferenceImages"
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                @click="form.reference_images.push('')"
            >
                <Plus />
                Add an image URL
            </Button>
            <p class="text-xs text-muted-foreground">
                Up to {{ options.maxReferenceImages }} links to guide the style.
                Each one adds to the cost.
            </p>
            <InputError :message="referenceImagesError" />
        </div>

        <div class="grid gap-1.5" aria-live="polite" data-test="quote">
            <dl class="divide-y rounded-md border tabular-nums">
                <div class="flex items-center justify-between px-3 py-2">
                    <dt class="text-muted-foreground">Estimated cost</dt>
                    <dd
                        v-if="quote"
                        class="font-medium"
                        data-test="estimated-price"
                    >
                        ≈ {{ formatUsdApprox(quote.estimated_price_nanos) }}
                    </dd>
                    <dd v-else-if="quoteUnavailable" class="text-destructive">
                        Unavailable
                    </dd>
                    <dd v-else><Skeleton class="h-4 w-16" /></dd>
                </div>
                <div class="flex items-center justify-between px-3 py-2">
                    <dt class="text-muted-foreground">Most it can cost</dt>
                    <dd v-if="quote" data-test="max-price">
                        {{ formatUsd(quote.max_price_nanos) }}
                    </dd>
                    <dd v-else-if="quoteUnavailable">
                        <button
                            type="button"
                            class="underline underline-offset-2"
                            @click="refreshQuote"
                        >
                            Try again
                        </button>
                    </dd>
                    <dd v-else><Skeleton class="h-4 w-20" /></dd>
                </div>
            </dl>
            <p class="text-xs text-muted-foreground">
                You pay the real cost once the image is done. It depends on how
                much the model thinks before drawing, so it is only known then.
                Failed generations are free.
            </p>
        </div>

        <Alert v-if="submitError" variant="destructive" class="text-sm">
            <AlertDescription>
                {{ submitError }}
            </AlertDescription>
        </Alert>
        <Alert v-else-if="quote && !canAfford" class="text-sm">
            <AlertDescription>
                You need {{ formatUsd(quote.max_price_nanos) }} available to
                cover the most this could cost, and you have
                {{ formatUsd(credits.available) }}. Buy credits to continue.
            </AlertDescription>
        </Alert>

        <Button
            type="submit"
            size="sm"
            class="w-full text-sm"
            :disabled="form.processing || !canAfford || quoteUnavailable"
            data-test="generate-button"
        >
            <Spinner v-if="form.processing" />
            <Sparkles v-else />
            Generate
        </Button>
    </form>
</template>
