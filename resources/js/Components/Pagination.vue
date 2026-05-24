<template>
    <div v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-2 mt-6">
        <template v-for="(link, key) in links" :key="key">
            <!-- Disabled Link -->
            <div v-if="link.url === null" 
                 class="px-4 py-2 text-sm font-medium text-slate-400 dark:text-slate-600 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900/50 cursor-not-allowed" 
                 v-html="link.label">
            </div>
            
            <!-- Active/Inactive Link -->
            <Link v-else 
                  :href="link.url" 
                  preserve-scroll
                  preserve-state
                  class="px-4 py-2 text-sm font-bold border rounded-xl transition-all active:scale-95"
                  :class="link.active ? 'bg-primary text-white border-primary shadow-md hover:bg-primary-dark' : 'text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800/80'" 
                  v-html="link.label">
            </Link>
        </template>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>
