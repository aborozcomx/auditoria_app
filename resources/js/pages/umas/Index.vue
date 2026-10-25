<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Calendar, Coins, Edit2, Plus, Search, Trash2, X } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import DataTablePagination from '@/components/DataTablePagination.vue';
import TableSortHeader from '@/components/TableSortHeader.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import type { PaginatedData, TableFilterParams, Uma } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Catálogos', href: '#' },
            { title: 'UMAs', href: '/umas' },
        ],
    },
});

interface Props {
    umas: PaginatedData<Uma>;
    filters: TableFilterParams;
}

const props = defineProps<Props>();

// Search & filter state
const searchQuery = ref(props.filters.search || '');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = (overrides: Partial<TableFilterParams> = {}) => {
    const params: Record<string, any> = {
        search: searchQuery.value || undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        ...overrides,
    };

    // Remove undefined values
    Object.keys(params).forEach((key) => {
        if (params[key] === undefined) {
            delete params[key];
        }
    });

    router.get('/umas', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const handleSearchInput = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        applyFilters({ search: searchQuery.value || undefined });
    }, 300);
};

const clearSearch = () => {
    searchQuery.value = '';
    applyFilters({ search: undefined });
};

const handleSort = (field: string) => {
    let nextDirection: 'asc' | 'desc' = 'asc';
    if (props.filters.sort === field) {
        nextDirection = props.filters.direction === 'asc' ? 'desc' : 'asc';
    }
    applyFilters({ sort: field, direction: nextDirection });
};

const handlePerPageChange = (val: number) => {
    applyFilters({ per_page: val });
};

// Create / Edit modal state
const modalOpen = ref(false);
const editingUma = ref<Uma | null>(null);

const form = useForm({
    anio: new Date().getFullYear(),
    saldo: '',
});

const openCreateModal = () => {
    editingUma.value = null;
    form.reset();
    form.clearErrors();
    form.anio = new Date().getFullYear();
    form.saldo = '';
    modalOpen.value = true;
};

const openEditModal = (uma: Uma) => {
    editingUma.value = uma;
    form.clearErrors();
    form.anio = uma.anio;
    form.saldo = String(uma.saldo);
    modalOpen.value = true;
};

const submitForm = () => {
    if (editingUma.value) {
        form.put(`/umas/${editingUma.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('UMA actualizada exitosamente');
            },
        });
    } else {
        form.post('/umas', {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('UMA registrada exitosamente');
            },
        });
    }
};

// Delete modal state
const deleteModalOpen = ref(false);
const itemToDelete = ref<Uma | null>(null);
const isDeleting = ref(false);

const confirmDelete = (uma: Uma) => {
    itemToDelete.value = uma;
    deleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) {
        return;
    }
    isDeleting.value = true;
    router.delete(`/umas/${itemToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteModalOpen.value = false;
            itemToDelete.value = null;
            isDeleting.value = false;
            toast.success('Registro de UMA eliminado');
        },
        onError: () => {
            isDeleting.value = false;
            toast.error('Ocurrió un error al intentar eliminar la UMA');
        },
    });
};

const formatCurrency = (val: string | number) => {
    const num = Number(val);
    if (isNaN(num)) return '$0.00';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(num);
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Catálogo de UMAs" />

        <!-- Header Section -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    >
                        <Coins class="h-5 w-5" />
                    </span>
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        Catálogo de UMAs
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Valores de la Unidad de Medida y Actualización por año para
                    cálculos de topes y auditorías.
                </p>
            </div>

            <Button
                class="gap-2 self-start sm:self-auto"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />
                Nueva UMA
            </Button>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="rounded-xl border bg-card p-4 shadow-2xs">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative w-full max-w-sm">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por año o valor..."
                        class="pr-9 pl-9 text-sm"
                        @input="handleSearchInput"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="clearSearch"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <span
                        >Total histórico:
                        <strong class="text-foreground">{{
                            umas.total
                        }}</strong></span
                    >
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-hidden rounded-xl border bg-card shadow-2xs">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableSortHeader
                            field="anio"
                            label="Año"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="saldo"
                            label="Valor Diario"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableHead class="w-24 text-right">
                            <div
                                class="inline-flex w-full items-center justify-end text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Acciones
                            </div>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="uma in umas.data" :key="uma.id">
                        <TableCell>
                            <Badge
                                variant="outline"
                                class="gap-1.5 px-2.5 py-0.5 text-xs font-semibold"
                            >
                                <Calendar
                                    class="h-3 w-3 text-muted-foreground"
                                />
                                {{ uma.anio }}
                            </Badge>
                        </TableCell>
                        <TableCell
                            class="text-right font-medium text-foreground"
                        >
                            {{ formatCurrency(uma.saldo) }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    title="Editar UMA"
                                    @click="openEditModal(uma)"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                    title="Eliminar UMA"
                                    @click="confirmDelete(uma)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableEmpty v-if="umas.data.length === 0" :colspan="3">
                        <Coins class="h-10 w-10 text-muted-foreground/40" />
                        <p class="font-medium text-foreground">
                            No se encontraron registros de UMA
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                searchQuery
                                    ? 'Intenta con otro término de búsqueda.'
                                    : 'Comienza dando de alta una UMA.'
                            }}
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <DataTablePagination
                :pagination="umas"
                :per-page="filters.per_page"
                @change-per-page="handlePerPageChange"
            />
        </div>

        <!-- Create / Edit Dialog -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingUma ? 'Editar Registro de UMA' : 'Nueva UMA'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Ingresa el año fiscal y el valor diario de la UMA.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4 py-2" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="anio">Año Fiscal</Label>
                        <Input
                            id="anio"
                            v-model="form.anio"
                            type="number"
                            min="1990"
                            max="2100"
                            placeholder="Ej. 2026"
                            required
                        />
                        <InputError :message="form.errors.anio" />
                    </div>

                    <div class="space-y-2">
                        <Label for="saldo">Valor Diario / Saldo (MXN)</Label>
                        <Input
                            id="saldo"
                            v-model="form.saldo"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="Ej. 118.42"
                            required
                        />
                        <InputError :message="form.errors.saldo" />
                    </div>

                    <DialogFooter class="mt-4 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="modalOpen = false"
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else>{{
                                editingUma ? 'Actualizar' : 'Guardar'
                            }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <ConfirmDeleteModal
            :open="deleteModalOpen"
            :loading="isDeleting"
            :title="`¿Eliminar UMA ${itemToDelete?.anio}?`"
            description="Esta operación eliminará el registro de este valor fiscal. ¿Deseas continuar?"
            @update:open="deleteModalOpen = $event"
            @confirm="executeDelete"
        />
    </div>
</template>
