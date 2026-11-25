<script setup lang="ts">
import { EyeOff, Plane, Shield, Skull } from '@lucide/vue';
import type { EnemyFleetSummaryItem } from '@/types/game';

defineProps<{
    fleet: EnemyFleetSummaryItem[];
    opponentCallsign: string;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-slate-700/80 bg-slate-900/85 p-3 shadow-lg backdrop-blur"
    >
        <div class="mb-2 flex items-center justify-between">
            <span
                class="font-mono text-xs font-semibold tracking-widest text-slate-300 uppercase"
            >
                Hostile Squadron
            </span>
            <span class="font-mono text-xs text-slate-400">
                {{ fleet.filter((p) => !p.is_destroyed).length }} /
                {{ fleet.length }} Airborne
            </span>
        </div>

        <div class="space-y-1.5">
            <div
                v-for="(plane, idx) in fleet"
                :key="`${plane.plane_type}-${idx}`"
                class="flex items-center justify-between rounded-lg border px-2.5 py-1.5 text-xs"
                :class="
                    plane.is_destroyed
                        ? 'border-rose-500/40 bg-rose-950/40 text-rose-300 line-through opacity-75'
                        : plane.cockpit_cracked
                          ? 'border-amber-400/50 bg-amber-950/35 text-amber-200'
                          : 'border-slate-800 bg-slate-950/70 text-slate-200'
                "
            >
                <div class="flex items-center gap-2">
                    <Skull
                        v-if="plane.is_destroyed"
                        class="h-3.5 w-3.5 text-rose-400"
                    />
                    <Plane v-else class="h-3.5 w-3.5 text-emerald-400" />
                    <span class="font-medium">{{ plane.label }}</span>
                </div>

                <div class="flex items-center gap-1.5">
                    <span
                        v-if="
                            plane.plane_type === 'stealth' &&
                            !plane.is_destroyed
                        "
                        class="inline-flex items-center gap-0.5 rounded bg-violet-500/20 px-1.5 py-0.5 font-mono text-[10px] text-violet-200"
                        title="Hidden from Radar Recon"
                    >
                        <EyeOff class="h-2.5 w-2.5" />
                        CLOAK
                    </span>
                    <span
                        v-if="
                            plane.plane_type === 'heavy' && !plane.is_destroyed
                        "
                        class="inline-flex items-center gap-0.5 rounded bg-sky-500/20 px-1.5 py-0.5 font-mono text-[10px] text-sky-200"
                        title="2-HP Armored Cockpit"
                    >
                        <Shield class="h-2.5 w-2.5" />
                        {{ plane.cockpit_cracked ? 'CRACKED' : 'ARMOR' }}
                    </span>
                    <span
                        v-if="plane.is_destroyed"
                        class="font-mono text-[10px] font-bold text-rose-300 no-underline"
                    >
                        DOWN
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
