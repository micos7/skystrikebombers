<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Copy,
    Flag,
    Plane,
    Radar,
    RefreshCw,
    RotateCw,
    Shuffle,
    Trash2,
    Trophy,
    Volume2,
    VolumeX,
    Wifi,
    WifiOff,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    fire,
    forfeit,
    placeFleet,
    rematch,
} from '@/actions/App/Http/Controllers/Game/GameActionController';
import { store as sendChatMessage } from '@/actions/App/Http/Controllers/Game/GameChatController';
import {
    show,
    state as fetchGameState,
} from '@/actions/App/Http/Controllers/Game/GameController';
import { home } from '@/routes';
import EnemyFleetHud from '@/components/game/EnemyFleetHud.vue';
import GameChat from '@/components/game/GameChat.vue';
import TacticalGrid from '@/components/game/TacticalGrid.vue';
import WeaponSelector from '@/components/game/WeaponSelector.vue';
import { getEcho } from '@/lib/echo';
import {
    computePlaneCells,
    computeWeaponFootprint,
    formatCoordinate,
    generateRandomDraftFleet,
    isPlacementValid,
    nextOrientation,
} from '@/lib/planeGeometry';
import { isSoundMuted, setSoundMuted, soundFx } from '@/lib/sound';
import type {
    ChatMessage,
    DraftPlacement,
    GameStatePayload,
    MissileTypeValue,
    OrientationValue,
    PlaneState,
} from '@/types/game';

const props = defineProps<{
    initialGame: GameStatePayload;
}>();

const game = ref<GameStatePayload>(props.initialGame);
const errorBanner = ref<string | null>(null);
const copiedInvite = ref(false);
const muted = ref(isSoundMuted());
const isBusy = ref(false);
const wsConnected = ref(false);
const onlineSlots = ref<number[]>([]);

// Fleet placement state
const draftPlanes = ref<DraftPlacement[]>([]);
const selectedRosterIndex = ref<number | null>(0);
const placementOrientation = ref<OrientationValue>('N');
const hoverCoord = ref<{ x: number; y: number } | null>(null);

// Combat state
const selectedMissile = ref<MissileTypeValue>('standard');
const carpetOrientation = ref<'H' | 'V'>('H');
const turnCountdown = ref<number | null>(
    props.initialGame.turn_seconds_remaining,
);

let countdownInterval: ReturnType<typeof setInterval> | null = null;
let fallbackPollInterval: ReturnType<typeof setInterval> | null = null;

function readCsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function apiPost<T>(url: string, payload: unknown = {}): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': readCsrfToken(),
        },
        body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message ?? 'Action failed.');
    }

    return data as T;
}

