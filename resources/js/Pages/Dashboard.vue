<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    stats: Object,
    alumnos: Object,
    proximoEvento: Object,
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
    if (!busqueda.value) return props.alumnos.data;
    const q = busqueda.value.toLowerCase();
    return props.alumnos.data.filter(a =>
        (a.nombre && String(a.nombre).toLowerCase().includes(q)) ||
        (a.apellido && String(a.apellido).toLowerCase().includes(q)) ||
        (a.matricula && String(a.matricula).toLowerCase().includes(q))
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
    nivel_educativo: '',
    grado: '',
    grupo: '',
    discapacidad: '',
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
    form.nivel_educativo = '';
    form.grado = '';
    form.grupo = '';
    form.discapacidad = '';
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
    form.nivel_educativo = normalizeNivel(alumno.nivel_educativo);
    form.grado = alumno.grado;
    form.grupo = alumno.grupo;
    form.discapacidad = alumno.discapacidad;
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
                    timer: 3000
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
                    timer: 3000
                });
            }
        });
    }
};
</script>

<template>
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <div class="p-6 md:p-8 max-w-7xl mx-auto">

            <!-- Header -->
            <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4 animate-fade-in-up">
                <div>
                    <h1 class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight">
                        Panel de <span class="text-gradient-primary">Control</span>
                    </h1>
                    <p class="text-slate-400 text-sm font-bold mt-1">
                        Centro de Atención Múltiple #48 — Resumen del día
                    </p>
                </div>
                <div class="bg-white dark:bg-slate-800/80 px-5 py-3 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 flex items-center gap-4 self-start md:self-auto backdrop-blur-md">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i class="fas fa-clock text-primary text-lg"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest leading-none mb-1">Hora Actual</p>
                        <p class="text-xl font-black text-slate-700 dark:text-slate-200 leading-none font-display">{{ horaActual }}</p>
                    </div>
                </div>
            </div>

            <!-- Recordatorio Próximo Evento -->
            <div v-if="proximoEvento" class="mb-8 p-4 md:p-6 bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-500/10 dark:to-amber-500/10 border border-orange-100 dark:border-orange-500/20 rounded-3xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 animate-fade-in-up">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-orange-100 dark:bg-orange-500/20 text-orange-500 flex items-center justify-center text-xl shrink-0 shadow-inner">
                        <i class="fas fa-bell animate-pulse"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-orange-500 dark:text-orange-400 mb-0.5">Próximo Evento Programado</p>
                        <h3 class="text-lg font-black text-slate-800 dark:text-slate-200">{{ proximoEvento.nombre_evento }}</h3>
                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-3">
                            <span><i class="fas fa-calendar-day mr-1"></i> {{ new Date(proximoEvento.fecha_hora).toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                            <span v-if="proximoEvento.ubicacion"><i class="fas fa-map-marker-alt mr-1"></i> {{ proximoEvento.ubicacion }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 stagger-children">

                <!-- Alumnos Activos -->
                <div class="kpi-card group">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Alumnos Activos</p>
                        <h3 class="kpi-number text-slate-800 dark:text-white">{{ stats.totalAlumnos }}</h3>
                        <span class="text-xs font-bold text-primary mt-1 inline-block">Inscritos en el ciclo</span>
                    </div>
                    <div class="kpi-icon bg-primary/10">
                        <i class="fas fa-graduation-cap text-2xl text-primary"></i>
                    </div>
                </div>

                <!-- Asistencias Hoy -->
                <div class="kpi-card group">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Asistencias Hoy</p>
                        <h3 class="kpi-number text-emerald-500">{{ stats.asistenciasHoy }}</h3>
                        <span class="text-xs font-bold text-emerald-500 mt-1 inline-block">Registros escaneados</span>
                    </div>
                    <div class="kpi-icon bg-emerald-500/10">
                        <i class="fas fa-qrcode text-2xl text-emerald-500"></i>
                    </div>
                </div>

                <!-- Pendientes -->
                <div class="kpi-card group">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Pendientes</p>
                        <h3 class="kpi-number text-amber-500">{{ stats.pendientes }}</h3>
                        <span class="text-xs font-bold text-amber-500 mt-1 inline-block">Sin registro hoy</span>
                    </div>
                    <div class="kpi-icon bg-amber-500/10">
                        <i class="fas fa-clock text-2xl text-amber-500"></i>
                    </div>
                </div>
            </div>

            <!-- Tabla de Expedientes -->
            <div class="premium-card-static overflow-hidden animate-fade-in-up" style="animation-delay: 200ms">
                <div class="p-6 md:p-8 border-b border-slate-100/80 dark:border-slate-700/50 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="font-display text-2xl font-black text-slate-800 dark:text-white">Expedientes</h2>
                        <span class="badge badge-blue hidden md:inline-block">
                            {{ alumnosFiltrados.length }} registros
                        </span>
                        <button @click="abrirModalNuevo" class="btn-premium shrink-0">
                            <i class="fas fa-user-plus mr-1"></i> <span class="hidden md:inline">Nuevo Alumno</span>
                        </button>
                    </div>
                    <div class="relative w-full md:w-96">
                        <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                        <input
                            type="text"
                            v-model="busqueda"
                            placeholder="Buscar por nombre o matrícula..."
                            class="input-premium w-full pl-12 text-sm"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Nombre Completo</th>
                                <th>Nivel</th>
                                <th>Grado</th>
                                <th>Discapacidad</th>
                                <th>Tutor</th>
                                <th class="text-center print-hidden">Acciones</th>
                            </tr>
                        </thead>
                        <tbody v-auto-animate>
                            <tr
                                v-for="alumno in alumnosFiltrados"
                                :key="alumno.matricula"
                            >
                                <td>
                                    <span class="font-mono font-black text-primary text-sm">{{ alumno.matricula }}</span>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-700 dark:text-slate-200 uppercase text-sm">{{ alumno.nombre_completo }}</p>
                                </td>
                                <td>
                                    <span class="badge badge-slate">
                                        {{ alumno.nivel_educativo || '—' }}
                                    </span>
                                </td>
                                <td class="font-bold text-slate-600 dark:text-slate-300 text-sm">
                                    <span v-if="alumno.grado || alumno.grupo">
                                        {{ alumno.grado }}<template v-if="alumno.grado && !isNaN(alumno.grado)">°</template> {{ alumno.grupo }}
                                    </span>
                                    <span v-else class="text-slate-400 dark:text-slate-500">—</span>
                                </td>
                                <td>
                                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">{{ alumno.discapacidad || 'Ninguna' }}</span>
                                </td>
                                <td>
                                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">{{ alumno.tutor }}</span>
                                </td>
                                <td class="print-hidden">
                                    <div class="flex justify-center">
                                        <button @click="abrirModalEditar(alumno)" class="table-action-btn bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 hover:bg-primary hover:text-white dark:hover:text-white" v-tippy="'Editar Expediente'">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="alumnosFiltrados.length === 0">
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-search text-3xl"></i>
                                        </div>
                                        <p class="text-slate-500 font-bold text-lg">Sin resultados</p>
                                        <p class="text-slate-400 text-sm mt-1">No se encontraron alumnos con esa búsqueda.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700/50">
                    <Pagination :links="alumnos.links" />
                </div>
            </div>

        </div>

        <!-- Modal Editar Alumno -->
        <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="modalEditar" class="modal-overlay fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="modal-content bg-white dark:bg-slate-800 w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden" @click.stop>
                    <div class="p-6 border-b border-slate-100 dark:border-slate-700/50 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="font-display font-black text-xl text-slate-800 dark:text-white">
                            {{ modoEdicion ? 'Editar Expediente' : 'Nuevo Alumno' }}
                            <span v-if="modoEdicion" class="text-primary">{{ matriculaSeleccionada }}</span>
                        </h3>
                        <button @click="modalEditar = false" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 hover:bg-red-500 hover:text-white dark:hover:bg-red-500 transition-colors" v-tippy="'Cerrar'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form @submit.prevent="guardarEdicion" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-if="!modoEdicion">
                                <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Matrícula (CURP o ID)</label>
                                <input type="text" v-model="form.matricula" class="input-premium w-full uppercase" placeholder="Ej. PRI-01, SEC-02, LAB-03" required autofocus>
                                <p v-if="form.errors.matricula" class="text-red-500 text-xs font-bold mt-1">{{ form.errors.matricula }}</p>
                            </div>
                            <div :class="modoEdicion ? 'md:col-span-1' : ''">
                                <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Nombres</label>
                                <input type="text" v-model="form.nombre" class="input-premium w-full uppercase" placeholder="Ej. JUAN CARLOS" required :autofocus="modoEdicion">
                            </div>
                            <div>
                                <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Apellidos</label>
                                <input type="text" v-model="form.apellido" class="input-premium w-full uppercase" placeholder="Ej. PÉREZ GARCÍA" required>
                            </div>
                            <div v-if="!modoEdicion">
                                <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Sexo</label>
                                <select v-model="form.sexo" class="input-premium w-full cursor-pointer">
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Nivel Educativo</label>
                                <select v-model="form.nivel_educativo" class="input-premium w-full">
                                    <option value="">Seleccione un nivel...</option>
                                    <option value="Preescolar">Preescolar</option>
                                    <option value="Primaria">Primaria</option>
                                    <option value="Secundaria">Secundaria</option>
                                    <option value="Formación Laboral">Formación Laboral</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Grado</label>
                                    <input type="text" v-model="form.grado" class="input-premium w-full" placeholder="Ej. 1, 2, 3">
                                </div>
                                <div>
                                    <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Grupo</label>
                                    <input type="text" v-model="form.grupo" class="input-premium w-full uppercase" placeholder="Ej. A, B">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Discapacidad / Diagnóstico</label>
                                <input type="text" v-model="form.discapacidad" class="input-premium w-full" placeholder="Ej. Autismo, Motriz, Intelectual...">
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2 block">Foto de Perfil (Opcional)</label>
                                <input type="file" @change="handleFotoChange" accept="image/jpeg, image/png, image/jpg" class="input-premium w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end gap-3">
                            <button type="button" @click="modalEditar = false" class="btn-secondary">Cancelar</button>
                            <button type="submit" :disabled="form.processing" class="btn-premium">
                                <i class="fas fa-spinner fa-spin mr-2" v-if="form.processing"></i> 
                                <i class="fas fa-save mr-2" v-else></i>
                                {{ modoEdicion ? 'Guardar Cambios' : 'Registrar Alumno' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>
