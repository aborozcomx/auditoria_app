<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    Calendar,
    Coins,
    Edit2,
    FileSpreadsheet,
    Plus,
    Receipt,
    Search,
    ShieldCheck,
    Trash2,
    TrendingUp,
    X,
} from '@lucide/vue';
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
import type { Empresa, PaginatedData, TableFilterParams } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Empresas', href: '/empresas' },
        ],
    },
});

interface Props {
    empresas: PaginatedData<Empresa>;
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

    Object.keys(params).forEach((key) => {
        if (params[key] === undefined) {
            delete params[key];
        }
    });

    router.get('/empresas', params, {
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
const editingEmpresa = ref<Empresa | null>(null);

const form = useForm({
    nombre: '',
    rfc: '',
    razon_social: '',
    dias_aguinaldo: 15,
    prima_vacacional: 25.0,
});

const openCreateModal = () => {
    editingEmpresa.value = null;
    form.reset();
    form.clearErrors();
    form.nombre = '';
    form.rfc = '';
    form.razon_social = '';
    form.dias_aguinaldo = 15;
    form.prima_vacacional = 25.0;
    modalOpen.value = true;
};

const openEditModal = (empresa: Empresa) => {
    editingEmpresa.value = empresa;
    form.clearErrors();
    form.nombre = empresa.nombre;
    form.rfc = empresa.rfc;
    form.razon_social = empresa.razon_social;
    form.dias_aguinaldo = empresa.dias_aguinaldo;
    form.prima_vacacional = Number(empresa.prima_vacacional);
    modalOpen.value = true;
};

const submitForm = () => {
    // Normalize RFC to uppercase
    form.rfc = form.rfc.toUpperCase().trim();

    if (editingEmpresa.value) {
        form.put(`/empresas/${editingEmpresa.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('Empresa actualizada exitosamente');
            },
        });
    } else {
        form.post('/empresas', {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('Empresa registrada exitosamente');
            },
        });
    }
};

// Delete modal state
const deleteModalOpen = ref(false);
const itemToDelete = ref<Empresa | null>(null);
const isDeleting = ref(false);

const confirmDelete = (empresa: Empresa) => {
    itemToDelete.value = empresa;
    deleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) {
        return;
    }
    isDeleting.value = true;
    router.delete(`/empresas/${itemToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteModalOpen.value = false;
            itemToDelete.value = null;
            isDeleting.value = false;
            toast.success('Empresa eliminada');
        },
        onError: () => {
            isDeleting.value = false;
            toast.error('Ocurrió un error al intentar eliminar la empresa');
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Empresas para Auditorías" />

        <!-- Header Section -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                    >
                        <Building2 class="h-5 w-5" />
                    </span>
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        Empresas Clientes
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Catálogo de empresas a auditar, parámetros fiscales y
                    prestaciones laborales configuradas.
                </p>
            </div>

            <Button
                class="gap-2 self-start sm:self-auto"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />
                Nueva Empresa
            </Button>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="rounded-xl border bg-card p-4 shadow-2xs">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative w-full max-w-md">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por Nombre, RFC o Razón Social..."
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
                        >Empresas dadas de alta:
                        <strong class="text-foreground">{{
                            empresas.total
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
                            field="id"
                            label="ID"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="nombre"
                            label="Nombre Comercial / Razón Social"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="rfc"
                            label="RFC"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="dias_aguinaldo"
                            label="Días Aguinaldo"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="prima_vacacional"
                            label="Prima Vacacional"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="created_at"
                            label="Fecha Registro"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableHead class="w-32 text-right">
                            <div
                                class="inline-flex w-full items-center justify-end text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Acciones
                            </div>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="emp in empresas.data" :key="emp.id">
                        <TableCell
                            class="font-mono text-xs text-muted-foreground"
                        >
                            #{{ emp.id }}
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-col">
                                <span class="font-semibold text-foreground">{{
                                    emp.nombre
                                }}</span>
                                <span
                                    class="line-clamp-1 text-xs text-muted-foreground"
                                    >{{ emp.razon_social }}</span
                                >
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                class="font-mono text-xs font-semibold tracking-wider uppercase"
                            >
                                {{ emp.rfc }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-center">
                            <span
                                class="inline-flex items-center gap-1 font-semibold text-foreground"
                            >
                                <Calendar
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                {{ emp.dias_aguinaldo }} días
                            </span>
                        </TableCell>
                        <TableCell class="text-center">
                            <span
                                class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                <Coins class="h-3.5 w-3.5" />
                                {{ emp.prima_vacacional }}%
                            </span>
                        </TableCell>
                        <TableCell
                            class="text-center text-xs text-muted-foreground"
                        >
                            {{
                                new Date(emp.created_at).toLocaleDateString(
                                    'es-MX',
                                )
                            }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    :href="`/empresas/${emp.id}/importar-cfdi`"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-emerald-600 transition-colors hover:bg-emerald-500/10 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                                    title="Importar CFDIs"
                                >
                                    <FileSpreadsheet class="h-4 w-4" />
                                </Link>
                                <Link
                                    :href="`/empresas/${emp.id}/importar-variables`"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-indigo-600 transition-colors hover:bg-indigo-500/10 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    title="Importar Variables de Empleados"
                                >
                                    <TrendingUp class="h-4 w-4" />
                                </Link>
                                <Link
                                    :href="`/empresas/${emp.id}/importar-cuotas-imss`"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-amber-600 transition-colors hover:bg-amber-500/10 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                                    title="Importar Cuotas IMSS"
                                >
                                    <Receipt class="h-4 w-4" />
                                </Link>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    title="Editar empresa"
                                    @click="openEditModal(emp)"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                    title="Eliminar empresa"
                                    @click="confirmDelete(emp)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableEmpty v-if="empresas.data.length === 0" :colspan="7">
                        <Building2 class="h-10 w-10 text-muted-foreground/40" />
                        <p class="font-medium text-foreground">
                            No se encontraron empresas
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                searchQuery
                                    ? 'No hay resultados que coincidan con la búsqueda.'
                                    : 'Agrega tu primera empresa para comenzar las auditorías contables.'
                            }}
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <DataTablePagination
                :pagination="empresas"
                :per-page="filters.per_page"
                @change-per-page="handlePerPageChange"
            />
        </div>

        <!-- Create / Edit Dialog -->
        <Dialog :open="modalOpen" @update:open="modalOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingEmpresa
                                ? 'Editar Empresa'
                                : 'Dar de Alta Empresa'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Datos fiscales y parámetros de cálculo laboral para
                        auditoría contable.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4 py-2" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="emp-nombre">Nombre Comercial</Label>
                        <Input
                            id="emp-nombre"
                            v-model="form.nombre"
                            type="text"
                            placeholder="Ej. Grupo Industrial Gamma"
                            maxlength="150"
                            required
                        />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="emp-rfc">RFC Oficial</Label>
                            <Input
                                id="emp-rfc"
                                v-model="form.rfc"
                                type="text"
                                placeholder="GIG980512XYZ"
                                maxlength="13"
                                class="font-mono uppercase"
                                required
                            />
                            <InputError :message="form.errors.rfc" />
                        </div>

                        <div class="space-y-2">
                            <Label for="emp-razon">Razón Social</Label>
                            <Input
                                id="emp-razon"
                                v-model="form.razon_social"
                                type="text"
                                placeholder="Grupo Industrial Gamma S.A. de C.V."
                                maxlength="255"
                                required
                            />
                            <InputError :message="form.errors.razon_social" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="emp-aguinaldo">Días de Aguinaldo</Label>
                            <Input
                                id="emp-aguinaldo"
                                v-model="form.dias_aguinaldo"
                                type="number"
                                min="1"
                                max="365"
                                placeholder="15"
                                required
                            />
                            <span class="text-[11px] text-muted-foreground"
                                >Mínimo legal LFT: 15 días</span
                            >
                            <InputError :message="form.errors.dias_aguinaldo" />
                        </div>

                        <div class="space-y-2">
                            <Label for="emp-prima">Prima Vacacional (%)</Label>
                            <Input
                                id="emp-prima"
                                v-model="form.prima_vacacional"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                placeholder="25.00"
                                required
                            />
                            <span class="text-[11px] text-muted-foreground"
                                >Mínimo legal LFT: 25.00%</span
                            >
                            <InputError
                                :message="form.errors.prima_vacacional"
                            />
                        </div>
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
                                editingEmpresa
                                    ? 'Actualizar'
                                    : 'Guardar Empresa'
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
            :title="`¿Eliminar empresa '${itemToDelete?.nombre}'?`"
            description="Esta acción eliminará los parámetros fiscales configurados para esta empresa. ¿Deseas continuar?"
            @update:open="deleteModalOpen = $event"
            @confirm="executeDelete"
        />
    </div>
</template>
