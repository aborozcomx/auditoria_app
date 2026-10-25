<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    FileUp,
    HelpCircle,
    Info,
    Receipt,
    RotateCw,
    ShieldCheck,
    Upload,
    Users,
    XCircle,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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
    TableHead,
} from '@/components/ui/table';
import InputError from '@/components/InputError.vue';
import type { Empresa } from '@/types';

interface HistorialItem {
    id: number;
    anio: number;
    archivo_nombre: string;
    estado: 'pendiente' | 'procesando' | 'completado' | 'fallido';
    total_filas: number;
    filas_procesadas: number;
    cuotas_creadas: number;
    salarios_actualizados: number;
    total_auditoria: string | number;
    total_imss: string | number;
    total_diferencia: string | number;
    created_at: string;
}

interface Props {
    empresa: Empresa;
    historial: HistorialItem[];
    aniosDisponibles: number[];
}

const props = defineProps<Props>();

const form = useForm({
    anio: props.aniosDisponibles[0] || new Date().getFullYear(),
    archivo: null as File | null,
});

const isDragging = ref(false);
const selectedFileName = ref<string>('');
const selectedFileSize = ref<string>('');

const onFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        setFile(target.files[0]);
    }
};

const onDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
        setFile(event.dataTransfer.files[0]);
    }
};

const setFile = (file: File) => {
    form.archivo = file;
    selectedFileName.value = file.name;
    const mb = file.size / (1024 * 1024);
    selectedFileSize.value = mb >= 1 ? `${mb.toFixed(2)} MB` : `${(file.size / 1024).toFixed(0)} KB`;
};

const clearFile = () => {
    form.archivo = null;
    selectedFileName.value = '';
    selectedFileSize.value = '';
};

const submitForm = () => {
    if (!form.archivo) {
        toast.error('Por favor selecciona un archivo Excel para continuar.');
        return;
    }

    form.post(`/empresas/${props.empresa.id}/importar-cuotas-imss`, {
        preserveScroll: true,
        onError: () => {
            toast.error('Ocurrió un error al procesar la carga del archivo.');
        },
    });
};

