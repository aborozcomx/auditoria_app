<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertTriangle } from '@lucide/vue';

interface Props {
    open: boolean;
    title?: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    title: '¿Confirmar eliminación?',
    description:
        'Esta acción no se puede deshacer. Se eliminará el registro permanentemente.',
    confirmText: 'Eliminar',
    cancelText: 'Cancelar',
    loading: false,
});

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="gap-2">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                >
                    <AlertTriangle class="h-5 w-5" />
                </div>
                <div>
                    <DialogTitle class="text-lg font-semibold">{{
                        title
                    }}</DialogTitle>
                    <DialogDescription
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ description }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <DialogFooter class="mt-4 flex gap-2 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="loading"
                    @click="emit('update:open', false)"
                >
                    {{ cancelText }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    <span v-if="loading">Eliminando...</span>
                    <span v-else>{{ confirmText }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
