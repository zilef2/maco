<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Building2,
    Activity,
    Folder,
    LayoutDashboard,
    LayoutGrid,
    Users,
    Boxes,
    Shirt,
    ClipboardList,
    ArrowLeftRight,
    PackageCheck,
    CheckCircle2,
    Factory,
    FileSpreadsheet,
    Database,
    Tag,
    Truck,
    Clock,
    FileUp,
    BookOpen,
    ShieldCheck,
    Layers,
    Settings,
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
                icon: Shirt,
            },
        ],
    },
    {
        label: 'Orden Producción',
        items: [
            {
                title: 'OrdenProduccion',
                href: '/OrdenProduccion',
                icon: ClipboardList,
            },
        ],
    },
];

const page = usePage();
const esSuperadmin = computed(() => Boolean((page.props as any)?.auth?.can?.['issuperadmin']));

const navDropdown = computed<NavGroup[]>(() => {
    const items: NavGroup[] = [
        {
            label: 'Terceros',
            icon: Building2,
            items: [
                {
                    title: 'Proveedores y terceros',
                    href: '/Proveedor',
                    icon: Truck,
                },
            ],
        },
        {
            label: 'Fichas y Procesos',
            icon: Layers,
            items: [
                {
                    title: 'Ficha Producto',
                    href: '/FichaTecnicaMateriales',
                    icon: FileSpreadsheet,
                },
                {
                    title: 'Proceso',
                    href: '/Procesoop',
                    icon: LayoutGrid,
                },
                // {
                //     title: 'FichaEnsamble',
                //     href: '/FichaEnsamble',
                //     icon: FileSpreadsheet,
                // },
                // {
                //     title: 'FichaEnsambleMaterial',
                //     href: '/FichaEnsambleMaterial',
                //     icon: FileSpreadsheet,
                // },
                // {
                //     title: 'KitProceso',
                //     href: '/KitProceso',
                //     icon: PackageCheck,
                // },
                {
                    title: 'Compromiso Material',
                    href: '/Compromisomaterial',
                    icon: LayoutGrid,
                },
                //aquipuesSide

            ],
        },
        // {
        //     label: 'Mantenimiento',
        //     icon: Layers,
        //     items: [

        //aquipuesSide

        //     ],
        // },

        // {
        //     label: 'Listas',
        //     icon: Database,
        //     items: [


        //         // {
        //         //     title: 'GrupoTalla',
        //         //     href: '/GrupoTalla',
        //         //     icon: Tag,
        //         // },


        //         // {
        //         //     title: 'EstadoOpMaterial',
        //         //     href: '/EstadoOpMaterial',
        //         //     icon: LayoutGrid,
        //         // },


        //     ],
        // },
        {
            label: 'PosProducción',
            icon: Factory,
            items: [
                // {
                //     title: 'ProcesoExterno',
                //     href: '/ProcesoExterno',
                //     icon: ArrowLeftRight,
                // },
                {
                    title: 'RecepcionCalidad',
                    href: '/RecepcionCalidad',
                    icon: ShieldCheck,
                },
                // {
                //     title: 'CierreOrdenProduccion',
                //     href: '/CierreOrdenProduccion',
                //     icon: CheckCircle2,
                // },
                {
                    title: 'Merma',
                    href: '/Merma',
                    icon: LayoutGrid,
                },

            ],
        },
        {
            label: 'Personas',
            icon: Users,
            items: [
                {
                    title: 'Usuarios',
                    href: '/user',
                    icon: Users,
                },
                {
                    title: 'Proveedor',
                    href: '/Proveedor',
                    icon: Truck,
                },
            ],
        },
    ];

    if (esSuperadmin.value) {
        items.push({
            label: 'Sistema',
            icon: Settings,
            items: [
                {
                    title: 'Importar Excel',
                    href: '/ImportarExcel',
                    icon: FileUp,
                },
            ],
        });
    }

    return items;
});

// const footerAdminItems = [
//     ];
const footerSuperItems = [
    {
        title: 'Desarrollo',
        href: '/desarrollo',
        icon: Folder,
    },
    {
        title: 'Actividad',
        href: '/Actividad',
        icon: Activity,
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

        <!-- <SidebarFooter class="hidden md:flex" v-if="can(['isadmin'])">
            <NavFooter :items="footerAdminItems" />
        </SidebarFooter> -->
        <SidebarFooter class="hidden md:flex" v-if="can(['issuperadmin'])">
            <NavFooter :items="footerSuperItems" />
        </SidebarFooter>
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
