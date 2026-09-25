<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import Toast from '@/components/Toast.vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <Toast :flash="$page.props.flash" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <slot />
    </AppLayout>
</template>