const formatCurrency = (amount: number | string | null | undefined): string => {
    if (amount === null || amount === undefined || isNaN(Number(amount))) {
        return '$0.00';
    }
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(amount));
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Importar Cuotas IMSS" />

        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Receipt class="h-5 w-5" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Importar Cuotas IMSS de Empleados
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Carga el archivo Excel de liquidación o emisión IMSS para auditar cuotas obrero-patronales, contrastar diferencias y actualizar el SDI cotizado.
                </p>
            </div>

            <Button variant="outline" as-child class="gap-2 self-start sm:self-auto">
                <Link href="/empresas">
                    <ArrowLeft class="h-4 w-4" />
                    Volver a Empresas
                </Link>
            </Button>
        </div>

        <!-- Empresa Summary Card -->
        <Card class="border-border bg-card">
            <CardHeader class="pb-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Building2 class="h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle class="text-lg font-semibold">{{ empresa.nombre }}</CardTitle>
                            <CardDescription class="text-xs text-muted-foreground">{{ empresa.razon_social }}</CardDescription>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" class="font-mono text-xs font-semibold">
                            RFC: {{ empresa.rfc }}
                        </Badge>
                        <Badge variant="secondary" class="text-xs">
                            Aguinaldo: {{ empresa.dias_aguinaldo }} días
                        </Badge>
                        <Badge variant="secondary" class="text-xs">
                            Prima Vacacional: {{ empresa.prima_vacacional }}%
                        </Badge>
                    </div>
                </div>
            </CardHeader>
        </Card>

        <!-- Form and Guide Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Upload Form -->
            <Card class="border-border bg-card lg:col-span-7">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base font-semibold">
                        <FileUp class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        Cargar Archivo Excel de Cuotas IMSS
                    </CardTitle>
                    <CardDescription>
                        Selecciona el año correspondiente y sube el archivo con el desglose de cuotas obrero-patronales emitidas.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <!-- Año Selector -->
                        <div class="space-y-2">
                            <Label for="anio" class="flex items-center gap-1.5 text-sm font-medium">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                Año Fiscal de las Cuotas
                            </Label>
                            <select
                                id="anio"
                                v-model="form.anio"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option v-for="yr in aniosDisponibles" :key="yr" :value="yr">
                                    Año {{ yr }}
                                </option>
                            </select>
                            <p class="text-[11px] text-muted-foreground">
                                Nota: Para el <strong>Periodo 1</strong>, el sistema aplicará automáticamente la UMA del año anterior (<span class="font-mono">{{ form.anio - 1 }}</span>) conforme a las disposiciones legales.
                            </p>
                            <InputError :message="form.errors.anio" />
                        </div>

                        <!-- Drag & Drop Zone -->
                        <div class="space-y-2">
                            <Label class="flex items-center gap-1.5 text-sm font-medium">
                                <FileSpreadsheet class="h-4 w-4 text-muted-foreground" />
                                Archivo Excel (.xlsx, .xls o .csv)
                            </Label>
                            <div
                                :class="[
                                    'relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 text-center transition-colors',
                                    isDragging
                                        ? 'border-emerald-500 bg-emerald-500/5'
                                        : 'border-muted-foreground/25 hover:border-emerald-500/50 hover:bg-muted/30',
                                    form.errors.archivo ? 'border-destructive bg-destructive/5' : '',
                                ]"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onDrop"
                            >
                                <input
                                    id="file-upload"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    class="sr-only"
                                    @change="onFileSelected"
                                />

                                <div v-if="!selectedFileName" class="flex flex-col items-center gap-2">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <Upload class="h-6 w-6" />
                                    </div>
                                    <div class="text-sm">
                                        <label
                                            for="file-upload"
                                            class="cursor-pointer font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                                        >
                                            Haz clic para seleccionar
                                        </label>
                                        <span class="text-muted-foreground"> o arrastra y suelta tu archivo aquí</span>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        Archivos Excel (.xlsx, .xls) o CSV hasta 100 MB
                                    </p>
                                </div>

                                <div v-else class="flex w-full items-center justify-between rounded-lg bg-background p-3 border">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                            <FileSpreadsheet class="h-5 w-5" />
                                        </div>
                                        <div class="min-w-0 text-left">
                                            <p class="truncate text-sm font-semibold text-foreground">
                                                {{ selectedFileName }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ selectedFileSize }}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-xs text-muted-foreground hover:text-destructive"
                                        @click="clearFile"
                                    >
                                        Cambiar
                                    </Button>
                                </div>
                            </div>
                            <InputError :message="form.errors.archivo" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-2">
                            <Button
                                type="submit"
                                :disabled="form.processing || !form.archivo"
                                class="gap-2 w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white"
                            >
                                <RotateCw v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Upload v-else class="h-4 w-4" />
                                {{ form.processing ? 'Cargando y validando...' : 'Iniciar Importación de Cuotas' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Guide and Specifications -->
            <Card class="border-border bg-card lg:col-span-5">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base font-semibold">
                        <HelpCircle class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        Estructura Esperada del Archivo
                    </CardTitle>
                    <CardDescription>
                        El archivo Excel debe contener los siguientes encabezados de columnas:
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-xs">
                    <div class="rounded-lg border bg-muted/40 p-3 space-y-2 font-mono">
                        <div class="font-bold text-foreground text-xs pb-1 border-b">
                            Columnas Requeridas:
                        </div>
                        <ul class="space-y-1 text-muted-foreground">
                            <li><strong class="text-foreground">Periodo:</strong> Mes o periodo (1 a 12)</li>
                            <li><strong class="text-foreground">NSS:</strong> Número de Seguridad Social (11 dígitos)</li>
                            <li><strong class="text-foreground">NOM:</strong> Nombre del empleado</li>
                            <li><strong class="text-foreground">Dias:</strong> Días cotizados en el periodo</li>
                            <li><strong class="text-foreground">SDI:</strong> Salario Diario Integrado reportado</li>
                            <li><strong class="text-foreground">Inc.:</strong> Días de incapacidad (opcional)</li>
                            <li><strong class="text-foreground">Aus.:</strong> Días de ausencia (opcional)</li>
                            <li><strong class="text-foreground">Cuota IMSS:</strong> Subtotal de cuota IMSS del archivo</li>
                            <li><strong class="text-foreground">Prima:</strong> Prima de riesgo de trabajo</li>
                            <li><strong class="text-foreground">RP:</strong> Registro Patronal (opcional)</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 space-y-2">
                        <div class="flex items-center gap-2 font-semibold text-emerald-800 dark:text-emerald-300">
                            <ShieldCheck class="h-4 w-4 shrink-0" />
                            Reglas de Auditoría y Fórmulas
                        </div>
                        <p class="text-muted-foreground leading-relaxed">
                            1. Si el empleado cuenta con un registro en <em>Salarios</em> para ese periodo y año, se tomará su <strong>SDI Auditado</strong> como base y se actualizará la columna <strong>IMSS Cía</strong> con el SDI del archivo y se calculará la <strong>Diferencia</strong>.
                        </p>
                        <p class="text-muted-foreground leading-relaxed">
                            2. El sistema calculará automáticamente Cuota Fija, Excedente 3 UMA, Prestaciones en Dinero, Invalidez y Vida (topada a 25 UMA), Pensionados, Riesgo de Trabajo y Guarderías.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- History of Imports -->
        <Card class="border-border bg-card shadow-2xs">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base font-semibold">
                    <Clock class="h-5 w-5 text-muted-foreground" />
                    Historial de Importaciones de Cuotas IMSS
                </CardTitle>
                <CardDescription>
                    Últimas importaciones realizadas para {{ empresa.nombre }}.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto rounded-lg border">
                    <Table class="min-w-full text-xs">
                        <TableHeader>
                            <TableRow class="bg-muted/40">
                                <TableHead class="w-16">ID</TableHead>
                                <TableHead class="text-center">Año</TableHead>
                                <TableHead>Nombre del Archivo</TableHead>
                                <TableHead class="text-center">Estado</TableHead>
                                <TableHead class="text-center">Cuotas</TableHead>
                                <TableHead class="text-center">Salarios Actualizados</TableHead>
                                <TableHead class="text-right">Total Auditoría</TableHead>
                                <TableHead class="text-right">Total IMSS</TableHead>
                                <TableHead class="text-right">Diferencia</TableHead>
                                <TableHead class="text-center">Fecha</TableHead>
                                <TableHead class="text-right">Acción</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in historial" :key="item.id">
                                <TableCell class="font-mono text-muted-foreground">
                                    #{{ item.id }}
                                </TableCell>
                                <TableCell class="text-center font-mono font-bold">
                                    {{ item.anio }}
                                </TableCell>
                                <TableCell class="font-medium truncate max-w-xs" :title="item.archivo_nombre">
                                    {{ item.archivo_nombre }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        :variant="
                                            item.estado === 'completado'
                                                ? 'default'
                                                : item.estado === 'fallido'
                                                ? 'destructive'
                                                : 'outline'
                                        "
                                        class="capitalize text-[10px]"
                                    >
                                        {{ item.estado }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center font-mono">
                                    {{ item.cuotas_creadas || item.filas_procesadas }}
                                </TableCell>
                                <TableCell class="text-center font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ item.salarios_actualizados }}
                                </TableCell>
                                <TableCell class="text-right font-mono font-semibold text-foreground">
                                    {{ formatCurrency(item.total_auditoria) }}
                                </TableCell>
                                <TableCell class="text-right font-mono text-muted-foreground">
                                    {{ formatCurrency(item.total_imss) }}
                                </TableCell>
                                <TableCell class="text-right font-mono font-bold" :class="Number(item.total_diferencia) !== 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                                    {{ formatCurrency(item.total_diferencia) }}
                                </TableCell>
                                <TableCell class="text-center text-muted-foreground">
                                    {{ new Date(item.created_at).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                        class="h-7 text-xs"
                                    >
                                        <Link :href="`/empresas/${empresa.id}/importar-cuotas-imss/${item.id}`">
                                            Ver Visualizador
                                        </Link>
                                    </Button>
                                </TableCell>
                            </TableRow>

                            <TableEmpty v-if="historial.length === 0" :colspan="11">
                                <Receipt class="h-8 w-8 text-muted-foreground/40 mx-auto" />
                                <p class="font-medium text-foreground mt-2">No se han realizado importaciones de cuotas IMSS</p>
                                <p class="text-xs text-muted-foreground">Sube el primer archivo Excel para comenzar.</p>
                            </TableEmpty>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
