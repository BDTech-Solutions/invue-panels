<script setup>
// Composes the *resolved* Sidebar/Topbar (the wrapper one directory up),
// not their Base implementations directly — so a registry swap of
// `panels.Sidebar`/`panels.Topbar` alone still applies here, same as
// invue/forms' KeyValue composing Repeater/TextInput via their wrappers.
import Sidebar from '../Sidebar.vue'
import Topbar from '../Topbar.vue'
</script>

<template>
    <div class="invue-panel flex min-h-screen bg-gray-50">
        <Sidebar />

        <div class="flex flex-1 flex-col">
            <!-- Brand lives in Sidebar, not here — see its `showBrand`
                 prop on Topbar for why. -->
            <Topbar :show-brand="false">
                <slot name="topbar" />
            </Topbar>

            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
