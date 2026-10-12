<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { ref, onMounted } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const isPasswordVisible = ref(false);
const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark') || 
                   localStorage.getItem('theme') === 'dark' || 
                   (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
});

const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Acceso al Sistema — FamilyLock CAM #48" />

    <div class="min-h-screen bg-slate-50 dark:bg-[#0b0f19] flex flex-col md:flex-row font-sans selection:bg-primary/20 selection:text-primary transition-colors duration-300">
        
        <!-- Left Side: Institutional Showcase (Apple Keynote Style) -->
        <div class="hidden md:flex md:w-5/12 lg:w-1/2 bg-[#0c1322] text-white p-10 lg:p-14 flex-col justify-between relative overflow-hidden border-r border-slate-800/80">
            <!-- Subtle Ambient Light Orbs -->
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-sky-500/10 rounded-full blur-[100px] pointer-events-none"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-purple-500/10 rounded-full blur-[100px] pointer-events-none"></div>

            <!-- Top Header Badge -->
            <div class="relative z-10">
                <div class="inline-flex items-center bg-white/[0.08] backdrop-blur-xl px-5 py-3 rounded-2xl border border-white/10 shadow-sm">
                    <ApplicationLogo variant="horizontal" force-theme="dark" size="h-12 lg:h-14" alt="CAM Tonalpouhqui #48" />
                </div>
            </div>

            <!-- Hero Value Proposition -->
            <div class="relative z-10 max-w-lg my-auto py-8">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-sky-400/10 border border-sky-400/20 text-sky-300 text-xs font-semibold mb-6">
                    <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                    Plataforma Institucional Oficial
                </div>

                <h1 class="font-display font-black text-4xl lg:text-5xl text-white tracking-tight leading-[1.1] mb-5">
                    Gestión Escolar <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 via-blue-200 to-white">
                        Confiable y Precisa
                    </span>
                </h1>

                <p class="text-slate-300 text-base leading-relaxed mb-8 font-normal max-w-md">
                    Control centralizado de asistencias QR en tiempo real, expedientes estudiantiles y comunicación escolar automatizada.
                </p>

                <!-- Key Feature Chips -->
                <div class="grid grid-cols-2 gap-3.5 max-w-md">
                    <div class="bg-white/[0.05] backdrop-blur-md p-3.5 rounded-2xl border border-white/[0.08] flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-sky-500/15 text-sky-400 flex items-center justify-center text-sm shrink-0">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-white leading-tight">Pase de Lista</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Lectura QR y USB</p>
                        </div>
                    </div>

                    <div class="bg-white/[0.05] backdrop-blur-md p-3.5 rounded-2xl border border-white/[0.08] flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-sm shrink-0">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-white leading-tight">Telegram Sync</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Alertas a tutores</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="relative z-10 text-xs text-slate-500 font-medium flex items-center justify-between border-t border-white/[0.08] pt-6">
                <span>Centro de Atención Múltiple #48</span>
                <span>Ciclo Escolar 2025-2026</span>
            </div>
        </div>

        <!-- Floating Theme Toggle (Apple Glass Style) -->
        <button 
            @click="toggleTheme" 
            type="button"
            class="fixed top-5 right-5 z-30 w-10 h-10 rounded-2xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200/60 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:text-primary transition-all flex items-center justify-center active:scale-95 shadow-sm"
            :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
            :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        >
            <i v-if="!isDark" class="fas fa-moon text-sm"></i>
            <i v-else class="fas fa-sun text-sm text-amber-400"></i>
        </button>

        <!-- Right Side: Login Form -->
        <div class="w-full md:w-7/12 lg:w-1/2 flex items-center justify-center p-6 sm:p-8 lg:p-12 xl:p-16 relative">
            <div class="w-full max-w-md">
                
                <!-- Institutional Header with Full Logo (Modo Claro: logo-claro.svg / Modo Oscuro: logo-oscuro.svg) -->
                <div class="mb-5 flex flex-col items-center text-center">
                    <div class="mb-3 flex justify-center w-full">
                        <ApplicationLogo
                            variant="completo"
                            size="h-36 sm:h-40 md:h-44 lg:h-48"
                            alt="Centro de Atención Múltiple Tonalpouhqui #48"
                            class="transition-transform duration-300 hover:scale-[1.02]"
                        />
                    </div>
                    <h2 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                        Iniciar Sesión
                    </h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1.5 font-normal max-w-sm">
                        Ingresa tus credenciales autorizadas para acceder a la administración de FamilyLock.
                    </p>
                </div>

                <!-- Status alert -->
                <div v-if="status" class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-300 rounded-2xl text-xs font-semibold flex items-center gap-3">
                    <i class="fas fa-check-circle text-base text-emerald-500"></i> 
                    <span>{{ status }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-2">
                            Correo Electrónico
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-envelope text-sm"></i>
                            </div>
                            <input 
                                id="email" 
                                type="email" 
                                v-model="form.email" 
                                required 
                                autofocus 
                                autocomplete="username"
                                class="input-premium w-full pl-10 pr-4 py-3"
                                placeholder="usuario@cam48.gob.mx"
                            />
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label for="password" class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Contraseña
                            </label>
                            <Link 
                                v-if="canResetPassword" 
                                :href="route('password.request')" 
                                class="text-xs font-semibold text-primary hover:text-sky-600 dark:text-sky-400 transition-colors focus:outline-none focus:underline"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock text-sm"></i>
                            </div>
                            <input 
                                id="password" 
                                :type="isPasswordVisible ? 'text' : 'password'" 
                                v-model="form.password" 
                                required 
                                autocomplete="current-password"
                                class="input-premium w-full pl-10 pr-11 py-3"
                                placeholder="••••••••"
                            />
                            <button 
                                type="button" 
                                @click="isPasswordVisible = !isPasswordVisible" 
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors outline-none"
                                :aria-label="isPasswordVisible ? 'Ocultar contraseña' : 'Ver contraseña'"
                            >
                                <i class="fas text-sm" :class="isPasswordVisible ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <!-- Remember Me & Actions -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center cursor-pointer select-none group">
                            <input 
                                type="checkbox" 
                                v-model="form.remember" 
                                class="w-4 h-4 rounded-md border-slate-300 dark:border-slate-700 text-primary focus:ring-primary/20 transition-all cursor-pointer"
                            />
                            <span class="ml-2.5 text-xs font-medium text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-slate-200 transition-colors">
                                Mantener sesión activa
                            </span>
                        </label>
                    </div>

                    <!-- Submit Button (Apple instant tactile feedback) -->
                    <div class="pt-2">
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="btn-premium w-full py-3.5 text-sm font-bold flex items-center justify-center gap-2 shadow-md disabled:opacity-50"
                        >
                            <i v-if="form.processing" class="fas fa-circle-notch fa-spin text-sm"></i>
                            <span>{{ form.processing ? 'Verificando acceso...' : 'Ingresar a FamilyLock' }}</span>
                            <i v-if="!form.processing" class="fas fa-arrow-right text-xs ml-1"></i>
                        </button>
                    </div>
                </form>

                <!-- Institutional Footer Badge -->
                <div class="mt-6 pt-5 border-t border-slate-200/60 dark:border-slate-800/80 text-center">
                    <p class="text-xs text-slate-400 dark:text-slate-500 font-medium">
                        Sistema Administrativo Escolar &bull; Versión 2.0
                    </p>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
/* Ocultar iconos nativos de contraseñas de Microsoft Edge */
input::-ms-reveal,
input::-ms-clear {
    display: none;
}
</style>
