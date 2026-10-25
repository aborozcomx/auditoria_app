<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Copy,
    DollarSign,
    Download,
    FileSpreadsheet,
    Layers,
    Receipt,
    RefreshCw,
    ShieldCheck,
    Users,
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
import { Label } from '@/components/ui/label';

interface EmpresaItem {
    id: number;
    nombre: string;
    rfc: string;
    razon_social?: string | null;
}

interface InitialSelection {
    empresa_id: number;
    anio: number;
    periodo: string;
    total_cuotas: number;
    total_salarios: number;
    nombre_archivo: string;
    periodo_rango: string;
}

interface Props {
    empresas: EmpresaItem[];
    anios: number[];
    initialSelection: InitialSelection;
}

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Exportar Auditoría', href: '/exportar-auditoria' },
        ],
    },
});

const mesesNombres = [
    { periodo: '1', nombre: '1 - Enero' },
    { periodo: '2', nombre: '2 - Febrero' },
    { periodo: '3', nombre: '3 - Marzo' },
    { periodo: '4', nombre: '4 - Abril' },
    { periodo: '5', nombre: '5 - Mayo' },
    { periodo: '6', nombre: '6 - Junio' },
    { periodo: '7', nombre: '7 - Julio' },
    { periodo: '8', nombre: '8 - Agosto' },
    { periodo: '9', nombre: '9 - Septiembre' },
    { periodo: '10', nombre: '10 - Octubre' },
    { periodo: '11', nombre: '11 - Noviembre' },
    { periodo: '12', nombre: '12 - Diciembre' },
];

const selectedEmpresaId = ref<number>(props.initialSelection.empresa_id || (props.empresas[0]?.id ?? 0));
const selectedAnio = ref<number>(props.initialSelection.anio || (props.anios[0] ?? new Date().getFullYear()));
const selectedPeriodo = ref<string>(props.initialSelection.periodo || '1');

const totalCuotas = ref<number>(props.initialSelection.total_cuotas ?? 0);
const totalSalarios = ref<number>(props.initialSelection.total_salarios ?? 0);
const nombreArchivo = ref<string>(props.initialSelection.nombre_archivo ?? '');
const periodoRango = ref<string>(props.initialSelection.periodo_rango ?? '');

const isLoadingResumen = ref(false);
const isDownloading = ref(false);

const selectedEmpresa = computed(() => {
    return props.empresas.find((e) => e.id === Number(selectedEmpresaId.value)) || null;
});

const sanitizeRfc = (rfc: string | undefined): string => {
    return (rfc || 'EMPRESA').replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
};

const computedNombreArchivo = computed(() => {
    if (nombreArchivo.value) {
        return nombreArchivo.value;
    }
    const rfc = sanitizeRfc(selectedEmpresa.value?.rfc);
    return `Pruebas_${rfc}_${selectedPeriodo.value}_${selectedAnio.value}.xlsx`;
});

