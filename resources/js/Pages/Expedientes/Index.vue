<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    archivos: Array,
    alumnos: Array,
    filtros: Object
});

const busqueda = ref(props.filtros.busqueda || '');
const fileInput = ref(null);
const fileRefName = ref('Ningún archivo seleccionado');

const form = useForm({
    matricula: '',
    archivo: null
});

const mostrarVisor = ref(false);
const archivoSeleccionado = ref(null);

const abrirVisor = (archivo) => {
    archivoSeleccionado.value = archivo;
    mostrarVisor.value = true;
};

const cerrarVisor = () => {
    mostrarVisor.value = false;
    setTimeout(() => { archivoSeleccionado.value = null; }, 300);
};

// Búsqueda en tiempo real
let timeoutId = null;
watch(busqueda, (value) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        router.get(route('expedientes.index'), { busqueda: value }, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        // Validar cliente: max 10MB
        if (file.size > 10 * 1024 * 1024) {
            Swal.fire({
                title: 'Archivo muy grande',
                text: 'El límite máximo es 10MB por documento.',
                icon: 'error',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl font-bold px-6 py-3' }
            });
            e.target.value = '';
            form.archivo = null;
            fileRefName.value = 'Ningún archivo seleccionado';
            return;
        }
        form.archivo = file;
        fileRefName.value = file.name;
    } else {
        form.archivo = null;
        fileRefName.value = 'Ningún archivo seleccionado';
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const subirDocumento = () => {
    if (!form.matricula) {
        Swal.fire('Atención', 'Selecciona el alumno antes de subir el documento.', 'warning');
        return;
    }
    if (!form.archivo) {
        Swal.fire('Atención', 'Selecciona un archivo PDF o Imagen.', 'warning');
        return;
    }

    form.post(route('expedientes.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if(fileInput.value) fileInput.value.value = '';
            fileRefName.value = 'Ningún archivo seleccionado';
            Swal.fire({
                title: '¡Subido!',
                text: 'El documento se anexó al expediente digital.',
                icon: 'success',
                confirmButtonColor: '#009FE3',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl font-bold px-6 py-3' }
            });
        },
        onError: () => {
            if(form.errors.archivo) Swal.fire('Error', form.errors.archivo, 'error');
        }
    });
};

const eliminarDocumento = (id) => {
    Swal.fire({
        title: '¿Eliminar documento?',
        text: "Se borrará físicamente del servidor y no podrá recuperarse.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'rounded-3xl',
            confirmButton: 'rounded-xl font-bold px-6 py-3',
            cancelButton: 'rounded-xl font-bold px-6 py-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('expedientes.destroy', id));
        }
    });
};

const getIconForMime = (mime) => {
    if (mime.includes('pdf')) return 'fa-file-pdf text-red-500';
    if (mime.includes('image')) return 'fa-file-image text-blue-500';
    if (mime.includes('word')) return 'fa-file-word text-blue-700';
    if (mime.includes('zip') || mime.includes('rar')) return 'fa-file-archive text-amber-500';
    return 'fa-file-alt text-slate-500';
};
</script>

