<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BadgeAlert,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Eye,
    FileSpreadsheet,
    Filter,
    Layers,
    ReceiptText,
    RotateCcw,
    Search,
    ShieldAlert,
    TrendingUp,
    UserCheck,
    Users,
    X,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import DataTablePagination from '@/components/DataTablePagination.vue';
import TableSortHeader from '@/components/TableSortHeader.vue';
import type {
    EmpleadoSalario,
    Empresa,
    PaginatedData,
    SalariosTotales,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Salarios Empleados', href: '/salarios' },
        ],
    },
});

interface Filters {
    search?: string;
    empresa_id?: number | null;
    anio?: number | null;
    periodo?: string | null;
    solo_excedidos?: boolean;
    solo_diferencias?: boolean;
    sin_diferencias?: boolean;
    diferencia?: 'con' | 'sin' | '' | null;
    sort?: string;
    direction?: 'asc' | 'desc';
    per_page?: number;
}

interface Props {
    salarios: PaginatedData<EmpleadoSalario>;
    empresas: Array<{ id: number; nombre: string; rfc: string }>;
    anios: number[];
    periodos: string[];
    totales: SalariosTotales;
    filters: Filters;
}

const props = defineProps<Props>();

// Filters state
const searchQuery = ref(props.filters.search || '');
const selectedEmpresaId = ref<string | number>(props.filters.empresa_id || '');
const selectedAnio = ref<string | number>(props.filters.anio || '');
const selectedPeriodo = ref<string>(props.filters.periodo || '');
const soloExcedidos = ref<boolean>(!!props.filters.solo_excedidos);
const soloDiferencias = ref<boolean>(!!props.filters.solo_diferencias || props.filters.diferencia === 'con');
const sinDiferencias = ref<boolean>(!!props.filters.sin_diferencias || props.filters.diferencia === 'sin');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const hasActiveFilters = computed(() => {
    return (
        !!searchQuery.value ||
        !!selectedEmpresaId.value ||
        !!selectedAnio.value ||
        !!selectedPeriodo.value ||
        soloExcedidos.value ||
        soloDiferencias.value ||
        sinDiferencias.value
    );
});

