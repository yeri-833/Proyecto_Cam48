<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    registros: Object,
    fechaFiltro: String,
    busquedaActual: String,
    rolActual: String,
    estadisticas: Object
});

const fechaSeleccionada = ref(props.fechaFiltro);
const busqueda = ref(props.busquedaActual || '');
const rolActivo = ref(props.rolActual || 'Todos');

// Actualizar tabla al cambiar filtros (con debounce para la búsqueda)
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
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('historial.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                        icon: 'success', title: 'Registro eliminado'
                    });
                }
            });
        }
    });
};

const getColorRol = (rol) => {
    if (rol === 'Alumno') return 'badge-blue';
    if (rol === 'Tutor') return 'badge-amber';
    return 'badge-emerald'; // Personal
};

const getIconoRol = (rol) => {
    if (rol === 'Alumno') return 'fas fa-user-graduate';
    if (rol === 'Tutor') return 'fas fa-user-friends';
    return 'fas fa-chalkboard-teacher'; // Personal
};

const roles = [
    { key: 'Todos', label: 'Todos', icon: null, color: 'bg-slate-800' },
    { key: 'Alumno', label: 'Alumnos', icon: 'fas fa-user-graduate', color: 'bg-primary' },
    { key: 'Personal', label: 'Personal', icon: 'fas fa-chalkboard-teacher', color: 'bg-emerald-500' },
    { key: 'Tutor', label: 'Tutores', icon: 'fas fa-user-friends', color: 'bg-amber-500' },
];
</script>

<template>
    <Head title="Historial Diario" />

    <AuthenticatedLayout>
        <div class="p-6 md:p-8 max-w-7xl mx-auto">
            
            <!-- Cabecera -->
            <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4 animate-fade-in-up">
                <div>
                    <h1 class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight">
                        Historial <span class="text-gradient-primary">Diario</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-bold text-sm mt-1">Consulta los registros de entradas y salidas.</p>
                </div>
                
                <!-- Acciones Derecha -->
                <div class="flex items-center gap-3 print-hidden">
                    <!-- Date Picker -->
                    <div class="flex items-center gap-3 bg-white dark:bg-slate-800/50 p-2 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 flex-1 md:flex-auto">
                        <div class="stat-card-icon w-10 h-10 bg-primary/10 text-primary shrink-0">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <input type="date" v-model="fechaSeleccionada" class="border-none bg-transparent font-black text-slate-700 dark:text-slate-200 outline-none cursor-pointer focus:ring-0 mr-2 uppercase text-sm w-full md:w-auto">
                    </div>

                    <!-- Export Buttons -->
                    <button @click="exportarCsv" class="bg-emerald-500 hover:bg-emerald-600 text-white p-3 rounded-2xl shadow-md shadow-emerald-500/20 transition-all hover:-translate-y-0.5 active:translate-y-0 shrink-0 flex items-center gap-2 font-black text-sm" title="Exportar a CSV">
                        <i class="fas fa-file-excel"></i> <span class="hidden md:inline">CSV</span>
                    </button>
                    <button onclick="window.print()" class="bg-primary hover:bg-blue-600 text-white p-3 rounded-2xl shadow-md shadow-primary/20 transition-all hover:-translate-y-0.5 active:translate-y-0 shrink-0 flex items-center gap-2 font-black text-sm" title="Exportar a PDF / Imprimir">
                        <i class="fas fa-file-pdf"></i> <span class="hidden md:inline">PDF</span>
                    </button>
                </div>
            </div>

            <!-- Resumen Estadístico (Tarjetas) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 stagger-children">
                <div class="stat-card border-slate-100">
                    <div class="stat-card-icon bg-slate-50 dark:bg-slate-700 text-slate-400 dark:text-slate-300"><i class="fas fa-users"></i></div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest leading-none mb-1">Total Hoy</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white leading-none font-display">{{ estadisticas.total }}</p>
                    </div>
                </div>
                <div class="stat-card border-primary/20">
                    <div class="stat-card-icon bg-primary/10 text-primary"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-primary tracking-widest leading-none mb-1">Alumnos</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white leading-none font-display">{{ estadisticas.Alumno }}</p>
                    </div>
                </div>
                <div class="stat-card border-emerald-500/20">
                    <div class="stat-card-icon bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400 tracking-widest leading-none mb-1">Personal</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white leading-none font-display">{{ estadisticas.Personal }}</p>
                    </div>
                </div>
                <div class="stat-card border-amber-500/20">
                    <div class="stat-card-icon bg-amber-500/10 text-amber-600 dark:text-amber-400"><i class="fas fa-user-friends"></i></div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-amber-600 dark:text-amber-400 tracking-widest leading-none mb-1">Tutores</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white leading-none font-display">{{ estadisticas.Tutor }}</p>
                    </div>
                </div>
            </div>

            <!-- Controles de búsqueda y filtros -->
            <div class="premium-card-static overflow-hidden animate-fade-in-up print-hidden" style="animation-delay: 250ms">
                <div class="p-4 md:p-6 border-b border-slate-100/80 flex flex-col lg:flex-row justify-between items-center gap-4 bg-slate-50/30">
                    
                    <!-- Filtros por Rol -->
                    <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                        <button 
                            v-for="r in roles" :key="r.key"
                            @click="rolActivo = r.key" 
                            :class="rolActivo === r.key ? `${r.color} text-white shadow-md` : 'bg-white text-slate-500 hover:bg-slate-50'" 
                            class="filter-chip"
                        >
                            <i v-if="r.icon" :class="r.icon" class="mr-1"></i> {{ r.label }}
                        </button>
                    </div>

                    <!-- Buscador -->
                    <div class="relative w-full lg:w-96">
                        <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" v-model="busqueda" placeholder="Buscar por nombre o matrícula..." 
                               class="input-premium w-full pl-12 text-sm">
                    </div>
                </div>

                <!-- Tabla -->
                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Identificación</th>
                                <th>Persona</th>
                                <th>Rol</th>
                                <th class="text-center print-hidden">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="reg in registros.data" :key="reg.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-emerald-400 pulse-soft"></div>
                                        <p class="font-black text-slate-700 text-sm">{{ reg.hora_formateada }}</p>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-mono font-bold text-slate-400 text-xs">{{ reg.id_persona }}</p>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800 text-sm uppercase">{{ reg.nombre_completo }}</p>
                                </td>
                                <td>
                                    <span class="badge flex items-center gap-2 w-max" :class="getColorRol(reg.rol)">
                                        <i :class="getIconoRol(reg.rol)"></i> {{ reg.rol }}
                                    </span>
                                </td>
                                <td class="print-hidden">
                                    <div class="table-actions">
                                        <button @click="eliminarRegistro(reg.id)" class="table-action-btn bg-slate-50 text-slate-400 hover:text-red-500 hover:bg-red-50" title="Eliminar Registro">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="registros.data.length === 0">
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-inbox text-3xl"></i>
                                        </div>
                                        <p class="text-slate-500 font-bold text-lg">Sin registros</p>
                                        <p class="text-slate-400 text-sm mt-1">No hay registros de asistencia bajo este filtro.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="registros.links && registros.links.length > 3" class="pagination-container print-hidden">
                    <template v-for="(link, k) in registros.links" :key="k">
                        <Link 
                            v-if="link.url"
                            :href="link.url" 
                            v-html="link.label"
                            class="pagination-btn"
                            :class="link.active ? 'pagination-btn-active' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'"
                        />
                        <span v-else v-html="link.label" class="pagination-btn text-slate-400 bg-transparent border-transparent cursor-default"></span>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
