<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-xl bg-slate-100 dark:bg-slate-700/50 px-6 py-3 text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 shadow-sm transition-all hover:bg-slate-200 dark:hover:bg-slate-700 hover:-translate-y-0.5 focus:outline-none disabled:opacity-25"
    >
        <slot />
    </button>
</template>
