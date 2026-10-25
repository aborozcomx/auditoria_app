<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';
import { TableHead } from '@/components/ui/table';

interface Props {
    field: string;
    currentSort?: string;
    currentDirection?: 'asc' | 'desc';
    label: string;
    align?: 'left' | 'right' | 'center';
}

const props = withDefaults(defineProps<Props>(), {
    currentSort: '',
    currentDirection: 'asc',
    align: 'left',
});

const emit = defineEmits<{
    (e: 'sort', field: string): void;
}>();

const handleSort = () => {
    emit('sort', props.field);
};
</script>

<template>
    <TableHead
        :class="[
            'cursor-pointer transition-colors select-none hover:text-foreground',
            align === 'right'
                ? 'text-right'
                : align === 'center'
                  ? 'text-center'
                  : 'text-left',
        ]"
        @click="handleSort"
    >
        <div
            :class="[
                'inline-flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase',
                align === 'right'
                    ? 'justify-end'
                    : align === 'center'
                      ? 'justify-center'
                      : 'justify-start',
            ]"
        >
            <span>{{ label }}</span>
            <span class="text-muted-foreground/60">
                <template v-if="currentSort === field">
                    <ArrowUp
                        v-if="currentDirection === 'asc'"
                        class="h-3.5 w-3.5 text-primary"
                    />
                    <ArrowDown v-else class="h-3.5 w-3.5 text-primary" />
                </template>
                <ArrowUpDown
                    v-else
                    class="h-3 w-3 opacity-40 hover:opacity-100"
                />
            </span>
        </div>
    </TableHead>
</template>
