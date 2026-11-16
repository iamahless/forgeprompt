<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Welcome" />

    <div
        class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-center text-foreground"
    >
        <AppLogoIcon class="size-10 fill-current" />
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ $page.props.name }}
            </h1>
            <p class="max-w-md text-muted-foreground">
                Generate images from a prompt. You see the price before you
                submit, pay only for what the image really cost, and never pay
                for one that fails.
            </p>
        </div>
        <div class="flex gap-3">
            <Button v-if="$page.props.auth.user" as-child>
                <Link :href="dashboard()">Start generating</Link>
            </Button>
            <template v-else>
                <Button as-child>
                    <Link :href="register()">Create an account</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="login()">Log in</Link>
                </Button>
            </template>
        </div>
    </div>
</template>
