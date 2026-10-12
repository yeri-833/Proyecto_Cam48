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
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 text-xs font-bold uppercase tracking-wider shadow-sm shadow-rose-600/20 active:scale-[0.975] transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none"
    >
        <slot >
    </button>
</template>
