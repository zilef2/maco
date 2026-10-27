<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useMostVisited } from '@/composables/useMostVisited';
import { History } from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { top, visitar, limpiar } = useMostVisited();
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <Button variant="ghost" size="icon"
                        class="h-9 w-9 rounded-md text-muted-foreground hover:text-foreground cursor-pointer"
                        title="Rutas más visitadas" aria-label="Rutas más visitadas">
                        <History class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-72">
                    <DropdownMenuLabel>Más visitadas</DropdownMenuLabel>
                    <DropdownMenuSeparator v-if="top().length" />
                    <DropdownMenuItem v-for="ruta in top()" :key="ruta.path"
                        class="cursor-pointer items-center justify-between gap-2"
                        @click="visitar(ruta.path)">
                        <span class="truncate">{{ ruta.title }}</span>
                        <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ ruta.count }}×</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="!top().length" disabled>
                        Aún no hay rutas registradas.
                    </DropdownMenuItem>
                    <template v-if="top().length">
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="cursor-pointer text-destructive focus:text-destructive"
                            @click="limpiar()">
                            Borrar historial
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            <NotificationBell />
        </div>
    </header>
</template>
