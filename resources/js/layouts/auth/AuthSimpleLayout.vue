<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Radar } from '@lucide/vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="dark relative flex min-h-svh flex-col items-center justify-center bg-slate-950 p-6 text-slate-100 selection:bg-emerald-500/30 md:p-10"
    >
        <!-- Subtle tactical radar grid backdrop -->
        <div
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.14),transparent_55%),linear-gradient(to_right,rgba(16,185,129,0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgba(16,185,129,0.04)_1px,transparent_1px)] bg-[size:100%_100%,40px_40px,40px_40px]"
        />

        <div class="relative w-full max-w-md space-y-5">
            <!-- Brand Header -->
            <div class="flex flex-col items-center gap-3 text-center">
                <Link
                    :href="home()"
                    class="group flex items-center gap-3 transition"
                >
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl border border-emerald-400/40 bg-emerald-500/15 text-emerald-300 shadow-lg shadow-emerald-500/15 transition group-hover:border-emerald-300 group-hover:bg-emerald-500/25"
                    >
                        <Radar class="h-6 w-6" />
                    </div>
                    <div class="text-left">
                        <div
                            class="font-mono text-lg font-bold tracking-wider text-white uppercase"
                        >
                            SkyStrike Bombers
                        </div>
                        <div
                            class="font-mono text-[10px] tracking-widest text-emerald-300/80 uppercase"
                        >
                            Tactical Airspace Command
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Tactical Auth Card -->
            <div
                class="rounded-2xl border border-emerald-500/25 bg-slate-900/85 p-6 shadow-2xl shadow-black/50 backdrop-blur sm:p-7"
            >
                <div class="mb-6 space-y-1.5 text-center">
                    <h1
                        class="font-mono text-base font-bold tracking-wider text-white uppercase"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-xs leading-relaxed text-slate-400">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>

            <!-- Return to Lobby -->
            <div class="flex items-center justify-center">
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-1.5 font-mono text-xs text-slate-400 transition hover:text-emerald-300"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    Return to Command Center (Play as Guest)
                </Link>
            </div>
        </div>
    </div>
</template>
