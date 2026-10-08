<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
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
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import AppLogoMini from './AppLogoMini.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    // {
    //     title: 'Presupuesto',
    //     href: '/Presupuesto',
    //     icon: LayoutDashboard,
    // },
    {
        title: 'Usuarios',
        href: '/user',
        icon: Users,
    },
    // {
    //     title: 'Materias primas',
    //     href: '/materias_primas',
    //     icon: Boxes,
    // },
    {
        title: 'Producto',
        href: '/Producto',
        icon: ShoppingBag,
    },
    // {
    // 	title: 'Talla',
    // 	href: '/Talla',
    // 	icon: Ruler,
    // },
    {
        title: 'OrdenProduccion',
        href: '/OrdenProduccion',
        icon: ClipboardList,
    },
    {
        title: 'ProcesoExterno',
        href: '/ProcesoExterno',
        icon: ArrowLeftRight,
    },
    {
        title: 'KitTaller',
        href: '/KitTaller',
        icon: PackageOpen,
    },
    {
        title: 'RecepcionCalidad',
        href: '/RecepcionCalidad',
        icon: BadgeCheck,
    },
    {
        title: 'CierreOrdenProduccion',
        href: '/CierreOrdenProduccion',
        icon: CheckCircle2,
    },
    {
        title: 'EstadoOperacion',
        href: '/EstadoOperacion',
        icon: Activity,
    },
    {
        title: 'FichaTecnicaMateriales',
        href: '/FichaTecnicaMateriales',
        icon: BookText,
    },
    {
        title: 'PendientesProduccion',
        href: '/PendientesProduccion',
        icon: AlertCircle,
    },
    {
		title: 'Taller',
		href: '/Taller',
		icon: LayoutGrid,
	},
	//aquipuesSide
];

const footerAdminItems: NavItem[] = [
    {
        title: 'Desarrollo',
        href: '/desarrollo',
        icon: Folder,
    },
];
const footerNavItems: NavItem[] = [
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter  class="hidden md:flex" v-if="can(['isadmin'])">
            <NavFooter :items="footerAdminItems" />
        </SidebarFooter>
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
