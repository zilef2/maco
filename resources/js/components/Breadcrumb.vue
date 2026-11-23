<script setup lang="ts">
import { ChevronRightIcon } from '@heroicons/vue/24/solid';
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/solid';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
defineProps({
    title: String,
    breadcrumbs: Object
})

const collapsed = ref(false)

</script>

<template>
    <div class="flex items-center justify-between py-4 px-4 sm:px-0 text-gray-500 dark:text-gray-300">
        <div v-if="!collapsed" class="flex items-center gap-4">
            <p>{{ title }}</p>
            <div class="hidden sm:flex space-x-2 items-center">
                <Link :href="route('dashboard')" v-show="breadcrumbs?.length != 0">
                Dashboard
                </Link>
                <div v-for="(breadcrumb, index) in breadcrumbs" :key="index" class="flex items-center space-x-2">
                    <ChevronRightIcon class="w-3 h-3" />
                    <Link :href="breadcrumb.href">
                    {{ breadcrumb.label }}
                    </Link>
                </div>
            </div>
        </div>
        <button
            @click="collapsed = !collapsed"
            class="ml-2 p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            :aria-label="collapsed ? 'Mostrar título y breadcrumb' : 'Ocultar título y breadcrumb'"
        >
            <ChevronDownIcon v-if="collapsed" class="w-4 h-4" />
            <ChevronUpIcon v-else class="w-4 h-4" />
        </button>
    </div>
</template>