const applyFilters = (overrides: Partial<Filters> = {}) => {
    const params: Record<string, any> = {
        search: searchQuery.value || undefined,
        empresa_id: selectedEmpresaId.value || undefined,
        anio: selectedAnio.value || undefined,
        periodo: selectedPeriodo.value || undefined,
        solo_excedidos: soloExcedidos.value ? true : undefined,
        solo_diferencias: soloDiferencias.value ? true : undefined,
        sin_diferencias: sinDiferencias.value ? true : undefined,
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

    router.get('/salarios', params, {
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

const handleEmpresaChange = (event: Event) => {
    const val = (event.target as HTMLSelectElement).value;
    selectedEmpresaId.value = val;
    applyFilters({ empresa_id: val ? Number(val) : undefined });
};

const handleAnioChange = (event: Event) => {
    const val = (event.target as HTMLSelectElement).value;
    selectedAnio.value = val;
    applyFilters({ anio: val ? Number(val) : undefined });
};

const handlePeriodoChange = (event: Event) => {
    const val = (event.target as HTMLSelectElement).value;
    selectedPeriodo.value = val;
    applyFilters({ periodo: val || undefined });
};

const toggleSoloExcedidos = () => {
    soloExcedidos.value = !soloExcedidos.value;
    applyFilters({ solo_excedidos: soloExcedidos.value ? true : undefined });
};

const toggleSoloDiferencias = () => {
    if (soloDiferencias.value) {
        soloDiferencias.value = false;
    } else {
        soloDiferencias.value = true;
        sinDiferencias.value = false;
    }
    applyFilters({
        solo_diferencias: soloDiferencias.value ? true : undefined,
        sin_diferencias: undefined,
    });
};

const toggleSinDiferencias = () => {
    if (sinDiferencias.value) {
        sinDiferencias.value = false;
    } else {
        sinDiferencias.value = true;
        soloDiferencias.value = false;
    }
    applyFilters({
        sin_diferencias: sinDiferencias.value ? true : undefined,
        solo_diferencias: undefined,
    });
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedEmpresaId.value = '';
    selectedAnio.value = '';
    selectedPeriodo.value = '';
    soloExcedidos.value = false;
    soloDiferencias.value = false;
    sinDiferencias.value = false;
    applyFilters({
        search: undefined,
        empresa_id: undefined,
        anio: undefined,
        periodo: undefined,
        solo_excedidos: undefined,
        solo_diferencias: undefined,
        sin_diferencias: undefined,
    });
};

const handleSort = (field: string) => {
    const currentSort = props.filters.sort;
    const currentDirection = props.filters.direction || 'asc';

    let newDirection: 'asc' | 'desc' = 'asc';
    if (currentSort === field) {
        newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
    }

    applyFilters({ sort: field, direction: newDirection });
};

const handlePerPageChange = (newPerPage: number) => {
    applyFilters({ per_page: newPerPage });
};

// Modal details for single salary audit
const selectedSalario = ref<EmpleadoSalario | null>(null);
const detailModalOpen = ref(false);

const viewDetail = (salario: EmpleadoSalario) => {
    selectedSalario.value = salario;
    detailModalOpen.value = true;
};

const formatCurrency = (val: number | string | null | undefined): string => {
    if (val === null || val === undefined || isNaN(Number(val))) {
        return '$0.00';
    }
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(val));
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Salarios de Empleados" />

        <!-- Header Section -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    >
                        <ReceiptText class="h-5 w-5" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Salarios y Cálculos de Empleados
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Auditoría de percepciones, aguinaldo diario, prima vacacional y topes de SDI calculados a partir de nóminas ordinarias.
                </p>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <Button variant="outline" as-child class="gap-2">
                    <Link href="/empresas">
                        <Building2 class="h-4 w-4" />
                        Ver Empresas
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Summary Metric Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Total Registros</span>
                    <Users class="h-4 w-4 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ totales.total_registros.toLocaleString('es-MX') }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Salarios ordinarios calculados
                </p>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Salario Base Promedio</span>
                    <DollarSign class="h-4 w-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatCurrency(totales.promedio_salario_base) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Por jornada ordinaria
                </p>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">SDI Promedio</span>
                    <TrendingUp class="h-4 w-4 text-indigo-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ formatCurrency(totales.promedio_sdi) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Integrado con prestaciones
                </p>
            </div>

            <div
                class="rounded-xl border bg-card p-4 shadow-2xs transition-colors"
                :class="totales.con_exceso_tope > 0 ? 'border-amber-500/30 bg-amber-500/5' : ''"
            >
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Exceden Tope 25 UMAS</span>
                    <ShieldAlert
                        class="h-4 w-4"
                        :class="totales.con_exceso_tope > 0 ? 'text-amber-500' : 'text-muted-foreground'"
                    />
                </div>
                <p
                    class="mt-2 text-2xl font-bold"
                    :class="totales.con_exceso_tope > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'"
                >
                    {{ totales.con_exceso_tope.toLocaleString('es-MX') }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Casos que rebasan el tope legal
                </p>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="rounded-xl border bg-card p-4 shadow-2xs space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
                <!-- Search text input -->
                <div class="relative lg:col-span-4">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por empleado, RFC, núm. o NSS..."
                        class="pr-9 pl-9 text-sm"
                        @input="handleSearchInput"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="() => { searchQuery = ''; applyFilters({ search: undefined }); }"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Empresa Filter -->
                <div class="lg:col-span-3">
                    <select
                        :value="selectedEmpresaId"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handleEmpresaChange"
                    >
                        <option value="">Todas las empresas</option>
                        <option
                            v-for="emp in empresas"
                            :key="emp.id"
                            :value="emp.id"
                        >
                            {{ emp.nombre }} ({{ emp.rfc }})
                        </option>
                    </select>
                </div>

                <!-- Año Filter -->
                <div class="lg:col-span-2">
                    <select
                        :value="selectedAnio"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handleAnioChange"
                    >
                        <option value="">Todos los años</option>
                        <option v-for="yr in anios" :key="yr" :value="yr">
                            Año {{ yr }}
                        </option>
                    </select>
                </div>

                <!-- Periodo Filter -->
                <div class="lg:col-span-2">
                    <select
                        :value="selectedPeriodo"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handlePeriodoChange"
                    >
                        <option value="">Todos los periodos</option>
                        <option v-for="per in periodos" :key="per" :value="per">
                            Periodo {{ per }}
                        </option>
                    </select>
                </div>

                <!-- Reset / Clear Button -->
                <div class="flex items-center gap-2 lg:col-span-1">
                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="icon"
                        class="h-9 w-9 text-muted-foreground hover:text-foreground"
                        title="Limpiar todos los filtros"
                        @click="resetFilters"
                    >
                        <RotateCcw class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Second row: Toggle solo excedidos, diferencias & quick chips -->
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t text-xs text-muted-foreground">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-colors"
                        :class="soloExcedidos ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30' : 'bg-muted hover:bg-muted/80 text-foreground'"
                        @click="toggleSoloExcedidos"
                    >
                        <ShieldAlert class="h-3.5 w-3.5" />
                        Solo con exceso de tope (25 UMA)
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-colors"
                        :class="
                            soloDiferencias
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30'
                                : 'bg-muted hover:bg-muted/80 text-foreground'
                        "
                        @click="toggleSoloDiferencias"
                    >
                        <DollarSign class="h-3.5 w-3.5" />
                        Solo con diferencias
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-colors"
                        :class="
                            sinDiferencias
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30'
                                : 'bg-muted hover:bg-muted/80 text-foreground'
                        "
                        @click="toggleSinDiferencias"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        Sin diferencias
                    </button>

                    <span v-if="hasActiveFilters" class="text-xs text-primary font-medium">
                        Filtros activos
                    </span>
                </div>

                <div>
                    Mostrando
                    <strong class="text-foreground">{{ salarios.data.length }}</strong>
                    de
                    <strong class="text-foreground">{{ salarios.total }}</strong>
                    registros
                </div>
            </div>
        </div>

        <!-- Table Container with Horizontal Scroll -->
        <div class="overflow-x-auto rounded-xl border bg-card shadow-2xs">
            <Table class="min-w-max text-xs">
                <TableHeader>
                    <TableRow class="bg-muted/40">
                        <!-- 1. Periodo -->
                        <TableSortHeader
                            field="periodo"
                            label="Periodo"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 2. Año -->
                        <TableSortHeader
                            field="anio"
                            label="Año"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 3. Nombre -->
                        <TableSortHeader
                            field="nombre"
                            label="Nombre"
                            align="left"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 4. Numero empleado -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-center">
                            Numero empleado
                        </TableHead>
                        <!-- 5. Numero de seguridad social -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-center">
                            Numero de seguridad social
                        </TableHead>
                        <!-- 6. Fecha inicio -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-center">
                            Fecha inicio
                        </TableHead>
                        <!-- 7. Antigüedad Semanas -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-center">
                            Antigüedad (Semanas)
                        </TableHead>
                        <!-- 8. Antigüedad Años -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-center">
                            Antigüedad (Años)
                        </TableHead>
                        <!-- 9. Salario diario -->
                        <TableSortHeader
                            field="salario_base"
                            label="Salario diario"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 10. Aguinaldo diario -->
                        <TableSortHeader
                            field="aguinaldo_diario"
                            label="Aguinaldo diario"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 11. Dias de vacaciones -->
                        <TableSortHeader
                            field="dias_vacaciones"
                            label="Dias de vacaciones"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 12. Importe de vacaciones -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-right">
                            Importe de vacaciones
                        </TableHead>
                        <!-- 13. Prima vacacional -->
                        <TableSortHeader
                            field="prima_vacacional"
                            label="Prima vacacional"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 14. Salario diario integrado abreviado como SDI -->
                        <TableSortHeader
                            field="salario_diario_integrado"
                            label="SDI"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 15. Salario diario integrado CFDI abreviado como SDI CFDI -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-right">
                            SDI CFDI
                        </TableHead>
                        <!-- 16. Tope SDI -->
                        <TableSortHeader
                            field="limite_salario_diario"
                            label="Tope SDI"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 17. Cantidad Días -->
                        <TableSortHeader
                            field="cantidad_dias"
                            label="Cantidad Días"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 18. Importe Suma -->
                        <TableSortHeader
                            field="importe_suma"
                            label="Importe Suma"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 19. SDI Variable -->
                        <TableSortHeader
                            field="sdi_variable"
                            label="SDI Variable"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 20. SDI Total -->
                        <TableSortHeader
                            field="sdi_total"
                            label="SDI Total"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 21. SDI Auditado -->
                        <TableSortHeader
                            field="sdi_auditado"
                            label="SDI Auditado"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 22. IMSS Cía -->
                        <TableSortHeader
                            field="imss_cia"
                            label="IMSS Cía"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 23. Diferencia -->
                        <TableSortHeader
                            field="diferencia"
                            label="Diferencia"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="item in salarios.data"
                        :key="item.id"
                        class="hover:bg-muted/40 cursor-pointer transition-colors"
                        title="Haz clic para ver el desglose de cálculo"
                        @click="viewDetail(item)"
                    >
                        <!-- 1. Periodo -->
                        <TableCell class="text-center font-mono font-medium">
                            {{ item.periodo }}
                        </TableCell>

                        <!-- 2. Año -->
                        <TableCell class="text-center font-mono font-bold text-foreground">
                            {{ item.anio }}
                        </TableCell>

                        <!-- 3. Nombre -->
                        <TableCell class="font-semibold text-foreground whitespace-nowrap">
                            {{ item.empleado?.nombre || '-' }}
                        </TableCell>

                        <!-- 4. Numero empleado -->
                        <TableCell class="font-mono text-center font-medium">
                            {{ item.empleado?.numero_empleado || '-' }}
                        </TableCell>

                        <!-- 5. Numero de seguridad social -->
                        <TableCell class="font-mono text-center text-muted-foreground">
                            {{ item.empleado?.numero_seguro_social || '-' }}
                        </TableCell>

                        <!-- 6. Fecha inicio -->
                        <TableCell class="text-center font-mono whitespace-nowrap text-muted-foreground">
                            {{ item.empleado?.fecha_inicio_relacion_laboral ? new Date(item.empleado.fecha_inicio_relacion_laboral).toLocaleDateString('es-MX', { timeZone: 'UTC' }) : '-' }}
                        </TableCell>

                        <!-- 7. Antigüedad Semanas -->
                        <TableCell class="text-center font-mono text-muted-foreground">
                            {{ item.empleado?.antiguedad_semanas ?? '-' }}
                        </TableCell>

                        <!-- 8. Antigüedad Años -->
                        <TableCell class="text-center font-mono font-semibold text-foreground">
                            {{ item.empleado?.antiguedad_anios ?? 0 }}
                        </TableCell>

                        <!-- 9. Salario diario -->
                        <TableCell class="text-right font-mono font-semibold text-foreground">
                            {{ formatCurrency(item.salario_base) }}
                        </TableCell>

                        <!-- 10. Aguinaldo diario -->
                        <TableCell class="text-right font-mono text-foreground">
                            {{ formatCurrency(item.aguinaldo_diario) }}
                        </TableCell>

                        <!-- 11. Dias de vacaciones -->
                        <TableCell class="text-center font-mono font-semibold text-foreground">
                            {{ item.dias_vacaciones }}
                        </TableCell>

                        <!-- 12. Importe de vacaciones -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.importe_vacaciones) }}
                        </TableCell>

                        <!-- 13. Prima vacacional -->
                        <TableCell class="text-right font-mono text-foreground">
                            {{ formatCurrency(item.prima_vacacional) }}
                        </TableCell>

                        <!-- 14. SDI -->
                        <TableCell class="text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {{ formatCurrency(item.salario_diario_integrado) }}
                        </TableCell>

                        <!-- 15. SDI CFDI -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ item.salario_diario_integrado_excel ? formatCurrency(item.salario_diario_integrado_excel) : '-' }}
                        </TableCell>

                        <!-- 16. Tope SDI -->
                        <TableCell class="text-right font-mono font-medium text-foreground">
                            <span :class="item.excede_limite ? 'text-destructive font-bold' : ''">
                                {{ formatCurrency(item.limite_salario_diario) }}
                            </span>
                        </TableCell>

                        <!-- 17. Cantidad Días -->
                        <TableCell class="text-center font-mono text-foreground">
                            {{ Number(item.cantidad_dias || 0).toFixed(2) }}
                        </TableCell>

                        <!-- 18. Importe Suma -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.importe_suma || 0) }}
                        </TableCell>

                        <!-- 19. SDI Variable -->
                        <TableCell class="text-right font-mono font-medium text-indigo-600 dark:text-indigo-400">
                            {{ formatCurrency(item.sdi_variable || 0) }}
                        </TableCell>

                        <!-- 20. SDI Total -->
                        <TableCell class="text-right font-mono font-bold text-foreground">
                            {{ formatCurrency(item.sdi_total || 0) }}
                        </TableCell>

                        <!-- 21. SDI Auditado -->
                        <TableCell class="text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {{ formatCurrency(item.sdi_auditado || 0) }}
                        </TableCell>

                        <!-- 22. IMSS Cía -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.imss_cia || 0) }}
                        </TableCell>

                        <!-- 23. Diferencia -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.diferencia || 0) }}
                        </TableCell>
                    </TableRow>

                    <TableEmpty v-if="salarios.data.length === 0" :colspan="23">
                        <ReceiptText class="h-10 w-10 text-muted-foreground/40" />
                        <p class="font-medium text-foreground">
                            No se encontraron registros de salarios
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                hasActiveFilters
                                    ? 'No hay registros que coincidan con los filtros seleccionados.'
                                    : 'Importa archivos CFDI en el catálogo de empresas para registrar salarios de empleados.'
                            }}
                        </p>
                        <Button
                            v-if="hasActiveFilters"
                            variant="outline"
                            size="sm"
                            class="mt-2 gap-1.5"
                            @click="resetFilters"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                            Restablecer filtros
                        </Button>
                    </TableEmpty>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <DataTablePagination
                :pagination="salarios"
                :per-page="filters.per_page"
                @change-per-page="handlePerPageChange"
            />
        </div>

        <!-- Audit Detail Modal -->
        <Dialog :open="detailModalOpen" @update:open="detailModalOpen = $event">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ReceiptText class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        Auditoría y Desglose de Salario
                    </DialogTitle>
                    <DialogDescription>
                        Cálculo desglosado de percepciones, antigüedad y límite de 25 UMAs.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedSalario" class="space-y-4 py-2 text-sm">
                    <!-- Employee header card -->
                    <div class="rounded-lg border bg-muted/40 p-3 space-y-1">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-foreground">
                                {{ selectedSalario.empleado?.nombre }}
                            </h3>
                            <Badge variant="outline" class="font-mono text-xs uppercase">
                                {{ selectedSalario.empleado?.rfc_receptor }}
                            </Badge>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-muted-foreground pt-1">
                            <div>
                                <span>No. Empleado: </span>
                                <strong class="text-foreground">{{ selectedSalario.empleado?.numero_empleado }}</strong>
                            </div>
                            <div>
                                <span>Empresa: </span>
                                <strong class="text-foreground">{{ selectedSalario.empresa?.nombre }}</strong>
                            </div>
                            <div>
                                <span>NSS: </span>
                                <strong class="text-foreground">{{ selectedSalario.empleado?.numero_seguro_social || '-' }}</strong>
                            </div>
                            <div>
                                <span>CURP: </span>
                                <strong class="text-foreground">{{ selectedSalario.empleado?.curp || '-' }}</strong>
                            </div>
                            <div>
                                <span>Antigüedad: </span>
                                <strong class="text-foreground">
                                    {{ selectedSalario.empleado?.antiguedad_semanas }} sem. ({{ selectedSalario.empleado?.antiguedad_anios }} años)
                                </strong>
                            </div>
                            <div>
                                <span>Categoría: </span>
                                <strong class="text-foreground">{{ selectedSalario.categoria?.nombre || 'Operativo' }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown table -->
                    <div class="rounded-lg border divide-y">
                        <div class="flex justify-between items-center p-2.5">
                            <span class="text-muted-foreground">Periodo y Año:</span>
                            <span class="font-semibold text-foreground">Periodo {{ selectedSalario.periodo }} / {{ selectedSalario.anio }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <span class="text-muted-foreground">Tipo de Nómina:</span>
                            <Badge variant="outline" class="text-xs">{{ selectedSalario.tipo_nomina }}</Badge>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">Salario Base (Cuota diaria):</span>
                                <p class="text-[11px] text-muted-foreground">Tomado directamente del CFDI</p>
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ formatCurrency(selectedSalario.salario_base) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">Aguinaldo Diario:</span>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ formatCurrency(selectedSalario.salario_base) }} × {{ selectedSalario.empresa?.dias_aguinaldo ?? 15 }} días ÷ 365
                                </p>
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ formatCurrency(selectedSalario.aguinaldo_diario) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">Días e Importe de Vacaciones:</span>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ selectedSalario.dias_vacaciones }} días × {{ formatCurrency(selectedSalario.salario_base) }}
                                </p>
                            </div>
                            <span class="font-mono font-semibold text-foreground">
                                {{ formatCurrency(selectedSalario.importe_vacaciones) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">Prima Vacacional Diaria:</span>
                                <p class="text-[11px] text-muted-foreground">
                                    ({{ formatCurrency(selectedSalario.salario_base) }} × {{ selectedSalario.dias_vacaciones }} d × {{ selectedSalario.empresa?.prima_vacacional ?? 25 }}%) ÷ 365
                                </p>
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ formatCurrency(selectedSalario.prima_vacacional) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5 bg-emerald-500/5">
                            <div>
                                <span class="font-bold text-emerald-700 dark:text-emerald-400">Salario Diario Integrado (SDI):</span>
                                <p class="text-[11px] text-muted-foreground">
                                    Salario Base + Aguinaldo Diario + Prima Vacacional Diaria
                                </p>
                            </div>
                            <span class="font-mono font-bold text-base text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(selectedSalario.salario_diario_integrado) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">Límite Salario Diario (25 UMAS):</span>
                                <p class="text-[11px] text-muted-foreground">
                                    UMA {{ selectedSalario.anio_uma }} ({{ formatCurrency(selectedSalario.uma_utilizada) }}) × 25
                                </p>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="font-mono font-bold text-foreground">
                                    {{ formatCurrency(selectedSalario.limite_salario_diario) }}
                                </span>
                                <Badge
                                    v-if="selectedSalario.excede_limite"
                                    variant="destructive"
                                    class="text-[10px] mt-0.5"
                                >
                                    Excede tope legal
                                </Badge>
                                <span v-else class="text-[11px] text-emerald-600 dark:text-emerald-400">
                                    Dentro del tope legal
                                </span>
                            </div>
                        </div>

                        <!-- Variables & Audit Breakdown -->
                        <div class="bg-indigo-500/5 p-2.5 border-t">
                            <p class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                                Variables Bimestrales y SDI Auditado
                            </p>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">Cantidad Días Acumulados:</span>
                                <p class="text-[11px] text-muted-foreground">Suma de días calendario del bimestre</p>
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ Number(selectedSalario.cantidad_dias || 0).toFixed(2) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">Importe Variable Acumulado:</span>
                                <p class="text-[11px] text-muted-foreground">Suma de partes variables del bimestre</p>
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ formatCurrency(selectedSalario.importe_suma || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">SDI Variable:</span>
                                <p class="text-[11px] text-muted-foreground">Importe Suma ÷ Cantidad Días</p>
                            </div>
                            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ formatCurrency(selectedSalario.sdi_variable || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-medium text-foreground">SDI Total:</span>
                                <p class="text-[11px] text-muted-foreground">SDI Calculado + SDI Variable</p>
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ formatCurrency(selectedSalario.sdi_total || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5 bg-emerald-500/10">
                            <div>
                                <span class="font-bold text-emerald-700 dark:text-emerald-400">SDI Auditado:</span>
                                <p class="text-[11px] text-muted-foreground">SDI Total topado a 25 UMAS</p>
                            </div>
                            <span class="font-mono font-bold text-base text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(selectedSalario.sdi_auditado || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <span class="font-medium text-foreground">IMSS Cía:</span>
                            <span class="font-mono text-muted-foreground">
                                {{ formatCurrency(selectedSalario.imss_cia || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <span class="font-medium text-foreground">Diferencia:</span>
                            <span class="font-mono text-muted-foreground">
                                {{ formatCurrency(selectedSalario.diferencia || 0) }}
                            </span>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
