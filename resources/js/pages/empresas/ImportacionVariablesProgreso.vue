<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Coins,
    FileSpreadsheet,
    ListCheck,
    RotateCw,
    ShieldAlert,
    Users,
    XCircle,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Empresa } from '@/types';

interface ImportacionVariablesState {
    id: number;
    empresa_id: number;
    anio: number;
    archivo_nombre: string;
    estado: 'pendiente' | 'procesando' | 'completado' | 'fallido';
    total_filas: number;
    filas_procesadas: number;
    filas_con_error: number;
    empleados_actualizados: number;
    salarios_actualizados: number;
    empleados_no_encontrados?: string[];
    porcentaje: number;
    errores?: Array<{ fila: number; mensaje: string }>;
    ultimo_error?: string | null;
    iniciado_a?: string | null;
    finalizado_a?: string | null;
    created_at?: string;
}

interface SalarioMuestra {
    id: number;
    periodo: string;
    tipo_nomina?: string;
    salario_base: string | number;
    cantidad_dias: string | number;
    importe_suma: string | number;
    sdi_variable: string | number;
    salario_diario_integrado: string | number;
    sdi_total: string | number;
    sdi_auditado: string | number;
    limite_salario_diario: string | number;
    excede_limite: boolean;
    empleado?: {
        nombre: string;
        rfc_receptor: string;
        numero_empleado: string;
    };
    categoria?: {
        nombre: string;
    };
}

interface Props {
    empresa: Empresa;
    importacion: ImportacionVariablesState;
    salariosMuestra: SalarioMuestra[];
}

const props = defineProps<Props>();

const importData = ref<ImportacionVariablesState>({ ...props.importacion });
const salariosList = ref<SalarioMuestra[]>([...props.salariosMuestra]);
const isRetrying = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;

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
        const response = await fetch(`/empresas/${props.empresa.id}/importar-variables/${importData.value.id}/estado`, {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const data = await response.json();
            importData.value = { ...importData.value, ...data };

            if (data.estado === 'completado') {
                stopPolling();
                toast.success('¡Importación de variables completada exitosamente!');
                router.reload({ only: ['salariosMuestra'] });
            } else if (data.estado === 'fallido') {
                stopPolling();
                toast.error('La importación de variables ha finalizado con errores.');
            }
        }
    } catch {
        // Silently continue polling
    }
};

const startPolling = () => {
    if (['pendiente', 'procesando'].includes(importData.value.estado)) {
        pollTimer = setInterval(pollStatus, 1500);
    }
};

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const handleRetry = () => {
    isRetrying.value = true;
    router.post(
        `/empresas/${props.empresa.id}/importar-variables/${importData.value.id}/reintentar`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isRetrying.value = false;
                importData.value.estado = 'procesando';
                toast.success('Reanudando procesamiento...');
                startPolling();
            },
            onError: () => {
                isRetrying.value = false;
                toast.error('No se pudo reanudar el trabajo.');
            },
        },
    );
};

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});

const formatCurrency = (val: string | number | undefined | null) => {
    const num = Number(val);
    if (isNaN(num)) return '$0.00';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(num);
};

