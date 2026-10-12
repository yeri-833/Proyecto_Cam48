<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({ totalAlumnos: 0, asistenciasHoy: 0, pendientes: 0 }),
    },
    alumnos: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    proximoEvento: {
        type: Object,
        default: null,
    },
});

const busqueda = ref('');
const horaActual = ref('');
let intervaloReloj = null;

const actualizarReloj = () => {
    const ahora = new Date();
    horaActual.value = ahora.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
};

onMounted(() => {
    actualizarReloj();
    intervaloReloj = setInterval(actualizarReloj, 1000);
});

onUnmounted(() => {
    if (intervaloReloj) clearInterval(intervaloReloj);
});

const alumnosFiltrados = computed(() => {
    if (!props.alumnos?.data) return [];
    if (!busqueda.value) return props.alumnos.data;
    const q = busqueda.value.toLowerCase().trim();
    return props.alumnos.data.filter(a =>
        (a.nombre && String(a.nombre).toLowerCase().includes(q)) ||
        (a.apellido && String(a.apellido).toLowerCase().includes(q)) ||
        (a.matricula && String(a.matricula).toLowerCase().includes(q)) ||
        (a.nombre_completo && String(a.nombre_completo).toLowerCase().includes(q))
    );
});

// Lógica de Edición y Creación de Alumnos
const modalEditar = ref(false);
const modoEdicion = ref(false);
const form = useForm({
    matricula: '',
    nombre: '',
    apellido: '',
    sexo: 'M',
    curp: '',
    fecha_nacimiento: '',
    edad: '',
    nivel_educativo: '',
    grado: '',
    grupo: '',
    discapacidad: '',
    comentarios: '',
    foto_perfil: null,
    _method: 'put'
});
const matriculaSeleccionada = ref('');

const normalizeNivel = (nivel) => {
    if (!nivel) return '';
    const n = nivel.toLowerCase();
    if (n.includes('preescolar')) return 'Preescolar';
    if (n.includes('primaria')) return 'Primaria';
    if (n.includes('secundaria')) return 'Secundaria';
    if (n.includes('laboral')) return 'Formación Laboral';
    return nivel;
};

const abrirModalNuevo = () => {
    modoEdicion.value = false;
    form.clearErrors();
    form.reset();
    form.matricula = '';
    form.nombre = '';
    form.apellido = '';
    form.sexo = 'M';
    form.curp = '';
    form.fecha_nacimiento = '';
    form.edad = '';
    form.nivel_educativo = '';
    form.grado = '';
    form.grupo = '';
    form.discapacidad = '';
    form.comentarios = '';
    form.foto_perfil = null;
    form._method = 'post';
    modalEditar.value = true;
};

const abrirModalEditar = (alumno) => {
    modoEdicion.value = true;
    form.clearErrors();
    matriculaSeleccionada.value = alumno.matricula;
    form.nombre = alumno.nombre;
    form.apellido = alumno.apellido;
    form.curp = alumno.curp || '';
    form.fecha_nacimiento = alumno.fecha_nacimiento || '';
    form.edad = alumno.edad || '';
    form.nivel_educativo = normalizeNivel(alumno.nivel_educativo);
    form.grado = alumno.grado;
    form.grupo = alumno.grupo;
    form.discapacidad = alumno.discapacidad || '';
    form.comentarios = alumno.comentarios || '';
    form.foto_perfil = null;
    form._method = 'put';
    modalEditar.value = true;
};

const handleFotoChange = (e) => {
    form.foto_perfil = e.target.files[0];
};

