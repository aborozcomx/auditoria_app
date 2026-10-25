<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Eye,
    FileSpreadsheet,
    Filter,
    Receipt,
    ReceiptText,
    RotateCcw,
    Search,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
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
import type { Empresa, PaginatedData } from '@/types';

interface CuotaEmpleado {
    id: number;
    anio: number;
    periodo: string;
    nss: string;
    nombre: string | null;
    clave: string | null;
    registro_patronal: string | null;
    dias: number;
    ausencias: number;
    incapacidades: number;
    total_dias: number;
    dias_incapacidad: number;
    sdi_cotizado: string | number;
    sdi_archivo?: string | number;
    salario_base: string | number;
    salario_incapacidad: string | number;
    salario_uma_tope: string | number;
    base_price_em: string | number;
    base_price_rt: string | number;
    base_price_iv: string | number;
    salario_uma: string | number;
    fixed_price: string | number;
    sdmg: string | number;
    in_cash: string | number;
    disability_health: string | number;
    pensioners: string | number;
    risk: string | number;
    risk_price: string | number;
    nurseries: string | number;
    total_audit: string | number;
    subtotal: string | number;
    difference: string | number;
    uma_utilizada: string | number;
    anio_uma: number;
    empresa?: {
        id: number;
        nombre: string;
        rfc: string;
        prima_vacacional: string | number;
    } | null;
    empleado?: {
        id: number;
        empresa_id: number;
        nombre: string;
        rfc_receptor: string;
        numero_empleado: string;
        numero_seguro_social: string | null;
        curp: string | null;
    } | null;
    salario?: {
        id: number;
        sdi_total?: string | number;
        sdi_auditado: string | number;
        salario_diario_integrado?: string | number;
        imss_cia: string | number;
        diferencia: string | number;
    } | null;
}

interface CuotasTotales {
    total_registros: number;
    total_auditoria: number;
    total_imss: number;
    total_diferencia: number;
    con_diferencias: number;
}

interface Props {
    cuotas: PaginatedData<CuotaEmpleado>;
    empresas: Array<{ id: number; nombre: string; rfc: string }>;
    anios: number[];
    periodos: string[];
    totales: CuotasTotales;
    filters: {
        search?: string;
        empresa_id?: number | null;
        anio?: number | null;
        periodo?: string | null;
        solo_diferencias?: boolean;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Cuotas IMSS de Empleados', href: '/cuotas-imss' },
        ],
    },
});

const props = defineProps<Props>();

// Filter states
const search = ref(props.filters.search || '');
const empresaId = ref(props.filters.empresa_id || '');
const anio = ref(props.filters.anio || '');
const periodo = ref(props.filters.periodo || '');
const soloDiferencias = ref(!!props.filters.solo_diferencias);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const hasActiveFilters = computed(
    () =>
        !!search.value ||
        !!empresaId.value ||
        !!anio.value ||
        !!periodo.value ||
        soloDiferencias.value
);

const fetchFiltered = (extraParams = {}) => {
    const query: Record<string, any> = {
        search: search.value || undefined,
        empresa_id: empresaId.value || undefined,
        anio: anio.value || undefined,
        periodo: periodo.value || undefined,
        solo_diferencias: soloDiferencias.value ? true : undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        ...extraParams,
    };

    Object.keys(query).forEach((key) => {
        if (query[key] === undefined || query[key] === '') {
            delete query[key];
        }
    });

    router.get('/cuotas-imss', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const handleSearchInput = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchFiltered({ search: search.value || undefined });
    }, 300);
};

const handleEmpresaChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    empresaId.value = val;
    fetchFiltered({ empresa_id: val ? Number(val) : undefined });
};

const handleAnioChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    anio.value = val;
    fetchFiltered({ anio: val ? Number(val) : undefined });
};

const handlePeriodoChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    periodo.value = val;
    fetchFiltered({ periodo: val || undefined });
};

const toggleDiferencias = () => {
    soloDiferencias.value = !soloDiferencias.value;
    fetchFiltered({ solo_diferencias: soloDiferencias.value ? true : undefined });
};

