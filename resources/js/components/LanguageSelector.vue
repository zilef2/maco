<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router, usePage } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const currentLocale = computed(() => (page.props.locale as string) ?? 'es');

const setLocale = (locale: string) => {
    if (locale === currentLocale.value) return;
    router.post(route('locale.update', { locale }), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button variant="ghost" size="sm"
                class="h-9 gap-1.5 px-2.5 text-xs font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground cursor-pointer border border-sidebar-border/60 rounded-md">
                <span class="text-sm leading-none">🌐</span>
                <span>{{ currentLocale.toUpperCase() }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-36">
            <DropdownMenuItem class="flex items-center justify-between cursor-pointer" @click="setLocale('es')">
                <span>🇪🇸 Español</span>
                <Check v-if="currentLocale === 'es'" class="h-4 w-4 ml-2" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<!-- 
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="sm"
                class="h-9 gap-1.5 px-2.5 text-xs font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground cursor-pointer border border-sidebar-border/60 rounded-md"
            >
                <span class="text-sm leading-none">🌐</span>
                <span>{{ currentLocale.toUpperCase() }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-36">
            <DropdownMenuItem
                class="flex items-center justify-between cursor-pointer"
                @click="setLocale('es')"
            >
                <span>🇪🇸 Español</span>
                <Check v-if="currentLocale === 'es'" class="h-4 w-4 ml-2" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu> -->