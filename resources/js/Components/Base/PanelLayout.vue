<script setup>
import { computed, inject } from 'vue'
import { InvueRegistryKey } from 'invue/core'
// Composes the *resolved* Sidebar/Topbar (the wrapper one directory up),
// not their Base implementations directly — so a registry swap of
// `panels.Sidebar`/`panels.Topbar` alone still applies here, same as
// invue/forms' KeyValue composing Repeater/TextInput via their wrappers.
import Sidebar from '../Sidebar.vue'
import Topbar from '../Topbar.vue'
import Breadcrumbs from '../Breadcrumbs.vue'

// Same reasoning as Topbar's `panels.topbarBell` — invue/panels can't
// composer-depend on invue/notifications, so it resolves the toast
// container through the registry instead of importing it. invue:install
// registers Notifications under 'panels.notificationsContainer' when
// invue/notifications is present; resolves to null (nothing rendered)
// otherwise. It's position: fixed, so mounting it once here — instead of
// per-page — is enough for every page under this layout to get toasts for
// free, including the store/update/destroy notifications
// make:invue-resource's generated Controller now sends by default.
const registry = inject(InvueRegistryKey, null)
const NotificationsContainer = computed(() => registry?.resolve('panels.notificationsContainer', null) ?? null)

defineProps({
    // { label, url? }[] — rendered into Topbar's #start slot. null/empty
    // (the default) renders nothing, e.g. on Index pages and custom Pages
    // that don't pass one. make:invue-resource's Create/Edit stubs pass
    // this by default, no manual wiring needed.
    breadcrumbs: {
        type: Array,
        default: null,
    },
})
</script>

<template>
    <div class="invue-panel flex min-h-screen bg-gray-50">
        <Sidebar />

        <div class="flex flex-1 flex-col">
            <!-- Brand lives in Sidebar, not here — see its `showBrand`
                 prop on Topbar for why. -->
            <Topbar :show-brand="false">
                <template v-if="breadcrumbs && breadcrumbs.length" #start>
                    <Breadcrumbs :items="breadcrumbs" />
                </template>

                <slot name="topbar" />
            </Topbar>

            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>

        <component :is="NotificationsContainer" v-if="NotificationsContainer" />
    </div>
</template>
