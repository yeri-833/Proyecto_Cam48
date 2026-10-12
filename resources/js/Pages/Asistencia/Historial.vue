<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    registros: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    fechaFiltro: {
        type: String,
        default: '',
    },
    busquedaActual: {
        type: String,
        default: '',
    },
    rolActual: {
        type: String,
        default: 'Todos',
    },
    estadisticas: {
        type: Object,
        default: () => ({ total: 0, Alumno: 0, Personal: 0, Tutor: 0 }),
    }
});

const fechaSeleccionada = ref(props.fechaFiltro);
const busqueda = ref(props.busquedaActual || '');
const rolActivo = ref(props.rolActual || 'Todos');

const fetchDatos = debounce(() => {
    router.get(route('historial.index'), {
        fecha: fechaSeleccionada.value,
        busqueda: busqueda.value,
        rol: rolActivo.value
    }, { preserveState: true, replace: true });
}, 300);

watch([fechaSeleccionada, busqueda, rolActivo], () => {
    fetchDatos();
});

const exportarCsv = () => {
    const url = new URL(route('historial.exportar'), window.location.origin);
    url.searchParams.append('fecha', fechaSeleccionada.value);
    if (busqueda.value) url.searchParams.append('busqueda', busqueda.value);
    if (rolActivo.value !== 'Todos') url.searchParams.append('rol', rolActivo.value);
    
    window.location.href = url.toString();
};

const eliminarRegistro = (id) => {
    Swal.fire({
        title: '¿Eliminar registro?',
        text: "Esto removerá esta asistencia permanentemente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-popup',
            confirmButton: 'bg-rose-600 text-white font-bold px-5 py-2.5 rounded-xl',
            cancelButton: 'btn-ghost'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('historial.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        icon: 'success',
                        title: 'Registro eliminado exitosamente'
                    });
                }
            });
        }
    });
};

const getColorRol = (rol) => {
    if (rol === 'Alumno') return 'badge-blue';
    if (rol === 'Tutor') return 'badge-amber';
    return 'badge-emerald';
};

const getIconoRol = (rol) => {
    if (rol === 'Alumno') return 'fas fa-user-graduate';
    if (rol === 'Tutor') return 'fas fa-users';
    return 'fas fa-chalkboard-teacher';
};

const roles = [
    { key: 'Todos', label: 'Todos' },
    { key: 'Alumno', label: 'Alumnos', icon: 'fas fa-user-graduate' },
    { key: 'Personal', label: 'Personal', icon: 'fas fa-chalkboard-teacher' },
    { key: 'Tutor', label: 'Tutores', icon: 'fas fa-users' },
];
</script>

