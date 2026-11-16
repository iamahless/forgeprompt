<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronRight, ImageOff, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import GenerationStatusIcon from '@/components/generations/GenerationStatusIcon.vue';
import { Button } from '@/components/ui/button';
import { formatUsd, formatUsdApprox } from '@/lib/money';
import { store } from '@/routes/generations';
import type { Generation, GenerationStatus } from '@/types';

const props = defineProps<{
    generation: Generation;
}>();

const statusLabels: Record<GenerationStatus, string> = {
    queued: 'Queued',
    processing: 'Processing',
    succeeded: 'Succeeded',
    failed: 'Failed',
};

const imageFailedToLoad = ref(false);

// Pixelforge's image links stop working a day after the generation completes.
const image = computed(() => {
    const { image_url: url, image_url_expires_at: expiresAt } =
        props.generation;

    if (url === null || imageFailedToLoad.value) {
        return null;
    }

    return expiresAt !== null && new Date(expiresAt) <= new Date() ? null : url;
});

const usage = computed(() => {
    const tokens = props.generation.usage;

    if (tokens === null) {
        return [];
    }

    return [
        { label: 'Prompt', count: tokens.input_text_tokens },
        { label: 'Reference images', count: tokens.input_image_tokens },
        { label: 'Image', count: tokens.output_tokens },
        { label: 'Reasoning', count: tokens.reasoning_tokens },
    ];
});

const retrying = ref(false);
const retryError = ref<string>();

