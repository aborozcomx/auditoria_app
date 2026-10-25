<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
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

interface ImportacionState {
    id: number;
    empresa_id: number;
    anio: number;
    archivo_nombre: string;
    estado: 'pendiente' | 'procesando' | 'completado' | 'fallido';
    total_filas: number;
    filas_procesadas: number;
    filas_con_error: number;
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
    dias_vacaciones: number;
    importe_vacaciones: string | number;
    aguinaldo_diario: string | number;
    prima_vacacional: string | number;
    salario_diario_integrado: string | number;
    limite_salario_diario: string | number;
    excede_limite: boolean;
    empleado?: {
        nombre: string;
        rfc_receptor: string;
        numero_empleado: string;
        antiguedad_anios: number;
    };
    categoria?: {
        nombre: string;
    };
}

interface Props {
    empresa: Empresa;
    importacion: ImportacionState;
    salariosMuestra: SalarioMuestra[];
}

const props = defineProps<Props>();

const importData = ref<ImportacionState>({ ...props.importacion });
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
        const response = await fetch(`/empresas/${props.empresa.id}/importar-cfdi/${importData.value.id}/estado`, {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const data = await response.json();
            importData.value = { ...importData.value, ...data };

            if (data.estado === 'completado') {
                stopPolling();
                toast.success('¡Importación completada exitosamente!');
                router.reload({ only: ['salariosMuestra'] });
            } else if (data.estado === 'fallido') {
                stopPolling();
                toast.error('La importación ha finalizado con errores.');
            }
        }
    } catch {
        // Silently continue polling on network hiccups
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
        `/empresas/${props.empresa.id}/importar-cfdi/${importData.value.id}/reintentar`,
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
        <Head :title="`Progreso de Importación - ${importData.archivo_nombre}`" />

        <!-- Navigation & Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button variant="outline" size="icon" as-child class="h-9 w-9">
                    <Link :href="`/empresas/${empresa.id}/importar-cfdi`">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <FileSpreadsheet class="h-4 w-4" />
                        </span>
                        <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Progreso de Importación #{{ importData.id }}
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
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/empresas/${empresa.id}/importar-cfdi`">
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
                        <CardTitle class="text-lg">Estado del Procesamiento en Cola</CardTitle>
                        <CardDescription>
                            {{
                                importData.estado === 'completado'
                                    ? 'Se han procesado todos los registros y generado los salarios integrados.'
                                    : importData.estado === 'procesando'
                                    ? 'El servidor está leyendo el Excel línea por línea de manera optimizada...'
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
                            {{ importData.filas_procesadas.toLocaleString() }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-3">
                        <span class="text-xs text-muted-foreground">Total de Filas</span>
                        <p class="mt-1 font-mono text-xl font-bold text-foreground">
                            {{ importData.total_filas > 0 ? importData.total_filas.toLocaleString() : 'Calculando...' }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-3">
                        <span class="text-xs text-muted-foreground">Filas con Advertencias</span>
                        <p :class="['mt-1 font-mono text-xl font-bold', importData.filas_con_error > 0 ? 'text-amber-500' : 'text-foreground']">
                            {{ importData.filas_con_error.toLocaleString() }}
                        </p>
                    </div>
                    <div class="rounded-xl border bg-card p-3">
                        <span class="text-xs text-muted-foreground">Prestaciones Empresa</span>
                        <p class="mt-1 text-sm font-semibold text-foreground">
                            {{ empresa.dias_aguinaldo }}d ag. / {{ empresa.prima_vacacional }}% prima
                        </p>
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

                <!-- Errors log list -->
                <div
                    v-if="importData.errores && importData.errores.length > 0"
                    class="space-y-2 rounded-lg border border-amber-500/20 bg-amber-500/5 p-4"
                >
                    <div class="flex items-center gap-2 text-xs font-semibold text-amber-700 dark:text-amber-400">
                        <ShieldAlert class="h-4 w-4" />
                        <span>Detalle de advertencias durante la lectura (mostrando hasta 100):</span>
                    </div>
                    <div class="max-h-40 overflow-y-auto space-y-1 text-[11px] font-mono text-muted-foreground">
                        <div v-for="(err, idx) in importData.errores" :key="idx" class="flex gap-2">
                            <span class="font-bold text-foreground">Fila {{ err.fila }}:</span>
                            <span>{{ err.mensaje }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Sample of Generated Records (Audit Preview) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-foreground">
                        Registros de Empleados y Salarios Generados
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Muestra de comprobantes procesados con cálculo de SDI y topes de UMA.
                    </p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-2xs">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                No. Empleado
                            </TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Nombre Empleado
                            </TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Periodo
                            </TableHead>
                            <TableHead class="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Salario Base
                            </TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Días Vac.
                            </TableHead>
                            <TableHead class="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Aguinaldo Diario
                            </TableHead>
                            <TableHead class="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Prima Vac.
                            </TableHead>
                            <TableHead class="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                SDI Calculado
                            </TableHead>
                            <TableHead class="text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Límite UMA (25x)
                            </TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Estado Tope
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in salariosList" :key="item.id">
                            <TableCell class="font-mono text-xs font-medium text-foreground">
                                {{ item.empleado?.numero_empleado || 'N/A' }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-medium text-foreground">{{ item.empleado?.nombre || 'Empleado' }}</span>
                                    <span class="font-mono text-[11px] text-muted-foreground">{{ item.empleado?.rfc_receptor }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" class="font-mono text-xs">
                                    P-{{ item.periodo }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs">
                                {{ formatCurrency(item.salario_base) }}
                            </TableCell>
                            <TableCell class="text-center font-mono text-xs">
                                {{ item.dias_vacaciones }} días
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs">
                                {{ formatCurrency(item.aguinaldo_diario) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs">
                                {{ formatCurrency(item.prima_vacacional) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs font-bold text-foreground">
                                {{ formatCurrency(item.salario_diario_integrado) }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs text-muted-foreground">
                                {{ formatCurrency(item.limite_salario_diario) }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge
                                    v-if="item.excede_limite"
                                    variant="destructive"
                                    class="text-[10px]"
                                >
                                    Excede tope
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="text-[10px] text-emerald-600 border-emerald-500/30 dark:text-emerald-400"
                                >
                                    Dentro de ley
                                </Badge>
                            </TableCell>
                        </TableRow>

                        <TableEmpty v-if="salariosList.length === 0" :colspan="10">
                            <ListCheck class="h-10 w-10 text-muted-foreground/40" />
                            <p class="font-medium text-foreground">Aún no se han guardado registros</p>
                            <p class="text-xs text-muted-foreground">
                                En cuanto el trabajo en segundo plano procese las primeras filas del Excel, aparecerán aquí.
                            </p>
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
