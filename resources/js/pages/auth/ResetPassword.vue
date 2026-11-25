<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset Password',
        description: 'Set a new password for your pilot account below',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Reset Password — SkyStrike Bombers" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-4">
            <div class="grid gap-1.5">
                <Label
                    for="email"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    Email
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    v-model="inputEmail"
                    class="block w-full border-slate-800 bg-slate-950/60 text-slate-400"
                    readonly
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="password"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    New Password
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="block w-full border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                    autofocus
                    placeholder="New password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="password_confirmation"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    Confirm Password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    class="block w-full border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full rounded-xl bg-emerald-500 py-2.5 font-mono text-xs font-bold tracking-wider text-slate-950 uppercase shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                Reset password
            </Button>
        </div>
    </Form>
</template>
