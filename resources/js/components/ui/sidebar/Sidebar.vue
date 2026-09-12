<script setup lang="ts">
import type { SidebarProps } from '.'
import { cn } from '@/lib/utils'
import { Sheet, SheetContent } from '@/components/ui/sheet'
import SheetDescription from '@/components/ui/sheet/SheetDescription.vue'
import SheetHeader from '@/components/ui/sheet/SheetHeader.vue'
import SheetTitle from '@/components/ui/sheet/SheetTitle.vue'
import { SIDEBAR_WIDTH_MOBILE, useSidebar } from './utils'

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<SidebarProps>(), {
  side: 'left',
  variant: 'sidebar',
  collapsible: 'offcanvas',
})

const { isMobile, state, openMobile, setOpenMobile } = useSidebar()
</script>

<template>
  <div v-if="collapsible === 'none'" data-slot="sidebar"
    :class="cn('backdrop-blur-xl bg-white/60 dark:bg-zinc-950/60 border-r border-white/20 dark:border-white/10 shadow-[4px_0_30px_rgba(0,0,0,0.03)] text-sidebar-foreground flex h-full w-(--sidebar-width) flex-col transition-all duration-500 ease-out hover:shadow-[6px_0_40px_rgba(0,0,0,0.05)]', props.class)"
    v-bind="$attrs">
    <slot />
  </div>

  <Sheet v-else-if="isMobile" :open="openMobile" v-bind="$attrs" @update:open="setOpenMobile">
    <SheetContent data-sidebar="sidebar" data-slot="sidebar" data-mobile="true" :side="side"
      class="backdrop-blur-2xl bg-white/80 dark:bg-zinc-950/80 border-r border-white/30 dark:border-white/10 shadow-2xl text-sidebar-foreground w-(--sidebar-width) p-0 [&>button]:hidden"
      :style="{
        '--sidebar-width': SIDEBAR_WIDTH_MOBILE,
      }">
      <SheetHeader class="sr-only">
        <SheetTitle>Sidebar</SheetTitle>
        <SheetDescription>Displays the mobile sidebar.</SheetDescription>
      </SheetHeader>
      <div class="flex h-full w-full flex-col">
        <slot />
      </div>
    </SheetContent>
  </Sheet>

  <div v-else class="group peer text-sidebar-foreground hidden md:block" data-slot="sidebar" :data-state="state"
    :data-collapsible="state === 'collapsed' ? collapsible : ''" :data-variant="variant" :data-side="side">
    <!-- This is what handles the sidebar gap on desktop  -->
    <div :class="cn(
      'relative w-(--sidebar-width) bg-transparent transition-[width] duration-200 ease-linear',
      'group-data-[collapsible=offcanvas]:w-0',
      'group-data-[side=right]:rotate-180',
      variant === 'floating' || variant === 'inset'
        ? 'group-data-[collapsible=icon]:w-[calc(var(--sidebar-width-icon)+(--spacing(4)))]'
        : 'group-data-[collapsible=icon]:w-(--sidebar-width-icon)',
    )" />
    <div :class="cn(
      'fixed inset-y-0 z-10 hidden h-svh w-(--sidebar-width) transition-[left,right,width] duration-200 ease-linear md:flex',
      side === 'left'
        ? 'left-0 group-data-[collapsible=offcanvas]:left-[calc(var(--sidebar-width)*-1)]'
        : 'right-0 group-data-[collapsible=offcanvas]:right-[calc(var(--sidebar-width)*-1)]',
      // Adjust the padding for floating and inset variants.
      variant === 'floating' || variant === 'inset'
        ? 'p-2 group-data-[collapsible=icon]:w-[calc(var(--sidebar-width-icon)+(--spacing(4))+2px)]'
        : 'group-data-[collapsible=icon]:w-(--sidebar-width-icon) group-data-[side=left]:border-r group-data-[side=right]:border-l',
      props.class,
    )" v-bind="$attrs">
      <div data-sidebar="sidebar"
        class="backdrop-blur-xl bg-gradient-to-b from-white/70 to-white/40 dark:from-zinc-950/70 dark:to-zinc-950/40 border-r border-white/30 dark:border-white/10 shadow-[4px_0_30px_rgba(0,0,0,0.03)] hover:shadow-[8px_0_40px_rgba(0,0,0,0.06)] group-data-[variant=floating]:border-sidebar-border flex h-full w-full flex-col group-data-[variant=floating]:rounded-2xl group-data-[variant=floating]:border group-data-[variant=floating]:shadow-2xl transition-all duration-500 ease-out">
        <slot />
      </div>
    </div>
  </div>
</template>
