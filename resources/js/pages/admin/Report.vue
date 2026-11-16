<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { formatUsdFull } from '@/lib/money';
import { report } from '@/routes/admin';
import type { GenerationStatus, TokenUsage } from '@/types';

type Totals = {
    generations: number;
    charged_nanos: number;
    provider_cost_nanos: number;
    profit_nanos: number;
};

type UserTotals = Totals & {
    user: { id: number; name: string; email: string } | null;
};

type PixelforgeUsage = { generations: number; cost_nanos: number };

type ReportGeneration = {
    provider_id: string;
    status: GenerationStatus;
    user: { id: number; name: string; email: string } | null;
    completed_at: string;
    usage: TokenUsage | null;
    provider_cost_nanos: number | null;
    charged_nanos: number | null;
};

type Day = Totals & {
    date: string;
    users: UserTotals[];
    pixelforge: PixelforgeUsage | null;
};

const props = defineProps<{
    from: string;
    to: string;
    days: Day[];
    users: UserTotals[];
    totals: Totals;
    generations: ReportGeneration[];
    pixelforgeTotal: PixelforgeUsage | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin report',
                href: report(),
            },
        ],
    },
});

function matches(ours: Totals, theirs: PixelforgeUsage | null): boolean {
    return (
        ours.provider_cost_nanos === (theirs?.cost_nanos ?? 0) &&
        ours.generations === (theirs?.generations ?? 0)
    );
}

const reconciled = computed(() =>
    props.days.every((day) => matches(day, day.pixelforge)),
);

function tokens(usage: TokenUsage | null): string {
    if (usage === null) {
        return '—';
    }

    return [
        usage.input_text_tokens,
        usage.input_image_tokens,
        usage.output_tokens,
        usage.reasoning_tokens,
    ]
        .map((count) => count.toLocaleString())
        .join(' / ');
}

const summary = computed(() => [
    { label: 'Charged to users', value: props.totals.charged_nanos },
    { label: 'Pixelforge cost', value: props.totals.provider_cost_nanos },
    { label: 'Profit', value: props.totals.profit_nanos },
]);
</script>

