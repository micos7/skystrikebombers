<script setup lang="ts">
import { Bomb, Crosshair, Flame, Radar, RotateCw, Zap } from '@lucide/vue';
import type { MissileMeta, MissileTypeValue } from '@/types/game';

const props = defineProps<{
    missiles: MissileMeta[];
    ammo: Record<MissileTypeValue, number | null>;
    selectedMissile: MissileTypeValue;
    carpetOrientation: 'H' | 'V';
    empJammed: boolean;
    disabled: boolean;
}>();

const emit = defineEmits<{
    select: [missile: MissileTypeValue];
    toggleCarpetOrientation: [];
}>();

function isAvailable(missile: MissileMeta): boolean {
    if (props.disabled) {
        return false;
    }

    if (missile.is_special && props.empJammed) {
        return false;
    }

    const remaining = props.ammo[missile.type];

    return remaining === null || remaining > 0;
}

function patternBadge(type: MissileTypeValue): string {
    switch (type) {
        case 'standard':
            return '1×1';
        case 'radar':
            return '3×3 SCAN';
        case 'cluster':
            return '+ CROSS';
        case 'carpet':
            return props.carpetOrientation === 'V' ? '1×4 VERT' : '4×1 HORIZ';
        case 'emp':
            return '2×2 PIERCE';
    }
}
</script>

<template>
    <div
        class="rounded-xl border border-emerald-500/25 bg-slate-900/85 p-3 shadow-lg backdrop-blur"
    >
        <div class="mb-2 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span
                    class="font-mono text-xs font-semibold tracking-widest text-emerald-300 uppercase"
                >
                    Ordnance Arsenal
                </span>
                <span
                    v-if="empJammed"
                    class="inline-flex items-center gap-1 rounded bg-violet-500/25 px-2 py-0.5 font-mono text-[10px] font-semibold text-violet-200 ring-1 ring-violet-400/50"
                >
                    <Zap class="h-3 w-3" />
                    EMP JAMMED — SAM ONLY
                </span>
            </div>

            <button
                v-if="selectedMissile === 'carpet'"
                type="button"
                class="inline-flex items-center gap-1 rounded border border-amber-400/40 bg-amber-500/15 px-2 py-1 font-mono text-[11px] font-medium text-amber-200 transition hover:bg-amber-500/25"
                @click="emit('toggleCarpetOrientation')"
            >
                <RotateCw class="h-3 w-3" />
                Rotate Line (R):
                {{ carpetOrientation === 'H' ? 'Horiz' : 'Vert' }}
            </button>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
            <button
                v-for="missile in missiles"
                :key="missile.type"
                type="button"
                :disabled="!isAvailable(missile)"
                class="flex flex-col items-start rounded-lg border p-2.5 text-left transition"
                :class="[
                    selectedMissile === missile.type
                        ? 'border-amber-400 bg-amber-500/15 ring-1 ring-amber-400/60'
                        : isAvailable(missile)
                          ? 'border-slate-700 bg-slate-950/70 hover:border-emerald-400/50'
                          : 'cursor-not-allowed border-slate-800 bg-slate-950/40 opacity-45',
                ]"
                @click="emit('select', missile.type)"
            >
                <div
                    class="mb-1 flex w-full items-center justify-between gap-1"
                >
                    <Crosshair
                        v-if="missile.type === 'standard'"
                        class="h-4 w-4 text-emerald-300"
                    />
                    <Radar
                        v-else-if="missile.type === 'radar'"
                        class="h-4 w-4 text-cyan-300"
                    />
                    <Bomb
                        v-else-if="missile.type === 'cluster'"
                        class="h-4 w-4 text-amber-300"
                    />
                    <Flame
                        v-else-if="missile.type === 'carpet'"
                        class="h-4 w-4 text-orange-400"
                    />
                    <Zap v-else class="h-4 w-4 text-violet-300" />

                    <span
                        class="rounded bg-slate-800 px-1.5 py-0.5 font-mono text-[10px] font-bold"
                        :class="
                            ammo[missile.type] === null
                                ? 'text-emerald-300'
                                : (ammo[missile.type] ?? 0) > 0
                                  ? 'text-amber-200'
                                  : 'text-slate-500'
                        "
                    >
                        {{
                            ammo[missile.type] === null
                                ? '∞'
                                : ammo[missile.type]
                        }}
                    </span>
                </div>

                <div class="text-xs font-semibold text-slate-100">
                    {{ missile.label }}
                </div>
                <div class="mt-0.5 font-mono text-[10px] text-slate-400">
                    {{ patternBadge(missile.type) }}
                </div>
            </button>
        </div>

        <p class="mt-2 text-xs text-slate-400">
            {{
                missiles.find((m) => m.type === selectedMissile)?.description ??
                ''
            }}
        </p>
    </div>
</template>
