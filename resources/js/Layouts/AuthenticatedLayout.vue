<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';

const menuOpen = ref(false);
const page = usePage();
const user = page.props.auth.user;

// --- Lógica de Modo Oscuro ---
const isDark = ref(false);

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
                    title: `¡Tutor Vinculado!`,
                    text: `${nombre} ha conectado su Telegram.`,
                    customClass: { popup: 'rounded-xl shadow-lg border border-slate-100' }
                });
            });
            // Recargar datos si estamos en tutores
            if (window.location.pathname.includes('/tutores')) {
                router.reload({ only: ['tutores'] });
            }
        }
    } catch (e) {
        // Falla silenciosamente para no interrumpir al usuario
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

    // Optimización: Solo consultar Telegram si estamos en páginas donde se necesita (Personal o Tutores)
    // y aumentar el tiempo a 60 segundos para no bloquear el servidor local de Artisan.
    const isRelevantPage = window.location.pathname.includes('/tutores') || window.location.pathname.includes('/personal');
    
    if (isRelevantPage) {
        syncInterval = setInterval(checkTelegramSync, 60000); // 60 segundos
    }
});

onUnmounted(() => {
    if (syncInterval) clearInterval(syncInterval);
});

const confirmarSalida = () => {
    Swal.fire({
        title: '¿Cerrar sesión?',
        text: 'Tendrás que ingresar tus credenciales de nuevo.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-popup',
            title: 'swal2-title',
            confirmButton: 'swal2-confirm',
            cancelButton: 'swal2-cancel',
            icon: 'swal2-icon'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('logout'));
        }
    });
};
</script>

