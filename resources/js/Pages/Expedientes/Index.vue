<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import Swal from "sweetalert2";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    archivos: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    alumnos: {
        type: Array,
        default: () => [],
    },
    filtros: {
        type: Object,
        default: () => ({ busqueda: '' }),
    },
});

const busqueda = ref(props.filtros.busqueda || "");
const fileInput = ref(null);
const fileRefName = ref("Ningún archivo seleccionado");

const form = useForm({
    matricula: "",
    archivo: null,
});

const mostrarVisor = ref(false);
const archivoSeleccionado = ref(null);

const abrirVisor = (archivo) => {
    archivoSeleccionado.value = archivo;
    mostrarVisor.value = true;
};

const cerrarVisor = () => {
    mostrarVisor.value = false;
    setTimeout(() => {
        archivoSeleccionado.value = null;
    }, 250);
};

// Búsqueda en tiempo real
let timeoutId = null;
watch(busqueda, (value) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        router.get(
            route("expedientes.index"),
            { busqueda: value },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 300);
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            Swal.fire({
                title: "Archivo muy grande",
                text: "El límite máximo permitido es 10MB por documento.",
                icon: "warning",
                customClass: { popup: "rounded-2xl shadow-xl" },
            });
            e.target.value = "";
            form.archivo = null;
            fileRefName.value = "Ningún archivo seleccionado";
            return;
        }
        form.archivo = file;
        fileRefName.value = file.name;
    } else {
        form.archivo = null;
        fileRefName.value = "Ningún archivo seleccionado";
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const subirDocumento = () => {
    if (!form.matricula) {
        Swal.fire({
            title: "Selecciona un Alumno",
            text: "Elige el alumno titular antes de subir el documento.",
            icon: "warning",
            customClass: { popup: "rounded-2xl" },
        });
        return;
    }
    if (!form.archivo) {
        Swal.fire({
            title: "Selecciona un Archivo",
            text: "Por favor adjunta un archivo PDF o imagen.",
            icon: "warning",
            customClass: { popup: "rounded-2xl" },
        });
        return;
    }

    form.post(route("expedientes.store"), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = "";
            fileRefName.value = "Ningún archivo seleccionado";
            Swal.fire({
                title: "¡Documento Anexado!",
                text: "El archivo se ha guardado en el expediente digital.",
                icon: "success",
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 3000,
                customClass: { popup: "rounded-2xl" },
            });
        },
        onError: () => {
            if (form.errors.archivo) {
                Swal.fire("Error", form.errors.archivo, "error");
            }
        },
    });
};

const eliminarDocumento = (id) => {
    Swal.fire({
        title: "¿Eliminar documento?",
        text: "Se borrará permanentemente del expediente.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        customClass: {
            popup: "swal2-popup",
            confirmButton: "bg-rose-600 text-white font-bold px-5 py-2.5 rounded-xl",
            cancelButton: "btn-ghost",
        },
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("expedientes.destroy", id));
        }
    });
};

const getIconForMime = (mime) => {
    if (!mime) return "fa-file-alt text-slate-400";
    if (mime.includes("pdf")) return "fa-file-pdf text-rose-500";
    if (mime.includes("image")) return "fa-file-image text-sky-500";
    if (mime.includes("word")) return "fa-file-word text-blue-600";
    if (mime.includes("zip") || mime.includes("rar")) return "fa-file-archive text-amber-500";
    return "fa-file-alt text-slate-400";
};
</script>