async function refreshState(): Promise<void> {
    try {
        const res = await fetch(fetchGameState.url(game.value.code), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (res.ok) {
            const next = (await res.json()) as GameStatePayload;
            game.value = next;
            turnCountdown.value = next.turn_seconds_remaining;
        }
    } catch {
        // Ignore transient network errors
    }
}

const opponent = computed(() =>
    game.value.players.find((p) => p.slot !== game.value.viewer.slot),
);

const me = computed(() =>
    game.value.players.find((p) => p.slot === game.value.viewer.slot),
);

const opponentOnline = computed(() => {
    if (game.value.is_vs_ai) {
        return true;
    }

    if (!opponent.value) {
        return false;
    }

    return onlineSlots.value.includes(opponent.value.slot);
});

const draftAsPlaneStates = computed<PlaneState[]>(() =>
    draftPlanes.value.map((d) => ({
        id: d.id,
        plane_type: d.plane_type,
        label: d.plane_type,
        origin_x: d.origin_x,
        origin_y: d.origin_y,
        orientation: d.orientation,
        cells: computePlaneCells(
            d.plane_type,
            d.origin_x,
            d.origin_y,
            d.orientation,
        ),
        hit_cells: [],
        cockpit_hp: d.plane_type === 'heavy' ? 2 : 1,
        max_cockpit_hp: d.plane_type === 'heavy' ? 2 : 1,
        is_destroyed: false,
        destroyed_at_turn: null,
    })),
);

const placedRosterIndices = computed(() => {
    const map = new Set<number>();

    for (const d of draftPlanes.value) {
        const idx = Number(d.id.split('-').pop());

        if (!Number.isNaN(idx)) {
            map.add(idx);
        }
    }

    return map;
});

const placementOccupiedKeys = computed(() => {
    const set = new Set<string>();

    for (const plane of draftAsPlaneStates.value) {
        for (const c of plane.cells) {
            set.add(`${c.x},${c.y}`);
        }
    }

    return set;
});

const placementGhost = computed(() => {
    if (
        game.value.status !== 'placing' ||
        game.value.viewer.is_ready ||
        selectedRosterIndex.value === null ||
        !hoverCoord.value
    ) {
        return { cells: [], valid: true };
    }

    const rosterItem = game.value.fleet_roster[selectedRosterIndex.value];

    if (!rosterItem) {
        return { cells: [], valid: true };
    }

    const cells = computePlaneCells(
        rosterItem.plane_type,
        hoverCoord.value.x,
        hoverCoord.value.y,
        placementOrientation.value,
    );

    const valid = isPlacementValid(
        cells,
        game.value.grid_size,
        placementOccupiedKeys.value,
    );

    return { cells, valid };
});

const weaponGhostCells = computed(() => {
    if (
        game.value.status !== 'playing' ||
        !game.value.viewer.is_my_turn ||
        !hoverCoord.value
    ) {
        return [];
    }

    return computeWeaponFootprint(
        selectedMissile.value,
        hoverCoord.value.x,
        hoverCoord.value.y,
        game.value.grid_size,
        carpetOrientation.value,
    );
});

const canLockFleet = computed(
    () =>
        draftPlanes.value.length === game.value.fleet_roster.length &&
        !game.value.viewer.is_ready,
);

function selectNextUnplaced(): void {
    const nextIdx = game.value.fleet_roster.findIndex(
        (_, i) => !placedRosterIndices.value.has(i),
    );
    selectedRosterIndex.value = nextIdx === -1 ? null : nextIdx;
}

function handlePlacementClick(x: number, y: number): void {
    if (game.value.viewer.is_ready) {
        return;
    }

    // If clicking an already placed plane, remove it so the player can reposition it.
    const existingIdx = draftAsPlaneStates.value.findIndex((p) =>
        p.cells.some((c) => c.x === x && c.y === y),
    );

    if (existingIdx !== -1 && selectedRosterIndex.value === null) {
        const removed = draftPlanes.value.splice(existingIdx, 1)[0];
        const rosterIdx = Number(removed.id.split('-').pop());
        selectedRosterIndex.value = Number.isNaN(rosterIdx) ? 0 : rosterIdx;
        placementOrientation.value = removed.orientation;

        return;
    }

    if (selectedRosterIndex.value === null) {
        return;
    }

    if (!placementGhost.value.valid) {
        errorBanner.value =
            'Aircraft overlaps another plane or extends outside airspace bounds.';

        return;
    }

    errorBanner.value = null;
    const item = game.value.fleet_roster[selectedRosterIndex.value];

    draftPlanes.value.push({
        id: `${item.plane_type}-${selectedRosterIndex.value}`,
        plane_type: item.plane_type,
        origin_x: x,
        origin_y: y,
        orientation: placementOrientation.value,
    });

    soundFx.place();
    selectNextUnplaced();
}

function rotateCurrent(): void {
    if (game.value.status === 'placing') {
        placementOrientation.value = nextOrientation(
            placementOrientation.value,
        );
    } else if (
        game.value.status === 'playing' &&
        selectedMissile.value === 'carpet'
    ) {
        carpetOrientation.value = carpetOrientation.value === 'H' ? 'V' : 'H';
    }
}

function autoDeploy(): void {
    const rosterTypes = game.value.fleet_roster.map((r) => r.plane_type);
    draftPlanes.value = generateRandomDraftFleet(
        rosterTypes,
        game.value.grid_size,
    );
    selectedRosterIndex.value = null;
    errorBanner.value = null;
    soundFx.place();
}

function clearDraft(): void {
    draftPlanes.value = [];
    selectedRosterIndex.value = 0;
    errorBanner.value = null;
}

async function lockFleet(): Promise<void> {
    if (!canLockFleet.value || isBusy.value) {
        return;
    }

    isBusy.value = true;
    errorBanner.value = null;

    try {
        const updated = await apiPost<GameStatePayload>(
            placeFleet.url(game.value.code),
            {
                planes: draftPlanes.value.map((p) => ({
                    plane_type: p.plane_type,
                    origin_x: p.origin_x,
                    origin_y: p.origin_y,
                    orientation: p.orientation,
                })),
            },
        );
        game.value = updated;
        turnCountdown.value = updated.turn_seconds_remaining;
    } catch (e) {
        errorBanner.value =
            e instanceof Error ? e.message : 'Unable to lock fleet.';
    } finally {
        isBusy.value = false;
    }
}

async function handleFireClick(x: number, y: number): Promise<void> {
    if (
        game.value.status !== 'playing' ||
        !game.value.viewer.is_my_turn ||
        isBusy.value
    ) {
        return;
    }

    isBusy.value = true;
    errorBanner.value = null;

    try {
        const updated = await apiPost<GameStatePayload>(
            fire.url(game.value.code),
            {
                missile_type: selectedMissile.value,
                target_x: x,
                target_y: y,
                orientation:
                    selectedMissile.value === 'carpet'
                        ? carpetOrientation.value
                        : null,
            },
        );

        const latestShot = updated.my_shots[updated.my_shots.length - 1];

        if (latestShot) {
            if (latestShot.missile_type === 'radar') {
                soundFx.radar();
            } else if (latestShot.destroyed_planes.length > 0) {
                soundFx.cockpitKill();
            } else if (
                latestShot.results.some(
                    (r) =>
                        r.outcome === 'hit' || r.outcome === 'cockpit_cracked',
                )
            ) {
                soundFx.hit();
            } else {
                soundFx.miss();
            }
        }

        game.value = updated;
        turnCountdown.value = updated.turn_seconds_remaining;

        // Reset to standard if chosen special missile ran out or got jammed
        const ammoLeft = updated.viewer.ammo[selectedMissile.value];

        if (
            (ammoLeft !== null && ammoLeft <= 0) ||
            (selectedMissile.value !== 'standard' && updated.viewer.emp_jammed)
        ) {
            selectedMissile.value = 'standard';
        }

        if (updated.status === 'finished') {
            soundFx.victory();
        }
    } catch (e) {
        errorBanner.value =
            e instanceof Error ? e.message : 'Unable to launch ordnance.';
    } finally {
        isBusy.value = false;
    }
}

async function handleForfeit(): Promise<void> {
    if (isBusy.value) {
        return;
    }

    isBusy.value = true;
    errorBanner.value = null;

    try {
        game.value = await apiPost<GameStatePayload>(
            forfeit.url(game.value.code),
        );
    } catch (e) {
        errorBanner.value =
            e instanceof Error ? e.message : 'Unable to forfeit mission.';
    } finally {
        isBusy.value = false;
    }
}

async function handleRematch(): Promise<void> {
    if (isBusy.value) {
        return;
    }

    isBusy.value = true;
    errorBanner.value = null;

    try {
        const res = await apiPost<{
            game: GameStatePayload;
            rematch_game_code: string | null;
        }>(rematch.url(game.value.code));

        game.value = res.game;

        if (res.rematch_game_code) {
            router.visit(show.url(res.rematch_game_code), {
                preserveState: false,
            });
        }
    } catch (e) {
        errorBanner.value =
            e instanceof Error ? e.message : 'Unable to request rematch.';
    } finally {
        isBusy.value = false;
    }
}

async function handleSendMessage(
    body: string,
    isEmote: boolean,
): Promise<void> {
    try {
        const msg = await apiPost<ChatMessage>(
            sendChatMessage.url(game.value.code),
            {
                body,
                is_emote: isEmote,
            },
        );
        game.value.messages.push(msg);
    } catch {
        // Ignore chat errors
    }
}

async function copyInviteLink(): Promise<void> {
    const url = game.value.invite_url;
    let copied = false;

    if (window.isSecureContext && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(url);
            copied = true;
        } catch {
            copied = false;
        }
    }

    if (!copied) {
        try {
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.top = '0';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            copied = document.execCommand('copy');
            document.body.removeChild(textarea);
        } catch {
            copied = false;
        }
    }

    if (copied) {
        copiedInvite.value = true;
        setTimeout(() => {
            copiedInvite.value = false;
        }, 2000);
    }
}

