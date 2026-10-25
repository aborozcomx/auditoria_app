<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Coins,
    DollarSign,
    FileSpreadsheet,
    FolderGit2,
    Layers,
    LayoutGrid,
    Palmtree,
    Receipt,
    ReceiptText,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Empresas',
        href: '/empresas',
        icon: Building2,
    },
    {
        title: 'Salarios Empleados',
        icon: ReceiptText,
        subItems: [
            {
                title: 'Salarios Ordinarios',
                href: '/salarios',
                icon: DollarSign,
            },
            {
                title: 'Cuotas IMSS',
                href: '/cuotas-imss',
                icon: Receipt,
            },
            {
                title: 'Exportar Auditoría',
                href: '/exportar-auditoria',
                icon: FileSpreadsheet,
            },
        ],
    },
];

const catalogosNavItems: NavItem[] = [
    {
        title: 'UMAs',
        href: '/umas',
        icon: Coins,
    },
    {
        title: 'Categorías',
        href: '/categorias',
        icon: Layers,
    },
    {
        title: 'Vacaciones',
        href: '/vacaciones',
        icon: Palmtree,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Documentación Auditorías',
        href: '#',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="space-y-4">
            <NavMain :items="mainNavItems" label="Gestión" />
            <NavMain :items="catalogosNavItems" label="Catálogos Contables" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
