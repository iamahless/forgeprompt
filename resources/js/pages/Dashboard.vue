<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import CreditBalance from '@/components/generations/CreditBalance.vue';
import GenerationForm from '@/components/generations/GenerationForm.vue';
import GenerationRow from '@/components/generations/GenerationRow.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type {
    Credits,
    Generation,
    GenerationOptions,
    Paginated,
} from '@/types';

const props = defineProps<{
    generations: Paginated<Generation>;
    credits: Credits;
    creditPacks: number[];
    options: GenerationOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Generate',
                href: dashboard(),
            },
        ],
    },
});

const hasGenerationsInFlight = computed(() =>
    props.generations.data.some(
        (generation) =>
            generation.status === 'queued' ||
            generation.status === 'processing',
    ),
);

// Statuses change on the server when Pixelforge's webhooks arrive, so only poll while something is unfinished.
const { start, stop } = usePoll(
    2500,
    { only: ['generations', 'credits'] },
    { autoStart: false },
);

watch(hasGenerationsInFlight, (inFlight) => (inFlight ? start() : stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="Generate" />

    <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
        <section
            class="shrink-0 border-b lg:w-[22rem] lg:border-r lg:border-b-0"
            aria-labelledby="new-generation"
        >
            <div class="p-4 lg:sticky lg:top-0">
                <h1 id="new-generation" class="mb-4 text-sm font-medium">
                    New image
                </h1>
                <GenerationForm :options="options" :credits="credits" />
            </div>
        </section>

        <section class="flex min-w-0 flex-1 flex-col" aria-labelledby="history">
            <header
                class="flex min-h-11 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b px-4 py-2"
            >
                <h2 id="history" class="text-sm font-medium">
                    History
                    <span class="ml-1 font-normal text-muted-foreground">
                        {{ generations.meta.total.toLocaleString() }}
                    </span>
                </h2>
                <CreditBalance :credits="credits" :packs="creditPacks" />
            </header>

            <ul v-if="generations.data.length" class="divide-y border-b">
                <GenerationRow
                    v-for="generation in generations.data"
                    :key="generation.id"
                    :generation="generation"
                />
            </ul>
            <p
                v-else
                class="flex flex-1 items-center justify-center p-10 text-center text-sm text-muted-foreground"
            >
                No images yet. Write a prompt and they will show up here with
                their status and cost.
            </p>

            <nav
                v-if="generations.meta.last_page > 1"
                class="flex items-center justify-between px-4 py-2 text-sm"
                aria-label="History pages"
            >
                <Button
                    v-if="generations.links.prev"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="generations.links.prev">Newer</Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled>
                    Newer
                </Button>
                <span class="text-muted-foreground tabular-nums">
                    Page {{ generations.meta.current_page }} of
                    {{ generations.meta.last_page }}
                </span>
                <Button
                    v-if="generations.links.next"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="generations.links.next">Older</Link>
                </Button>
                <Button v-else variant="outline" size="sm" disabled>
                    Older
                </Button>
            </nav>
        </section>
    </div>
</template>
