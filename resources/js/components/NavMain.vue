<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    groups: NavGroup[];
    dropdown?: NavGroup[];
}>();

const page = usePage<SharedData>();

const isItemActive = (href: string): boolean => {
    if (!href || !page.url) return false;
    const currentPath = page.url.split('?')[0];
    return currentPath === href || (href !== '/' && currentPath.startsWith(href));
};

// texto largo (16+ caracteres) va 2px más pequeño: text-xs (12px) vs text-sm (14px)
const tamanoTexto = (titulo: string): string => (titulo.length > 15 ? 'text-xs' : 'text-sm');

const categoriaActiva = (cat: NavGroup): boolean =>
    cat.items.some((item) => isItemActive(item.href));
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="(item, index) in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isItemActive(item.href)"
                    :class="[
                        isItemActive(item.href)
                            ? '!bg-sky-200 !text-sky-950 font-semibold'
                            : (index % 10 === 0 ? 'bg-gray-100 text-gray-700' : 'text-gray-800'),
                        tamanoTexto(item.title),
                    ]"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ index + 1 }}. {{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>

    <hr class="my-4 border-gray-400 dark:border-gray-700" />


    <!-- Categorías desplegables: cada una es un botón expandible -->
    <SidebarGroup v-for="cat in dropdown" :key="cat.label" class="px-2 py-0">
        <Collapsible v-slot="{ open }" class="group/collapsible" :default-open="categoriaActiva(cat)">
            <SidebarMenu>
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :tooltip="cat.label"
                            :class="categoriaActiva(cat)
                                ? '!bg-sky-200 !text-sky-950 font-semibold'
                                : 'text-gray-800'"
                        >
                            <component v-if="cat.icon" :is="cat.icon" />
                            <span>{{ cat.label }}</span>
                            <ChevronRight
                                class="ml-auto h-4 w-4 transition-transform duration-200"
                                :class="open ? 'rotate-90' : ''"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem v-for="(item, index) in cat.items" :key="item.title">
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isItemActive(item.href)"
                                    :class="[
                                        isItemActive(item.href)
                                            ? '!bg-sky-200 !text-sky-950 font-semibold'
                                            : 'text-gray-700 dark:text-gray-300',
                                        tamanoTexto(item.title),
                                    ]"
                                >
                                    <Link :href="item.href">
                                        <component :is="item.icon" />
                                        <span>{{ index + 1 }}. {{ item.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </SidebarMenu>
        </Collapsible>
    </SidebarGroup>
</template>
