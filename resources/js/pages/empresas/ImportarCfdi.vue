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
    RotateCw,
    Upload,
    XCircle,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
} from '@/components/ui/table';
import InputError from '@/components/InputError.vue';
import type { Empresa } from '@/types';

interface ImportacionItem {
    id: number;
    empresa_id: number;
    anio: number;
    archivo_nombre: string;
    estado: 'pendiente' | 'procesando' | 'completado' | 'fallido';
    total_filas: number;
    filas_procesadas: number;
    filas_con_error: number;
    salarios_count?: number;
    created_at: string;
}

interface Props {
    empresa: Empresa;
    historial: ImportacionItem[];
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

    form.post(`/empresas/${props.empresa.id}/importar-cfdi`, {
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
        <Head :title="`Importar CFDIs - ${empresa.nombre}`" />

        <!-- Top Navigation & Header -->
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
                            <FileSpreadsheet class="h-4 w-4" />
                        </span>
                        <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Importar CFDIs de Nómina
                        </h1>
                    </div>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        Carga masiva de comprobantes de nómina para auditoría contable en segundo plano.
                    </p>
                </div>
            </div>

            <!-- Company Quick Badge -->
            <div class="flex items-center gap-3 rounded-lg border bg-card px-3 py-2 text-xs shadow-2xs">
                <Building2 class="h-4 w-4 text-primary" />
                <div>
                    <p class="font-semibold text-foreground">{{ empresa.nombre }}</p>
                    <p class="font-mono text-muted-foreground">{{ empresa.rfc }} • Aguinaldo: {{ empresa.dias_aguinaldo }}d • Prima: {{ empresa.prima_vacacional }}%</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Form Card (2 cols) -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-lg">Configurar Nueva Importación</CardTitle>
                    <CardDescription>
                        Selecciona el ejercicio fiscal de la auditoría y arrastra el archivo de nómina correspondiente.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submitForm">
                        <!-- Year input -->
                        <div class="space-y-2">
                            <Label for="anio" class="flex items-center gap-1.5 font-medium">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                Año Fiscal de la Auditoría
                            </Label>
                            <div class="flex max-w-xs items-center gap-2">
                                <select
                                    id="anio"
                                    v-model="form.anio"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                    required
                                >
                                    <option
                                        v-for="year in aniosDisponibles"
                                        :key="year"
                                        :value="year"
                                    >
                                        {{ year }}
                                    </option>
                                </select>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Utilizado para determinar las UMAs correspondientes (periodos 1 y 2 toman la UMA anterior).
                            </p>
                            <InputError :message="form.errors.anio" />
                        </div>

                        <!-- Dropzone File Upload -->
                        <div class="space-y-2">
                            <Label class="flex items-center gap-1.5 font-medium">
                                <FileUp class="h-4 w-4 text-muted-foreground" />
                                Archivo en Excel (.xlsx, .xls o .csv)
                            </Label>

                            <div
                                :class="[
                                    'relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 text-center transition-all',
                                    isDragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/30',
                                    form.archivo ? 'border-emerald-500/50 bg-emerald-500/5' : '',
                                ]"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onDrop"
                            >
                                <input
                                    id="archivo"
                                    type="file"
                                    accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                                    class="absolute inset-0 cursor-pointer opacity-0"
                                    @change="onFileSelected"
                                />

                                <template v-if="!form.archivo">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Upload class="h-6 w-6" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-foreground">
                                        Haz clic para seleccionar o arrastra el archivo aquí
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Formatos soportados: Excel (.xlsx, .xls) o CSV (hasta 100 MB)
                                    </p>
                                </template>

                                <template v-else>
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <FileSpreadsheet class="h-6 w-6" />
                                    </div>
                                    <p class="mt-3 font-mono text-sm font-semibold text-foreground">
                                        {{ selectedFileName }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Tamaño: {{ selectedFileSize }} • Listo para procesar
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="mt-3 text-xs"
                                        @click.stop="clearFile"
                                    >
                                        Cambiar archivo
                                    </Button>
                                </template>
                            </div>
                            <InputError :message="form.errors.archivo" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <Button
                                type="submit"
                                :disabled="form.processing || !form.archivo"
                                class="gap-2 px-6"
                            >
                                <RotateCw v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Upload v-else class="h-4 w-4" />
                                <span>{{ form.processing ? 'Subiendo archivo...' : 'Iniciar Importación en Cola' }}</span>
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
                        <CardTitle class="text-base">Estructura del Excel</CardTitle>
                    </div>
                    <CardDescription>
                        Encabezados esperados en la primera fila de tu archivo:
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex-1 space-y-4 text-xs">
                    <div class="space-y-1 rounded-lg bg-muted/50 p-3 font-mono text-[11px] leading-relaxed">
                        <p class="font-semibold text-foreground">Columnas detectadas automáticamente:</p>
                        <ul class="list-inside list-disc text-muted-foreground">
                            <li>Periodo</li>
                            <li>Tipo nomina</li>
                            <li>RFC emisor / Razon emisor</li>
                            <li>Registro patronal</li>
                            <li>RFC receptor</li>
                            <li>Razon receptor (Nombre)</li>
                            <li>Fecha inicio relacion laboral</li>
                            <li>Num empleado</li>
                            <li>Antiguedad (ej. P706W - 706 semanas)</li>
                            <li>Sindicalizado (Si / No)</li>
                            <li>CURP / No Seguro social</li>
                            <li>Salario base cot apor</li>
                            <li>Salario diario integrado</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-muted-foreground">
                        <p class="font-semibold text-foreground">Reglas de validación al subir:</p>
                        <ul class="mt-1 list-inside list-disc space-y-1">
                            <li>El <strong>RFC emisor</strong> debe coincidir con la empresa seleccionada y estar registrado en el sistema.</li>
                            <li>Un empleado solo puede pertenecer a <strong>una sola empresa</strong> en el sistema.</li>
                            <li>Solo se importan registros con tipo de nómina <strong>O - Nómina ordinaria</strong>.</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-muted bg-muted/30 p-3 text-muted-foreground">
                        <p class="font-semibold text-foreground">Cálculos automáticos:</p>
                        <p class="mt-1">
                            El sistema calcula automáticamente la antigüedad en semanas y años cerrados, días de vacaciones por categoría, aguinaldo diario, importe y prima vacacional, SDI y topes de 25 UMAs.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- History of Imports for this company -->
        <div class="mt-4 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-foreground">
                        Historial de Importaciones
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Registro de auditorías e importaciones ejecutadas para esta empresa.
                    </p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-2xs">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Folio
                            </TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Ejercicio
                            </TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Archivo
                            </TableHead>
                            <TableHead class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Estado
                            </TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Filas Procesadas
                            </TableHead>
                            <TableHead class="text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Fecha de Subida
                            </TableHead>
                            <TableHead class="w-24 text-right">
                                <div class="inline-flex w-full items-center justify-end text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Detalles
                                </div>
                            </TableHead>
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
                                    <span class="font-medium text-foreground">{{ item.archivo_nombre }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    v-if="item.estado === 'completado'"
                                    variant="secondary"
                                    class="gap-1 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Completado
                                </Badge>
                                <Badge
                                    v-else-if="item.estado === 'procesando'"
                                    variant="outline"
                                    class="gap-1 border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400"
                                >
                                    <RotateCw class="h-3 w-3 animate-spin" />
                                    Procesando
                                </Badge>
                                <Badge
                                    v-else-if="item.estado === 'pendiente'"
                                    variant="outline"
                                    class="gap-1 text-muted-foreground"
                                >
                                    <Clock class="h-3 w-3" />
                                    En Cola
                                </Badge>
                                <Badge
                                    v-else
                                    variant="destructive"
                                    class="gap-1"
                                >
                                    <XCircle class="h-3 w-3" />
                                    Fallido
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center font-mono text-xs">
                                <span class="font-semibold text-foreground">{{ item.filas_procesadas }}</span>
                                <span v-if="item.total_filas > 0" class="text-muted-foreground"> / {{ item.total_filas }}</span>
                            </TableCell>
                            <TableCell class="text-center text-xs text-muted-foreground">
                                {{ new Date(item.created_at).toLocaleString('es-MX') }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="sm" as-child class="text-xs">
                                    <Link :href="`/empresas/${empresa.id}/importar-cfdi/${item.id}`">
                                        Ver Progreso
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>

                        <TableEmpty v-if="historial.length === 0" :colspan="7">
                            <FileSpreadsheet class="h-10 w-10 text-muted-foreground/40" />
                            <p class="font-medium text-foreground">No hay importaciones previas</p>
                            <p class="text-xs text-muted-foreground">
                                Sube el primer archivo Excel para comenzar a auditar los CFDIs de esta empresa.
                            </p>
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
