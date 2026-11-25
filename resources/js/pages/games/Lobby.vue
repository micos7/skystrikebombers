<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Bot,
    Crosshair,
    Globe,
    Link2,
    Plane,
    Radar,
    Shield,
    Swords,
    Trophy,
    UserCheck,
    Zap,
} from '@lucide/vue';
import { ref } from 'vue';
import {
    join,
    joinByLink,
    show,
} from '@/actions/App/Http/Controllers/Game/GameController';
import {
    store,
    updateCallsign,
} from '@/actions/App/Http/Controllers/Game/LobbyController';
import type { GameModeValue } from '@/types/game';

interface ModeOption {
    value: GameModeValue;
    label: string;
    description: string;
    grid_size: number;
    plane_count: number;
    default_turn_timer_seconds: number | null;
    default_extra_turn_on_kill: boolean;
}

interface PublicGameItem {
    code: string;
    mode: GameModeValue;
    mode_label: string;
    grid_size: number;
    host_callsign: string;
    turn_timer_seconds: number | null;
    extra_turn_on_kill: boolean;
    created_at: string | null;
}

interface MyGameItem {
    code: string;
    mode_label: string;
    status: string;
    is_vs_ai: boolean;
    opponent_callsign: string;
    is_winner: boolean;
    updated_at: string | null;
}

const props = defineProps<{
    identity: {
        id: string;
        callsign: string;
        is_guest: boolean;
        wins: number;
        losses: number;
    };
    modes: ModeOption[];
    publicGames: PublicGameItem[];
    myGames: MyGameItem[];
}>();

const createForm = useForm({
    mode: 'tactical' as GameModeValue,
    is_public: false,
    is_vs_ai: false,
    turn_timer_seconds: 60 as number | null,
    extra_turn_on_kill: false,
});

const callsignForm = useForm({
    callsign: props.identity.callsign,
});

const joinCode = ref('');
const matchType = ref<'private' | 'public' | 'ai'>('private');

function selectMode(mode: ModeOption): void {
    createForm.mode = mode.value;
    createForm.turn_timer_seconds = mode.default_turn_timer_seconds;
    createForm.extra_turn_on_kill = mode.default_extra_turn_on_kill;
}

function submitCreate(): void {
    createForm.is_public = matchType.value === 'public';
    createForm.is_vs_ai = matchType.value === 'ai';
    createForm.post(store.url());
}

function submitCallsign(): void {
    callsignForm.post(updateCallsign.url(), {
        preserveScroll: true,
    });
}

function submitJoinCode(): void {
    const clean = joinCode.value.trim().toUpperCase();

    if (!clean) {
        return;
    }

    router.visit(joinByLink.url(clean));
}

function joinPublicGame(code: string): void {
    router.post(join.url(code));
}
</script>

