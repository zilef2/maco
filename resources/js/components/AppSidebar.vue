<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    Folder,
    LayoutDashboard,
    Users,
    LayoutGrid,
    ShoppingBag,
    ClipboardList,
    ArrowLeftRight,
    PackageOpen,
    BadgeCheck,
    CheckCircle2,
    Activity,
    BookText,
    AlertCircle,
    Boxes,
    List,
    FileUp,
} from 'lucide-vue-next';
import AppLogoMini from './AppLogoMini.vue';

const navGroups: NavGroup[] = [
    {
        label: 'Inventario',
        items: [
            {
                title: 'Material',
                href: '/Material',
                icon: Boxes,
            },
            {
                title: 'Producto',
                href: '/Producto',
                icon: ShoppingBag,
            },
        ],
    },
    {
        label: 'Orden Producción',
        items: [
            {
                title: 'Dashboard',
                href: '/dashboard',
                icon: LayoutDashboard,
            },
            {
                title: 'OrdenProduccion',
                href: '/OrdenProduccion',
                icon: ClipboardList,
            },
        ],
    },
];

const navDropdown: NavGroup[] = [
    {
        label: 'Operación',
        items: [
            {
                title: 'EstadoOperacion',
                href: '/EstadoOperacion',
                icon: Activity,
            },
            {
                title: 'RecepcionCalidad',
                href: '/RecepcionCalidad',
                icon: BadgeCheck,
            },
            {
                title: 'PendientesProduccion',
                href: '/PendientesProduccion',
                icon: AlertCircle,
            },
            {
                title: 'CierreOrdenProduccion',
                href: '/CierreOrdenProduccion',
                icon: CheckCircle2,
            },
            {
                title: 'Distribucion por tallas',
                href: '/Distribucion_por_tallas',
                icon: List,
            },
        ],
    },
    {
        label: 'Maestros',
        items: [
            {
                title: 'Taller',
                href: '/Taller',
                icon: LayoutGrid,
            },
            {
                title: 'KitTaller',
                href: '/KitTaller',
                icon: PackageOpen,
            },
            {
                title: 'ProcesoExterno',
                href: '/ProcesoExterno',
                icon: ArrowLeftRight,
            },
            {
                title: 'GrupoTalla',
                href: '/GrupoTalla',
                icon: BookText,
            },
            {
                title: 'FichaTecnicaMateriales',
                href: '/FichaTecnicaMateriales',
                icon: BookText,
            },
            {
                title: 'Proveedor',
                href: '/Proveedor',
                icon: List,
            },
            //aquipuesSide
        ],
    },
    {
        label: 'Sistema',
        items: [
            {
                title: 'Usuarios',
                href: '/user',
                icon: Users,
            },
            {
                title: 'Importar Excel',
                href: '/ImportarExcel',
                icon: FileUp,
            },
        ],
    },
];

const footerAdminItems = [
    {
        title: 'Desarrollo',
        href: '/desarrollo',
        icon: Folder,
    },
];
const footerNavItems = [
    {
        title: 'Página de la empresa',
        href: 'https://macondas.com/',
        icon: Building2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogoMini />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" :dropdown="navDropdown" />
        </SidebarContent>

        <SidebarFooter class="hidden md:flex" v-if="can(['isadmin'])">
            <NavFooter :items="footerAdminItems" />
        </SidebarFooter>
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
