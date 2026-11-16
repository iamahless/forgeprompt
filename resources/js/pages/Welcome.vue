<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import GenerationStatusIcon from '@/components/generations/GenerationStatusIcon.vue';
import { dashboard, login, register } from '@/routes';

const repositoryUrl = 'https://github.com/iamahless/forgeprompt';
</script>

<template>
    <Head title="Image generation with clear pricing" />

    <!-- The landing page is always dark, like the screenshot it shows. -->
    <div class="dark min-h-screen bg-sidebar text-foreground">
        <header
            class="sticky top-0 z-10 border-b bg-sidebar/80 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-14 max-w-6xl items-center justify-between px-6"
            >
                <span class="flex items-center gap-2 text-sm font-medium">
                    <AppLogoIcon class="size-4 fill-current" />
                    {{ $page.props.name }}
                </span>

                <nav
                    class="flex items-center gap-5 text-sm"
                    aria-label="Account"
                >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex h-8 items-center rounded-lg bg-foreground px-3 font-medium text-background transition-colors hover:bg-white"
                    >
                        Open the app
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex h-8 items-center rounded-lg bg-foreground px-3 font-medium text-background transition-colors hover:bg-white"
                        >
                            Sign up
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-6 pt-20 md:pt-28">
                <h1
                    class="max-w-3xl bg-linear-to-b from-white to-white/70 bg-clip-text pb-1 text-5xl leading-[1.04] font-medium tracking-[-0.03em] text-balance text-transparent md:text-6xl"
                >
                    Know what an image costs before you make it
                </h1>
                <p
                    class="mt-6 max-w-lg text-lg leading-relaxed text-muted-foreground"
                >
                    ForgePrompt shows the estimate and the most an image can
                    cost. You pay the real cost when it is done, and nothing
                    when it fails.
                </p>
                <div class="mt-8 flex items-center gap-5 text-sm">
                    <Link
                        :href="$page.props.auth.user ? dashboard() : register()"
                        class="inline-flex h-10 items-center rounded-lg bg-foreground px-4 font-medium text-background transition-colors hover:bg-white"
                    >
                        {{
                            $page.props.auth.user
                                ? 'Open the app'
                                : 'Start generating'
                        }}
                    </Link>
                    <a
                        :href="repositoryUrl"
                        class="font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        View the source
                    </a>
                </div>
            </section>

            <section class="mx-auto mt-16 max-w-6xl px-6 md:mt-20">
                <div
                    class="relative overflow-hidden rounded-t-xl border-x border-t"
                >
                    <img
                        src="/landing/app.webp"
                        width="2880"
                        height="1800"
                        alt="The ForgePrompt app: a prompt form with its estimated cost on the left, and a history of generated images with their status, estimated cost and actual cost on the right."
                        class="w-[56rem] max-w-none md:w-full"
                    />
                    <div
                        class="pointer-events-none absolute inset-x-0 bottom-0 h-1/3 bg-linear-to-t from-sidebar to-transparent"
                    />
                </div>
            </section>

            <section class="border-t">
                <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">
                    <div
                        class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between"
                    >
                        <h2
                            class="max-w-md text-3xl leading-[1.1] font-medium tracking-[-0.022em] text-balance md:text-4xl"
                        >
                            Every price comes with its working
                        </h2>
                        <p class="max-w-sm text-muted-foreground">
                            Image models bill per token, and part of that is
                            only known once an image is done. You see the figure
                            before and after, to the fraction of a cent.
                        </p>
                    </div>

                    <div class="mt-14 grid border-y md:grid-cols-3 md:divide-x">
                        <article class="py-8 md:pr-8">
                            <div class="flex h-36 items-center">
                                <dl
                                    class="w-full divide-y rounded-lg border bg-background text-sm tabular-nums"
                                >
                                    <div class="flex justify-between px-3 py-2">
                                        <dt class="text-muted-foreground">
                                            Estimated cost
                                        </dt>
                                        <dd class="font-medium">≈ $0.0722</dd>
                                    </div>
                                    <div class="flex justify-between px-3 py-2">
                                        <dt class="text-muted-foreground">
                                            Most it can cost
                                        </dt>
                                        <dd>$0.08625</dd>
                                    </div>
                                </dl>
                            </div>
                            <h3 class="mt-6 font-medium">
                                Priced before you submit
                            </h3>
                            <p class="mt-1.5 text-sm text-muted-foreground">
                                The form prices your prompt, size, quality and
                                reference images as you type.
                            </p>
                        </article>

                        <article class="border-t py-8 md:border-t-0 md:px-8">
                            <div class="flex h-36 items-center">
                                <dl
                                    class="w-full divide-y rounded-lg border bg-background text-sm tabular-nums"
                                >
                                    <div class="flex justify-between px-3 py-2">
                                        <dt class="text-muted-foreground">
                                            Provider cost
                                        </dt>
                                        <dd>$0.0472100</dd>
                                    </div>
                                    <div class="flex justify-between px-3 py-2">
                                        <dt class="text-muted-foreground">
                                            Markup, 25%
                                        </dt>
                                        <dd>$0.0118025</dd>
                                    </div>
                                    <div
                                        class="flex justify-between px-3 py-2 font-medium"
                                    >
                                        <dt>You pay</dt>
                                        <dd>$0.0590125</dd>
                                    </div>
                                </dl>
                            </div>
                            <h3 class="mt-6 font-medium">
                                Charged what it cost
                            </h3>
                            <p class="mt-1.5 text-sm text-muted-foreground">
                                When the image is done you pay the provider’s
                                cost plus 25%. Nothing is rounded up.
                            </p>
                        </article>

                        <article class="border-t py-8 md:border-t-0 md:pl-8">
                            <div class="flex h-36 items-center">
                                <div
                                    class="flex w-full items-center gap-3 rounded-lg border bg-background px-3 py-2.5 text-sm"
                                >
                                    <GenerationStatusIcon status="failed" />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate font-medium"
                                        >
                                            A storm over a harbour
                                        </span>
                                        <span
                                            class="block truncate text-xs text-muted-foreground"
                                        >
                                            <span class="text-destructive">
                                                Failed
                                            </span>
                                            · The model could not produce an
                                            image
                                        </span>
                                    </span>
                                    <span
                                        class="shrink-0 text-right text-xs tabular-nums"
                                    >
                                        <span
                                            class="block text-muted-foreground"
                                        >
                                            Actual
                                        </span>
                                        <span class="block">Not charged</span>
                                    </span>
                                </div>
                            </div>
                            <h3 class="mt-6 font-medium">Free when it fails</h3>
                            <p class="mt-1.5 text-sm text-muted-foreground">
                                A failed or rejected generation costs nothing,
                                and the history says why.
                            </p>
                        </article>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-8 text-sm text-muted-foreground"
            >
                <span class="flex items-center gap-2">
                    <AppLogoIcon class="size-4 fill-current" />
                    {{ $page.props.name }}
                </span>
                <span>Images are generated by the Pixelforge API</span>
            </div>
        </footer>
    </div>
</template>
