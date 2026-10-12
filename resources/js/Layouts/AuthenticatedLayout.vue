<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const menuOpen = ref(false);
const page = usePage();
const user = page.props.auth.user;

// --- Lógica de Modo Oscuro ---
const isDark = ref(false);
const isNavigating = ref(false);

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

let syncInterval = null;

const checkTelegramSync = async () => {
    try {
        const response = await axios.get('/api/telegram/sync');
        if (response.data.status === 'ok' && response.data.vinculados && response.data.vinculados.length > 0) {
            response.data.vinculados.forEach(nombre => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true,
                    icon: 'success',
                    title: '¡Tutor Vinculado!',
                    text: `${nombre} ha conectado su Telegram.`,
                    customClass: { popup: 'rounded-2xl shadow-xl border border-slate-100 dark:border-slate-800' }
                });
            });
            if (window.location.pathname.includes('/tutores')) {
                router.reload({ only: ['tutores'] });
            }
        }
    } catch (e) {
        // Falla silenciosamente para no interrumpir
    }
};

onMounted(() => {
    // Inicializar Modo Oscuro
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    } else {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    }

    // Polling condicional para tutores y personal
    const isRelevantPage = window.location.pathname.includes('/tutores') || window.location.pathname.includes('/personal');
    if (isRelevantPage) {
        syncInterval = setInterval(checkTelegramSync, 60000);
    }

    // Eventos de Navegación para Skeleton / Loading
    router.on('start', () => { isNavigating.value = true; });
    router.on('finish', () => { isNavigating.value = false; });
});

onUnmounted(() => {
    if (syncInterval) clearInterval(syncInterval);
});

const confirmarSalida = () => {
    Swal.fire({
        title: '¿Cerrar sesión?',
        text: 'Tendrás que ingresar tus credenciales de nuevo.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar sesión',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#009FE3',
        cancelButtonColor: '#64748b',
        customClass: {
            popup: 'swal2-popup',
            title: 'swal2-title font-display',
            confirmButton: 'btn-premium',
            cancelButton: 'btn-ghost'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('logout'));
        }
    });
};
</script>

