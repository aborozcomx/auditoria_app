<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    DollarSign,
    FileSpreadsheet,
    HelpCircle,
    Info,
    Receipt,
    RotateCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
    Users,
    X,
    XCircle,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    TableHeader,
    TableRow,
    TableHead,
} from '@/components/ui/table';
import DataTablePagination from '@/components/DataTablePagination.vue';
import type { Empresa } from '@/types';

interface ImportacionState {
    id: number;
    empresa_id: number;
    anio: number;
    archivo_nombre: string;
    estado: 'pendiente' | 'procesando' | 'completado' | 'fallido';
    total_filas: number;
    filas_procesadas: number;
    filas_con_error: number;
    cuotas_creadas: number;
    salarios_actualizados: number;
    total_auditoria: number | string;
    total_imss: number | string;
    total_diferencia: number | string;
    empleados_no_encontrados?: string[];
    porcentaje: number;
    errores?: Array<{ fila: number; mensaje: string }>;
    ultimo_error?: string | null;
    iniciado_a?: string | null;
    finalizado_a?: string | null;
    created_at?: string;
}

interface CuotaItem {
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
    empleado?: {
        id: number;
        nombre: string;
        rfc_receptor: string;
        numero_empleado: string;
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

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    empresa: Empresa;
    importacion: ImportacionState;
    cuotas: PaginatedData<CuotaItem>;
    periodosDisponibles: string[];
    filters: {
        search?: string;
        periodo?: string;
    };
}

const props = defineProps<Props>();

const importData = ref<ImportacionState>({ ...props.importacion });
const isRetrying = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;

// Search & filter
const searchInput = ref(props.filters.search || '');
const periodoFilter = ref(props.filters.periodo || '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        applyFilters();
    }, 350);
};

const onPeriodoChange = () => {
    applyFilters();
};

const clearSearch = () => {
    searchInput.value = '';
    applyFilters();
};

const applyFilters = () => {
    router.get(
        `/empresas/${props.empresa.id}/importar-cuotas-imss/${props.importacion.id}`,
        {
            search: searchInput.value || undefined,
            periodo: periodoFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

// Detail modal
const selectedCuota = ref<CuotaItem | null>(null);
const detailModalOpen = ref(false);

const openDetail = (item: CuotaItem) => {
    selectedCuota.value = item;
    detailModalOpen.value = true;
};

// Progress percentage
const progressPercent = computed(() => {
    if (importData.value.estado === 'completado') {
        return 100;
    }
    if (importData.value.total_filas <= 0) {
        return importData.value.filas_procesadas > 0 ? 50 : 5;
    }
    return Math.min(100, Math.round((importData.value.filas_procesadas / importData.value.total_filas) * 100));
});

const pollStatus = async () => {
    try {
        const response = await fetch(
            `/empresas/${props.empresa.id}/importar-cuotas-imss/${importData.value.id}/estado`,
            { headers: { Accept: 'application/json' } }
        );

        if (response.ok) {
            const data = await response.json();
            importData.value = { ...importData.value, ...data };

            if (data.estado === 'completado') {
                stopPolling();
                toast.success('¡Importación y cálculo de cuotas IMSS completado exitosamente!');
                router.reload();
            } else if (data.estado === 'fallido') {
                stopPolling();
                toast.error('La importación de cuotas IMSS ha finalizado con errores.');
            }
        }
    } catch {
        // Silently continue polling
    }
};

const startPolling = () => {
    if (importData.value.estado === 'completado' || importData.value.estado === 'fallido') {
        return;
    }
    pollTimer = setInterval(pollStatus, 1500);
};

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const reintentar = () => {
    isRetrying.value = true;
    router.post(
        `/empresas/${props.empresa.id}/importar-cuotas-imss/${importData.value.id}/reintentar`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRetrying.value = false;
                importData.value.estado = 'pendiente';
                startPolling();
            },
        }
    );
};

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
    if (searchTimer) clearTimeout(searchTimer);
});

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

