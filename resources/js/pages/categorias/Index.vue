<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Briefcase, Edit2, Layers, Plus, Search, Trash2, X } from '@lucide/vue';
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
import type { Categoria, PaginatedData, TableFilterParams } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Catálogos', href: '#' },
            { title: 'Categorías', href: '/categorias' },
        ],
    },
});

interface Props {
    categorias: PaginatedData<Categoria>;
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

    router.get('/categorias', params, {
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
const editingCategoria = ref<Categoria | null>(null);

const form = useForm({
    nombre: '',
});

const openCreateModal = () => {
    editingCategoria.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEditModal = (categoria: Categoria) => {
    editingCategoria.value = categoria;
    form.clearErrors();
    form.nombre = categoria.nombre;
    modalOpen.value = true;
};

const submitForm = () => {
    if (editingCategoria.value) {
        form.put(`/categorias/${editingCategoria.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('Categoría actualizada exitosamente');
            },
        });
    } else {
        form.post('/categorias', {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                toast.success('Categoría creada exitosamente');
            },
        });
    }
};

// Delete modal state
const deleteModalOpen = ref(false);
const itemToDelete = ref<Categoria | null>(null);
const isDeleting = ref(false);

const confirmDelete = (categoria: Categoria) => {
    itemToDelete.value = categoria;
    deleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) {
        return;
    }
    isDeleting.value = true;
    router.delete(`/categorias/${itemToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteModalOpen.value = false;
            itemToDelete.value = null;
            isDeleting.value = false;
            toast.success('Categoría eliminada');
        },
        onError: () => {
            isDeleting.value = false;
            toast.error('Ocurrió un error al intentar eliminar la categoría');
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Catálogo de Categorías" />

        <!-- Header Section -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400"
                    >
                        <Layers class="h-5 w-5" />
                    </span>
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        Catálogo de Categorías
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Clasificación de puestos o niveles laborales para asociar
                    prestaciones y tablas de vacaciones.
                </p>
            </div>

            <Button
                class="gap-2 self-start sm:self-auto"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />
                Nueva Categoría
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
                        placeholder="Buscar por nombre de categoría..."
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
                        >Total categorías:
                        <strong class="text-foreground">{{
                            categorias.total
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
                            label="Nombre de Categoría"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="vacaciones_count"
                            label="Reglas de Vacaciones"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <TableSortHeader
                            field="created_at"
                            label="Fecha de Creación"
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
                    <TableRow v-for="cat in categorias.data" :key="cat.id">
                        <TableCell
                            class="font-mono text-xs text-muted-foreground"
                        >
                            #{{ cat.id }}
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <Briefcase
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <span class="font-medium text-foreground">{{
                                    cat.nombre
                                }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="text-center">
                            <Badge
                                :variant="
                                    Number(cat.vacaciones_count) > 0
                                        ? 'secondary'
                                        : 'outline'
                                "
                                class="text-xs"
                            >
                                {{ cat.vacaciones_count || 0 }} reglas
                            </Badge>
                        </TableCell>
                        <TableCell
                            class="text-center text-xs text-muted-foreground"
                        >
                            {{
                                new Date(cat.created_at).toLocaleDateString(
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
                                    title="Editar categoría"
                                    @click="openEditModal(cat)"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                    title="Eliminar categoría"
                                    @click="confirmDelete(cat)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableEmpty
                        v-if="categorias.data.length === 0"
                        :colspan="5"
                    >
                        <Layers class="h-10 w-10 text-muted-foreground/40" />
                        <p class="font-medium text-foreground">
                            No se encontraron categorías
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                searchQuery
                                    ? 'Intenta con otro término de búsqueda.'
                                    : 'Crea tu primera categoría laboral.'
                            }}
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <DataTablePagination
                :pagination="categorias"
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
                            editingCategoria
                                ? 'Editar Categoría'
                                : 'Nueva Categoría'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Ingresa el nombre descriptivo de la categoría o
                        departamento.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4 py-2" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="cat-nombre">Nombre de la Categoría</Label>
                        <Input
                            id="cat-nombre"
                            v-model="form.nombre"
                            type="text"
                            placeholder="Ej. Operativo, Administrativo, Confianza..."
                            maxlength="150"
                            required
                        />
                        <InputError :message="form.errors.nombre" />
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
                                editingCategoria ? 'Actualizar' : 'Guardar'
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
            :title="`¿Eliminar categoría '${itemToDelete?.nombre}'?`"
            description="Al eliminar esta categoría, también se eliminarán las reglas de vacaciones vinculadas. ¿Deseas continuar?"
            @update:open="deleteModalOpen = $event"
            @confirm="executeDelete"
        />
    </div>
</template>
