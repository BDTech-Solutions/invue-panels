<script setup>
import { Link } from '@inertiajs/vue3'

// { label: String, url?: String }[] — the last item is the current page:
// plain text, no link, matching the last item's non-interactive treatment
// on every other Invue trail-style UI (e.g. ActionsColumn's row actions).
// An item with no `url` anywhere in the list renders as plain text too, so
// a caller can mark an intermediate crumb non-clickable on purpose.
defineProps({
    items: {
        type: Array,
        required: true,
    },
})
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb" class="flex min-w-0 items-center text-sm">
        <template v-for="(item, index) in items" :key="index">
            <span v-if="index > 0" class="mx-1.5 shrink-0 text-gray-300">/</span>

            <Link
                v-if="item.url && index < items.length - 1"
                :href="item.url"
                class="shrink-0 text-gray-500 hover:text-gray-700"
            >
                {{ item.label }}
            </Link>
            <span v-else class="truncate font-medium text-gray-900">{{ item.label }}</span>
        </template>
    </nav>
</template>