const getSdiRegistroSalario = (item: CuotaItem | null | undefined): number | string => {
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
        <Head title="Visualizador de Cuotas IMSS" />

        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Receipt class="h-5 w-5" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Visualizador de Importación y Cuotas IMSS
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Empresa: <strong class="text-foreground">{{ empresa.nombre }}</strong> &bull; Año Fiscal: <strong class="text-foreground">{{ importacion.anio }}</strong> &bull; Archivo: <span class="font-mono text-foreground">{{ importacion.archivo_nombre }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <Button variant="outline" as-child class="gap-2">
                    <Link :href="`/empresas/${empresa.id}/importar-cuotas-imss`">
                        <ArrowLeft class="h-4 w-4" />
                        Historial de Cuotas
                    </Link>
                </Button>
                <Button variant="secondary" as-child class="gap-2">
                    <Link href="/salarios">
                        <DollarSign class="h-4 w-4" />
                        Ver Catálogo de Salarios
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Progress Card -->
        <Card class="border-border bg-card shadow-2xs">
            <CardHeader class="pb-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <CardTitle class="text-base font-semibold">
                            Estado del Proceso de Importación
                        </CardTitle>
                        <Badge
                            :variant="
                                importData.estado === 'completado'
                                    ? 'default'
                                    : importData.estado === 'fallido'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                            class="capitalize"
                        >
                            <RotateCw
                                v-if="importData.estado === 'procesando' || importData.estado === 'pendiente'"
                                class="mr-1 h-3 w-3 animate-spin"
                            />
                            <CheckCircle2
                                v-else-if="importData.estado === 'completado'"
                                class="mr-1 h-3 w-3"
                            />
                            <XCircle
                                v-else
                                class="mr-1 h-3 w-3"
                            />
                            {{ importData.estado }}
                        </Badge>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <Clock class="h-3.5 w-3.5" />
                        <span v-if="importData.iniciado_a">
                            Iniciado: {{ new Date(importData.iniciado_a).toLocaleTimeString('es-MX') }}
                        </span>
                        <span v-if="importData.finalizado_a">
                            &bull; Finalizado: {{ new Date(importData.finalizado_a).toLocaleTimeString('es-MX') }}
                        </span>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Progress bar -->
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs font-semibold">
                        <span class="text-muted-foreground">Progreso de lectura y cálculo</span>
                        <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ progressPercent }}%</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all duration-500 ease-out"
                            :class="[
                                importData.estado === 'fallido'
                                    ? 'bg-destructive'
                                    : 'bg-emerald-600 dark:bg-emerald-500',
                                importData.estado === 'procesando' ? 'animate-pulse' : '',
                            ]"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                </div>

                <!-- Live Failure Message -->
                <div
                    v-if="importData.estado === 'fallido' && importData.ultimo_error"
                    class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive flex items-start gap-2"
                >
                    <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <p class="font-semibold">Error en el procesamiento:</p>
                        <p class="mt-0.5 font-mono">{{ importData.ultimo_error }}</p>
                    </div>
                    <Button
                        size="sm"
                        variant="destructive"
                        class="h-7 gap-1 text-xs"
                        :disabled="isRetrying"
                        @click="reintentar"
                    >
                        <RotateCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRetrying }" />
                        Reintentar
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Total Filas -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Filas Leídas</span>
                    <FileSpreadsheet class="h-4 w-4 text-primary" />
                </div>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ Number(importData.filas_procesadas || 0).toLocaleString('es-MX') }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    de {{ Number(importData.total_filas || 0).toLocaleString('es-MX') }} filas en archivo
                </p>
            </div>

            <!-- Salarios Actualizados -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Salarios Actualizados</span>
                    <TrendingUp class="h-4 w-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ Number(importData.salarios_actualizados || 0).toLocaleString('es-MX') }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    SDI Cotizado e IMSS Cía enlazados
                </p>
            </div>

            <!-- Total Auditoría -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Total Auditoría</span>
                    <ShieldCheck class="h-4 w-4 text-indigo-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ formatCurrency(importData.total_auditoria) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Cuota obrero-patronal auditada
                </p>
            </div>

            <!-- Total Archivo IMSS -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Total IMSS Archivo</span>
                    <Receipt class="h-4 w-4 text-muted-foreground" />
                </div>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ formatCurrency(importData.total_imss) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Suma columna Cuota IMSS
                </p>
            </div>

            <!-- Diferencia Neta -->
            <div class="rounded-xl border bg-card p-4 shadow-2xs">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-medium uppercase tracking-wider">Diferencia Neta</span>
                    <DollarSign class="h-4 w-4" :class="Number(importData.total_diferencia) !== 0 ? 'text-amber-500' : 'text-emerald-500'" />
                </div>
                <p
                    class="mt-2 text-2xl font-bold"
                    :class="Number(importData.total_diferencia) !== 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                >
                    {{ formatCurrency(importData.total_diferencia) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Auditoría vs Liquidación IMSS
                </p>
            </div>
        </div>

        <!-- Warning: Employees Not Found -->
        <div
            v-if="importData.empleados_no_encontrados && importData.empleados_no_encontrados.length > 0"
            class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs"
        >
            <div class="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-300 text-sm">
                <AlertCircle class="h-4 w-4 shrink-0" />
                <span>{{ importData.empleados_no_encontrados.length }} empleados no fueron encontrados en la base de datos</span>
            </div>
            <p class="mt-1 text-muted-foreground">
                Las cuotas fueron calculadas y guardadas para efectos de auditoría global con el SDI del archivo, pero no actualizaron registros de salarios:
            </p>
            <div class="mt-2 flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                <Badge
                    v-for="emp in importData.empleados_no_encontrados"
                    :key="emp"
                    variant="outline"
                    class="bg-background/80 font-mono text-[10px]"
                >
                    {{ emp }}
                </Badge>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="rounded-xl border bg-card p-4 shadow-2xs">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative w-full max-w-md">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchInput"
                            type="text"
                            placeholder="Buscar por Nombre, NSS, Clave o Registro Patronal..."
                            class="pr-9 pl-9 text-sm"
                            @input="onSearchInput"
                        />
                        <button
                            v-if="searchInput"
                            type="button"
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="clearSearch"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="w-full sm:w-48">
                        <select
                            v-model="periodoFilter"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            @change="onPeriodoChange"
                        >
                            <option value="">Todos los periodos</option>
                            <option v-for="p in periodosDisponibles" :key="p" :value="p">
                                Periodo {{ p }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="text-xs text-muted-foreground">
                    Mostrando <strong class="text-foreground">{{ cuotas.data.length }}</strong> de <strong class="text-foreground">{{ cuotas.total }}</strong> registros
                </div>
            </div>
        </div>

        <!-- Records Visualizer Table -->
        <div class="overflow-x-auto rounded-xl border bg-card shadow-2xs">
            <Table class="min-w-max text-xs">
                <TableHeader>
                    <TableRow class="bg-muted/40">
                        <TableHead class="text-center font-semibold">Periodo</TableHead>
                        <TableHead class="text-center font-semibold">Año</TableHead>
                        <TableHead class="font-semibold">Empleado</TableHead>
                        <TableHead class="text-center font-semibold">NSS</TableHead>
                        <TableHead class="text-right font-semibold">SDI Cotizado</TableHead>
                        <TableHead class="text-center font-semibold">Días</TableHead>
                        <TableHead class="text-center font-semibold">Ausencias</TableHead>
                        <TableHead class="text-center font-semibold">Incapacidad</TableHead>
                        <TableHead class="text-center font-semibold">Días Totales</TableHead>
                        <TableHead class="text-center font-semibold">Diferencia Días</TableHead>
                        <TableHead class="text-right font-semibold">Cotización E y M</TableHead>
                        <TableHead class="text-right font-semibold">Cotización RT y GPS</TableHead>
                        <TableHead class="text-right font-semibold">Cotización I y V</TableHead>
                        <TableHead class="text-right font-semibold">Cuota Fija</TableHead>
                        <TableHead class="text-right font-semibold">SDMG</TableHead>
                        <TableHead class="text-right font-semibold">En Dinero</TableHead>
                        <TableHead class="text-right font-semibold">Invalidez y Vida</TableHead>
                        <TableHead class="text-right font-semibold">Pensionados</TableHead>
                        <TableHead class="text-right font-semibold">Prima de Riesgo</TableHead>
                        <TableHead class="text-right font-semibold">Guarderías</TableHead>
                        <TableHead class="text-right font-bold text-indigo-600 dark:text-indigo-400">Total Auditoría</TableHead>
                        <TableHead class="text-right font-bold text-foreground">Cuota IMSS</TableHead>
                        <TableHead class="text-right font-bold">Diferencia</TableHead>
                        <TableHead class="text-center font-semibold">Detalle</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="item in cuotas.data"
                        :key="item.id"
                        class="hover:bg-muted/40 cursor-pointer transition-colors"
                        @click="openDetail(item)"
                    >
                        <TableCell class="text-center font-mono font-bold text-foreground">
                            {{ item.periodo }}
                        </TableCell>
                        <TableCell class="text-center font-mono font-bold text-foreground">
                            {{ item.anio }}
                        </TableCell>
                        <TableCell class="font-semibold text-foreground whitespace-nowrap">
                            {{ item.nombre || item.empleado?.nombre || '-' }}
                        </TableCell>
                        <TableCell class="text-center font-mono text-xs">
                            {{ item.nss }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(getSdiRegistroSalario(item)) }}
                        </TableCell>
                        <TableCell class="text-center font-mono">
                            {{ item.dias }}
                        </TableCell>
                        <TableCell class="text-center font-mono text-muted-foreground">
                            {{ item.ausencias }}
                        </TableCell>
                        <TableCell class="text-center font-mono text-muted-foreground">
                            {{ item.incapacidades }}
                        </TableCell>
                        <TableCell class="text-center font-mono font-semibold text-foreground">
                            {{ item.total_dias }}
                        </TableCell>
                        <TableCell class="text-center font-mono text-amber-600 dark:text-amber-400 font-medium">
                            {{ item.dias - item.total_dias }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.base_price_em) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.base_price_rt) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.base_price_iv) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.fixed_price) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.sdmg) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.in_cash) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.disability_health) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.pensioners) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.risk_price) }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">
                            {{ formatCurrency(item.nurseries) }}
                        </TableCell>
                        <TableCell class="text-right font-mono font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                            {{ formatCurrency(item.total_audit) }}
                        </TableCell>
                        <TableCell class="text-right font-mono font-bold text-foreground whitespace-nowrap">
                            {{ formatCurrency(item.subtotal) }}
                        </TableCell>
                        <TableCell
                            class="text-right font-mono font-bold whitespace-nowrap"
                            :class="Number(item.difference) !== 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ formatCurrency(item.difference) }}
                        </TableCell>
                        <TableCell class="text-center" @click.stop>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 px-2 text-xs text-primary hover:bg-primary/10"
                                @click="openDetail(item)"
                            >
                                Ver
                            </Button>
                        </TableCell>
                    </TableRow>

                    <TableEmpty v-if="cuotas.data.length === 0" :colspan="24">
                        <Receipt class="h-8 w-8 text-muted-foreground/40 mx-auto" />
                        <p class="font-medium text-foreground mt-2">
                            {{ importData.estado === 'procesando' ? 'Calculando cuotas IMSS...' : 'No se encontraron registros de cuotas' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ importData.estado === 'procesando' ? 'El visualizador se actualizará automáticamente.' : 'Prueba cambiando los términos de búsqueda o filtros.' }}
                        </p>
                    </TableEmpty>
                </TableBody>
            </Table>

            <DataTablePagination
                v-if="cuotas.data.length > 0"
                :pagination="cuotas"
                :per-page="cuotas.per_page"
            />
        </div>

        <!-- Detail Breakdown Dialog -->
        <Dialog :open="detailModalOpen" @update:open="detailModalOpen = $event">
            <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-semibold">
                        <Receipt class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        Desglose y Auditoría de Cuota IMSS
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Cálculo detallado de las 7 ramas de aseguramiento obrero-patronal conforme a la LSS.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedCuota" class="space-y-4 py-2 text-xs">
                    <!-- Employee summary -->
                    <div class="rounded-lg border bg-muted/40 p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-foreground">
                                {{ selectedCuota.nombre || selectedCuota.empleado?.nombre }}
                            </span>
                            <Badge variant="outline" class="font-mono text-xs">
                                NSS: {{ selectedCuota.nss }}
                            </Badge>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-muted-foreground pt-1">
                            <div>Periodo: <strong class="text-foreground">Periodo {{ selectedCuota.periodo }} / {{ selectedCuota.anio }}</strong></div>
                            <div>Registro Patronal: <strong class="text-foreground font-mono">{{ selectedCuota.registro_patronal || '-' }}</strong></div>
                            <div>Días Calendario: <strong class="text-foreground font-mono">{{ selectedCuota.dias }}</strong></div>
                            <div>Ausencias / Incapacidades: <strong class="text-foreground font-mono">{{ selectedCuota.ausencias }} aus. / {{ selectedCuota.incapacidades }} inc.</strong></div>
                            <div>Días Efectivos (Total Días): <strong class="text-foreground font-mono">{{ selectedCuota.total_dias }}</strong></div>
                            <div>Días Incapacidad (Días - Inc): <strong class="text-foreground font-mono">{{ selectedCuota.dias_incapacidad }}</strong></div>
                        </div>
                    </div>

                    <!-- Base and UMA reference -->
                    <div class="rounded-lg border divide-y">
                        <div class="flex justify-between items-center p-2.5">
                            <div>
                                <span class="font-semibold text-foreground">UMA Aplicada:</span>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ selectedCuota.periodo === '1' ? `Periodo 1 aplica UMA del año anterior (${selectedCuota.anio_uma})` : `UMA del año en curso (${selectedCuota.anio_uma})` }}
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
