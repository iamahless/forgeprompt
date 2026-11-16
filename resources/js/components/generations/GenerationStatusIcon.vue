<script setup lang="ts">
import { CircleCheck, CircleDashed, CircleX, LoaderCircle } from '@lucide/vue';
import type { GenerationStatus } from '@/types';

defineProps<{
    status: GenerationStatus;
}>();
</script>

<template>
    <CircleDashed
        v-if="status === 'queued'"
        class="size-4 shrink-0 text-muted-foreground"
    />
    <LoaderCircle
        v-else-if="status === 'processing'"
        class="size-4 shrink-0 animate-spin text-amber-500"
    />
    <CircleCheck
        v-else-if="status === 'succeeded'"
        class="size-4 shrink-0 text-primary"
    />
    <CircleX v-else class="size-4 shrink-0 text-destructive" />
</template>
