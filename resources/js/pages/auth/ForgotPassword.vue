<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recover Access',
        description:
            'Enter your email address to receive a password reset transmission',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot Password — SkyStrike Bombers" />

    <div
        v-if="status"
        class="mb-4 rounded-lg border border-emerald-500/35 bg-emerald-500/10 px-3 py-2 text-center text-xs font-medium text-emerald-300"
    >
        {{ status }}
    </div>

    <div class="space-y-5">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-1.5">
                <Label
                    for="email"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    Email address
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    v-focus
                    placeholder="pilot@bombers.test"
                    class="border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="mt-5 flex items-center justify-start">
                <Button
                    class="w-full rounded-xl bg-emerald-500 py-2.5 font-mono text-xs font-bold tracking-wider text-slate-950 uppercase shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Email password reset link
                </Button>
            </div>
        </Form>

        <div
            class="border-t border-slate-800 pt-4 text-center text-xs text-slate-400"
        >
            <span>Remembered your credentials?</span>
            <TextLink
                :href="login()"
                class="ml-1 font-mono font-semibold text-emerald-300 decoration-emerald-400/50 hover:text-emerald-200"
            >
                Return to log in
            </TextLink>
        </div>
    </div>
</template>
