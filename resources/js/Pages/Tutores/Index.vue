<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import Swal from "sweetalert2";
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    tutores: Object,
    alumnos: Array,
    filtros: Object,
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
            });
        },
    });
};

const enviarInvitacion = (tutor) => {
    Swal.fire({
        title: "¿Enviar Invitación?",
        text: `Se enviará un correo a ${tutor.t1_email || "..."} con el link mágico de Telegram.`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#0ea5e9",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Sí, enviar correo",
        cancelButtonText: "Cancelar",
        customClass: {
            popup: "rounded-3xl",
            confirmButton: "rounded-xl font-bold px-6 py-3",
            cancelButton: "rounded-xl font-bold px-6 py-3",
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
                            title: "¡Enviado!",
                            text: "El correo electrónico ha sido despachado.",
                            icon: "success",
                            toast: true,
                            position: "top-end",
                            showConfirmButton: false,
                            timer: 3000,
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
            });
        },
    });
};

const eliminarTutor = (id) => {
    Swal.fire({
        title: "¿Eliminar registro?",
        text: "El alumno se quedará sin tutor asignado.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        customClass: {
            popup: "rounded-3xl",
            confirmButton: "rounded-xl font-bold px-6 py-3",
            cancelButton: "rounded-xl font-bold px-6 py-3",
        },
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("tutores.destroy", id));
        }
    });
};
</script>

