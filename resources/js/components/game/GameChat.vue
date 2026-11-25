<script setup lang="ts">
import { ref } from 'vue';
import { MessageSquare, Send } from '@lucide/vue';
import type { ChatMessage } from '@/types/game';

const props = defineProps<{
    messages: ChatMessage[];
    mySlot: number | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    send: [body: string, isEmote: boolean];
}>();

const draft = ref('');

const quickEmotes = ['Target Locked!', 'Direct Hit!', 'Close Call!', 'GG!'];

function submitMessage(): void {
    const body = draft.value.trim();

    if (!body || props.disabled) {
        return;
    }

    emit('send', body, false);
    draft.value = '';
}
</script>

<template>
    <div
        class="flex flex-col rounded-xl border border-slate-700/80 bg-slate-900/85 p-3 shadow-lg backdrop-blur"
    >
        <div class="mb-2 flex items-center gap-1.5">
            <MessageSquare class="h-3.5 w-3.5 text-emerald-400" />
            <span
                class="font-mono text-xs font-semibold tracking-widest text-slate-300 uppercase"
            >
                Tactical Comms
            </span>
        </div>

        <div
            class="mb-2 max-h-40 min-h-24 space-y-1.5 overflow-y-auto rounded-lg border border-slate-800 bg-slate-950/70 p-2 text-xs"
        >
            <p
                v-if="messages.length === 0"
                class="py-4 text-center font-mono text-[11px] text-slate-500"
            >
                Radio silence...
            </p>
            <div
                v-for="msg in messages"
                :key="msg.id"
                class="flex flex-col"
                :class="
                    msg.sender_slot === mySlot ? 'items-end' : 'items-start'
                "
            >
                <span class="font-mono text-[10px] text-slate-500">
                    {{ msg.sender_callsign }}
                </span>
                <span
                    class="rounded-md px-2 py-1"
                    :class="
                        msg.sender_slot === mySlot
                            ? 'bg-emerald-600/30 text-emerald-100'
                            : 'bg-slate-800 text-slate-200'
                    "
                >
                    {{ msg.body }}
                </span>
            </div>
        </div>

        <div class="mb-2 flex flex-wrap gap-1">
            <button
                v-for="emote in quickEmotes"
                :key="emote"
                type="button"
                :disabled="disabled"
                class="rounded border border-slate-700 bg-slate-800/80 px-2 py-0.5 font-mono text-[10px] text-slate-300 transition hover:border-emerald-400/50 hover:text-emerald-200 disabled:opacity-40"
                @click="emit('send', emote, true)"
            >
                {{ emote }}
            </button>
        </div>

        <form class="flex gap-1.5" @submit.prevent="submitMessage">
            <input
                v-model="draft"
                type="text"
                maxlength="280"
                :disabled="disabled"
                placeholder="Transmit message..."
                class="flex-1 rounded-md border border-slate-700 bg-slate-950 px-2.5 py-1.5 text-xs text-slate-100 placeholder-slate-500 focus:border-emerald-400 focus:outline-none"
            />
            <button
                type="submit"
                :disabled="disabled || !draft.trim()"
                class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-2.5 py-1.5 text-white transition hover:bg-emerald-500 disabled:opacity-40"
            >
                <Send class="h-3.5 w-3.5" />
            </button>
        </form>
    </div>
</template>