<template>
    <Head title="Expediente Digital" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-xl text-slate-800 dark:text-white tracking-tight">Expediente <span class="text-primary">Digital</span></h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto animate-fade-in-up">
            
            <div class="mb-8 hidden md:block">
                <h1 class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight">
                    Expediente <span class="text-primary">Digital</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 font-bold text-sm mt-1">Almacenamiento en la nube y control de documentos de los alumnos.</p>
            </div>

            <!-- Panel de Carga Rápida -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-slate-800/80 dark:to-slate-900/80 border border-blue-100 dark:border-slate-700/50 p-6 md:p-8 rounded-3xl shadow-sm mb-8">
                <h2 class="font-black text-blue-900 dark:text-slate-200 text-lg uppercase tracking-widest mb-6 flex items-center gap-2">
                    <i class="fas fa-cloud-upload-alt text-primary text-xl"></i> Subir Nuevo Documento
                </h2>
                
                <form @submit.prevent="subirDocumento" class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <div class="md:col-span-4 flex flex-col">
                        <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Alumno Titular</label>
                        <select v-model="form.matricula" class="input-premium w-full h-12 py-0">
                            <option value="" disabled>Seleccionar un alumno...</option>
                            <option v-for="alumno in alumnos" :key="alumno.matricula" :value="alumno.matricula">
                                {{ alumno.nombre }} {{ alumno.apellido }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-6 flex flex-col">
                        <label class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Seleccionar Archivo (Máx 10MB)</label>
                        <input type="file" ref="fileInput" @change="handleFileChange" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar" class="hidden">
                        <div @click="triggerFileInput" class="w-full bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50 border-dashed rounded-2xl px-4 py-3 text-sm font-bold text-slate-500 hover:border-primary hover:text-primary cursor-pointer flex items-center gap-3 transition-colors shadow-sm h-12">
                            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center shrink-0 text-slate-400 group-hover:text-primary">
                                <i class="fas fa-paperclip"></i>
                            </div>
                            <span class="truncate flex-1">{{ fileRefName }}</span>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex flex-col justify-end">
                        <button type="submit" :disabled="form.processing" class="w-full h-12 bg-primary hover:bg-blue-600 text-white rounded-2xl font-black shadow-md shadow-primary/20 transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                            <i class="fas fa-upload" v-if="!form.processing"></i>
                            <i class="fas fa-spinner fa-spin" v-else></i>
                            <span class="hidden md:inline">Subir</span>
                        </button>
                    </div>
                </form>
                
                <div v-if="form.progress" class="mt-4">
                    <div class="w-full bg-slate-200 rounded-full h-2">
                        <div class="bg-primary h-2 rounded-full transition-all duration-300" :style="`width: ${form.progress.percentage}%`"></div>
                    </div>
                    <p class="text-xs text-center mt-2 font-bold text-slate-500">Subiendo {{ form.progress.percentage }}%</p>
                </div>
            </div>

            <!-- Tabla de Archivos -->
            <div class="premium-card overflow-hidden">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30 flex justify-between items-center">
                    <div class="relative w-full md:w-96">
                        <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 dark:text-slate-500"></i>
                        <input type="text" v-model="busqueda" placeholder="Buscar documento o alumno..." 
                               class="input-premium w-full pl-12 text-sm">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Alumno Titular</th>
                                <th>Fecha / Tamaño</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="archivo in archivos" :key="archivo.id" class="group">
                                <td>
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-xl shrink-0 shadow-sm border border-slate-200/50 dark:border-slate-600/50">
                                            <i class="fas" :class="getIconForMime(archivo.tipo_mime)"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-slate-200 text-sm max-w-[200px] md:max-w-[300px] truncate">{{ archivo.nombre_original }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div v-if="archivo.alumno">
                                        <p class="font-bold text-primary text-sm uppercase">{{ archivo.alumno.nombre }} {{ archivo.alumno.apellido }}</p>
                                        <p class="text-[10px] font-mono text-slate-500 dark:text-slate-400 font-bold mt-0.5">{{ archivo.alumno.matricula }}</p>
                                    </div>
                                    <span v-else class="text-red-500 text-xs font-bold uppercase">Alumno Desconocido</span>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-700 dark:text-slate-300 text-xs">{{ archivo.fecha_formateada }}</p>
                                    <p class="text-[10px] font-black text-slate-400 mt-1">{{ archivo.tamano_legible }}</p>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button @click="abrirVisor(archivo)" class="table-action-btn bg-sky-50 text-sky-500 hover:bg-sky-500 hover:text-white dark:bg-sky-500/10 dark:text-sky-400" title="Ver Documento">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="eliminarDocumento(archivo.id)" class="table-action-btn bg-slate-100 text-slate-500 hover:bg-red-500 hover:text-white dark:bg-red-500/10 dark:text-red-400" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="archivos.length === 0">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-folder-open text-2xl"></i>
                                        </div>
                                        <p class="text-slate-500 font-bold">El expediente digital está vacío.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Visor Premium (Estilo Classroom) -->
        <div v-if="mostrarVisor" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-6 bg-slate-900/80 backdrop-blur-md transition-opacity">
            <div class="absolute inset-0" @click="cerrarVisor"></div>
            
            <div class="relative w-full h-full sm:h-[90vh] md:w-11/12 lg:w-10/12 flex flex-col bg-slate-900 sm:rounded-3xl shadow-[0_0_50px_rgba(0,0,0,0.5)] overflow-hidden border border-slate-700 animate-scale-in">
                
                <!-- Header del Visor -->
                <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 bg-slate-800 border-b border-slate-700/50 relative z-10 shadow-lg">
                    <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-slate-700 text-slate-200 flex items-center justify-center shrink-0 border border-slate-600 shadow-inner">
                            <i class="fas text-lg" :class="getIconForMime(archivoSeleccionado?.tipo_mime)"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-white font-black text-sm sm:text-base tracking-wide truncate">{{ archivoSeleccionado?.nombre_original }}</h3>
                            <p class="text-slate-400 text-xs font-bold mt-0.5 truncate uppercase flex items-center gap-1.5">
                                <i class="fas fa-user text-[10px]"></i> {{ archivoSeleccionado?.alumno?.nombre }} {{ archivoSeleccionado?.alumno?.apellido }}
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0 pl-2">
                        <a :href="archivoSeleccionado?.url" target="_blank" class="h-9 px-4 bg-slate-700 hover:bg-sky-500 text-white text-xs font-black uppercase tracking-widest rounded-xl transition-colors flex items-center gap-2 shadow-sm border border-slate-600 hover:border-sky-400">
                            <i class="fas fa-external-link-alt"></i> <span class="hidden sm:inline">Nueva Pestaña</span>
                        </a>
                        <button @click="cerrarVisor" class="w-9 h-9 rounded-xl bg-slate-700 border border-slate-600 text-slate-300 hover:bg-red-500 hover:border-red-500 hover:text-white flex items-center justify-center transition-colors shadow-sm">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Contenedor del Iframe -->
                <div class="flex-1 bg-slate-950 relative w-full h-full overflow-hidden flex items-center justify-center">
                    <div v-if="!archivoSeleccionado" class="flex flex-col items-center justify-center text-slate-600">
                        <i class="fas fa-spinner fa-spin text-4xl mb-4"></i>
                        <p class="font-bold">Cargando visor...</p>
                    </div>
                    <iframe v-else :src="archivoSeleccionado.url" class="w-full h-full border-0 absolute inset-0 bg-white" title="Visor de Documentos"></iframe>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
