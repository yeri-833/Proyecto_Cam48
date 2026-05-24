<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const isPasswordVisible = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Acceso al Sistema" />

    <div class="min-h-screen bg-slate-50 flex flex-col md:flex-row font-sans selection:bg-primary selection:text-white overflow-hidden relative">
        
        <!-- Elementos Decorativos de Fondo (para móvil) -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 md:hidden"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-secondary/10 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2 md:hidden"></div>

        <!-- Columna Izquierda: Branding (Oculta en móviles pequeños) -->
        <div class="hidden md:flex md:w-5/12 lg:w-1/2 bg-gradient-to-br from-primary via-blue-600 to-secondary p-12 flex-col justify-between relative overflow-hidden text-white shadow-2xl z-10">
            <!-- Capas Decorativas Glassmorphism -->
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
                <div class="absolute -top-24 -left-24 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-10 -right-20 w-[30rem] h-[30rem] bg-secondary/20 rounded-full blur-3xl mix-blend-screen"></div>
                <!-- Círculos de Cristal -->
                <div class="absolute top-1/4 right-1/4 w-32 h-32 rounded-full border border-white/20 bg-white/5 backdrop-blur-md"></div>
                <div class="absolute bottom-1/3 left-1/4 w-16 h-16 rounded-full border border-white/20 bg-white/10 backdrop-blur-sm animate-pulse"></div>
            </div>

            <!-- Header Branding -->
            <div class="relative z-10">
                <div class="inline-flex items-center gap-3 bg-white/10 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/20 shadow-glass">
                    <img src="/Logo del CAM.png" alt="Logo" class="h-8 object-contain drop-shadow-md">
                    <span class="font-display font-black tracking-widest uppercase text-sm">CAM #48</span>
                </div>
            </div>

            <!-- Hero Message -->
            <div class="relative z-10 max-w-lg">
                <h1 class="font-display font-black text-5xl lg:text-6xl leading-[1.1] tracking-tight mb-6">
                    Innovación <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-200 to-white">Escolar</span>
                </h1>
                <p class="text-blue-100 font-bold text-lg leading-relaxed mb-8 opacity-90">
                    Plataforma integral de gestión administrativa, control de asistencias y análisis estadístico.
                </p>
                <div class="flex gap-4">
                    <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 w-32">
                        <i class="fas fa-bolt text-2xl mb-2 text-blue-200"></i>
                        <p class="text-xs font-black uppercase tracking-widest opacity-80">Rápido</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 w-32">
                        <i class="fas fa-shield-alt text-2xl mb-2 text-blue-200"></i>
                        <p class="text-xs font-black uppercase tracking-widest opacity-80">Seguro</p>
                    </div>
                </div>
            </div>

            <!-- Footer Branding -->
            <div class="relative z-10 opacity-70 text-sm font-bold flex items-center gap-2">
                <i class="fas fa-code"></i> Sistema Modernizado &bull; Laravel + Vue 3
            </div>
        </div>

        <!-- Columna Derecha: Formulario -->
        <div class="w-full md:w-7/12 lg:w-1/2 flex items-center justify-center p-6 sm:p-12 lg:p-24 relative z-10">
            <div class="w-full max-w-md bg-white/80 md:bg-transparent backdrop-blur-xl md:backdrop-blur-none p-8 md:p-0 rounded-3xl md:rounded-none shadow-2xl md:shadow-none border border-white md:border-none">
                
                <!-- Logo para móvil -->
                <div class="md:hidden flex justify-center mb-8">
                    <div class="w-20 h-20 bg-white rounded-3xl shadow-soft flex items-center justify-center border border-slate-100">
                        <img src="/Logo del CAM.png" alt="Logo" class="h-12 object-contain">
                    </div>
                </div>

                <div class="mb-10 text-center md:text-left">
                    <h2 class="font-display font-black text-3xl md:text-4xl text-slate-800 tracking-tight mb-2">¡Hola de nuevo!</h2>
                    <p class="text-slate-500 font-bold text-sm">Ingresa tus credenciales administrativas para continuar.</p>
                </div>

                <div v-if="status" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-2xl text-sm font-bold flex items-center gap-3 animate-fade-in-up">
                    <i class="fas fa-check-circle text-lg"></i> {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2 pl-1">Correo Electrónico</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <input 
                                id="email" 
                                type="email" 
                                v-model="form.email" 
                                required 
                                autofocus 
                                autocomplete="username"
                                class="w-full pl-12 pr-4 py-4 bg-slate-50 md:bg-white border-2 border-transparent focus:border-primary/20 focus:bg-white focus:ring-4 focus:ring-primary/10 rounded-2xl text-slate-700 font-bold transition-all shadow-sm"
                                placeholder="director@cam48.com"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex justify-between items-center mb-2 pl-1">
                            <label for="password" class="block text-xs font-black text-slate-400 uppercase tracking-widest">Contraseña</label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-[11px] font-bold text-primary hover:text-blue-700 transition-colors">
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input 
                                id="password" 
                                :type="isPasswordVisible ? 'text' : 'password'" 
                                v-model="form.password" 
                                required 
                                autocomplete="current-password"
                                class="w-full pl-12 pr-12 py-4 bg-slate-50 md:bg-white border-2 border-transparent focus:border-primary/20 focus:bg-white focus:ring-4 focus:ring-primary/10 rounded-2xl text-slate-700 font-bold transition-all shadow-sm tracking-widest"
                                placeholder="••••••••"
                            />
                            <button type="button" @click="isPasswordVisible = !isPasswordVisible" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors outline-none focus:text-primary">
                                <i class="fas" :class="isPasswordVisible ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <!-- Remember Me & Submit -->
                    <div class="pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                        <label class="flex items-center cursor-pointer group">
                            <div class="relative w-5 h-5 flex items-center justify-center mr-3 shrink-0">
                                <input type="checkbox" v-model="form.remember" class="peer appearance-none w-5 h-5 border-2 border-slate-300 rounded-lg checked:bg-primary checked:border-primary transition-all cursor-pointer outline-none focus:ring-4 focus:ring-primary/20" />
                                <i class="fas fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                            </div>
                            <span class="text-sm font-bold text-slate-500 group-hover:text-slate-700 transition-colors">Mantener sesión activa</span>
                        </label>

                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="bg-slate-800 hover:bg-slate-900 text-white px-8 py-4 rounded-2xl font-black text-sm tracking-widest shadow-soft shadow-slate-900/30 transition-all hover:-translate-y-1 disabled:opacity-50 disabled:hover:translate-y-0 flex justify-center items-center gap-3 w-full sm:w-auto"
                        >
                            <span v-if="!form.processing">ACCEDER</span>
                            <i v-if="!form.processing" class="fas fa-arrow-right"></i>
                            <i v-else class="fas fa-circle-notch fa-spin text-xl"></i>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</template>
