<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import Swal from "sweetalert2";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    tutores: {
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
const mostrarModal = ref(false);
const mostrarModalMensaje = ref(false);
const tutorSeleccionado = ref(null);

const form = useForm({
    matricula_alumno: "",
    t1_nombre: "",
    t1_telefono: "",
    t1_email: "",
    t1_telegram_chat_id: "",
    t1_foto_perfil: null,
    _method: "post",
});

const formMensaje = useForm({
    mensaje: "",
});

// Búsqueda en tiempo real
let timeoutId = null;
watch(busqueda, (value) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        router.get(
            route("tutores.index"),
            { busqueda: value },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 300);
});

const abrirModalNuevo = () => {
    form.reset();
    form.t1_foto_perfil = null;
    mostrarModal.value = true;
};

const abrirModalMensaje = (tutor) => {
    tutorSeleccionado.value = tutor;
    formMensaje.reset();
    mostrarModalMensaje.value = true;
};

const enviarMensaje = () => {
    formMensaje.post(route("tutores.mensaje", tutorSeleccionado.value.id), {
        onSuccess: () => {
            mostrarModalMensaje.value = false;
            Swal.fire({
                title: "¡Enviado!",
                text: "El mensaje ha sido enviado por Telegram exitosamente.",
                icon: "success",
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 3000,
                customClass: { popup: "rounded-2xl" },
            });
        },
    });
};

const enviarInvitacion = (tutor) => {
    Swal.fire({
        title: "¿Enviar Invitación?",
        text: `Se enviará un correo a ${tutor.t1_email || "..."} con el enlace para vincular Telegram.`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#009FE3",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Sí, enviar invitación",
        cancelButtonText: "Cancelar",
        customClass: {
            popup: "swal2-popup",
            confirmButton: "btn-premium",
            cancelButton: "btn-ghost",
        },
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(
                route("tutores.invitacion", tutor.id),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire({
                            title: "¡Invitación Despachada!",
                            text: "El correo electrónico ha sido enviado al tutor.",
                            icon: "success",
                            toast: true,
                            position: "top-end",
                            showConfirmButton: false,
                            timer: 3000,
                            customClass: { popup: "rounded-2xl" },
                        });
                    },
                },
            );
        }
    });
};

const abrirModalEditar = (tutor) => {
    form.matricula_alumno = tutor.matricula_alumno;
    form.t1_nombre = tutor.t1_nombre;
    form.t1_telefono = tutor.t1_telefono;
    form.t1_email = tutor.t1_email || "";
    form.t1_telegram_chat_id = tutor.t1_telegram_chat_id || "";
    form.t1_foto_perfil = null;
    mostrarModal.value = true;
};

const handleFotoChange = (e) => {
    form.t1_foto_perfil = e.target.files[0];
};

const guardarTutor = () => {
    form.post(route("tutores.store"), {
        forceFormData: true,
        onSuccess: () => {
            mostrarModal.value = false;
            Swal.fire({
                title: "¡Guardado!",
                text: "La información del tutor ha sido actualizada.",
                icon: "success",
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 3000,
                customClass: { popup: "rounded-2xl" },
            });
        },
    });
};

const eliminarTutor = (id) => {
    Swal.fire({
        title: "¿Eliminar tutor asignado?",
        text: "El alumno quedará temporalmente sin tutor registrado.",
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
            router.delete(route("tutores.destroy", id));
        }
    });
};
</script>