<template>
    <div
        class="min-h-screen bg-slate-950 text-slate-100 selection:bg-emerald-500/30"
    >
        <Head title="Command Center — SkyStrike Bombers" />

        <!-- Subtle radar grid backdrop -->
        <div
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.12),transparent_55%),linear-gradient(to_right,rgba(16,185,129,0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgba(16,185,129,0.04)_1px,transparent_1px)] bg-[size:100%_100%,40px_40px,40px_40px]"
        />

        <div class="relative mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <header
                class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-emerald-500/20 pb-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl border border-emerald-400/40 bg-emerald-500/15 text-emerald-300 shadow-lg shadow-emerald-500/10"
                    >
                        <Radar class="h-6 w-6" />
                    </div>
                    <div>
                        <h1
                            class="font-mono text-xl font-bold tracking-wider text-white uppercase sm:text-2xl"
                        >
                            SkyStrike Bombers
                        </h1>
                        <p class="text-xs text-slate-400">
                            Real-Time Tactical Airspace Warfare • Guest &
                            Squadron PvP
                        </p>
                    </div>
                </div>

                <!-- Pilot Identity Card -->
                <div
                    class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/90 px-3.5 py-2"
                >
                    <div class="flex items-center gap-2 text-xs">
                        <UserCheck class="h-4 w-4 text-emerald-400" />
                        <span
                            class="rounded bg-slate-800 px-1.5 py-0.5 font-mono text-[10px] text-slate-300 uppercase"
                        >
                            {{ identity.is_guest ? 'Guest Pilot' : 'Account' }}
                        </span>
                        <span
                            v-if="identity.wins > 0 || identity.losses > 0"
                            class="font-mono text-[11px] text-emerald-300"
                        >
                            {{ identity.wins }}W / {{ identity.losses }}L
                        </span>
                    </div>

                    <form
                        v-if="identity.is_guest"
                        class="flex items-center gap-1.5"
                        @submit.prevent="submitCallsign"
                    >
                        <input
                            v-model="callsignForm.callsign"
                            type="text"
                            maxlength="24"
                            class="w-32 rounded border border-slate-700 bg-slate-950 px-2 py-1 font-mono text-xs text-white focus:border-emerald-400 focus:outline-none"
                            placeholder="Callsign"
                        />
                        <button
                            type="submit"
                            :disabled="callsignForm.processing"
                            class="rounded bg-slate-800 px-2 py-1 font-mono text-[11px] text-emerald-300 transition hover:bg-slate-700"
                        >
                            Save
                        </button>
                    </form>
                    <span
                        v-else
                        class="font-mono text-xs font-semibold text-white"
                    >
                        {{ identity.callsign }}
                    </span>

                    <Link
                        v-if="identity.is_guest"
                        href="/login"
                        class="font-mono text-[11px] text-slate-400 underline-offset-4 hover:text-emerald-300 hover:underline"
                    >
                        Sign in
                    </Link>
                </div>
            </header>

            <!-- Main Grid -->
            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Left Column: Launch Mission -->
                <section class="space-y-6 lg:col-span-7">
                    <div
                        class="rounded-2xl border border-emerald-500/25 bg-slate-900/85 p-5 shadow-xl shadow-black/40 backdrop-blur"
                    >
                        <div class="mb-4 flex items-center gap-2">
                            <Swords class="h-5 w-5 text-emerald-400" />
                            <h2
                                class="font-mono text-sm font-bold tracking-widest text-emerald-300 uppercase"
                            >
                                Launch New Mission
                            </h2>
                        </div>

                        <!-- Game Mode Selector -->
                        <div class="mb-5 grid gap-2.5 sm:grid-cols-3">
                            <button
                                v-for="mode in modes"
                                :key="mode.value"
                                type="button"
                                class="flex flex-col rounded-xl border p-3 text-left transition"
                                :class="
                                    createForm.mode === mode.value
                                        ? 'border-emerald-400 bg-emerald-500/15 ring-1 ring-emerald-400/50'
                                        : 'border-slate-800 bg-slate-950/70 hover:border-slate-600'
                                "
                                @click="selectMode(mode)"
                            >
                                <div
                                    class="mb-1 flex items-center justify-between"
                                >
                                    <span class="text-xs font-bold text-white">
                                        {{ mode.label }}
                                    </span>
                                    <span
                                        class="rounded bg-slate-800 px-1.5 py-0.5 font-mono text-[10px] text-emerald-300"
                                    >
                                        {{ mode.grid_size }}×{{
                                            mode.grid_size
                                        }}
                                    </span>
                                </div>
                                <p
                                    class="text-[11px] leading-relaxed text-slate-400"
                                >
                                    {{ mode.description }}
                                </p>
                            </button>
                        </div>

                        <!-- Match Type -->
                        <div class="mb-5">
                            <label
                                class="mb-2 block font-mono text-[11px] tracking-wider text-slate-400 uppercase"
                            >
                                Match Type
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    class="flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2.5 text-xs font-semibold transition"
                                    :class="
                                        matchType === 'private'
                                            ? 'border-amber-400 bg-amber-500/15 text-amber-200'
                                            : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-700'
                                    "
                                    @click="matchType = 'private'"
                                >
                                    <Link2 class="h-4 w-4" />
                                    Invite Link
                                </button>
                                <button
                                    type="button"
                                    class="flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2.5 text-xs font-semibold transition"
                                    :class="
                                        matchType === 'public'
                                            ? 'border-emerald-400 bg-emerald-500/15 text-emerald-200'
                                            : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-700'
                                    "
                                    @click="matchType = 'public'"
                                >
                                    <Globe class="h-4 w-4" />
                                    Public Lobby
                                </button>
                                <button
                                    type="button"
                                    class="flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2.5 text-xs font-semibold transition"
                                    :class="
                                        matchType === 'ai'
                                            ? 'border-cyan-400 bg-cyan-500/15 text-cyan-200'
                                            : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-700'
                                    "
                                    @click="matchType = 'ai'"
                                >
                                    <Bot class="h-4 w-4" />
                                    vs AI Bot
                                </button>
                            </div>
                        </div>

                        <!-- Rules -->
                        <div class="mb-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1.5 block font-mono text-[11px] tracking-wider text-slate-400 uppercase"
                                >
                                    Turn Timer
                                </label>
                                <select
                                    v-model="createForm.turn_timer_seconds"
                                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-xs text-white focus:border-emerald-400 focus:outline-none"
                                >
                                    <option :value="25">25s (Blitz)</option>
                                    <option :value="45">45s (Fast)</option>
                                    <option :value="60">60s (Standard)</option>
                                    <option :value="90">90s (Tactical)</option>
                                    <option :value="null">Unlimited</option>
                                </select>
                            </div>

                            <div class="flex items-end">
                                <label
                                    class="flex w-full cursor-pointer items-center justify-between rounded-lg border border-slate-800 bg-slate-950/70 px-3 py-2"
                                >
                                    <span class="text-xs text-slate-200">
                                        Bonus Turn on Cockpit Kill
                                    </span>
                                    <input
                                        v-model="createForm.extra_turn_on_kill"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-600 bg-slate-900 accent-emerald-500"
                                    />
                                </label>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="createForm.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-3 font-mono text-sm font-bold tracking-wider text-slate-950 uppercase shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400 disabled:opacity-50"
                            @click="submitCreate"
                        >
                            <Plane class="h-4 w-4" />
                            {{
                                matchType === 'ai'
                                    ? 'Deploy vs AI Commander'
                                    : matchType === 'public'
                                      ? 'Open Public Mission'
                                      : 'Create Invite Mission'
                            }}
                        </button>
                    </div>

                    <!-- Field Manual Highlights -->
                    <div
                        class="grid gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:grid-cols-3"
                    >
                        <div class="space-y-1">
                            <div
                                class="flex items-center gap-1.5 font-mono text-xs font-semibold text-amber-300"
                            >
                                <Crosshair class="h-3.5 w-3.5" />
                                Cockpit = Instant Kill
                            </div>
                            <p class="text-[11px] text-slate-400">
                                Strike an aircraft's cockpit to down the entire
                                plane at once. Heavy Stratofortresses carry 2-HP
                                armored cockpits!
                            </p>
                        </div>
                        <div class="space-y-1">
                            <div
                                class="flex items-center gap-1.5 font-mono text-xs font-semibold text-violet-300"
                            >
                                <Shield class="h-3.5 w-3.5" />
                                Stealth & Armor
                            </div>
                            <p class="text-[11px] text-slate-400">
                                Stealth Bombers are invisible to Radar Recon
                                sweeps. Only direct hits reveal them.
                            </p>
                        </div>
                        <div class="space-y-1">
                            <div
                                class="flex items-center gap-1.5 font-mono text-xs font-semibold text-cyan-300"
                            >
                                <Zap class="h-3.5 w-3.5" />
                                5 Tactical Weapons
                            </div>
                            <p class="text-[11px] text-slate-400">
                                Combine SAMs, 3×3 Radar Scans, (+) Cluster
                                Bombs, 1×4 Carpet Runs, and armor-piercing EMPs.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Right Column: Join by Code, Public Lobby, My Missions -->
                <section class="space-y-6 lg:col-span-5">
                    <!-- Join by Code -->
                    <div
                        class="rounded-2xl border border-slate-800 bg-slate-900/85 p-4 shadow-lg"
                    >
                        <h3
                            class="mb-2.5 font-mono text-xs font-bold tracking-widest text-slate-300 uppercase"
                        >
                            Join by Mission Code
                        </h3>
                        <form
                            class="flex gap-2"
                            @submit.prevent="submitJoinCode"
                        >
                            <input
                                v-model="joinCode"
                                type="text"
                                placeholder="SKY-7K9P"
                                class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-sm tracking-wider text-white uppercase placeholder-slate-500 focus:border-emerald-400 focus:outline-none"
                            />
                            <button
                                type="submit"
                                class="rounded-lg bg-slate-800 px-4 py-2 font-mono text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500 hover:text-slate-950"
                            >
                                Join
                            </button>
                        </form>
                    </div>

                    <!-- Public Open Missions -->
                    <div
                        class="rounded-2xl border border-slate-800 bg-slate-900/85 p-4 shadow-lg"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <h3
                                class="font-mono text-xs font-bold tracking-widest text-emerald-300 uppercase"
                            >
                                Open Public Missions
                            </h3>
                            <span class="font-mono text-[11px] text-slate-400">
                                {{ publicGames.length }} live
                            </span>
                        </div>

                        <div
                            v-if="publicGames.length === 0"
                            class="rounded-xl border border-dashed border-slate-800 py-6 text-center font-mono text-xs text-slate-500"
                        >
                            No open public missions. Launch one to host!
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="game in publicGames"
                                :key="game.code"
                                class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/75 px-3 py-2.5"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-mono text-xs font-bold text-white"
                                        >
                                            {{ game.host_callsign }}
                                        </span>
                                        <span
                                            class="rounded bg-emerald-500/15 px-1.5 py-0.5 font-mono text-[10px] text-emerald-300"
                                        >
                                            {{ game.mode_label }}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-0.5 font-mono text-[10px] text-slate-400"
                                    >
                                        {{ game.code }} •
                                        {{ game.grid_size }}×{{
                                            game.grid_size
                                        }}
                                        • {{ game.created_at }}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-lg bg-emerald-500/20 px-3 py-1.5 font-mono text-xs font-semibold text-emerald-300 ring-1 ring-emerald-500/40 transition hover:bg-emerald-500 hover:text-slate-950"
                                    @click="joinPublicGame(game.code)"
                                >
                                    Engage
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- My Active / Recent Missions -->
                    <div
                        v-if="myGames.length > 0"
                        class="rounded-2xl border border-slate-800 bg-slate-900/85 p-4 shadow-lg"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <Trophy class="h-4 w-4 text-amber-400" />
                            <h3
                                class="font-mono text-xs font-bold tracking-widest text-slate-300 uppercase"
                            >
                                Your Missions
                            </h3>
                        </div>

                        <div class="space-y-2">
                            <Link
                                v-for="game in myGames"
                                :key="game.code"
                                :href="show.url(game.code)"
                                class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-xs transition hover:border-emerald-500/40"
                            >
                                <div>
                                    <div class="font-semibold text-slate-100">
                                        vs {{ game.opponent_callsign }}
                                    </div>
                                    <div
                                        class="font-mono text-[10px] text-slate-400"
                                    >
                                        {{ game.code }} • {{ game.mode_label }}
                                    </div>
                                </div>
                                <span
                                    class="rounded px-2 py-0.5 font-mono text-[10px] font-semibold uppercase"
                                    :class="
                                        game.status === 'finished'
                                            ? game.is_winner
                                                ? 'bg-emerald-500/20 text-emerald-300'
                                                : 'bg-rose-500/20 text-rose-300'
                                            : 'bg-amber-500/20 text-amber-200'
                                    "
                                >
                                    {{
                                        game.status === 'finished'
                                            ? game.is_winner
                                                ? 'Victory'
                                                : 'Defeat'
                                            : game.status
                                    }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
