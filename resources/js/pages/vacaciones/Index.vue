<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Clock,
    Edit2,
    Palmtree,
    Plus,
    Search,
    Trash2,
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
import type {
    Categoria,
    PaginatedData,
    TableFilterParams,
    Vacacion,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Catálogos', href: '#' },
            { title: 'Vacaciones', href: '/vacaciones' },
        ],
    },
});

interface Props {
    vacaciones: PaginatedData<Vacacion>;
    categorias: Categoria[];
    filters: TableFilterParams;
}

const props = defineProps<Props>();

// Search & filter state
const searchQuery = ref(props.filters.search || '');
const selectedCategoriaId = ref<number | string>(
    props.filters.categoria_id || '',
);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const applyFilters = (overrides: Partial<TableFilterParams> = {}) => {
    const params: Record<string, any> = {
        search: searchQuery.value || undefined,
        categoria_id: selectedCategoriaId.value || undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        ...overrides,
    };

    Object.keys(params).forEach((key) => {
        if (params[key] === undefined || params[key] === '') {
            delete params[key];
        }
    });

    router.get('/vacaciones', params, {
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

const handleCategoryChange = (event: Event) => {
    const val = (event.target as HTMLSelectElement).value;
    selectedCategoriaId.value = val;
    applyFilters({ categoria_id: val ? Number(val) : undefined });
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
const editingVacacion = ref<Vacacion | null>(null);

const form = useForm({
    categoria_id: '' as string | number,
    anios: 1,
    dias: 12,
});

const openCreateModal = () => {
    editingVacacion.value = null;
    form.reset();
    form.clearErrors();
    form.categoria_id =
        props.categorias.length > 0 ? props.categorias[0].id : '';
    form.anios = 1;
    form.dias = 12;
    modalOpen.value = true;
};

const openEditModal = (vacacion: Vacacion) => {
    editingVacacion.value = vacacion;
    form.clearErrors();
    form.categoria_id = vacacion.categoria_id;
    form.anios = vacacion.anios;
    form.dias = vacacion.dias;
    modalOpen.value = true;
};

const submitForm = () => {
    if (editingVacacion.value) {
        form.put(`/vacaciones/${editingVacacion.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('Regla de vacaciones actualizada exitosamente');
            },
        });
    } else {
        form.post('/vacaciones', {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('Regla de vacaciones creada exitosamente');
            },
        });
    }
};

// Delete modal state
const deleteModalOpen = ref(false);
const itemToDelete = ref<Vacacion | null>(null);
const isDeleting = ref(false);

const confirmDelete = (vacacion: Vacacion) => {
    itemToDelete.value = vacacion;
    deleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) {
        return;
    }
    isDeleting.value = true;
    router.delete(`/vacaciones/${itemToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteModalOpen.value = false;
            itemToDelete.value = null;
            isDeleting.value = false;
            toast.success('Regla de vacaciones eliminada');
        },
        onError: () => {
            isDeleting.value = false;
            toast.error('Ocurrió un error al eliminar la regla');
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Tabla de Vacaciones" />

        <!-- Header Section -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400"
                    >
                        <Palmtree class="h-5 w-5" />
                    </span>
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        Catálogo de Vacaciones
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Tabulador de días de vacaciones correspondientes según años
                    de antigüedad por categoría laboral.
                </p>
            </div>

            <Button
                class="gap-2 self-start sm:self-auto"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />
                Nueva Regla de Vacaciones
            </Button>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="rounded-xl border bg-card p-4 shadow-2xs">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div
                    class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <!-- Search input -->
                    <div class="relative w-full max-w-xs">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar por categoría o días..."
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

                    <!-- Category Filter select -->
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs whitespace-nowrap text-muted-foreground"
                            >Filtrar categoría:</span
                        >
                        <select
                            :value="selectedCategoriaId"
                            @change="handleCategoryChange"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-hidden"
                        >
                            <option value="">Todas las categorías</option>
                            <option
                                v-for="cat in categorias"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.nombre }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <span
                        >Total registros:
                        <strong class="text-foreground">{{
                            vacaciones.total
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
                            field="categoria_id"
                            label="Categoría"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="anios"
                            label="Años Antigüedad"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="dias"
                            label="Días de Vacaciones"
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
                    <TableRow v-for="vac in vacaciones.data" :key="vac.id">
                        <TableCell
                            class="font-mono text-xs text-muted-foreground"
                        >
                            #{{ vac.id }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="secondary"
                                class="text-xs font-medium"
                            >
                                {{
                                    vac.categoria?.nombre ||
                                    'Categoría #' + vac.categoria_id
                                }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-center">
                            <span
                                class="inline-flex items-center gap-1 font-semibold text-foreground"
                            >
                                <Clock
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                {{ vac.anios }}
                                {{ vac.anios === 1 ? 'año' : 'años' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-center">
                            <span
                                class="inline-flex items-center gap-1 font-bold text-teal-600 dark:text-teal-400"
                            >
                                <CalendarDays class="h-3.5 w-3.5" />
                                {{ vac.dias }} días
                            </span>
                        </TableCell>
                        <TableCell
                            class="text-center text-xs text-muted-foreground"
                        >
                            {{
                                new Date(vac.created_at).toLocaleDateString(
                                    'es-MX',
                                )
                            }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    title="Editar regla"
                                    @click="openEditModal(vac)"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                    title="Eliminar regla"
                                    @click="confirmDelete(vac)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableEmpty
                        v-if="vacaciones.data.length === 0"
                        :colspan="6"
                    >
                        <Palmtree class="h-10 w-10 text-muted-foreground/40" />
                        <p class="font-medium text-foreground">
                            No se encontraron reglas de vacaciones
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                searchQuery || selectedCategoriaId
                                    ? 'Intenta ajustando los filtros de búsqueda.'
                                    : 'Crea la primera regla del tabulador de vacaciones.'
                            }}
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <DataTablePagination
                :pagination="vacaciones"
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
                            editingVacacion
                                ? 'Editar Regla de Vacaciones'
                                : 'Nueva Regla de Vacaciones'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Asigna los días de vacaciones que corresponden según los
                        años trabajados y la categoría laboral.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4 py-2" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="vac-categoria">Categoría</Label>
                        <select
                            id="vac-categoria"
                            v-model="form.categoria_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-hidden"
                            required
                        >
                            <option value="" disabled>
                                Selecciona una categoría
                            </option>
                            <option
                                v-for="cat in categorias"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.nombre }}
                            </option>
                        </select>
                        <InputError :message="form.errors.categoria_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="vac-anios">Años Antigüedad</Label>
                            <Input
                                id="vac-anios"
                                v-model="form.anios"
                                type="number"
                                min="1"
                                max="60"
                                placeholder="Ej. 1"
                                required
                            />
                            <InputError :message="form.errors.anios" />
                        </div>

                        <div class="space-y-2">
                            <Label for="vac-dias">Días Vacaciones</Label>
                            <Input
                                id="vac-dias"
                                v-model="form.dias"
                                type="number"
                                min="1"
                                max="365"
                                placeholder="Ej. 12"
                                required
                            />
                            <InputError :message="form.errors.dias" />
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
                                editingVacacion ? 'Actualizar' : 'Guardar'
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
            :title="`¿Eliminar regla de vacaciones?`"
            :description="`Se eliminará la regla de ${itemToDelete?.anios} años (${itemToDelete?.dias} días) para la categoría correspondiente. ¿Deseas continuar?`"
            @update:open="deleteModalOpen = $event"
            @confirm="executeDelete"
        />
    </div>
</template>
