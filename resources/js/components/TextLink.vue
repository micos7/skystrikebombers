<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <Link
        :href="props.href"
        :tabindex="props.tabindex"
        :method="props.method"
        :as="props.as"
        :class="
            cn(
                'text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500',
                props.class,
            )
        "
    >
        <slot />
    </Link>
</template>