<template>
    <Head title="Historial Diario — FamilyLock" />

    <AuthenticatedLayout>
        <div class="p-6 md:p-8 max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                        Historial <span class="text-gradient-primary">Diario de Accesos</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-medium text-xs sm:text-sm mt-1">
                        Bitácora cronológica de entradas y salidas registradas por escáner QR.
                    </p>
                </div>
                
                <!-- Date Picker & Exports -->
                <div class="flex flex-wrap items-center gap-2.5 print-hidden">
                    <div class="flex items-center gap-2 bg-white dark:bg-slate-800/80 px-3 py-1.5 rounded-2xl border border-slate-200/70 dark:border-white/10 shadow-sm">
                        <i class="far fa-calendar text-sky-500 text-xs"></i>
                        <input 
                            type="date" 
                            v-model="fechaSeleccionada" 
                            class="border-none bg-transparent font-bold text-slate-700 dark:text-slate-200 outline-none cursor-pointer focus:ring-0 text-xs p-0 uppercase"
                        />
                    </div>

                    <button @click="exportarCsv" class="btn-ghost flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <i class="fas fa-file-excel text-xs"></i> <span>CSV</span>
                    </button>
                    <button onclick="window.print()" class="btn-ghost flex items-center gap-1.5">
                        <i class="fas fa-print text-xs"></i> <span>Imprimir</span>
                    </button>
                </div>
            </div>

            <!-- Summary Metric Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                <div class="premium-card p-4 sm:p-5 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-sm shrink-0">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase text-slate-400 tracking-wider">Total Día</p>
                        <p class="text-2xl font-black text-slate-900 dark:text-white font-mono leading-none mt-0.5">
                            {{ estadisticas.total }}
                        </p>
                    </div>
                </div>

                <div class="premium-card p-4 sm:p-5 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase text-sky-600 dark:text-sky-400 tracking-wider">Alumnos</p>
                        <p class="text-2xl font-black text-slate-900 dark:text-white font-mono leading-none mt-0.5">
                            {{ estadisticas.Alumno }}
                        </p>
                    </div>
                </div>

                <div class="premium-card p-4 sm:p-5 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase text-purple-600 dark:text-purple-400 tracking-wider">Personal</p>
                        <p class="text-2xl font-black text-slate-900 dark:text-white font-mono leading-none mt-0.5">
                            {{ estadisticas.Personal }}
                        </p>
                    </div>
                </div>

                <div class="premium-card p-4 sm:p-5 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase text-amber-600 dark:text-amber-400 tracking-wider">Tutores</p>
                        <p class="text-2xl font-black text-slate-900 dark:text-white font-mono leading-none mt-0.5">
                            {{ estadisticas.Tutor }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Table Card with Filter Chips & Search -->
            <div class="premium-card-static overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-200/70 dark:border-white/[0.08] flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    
                    <!-- Role Filter Chips -->
                    <div class="inline-flex p-1 rounded-2xl bg-slate-200/70 dark:bg-slate-800 border border-slate-300/60 dark:border-white/10 text-xs font-bold shadow-inner">
                        <button 
                            v-for="r in roles" 
                            :key="r.key"
                            @click="rolActivo = r.key" 
                            :class="rolActivo === r.key ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            class="px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5"
                        >
                            <i v-if="r.icon" :class="r.icon" class="text-xs"></i>
                            <span>{{ r.label }}</span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full md:w-80">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input 
                            type="text" 
                            v-model="busqueda" 
                            placeholder="Buscar por nombre o matrícula..." 
                            class="input-premium w-full pl-9 pr-4 py-2 text-xs"
                        />
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Identificación</th>
                                <th>Nombre Completo</th>
                                <th>Rol</th>
                                <th class="text-center print-hidden">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="reg in registros.data" :key="reg.id" class="group">
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span class="font-mono font-bold text-xs text-slate-800 dark:text-slate-200">
                                            {{ reg.hora_formateada }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-mono text-xs font-semibold text-slate-500 dark:text-slate-400">
                                        {{ reg.id_persona }}
                                    </span>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase">
                                        {{ reg.nombre_completo }}
                                    </p>
                                </td>
                                <td>
                                    <span class="badge" :class="getColorRol(reg.rol)">
                                        <i :class="getIconoRol(reg.rol)" class="mr-1"></i> {{ reg.rol }}
                                    </span>
                                </td>
                                <td class="print-hidden">
                                    <div class="table-actions">
                                        <button 
                                            @click="eliminarRegistro(reg.id)" 
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" 
                                            title="Eliminar Registro"
                                            aria-label="Eliminar Registro"
                                        >
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="registros.data.length === 0">
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-history text-2xl"></i>
                                        </div>
                                        <p class="text-slate-700 dark:text-slate-300 font-bold text-sm">Sin registros para esta fecha</p>
                                        <p class="text-slate-400 text-xs mt-1">Selecciona otra fecha en el calendario o remueve los filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="registros.links && registros.links.length > 3" class="p-4 border-t border-slate-200/70 dark:border-white/[0.08] bg-slate-50/30 dark:bg-slate-900/20 print-hidden">
                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                        <template v-for="(link, k) in registros.links" :key="k">
                            <Link 
                                v-if="link.url"
                                :href="link.url" 
                                v-html="link.label"
                                class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition-all select-none"
                                :class="link.active 
                                    ? 'bg-primary text-white shadow-sm' 
                                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                            />
                            <span 
                                v-else 
                                v-html="link.label" 
                                class="px-3.5 py-1.5 text-xs text-slate-400 cursor-not-allowed select-none"
                            ></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