const fetchResumen = async () => {
    if (!selectedEmpresaId.value) return;

    isLoadingResumen.value = true;
    try {
        const response = await fetch(
            `/exportar-auditoria/resumen?empresa_id=${selectedEmpresaId.value}&anio=${selectedAnio.value}&periodo=${selectedPeriodo.value}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        if (!response.ok) {
            throw new Error('Error al consultar información');
        }

        const data = await response.json();
        totalCuotas.value = Number(data.total_cuotas ?? 0);
        totalSalarios.value = Number(data.total_salarios ?? 0);
        nombreArchivo.value = data.nombre_archivo ?? '';
        periodoRango.value = data.periodo_rango ?? '';
    } catch (error) {
        console.error('Error fetching resumen:', error);
    } finally {
        isLoadingResumen.value = false;
    }
};

watch([selectedEmpresaId, selectedAnio, selectedPeriodo], () => {
    fetchResumen();
});

const handleDownload = () => {
    if (!selectedEmpresaId.value) {
        toast.error('Selecciona una empresa para exportar');
        return;
    }

    isDownloading.value = true;
    toast.info('Generando archivo Excel de auditoría...');

    const url = `/exportar-auditoria/descargar?empresa_id=${selectedEmpresaId.value}&anio=${selectedAnio.value}&periodo=${selectedPeriodo.value}`;
    
    // Trigger download
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', computedNombreArchivo.value);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    setTimeout(() => {
        isDownloading.value = false;
        toast.success(`Archivo ${computedNombreArchivo.value} descargado`);
    }, 2000);
};

const copyFileName = async () => {
    try {
        await navigator.clipboard.writeText(computedNombreArchivo.value);
        toast.success('Nombre de archivo copiado al portapapeles');
    } catch {
        toast.error('No se pudo copiar el nombre');
    }
};
</script>

<template>
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <Head title="Exportar Salarios y Cuotas IMSS" />

        <!-- Header Section -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <FileSpreadsheet class="h-5 w-5" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Exportar Salarios y Cuotas IMSS
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Generación de libro de trabajo Excel (.xlsx) con cédulas completas de IMSS y CFDIS para auditoría fiscal y laboral.
                </p>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <Button variant="outline" as-child class="gap-2">
                    <Link href="/salarios">
                        <DollarSign class="h-4 w-4" />
                        Ver Salarios
                    </Link>
                </Button>
                <Button variant="secondary" as-child class="gap-2">
                    <Link href="/cuotas-imss">
                        <Receipt class="h-4 w-4" />
                        Ver Cuotas IMSS
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Main Card: Filter Selectors & Action -->
        <Card class="border shadow-2xs">
            <CardHeader class="border-b bg-muted/20 pb-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="text-lg font-semibold flex items-center gap-2">
                            <Layers class="h-5 w-5 text-primary" />
                            Parámetros de Exportación
                        </CardTitle>
                        <CardDescription>
                            Selecciona la empresa, el año fiscal y el periodo para compilar las hojas IMSS y CFDIS.
                        </CardDescription>
                    </div>

                    <!-- Nomenclature Badge -->
                    <div class="inline-flex items-center gap-2 rounded-lg border bg-background px-3 py-1.5 text-xs font-mono text-muted-foreground shadow-2xs">
                        <span class="font-semibold text-foreground">Nomenclatura:</span>
                        <span>Pruebas_{RFC}_{periodo}_{año}.xlsx</span>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="pt-6 space-y-6">
                <!-- Selectors Grid -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <!-- Empresa Selector -->
                    <div class="space-y-2">
                        <Label class="text-sm font-medium flex items-center gap-1.5 text-foreground">
                            <Building2 class="h-4 w-4 text-primary" />
                            Empresa
                        </Label>
                        <select
                            v-model="selectedEmpresaId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="emp in empresas" :key="emp.id" :value="emp.id">
                                {{ emp.nombre }} — {{ emp.rfc }}
                            </option>
                        </select>
                        <p v-if="selectedEmpresa" class="text-xs text-muted-foreground">
                            RFC Emisor: <span class="font-medium text-foreground">{{ selectedEmpresa.rfc }}</span>
                        </p>
                    </div>

                    <!-- Año Selector -->
                    <div class="space-y-2">
                        <Label class="text-sm font-medium flex items-center gap-1.5 text-foreground">
                            <Calendar class="h-4 w-4 text-primary" />
                            Año Fiscal
                        </Label>
                        <select
                            v-model="selectedAnio"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="yr in anios" :key="yr" :value="yr">
                                Ejercicio Fiscal {{ yr }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Ejercicio para auditoría de salarios y cuotas
                        </p>
                    </div>

                    <!-- Periodo Selector -->
                    <div class="space-y-2">
                        <Label class="text-sm font-medium flex items-center gap-1.5 text-foreground">
                            <Clock class="h-4 w-4 text-primary" />
                            Periodo / Mes
                        </Label>
                        <select
                            v-model="selectedPeriodo"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="m in mesesNombres" :key="m.periodo" :value="m.periodo">
                                {{ m.nombre }}
                            </option>
                        </select>
                        <p v-if="periodoRango" class="text-xs text-muted-foreground">
                            Rango: <span class="font-medium text-foreground">{{ periodoRango }}</span>
                        </p>
                    </div>
                </div>

                <!-- File Target Details & Previews -->
                <div class="rounded-xl border bg-muted/30 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
                        <div class="space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Archivo Generado
                            </span>
                            <div class="flex items-center gap-2">
                                <FileSpreadsheet class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                <span class="text-base font-mono font-bold text-foreground">
                                    {{ computedNombreArchivo }}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                    title="Copiar nombre de archivo"
                                    @click="copyFileName"
                                >
                                    <Copy class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Badge v-if="isLoadingResumen" variant="outline" class="gap-1.5 text-xs animate-pulse">
                                <RefreshCw class="h-3 w-3 animate-spin" />
                                Actualizando métricas...
                            </Badge>
                            <Badge v-else-if="totalCuotas > 0 || totalSalarios > 0" variant="secondary" class="gap-1.5 text-xs bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20">
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Datos disponibles para exportar
                            </Badge>
                            <Badge v-else variant="outline" class="gap-1.5 text-xs text-amber-600 dark:text-amber-400 border-amber-500/30 bg-amber-500/5">
                                <AlertCircle class="h-3.5 w-3.5" />
                                Sin registros en este periodo
                            </Badge>
                        </div>
                    </div>

                    <!-- Two Sheets Breakdown Cards -->
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- Sheet 1: IMSS -->
                        <div class="rounded-lg border bg-card p-4 shadow-2xs space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 font-bold text-xs">
                                        1
                                    </span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-foreground">
                                            Hoja 1: IMSS
                                        </h4>
                                        <p class="text-xs text-muted-foreground">
                                            Cédula de Cuotas Obrero-Patronales
                                        </p>
                                    </div>
                                </div>
                                <Badge variant="outline" class="text-xs">
                                    25 Columnas
                                </Badge>
                            </div>

                            <div class="mt-2 flex items-baseline justify-between border-t pt-2">
                                <span class="text-xs text-muted-foreground">Registros a exportar:</span>
                                <span class="text-lg font-bold text-foreground">
                                    {{ totalCuotas.toLocaleString('es-MX') }}
                                </span>
                            </div>

                            <p class="text-[11px] text-muted-foreground leading-relaxed">
                                Incluye: SDC, Días, Ausencias, Incapacidades, Cotizaciones, Cuota fija, SDMG, En dinero, Invalidez y vida, Pensionados, Riesgo, Guarderías, Total audit, Total Comp y Dif. a pagar.
                            </p>
                        </div>

                        <!-- Sheet 2: CFDIS -->
                        <div class="rounded-lg border bg-card p-4 shadow-2xs space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 font-bold text-xs">
                                        2
                                    </span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-foreground">
                                            Hoja 2: CFDIS
                                        </h4>
                                        <p class="text-xs text-muted-foreground">
                                            Cédula de Salarios y Acumulados CFDI
                                        </p>
                                    </div>
                                </div>
                                <Badge variant="outline" class="text-xs">
                                    19 Columnas
                                </Badge>
                            </div>

                            <div class="mt-2 flex items-baseline justify-between border-t pt-2">
                                <span class="text-xs text-muted-foreground">Registros a exportar:</span>
                                <span class="text-lg font-bold text-foreground">
                                    {{ totalSalarios.toLocaleString('es-MX') }}
                                </span>
                            </div>

                            <p class="text-[11px] text-muted-foreground leading-relaxed">
                                Incluye: Fecha inicio, Sueldo diario, Aguinaldo diario, Vacaciones, Prima vacacional, SDI fijo, SDI variable, SDI total, Tope SDI, SDI aud., IMSS y Diferencia.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Export Action Button -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pt-2">
                    <div class="text-xs text-muted-foreground flex items-center gap-1.5">
                        <ShieldCheck class="h-4 w-4 text-emerald-500" />
                        Cumple con la estructura oficial requerida para auditorías de cuotas obrero-patronales y timbrado CFDI.
                    </div>

                    <Button
                        size="lg"
                        class="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-md sm:w-auto w-full"
                        :disabled="isDownloading || !selectedEmpresaId"
                        @click="handleDownload"
                    >
                        <RefreshCw v-if="isDownloading" class="h-4 w-4 animate-spin" />
                        <Download v-else class="h-4 w-4" />
                        {{ isDownloading ? 'Generando archivo...' : 'Exportar a Excel (.xlsx)' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
