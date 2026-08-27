<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    // A count pill next to the title (e.g. how many related records) —
    // omitted entirely when left null, not forced to 0.
    count: {
        type: Number,
        default: null,
    },
})
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ title }}</h3>
                <span
                    v-if="count !== null"
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500"
                >
                    {{ count }}
                </span>
            </div>

            <!-- Typically a small inline "add" form built from invue/forms
                 fields + a submit button — RelationManager doesn't know or
                 care what creating a related record needs, same "compose
                 from existing pieces, no PHP UI builder" rule as
                 everywhere else in Invue. -->
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <!-- Typically a <Table> from invue/tables, scoped server-side via
             TableQuery::for($parent->relation()). -->
        <div class="p-4">
            <slot />
        </div>
    </div>
</template>
