<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 font-sans relative overflow-hidden transition-colors duration-300">
        
        <!-- Subtle ambient radial mesh -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden">
            <div class="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-primary/10 dark:bg-primary/5 blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[50vw] h-[50vw] rounded-full bg-secondary/10 dark:bg-secondary/5 blur-[120px]"></div>
        </div>

        <div class="relative z-10 w-full max-w-md flex flex-col items-center">
            <div class="mb-8">
                <Link href="/" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-2xl p-1 inline-block">
                    <ApplicationLogo />
                </Link>
            </div>

            <div class="w-full bg-white/85 dark:bg-slate-900/80 backdrop-blur-2xl border border-white/80 dark:border-white/10 rounded-3xl p-8 shadow-xl shadow-slate-900/5 dark:shadow-black/40">
                <slot />
            </div>
        </div>
    </div>
</template>