// A failed generation was free, so trying again is a new generation with the same settings.
function tryAgain(): void {
    const { prompt, size, quality, reference_images } = props.generation;

    router.post(
        store.url(),
        { prompt, size, quality, reference_images },
        {
            preserveScroll: true,
            onStart: () => (retrying.value = true),
            onError: (errors) => (retryError.value = Object.values(errors)[0]),
            onFinish: () => (retrying.value = false),
        },
    );
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <li :data-status="generation.status">
        <details class="group">
            <summary
                class="flex cursor-pointer list-none items-center gap-3 px-4 py-2 outline-none hover:bg-accent/60 focus-visible:bg-accent/60 [&::-webkit-details-marker]:hidden"
            >
                <GenerationStatusIcon :status="generation.status" />

                <span
                    class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted"
                >
                    <img
                        v-if="generation.status === 'succeeded' && image"
                        :src="image"
                        alt=""
                        loading="lazy"
                        class="size-full object-cover"
                        @error="imageFailedToLoad = true"
                    />
                    <ImageOff
                        v-else-if="generation.status === 'succeeded'"
                        class="size-4 text-muted-foreground"
                    />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">
                        {{ generation.prompt }}
                    </span>
                    <span class="block truncate text-xs text-muted-foreground">
                        <span
                            :class="{
                                'text-destructive':
                                    generation.status === 'failed',
                                'text-amber-600 dark:text-amber-500':
                                    generation.status === 'processing',
                            }"
                            data-test="status"
                        >
                            {{ statusLabels[generation.status] }}
                        </span>
                        <template v-if="generation.status === 'failed'">
                            · {{ generation.error_message }}
                        </template>
                        <template v-else>
                            · {{ generation.size.replace('x', '×') }} ·
                            {{ generation.quality }} ·
                            {{ formatDate(generation.created_at) }}
                        </template>
                    </span>
                </span>

                <span
                    class="hidden w-24 shrink-0 text-right text-xs tabular-nums sm:block"
                >
                    <span class="block text-muted-foreground">Estimated</span>
                    <span class="block" data-test="estimated-price">
                        ≈
                        {{ formatUsdApprox(generation.estimated_price_nanos) }}
                    </span>
                </span>

                <span class="w-28 shrink-0 text-right text-xs tabular-nums">
                    <span class="block text-muted-foreground">Actual</span>
                    <span
                        v-if="generation.status === 'succeeded'"
                        class="block font-medium"
                        data-test="actual-price"
                    >
                        {{ formatUsd(generation.price_nanos ?? 0) }}
                    </span>
                    <span
                        v-else-if="generation.status === 'failed'"
                        class="block"
                        data-test="actual-price"
                    >
                        Not charged
                    </span>
                    <span v-else class="block text-muted-foreground">
                        Pending
                    </span>
                </span>

                <ChevronRight
                    class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90"
                />
            </summary>

            <div
                class="grid gap-6 border-t bg-muted/40 px-4 py-4 md:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]"
            >
                <div
                    class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border bg-background text-center text-sm text-muted-foreground"
                >
                    <a
                        v-if="generation.status === 'succeeded' && image"
                        :href="image"
                        target="_blank"
                        rel="noopener"
                        class="size-full"
                    >
                        <img
                            :src="image"
                            :alt="generation.prompt"
                            loading="lazy"
                            class="size-full object-contain"
                        />
                    </a>
                    <p
                        v-else-if="generation.status === 'succeeded'"
                        class="p-6"
                    >
                        This image's link has expired.
                    </p>
                    <p
                        v-else-if="generation.status === 'failed'"
                        class="p-6 text-destructive"
                    >
                        {{ generation.error_message }}
                    </p>
                    <p v-else class="animate-pulse p-6">
                        {{
                            generation.status === 'processing'
                                ? 'Generating your image…'
                                : 'Waiting in the queue…'
                        }}
                    </p>
                </div>

                <div class="min-w-0 space-y-4 text-sm">
                    <p class="whitespace-pre-wrap">{{ generation.prompt }}</p>

                    <dl
                        class="grid grid-cols-[8rem_1fr] gap-x-4 gap-y-1.5 tabular-nums"
                    >
                        <dt class="text-muted-foreground">Status</dt>
                        <dd>{{ statusLabels[generation.status] }}</dd>

                        <dt class="text-muted-foreground">Size and quality</dt>
                        <dd>
                            {{ generation.size.replace('x', '×') }},
                            {{ generation.quality }}
                        </dd>

                        <template v-if="generation.reference_images.length">
                            <dt class="text-muted-foreground">
                                Reference images
                            </dt>
                            <dd class="min-w-0">
                                <a
                                    v-for="url in generation.reference_images"
                                    :key="url"
                                    :href="url"
                                    target="_blank"
                                    rel="noopener"
                                    class="block truncate underline-offset-2 hover:underline"
                                >
                                    {{ url }}
                                </a>
                            </dd>
                        </template>

                        <dt class="text-muted-foreground">Created</dt>
                        <dd>{{ formatDate(generation.created_at) }}</dd>

                        <template v-if="generation.completed_at">
                            <dt class="text-muted-foreground">Finished</dt>
                            <dd>{{ formatDate(generation.completed_at) }}</dd>
                        </template>

                        <dt class="text-muted-foreground">Estimated cost</dt>
                        <dd>
                            ≈
                            {{
                                formatUsdApprox(
                                    generation.estimated_price_nanos,
                                )
                            }}
                            <span class="text-muted-foreground">
                                (at most
                                {{ formatUsd(generation.max_price_nanos) }})
                            </span>
                        </dd>

                        <dt class="text-muted-foreground">Actual cost</dt>
                        <dd
                            v-if="generation.status === 'succeeded'"
                            class="font-medium"
                        >
                            {{ formatUsd(generation.price_nanos ?? 0) }}
                        </dd>
                        <dd v-else-if="generation.status === 'failed'">
                            $0.00. Failed generations are free.
                        </dd>
                        <dd v-else class="text-muted-foreground">
                            Known when the image is done.
                            {{ formatUsd(generation.max_price_nanos) }} is held
                            until then.
                        </dd>
                    </dl>

                    <div
                        v-if="generation.status === 'failed'"
                        class="space-y-1.5"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="retrying"
                            data-test="try-again-button"
                            @click="tryAgain"
                        >
                            <RotateCcw />
                            Try again
                        </Button>
                        <p v-if="retryError" class="text-xs text-destructive">
                            {{ retryError }}
                        </p>
                    </div>

                    <div v-if="usage.length">
                        <h3 class="mb-1.5 text-xs text-muted-foreground">
                            Tokens used
                        </h3>
                        <dl
                            class="grid max-w-xs grid-cols-[1fr_auto] gap-x-4 gap-y-1 tabular-nums"
                        >
                            <template v-for="row in usage" :key="row.label">
                                <dt>{{ row.label }}</dt>
                                <dd class="text-right">
                                    {{ row.count.toLocaleString() }}
                                </dd>
                            </template>
                        </dl>
                    </div>
                </div>
            </div>
        </details>
    </li>
</template>