<template>
    <Head title="Directorio de Tutores" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1
                class="font-display font-black text-xl text-slate-800 dark:text-white tracking-tight"
            >
                Directorio de <span class="text-primary">Tutores</span>
            </h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto animate-fade-in-up">
            <div class="mb-8 hidden md:flex items-center justify-between">
                <div>
                    <h1
                        class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight"
                    >
                        Directorio de <span class="text-primary">Tutores</span>
                    </h1>
                    <p
                        class="text-slate-500 dark:text-slate-400 font-bold text-sm mt-1"
                    >
                        Gestión de padres de familia y contactos de emergencia.
                    </p>
                </div>

                <button @click="abrirModalNuevo" class="btn-premium">
                    <i class="fas fa-plus"></i> Registrar Tutor
                </button>
            </div>

            <div
                class="premium-card-static overflow-hidden animate-fade-in-up"
                style="animation-delay: 150ms"
            >
                <!-- Buscador -->
                <div
                    class="p-6 border-b border-slate-100/80 dark:border-slate-700/50 bg-slate-50/30 dark:bg-slate-800/30 flex justify-between items-center"
                >
                    <div class="relative w-full md:w-96">
                        <i
                            class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"
                        ></i>
                        <input
                            type="text"
                            v-model="busqueda"
                            placeholder="Buscar por nombre o teléfono..."
                            class="input-premium w-full pl-12 text-sm"
                        />
                    </div>
                </div>

                <!-- Tabla -->
                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Nombre del Tutor</th>
                                <th>Teléfono (Contacto)</th>
                                <th>Alumno Vinculado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody v-auto-animate>
                            <tr
                                v-for="tutor in tutores.data"
                                :key="tutor.id"
                                class="group"
                            >
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div
                                            v-if="tutor.t1_foto_perfil"
                                            class="w-10 h-10 rounded-full overflow-hidden border-2 border-white shadow-sm shrink-0"
                                        >
                                            <img
                                                :src="`/storage/${tutor.t1_foto_perfil}`"
                                                class="w-full h-full object-cover"
                                            />
                                        </div>
                                        <div
                                            v-else
                                            class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center text-orange-500 font-black shrink-0"
                                        >
                                            {{ tutor.t1_nombre.charAt(0) }}
                                        </div>
                                        <p
                                            class="font-bold text-slate-800 dark:text-slate-200 text-sm uppercase"
                                        >
                                            {{ tutor.t1_nombre }}
                                        </p>
                                    </div>
                                </td>
                                <td>
                                    <div
                                        class="flex items-center gap-2 text-slate-600 dark:text-slate-300 font-bold text-sm"
                                    >
                                        <i
                                            class="fas fa-phone-alt text-slate-400 dark:text-slate-500"
                                        ></i>
                                        {{ tutor.t1_telefono }}
                                    </div>
                                    <div
                                        v-if="tutor.t1_email"
                                        class="flex items-center gap-2 text-slate-500 dark:text-slate-400 font-bold text-xs mt-1"
                                    >
                                        <i
                                            class="fas fa-envelope text-slate-400 dark:text-slate-500"
                                        ></i>
                                        {{ tutor.t1_email }}
                                    </div>
                                    <div
                                        v-if="tutor.t1_telegram_chat_id"
                                        class="flex items-center gap-2 text-sky-500 font-bold text-xs mt-1"
                                    >
                                        <i
                                            class="fab fa-telegram-plane text-sky-400"
                                        ></i>
                                        {{ tutor.t1_telegram_chat_id }}
                                    </div>
                                </td>
                                <td>
                                    <div v-if="tutor.alumno">
                                        <p
                                            class="font-bold text-primary text-sm uppercase"
                                        >
                                            {{ tutor.alumno.nombre }}
                                            {{ tutor.alumno.apellido }}
                                        </p>
                                        <p
                                            class="text-[10px] font-mono text-slate-500 font-bold mt-0.5"
                                        >
                                            {{ tutor.alumno.matricula }}
                                        </p>
                                    </div>
                                    <span v-else class="badge badge-red"
                                        >Sin Alumno</span
                                    >
                                </td>
                                <td>
                                    <div
                                        class="table-actions flex justify-center gap-2"
                                    >
                                        <button
                                            v-if="
                                                !tutor.t1_telegram_chat_id &&
                                                tutor.t1_email
                                            "
                                            @click="enviarInvitacion(tutor)"
                                            class="table-action-btn bg-orange-50 text-orange-500 hover:bg-orange-500 hover:text-white dark:bg-orange-500/10 dark:text-orange-400 transition-all"
                                            v-tippy="
                                                'Enviar Invitación Telegram'
                                            "
                                        >
                                            <i
                                                class="fas fa-envelope-open-text"
                                            ></i>
                                        </button>
                                        <button
                                            v-if="tutor.t1_telegram_chat_id"
                                            @click="abrirModalMensaje(tutor)"
                                            class="table-action-btn bg-sky-50 text-sky-500 hover:bg-sky-500 hover:text-white dark:bg-sky-500/10 dark:text-sky-400 transition-all"
                                            v-tippy="'Enviar Mensaje Directo'"
                                        >
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                        <button
                                            @click="abrirModalEditar(tutor)"
                                            class="table-action-btn bg-slate-100 text-slate-500 hover:text-white hover:bg-secondary dark:bg-slate-700/50 dark:text-slate-400 transition-all"
                                            v-tippy="'Editar Tutor'"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button
                                            @click="eliminarTutor(tutor.id)"
                                            class="table-action-btn bg-red-50 text-red-500 hover:bg-red-500 hover:text-white dark:bg-red-500/10 dark:text-red-400 transition-all"
                                            v-tippy="'Eliminar Tutor'"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="tutores.data.length === 0">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i
                                                class="fas fa-users-slash text-2xl"
                                            ></i>
                                        </div>
                                        <p
                                            class="text-slate-500 font-bold text-lg"
                                        >
                                            No hay tutores registrados con ese
                                            criterio.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div
                    class="p-4 border-t border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/30"
                >
                    <Pagination :links="tutores.links" />
                </div>
            </div>
        </div>

        <!-- Modal Formulario -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mostrarModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                    @click="mostrarModal = false"
                ></div>

                <div
                    class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md w-full max-w-md rounded-3xl shadow-2xl z-10 relative overflow-hidden border border-white/20 dark:border-slate-700/50"
                    @click.stop
                >
                    <div
                        class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-primary to-secondary"
                    ></div>
                    <div
                        class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50"
                    >
                        <h3
                            class="font-black text-slate-800 dark:text-white text-xl font-display tracking-tight"
                        >
                            Datos del Tutor
                        </h3>
                        <button
                            @click="mostrarModal = false"
                            class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center transition-colors"
                            v-tippy="'Cerrar'"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="p-6">
                        <form @submit.prevent="guardarTutor">
                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2"
                                        >Alumno Vinculado</label
                                    >
                                    <select
                                        v-model="form.matricula_alumno"
                                        required
                                        class="input-premium w-full text-sm"
                                    >
                                        <option value="" disabled>
                                            Seleccione un alumno...
                                        </option>
                                        <option
                                            v-for="alumno in alumnos"
                                            :key="alumno.matricula"
                                            :value="alumno.matricula"
                                        >
                                            {{ alumno.nombre }}
                                            {{ alumno.apellido }} ({{
                                                alumno.matricula
                                            }})
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.matricula_alumno"
                                        class="text-red-500 text-xs font-bold mt-1"
                                    >
                                        {{ form.errors.matricula_alumno }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2"
                                        >Nombre Completo del Tutor</label
                                    >
                                    <input
                                        type="text"
                                        v-model="form.t1_nombre"
                                        required
                                        autofocus
                                        class="input-premium w-full text-sm uppercase"
                                        placeholder="Ej. JUAN PÉREZ"
                                    />
                                    <p
                                        v-if="form.errors.t1_nombre"
                                        class="text-red-500 text-xs font-bold mt-1"
                                    >
                                        {{ form.errors.t1_nombre }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2"
                                        >Teléfono (WhatsApp)</label
                                    >
                                    <input
                                        type="text"
                                        v-model="form.t1_telefono"
                                        required
                                        class="input-premium w-full text-sm"
                                        placeholder="10 dígitos"
                                    />
                                    <p
                                        v-if="form.errors.t1_telefono"
                                        class="text-red-500 text-xs font-bold mt-1"
                                    >
                                        {{ form.errors.t1_telefono }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2"
                                        >Correo Electrónico (Opcional)</label
                                    >
                                    <input
                                        type="email"
                                        v-model="form.t1_email"
                                        class="input-premium w-full text-sm"
                                        placeholder="correo@ejemplo.com"
                                    />
                                    <p
                                        v-if="form.errors.t1_email"
                                        class="text-red-500 text-xs font-bold mt-1"
                                    >
                                        {{ form.errors.t1_email }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2"
                                        >Telegram Chat ID (Opcional)</label
                                    >
                                    <input
                                        type="text"
                                        v-model="form.t1_telegram_chat_id"
                                        class="input-premium w-full text-sm"
                                        placeholder="Ej. 123456789"
                                    />
                                    <p
                                        v-if="form.errors.t1_telegram_chat_id"
                                        class="text-red-500 text-xs font-bold mt-1"
                                    >
                                        {{ form.errors.t1_telegram_chat_id }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2"
                                        >Foto de Perfil (Opcional)</label
                                    >
                                    <input
                                        type="file"
                                        @change="handleFotoChange"
                                        accept="image/jpeg, image/png, image/jpg"
                                        class="input-premium w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer"
                                    />
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="mostrarModal = false"
                                    class="btn-secondary"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="btn-premium"
                                >
                                    <i
                                        class="fas fa-spinner fa-spin mr-2"
                                        v-if="form.processing"
                                    ></i>
                                    Guardar Tutor
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Enviar Mensaje -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mostrarModalMensaje"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                    @click="mostrarModalMensaje = false"
                ></div>

                <div
                    class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md w-full max-w-md rounded-3xl shadow-2xl z-10 relative overflow-hidden border border-white/20 dark:border-slate-700/50"
                    @click.stop
                >
                    <div
                        class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-sky-400 to-sky-500"
                    ></div>
                    <div
                        class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50"
                    >
                        <h3
                            class="font-black text-slate-800 dark:text-white text-xl font-display tracking-tight"
                        >
                            Mensaje Directo
                        </h3>
                        <button
                            @click="mostrarModalMensaje = false"
                            class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center transition-colors"
                            v-tippy="'Cerrar'"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="p-6">
                        <form @submit.prevent="enviarMensaje">
                            <div
                                class="mb-4 bg-sky-50 p-4 rounded-2xl border border-sky-100 flex items-center gap-3"
                            >
                                <div
                                    class="w-10 h-10 rounded-full bg-white text-sky-500 flex items-center justify-center font-black shadow-sm"
                                >
                                    <i class="fab fa-telegram-plane"></i>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-black text-slate-500 uppercase tracking-widest"
                                    >
                                        Destinatario
                                    </p>
                                    <p
                                        class="text-sm font-black text-slate-800 uppercase"
                                    >
                                        {{ tutorSeleccionado?.t1_nombre }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2"
                                        >Escribe tu mensaje</label
                                    >
                                    <textarea
                                        v-model="formMensaje.mensaje"
                                        required
                                        autofocus
                                        rows="4"
                                        class="input-premium w-full text-sm resize-none"
                                        placeholder="Escriba aquí el comunicado para el padre o tutor..."
                                    ></textarea>
                                    <p
                                        v-if="formMensaje.errors.mensaje"
                                        class="text-red-500 text-xs font-bold mt-1"
                                    >
                                        {{ formMensaje.errors.mensaje }}
                                    </p>
                                    <p
                                        class="text-xs font-bold text-slate-400 mt-2"
                                    >
                                        <i class="fas fa-info-circle"></i> El
                                        mensaje se enviará instantáneamente por
                                        Telegram.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end gap-3">
                                <button
                                    type="button"
                                    @click="mostrarModalMensaje = false"
                                    class="btn-ghost"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="
                                        formMensaje.processing ||
                                        !tutorSeleccionado?.t1_telegram_chat_id
                                    "
                                    class="btn-premium flex items-center justify-center gap-2"
                                    style="
                                        background: linear-gradient(
                                            135deg,
                                            #0ea5e9,
                                            #0284c7
                                        );
                                        box-shadow: 0 8px 24px -6px
                                            rgba(14, 165, 233, 0.4);
                                    "
                                >
                                    <span
                                        v-if="
                                            !tutorSeleccionado?.t1_telegram_chat_id
                                        "
                                        >Sin Telegram vinculado</span
                                    >
                                    <span v-else>
                                        <i
                                            class="fas fa-spinner fa-spin mr-2"
                                            v-if="formMensaje.processing"
                                        ></i>
                                        Enviando...
                                        <i
                                            class="fas fa-paper-plane"
                                            v-if="!formMensaje.processing"
                                        ></i>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
