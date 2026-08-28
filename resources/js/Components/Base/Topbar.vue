<script setup>
import { computed, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { InvueRegistryKey } from 'invue/core'

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
    // PanelLayout sets this to false — the brand now lives in Sidebar (see
    // Base/Sidebar.vue), so the default panel composition doesn't show it
    // twice. Still true by default so Topbar used on its own (no Sidebar
    // alongside it) keeps working exactly as documented.
    showBrand: {
        type: Boolean,
        default: true,
    },
    // Overrides '/logout' — the path invue:install's generated auth routes
    // register by default (POST, name 'logout'). Only relevant when a real
    // `auth.user` is shared (see `user` below); no route, no menu item.
    logoutUrl: {
        type: String,
        default: '/logout',
    },
})

const page = usePage()

const panel = computed(() => page.props.invuePanel ?? null)
// import.meta.env.VITE_APP_NAME, not a hardcoded 'Invue' — every Laravel
// .env ships APP_NAME/VITE_APP_NAME already, so a fresh install shows the
// app's own name by default instead of the framework's, the same
// impression a fresh Filament install gives (see Panel::getBrandName()
// for the equivalent server-side default, used once a real Panel backs
// this route).
const name = computed(() => props.brandName ?? panel.value?.brandName ?? import.meta.env.VITE_APP_NAME ?? 'Laravel')
const logoUrl = computed(() => props.brandLogoUrl ?? panel.value?.brandLogoUrl ?? null)

// Breeze/Jetstream's own convention (HandleInertiaRequests::share()
// returning 'auth' => ['user' => ...]) — read directly if the app already
// shares it that way, no invue/panels-specific wiring needed on top.
const user = computed(() => page.props.auth?.user ?? null)
const initials = computed(() => {
    const source = (user.value?.name || user.value?.email || '').trim()

    if (! source) {
        return null
    }

    const parts = source.split(/\s+/).filter(Boolean)

    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : source.slice(0, 2).toUpperCase()
})

// invue/panels can't composer-depend on invue/notifications (wrong
// direction — see the parent skill's "runtime capability detection"
// mechanism), so it can't import Bell.vue directly either without breaking
// the build for anyone who only installed panels. invue:install registers
// it here instead (registry.register('panels.topbarBell', Bell)) when
// invue/notifications is present, so the bell still shows up with zero
// per-page wiring — resolving to null (nothing rendered) otherwise.
const registry = inject(InvueRegistryKey, null)
const TopbarBell = computed(() => registry?.resolve('panels.topbarBell', null) ?? null)

function logout() {
    router.post(props.logoutUrl)
}
</script>

<template>
    <header
        class="flex h-14 items-center gap-4 border-b bg-white px-6"
        :class="ACCENT_BORDER_CLASSES[color] ?? ACCENT_BORDER_CLASSES.gray"
    >
        <!-- #brand replaces the logo+name block entirely; the default
             here covers the common "just a name/logo/badge" case.
             show-brand="false" (PanelLayout's default) skips this whole
             block, brand and all — see the `showBrand` prop above. -->
        <slot v-if="showBrand" name="brand">
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

            <component :is="TopbarBell" v-if="TopbarBell" />

            <!-- Native <details>/<summary> disclosure, same interaction
                 pattern as invue/notifications' Bell.vue — no extra JS
                 state needed for a dropdown this simple. -->
            <details v-if="user" class="relative">
                <summary
                    class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-full bg-gray-800 text-xs font-semibold text-white select-none"
                    :title="user.name ?? user.email"
                >
                    {{ initials }}
                </summary>

                <div class="absolute right-0 z-10 mt-1 w-48 overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg">
                    <div class="border-b border-gray-100 px-3 py-2">
                        <p class="truncate text-sm font-medium text-gray-900">{{ user.name ?? user.email }}</p>
                        <p v-if="user.name" class="truncate text-xs text-gray-500">{{ user.email }}</p>
                    </div>

                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50"
                        @click="logout"
                    >
                        Log out
                    </button>
                </div>
            </details>
        </div>
    </header>
</template>
