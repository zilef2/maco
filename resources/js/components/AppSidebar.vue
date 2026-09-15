<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Building2, BookOpen, Folder, LayoutGrid } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Presupuesto',
        href: '/Presupuesto',
        icon: LayoutGrid,
    },
    {
        title: 'Desarrollo',
        href: '/desarrollo',
        icon: Folder,
    },
    //aquipuesSide
];

const SoloSuperNavItems: NavItem[] = [

]
const footerSuperNavItems: NavItem[] = [
    {
        title: 'Roles',
        href: '/role',
        icon: Folder,
    },
    {
        title: 'Permissions',
        href: '/permission',
        icon: Folder,
    },
    {
        title: 'Parametros',
        href: '/Parametros',
        icon: BookOpen,
    },
];
const footerNavItems: NavItem[] = [
    {
        title: 'Empresa',
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
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter v-if="can(['issuperadmin'])">
            <NavFooter :items="SoloSuperNavItems" />
        </SidebarFooter>
        <SidebarFooter>
            <NavFooter :items="footerSuperNavItems" />
        </SidebarFooter>
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
