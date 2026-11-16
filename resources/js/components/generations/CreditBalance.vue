<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatUsd } from '@/lib/money';
import { store } from '@/routes/credits';
import type { Credits } from '@/types';

defineProps<{
    credits: Credits;
    packs: number[];
}>();

function buy(amount: number): void {
    router.post(store.url(), { amount }, { preserveScroll: true });
}
</script>

<template>
    <div class="flex items-center gap-3 text-sm">
        <p class="flex items-baseline gap-1.5 tabular-nums">
            <span class="text-muted-foreground">Credits</span>
            <span class="font-medium" data-test="available-credits">
                {{ formatUsd(credits.available) }}
            </span>
            <span
                v-if="credits.reserved > 0"
                class="hidden text-xs text-muted-foreground md:inline"
            >
                + {{ formatUsd(credits.reserved) }} held for images in progress
            </span>
        </p>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    data-test="buy-credits-button"
                >
                    <Plus />
                    Buy credits
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56 text-sm">
                <DropdownMenuLabel
                    class="text-xs font-normal text-muted-foreground"
                >
                    Demo purchase. No payment is taken.
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    v-for="pack in packs"
                    :key="pack"
                    class="text-sm"
                    @select="buy(pack)"
                >
                    Add ${{ pack }}.00
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
