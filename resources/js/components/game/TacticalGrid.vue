<script setup lang="ts">
import { computed, ref } from 'vue';
import { Crosshair, Flame, Radar, ShieldAlert, Skull } from '@lucide/vue';
import type {
    CellOutcomeValue,
    PlaneCell,
    PlaneState,
    PlaneTypeValue,
    ShotState,
} from '@/types/game';

interface GhostCell {
    x: number;
    y: number;
    part?: string;
}

const props = withDefaults(
    defineProps<{
        gridSize: number;
        title: string;
        subtitle?: string;
        mode: 'placement' | 'enemy' | 'friendly';
        planes?: PlaneState[];
        shots?: ShotState[];
        ghostCells?: GhostCell[];
        ghostValid?: boolean;
        interactive?: boolean;
        compact?: boolean;
    }>(),
    {
        subtitle: '',
        planes: () => [],
        shots: () => [],
        ghostCells: () => [],
        ghostValid: true,
        interactive: false,
        compact: false,
    },
);

const emit = defineEmits<{
    cellClick: [x: number, y: number];
    cellHover: [x: number, y: number];
    cellLeave: [];
    rightClick: [];
}>();

const hoveredKey = ref<string | null>(null);

const columns = computed(() =>
    Array.from({ length: props.gridSize }, (_, i) =>
        String.fromCharCode(65 + i),
    ),
);

const rows = computed(() =>
    Array.from({ length: props.gridSize }, (_, i) => i),
);

const planeCellMap = computed(() => {
    const map = new Map<
        string,
        {
            part: PlaneCell['part'];
            planeType: PlaneTypeValue;
            isDestroyed: boolean;
            cockpitCracked: boolean;
        }
    >();

    for (const plane of props.planes) {
        const cracked =
            !plane.is_destroyed && plane.cockpit_hp < plane.max_cockpit_hp;

        for (const cell of plane.cells) {
            map.set(`${cell.x},${cell.y}`, {
                part: cell.part,
                planeType: plane.plane_type,
                isDestroyed: plane.is_destroyed,
                cockpitCracked: cracked && cell.part === 'cockpit',
            });
        }
    }

    return map;
});

const shotOutcomeMap = computed(() => {
    const map = new Map<string, CellOutcomeValue>();

    for (const shot of props.shots) {
        if (shot.missile_type === 'radar') {
            continue;
        }

        for (const res of shot.results) {
            const key = `${res.x},${res.y}`;
            const prev = map.get(key);

            if (prev === 'cockpit_killed') {
                continue;
            }

            if (
                res.outcome === 'cockpit_killed' ||
                res.outcome === 'cockpit_cracked' ||
                res.outcome === 'hit' ||
                !prev
            ) {
                map.set(key, res.outcome);
            }
        }
    }

    return map;
});

const radarCenterMap = computed(() => {
    const map = new Map<string, number>();

    for (const shot of props.shots) {
        if (shot.missile_type === 'radar' && shot.radar_count !== null) {
            map.set(`${shot.target_x},${shot.target_y}`, shot.radar_count);
        }
    }

    return map;
});

const radarScannedSet = computed(() => {
    const set = new Set<string>();

    for (const shot of props.shots) {
        if (shot.missile_type === 'radar') {
            for (const res of shot.results) {
                set.add(`${res.x},${res.y}`);
            }
        }
    }

    return set;
});

const ghostMap = computed(() => {
    const map = new Map<string, GhostCell>();

    for (const cell of props.ghostCells) {
        map.set(`${cell.x},${cell.y}`, cell);
    }

    return map;
});

function planeColorClasses(
    planeType: PlaneTypeValue,
    part: PlaneCell['part'],
    isDestroyed: boolean,
): string {
    if (isDestroyed) {
        return part === 'cockpit'
            ? 'bg-rose-600/80 border-rose-300 text-white'
            : 'bg-rose-950/80 border-rose-500/60 text-rose-200';
    }

    if (part === 'cockpit') {
        return 'bg-amber-400/90 border-amber-200 text-slate-950 shadow-[inset_0_0_8px_rgba(251,191,36,0.9)]';
    }

    switch (planeType) {
        case 'heavy':
            return 'bg-sky-500/45 border-sky-400/70';
        case 'stealth':
            return 'bg-violet-500/45 border-violet-400/70';
        case 'fighter':
            return 'bg-emerald-500/45 border-emerald-400/70';
        case 'interceptor':
            return 'bg-cyan-400/45 border-cyan-300/70';
    }
}

function cellClasses(x: number, y: number): string {
    const key = `${x},${y}`;
    const ghost = ghostMap.value.get(key);
    const planeCell = planeCellMap.value.get(key);
    const outcome = shotOutcomeMap.value.get(key);
    const isRadarZone = radarScannedSet.value.has(key);

    if (ghost) {
        if (props.mode === 'placement') {
            return props.ghostValid
                ? ghost.part === 'cockpit'
                    ? 'bg-amber-400/80 border-amber-200 ring-1 ring-amber-300'
                    : 'bg-emerald-400/50 border-emerald-300'
                : 'bg-rose-500/60 border-rose-300';
        }

        return 'bg-amber-400/30 border-amber-300/90 ring-1 ring-amber-300/70';
    }

    if (outcome === 'cockpit_killed') {
        return 'bg-rose-600/85 border-rose-300 text-white';
    }

    if (outcome === 'cockpit_cracked') {
        return 'bg-amber-500/75 border-amber-200 text-slate-950';
    }

    if (outcome === 'hit') {
        return planeCell?.isDestroyed
            ? 'bg-rose-900/85 border-rose-500/70 text-rose-200'
            : 'bg-orange-500/70 border-orange-300 text-white';
    }

    if (planeCell) {
        return planeColorClasses(
            planeCell.planeType,
            planeCell.part,
            planeCell.isDestroyed,
        );
    }

    if (outcome === 'miss') {
        return 'bg-slate-800/80 border-slate-700/80 text-slate-500';
    }

    if (isRadarZone) {
        return 'bg-emerald-950/60 border-emerald-500/35';
    }

    return 'bg-slate-950/75 border-emerald-500/15 hover:border-emerald-400/45';
}

