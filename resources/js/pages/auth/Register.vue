<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Enlist Pilot Account',
        description:
            'Register your callsign to save match history and compete across devices',
    },
});
</script>

<template>
    <Head title="Register — SkyStrike Bombers" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-4">
            <div class="grid gap-1.5">
                <Label
                    for="name"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    Pilot Callsign / Name
                </Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Maverick"
                    class="border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                />
                <InputError :message="errors.name" />
            </div>

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
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="pilot@bombers.test"
                    class="border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="password"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Create password"
                    :passwordrules="passwordRules"
                    class="border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-1.5">
                <Label
                    for="password_confirmation"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    Confirm password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                    class="border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full rounded-xl bg-emerald-500 py-2.5 font-mono text-xs font-bold tracking-wider text-slate-950 uppercase shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div
            class="border-t border-slate-800 pt-4 text-center text-xs text-slate-400"
        >
            Already enlisted?
            <TextLink
                :href="login()"
                class="font-mono font-semibold text-emerald-300 decoration-emerald-400/50 hover:text-emerald-200"
                :tabindex="6"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