const guardarEdicion = () => {
    if (modoEdicion.value) {
        form.post(route('alumnos.update', matriculaSeleccionada.value), {
            forceFormData: true,
            onSuccess: () => {
                modalEditar.value = false;
                Swal.fire({
                    title: '¡Actualizado!',
                    text: 'El expediente se ha modificado con éxito.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    customClass: { popup: 'rounded-2xl shadow-xl' }
                });
            }
        });
    } else {
        form.post(route('alumnos.store'), {
            forceFormData: true,
            onSuccess: () => {
                modalEditar.value = false;
                Swal.fire({
                    title: '¡Registrado!',
                    text: 'El alumno ha sido registrado con éxito.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    customClass: { popup: 'rounded-2xl shadow-xl' }
                });
            }
        });
    }
};
</script>

<template>
    <Head title="Panel de Control — FamilyLock" />

    <AuthenticatedLayout>
        <div class="p-6 md:p-8 max-w-7xl mx-auto">

            <!-- Header Section -->
            <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight leading-tight">
                        Panel de <span class="text-gradient-primary">Control</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-medium mt-1">
                        Centro de Atención Múltiple #48 &bull; Resumen de Actividad
                    </p>
                </div>

                <!-- Clock Card (Apple Widget Style) -->
                <div class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl px-4 py-2.5 rounded-2xl border border-slate-200/70 dark:border-white/10 shadow-sm flex items-center gap-3 self-start sm:self-auto">
                    <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                        <i class="far fa-clock text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-0.5">Hora Local</p>
                        <p class="text-sm font-black text-slate-800 dark:text-slate-100 font-mono tracking-tight leading-none">{{ horaActual }}</p>
                    </div>
                </div>
            </div>

            <!-- Proximo Evento Banner (iOS Reminder Banner Style) -->
            <div v-if="proximoEvento" class="mb-8 p-4 sm:p-5 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-500/20 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-2xl bg-amber-500/15 text-amber-500 dark:text-amber-400 flex items-center justify-center text-lg shrink-0">
                        <i class="fas fa-calendar-star animate-pulse"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Próxima Actividad</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 mt-0.5">{{ proximoEvento.nombre_evento }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex flex-wrap items-center gap-3">
                            <span><i class="far fa-calendar mr-1"></i> {{ new Date(proximoEvento.fecha_hora).toLocaleDateString('es-MX', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                            <span v-if="proximoEvento.ubicacion"><i class="fas fa-map-pin mr-1 text-slate-400"></i> {{ proximoEvento.ubicacion }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- KPI Indicator Cards (Apple Cards with Subtle Depth & Real Hierarchy) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">

                <!-- Alumnos Activos -->
                <div class="kpi-card group">
                    <div class="flex-1 min-w-0 pr-3">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-wide uppercase">
                            Alumnos Activos
                        </p>
                        <h3 class="kpi-number text-sky-600 dark:text-sky-400">
                            {{ stats.totalAlumnos }}
                        </h3>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span>
                            <span class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                Matrícula escolar
                            </span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-500/25 shadow-sm group-hover:bg-sky-100 dark:group-hover:bg-sky-500/20">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7" />
                        </svg>
                    </div>
                </div>

                <!-- Asistencias Hoy -->
                <div class="kpi-card group">
                    <div class="flex-1 min-w-0 pr-3">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-wide uppercase">
                            Asistencias Hoy
                        </p>
                        <h3 class="kpi-number text-emerald-600 dark:text-emerald-400">
                            {{ stats.asistenciasHoy }}
                        </h3>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                Registros escaneados
                            </span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/25 shadow-sm group-hover:bg-emerald-100 dark:group-hover:bg-emerald-500/20">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                </div>

                <!-- Pendientes del Día -->
                <div class="kpi-card group">
                    <div class="flex-1 min-w-0 pr-3">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-wide uppercase">
                            Pendientes Hoy
                        </p>
                        <h3 class="kpi-number text-amber-600 dark:text-amber-400">
                            {{ stats.pendientes }}
                        </h3>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                                Sin escaneo registrado
                            </span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-500/25 shadow-sm group-hover:bg-amber-100 dark:group-hover:bg-amber-500/20">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Tabla de Alumnos & Expedientes -->
            <div class="premium-card-static overflow-hidden">
                <!-- Toolbar -->
                <div class="p-5 sm:p-6 border-b border-slate-200/70 dark:border-white/[0.08] flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-start">
                        <div class="flex items-center gap-3">
                            <h2 class="font-display text-xl font-black text-slate-900 dark:text-white">Alumnos</h2>
                            <span class="badge badge-blue">
                                {{ alumnosFiltrados.length }} inscritos
                            </span>
                        </div>
                        <button @click="abrirModalNuevo" class="btn-premium shrink-0">
                            <i class="fas fa-plus text-xs"></i> <span>Nuevo Alumno</span>
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
                                <th>Matrícula</th>
                                <th>Nombre Completo</th>
                                <th>Nivel</th>
                                <th>Grado / Grupo</th>
                                <th>Diagnóstico</th>
                                <th>Tutor</th>
                                <th class="text-center print-hidden">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="alumno in alumnosFiltrados"
                                :key="alumno.matricula"
                                class="group"
                            >
                                <td>
                                    <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs bg-sky-50 dark:bg-sky-500/10 px-2 py-1 rounded-md border border-sky-200/50 dark:border-sky-500/20">
                                        {{ alumno.matricula }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0">
                                            <img 
                                                v-if="alumno.foto_perfil" 
                                                :src="`/storage/${alumno.foto_perfil}`" 
                                                alt="Foto" 
                                                class="w-full h-full object-cover"
                                            />
                                            <span v-else class="text-[10px] font-bold text-slate-500 uppercase">
                                                {{ alumno.nombre?.charAt(0) || 'A' }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase leading-tight">
                                                {{ alumno.nombre_completo || `${alumno.nombre} ${alumno.apellido}` }}
                                            </p>
                                            <p v-if="alumno.curp" class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                {{ alumno.curp }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-slate">
                                        {{ alumno.nivel_educativo || '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="alumno.grado || alumno.grupo" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        {{ alumno.grado }}<template v-if="alumno.grado && !isNaN(alumno.grado)">°</template> {{ alumno.grupo }}
                                    </span>
                                    <span v-else class="text-slate-400 text-xs">—</span>
                                </td>
                                <td>
                                    <span class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                        {{ alumno.discapacidad || 'Sin diagnóstico' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">
                                        {{ alumno.tutor || 'No asignado' }}
                                    </span>
                                </td>
                                <td class="print-hidden">
                                    <div class="table-actions">
                                        <button 
                                            @click="abrirModalEditar(alumno)" 
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white"
                                            title="Editar Expediente"
                                            aria-label="Editar Expediente"
                                        >
                                            <i class="fas fa-pen text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="alumnosFiltrados.length === 0">
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-search text-2xl"></i>
                                        </div>
                                        <p class="text-slate-700 dark:text-slate-300 font-bold text-base">No se encontraron alumnos</p>
                                        <p class="text-slate-400 text-xs mt-1">Prueba con otro término de búsqueda o registra un nuevo alumno.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Container -->
                <div v-if="alumnos.links" class="p-4 border-t border-slate-200/70 dark:border-white/[0.08] bg-slate-50/30 dark:bg-slate-900/20">
                    <Pagination :links="alumnos.links" />
                </div>
            </div>

        </div>

        <!-- Modal Editar / Registrar Alumno (Apple Dialog Design) -->
        <div v-if="modalEditar" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-md transition-opacity" @click="modalEditar = false"></div>

            <!-- Content Card -->
            <div class="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-10 max-h-[90vh] flex flex-col">
                <div class="modal-accent-bar"></div>

                <!-- Modal Header -->
                <div class="p-5 sm:p-6 border-b border-slate-200/70 dark:border-white/[0.08] flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                    <div>
                        <h3 class="font-display font-black text-lg text-slate-900 dark:text-white tracking-tight">
                            {{ modoEdicion ? 'Editar Expediente de Alumno' : 'Registrar Nuevo Alumno' }}
                        </h3>
                        <p v-if="modoEdicion" class="text-xs text-sky-600 dark:text-sky-400 font-mono font-bold mt-0.5">
                            Matrícula: {{ matriculaSeleccionada }}
                        </p>
                    </div>
                    <button 
                        @click="modalEditar = false" 
                        class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition-all active:scale-95"
                        aria-label="Cerrar modal"
                    >
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                
                <!-- Modal Body Form -->
                <form @submit.prevent="guardarEdicion" class="p-5 sm:p-6 overflow-y-auto custom-scroll flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="!modoEdicion">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Matrícula (CURP o ID) *</label>
                            <input type="text" v-model="form.matricula" class="input-premium w-full uppercase" placeholder="Ej. PRI-01, SEC-02" required autofocus>
                            <p v-if="form.errors.matricula" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.matricula }}</p>
                        </div>

                        <div :class="modoEdicion ? 'sm:col-span-1' : ''">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Nombres *</label>
                            <input type="text" v-model="form.nombre" class="input-premium w-full uppercase" placeholder="Ej. JUAN CARLOS" required :autofocus="modoEdicion">
                            <p v-if="form.errors.nombre" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.nombre }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Apellidos *</label>
                            <input type="text" v-model="form.apellido" class="input-premium w-full uppercase" placeholder="Ej. PÉREZ GARCÍA" required>
                            <p v-if="form.errors.apellido" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.apellido }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">CURP</label>
                            <input type="text" v-model="form.curp" class="input-premium w-full uppercase" placeholder="18 Caracteres" maxlength="18">
                            <p v-if="form.errors.curp" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.curp }}</p>
                        </div>

                        <div v-if="!modoEdicion">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Sexo</label>
                            <select v-model="form.sexo" class="input-premium w-full cursor-pointer">
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Nivel Educativo</label>
                            <select v-model="form.nivel_educativo" class="input-premium w-full cursor-pointer">
                                <option value="">Seleccione nivel...</option>
                                <option value="Preescolar">Preescolar</option>
                                <option value="Primaria">Primaria</option>
                                <option value="Secundaria">Secundaria</option>
                                <option value="Formación Laboral">Formación Laboral</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Grado</label>
                                <input type="text" v-model="form.grado" class="input-premium w-full" placeholder="Ej. 1, 2">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Grupo</label>
                                <input type="text" v-model="form.grupo" class="input-premium w-full uppercase" placeholder="Ej. A, B">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Nacimiento</label>
                                <input type="date" v-model="form.fecha_nacimiento" class="input-premium w-full cursor-pointer">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Edad</label>
                                <input type="number" min="0" max="99" v-model="form.edad" class="input-premium w-full" placeholder="Años">
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Discapacidad / Diagnóstico</label>
                            <input type="text" v-model="form.discapacidad" class="input-premium w-full" placeholder="Ej. Autismo, Motriz, Intelectual...">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Observaciones (Opcional)</label>
                            <textarea v-model="form.comentarios" rows="2" class="input-premium w-full resize-none" placeholder="Observaciones adicionales..."></textarea>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">Fotografía del Alumno (Opcional)</label>
                            <input 
                                type="file" 
                                @change="handleFotoChange" 
                                accept="image/jpeg, image/png, image/jpg" 
                                class="input-premium w-full file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 cursor-pointer"
                            >
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 pt-4 border-t border-slate-200/70 dark:border-white/[0.08] flex justify-end gap-3">
                        <button type="button" @click="modalEditar = false" class="btn-ghost">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" class="btn-premium">
                            <i class="fas fa-circle-notch fa-spin text-xs" v-if="form.processing"></i> 
                            <i class="fas fa-check text-xs" v-else></i>
                            <span>{{ modoEdicion ? 'Guardar Cambios' : 'Registrar Alumno' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