const formatNumber = (val: string | number | undefined | null) => {
    const num = Number(val);
    if (isNaN(num)) return '0.00';
    return new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head :title="`Progreso de Importación de Variables - ${importData.archivo_nombre}`" />

        <!-- Navigation & Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button variant="outline" size="icon" as-child class="h-9 w-9">
                    <Link :href="`/empresas/${empresa.id}/importar-variables`">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Coins class="h-4 w-4" />
                        </span>
                        <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Progreso de Importación de Variables #{{ importData.id }}
                        </h1>
                    </div>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        {{ empresa.nombre }} • Ejercicio Fiscal: {{ importData.anio }} • Archivo: <span class="font-mono font-medium text-foreground">{{ importData.archivo_nombre }}</span>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="importData.estado === 'fallido'"
                    variant="default"
                    size="sm"
                    :disabled="isRetrying"
                    class="gap-1.5"
                    @click="handleRetry"
                >
                    <RotateCw :class="['h-4 w-4', isRetrying ? 'animate-spin' : '']" />
                    <span>Retomar / Reintentar</span>
                </Button>
                <Button
                    v-if="importData.estado === 'completado'"
                    variant="default"
                    size="sm"
                    as-child
                    class="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                >
                    <Link :href="`/salarios?empresa_id=${empresa.id}&anio=${importData.anio}`">
                        <ListCheck class="h-4 w-4" />
                        <span>Ver Salarios Actualizados</span>
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/empresas/${empresa.id}/importar-variables`">
                        Subir otro archivo
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/empresas">
                        Catálogo Empresas
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Progress Overview Card -->
        <Card class="border-2 shadow-sm">
            <CardHeader class="pb-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="text-lg">Estado de la Carga de Variables</CardTitle>
                        <CardDescription>
                            {{
                                importData.estado === 'completado'
                                    ? 'Se han procesado las variables, agrupado por empleado y actualizado los salarios calculados.'
                                    : importData.estado === 'procesando'
                                    ? 'El servidor está procesando las filas del archivo de variables y calculando el SDI Variable...'
                                    : importData.estado === 'pendiente'
                                    ? 'Esperando turno en la cola de trabajo...'
                                    : 'El procesamiento se detuvo debido a un error.'
                            }}
                        </CardDescription>
                    </div>

                    <!-- Status badge -->
                    <div>
                        <Badge
                            v-if="importData.estado === 'completado'"
                            variant="secondary"
                            class="gap-1.5 px-3 py-1 text-sm border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            Completado
                        </Badge>
                        <Badge
                            v-else-if="importData.estado === 'procesando'"
                            variant="outline"
                            class="gap-1.5 px-3 py-1 text-sm border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400"
                        >
                            <RotateCw class="h-4 w-4 animate-spin" />
                            Procesando ({{ progressPercent }}%)
                        </Badge>
                        <Badge
                            v-else-if="importData.estado === 'pendiente'"
                            variant="outline"
                            class="gap-1.5 px-3 py-1 text-sm text-muted-foreground"
                        >
                            <Clock class="h-4 w-4" />
                            En Cola
                        </Badge>
                        <Badge
                            v-else
                            variant="destructive"
                            class="gap-1.5 px-3 py-1 text-sm"
                        >
                            <XCircle class="h-4 w-4" />
                            Fallido
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Visual Progress Bar -->
                <div class="space-y-2">
                    <div class="flex justify-between text-xs font-semibold">
                        <span class="text-foreground">Avance general</span>
                        <span class="font-mono text-primary">{{ progressPercent }}%</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            :class="[
                                'h-full transition-all duration-500 ease-out',
                                importData.estado === 'completado'
                                    ? 'bg-emerald-500'
                                    : importData.estado === 'fallido'
                                    ? 'bg-destructive'
                                    : 'bg-primary',
                            ]"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                </div>

                <!-- Metrics Grid -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border bg-card p-3">
                        <span class="text-xs text-muted-foreground">Filas Procesadas</span>
                        <p class="mt-1 font-mono text-xl font-bold text-foreground">
                            {{ (importData.filas_procesadas || 0).toLocaleString() }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-3">
                        <span class="text-xs text-muted-foreground">Total de Filas</span>
                        <p class="mt-1 font-mono text-xl font-bold text-foreground">
                            {{ importData.total_filas > 0 ? importData.total_filas.toLocaleString() : 'Calculando...' }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-3">
                        <span class="text-xs text-muted-foreground">Empleados Actualizados</span>
                        <p class="mt-1 font-mono text-xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ (importData.empleados_actualizados || 0).toLocaleString() }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-3">
                        <span class="text-xs text-muted-foreground">Salarios Calculados</span>
                        <p class="mt-1 font-mono text-xl font-bold text-primary">
                            {{ (importData.salarios_actualizados || 0).toLocaleString() }}
                        </p>
                    </div>
                </div>

                <!-- Employees not found warning -->
                <div
                    v-if="importData.empleados_no_encontrados && importData.empleados_no_encontrados.length > 0"
                    class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-4 text-xs text-amber-700 dark:text-amber-300"
                >
                    <div class="flex items-start gap-2">
                        <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <div>
                            <p class="font-semibold">
                                {{ importData.empleados_no_encontrados.length }} número(s) de empleado no están dados de alta en esta empresa:
                            </p>
                            <p class="mt-1 text-muted-foreground">
                                Conforme a las reglas del sistema, se asignó 0 a su SDI Variable o fueron ignorados:
                                <span class="font-mono font-medium text-foreground">
                                    {{ importData.empleados_no_encontrados.slice(0, 15).join(', ') }}
                                    <span v-if="importData.empleados_no_encontrados.length > 15">... ({{ importData.empleados_no_encontrados.length - 15 }} más)</span>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Last Error Alert (if any) -->
                <div
                    v-if="importData.ultimo_error"
                    class="flex items-start gap-3 rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-xs text-destructive"
                >
                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p class="font-semibold">Último error reportado:</p>
                        <p class="mt-0.5 font-mono">{{ importData.ultimo_error }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Preview of updated salaries -->
        <Card>
            <CardHeader>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="text-base">Muestra de Salarios Actualizados con Variables</CardTitle>
                        <CardDescription>
                            Primeros registros calculados con Cantidad de Días, Importe Suma, SDI Variable y SDI Auditado.
                        </CardDescription>
                    </div>
                    <Button
                        v-if="importData.estado === 'completado'"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="`/salarios?empresa_id=${empresa.id}&anio=${importData.anio}`">
                            Ver todos en Salarios
                        </Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-16 text-center">Periodo</TableHead>
                            <TableHead>Empleado</TableHead>
                            <TableHead>Categoría</TableHead>
                            <TableHead class="text-right">Días Calendario</TableHead>
                            <TableHead class="text-right">Importe Acumulado</TableHead>
                            <TableHead class="text-right">SDI Fijo</TableHead>
                            <TableHead class="text-right text-emerald-600 font-semibold">SDI Variable</TableHead>
                            <TableHead class="text-right font-bold">SDI Total</TableHead>
                            <TableHead class="text-right font-bold text-primary">SDI Auditado</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="s in salariosList" :key="s.id">
                            <TableCell class="text-center font-mono font-bold text-xs">
                                P{{ s.periodo }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-medium text-foreground text-xs">{{ s.empleado?.nombre || 'Sin nombre' }}</span>
                                    <span class="text-[11px] font-mono text-muted-foreground">#{{ s.empleado?.numero_empleado }} • {{ s.empleado?.rfc_receptor }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" class="text-[11px]">
                                    {{ s.categoria?.nombre || 'General' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs">
                                {{ formatNumber(s.cantidad_dias) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs font-medium">
                                {{ formatCurrency(s.importe_suma) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs text-muted-foreground">
                                {{ formatCurrency(s.salario_diario_integrado) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(s.sdi_variable) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs font-bold">
                                {{ formatCurrency(s.sdi_total) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs font-bold text-primary">
                                {{ formatCurrency(s.sdi_auditado) }}
                            </TableCell>
                        </TableRow>

                        <TableEmpty v-if="salariosList.length === 0" :colspan="9">
                            <div class="flex flex-col items-center justify-center py-6 text-center text-muted-foreground">
                                <RotateCw v-if="['pendiente', 'procesando'].includes(importData.estado)" class="h-8 w-8 animate-spin text-primary/60 mb-2" />
                                <Coins v-else class="h-8 w-8 text-muted-foreground/40 mb-2" />
                                <p class="font-medium text-foreground text-sm">
                                    {{
                                        ['pendiente', 'procesando'].includes(importData.estado)
                                            ? 'Calculando y actualizando registros de salarios...'
                                            : 'No hay muestra de salarios disponible aún.'
                                    }}
                                </p>
                            </div>
                        </TableEmpty>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