<template>
    <div class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-sans flex min-h-screen overflow-hidden transition-colors duration-500" @keydown.escape="menuOpen = false">
        
        <!-- Mobile menu overlay -->
        <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-show="menuOpen" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-40 md:hidden" @click="menuOpen = false"></div>
        </Transition>

        <!-- Sidebar Premium -->
        <aside :class="{'translate-x-0': menuOpen, '-translate-x-full': !menuOpen, 'md:translate-x-0': true}" 
               class="sidebar-shell w-64 text-white fixed md:sticky top-0 left-0 h-screen z-50 transition-transform duration-300 ease-in-out flex flex-col">
            
            <!-- Decorative glow -->
            <div class="absolute -top-32 -left-32 w-64 h-64 bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>
            <div class="absolute bottom-20 -right-20 w-48 h-48 bg-secondary/8 rounded-full blur-[80px] pointer-events-none"></div>

            <!-- Logo Section -->
            <div class="p-6 border-b border-white/[0.06] relative">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center border border-white/10">
                        <img src="/Logo del CAM.png" alt="Logo CAM" class="h-7 object-contain">
                    </div>
                    <div>
                        <h2 class="font-display font-black text-lg tracking-tight leading-none">CAM <span class="text-primary">#48</span></h2>
                        <p class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em] mt-0.5">Panel Admin</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-3 py-4 space-y-1 text-[13px] font-medium custom-scroll overflow-y-auto relative">
                
                <p class="text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] px-3 pt-2 pb-3">Principal</p>

                <Link :href="route('dashboard')" :class="route().current('dashboard') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-th-large w-5 text-center" :class="route().current('dashboard') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Dashboard
                </Link>

                <Link href="/personal" :class="$page.url.startsWith('/personal') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-chalkboard-teacher w-5 text-center" :class="$page.url.startsWith('/personal') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Personal
                </Link>

                <Link :href="route('tutores.index')" :class="route().current('tutores.index') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-users w-5 text-center" :class="route().current('tutores.index') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Tutores
                </Link>

                <p class="text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] px-3 pt-4 pb-3">Operación</p>

                <Link :href="route('historial.index')" :class="route().current('historial.index') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-history w-5 text-center" :class="route().current('historial.index') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Historial
                </Link>

                <Link :href="route('expedientes.index')" :class="route().current('expedientes.index') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-folder-open w-5 text-center" :class="route().current('expedientes.index') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Expedientes
                </Link>
                
                <Link :href="route('asistencia.escaner')" :class="route().current('asistencia.escaner') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-qrcode w-5 text-center" :class="route().current('asistencia.escaner') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Pase de Lista
                </Link>

                <Link :href="route('credenciales.index')" :class="route().current('credenciales.index') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-id-card w-5 text-center" :class="route().current('credenciales.index') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Credenciales
                </Link>

                <p class="text-[9px] font-black text-slate-600 uppercase tracking-[0.2em] px-3 pt-4 pb-3">Análisis</p>
                
                <Link :href="route('estadisticas.index')" :class="route().current('estadisticas.index') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-chart-pie w-5 text-center" :class="route().current('estadisticas.index') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Estadísticas
                </Link>
                
                <Link :href="route('eventos.index')" :class="route().current('eventos.index') ? 'sidebar-link-active' : 'sidebar-link'" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group">
                    <i class="fas fa-calendar-alt w-5 text-center" :class="route().current('eventos.index') ? 'text-primary' : 'text-slate-500 group-hover:text-primary'"></i> Agenda
                </Link>
            </nav>

            <!-- User Profile / Logout -->
            <div class="p-3 border-t border-white/[0.06] relative">
                <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-white/[0.04] transition-colors cursor-default mb-2">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-blue-600 flex items-center justify-center font-display font-black text-xs text-white shadow-lg shadow-primary/20 shrink-0">
                        {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-slate-300 truncate">{{ user.name }}</p>
                        <p class="text-[10px] text-slate-500 truncate">{{ user.email }}</p>
                    </div>
                </div>
                <button @click="confirmarSalida" class="w-full flex items-center justify-center gap-2 text-slate-500 hover:text-red-400 hover:bg-red-500/10 p-2.5 rounded-xl transition-all font-bold text-xs group">
                    <i class="fas fa-arrow-right-from-bracket group-hover:text-red-400 transition-colors"></i> Cerrar Sesión
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 h-screen overflow-y-auto custom-scroll relative z-10">
            
            <!-- Animated Background Blobs -->
            <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
                <div class="absolute top-[-10%] left-[-10%] w-[40vw] h-[40vw] rounded-full bg-primary/20 dark:bg-primary/10 blur-[100px] animate-blob mix-blend-multiply dark:mix-blend-screen opacity-70"></div>
                <div class="absolute top-[20%] right-[-10%] w-[35vw] h-[35vw] rounded-full bg-secondary/20 dark:bg-secondary/10 blur-[100px] animate-blob animation-delay-2000 mix-blend-multiply dark:mix-blend-screen opacity-70"></div>
                <div class="absolute bottom-[-20%] left-[20%] w-[45vw] h-[45vw] rounded-full bg-sky-400/20 dark:bg-sky-400/10 blur-[120px] animate-blob animation-delay-4000 mix-blend-multiply dark:mix-blend-screen opacity-70"></div>
            </div>

            <!-- Top Navigation Bar -->
            <nav class="bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border-b border-slate-200/40 dark:border-slate-700/40 py-3 px-6 md:px-8 flex justify-between items-center sticky top-0 z-30 transition-colors duration-500">
                <div class="flex items-center gap-4">
                    <button @click="menuOpen = !menuOpen" class="md:hidden w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-primary/10 text-slate-500 hover:text-primary flex items-center justify-center transition-all">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div id="portal-header-title"></div>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Dark Mode Toggle -->
                    <button @click="toggleTheme" class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-primary/10 text-slate-500 dark:text-slate-400 hover:text-primary transition-all flex items-center justify-center shadow-sm relative overflow-hidden" title="Alternar Modo Oscuro">
                        <Transition enter-active-class="transition-transform duration-300" enter-from-class="rotate-180 scale-0" enter-to-class="rotate-0 scale-100" leave-active-class="transition-transform duration-300 absolute" leave-from-class="rotate-0 scale-100" leave-to-class="-rotate-180 scale-0">
                            <i v-if="!isDark" class="fas fa-moon text-lg absolute"></i>
                            <i v-else class="fas fa-sun text-lg absolute text-yellow-400"></i>
                        </Transition>
                    </button>
                    
                    <Link :href="route('profile.edit')" class="w-10 h-10 rounded-full bg-gradient-primary text-white flex items-center justify-center font-display font-black text-sm shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 transition-all z-10" :title="user.name">
                        {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </Link>
                </div>
            </nav>

            <!-- Page Content -->
            <slot />
        </main>
    </div>
</template>
