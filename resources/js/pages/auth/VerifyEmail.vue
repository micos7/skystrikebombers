<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify Comms Channel',
        description:
            'Please verify your email address by clicking the link we just transmitted to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email Verification — SkyStrike Bombers" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 rounded-lg border border-emerald-500/35 bg-emerald-500/10 px-3 py-2 text-center text-xs font-medium text-emerald-300"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-5 text-center"
        v-slot="{ processing }"
    >
        <Button
            :disabled="processing"
            class="w-full rounded-xl bg-emerald-500 py-2.5 font-mono text-xs font-bold tracking-wider text-slate-950 uppercase shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
        >
            <Spinner v-if="processing" />
            Resend verification email
        </Button>

        <div class="border-t border-slate-800 pt-4">
            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block font-mono text-xs text-slate-400 hover:text-emerald-300"
            >
                Log out
            </TextLink>
        </div>
    </Form>
</template>