const resetFilters = () => {
    search.value = '';
    empresaId.value = '';
    anio.value = '';
    periodo.value = '';
    soloDiferencias.value = false;
    fetchFiltered({
        search: undefined,
        empresa_id: undefined,
        anio: undefined,
        periodo: undefined,
        solo_diferencias: undefined,
    });
};

const handleSort = (field: string) => {
    const currentSort = props.filters.sort;
    const currentDir = props.filters.direction || 'asc';
    let nextDir = 'asc';

    if (currentSort === field) {
        nextDir = currentDir === 'asc' ? 'desc' : 'asc';
    }

    fetchFiltered({ sort: field, direction: nextDir });
};

const handlePerPageChange = (val: number) => {
    fetchFiltered({ per_page: val });
};

// Detail modal
const selectedCuota = ref<CuotaEmpleado | null>(null);
const detailOpen = ref(false);

const openDetailModal = (item: CuotaEmpleado) => {
    selectedCuota.value = item;
    detailOpen.value = true;
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

const getSdiRegistroSalario = (item: CuotaEmpleado | null | undefined): number | string => {
    if (!item) return 0;
    if (item.salario) {
        const total = Number(item.salario.sdi_total ?? 0);
        if (total > 0) return total;
        const aud = Number(item.salario.sdi_auditado ?? 0);
        if (aud > 0) return aud;
        const sdi = Number(item.salario.salario_diario_integrado ?? 0);
        if (sdi > 0) return sdi;
    }
    const cotizado = Number(item.sdi_cotizado ?? 0);
    if (cotizado > 0) return cotizado;
    return item.salario_base ?? 0;
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Cuotas IMSS de Empleados" />

        <!-- Header Section -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Receipt class="h-5 w-5" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Cuotas IMSS de Empleados
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Auditoría de cuotas obrero-patronales, cálculo de las 7 ramas de aseguramiento y diferencias contra liquidación IMSS.
                </p>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <Button variant="outline" as-child class="gap-2">
                    <Link href="/empresas">
                        <Building2 class="h-4 w-4" />
                        Ver Empresas
                    </Link>
                </Button>
                <Button variant="secondary" as-child class="gap-2">
                    <Link href="/salarios">
                        <DollarSign class="h-4 w-4" />
                        Ver Salarios
                    </Link>
                </Button>
            </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- 1. Total Registros -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Total Registros</span>
                    <Users class="h-4 w-4 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ totales.total_registros.toLocaleString('es-MX') }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Cuotas calculadas y archivadas
                </p>
            </div>

            <!-- 2. Total Auditoría -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Total Auditoría</span>
                    <ShieldCheck class="h-4 w-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatCurrency(totales.total_auditoria) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Cuota obrero-patronal auditada
                </p>
            </div>

            <!-- 3. Total IMSS Declarado -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Total IMSS Declarado</span>
                    <ReceiptText class="h-4 w-4 text-indigo-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ formatCurrency(totales.total_imss) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Monto reportado en liquidaciones
                </p>
            </div>

            <!-- 4. Diferencias -->
            <div
                class="rounded-xl border bg-card p-4 shadow-2xs transition-colors"
                :class="totales.con_diferencias > 0 ? 'border-amber-500/30 bg-amber-500/5' : ''"
            >
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Diferencia Neta</span>
                    <DollarSign
                        class="h-4 w-4"
                        :class="totales.con_diferencias > 0 ? 'text-amber-500' : 'text-muted-foreground'"
                    />
                </div>
                <p
                    class="mt-2 text-2xl font-bold"
                    :class="totales.con_diferencias > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'"
                >
                    {{ formatCurrency(totales.total_diferencia) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ totales.con_diferencias }} casos con discrepancia
                </p>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="rounded-xl border bg-card p-4 shadow-2xs space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
                <!-- Search Input -->
                <div class="relative lg:col-span-4">
                    <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por empleado, RFC, núm. o NSS..."
                        class="pr-9 pl-9 text-sm"
                        @input="handleSearchInput"
                    />
                    <button
                        v-if="search"
                        type="button"
                        class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="() => { search = ''; fetchFiltered({ search: undefined }); }"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Empresa Select -->
                <div class="lg:col-span-3">
                    <select
                        :value="empresaId"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        @change="handleEmpresaChange"
                    >
                        <option value="">Todas las empresas</option>
                        <option v-for="emp in empresas" :key="emp.id" :value="emp.id">
                            {{ emp.nombre }} ({{ emp.rfc }})
                        </option>
                    </select>
                </div>

                <!-- Año Select -->
                <div class="lg:col-span-2">
                    <select
                        :value="anio"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        @change="handleAnioChange"
                    >
                        <option value="">Todos los años</option>
                        <option v-for="yr in anios" :key="yr" :value="yr">
                            Año {{ yr }}
                        </option>
                    </select>
                </div>

                <!-- Periodo Select -->
                <div class="lg:col-span-2">
                    <select
                        :value="periodo"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        @change="handlePeriodoChange"
                    >
                        <option value="">Todos los periodos</option>
                        <option v-for="per in periodos" :key="per" :value="per">
                            Periodo {{ per }}
                        </option>
                    </select>
                </div>

                <!-- Clear Filters Button -->
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

            <!-- Toolbar Subrow -->
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t text-xs text-muted-foreground">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-colors"
                        :class="
                            soloDiferencias
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30'
                                : 'bg-muted hover:bg-muted/80 text-foreground'
                        "
                        @click="toggleDiferencias"
                    >
                        <DollarSign class="h-3.5 w-3.5" />
                        Solo con diferencias
                    </button>

                    <span v-if="hasActiveFilters" class="text-xs text-primary font-medium">
                        Filtros activos
                    </span>
                </div>

                <div>
                    Mostrando <strong class="text-foreground">{{ cuotas.data.length }}</strong> de <strong class="text-foreground">{{ cuotas.total }}</strong> registros
                </div>
            </div>
        </div>

        <!-- Table Container -->
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
                        <!-- 3. Empleado -->
                        <TableSortHeader
                            field="nombre"
                            label="Empleado"
                            align="left"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 4. NSS -->
                        <TableSortHeader
                            field="nss"
                            label="NSS"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 5. SDI Cotizado -->
                        <TableSortHeader
                            field="sdi_cotizado"
                            label="SDI Cotizado"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 6. Días -->
                        <TableSortHeader
                            field="dias"
                            label="Días"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 7. Ausencias -->
                        <TableSortHeader
                            field="ausencias"
                            label="Ausencias"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 8. Incapacidad -->
                        <TableSortHeader
                            field="incapacidades"
                            label="Incapacidad"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 9. Días Totales -->
                        <TableSortHeader
                            field="total_dias"
                            label="Días Totales"
                            align="center"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 10. Diferencia Días -->
                        <TableHead class="text-xs font-semibold tracking-wider uppercase whitespace-nowrap text-center">
                            Diferencia Días
                        </TableHead>
                        <!-- 11. Cotización E y M -->
                        <TableSortHeader
                            field="base_price_em"
                            label="Cotización E y M"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 12. Cotización RT y GPS -->
                        <TableSortHeader
                            field="base_price_rt"
                            label="Cotización RT y GPS"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 13. Cotización I y V -->
                        <TableSortHeader
                            field="base_price_iv"
                            label="Cotización I y V"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 14. Cuota Fija -->
                        <TableSortHeader
                            field="fixed_price"
                            label="Cuota Fija"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 15. SDMG -->
                        <TableSortHeader
                            field="sdmg"
                            label="SDMG"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 16. En Dinero -->
                        <TableSortHeader
                            field="in_cash"
                            label="En Dinero"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 17. Invalidez y Vida -->
                        <TableSortHeader
                            field="disability_health"
                            label="Invalidez y Vida"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 18. Pensionados -->
                        <TableSortHeader
                            field="pensioners"
                            label="Pensionados"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 19. Prima de Riesgo -->
                        <TableSortHeader
                            field="risk_price"
                            label="Prima de Riesgo"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 20. Guarderías -->
                        <TableSortHeader
                            field="nurseries"
                            label="Guarderías"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 21. Total Auditoría -->
                        <TableSortHeader
                            field="total_audit"
                            label="Total Auditoría"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 22. Cuota IMSS -->
                        <TableSortHeader
                            field="subtotal"
                            label="Cuota IMSS"
                            align="right"
                            :current-sort="filters.sort"
                            :current-direction="filters.direction"
                            @sort="handleSort"
                        />
                        <!-- 23. Diferencia -->
                        <TableSortHeader
                            field="difference"
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
                        v-for="item in cuotas.data"
                        :key="item.id"
                        class="hover:bg-muted/40 cursor-pointer transition-colors"
                        title="Haz clic para ver el desglose matemático de cálculo"
                        @click="openDetailModal(item)"
                    >
                        <!-- 1. Periodo -->
                        <TableCell class="text-center font-mono font-medium">
                            {{ item.periodo }}
                        </TableCell>
                        <!-- 2. Año -->
                        <TableCell class="text-center font-mono font-bold text-foreground">
                            {{ item.anio }}
                        </TableCell>
                        <!-- 3. Empleado -->
                        <TableCell class="font-semibold text-foreground whitespace-nowrap">
                            {{ item.nombre || item.empleado?.nombre || '-' }}
                        </TableCell>
                        <!-- 4. NSS -->
                        <TableCell class="text-center font-mono text-muted-foreground whitespace-nowrap">
                            {{ item.nss }}
                        </TableCell>
                        <!-- 5. SDI Cotizado -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(getSdiRegistroSalario(item)) }}
                        </TableCell>
                        <!-- 6. Días -->
                        <TableCell class="text-center font-mono">
                            {{ item.dias }}
                        </TableCell>
                        <!-- 7. Ausencias -->
                        <TableCell class="text-center font-mono text-muted-foreground">
                            {{ item.ausencias }}
                        </TableCell>
                        <!-- 8. Incapacidad -->
                        <TableCell class="text-center font-mono text-muted-foreground">
                            {{ item.incapacidades }}
                        </TableCell>
                        <!-- 9. Días Totales -->
                        <TableCell class="text-center font-mono font-semibold text-foreground">
                            {{ item.total_dias }}
                        </TableCell>
                        <!-- 10. Diferencia Días -->
                        <TableCell class="text-center font-mono text-amber-600 dark:text-amber-400 font-medium">
                            {{ item.dias - item.total_dias }}
                        </TableCell>
                        <!-- 11. Cotización E y M -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.base_price_em) }}
                        </TableCell>
                        <!-- 12. Cotización RT y GPS -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.base_price_rt) }}
                        </TableCell>
                        <!-- 13. Cotización I y V -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.base_price_iv) }}
                        </TableCell>
                        <!-- 14. Cuota Fija -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.fixed_price) }}
                        </TableCell>
                        <!-- 15. SDMG -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.sdmg) }}
                        </TableCell>
                        <!-- 16. En Dinero -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.in_cash) }}
                        </TableCell>
                        <!-- 17. Invalidez y Vida -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.disability_health) }}
                        </TableCell>
                        <!-- 18. Pensionados -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.pensioners) }}
                        </TableCell>
                        <!-- 19. Prima de Riesgo -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.risk_price) }}
                        </TableCell>
                        <!-- 20. Guarderías -->
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.nurseries) }}
                        </TableCell>
                        <!-- 21. Total Auditoría -->
                        <TableCell class="text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                            {{ formatCurrency(item.total_audit) }}
                        </TableCell>
                        <!-- 22. Cuota IMSS -->
                        <TableCell class="text-right font-mono font-bold text-foreground whitespace-nowrap">
                            {{ formatCurrency(item.subtotal) }}
                        </TableCell>
                        <!-- 23. Diferencia -->
                        <TableCell
                            class="text-right font-mono font-bold whitespace-nowrap"
                            :class="Number(item.difference) !== 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ formatCurrency(item.difference) }}
                        </TableCell>
                    </TableRow>

                    <TableEmpty v-if="cuotas.data.length === 0" :colspan="23">
                        <Receipt class="h-10 w-10 text-muted-foreground/40" />
                        <p class="font-medium text-foreground">
                            No se encontraron registros de cuotas IMSS
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                hasActiveFilters
                                    ? 'No hay registros que coincidan con los filtros seleccionados.'
                                    : 'Importa archivos de cuotas IMSS en el catálogo de empresas para registrar auditorías.'
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
                :pagination="cuotas"
                :per-page="filters.per_page"
                @change-per-page="handlePerPageChange"
            />
        </div>

        <!-- Detail Breakdown Dialog -->
        <Dialog :open="detailOpen" @update:open="detailOpen = $event">
            <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Receipt class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        Auditoría y Desglose de Cuota IMSS
                    </DialogTitle>
                    <DialogDescription>
                        Cálculo detallado de las 7 ramas de aseguramiento obrero-patronal conforme a la LSS.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedCuota" class="space-y-4 py-2 text-xs">
                    <!-- Employee summary card -->
                    <div class="rounded-lg border bg-muted/40 p-3 space-y-1">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-foreground text-sm">
                                {{ selectedCuota.nombre || selectedCuota.empleado?.nombre }}
                            </h3>
                            <Badge variant="outline" class="font-mono text-xs uppercase">
                                NSS: {{ selectedCuota.nss }}
                            </Badge>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-muted-foreground pt-1">
                            <div>Empresa: <strong class="text-foreground">{{ selectedCuota.empresa?.nombre }}</strong></div>
                            <div>Periodo / Año: <strong class="text-foreground">Periodo {{ selectedCuota.periodo }} / {{ selectedCuota.anio }}</strong></div>
                            <div>Registro Patronal: <strong class="text-foreground font-mono">{{ selectedCuota.registro_patronal || '-' }}</strong></div>
                            <div>Días Calendario: <strong class="text-foreground font-mono">{{ selectedCuota.dias }}</strong></div>
                            <div>Ausencias / Incapacidades: <strong class="text-foreground font-mono">{{ selectedCuota.ausencias }} aus. / {{ selectedCuota.incapacidades }} inc.</strong></div>
                            <div>Días Efectivos (Total Días): <strong class="text-foreground font-mono">{{ selectedCuota.total_dias }}</strong></div>
                        </div>
                    </div>

                    <!-- Base and UMA reference -->
                    <div class="rounded-lg border divide-y">
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-semibold text-foreground">UMA Aplicada:</span>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ selectedCuota.periodo === '1' ? `Periodo 1 toma UMA del año anterior (${selectedCuota.anio_uma})` : `UMA del año en curso (${selectedCuota.anio_uma})` }}
                                </p>
                            </div>
                            <span class="font-mono font-bold text-foreground">{{ formatCurrency(selectedCuota.uma_utilizada) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2.5 bg-muted/20">
                            <div>
                                <span class="font-semibold text-foreground">SDI del Archivo Importado:</span>
                                <p class="text-[11px] text-muted-foreground">Valor reportado en el archivo Excel que se importó (encabezado SDI)</p>
                            </div>
                            <span class="font-mono font-bold text-foreground">{{ formatCurrency(selectedCuota.sdi_archivo ?? selectedCuota.salario?.imss_cia) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-semibold text-foreground">SDI Registro de Salarios (Variables y Prestaciones):</span>
                                <p class="text-[11px] text-muted-foreground">Valor del registro de salarios con variables y prestaciones</p>
                            </div>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(getSdiRegistroSalario(selectedCuota)) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-semibold text-foreground">Base Salario (SDI Auditado para Cuotas):</span>
                                <p class="text-[11px] text-muted-foreground">Salario base de cálculo para las ramas de aseguramiento (topado a 25 UMA)</p>
                            </div>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(selectedCuota.salario_base) }}</span>
                        </div>
                    </div>

                    <!-- Insurance branches -->
                    <div class="rounded-lg border divide-y">
                        <div class="bg-muted/40 p-2 font-bold text-foreground text-[11px] uppercase tracking-wider">
                            Ramas de Aseguramiento Auditadas:
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <div>
                                <span>1. Cuota Fija (Enfermedades y Maternidad):</span>
                                <p class="text-[10px] text-muted-foreground font-mono">
                                    UMA ({{ formatCurrency(selectedCuota.uma_utilizada) }}) × {{ selectedCuota.dias_incapacidad }} d × 20.40%
                                </p>
                            </div>
                            <span class="font-mono font-semibold">{{ formatCurrency(selectedCuota.fixed_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <div>
                                <span>2. Excedente 3 UMA (SDMG):</span>
                                <p class="text-[10px] text-muted-foreground font-mono">
                                    {{ Number(selectedCuota.salario_base) > Number(selectedCuota.uma_utilizada) * 3 ? `(Base EM - 3 UMA × ${selectedCuota.total_dias} d) × 1.5%` : 'Base no excede 3 UMA ($0.00)' }}
                                </p>
                            </div>
                            <span class="font-mono font-semibold">{{ formatCurrency(selectedCuota.sdmg) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <div>
                                <span>3. Prestaciones en Dinero:</span>
                                <p class="text-[10px] text-muted-foreground font-mono">
                                    Base EM ({{ formatCurrency(selectedCuota.base_price_em) }}) × 0.95%
                                </p>
                            </div>
                            <span class="font-mono font-semibold">{{ formatCurrency(selectedCuota.in_cash) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <div>
                                <span>4. Invalidez y Vida (Topada a 25 UMA):</span>
                                <p class="text-[10px] text-muted-foreground font-mono">
                                    Base IV ({{ formatCurrency(selectedCuota.base_price_iv) }}) × 2.375%
                                </p>
                            </div>
                            <span class="font-mono font-semibold">{{ formatCurrency(selectedCuota.disability_health) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <div>
                                <span>5. Gastos Médicos Pensionados:</span>
                                <p class="text-[10px] text-muted-foreground font-mono">
                                    Base EM ({{ formatCurrency(selectedCuota.base_price_em) }}) × 1.425%
                                </p>
                            </div>
                            <span class="font-mono font-semibold">{{ formatCurrency(selectedCuota.pensioners) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <div>
                                <span>6. Riesgo de Trabajo:</span>
                                <p class="text-[10px] text-muted-foreground font-mono">
                                    Base RT ({{ formatCurrency(selectedCuota.base_price_rt) }}) × {{ (Number(selectedCuota.risk) * 100).toFixed(5) }}%
                                </p>
                            </div>
                            <span class="font-mono font-semibold">{{ formatCurrency(selectedCuota.risk_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <div>
                                <span>7. Guarderías y Prestaciones Sociales:</span>
                                <p class="text-[10px] text-muted-foreground font-mono">
                                    Base RT ({{ formatCurrency(selectedCuota.base_price_rt) }}) × 1.00%
                                </p>
                            </div>
                            <span class="font-mono font-semibold">{{ formatCurrency(selectedCuota.nurseries) }}</span>
                        </div>
                    </div>

                    <!-- Comparison summary -->
                    <div class="rounded-lg border bg-emerald-500/5 p-3 space-y-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-bold text-emerald-800 dark:text-emerald-300">Total Auditoría Calculado:</span>
                            <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400 text-base">
                                {{ formatCurrency(selectedCuota.total_audit) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-foreground">Cuota IMSS Declarada en Archivo:</span>
                            <span class="font-mono font-bold text-foreground">
                                {{ formatCurrency(selectedCuota.subtotal) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm pt-1 border-t">
                            <span class="font-bold text-foreground">Diferencia Neta (Audit. - IMSS):</span>
                            <span
                                class="font-mono font-bold text-base"
                                :class="Number(selectedCuota.difference) !== 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{ formatCurrency(selectedCuota.difference) }}
                            </span>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
