<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, Menu } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    groups: NavGroup[];
    dropdown?: NavGroup[];
}>();

const page = usePage<SharedData>();

const isItemActive = (href: string): boolean => {
    if (!href || !page.url) return false;
    const currentPath = page.url.split('?')[0];
    return currentPath === href || (href !== '/' && currentPath.startsWith(href));
};

const dropdownHasActive = computed(() =>
    (props.dropdown ?? []).some((group) => group.items.some((item) => isItemActive(item.href)))
);
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

    <!-- Menú desplegable con el resto de links -->
    <SidebarGroup v-if="dropdown && dropdown.length" class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem>
                <Collapsible v-slot="{ open }" class="group/collapsible">
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :tooltip="'Más módulos'"
                            :class="[
                                dropdownHasActive
                                    ? '!bg-sky-200 !text-sky-950 font-semibold'
                                    : 'text-gray-800'
                            ]"
                        >
                            <Menu />
                            <span>Más módulos</span>
                            <ChevronRight
                                class="ml-auto h-4 w-4 transition-transform duration-200"
                                :class="open ? 'rotate-90' : ''"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div v-for="(cat, catIndex) in dropdown" :key="cat.label"
                            :class="catIndex === 0 ? 'mt-1' : 'mt-3'">
                            <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-data-[collapsible=icon]:hidden">
                                {{ cat.label }}
                            </p>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="(item, index) in cat.items" :key="item.title">
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isItemActive(item.href)"
                                        :class="isItemActive(item.href)
                                            ? '!bg-sky-200 !text-sky-950 font-semibold'
                                            : 'text-gray-700 dark:text-gray-300'"
                                    >
                                        <Link :href="item.href">
                                            <component :is="item.icon" />
                                            <span>{{ index + 1 }}. {{ item.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