<template>
    <Head title="Admin report" />

    <div class="flex flex-1 flex-col text-sm">
        <header class="border-b px-4 py-3">
            <h1 class="text-sm font-medium">Admin report</h1>
            <p class="text-muted-foreground">
                Succeeded generations from {{ from }} to {{ to }}, by the UTC
                day they completed. Failed generations cost nothing and are left
                out.
            </p>
        </header>

        <div
            v-if="pixelforgeTotal === null"
            class="flex items-start gap-2 border-b px-4 py-2.5 text-destructive"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>
                Pixelforge usage is unavailable, so these are only our own
                records. Reload to compare them with what Pixelforge reports.
            </p>
        </div>
        <div
            v-else-if="reconciled"
            class="flex items-start gap-2 border-b px-4 py-2.5"
            data-test="reconciled"
        >
            <CircleCheck class="mt-0.5 size-4 shrink-0 text-primary" />
            <p>
                <span class="font-medium">
                    Provider costs match Pixelforge.
                </span>
                <span class="text-muted-foreground">
                    GET /v1/usage reports
                    {{ formatUsdFull(pixelforgeTotal.cost_nanos) }} for
                    {{ pixelforgeTotal.generations }}
                    {{
                        pixelforgeTotal.generations === 1
                            ? 'generation'
                            : 'generations'
                    }}
                    on this environment's API key, the same as our records on
                    every day.
                </span>
            </p>
        </div>
        <div
            v-else
            class="flex items-start gap-2 border-b px-4 py-2.5 text-destructive"
            data-test="not-reconciled"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>
                <span class="font-medium">
                    Provider costs differ from Pixelforge.
                </span>
                GET /v1/usage reports
                {{ formatUsdFull(pixelforgeTotal.cost_nanos) }} for
                {{ pixelforgeTotal.generations }} generations. The days that
                differ are marked below.
            </p>
        </div>

        <dl
            class="grid grid-cols-2 divide-x border-b tabular-nums lg:grid-cols-4"
        >
            <div v-for="item in summary" :key="item.label" class="px-4 py-3">
                <dt class="text-xs text-muted-foreground">{{ item.label }}</dt>
                <dd class="mt-0.5 text-base font-medium">
                    {{ formatUsdFull(item.value) }}
                </dd>
            </div>
            <div class="px-4 py-3">
                <dt class="text-xs text-muted-foreground">Generations</dt>
                <dd class="mt-0.5 text-base font-medium">
                    {{ totals.generations.toLocaleString() }}
                </dd>
            </div>
        </dl>

        <section aria-labelledby="by-day">
            <h2
                id="by-day"
                class="border-b bg-muted/50 px-4 py-1.5 text-xs font-medium text-muted-foreground"
            >
                By day
            </h2>
            <div class="overflow-x-auto border-b">
                <table class="w-full tabular-nums">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 font-normal">Day (UTC)</th>
                            <th class="px-4 py-2 text-right font-normal">
                                Generations
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Charged to users
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Profit
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Pixelforge reports
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        v-for="day in days"
                        :key="day.date"
                        class="border-b last:border-b-0"
                    >
                        <tr class="font-medium">
                            <td class="px-4 py-2">{{ day.date }}</td>
                            <td class="px-4 py-2 text-right">
                                {{ day.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ formatUsdFull(day.charged_nanos) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ formatUsdFull(day.provider_cost_nanos) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ formatUsdFull(day.profit_nanos) }}
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <template v-if="pixelforgeTotal !== null">
                                    {{
                                        formatUsdFull(
                                            day.pixelforge?.cost_nanos ?? 0,
                                        )
                                    }}
                                    <Badge
                                        :variant="
                                            matches(day, day.pixelforge)
                                                ? 'secondary'
                                                : 'destructive'
                                        "
                                        class="ml-2 rounded-md font-normal"
                                    >
                                        {{
                                            matches(day, day.pixelforge)
                                                ? 'Match'
                                                : 'Differs'
                                        }}
                                    </Badge>
                                </template>
                                <template v-else>—</template>
                            </td>
                        </tr>
                        <tr
                            v-for="row in day.users"
                            :key="row.user?.id ?? 'deleted'"
                            class="text-muted-foreground"
                        >
                            <td class="py-1.5 pr-4 pl-8">
                                {{ row.user?.name ?? 'Deleted user' }}
                                <span v-if="row.user" class="text-xs">
                                    {{ row.user.email }}
                                </span>
                            </td>
                            <td class="px-4 py-1.5 text-right">
                                {{ row.generations }}
                            </td>
                            <td class="px-4 py-1.5 text-right">
                                {{ formatUsdFull(row.charged_nanos) }}
                            </td>
                            <td class="px-4 py-1.5 text-right">
                                {{ formatUsdFull(row.provider_cost_nanos) }}
                            </td>
                            <td class="px-4 py-1.5 text-right">
                                {{ formatUsdFull(row.profit_nanos) }}
                            </td>
                            <td />
                        </tr>
                    </tbody>
                    <tbody v-if="!days.length">
                        <tr>
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No generations have succeeded in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="by-user">
            <h2
                id="by-user"
                class="border-b bg-muted/50 px-4 py-1.5 text-xs font-medium text-muted-foreground"
            >
                By user
            </h2>
            <div class="overflow-x-auto border-b">
                <table class="w-full tabular-nums">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 font-normal">User</th>
                            <th class="px-4 py-2 text-right font-normal">
                                Generations
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Charged
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="row in users"
                            :key="row.user?.id ?? 'deleted'"
                        >
                            <td class="px-4 py-2">
                                <span class="font-medium">
                                    {{ row.user?.name ?? 'Deleted user' }}
                                </span>
                                <span
                                    v-if="row.user"
                                    class="ml-1 text-xs text-muted-foreground"
                                >
                                    {{ row.user.email }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ row.generations }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ formatUsdFull(row.charged_nanos) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ formatUsdFull(row.provider_cost_nanos) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ formatUsdFull(row.profit_nanos) }}
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No users have been charged in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <section aria-labelledby="latest-generations">
            <h2
                id="latest-generations"
                class="border-b bg-muted/50 px-4 py-1.5 text-xs font-medium text-muted-foreground"
            >
                Latest generations
                <span class="font-normal">
                    (the newest {{ generations.length }} that Pixelforge has a
                    record of)
                </span>
            </h2>
            <div class="overflow-x-auto border-b">
                <table class="w-full tabular-nums">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 font-normal">
                                Completed (UTC)
                            </th>
                            <th class="px-4 py-2 font-normal">Pixelforge id</th>
                            <th class="px-4 py-2 font-normal">User</th>
                            <th class="px-4 py-2 font-normal">Status</th>
                            <th class="px-4 py-2 text-right font-normal">
                                Tokens: prompt / references / image / reasoning
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Charged
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="generation in generations"
                            :key="generation.provider_id"
                        >
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{
                                    generation.completed_at
                                        .slice(0, 19)
                                        .replace('T', ' ')
                                }}
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ generation.provider_id }}
                            </td>
                            <td class="px-4 py-2">
                                {{ generation.user?.name ?? 'Deleted user' }}
                            </td>
                            <td
                                class="px-4 py-2 capitalize"
                                :class="{
                                    'text-destructive':
                                        generation.status === 'failed',
                                }"
                            >
                                {{ generation.status }}
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                {{ tokens(generation.usage) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{
                                    formatUsdFull(
                                        generation.provider_cost_nanos ?? 0,
                                    )
                                }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{
                                    formatUsdFull(generation.charged_nanos ?? 0)
                                }}
                            </td>
                        </tr>
                        <tr v-if="!generations.length">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No generations have finished in this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
