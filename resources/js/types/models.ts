export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

export interface Uma {
    id: number;
    anio: number;
    saldo: string | number;
    created_at: string;
    updated_at?: string;
}

export interface Categoria {
    id: number;
    nombre: string;
    vacaciones_count?: number;
    created_at: string;
    updated_at?: string;
}

export interface Vacacion {
    id: number;
    categoria_id: number;
    anios: number;
    dias: number;
    categoria?: Categoria;
    created_at: string;
    updated_at?: string;
}

export interface Empresa {
    id: number;
    nombre: string;
    rfc: string;
    razon_social: string;
    dias_aguinaldo: number;
    prima_vacacional: string | number;
    created_at: string;
    updated_at?: string;
}

export interface TableFilterParams {
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
    per_page?: number;
    categoria_id?: number | null;
}

export interface Empleado {
    id: number;
    empresa_id: number;
    categoria_id: number;
    registro_patronal: string | null;
    rfc_receptor: string;
    nombre: string;
    fecha_inicio_relacion_laboral: string | null;
    numero_empleado: string;
    antiguedad_texto: string | null;
    antiguedad_semanas: number;
    antiguedad_anios: number;
    es_sindicalizado: boolean;
    curp: string | null;
    numero_seguro_social: string | null;
    salario_base: string | number;
    salario_diario_integrado: string | number;
    empresa?: Empresa;
    categoria?: Categoria;
    created_at?: string;
}

export interface EmpleadoSalario {
    id: number;
    empleado_id: number;
    empresa_id: number;
    importacion_cfdi_id?: number | null;
    categoria_id: number;
    anio: number;
    periodo: string;
    tipo_nomina: string;
    salario_base: string | number;
    dias_vacaciones: number;
    importe_vacaciones: string | number;
    aguinaldo_diario: string | number;
    prima_vacacional: string | number;
    salario_diario_integrado: string | number;
    salario_diario_integrado_excel?: string | number | null;
    cantidad_dias?: string | number;
    importe_suma?: string | number;
    sdi_variable?: string | number;
    sdi_total?: string | number;
    sdi_auditado?: string | number;
    imss_cia?: string | number;
    diferencia?: string | number;
    limite_salario_diario: string | number;
    excede_limite: boolean;
    uma_utilizada: string | number;
    anio_uma: number;
    empleado?: Empleado;
    empresa?: Empresa;
    categoria?: Categoria;
    created_at: string;
    updated_at?: string;
}

export interface SalariosTotales {
    total_registros: number;
    promedio_salario_base: number;
    promedio_sdi: number;
    con_exceso_tope: number;
}

