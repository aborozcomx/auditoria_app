<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Coins,
    FileSpreadsheet,
    FileUp,
    HelpCircle,
    Info,
    RotateCw,
    TrendingUp,
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
    empleados_actualizados: number;
    salarios_actualizados: number;
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
        toast.error('Por favor selecciona un archivo Excel (.xlsx o .csv).');
        return;
    }

    form.post(`/empresas/${props.empresa.id}/importar-variables`, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            if (errors.archivo) {
                toast.error(errors.archivo);
            } else if (errors.anio) {
                toast.error(errors.anio);
            } else {
                toast.error('Ocurrió un error al cargar el archivo. Verifica los campos.');
            }
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head :title="`Importar Variables - ${empresa.nombre}`" />

        <!-- Navigation & Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button variant="outline" size="icon" as-child class="h-9 w-9">
                    <Link href="/empresas">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Coins class="h-4 w-4" />
                        </span>
                        <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Importar Variables de Empleados
                        </h1>
                    </div>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        {{ empresa.nombre }} • RFC: <span class="font-mono">{{ empresa.rfc }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/salarios?empresa_id=${empresa.id}`">
                        Ver Salarios
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/empresas/${empresa.id}/importar-cfdi`">
                        Importar CFDIs
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Upload Form Card -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-lg">Subir Archivo de Variables</CardTitle>
                    <CardDescription>
                        Carga el archivo Excel o CSV con los días calendario y la parte variable acumulada.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <!-- Fiscal Year Selection -->
                        <div class="space-y-2">
                            <Label for="anio" class="flex items-center gap-1.5 text-sm font-semibold">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                <span>Año Fiscal a Afectar</span>
                                <span class="text-destructive">*</span>
                            </Label>
                            <div class="max-w-xs">
                                <select
                                    id="anio"
                                    v-model="form.anio"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    :disabled="form.processing"
                                >
                                    <option v-for="a in aniosDisponibles" :key="a" :value="a">
                                        {{ a }}
                                    </option>
                                </select>
                            </div>
                            <InputError :message="form.errors.anio" />
                        </div>

                        <!-- Drag and Drop Dropzone -->
                        <div class="space-y-2">
                            <Label class="flex items-center gap-1.5 text-sm font-semibold">
                                <FileSpreadsheet class="h-4 w-4 text-muted-foreground" />
                                <span>Archivo de Variables (Excel o CSV)</span>
                                <span class="text-destructive">*</span>
                            </Label>

                            <div
                                :class="[
                                    'relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors',
                                    isDragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-muted-foreground/25 hover:border-muted-foreground/50 hover:bg-muted/30',
                                    form.processing ? 'opacity-50 pointer-events-none' : '',
                                ]"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onDrop"
                            >
                                <input
                                    id="archivo"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                    :disabled="form.processing"
                                    @change="onFileSelected"
                                />

                                <div class="flex flex-col items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <Upload class="h-6 w-6" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm font-medium text-foreground">
                                            Arrastra el archivo aquí o <span class="text-primary underline">haz clic para examinar</span>
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Formatos soportados: Excel (.xlsx, .xls) o CSV (.csv). Hasta 100 MB.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Selected File Indicator -->
                            <div
                                v-if="selectedFileName"
                                class="flex items-center justify-between rounded-lg border bg-muted/40 p-3"
                            >
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <FileSpreadsheet class="h-5 w-5" />
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="truncate text-sm font-medium text-foreground">
                                            {{ selectedFileName }}
                                        </p>
                                        <p class="text-xs text-muted-foreground font-mono">
                                            {{ selectedFileSize }}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="form.processing"
                                    @click="clearFile"
                                >
                                    Cambiar
                                </Button>
                            </div>

                            <InputError :message="form.errors.archivo" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center gap-3 pt-2">
                            <Button
                                type="submit"
                                :disabled="form.processing || !form.archivo"
                                class="gap-2"
                            >
                                <RotateCw v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <FileUp v-else class="h-4 w-4" />
                                <span>{{ form.processing ? 'Subiendo y procesando...' : 'Iniciar Importación de Variables' }}</span>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Guide & Layout Requirements Card -->
            <Card class="flex flex-col">
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Info class="h-4 w-4 text-primary" />
                        <CardTitle class="text-base">Estructura del Archivo</CardTitle>
                    </div>
                    <CardDescription>
                        Columnas requeridas en la cabecera del archivo de variables:
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex-1 space-y-4 text-xs">
                    <div class="space-y-1.5 rounded-lg bg-muted/50 p-3 font-mono text-[11px] leading-relaxed">
                        <p class="font-semibold text-foreground">Encabezados esperados:</p>
                        <ul class="list-inside list-disc text-muted-foreground space-y-0.5">
                            <li><strong class="text-foreground">Número de personal:</strong> Número de empleado registrado en el sistema.</li>
                            <li><strong class="text-foreground">Final del período en Nómina:</strong> Fecha de corte (ej. 15/1/2025).</li>
                            <li><strong class="text-foreground">Texto expl.CC-nómina:</strong> "Días calendario" o "Parte variable acumulada".</li>
                            <li><strong class="text-foreground">Cantidad:</strong> Días calendario acumulados.</li>
                            <li><strong class="text-foreground">Importe:</strong> Monto de variable acumulada.</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-muted-foreground">
                        <p class="font-semibold text-foreground">Regla de Afectación Bimestral:</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5 text-[11px]">
                            <li><strong>Noviembre / Diciembre (11, 12):</strong> Afecta Periodos 1 y 2</li>
                            <li><strong>Enero / Febrero (1, 2):</strong> Afecta Periodos 3 y 4</li>
                            <li><strong>Marzo / Abril (3, 4):</strong> Afecta Periodos 5 y 6</li>
                            <li><strong>Mayo / Junio (5, 6):</strong> Afecta Periodos 7 y 8</li>
                            <li><strong>Julio / Agosto (7, 8):</strong> Afecta Periodos 9 y 10</li>
                            <li><strong>Septiembre / Octubre (9, 10):</strong> Afecta Periodos 11 y 12</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-muted bg-muted/30 p-3 text-muted-foreground">
                        <p class="font-semibold text-foreground">Fórmulas aplicadas:</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5 text-[11px]">
                            <li><strong>SDI Variable:</strong> Importe Suma ÷ Cantidad Días</li>
                            <li><strong>SDI Total:</strong> SDI Calculado + SDI Variable</li>
                            <li><strong>SDI Auditado:</strong> Si SDI Total > Tope 25 UMAs, se topa; de lo contrario toma el SDI Total.</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- History of Past Variable Imports -->
        <Card>
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base">Historial de Importaciones de Variables</CardTitle>
                        <CardDescription>
                            Registro de archivos procesados y su estado de avance para esta empresa.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-16">#ID</TableHead>
                            <TableHead class="w-24">Año</TableHead>
                            <TableHead>Archivo</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-center">Progreso</TableHead>
                            <TableHead class="text-center">Empleados Act.</TableHead>
                            <TableHead class="text-center">Fecha de Carga</TableHead>
                            <TableHead class="text-right">Acción</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in historial" :key="item.id">
                            <TableCell class="font-mono text-xs text-muted-foreground">
                                #{{ item.id }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" class="font-semibold">
                                    {{ item.anio }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <FileSpreadsheet class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                    <span class="font-medium text-foreground text-xs">{{ item.archivo_nombre }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    v-if="item.estado === 'completado'"
                                    variant="secondary"
                                    class="gap-1 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Completado
                                </Badge>
                                <Badge
                                    v-else-if="item.estado === 'procesando'"
                                    variant="outline"
                                    class="gap-1 border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400 text-xs"
                                >
                                    <RotateCw class="h-3 w-3 animate-spin" />
                                    Procesando
                                </Badge>
                                <Badge
                                    v-else-if="item.estado === 'pendiente'"
                                    variant="outline"
                                    class="gap-1 text-muted-foreground text-xs"
                                >
                                    <Clock class="h-3 w-3" />
                                    En Cola
                                </Badge>
                                <Badge
                                    v-else
                                    variant="destructive"
                                    class="gap-1 text-xs"
                                >
                                    <XCircle class="h-3 w-3" />
                                    Fallido
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center font-mono text-xs">
                                <span class="font-semibold text-foreground">{{ item.filas_procesadas || 0 }}</span>
                                <span v-if="item.total_filas > 0" class="text-muted-foreground"> / {{ item.total_filas }}</span>
                            </TableCell>
                            <TableCell class="text-center font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ item.empleados_actualizados || 0 }}
                            </TableCell>
                            <TableCell class="text-center text-xs text-muted-foreground">
                                {{ new Date(item.created_at).toLocaleString('es-MX') }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="sm" as-child class="text-xs">
                                    <Link :href="`/empresas/${empresa.id}/importar-variables/${item.id}`">
                                        Ver Progreso
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>

                        <TableEmpty v-if="historial.length === 0" :colspan="8">
                            <Coins class="h-10 w-10 text-muted-foreground/40" />
                            <p class="font-medium text-foreground">No hay importaciones de variables previas</p>
                            <p class="text-xs text-muted-foreground">
                                Sube el primer archivo Excel para calcular el SDI Variable y SDI Auditado de los empleados.
                            </p>
                        </TableEmpty>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
