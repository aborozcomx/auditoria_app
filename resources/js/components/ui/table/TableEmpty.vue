<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import TableRow from './TableRow.vue';
import TableCell from './TableCell.vue';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        colspan?: number;
    }>(),
    {
        colspan: 1,
    },
);
</script>

<template>
    <TableRow>
        <TableCell
            :class="
                cn(
                    'p-8 text-center text-sm text-muted-foreground',
                    props.class,
                )
            "
            :colspan="props.colspan"
        >
            <div class="flex flex-col items-center justify-center gap-2">
                <slot />
            </div>
        </TableCell>
    </TableRow>
</template>
