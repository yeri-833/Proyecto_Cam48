<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-xs font-bold text-red-500">
            {{ message }}
        </p>
    </div>
</template>
