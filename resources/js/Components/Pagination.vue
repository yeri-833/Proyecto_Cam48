<template>
    <nav
        v-if="links && links.length > 3"
        class="flex flex-wrap items-center justify-center gap-1.5 py-4"
        aria-label="Paginación"
    >
        <template v-for="(link, key) in links" :key="key">
            <!-- Disabled Link -->
            <span
                v-if="link.url === null"
                class="px-3.5 py-2 text-xs font-semibold text-slate-400 dark:text-slate-600 rounded-xl bg-slate-100/60 dark:bg-slate-800/40 cursor-not-allowed select-none"
                v-html="link.label"
            ></span>

            <!-- Active/Inactive Link -->
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                preserve-state
                :class="[
                    'px-3.5 py-2 text-xs font-bold rounded-xl transition-all duration-150 select-none flex items-center justify-center',
                    link.active
                        ? 'bg-primary text-white shadow-sm shadow-primary/30'
                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white active:scale-95'
                ]"
                :aria-current="link.active ? 'page' : null"
                :aria-label="getAriaLabel(link.label)"
                v-html="link.label"
            >
            </Link>
        </template>
    </nav>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    links: {
        type: Array,
        required: true,
    },
});

const getAriaLabel = (label) => {
    if (label.includes('Next')) return 'Ir a la página siguiente';
    if (label.includes('Previous')) return 'Ir a la página anterior';
    return `Ir a la página ${label}`;
};
</script>
