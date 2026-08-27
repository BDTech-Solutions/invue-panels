<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Same reasoning as Base/Sidebar.vue's ACTIVE_ITEM_CLASSES/WIDTH_CLASSES —
// a color name resolved through a static map here, not an arbitrary
// string, since Tailwind only scans literal class text already present in
// vendor/invue/**/*.vue.
const ACCENT_BORDER_CLASSES = {
    gray: 'border-gray-200',
    red: 'border-red-300',
    green: 'border-green-300',
    blue: 'border-blue-300',
    yellow: 'border-yellow-300',
    amber: 'border-amber-300',
    sky: 'border-sky-300',
    rose: 'border-rose-300',
    purple: 'border-purple-300',
    pink: 'border-pink-300',
}

const BADGE_CLASSES = {
    gray: 'bg-gray-100 text-gray-600',
    red: 'bg-red-100 text-red-700',
    green: 'bg-green-100 text-green-700',
    blue: 'bg-blue-100 text-blue-700',
    yellow: 'bg-yellow-100 text-yellow-700',
    amber: 'bg-amber-100 text-amber-700',
    sky: 'bg-sky-100 text-sky-700',
    rose: 'bg-rose-100 text-rose-700',
    purple: 'bg-purple-100 text-purple-700',
    pink: 'bg-pink-100 text-pink-700',
}

const props = defineProps({
    // Overrides page.props.invuePanel.brandName/brandLogoUrl — lets a
    // Topbar render standalone, same reasoning as Sidebar's `items` prop.
    brandName: {
        type: String,
        default: null,
    },
    brandLogoUrl: {
        type: String,
        default: null,
    },
    // Small pill next to the brand name (e.g. "Admin", "Beta") — omitted
    // entirely when not set.
    badge: {
        type: String,
        default: null,
    },
    // One of the shared Invue color names (see Base/Sidebar.vue's
    // `selectedColor`) — tints the bottom border and, when set, the
    // `badge` pill. Defaults to the original plain gray look.
    color: {
        type: String,
        default: 'gray',
    },
})

const page = usePage()

const panel = computed(() => page.props.invuePanel ?? null)
const name = computed(() => props.brandName ?? panel.value?.brandName ?? 'Invue')
const logoUrl = computed(() => props.brandLogoUrl ?? panel.value?.brandLogoUrl ?? null)
</script>

<template>
    <header
        class="flex h-14 items-center gap-4 border-b bg-white px-6"
        :class="ACCENT_BORDER_CLASSES[color] ?? ACCENT_BORDER_CLASSES.gray"
    >
        <!-- #brand replaces the logo+name block entirely; the default
             here covers the common "just a name/logo/badge" case. -->
        <slot name="brand">
            <div class="flex shrink-0 items-center gap-2 text-base font-semibold text-gray-900">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="h-6 w-6 rounded" />
                <span>{{ name }}</span>
                <span
                    v-if="badge"
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="BADGE_CLASSES[color] ?? BADGE_CLASSES.gray"
                >
                    {{ badge }}
                </span>
            </div>
        </slot>

        <!-- #start: breadcrumbs, a page title, a search box, ... — sits
             between the brand and the (unnamed, right-aligned) actions
             slot, which stays exactly as before for existing consumers
             like PanelLayout's `<slot name="topbar" />`. -->
        <div class="flex flex-1 items-center gap-3">
            <slot name="start" />
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <slot />
        </div>
    </header>
</template>