<template>
    <Head title="Directorio de Tutores — FamilyLock" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-lg text-slate-900 dark:text-white">Tutores</h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                        Directorio de <span class="text-gradient-primary">Tutores</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-medium text-xs sm:text-sm mt-1">
                        Padres de familia, responsables legales y contactos de emergencia del CAM #48.
                    </p>
                </div>

                <button @click="abrirModalNuevo" class="btn-premium flex items-center gap-2">
                    <i class="fas fa-plus text-xs"></i> <span>Registrar Tutor</span>
                </button>
            </div>

            <!-- Table Card -->
            <div class="premium-card-static overflow-hidden">
                <div class="p-5 border-b border-slate-200/70 dark:border-white/[0.08] flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="relative w-full sm:w-80">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input
                            type="text"
                            v-model="busqueda"
                            placeholder="Buscar por nombre o teléfono..."
                            class="input-premium w-full pl-9 pr-4 py-2 text-xs"
                        />
                    </div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {{ tutores.data.length }} tutores registrados
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Nombre del Tutor</th>
                                <th>Contacto / Teléfono</th>
                                <th>Alumno Vinculado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tutor in tutores.data" :key="tutor.id" class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full overflow-hidden bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                            <img
                                                v-if="tutor.t1_foto_perfil"
                                                :src="`/storage/${tutor.t1_foto_perfil}`"
                                                class="w-full h-full object-cover"
                                                alt="Foto Tutor"
                                            />
                                            <span v-else>{{ tutor.t1_nombre?.charAt(0) || 'T' }}</span>
                                        </div>
                                        <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase leading-tight">
                                            {{ tutor.t1_nombre }}
                                        </p>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-xs space-y-0.5">
                                        <div class="flex items-center gap-1.5 font-mono text-slate-700 dark:text-slate-300 font-semibold">
                                            <i class="fas fa-phone-alt text-[10px] text-slate-400"></i>
                                            <span>{{ tutor.t1_telefono }}</span>
                                        </div>
                                        <div v-if="tutor.t1_email" class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                                            <i class="fas fa-envelope text-[10px]"></i>
                                            <span class="truncate max-w-[160px]">{{ tutor.t1_email }}</span>
                                        </div>
                                        <div v-if="tutor.t1_telegram_chat_id" class="inline-flex items-center gap-1.5 text-sky-500 font-semibold text-[11px]">
                                            <i class="fab fa-telegram-plane"></i>
                                            <span>Telegram Activo</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div v-if="tutor.alumno">
                                        <p class="font-bold text-sky-600 dark:text-sky-400 text-xs uppercase leading-tight">
                                            {{ tutor.alumno.nombre }} {{ tutor.alumno.apellido }}
                                        </p>
                                        <p class="text-[10px] font-mono text-slate-400 mt-0.5">
                                            {{ tutor.alumno.matricula }}
                                        </p>
                                    </div>
                                    <span v-else class="badge badge-red">Sin Alumno</span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button
                                            v-if="!tutor.t1_telegram_chat_id && tutor.t1_email"
                                            @click="enviarInvitacion(tutor)"
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-500/10"
                                            title="Enviar Invitación Telegram"
                                            aria-label="Enviar Invitación Telegram"
                                        >
                                            <i class="fas fa-paper-plane text-xs"></i>
                                        </button>
                                        <button
                                            v-if="tutor.t1_telegram_chat_id"
                                            @click="abrirModalMensaje(tutor)"
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-sky-500 hover:bg-sky-50 dark:hover:bg-sky-500/10"
                                            title="Enviar Mensaje Directo"
                                            aria-label="Enviar Mensaje Directo"
                                        >
                                            <i class="fab fa-telegram-plane text-xs"></i>
                                        </button>
                                        <button
                                            @click="abrirModalEditar(tutor)"
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-primary hover:bg-sky-50 dark:hover:bg-sky-500/10"
                                            title="Editar Tutor"
                                            aria-label="Editar Tutor"
                                        >
                                            <i class="fas fa-pen text-xs"></i>
                                        </button>
                                        <button
                                            @click="eliminarTutor(tutor.id)"
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                            title="Eliminar Tutor"
                                            aria-label="Eliminar Tutor"
                                        >
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="tutores.data.length === 0">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-users-slash text-2xl"></i>
                                        </div>
                                        <p class="text-slate-700 dark:text-slate-300 font-bold text-sm">No hay tutores encontrados</p>
                                        <p class="text-slate-400 text-xs mt-1">Registra o vincula un tutor a un alumno.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="tutores.links" class="p-4 border-t border-slate-200/70 dark:border-white/[0.08] bg-slate-50/30 dark:bg-slate-900/20">
                    <Pagination :links="tutores.links" />
                </div>
            </div>

        </div>

        <!-- Modal Registrar / Editar Tutor (Apple Dialog) -->
        <div v-if="mostrarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-md" @click="mostrarModal = false"></div>

            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-10 flex flex-col max-h-[90vh]">
                <div class="modal-accent-bar"></div>

                <div class="p-5 border-b border-slate-200/70 dark:border-white/[0.08] flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                    <div>
                        <h3 class="font-display font-black text-lg text-slate-900 dark:text-white tracking-tight">
                            Datos del Tutor
                        </h3>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">Asignación de padre o tutor legal</p>
                    </div>
                    <button @click="mostrarModal = false" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-white flex items-center justify-center">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <form @submit.prevent="guardarTutor" class="p-5 overflow-y-auto custom-scroll space-y-4">
                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Alumno Vinculado *
                        </label>
                        <select v-model="form.matricula_alumno" required class="input-premium w-full cursor-pointer text-xs">
                            <option value="" disabled>Seleccione un alumno...</option>
                            <option v-for="alumno in alumnos" :key="alumno.matricula" :value="alumno.matricula">
                                {{ alumno.nombre }} {{ alumno.apellido }} ({{ alumno.matricula }})
                            </option>
                        </select>
                        <p v-if="form.errors.matricula_alumno" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.matricula_alumno }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Nombre Completo del Tutor *
                        </label>
                        <input
                            type="text"
                            v-model="form.t1_nombre"
                            required
                            class="input-premium w-full uppercase"
                            placeholder="Ej. MARÍA LÓPEZ HERNÁNDEZ"
                        />
                        <p v-if="form.errors.t1_nombre" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.t1_nombre }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Teléfono de Contacto (WhatsApp) *
                        </label>
                        <input
                            type="tel"
                            v-model="form.t1_telefono"
                            required
                            class="input-premium w-full"
                            placeholder="10 dígitos"
                        />
                        <p v-if="form.errors.t1_telefono" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.t1_telefono }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Correo Electrónico (Opcional)
                        </label>
                        <input
                            type="email"
                            v-model="form.t1_email"
                            class="input-premium w-full"
                            placeholder="tutor@ejemplo.com"
                        />
                        <p v-if="form.errors.t1_email" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.t1_email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Telegram Chat ID (Opcional)
                        </label>
                        <input
                            type="text"
                            v-model="form.t1_telegram_chat_id"
                            class="input-premium w-full"
                            placeholder="Ej. 123456789"
                        />
                        <p v-if="form.errors.t1_telegram_chat_id" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.t1_telegram_chat_id }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Foto de Perfil (Opcional)
                        </label>
                        <input
                            type="file"
                            @change="handleFotoChange"
                            accept="image/jpeg, image/png, image/jpg"
                            class="input-premium w-full file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 cursor-pointer"
                        />
                    </div>

                    <div class="pt-4 border-t border-slate-200/70 dark:border-white/[0.08] flex justify-end gap-3">
                        <button type="button" @click="mostrarModal = false" class="btn-ghost">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-premium">
                            <i class="fas fa-circle-notch fa-spin text-xs" v-if="form.processing"></i>
                            <i class="fas fa-check text-xs" v-else></i>
                            <span>Guardar Tutor</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Enviar Mensaje Telegram -->
        <div v-if="mostrarModalMensaje" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-md" @click="mostrarModalMensaje = false"></div>

            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-10 p-6">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h3 class="font-display font-black text-lg text-slate-900 dark:text-white">Mensaje Directo</h3>
                        <p class="text-xs text-sky-600 dark:text-sky-400 font-semibold mt-0.5">
                            Destinatario: {{ tutorSeleccionado?.t1_nombre }}
                        </p>
                    </div>
                    <button @click="mostrarModalMensaje = false" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-white flex items-center justify-center">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <form @submit.prevent="enviarMensaje" class="space-y-4">
                    <textarea
                        v-model="formMensaje.mensaje"
                        required
                        autofocus
                        rows="4"
                        class="input-premium w-full resize-none text-xs"
                        placeholder="Escribe el comunicado o reporte para el tutor..."
                    ></textarea>
                    <p v-if="formMensaje.errors.mensaje" class="text-rose-500 text-xs font-semibold">{{ formMensaje.errors.mensaje }}</p>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="mostrarModalMensaje = false" class="btn-ghost">Cancelar</button>
                        <button
                            type="submit"
                            :disabled="formMensaje.processing || !tutorSeleccionado?.t1_telegram_chat_id"
                            class="btn-premium flex items-center gap-2"
                        >
                            <i class="fas fa-paper-plane text-xs"></i>
                            <span>{{ formMensaje.processing ? 'Enviando...' : 'Enviar por Telegram' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