function handleEnter(x: number, y: number): void {
    hoveredKey.value = `${x},${y}`;
    emit('cellHover', x, y);
}

function handleLeave(): void {
    hoveredKey.value = null;
    emit('cellLeave');
}

function handleClick(x: number, y: number): void {
    if (!props.interactive) {
        return;
    }

    emit('cellClick', x, y);
}
</script>

<template>
    <div
        class="rounded-xl border border-emerald-500/25 bg-slate-900/85 p-3 shadow-xl shadow-black/40 backdrop-blur"
        @contextmenu.prevent="emit('rightClick')"
    >
        <div class="mb-2.5 flex items-center justify-between gap-2">
            <div>
                <h3
                    class="font-mono text-xs font-semibold tracking-widest text-emerald-300 uppercase"
                >
                    {{ title }}
                </h3>
                <p v-if="subtitle" class="text-xs text-slate-400">
                    {{ subtitle }}
                </p>
            </div>
            <span
                class="rounded border border-emerald-500/30 bg-emerald-950/60 px-2 py-0.5 font-mono text-[11px] text-emerald-300"
            >
                {{ gridSize }}×{{ gridSize }}
            </span>
        </div>

        <div class="select-none" @mouseleave="handleLeave">
            <!-- Column headers -->
            <div
                class="mb-1 grid gap-0.5 pl-6"
                :style="{
                    gridTemplateColumns: `repeat(${gridSize}, minmax(0, 1fr))`,
                }"
            >
                <div
                    v-for="col in columns"
                    :key="col"
                    class="text-center font-mono text-[10px] font-medium text-slate-400"
                >
                    {{ col }}
                </div>
            </div>

            <!-- Grid rows -->
            <div class="space-y-0.5">
                <div v-for="y in rows" :key="y" class="flex items-center gap-1">
                    <div
                        class="w-5 shrink-0 text-right font-mono text-[10px] text-slate-400"
                    >
                        {{ y + 1 }}
                    </div>
                    <div
                        class="grid flex-1 gap-0.5"
                        :style="{
                            gridTemplateColumns: `repeat(${gridSize}, minmax(0, 1fr))`,
                        }"
                    >
                        <button
                            v-for="x in gridSize"
                            :key="`${x - 1},${y}`"
                            type="button"
                            class="relative flex aspect-square items-center justify-center rounded-[3px] border transition-colors duration-100"
                            :class="[
                                cellClasses(x - 1, y),
                                interactive
                                    ? 'cursor-crosshair'
                                    : 'cursor-default',
                                compact ? 'min-h-5' : 'min-h-6 sm:min-h-7',
                            ]"
                            @mouseenter="handleEnter(x - 1, y)"
                            @click="handleClick(x - 1, y)"
                        >
                            <!-- Cockpit Killed -->
                            <Skull
                                v-if="
                                    shotOutcomeMap.get(`${x - 1},${y}`) ===
                                    'cockpit_killed'
                                "
                                class="h-3.5 w-3.5 text-white drop-shadow"
                            />

                            <!-- Cockpit Cracked -->
                            <ShieldAlert
                                v-else-if="
                                    shotOutcomeMap.get(`${x - 1},${y}`) ===
                                    'cockpit_cracked'
                                "
                                class="h-3.5 w-3.5 animate-pulse text-slate-950"
                            />

                            <!-- Direct Hit -->
                            <Flame
                                v-else-if="
                                    shotOutcomeMap.get(`${x - 1},${y}`) ===
                                    'hit'
                                "
                                class="h-3.5 w-3.5 text-amber-100"
                            />

                            <!-- Miss Dot -->
                            <span
                                v-else-if="
                                    shotOutcomeMap.get(`${x - 1},${y}`) ===
                                    'miss'
                                "
                                class="h-1.5 w-1.5 rounded-full bg-slate-500"
                            />

                            <!-- Cockpit marker on friendly/placement/revealed board -->
                            <Crosshair
                                v-else-if="
                                    planeCellMap.get(`${x - 1},${y}`)?.part ===
                                    'cockpit'
                                "
                                class="h-3.5 w-3.5 text-slate-950"
                            />

                            <!-- Radar Scan Count Badge -->
                            <span
                                v-if="
                                    radarCenterMap.has(`${x - 1},${y}`) &&
                                    !shotOutcomeMap.has(`${x - 1},${y}`)
                                "
                                class="inline-flex items-center gap-0.5 rounded bg-emerald-500/90 px-1 py-0.5 font-mono text-[10px] leading-none font-bold text-slate-950 shadow"
                                :title="`Radar Sweep detected ${radarCenterMap.get(`${x - 1},${y}`)} non-stealth signature(s)`"
                            >
                                <Radar class="h-2.5 w-2.5" />
                                {{ radarCenterMap.get(`${x - 1},${y}`) }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
