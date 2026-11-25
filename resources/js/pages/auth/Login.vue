<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Pilot Sign In',
        description:
            'Authenticate to track your squadron record across missions',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Pilot Sign In — SkyStrike Bombers" />

    <div
        v-if="status"
        class="mb-4 rounded-lg border border-emerald-500/35 bg-emerald-500/10 px-3 py-2 text-center text-xs font-medium text-emerald-300"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-4">
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
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="pilot@bombers.test"
                    class="border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                    >
                        Password
                    </Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="font-mono text-[11px] text-emerald-300 decoration-emerald-400/50 hover:text-emerald-200"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <Label
                    for="remember"
                    class="flex cursor-pointer items-center space-x-2.5 text-xs text-slate-300"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="border-slate-600 bg-slate-950 data-[state=checked]:border-emerald-400 data-[state=checked]:bg-emerald-500 data-[state=checked]:text-slate-950"
                    />
                    <span>Remember me on this terminal</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full rounded-xl bg-emerald-500 py-2.5 font-mono text-xs font-bold tracking-wider text-slate-950 uppercase shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div
            class="border-t border-slate-800 pt-4 text-center text-xs text-slate-400"
        >
            New pilot?
            <TextLink
                :href="register()"
                :tabindex="5"
                class="font-mono font-semibold text-emerald-300 decoration-emerald-400/50 hover:text-emerald-200"
            >
                Create an account
            </TextLink>
        </div>
    </Form>
</template>