<template>
    <Head title="Expedientes Digitales — FamilyLock" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-lg text-slate-900 dark:text-white">Expedientes</h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                    Expediente <span class="text-gradient-primary">Digital</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 font-medium text-xs sm:text-sm mt-1">
                    Repositorio de documentos, actas y certificados de los alumnos del CAM #48.
                </p>
            </div>

            <!-- Upload Zone Card (Apple Card Style) -->
            <div class="premium-card p-6 mb-8">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-sm">
                        <i class="fas fa-cloud-arrow-up"></i>
                    </div>
                    <div>
                        <h2 class="font-display font-black text-base text-slate-900 dark:text-white">
                            Subir Nuevo Documento
                        </h2>
                        <p class="text-xs text-slate-400 font-medium">Adjunta archivos PDF o imágenes (máximo 10MB por documento).</p>
                    </div>
                </div>

                <form @submit.prevent="subirDocumento" class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <!-- Student Selector -->
                    <div class="md:col-span-5 flex flex-col">
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Alumno Titular *
                        </label>
                        <select v-model="form.matricula" class="input-premium w-full cursor-pointer text-xs py-2.5">
                            <option value="" disabled>Seleccione un alumno...</option>
                            <option v-for="alumno in alumnos" :key="alumno.matricula" :value="alumno.matricula">
                                {{ alumno.nombre }} {{ alumno.apellido }} ({{ alumno.matricula }})
                            </option>
                        </select>
                    </div>

                    <!-- File Picker Area -->
                    <div class="md:col-span-5 flex flex-col">
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Archivo a Adjuntar
                        </label>
                        <input
                            type="file"
                            ref="fileInput"
                            @change="handleFileChange"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip,.rar"
                            class="hidden"
                        />
                        <div
                            @click="triggerFileInput"
                            class="input-premium w-full cursor-pointer flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300 hover:border-primary py-2.5"
                        >
                            <i class="fas fa-paperclip text-slate-400"></i>
                            <span class="truncate flex-1 font-medium">{{ fileRefName }}</span>
                            <span class="text-[10px] font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">Examinar</span>
                        </div>
                    </div>

                    <!-- Upload Action Button -->
                    <div class="md:col-span-2 flex flex-col justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn-premium w-full py-2.5 text-xs font-bold"
                        >
                            <i class="fas fa-circle-notch fa-spin" v-if="form.processing"></i>
                            <i class="fas fa-upload" v-else></i>
                            <span>{{ form.processing ? 'Subiendo...' : 'Anexar' }}</span>
                        </button>
                    </div>
                </form>

                <!-- Upload Progress -->
                <div v-if="form.progress" class="mt-4">
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-primary h-1.5 rounded-full transition-all duration-200" :style="`width: ${form.progress.percentage}%`"></div>
                    </div>
                    <p class="text-[10px] text-right mt-1 font-semibold text-slate-400">
                        Cargando {{ form.progress.percentage }}%
                    </p>
                </div>
            </div>

            <!-- Documents Table Card -->
            <div class="premium-card-static overflow-hidden">
                <div class="p-5 border-b border-slate-200/70 dark:border-white/[0.08] flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="relative w-full sm:w-80">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input
                            type="text"
                            v-model="busqueda"
                            placeholder="Buscar documento o alumno..."
                            class="input-premium w-full pl-9 pr-4 py-2 text-xs"
                        />
                    </div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {{ archivos.data.length }} documentos listados
                    </span>
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
                            <tr v-for="archivo in archivos.data" :key="archivo.id" class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-white/10 flex items-center justify-center text-base shrink-0">
                                            <i class="fas" :class="getIconForMime(archivo.tipo_mime)"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-800 dark:text-slate-100 text-xs max-w-xs truncate">
                                                {{ archivo.nombre_original }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div v-if="archivo.alumno">
                                        <p class="font-bold text-sky-600 dark:text-sky-400 text-xs uppercase leading-tight">
                                            {{ archivo.alumno.nombre }} {{ archivo.alumno.apellido }}
                                        </p>
                                        <p class="text-[10px] font-mono text-slate-400 mt-0.5">
                                            {{ archivo.alumno.matricula }}
                                        </p>
                                    </div>
                                    <span v-else class="text-rose-500 text-xs font-bold">Sin alumno</span>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-700 dark:text-slate-300 text-xs">
                                        {{ archivo.fecha_formateada }}
                                    </p>
                                    <p class="text-[10px] font-mono text-slate-400 mt-0.5">
                                        {{ archivo.tamano_legible }}
                                    </p>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button
                                            @click="abrirVisor(archivo)"
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white"
                                            title="Ver Documento"
                                            aria-label="Ver Documento"
                                        >
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                        <button
                                            @click="eliminarDocumento(archivo.id)"
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-rose-600 hover:text-white"
                                            title="Eliminar Documento"
                                            aria-label="Eliminar Documento"
                                        >
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="archivos.data.length === 0">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-folder-open text-2xl"></i>
                                        </div>
                                        <p class="text-slate-700 dark:text-slate-300 font-bold text-sm">El expediente digital está vacío</p>
                                        <p class="text-slate-400 text-xs mt-1">Sube documentos para comenzar a gestionar el archivo digital.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="archivos.links" class="p-4 border-t border-slate-200/70 dark:border-white/[0.08] bg-slate-50/30 dark:bg-slate-900/20">
                    <Pagination :links="archivos.links" />
                </div>
            </div>

        </div>

        <!-- Visor Quick Look (Apple macOS QuickLook Style) -->
        <div v-if="mostrarVisor" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6">
            <div class="fixed inset-0 bg-slate-950/75 backdrop-blur-md" @click="cerrarVisor"></div>

            <div class="relative w-full h-[90vh] max-w-5xl flex flex-col bg-slate-900 rounded-3xl shadow-2xl overflow-hidden border border-white/10 z-10">
                <!-- Viewer Header -->
                <div class="flex items-center justify-between px-5 py-3.5 bg-slate-800/90 border-b border-white/10 shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-slate-700 text-slate-200 flex items-center justify-center text-sm shrink-0">
                            <i class="fas" :class="getIconForMime(archivoSeleccionado?.tipo_mime)"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-white font-bold text-xs sm:text-sm truncate">
                                {{ archivoSeleccionado?.nombre_original }}
                            </h3>
                            <p class="text-slate-400 text-[11px] font-medium truncate">
                                {{ archivoSeleccionado?.alumno?.nombre }} {{ archivoSeleccionado?.alumno?.apellido }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            :href="archivoSeleccionado?.url"
                            target="_blank"
                            class="btn-ghost py-1.5 px-3 text-xs bg-slate-700 text-slate-200 hover:bg-slate-600 border-white/10"
                        >
                            <i class="fas fa-external-link-alt text-xs mr-1"></i>
                            <span class="hidden sm:inline">Abrir en Pestaña</span>
                        </a>
                        <button
                            @click="cerrarVisor"
                            class="w-8 h-8 rounded-lg bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition-colors"
                            aria-label="Cerrar visor"
                        >
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Viewer Content -->
                <div class="flex-1 bg-slate-950 relative w-full h-full">
                    <iframe
                        v-if="archivoSeleccionado"
                        :src="archivoSeleccionado.url"
                        class="w-full h-full border-0 absolute inset-0 bg-white"
                        title="Visor de Documentos"
                    ></iframe>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