function toggleMute(): void {
    muted.value = !muted.value;
    setSoundMuted(muted.value);
}

function onKeyDown(e: KeyboardEvent): void {
    if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement
    ) {
        return;
    }

    if (e.key === 'r' || e.key === 'R') {
        e.preventDefault();
        rotateCurrent();
    }
}

watch(
    () => game.value.rematch_game_code,
    (code) => {
        if (code) {
            router.visit(show.url(code), { preserveState: false });
        }
    },
);

onMounted(() => {
    window.addEventListener('keydown', onKeyDown);

    countdownInterval = setInterval(() => {
        if (
            game.value.status === 'playing' &&
            turnCountdown.value !== null &&
            turnCountdown.value > 0
        ) {
            turnCountdown.value--;
        }
    }, 1000);

    const echo = getEcho();

    if (echo && game.value.viewer.is_participant) {
        echo.join(`game.${game.value.code}`)
            .here((members: Array<{ slot: number }>) => {
                wsConnected.value = true;
                onlineSlots.value = members.map((m) => m.slot);
            })
            .joining((member: { slot: number }) => {
                if (!onlineSlots.value.includes(member.slot)) {
                    onlineSlots.value.push(member.slot);
                }
            })
            .leaving((member: { slot: number }) => {
                onlineSlots.value = onlineSlots.value.filter(
                    (s) => s !== member.slot,
                );
            })
            .listen('.PlayerJoined', () => {
                soundFx.place();
                void refreshState();
            })
            .listen('.FleetReadyUpdated', () => {
                void refreshState();
            })
            .listen(
                '.ShotFired',
                (payload: {
                    shot?: {
                        destroyed_planes?: unknown[];
                        missile_type?: string;
                    };
                }) => {
                    if (payload.shot?.missile_type === 'radar') {
                        soundFx.radar();
                    } else if (
                        (payload.shot?.destroyed_planes?.length ?? 0) > 0
                    ) {
                        soundFx.cockpitKill();
                    } else {
                        soundFx.hit();
                    }

                    void refreshState();
                },
            )
            .listen('.GameFinished', () => {
                soundFx.victory();
                void refreshState();
            })
            .listen('.ChatMessageSent', (msg: ChatMessage) => {
                if (!game.value.messages.some((m) => m.id === msg.id)) {
                    game.value.messages.push(msg);
                }
            })
            .listen('.RematchRequested', () => {
                soundFx.place();
                void refreshState();
            })
            .listen('.RematchCreated', (payload: { new_game_code: string }) => {
                router.visit(show.url(payload.new_game_code), {
                    preserveState: false,
                });
            });
    }

    // Fallback sync every 3s if WebSocket server isn't reachable (including awaiting rematch)
    fallbackPollInterval = setInterval(() => {
        const waitingOnRematch =
            game.value.status === 'finished' && !game.value.rematch_game_code;

        if (
            !wsConnected.value &&
            game.value.status !== 'abandoned' &&
            (game.value.status !== 'finished' || waitingOnRematch)
        ) {
            void refreshState();
        }
    }, 3000);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown);

    if (countdownInterval) {
        clearInterval(countdownInterval);
    }

    if (fallbackPollInterval) {
        clearInterval(fallbackPollInterval);
    }

    const echo = getEcho();

    if (echo) {
        echo.leave(`game.${game.value.code}`);
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <Head :title="`Mission ${game.code} — SkyStrike Bombers`" />

        <div
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.1),transparent_55%)]"
        />

        <div class="relative mx-auto max-w-7xl px-3 py-4 sm:px-6">
            <!-- Top HUD Bar -->
            <header
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-500/25 bg-slate-900/90 px-4 py-2.5 shadow-lg backdrop-blur"
            >
                <div class="flex items-center gap-3">
                    <Link
                        :href="home.url()"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800/80 px-2.5 py-1.5 font-mono text-xs text-slate-300 transition hover:border-emerald-400/50 hover:text-white"
                    >
                        <ArrowLeft class="h-3.5 w-3.5" />
                        Lobby
                    </Link>

                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="font-mono text-sm font-bold tracking-wider text-white"
                            >
                                {{ game.code }}
                            </span>
                            <span
                                class="rounded bg-emerald-500/15 px-1.5 py-0.5 font-mono text-[10px] text-emerald-300"
                            >
                                {{ game.mode_label }}
                            </span>
                        </div>
                        <div class="font-mono text-[11px] text-slate-400">
                            {{ me?.callsign ?? game.viewer.callsign }} vs
                            {{ opponent?.callsign ?? 'Awaiting Challenger' }}
                        </div>
                    </div>
                </div>

                <!-- Center Status Pill -->
                <div class="flex items-center gap-2">
                    <div
                        v-if="game.status === 'playing'"
                        class="flex items-center gap-2 rounded-full border px-3.5 py-1 font-mono text-xs font-bold uppercase"
                        :class="
                            game.viewer.is_my_turn
                                ? 'border-emerald-400 bg-emerald-500/20 text-emerald-200'
                                : 'border-amber-400/40 bg-amber-500/10 text-amber-200'
                        "
                    >
                        <span>
                            {{
                                game.viewer.is_my_turn
                                    ? 'Your Turn — Weapons Free'
                                    : `${opponent?.callsign ?? 'Enemy'} Aiming...`
                            }}
                        </span>
                        <span
                            v-if="turnCountdown !== null"
                            class="rounded bg-slate-950/70 px-1.5 py-0.5 text-[11px]"
                        >
                            {{ turnCountdown }}s
                        </span>
                    </div>

                    <div
                        v-else-if="game.status === 'placing'"
                        class="rounded-full border border-cyan-400/40 bg-cyan-500/15 px-3.5 py-1 font-mono text-xs font-semibold text-cyan-200 uppercase"
                    >
                        Fleet Deployment Phase
                    </div>

                    <div
                        v-else-if="game.status === 'waiting'"
                        class="rounded-full border border-amber-400/40 bg-amber-500/15 px-3.5 py-1 font-mono text-xs font-semibold text-amber-200 uppercase"
                    >
                        Awaiting Challenger
                    </div>
                </div>

                <!-- Right Controls -->
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1 rounded-md border px-2 py-1 font-mono text-[10px]"
                        :class="
                            opponentOnline
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                                : 'border-slate-700 bg-slate-800 text-slate-400'
                        "
                    >
                        <Wifi v-if="opponentOnline" class="h-3 w-3" />
                        <WifiOff v-else class="h-3 w-3" />
                        {{ opponentOnline ? 'Opponent Live' : 'Offline' }}
                    </span>

                    <button
                        type="button"
                        class="rounded-lg border border-slate-700 bg-slate-800 p-1.5 text-slate-300 transition hover:text-white"
                        :title="muted ? 'Unmute Sound FX' : 'Mute Sound FX'"
                        @click="toggleMute"
                    >
                        <VolumeX v-if="muted" class="h-4 w-4" />
                        <Volume2 v-else class="h-4 w-4" />
                    </button>

                    <button
                        v-if="
                            game.status === 'playing' &&
                            game.viewer.is_participant
                        "
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-rose-500/40 bg-rose-950/50 px-2.5 py-1.5 font-mono text-xs text-rose-300 transition hover:bg-rose-900/70"
                        @click="handleForfeit"
                    >
                        <Flag class="h-3.5 w-3.5" />
                        Forfeit
                    </button>
                </div>
            </header>

            <!-- Error Banner -->
            <div
                v-if="errorBanner"
                class="mb-4 rounded-xl border border-rose-500/50 bg-rose-950/70 px-4 py-2.5 text-xs font-medium text-rose-200"
            >
                {{ errorBanner }}
            </div>

            <!-- PHASE 1: WAITING FOR OPPONENT -->
            <div
                v-if="game.status === 'waiting'"
                class="mx-auto mt-10 max-w-xl rounded-2xl border border-emerald-500/30 bg-slate-900/90 p-6 text-center shadow-2xl"
            >
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full border border-emerald-400/40 bg-emerald-500/15 text-emerald-300"
                >
                    <Radar class="h-8 w-8 animate-spin" />
                </div>
                <h2 class="font-mono text-lg font-bold text-white uppercase">
                    Waiting for Challenger
                </h2>
                <p class="mt-1 text-xs text-slate-400">
                    Share this mission link with a friend. They can join
                    instantly as a guest—no signup required.
                </p>

                <div class="mt-5 flex items-center gap-2">
                    <input
                        type="text"
                        readonly
                        :value="game.invite_url"
                        class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-xs text-emerald-300 focus:outline-none"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500 px-4 py-2 font-mono text-xs font-bold text-slate-950 transition hover:bg-emerald-400"
                        @click="copyInviteLink"
                    >
                        <Check v-if="copiedInvite" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ copiedInvite ? 'Copied!' : 'Copy Link' }}
                    </button>
                </div>

                <div class="mt-4 font-mono text-xs text-slate-400">
                    Mission Code:
                    <span class="font-bold text-white">{{ game.code }}</span>
                </div>
            </div>

            <!-- PHASE 2: FLEET DEPLOYMENT -->
            <div
                v-else-if="game.status === 'placing'"
                class="grid gap-5 lg:grid-cols-12"
            >
                <div class="lg:col-span-7">
                    <TacticalGrid
                        :grid-size="game.grid_size"
                        title="Deploy Your Squadron"
                        subtitle="Click to place cockpit • Press R or Right-Click to rotate • Click placed aircraft to move"
                        mode="placement"
                        :planes="
                            game.viewer.is_ready
                                ? game.my_planes
                                : draftAsPlaneStates
                        "
                        :ghost-cells="placementGhost.cells"
                        :ghost-valid="placementGhost.valid"
                        :interactive="!game.viewer.is_ready"
                        @cell-click="handlePlacementClick"
                        @cell-hover="(x, y) => (hoverCoord = { x, y })"
                        @cell-leave="hoverCoord = null"
                        @right-click="rotateCurrent"
                    />
                </div>

                <div class="space-y-4 lg:col-span-5">
                    <div
                        class="rounded-xl border border-emerald-500/25 bg-slate-900/90 p-4 shadow-lg"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <h3
                                class="font-mono text-xs font-bold tracking-widest text-emerald-300 uppercase"
                            >
                                Squadron Hangar
                            </h3>
                            <span class="font-mono text-xs text-slate-400">
                                Heading:
                                <strong class="text-amber-300">{{
                                    placementOrientation
                                }}</strong>
                            </span>
                        </div>

                        <div class="mb-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                :disabled="game.viewer.is_ready"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 font-mono text-xs text-slate-200 hover:border-amber-400/50 disabled:opacity-40"
                                @click="rotateCurrent"
                            >
                                <RotateCw class="h-3.5 w-3.5" />
                                Rotate (R)
                            </button>
                            <button
                                type="button"
                                :disabled="game.viewer.is_ready"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/40 bg-emerald-500/15 px-3 py-1.5 font-mono text-xs text-emerald-200 hover:bg-emerald-500/25 disabled:opacity-40"
                                @click="autoDeploy"
                            >
                                <Shuffle class="h-3.5 w-3.5" />
                                Auto-Deploy
                            </button>
                            <button
                                type="button"
                                :disabled="game.viewer.is_ready"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 font-mono text-xs text-slate-300 hover:text-rose-300 disabled:opacity-40"
                                @click="clearDraft"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Clear
                            </button>
                        </div>

                        <div class="space-y-2">
                            <button
                                v-for="(item, idx) in game.fleet_roster"
                                :key="`${item.plane_type}-${idx}`"
                                type="button"
                                :disabled="
                                    game.viewer.is_ready ||
                                    placedRosterIndices.has(idx)
                                "
                                class="flex w-full items-start justify-between rounded-lg border p-2.5 text-left transition"
                                :class="
                                    placedRosterIndices.has(idx)
                                        ? 'border-emerald-500/40 bg-emerald-950/40 text-emerald-300'
                                        : selectedRosterIndex === idx
                                          ? 'border-amber-400 bg-amber-500/15 ring-1 ring-amber-400/60'
                                          : 'border-slate-800 bg-slate-950/70 hover:border-slate-600'
                                "
                                @click="selectedRosterIndex = idx"
                            >
                                <div>
                                    <div
                                        class="flex items-center gap-1.5 text-xs font-bold"
                                    >
                                        <Plane class="h-3.5 w-3.5" />
                                        {{ item.label }}
                                    </div>
                                    <p
                                        class="mt-0.5 text-[11px] text-slate-400"
                                    >
                                        {{ item.perk }}
                                    </p>
                                </div>
                                <CheckCircle2
                                    v-if="placedRosterIndices.has(idx)"
                                    class="h-4 w-4 shrink-0 text-emerald-400"
                                />
                            </button>
                        </div>

                        <div class="mt-4">
                            <button
                                v-if="!game.viewer.is_ready"
                                type="button"
                                :disabled="!canLockFleet || isBusy"
                                class="w-full rounded-xl bg-emerald-500 px-4 py-3 font-mono text-xs font-bold tracking-wider text-slate-950 uppercase transition hover:bg-emerald-400 disabled:opacity-40"
                                @click="lockFleet"
                            >
                                Lock Fleet & Ready Up
                            </button>
                            <div
                                v-else
                                class="rounded-xl border border-emerald-500/40 bg-emerald-950/50 p-3 text-center font-mono text-xs text-emerald-200"
                            >
                                Fleet Locked! Waiting for
                                {{ opponent?.callsign ?? 'opponent' }} to
                                deploy...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PHASE 3 & 4: LIVE COMBAT & DEBRIEF -->
            <div v-else class="space-y-4">
                <!-- Victory / Defeat Debrief Banner -->
                <div
                    v-if="game.status === 'finished'"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4 shadow-xl"
                    :class="
                        game.winner_slot === game.viewer.slot
                            ? 'border-emerald-400/60 bg-emerald-950/70'
                            : 'border-rose-500/50 bg-rose-950/60'
                    "
                >
                    <div class="flex items-center gap-3">
                        <Trophy
                            class="h-8 w-8"
                            :class="
                                game.winner_slot === game.viewer.slot
                                    ? 'text-amber-300'
                                    : 'text-rose-400'
                            "
                        />
                        <div>
                            <h2
                                class="font-mono text-lg font-bold tracking-wider uppercase"
                            >
                                {{
                                    game.winner_slot === game.viewer.slot
                                        ? 'Air Superiority Achieved — Victory!'
                                        : 'Squadron Down — Defeat'
                                }}
                            </h2>
                            <p class="text-xs text-slate-300">
                                Full enemy airspace has been revealed below.
                                Completed in {{ game.turn_number }} turns.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :disabled="game.viewer.rematch_requested || isBusy"
                            class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 font-mono text-xs font-bold text-slate-950 uppercase transition disabled:opacity-50"
                            :class="
                                opponent?.rematch_requested &&
                                !game.viewer.rematch_requested
                                    ? 'animate-pulse bg-emerald-400 hover:bg-emerald-300'
                                    : 'bg-amber-400 hover:bg-amber-300'
                            "
                            @click="handleRematch"
                        >
                            <RefreshCw class="h-4 w-4" />
                            {{
                                game.viewer.rematch_requested
                                    ? 'Waiting for Opponent...'
                                    : opponent?.rematch_requested
                                      ? `Accept Rematch (${opponent.callsign} Ready!)`
                                      : 'Request Rematch'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Weapon Selector -->
                <WeaponSelector
                    v-if="
                        game.status === 'playing' && game.viewer.is_participant
                    "
                    :missiles="game.missiles_meta"
                    :ammo="game.viewer.ammo"
                    :selected-missile="selectedMissile"
                    :carpet-orientation="carpetOrientation"
                    :emp-jammed="game.viewer.emp_jammed"
                    :disabled="!game.viewer.is_my_turn || isBusy"
                    @select="(m) => (selectedMissile = m)"
                    @toggle-carpet-orientation="rotateCurrent"
                />

                <!-- Dual Tactical Boards + Intel Sidebar -->
                <div class="grid gap-4 xl:grid-cols-12">
                    <!-- Enemy Airspace (Primary Targeting Radar) -->
                    <div class="xl:col-span-5">
                        <TacticalGrid
                            :grid-size="game.grid_size"
                            title="Enemy Airspace — Targeting Radar"
                            :subtitle="
                                game.viewer.is_my_turn
                                    ? 'Select weapon above and click target coordinates'
                                    : 'Stand by for enemy salvo'
                            "
                            mode="enemy"
                            :planes="game.enemy_revealed_planes"
                            :shots="game.my_shots"
                            :ghost-cells="weaponGhostCells"
                            :interactive="
                                game.status === 'playing' &&
                                game.viewer.is_my_turn
                            "
                            @cell-click="handleFireClick"
                            @cell-hover="(x, y) => (hoverCoord = { x, y })"
                            @cell-leave="hoverCoord = null"
                            @right-click="rotateCurrent"
                        />
                    </div>

                    <!-- Friendly Airspace -->
                    <div class="xl:col-span-4">
                        <TacticalGrid
                            :grid-size="game.grid_size"
                            title="Your Airspace — Fleet Defense"
                            subtitle="Amber crosshairs mark your cockpits"
                            mode="friendly"
                            :planes="game.my_planes"
                            :shots="game.enemy_shots"
                            compact
                        />
                    </div>

                    <!-- Right Intel Column -->
                    <div class="space-y-4 xl:col-span-3">
                        <EnemyFleetHud
                            :fleet="game.enemy_fleet_summary"
                            :opponent-callsign="opponent?.callsign ?? 'Hostile'"
                        />

                        <!-- Combat Log -->
                        <div
                            class="rounded-xl border border-slate-700/80 bg-slate-900/85 p-3 shadow-lg"
                        >
                            <h3
                                class="mb-2 font-mono text-xs font-semibold tracking-widest text-slate-300 uppercase"
                            >
                                Combat Log
                            </h3>
                            <div
                                class="max-h-44 space-y-1.5 overflow-y-auto font-mono text-[11px]"
                            >
                                <p
                                    v-if="game.combat_log.length === 0"
                                    class="py-3 text-center text-slate-500"
                                >
                                    Awaiting first salvo...
                                </p>
                                <div
                                    v-for="entry in game.combat_log"
                                    :key="entry.id"
                                    class="rounded border border-slate-800 bg-slate-950/75 px-2 py-1.5"
                                >
                                    <div
                                        class="flex items-center justify-between text-[10px] text-slate-400"
                                    >
                                        <span>
                                            #{{ entry.turn_number }}
                                            {{ entry.attacker_callsign }}
                                        </span>
                                        <span>{{
                                            formatCoordinate(
                                                entry.target_x,
                                                entry.target_y,
                                            )
                                        }}</span>
                                    </div>
                                    <div class="mt-0.5 text-slate-200">
                                        {{ entry.missile_label }}
                                        <span
                                            v-if="entry.radar_count !== null"
                                            class="text-cyan-300"
                                        >
                                            →
                                            {{ entry.radar_count }} signature(s)
                                        </span>
                                        <span
                                            v-else-if="
                                                entry.destroyed_count > 0
                                            "
                                            class="font-bold text-rose-400"
                                        >
                                            → AIRCRAFT DESTROYED!
                                        </span>
                                        <span
                                            v-else-if="
                                                entry.results.some(
                                                    (r) =>
                                                        r.outcome ===
                                                        'cockpit_cracked',
                                                )
                                            "
                                            class="font-bold text-amber-300"
                                        >
                                            → ARMORED COCKPIT CRACKED!
                                        </span>
                                        <span
                                            v-else-if="
                                                entry.results.some(
                                                    (r) => r.outcome === 'hit',
                                                )
                                            "
                                            class="text-orange-300"
                                        >
                                            → HIT
                                        </span>
                                        <span v-else class="text-slate-500">
                                            → Miss
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <GameChat
                            v-if="!game.is_vs_ai && game.viewer.is_participant"
                            :messages="game.messages"
                            :my-slot="game.viewer.slot"
                            @send="handleSendMessage"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
