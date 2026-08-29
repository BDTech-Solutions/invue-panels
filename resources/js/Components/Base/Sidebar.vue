<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Icon } from 'invue/core'

// Per-component inline color/width-class maps, same shape as
// invue/notifications' Toast.vue (CARD_COLOR_CLASSES/ICON_COLOR_CLASSES) —
// Tailwind only scans vendor/invue/**/*.vue for literal class strings (see
// invue/core's tailwind.content.js + the parent skill's "Tailwind
// content-scanning gotcha"), so an arbitrary color/width string passed
// through a prop can never resolve to a real class — only a name looked up
// against a map whose values are already static text in this file can.
const ACTIVE_ITEM_CLASSES = {
    gray: 'bg-gray-100 text-gray-900',
    red: 'bg-red-50 text-red-700',
    green: 'bg-green-50 text-green-700',
    blue: 'bg-blue-50 text-blue-700',
    yellow: 'bg-yellow-50 text-yellow-700',
    amber: 'bg-amber-50 text-amber-700',
    sky: 'bg-sky-50 text-sky-700',
    rose: 'bg-rose-50 text-rose-700',
    purple: 'bg-purple-50 text-purple-700',
    pink: 'bg-pink-50 text-pink-700',
}

const WIDTH_CLASSES = {
    sm: 'w-48',
    md: 'w-56',
    lg: 'w-64',
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

const INACTIVE_ITEM_CLASSES = 'text-gray-600 hover:bg-gray-50'

const props = defineProps({
    // Overrides page.props.invuePanel.navigation — lets a Sidebar be used
    // (previewed, tested, reused outside a Panel) without an Inertia page
    // actually sharing invuePanel.
    items: {
        type: Array,
        default: null,
    },
    // One of the shared Invue color names (see invue/notifications' Toast
    // `color` prop) — the active nav item's background/text color.
    selectedColor: {
        type: String,
        default: 'green',
    },
    width: {
        type: String,
        default: 'md',
    },
    // Overrides page.props.invuePanel.brandName/brandLogoUrl — same
    // standalone-mode reasoning as `items`. The brand lives here, not in
    // Topbar (PanelLayout renders Topbar with :show-brand="false") — matches
    // Filament's own layout, where the logo/app name pins to the sidebar.
    brandName: {
        type: String,
        default: null,
    },
    brandLogoUrl: {
        type: String,
        default: null,
    },
})

const page = usePage()

const navigation = computed(() => props.items ?? page.props.invuePanel?.navigation ?? [])

const panel = computed(() => page.props.invuePanel ?? null)
// import.meta.env.VITE_APP_NAME, not a hardcoded string — see Topbar.vue's
// identical fallback chain for why (every Laravel .env already has this).
const brandName = computed(() => props.brandName ?? panel.value?.brandName ?? import.meta.env.VITE_APP_NAME ?? 'Laravel')
const brandLogoUrl = computed(() => props.brandLogoUrl ?? panel.value?.brandLogoUrl ?? null)

// Groups items by their (optional) `group` field, preserving first-seen
// order — an item with no group renders flat, exactly like before this
// existed, so existing single-group navigations are unaffected.
const groupedNavigation = computed(() => {
    const buckets = []
    const byGroup = new Map()

    for (const item of navigation.value) {
        const key = item.group ?? null

        if (!byGroup.has(key)) {
            const bucket = { group: key, items: [] }
            byGroup.set(key, bucket)
            buckets.push(bucket)
        }

        byGroup.get(key).items.push(item)
    }

    return buckets
})

// Picks the single BEST (longest) matching nav url for the current path,
// not "every item whose url happens to be a prefix" — the Dashboard's own
// url is the panel root (e.g. '/admin'), which is a prefix of every other
// item's url in the same panel, so a naive startsWith() marked Dashboard
// active on every resource page too. Only the most specific match wins.
const activeUrl = computed(() => {
    const current = page.props.invuePanel?.current ?? (typeof window !== 'undefined' ? window.location.pathname : '')

    let best = null

    for (const item of navigation.value) {
        if (current === item.url || current.startsWith(`${item.url}/`)) {
            if (best === null || item.url.length > best.length) {
                best = item.url
            }
        }
    }

    return best
})

function isActive(url) {
    return url === activeUrl.value
}
</script>

<template>
    <aside
        class="invue-sidebar flex shrink-0 flex-col border-r border-gray-200 bg-white"
        :class="WIDTH_CLASSES[width] ?? WIDTH_CLASSES.md"
    >
        <div class="flex h-14 shrink-0 items-center gap-2 border-b border-gray-200 px-4 text-base font-semibold text-gray-900">
            <slot name="brand">
                <img v-if="brandLogoUrl" :src="brandLogoUrl" alt="" class="h-6 w-6 rounded" />
                <span class="truncate">{{ brandName }}</span>
            </slot>
        </div>

        <div v-if="$slots.header" class="border-b border-gray-200 px-4 py-3">
            <slot name="header" />
        </div>

        <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-4">
            <template v-for="bucket in groupedNavigation" :key="bucket.group ?? '__ungrouped'">
                <p
                    v-if="bucket.group"
                    class="mt-4 mb-1 px-3 text-xs font-semibold tracking-wide text-gray-400 uppercase first:mt-0"
                >
                    {{ bucket.group }}
                </p>

                <template v-for="item in bucket.items" :key="item.url">
                    <!-- Default row markup renders when the caller doesn't
                         fill #item — a scoped slot so a store variant can
                         restyle or enrich a row (nesting, a different badge
                         treatment, ...) without forking the whole component
                         just for that. -->
                    <slot name="item" :item="item" :active="isActive(item.url)">
                        <Link
                            :href="item.url"
                            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                            :class="isActive(item.url) ? (ACTIVE_ITEM_CLASSES[selectedColor] ?? ACTIVE_ITEM_CLASSES.green) : INACTIVE_ITEM_CLASSES"
                        >
                            <Icon v-if="item.icon" :name="item.icon" class="h-4 w-4 shrink-0" />
                            <span class="flex-1">{{ item.label }}</span>
                            <span
                                v-if="item.badge"
                                class="rounded-full px-1.5 py-0.5 text-xs font-semibold"
                                :class="BADGE_CLASSES[item.badgeColor] ?? BADGE_CLASSES.gray"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </slot>
                </template>
            </template>
        </nav>

        <div v-if="$slots.footer" class="border-t border-gray-200 px-2 py-3">
            <slot name="footer" />
        </div>
    </aside>
</template>
