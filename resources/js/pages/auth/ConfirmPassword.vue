<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirm Clearance',
        description:
            'This is a restricted sector. Confirm your password before proceeding.',
    },
});
</script>

<template>
    <Head title="Confirm Password — SkyStrike Bombers" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-5">
            <div class="grid gap-1.5">
                <Label
                    htmlFor="password"
                    class="font-mono text-[11px] tracking-wider text-slate-300 uppercase"
                >
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="block w-full border-slate-700 bg-slate-950 text-slate-100 placeholder:text-slate-500 focus-visible:border-emerald-400 focus-visible:ring-emerald-400/25"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full rounded-xl bg-emerald-500 py-2.5 font-mono text-xs font-bold tracking-wider text-slate-950 uppercase shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Confirm password
                </Button>
            </div>
        </div>
    </Form>
</template>
