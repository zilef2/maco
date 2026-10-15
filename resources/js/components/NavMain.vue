<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    groups: NavGroup[];
}>();

const page = usePage<SharedData>();

const isItemActive = (href: string): boolean => {
    if (!href || !page.url) return false;
    const currentPath = page.url.split('?')[0];
    return currentPath === href || (href !== '/' && currentPath.startsWith(href));
};
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
                            : (index % 10 === 0 ? 'bg-gray-100 text-gray-700' : 'text-gray-800')
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
</template>
