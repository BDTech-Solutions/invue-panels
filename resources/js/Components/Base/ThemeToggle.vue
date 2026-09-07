<script setup>
import { Icon, useAppearance } from 'invue/core'

const { isDark, toggle } = useAppearance()
</script>

<template>
    <button
        type="button"
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        :title="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        @click="toggle"
    >
        <Icon :name="isDark ? 'sun' : 'moon'" class="h-4 w-4" />
    </button>
</template>