<template>
    <div class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 font-sans flex min-h-screen overflow-hidden transition-colors duration-300 relative" @keydown.escape="menuOpen = false">
        
        <!-- Subtle Ambient Background Light (Calm & Elegant, no jarring blobs) -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
            <div class="absolute -top-32 -left-32 w-[45vw] h-[45vw] rounded-full bg-sky-400/[0.07] dark:bg-sky-500/[0.04] blur-[120px]"></div>
            <div class="absolute top-[25%] -right-20 w-[40vw] h-[40vw] rounded-full bg-purple-500/[0.05] dark:bg-purple-600/[0.03] blur-[140px]"></div>
        </div>

        <!-- Mobile Backdrop Scrim -->
        <Transition 
            enter-active-class="transition-opacity duration-300 ease-out" 
            enter-from-class="opacity-0" 
            enter-to-class="opacity-100" 
            leave-active-class="transition-opacity duration-200 ease-in" 
            leave-from-class="opacity-100" 
            leave-to-class="opacity-0"
        >
            <div 
                v-show="menuOpen" 
                class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 md:hidden" 
                @click="menuOpen = false"
                aria-hidden="true"
            ></div>
        </Transition>

        <!-- Sidebar (macOS Style Vibrancy) -->
        <aside 
            :class="[
                menuOpen ? 'translate-x-0' : '-translate-x-full',
                'md:translate-x-0'
            ]" 
            class="sidebar-shell w-64 lg:w-72 text-white fixed md:sticky top-0 left-0 h-screen z-50 transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] flex flex-col shrink-0"
            aria-label="Navegación principal"
        >
            <!-- Header Branding -->
            <div class="px-3 py-4 border-b border-white/[0.08] relative">
                <Link 
                    :href="route('dashboard')" 
                    class="flex items-center justify-start overflow-hidden group focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 rounded-2xl p-1 transition-all w-full"
                    title="FamilyLock CAM #48 — Dashboard"
                >
                    <ApplicationLogo 
                        variant="horizontal" 
                        force-theme="dark" 
                        size="h-16 sm:h-[68px] lg:h-[72px]" 
                        class="w-full max-w-full transition-transform duration-200 group-hover:scale-[1.02] active:scale-[0.98]" 
                        alt="CAM Tonalpouhqui #48"
                    />
                </Link>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 min-h-0 px-3 py-3 space-y-0.5 text-[13px] font-medium custom-scroll overflow-y-auto relative">
                
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.18em] px-3 pt-1 pb-2">Principal</p>

                <Link 
                    :href="route('dashboard')" 
                    :class="route().current('dashboard') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('dashboard') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <span>Dashboard</span>
                </Link>

                <Link 
                    href="/personal" 
                    :class="$page.url.startsWith('/personal') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="$page.url.startsWith('/personal') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <span>Personal</span>
                </Link>

                <Link 
                    :href="route('tutores.index')" 
                    :class="route().current('tutores.index') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('tutores.index') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-users"></i>
                    </div>
                    <span>Tutores</span>
                </Link>

                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.18em] px-3 pt-3 pb-2">Operación</p>

                <Link 
                    :href="route('historial.index')" 
                    :class="route().current('historial.index') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('historial.index') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-history"></i>
                    </div>
                    <span>Historial</span>
                </Link>

                <Link 
                    :href="route('expedientes.index')" 
                    :class="route().current('expedientes.index') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('expedientes.index') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <span>Expedientes</span>
                </Link>
                
                <Link 
                    :href="route('asistencia.escaner')" 
                    :class="route().current('asistencia.escaner') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('asistencia.escaner') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <span>Pase de Lista</span>
                </Link>

                <Link 
                    :href="route('credenciales.index')" 
                    :class="route().current('credenciales.index') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('credenciales.index') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <span>Credenciales</span>
                </Link>

                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.18em] px-3 pt-3 pb-2">Análisis</p>
                
                <Link 
                    :href="route('estadisticas.index')" 
                    :class="route().current('estadisticas.index') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('estadisticas.index') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <span>Estadísticas</span>
                </Link>
                
                <Link 
                    :href="route('eventos.index')" 
                    :class="route().current('eventos.index') ? 'sidebar-link-active' : 'sidebar-link'" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group"
                >
                    <div class="w-6 text-center text-sm" :class="route().current('eventos.index') ? 'text-sky-400' : 'text-slate-400 group-hover:text-slate-200'">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <span>Agenda</span>
                </Link>
            </nav>

            <!-- User Footer Profile / Logout -->
            <div class="p-3 border-t border-white/[0.08] relative">
                <Link :href="route('profile.edit')" class="flex items-center gap-3 p-2 rounded-xl hover:bg-white/[0.06] transition-colors mb-1 group" title="Ver Perfil">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-sky-500 to-blue-600 flex items-center justify-center font-display font-black text-xs text-white shadow-sm shrink-0 border border-white/20">
                        {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-200 truncate group-hover:text-white transition-colors">{{ user.name }}</p>
                        <p class="text-[10px] text-slate-400 truncate">{{ user.email }}</p>
                    </div>
                    <i class="fas fa-chevron-right text-[10px] text-slate-500 group-hover:text-slate-300 transition-colors"></i>
                </Link>
                <button 
                    @click="confirmarSalida" 
                    class="w-full flex items-center justify-center gap-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 p-2 rounded-xl transition-all font-semibold text-xs active:scale-[0.98]"
                >
                    <i class="fas fa-power-off text-xs"></i> Cerrar Sesión
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 h-screen overflow-y-auto custom-scroll relative z-10 flex flex-col">
            
            <!-- Top Navigation Bar (Translucent Glass Layer - Apple Principle §12) -->
            <nav class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-2xl border-b border-slate-200/70 dark:border-white/[0.08] py-3.5 px-6 md:px-8 flex justify-between items-center sticky top-0 z-30 transition-colors duration-300 shrink-0">
                <div class="flex items-center gap-4">
                    <button 
                        @click="menuOpen = !menuOpen" 
                        class="md:hidden w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary flex items-center justify-center transition-all active:scale-95"
                        aria-label="Abrir menú de navegación"
                    >
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div id="portal-header-title" class="md:hidden"></div>

                    <!-- Mobile Institutional Brand in Header -->
                    <div class="md:hidden flex items-center">
                        <Link :href="route('dashboard')" class="flex items-center">
                            <ApplicationLogo variant="horizontal" size="h-9 sm:h-10" alt="CAM Tonalpouhqui #48" />
                        </Link>
                    </div>
                    
                    <!-- Welcome / Breadcrumb Area -->
                    <div class="hidden md:flex flex-col items-start justify-center">
                        <p class="text-[10px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-[0.2em] leading-none mb-1">
                            Centro de Atención Múltiple #48
                        </p>
                        <p class="text-base font-black text-slate-800 dark:text-white leading-none font-display">
                            {{ user.name }}
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2.5">
                    <!-- Dark Mode Toggle Button (Apple tactile response) -->
                    <button 
                        @click="toggleTheme" 
                        class="w-10 h-10 rounded-xl bg-slate-100/90 dark:bg-slate-800/90 border border-slate-200/60 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:text-primary transition-all flex items-center justify-center active:scale-95 shadow-sm" 
                        :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
                        :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
                    >
                        <i v-if="!isDark" class="fas fa-moon text-sm"></i>
                        <i v-else class="fas fa-sun text-sm text-amber-400"></i>
                    </button>

                    <!-- User Profile Avatar Pill -->
                    <Link 
                        :href="route('profile.edit')" 
                        class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full bg-slate-100/90 dark:bg-slate-800/90 border border-slate-200/60 dark:border-white/10 hover:border-primary/40 transition-all active:scale-95 shadow-sm"
                        title="Configurar Perfil"
                    >
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-sky-500 to-blue-600 text-white flex items-center justify-center font-display font-black text-xs shadow-sm">
                            {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 hidden sm:inline">
                            Perfil
                        </span>
                    </Link>
                </div>
            </nav>

            <!-- Page Content -->
            <div class="relative flex-1">
                <!-- Navigation Transition Loader -->
                <Transition 
                    enter-active-class="transition-opacity duration-200" 
                    enter-from-class="opacity-0" 
                    enter-to-class="opacity-100" 
                    leave-active-class="transition-opacity duration-200" 
                    leave-from-class="opacity-100" 
                    leave-to-class="opacity-0"
                >
                    <div v-if="isNavigating" class="absolute inset-0 z-30 bg-slate-900/10 dark:bg-black/30 backdrop-blur-[2px] flex items-center justify-center pointer-events-none">
                        <div class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-xl px-5 py-3 rounded-2xl shadow-xl border border-slate-200/60 dark:border-white/10 flex items-center gap-3">
                            <i class="fas fa-circle-notch fa-spin text-primary text-base"></i>
                            <span class="font-bold text-slate-700 dark:text-slate-200 text-xs tracking-wide">Cargando...</span>
                        </div>
                    </div>
                </Transition>
                
                <slot />
            </div>
        </main>
    </div>
</template>
