<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    personal: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const busqueda = ref('');
const modalAbierto = ref(false);
const modoEdicion = ref(false);

const form = useForm({
    id: '',
    rfc: '',
    nombre_completo: '',
    email: '',
    categoria: 'Docente',
    funcion: '',
    plaza: '',
    grado_grupo: '',
    telefono: '',
    telegram_chat_id: '',
    foto_perfil: null,
    _method: 'post'
});

const personalFiltrado = computed(() => {
    if (!props.personal?.data) return [];
    if (!busqueda.value) return props.personal.data;
    const q = busqueda.value.toLowerCase().trim();
    return props.personal.data.filter(p =>
        (p.nombre_completo && String(p.nombre_completo).toLowerCase().includes(q)) ||
        (p.id && String(p.id).toLowerCase().includes(q)) ||
        (p.categoria && String(p.categoria).toLowerCase().includes(q))
    );
});

const abrirModalNuevo = () => {
    modoEdicion.value = false;
    form.reset();
    form.clearErrors();
    form.telegram_chat_id = '';
    form.foto_perfil = null;
    form._method = 'post';
    modalAbierto.value = true;
};

const abrirModalEditar = (p) => {
    modoEdicion.value = true;
    form.clearErrors();
    form.id = p.id;
    form.rfc = p.rfc || '';
    form.nombre_completo = p.nombre_completo;
    form.email = p.email || '';
    form.categoria = p.categoria || 'Docente';
    form.funcion = p.funcion || '';
    form.plaza = p.plaza || '';
    form.grado_grupo = p.grado_grupo || '';
    form.telefono = p.telefono || '';
    form.telegram_chat_id = p.telegram_chat_id || '';
    form.foto_perfil = null;
    form._method = 'put';
    modalAbierto.value = true;
};

const handleFotoChange = (e) => {
    form.foto_perfil = e.target.files[0];
};

const guardar = () => {
    if (modoEdicion.value) {
        form.post(route('personal.update', form.id), {
            forceFormData: true,
            onSuccess: () => {
                modalAbierto.value = false;
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    showConfirmButton: false, 
                    timer: 3000,
                    icon: 'success', 
                    title: 'Personal actualizado exitosamente',
                    customClass: { popup: 'rounded-2xl' }
                });
            }
        });
    } else {
        form.post(route('personal.store'), {
            forceFormData: true,
            onSuccess: () => {
                modalAbierto.value = false;
                Swal.fire({
                    toast: true, 
                    position: 'top-end', 
                    showConfirmButton: false, 
                    timer: 3000,
                    icon: 'success', 
                    title: 'Personal registrado exitosamente',
                    customClass: { popup: 'rounded-2xl' }
                });
            }
        });
    }
};

const eliminar = (id) => {
    Swal.fire({
        title: '¿Dar de baja registro?',
        text: "El docente o colaborador será dado de baja del sistema.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-popup',
            confirmButton: 'bg-rose-600 text-white font-bold px-5 py-2.5 rounded-xl',
            cancelButton: 'btn-ghost'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('personal.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        toast: true, 
                        position: 'top-end', 
                        showConfirmButton: false, 
                        timer: 3000,
                        icon: 'success', 
                        title: 'Baja completada'
                    });
                }
            });
        }
    });
};

const modalMensaje = ref(false);
const modalMensajeMasivo = ref(false);
const personalSeleccionado = ref(null);

const formMensaje = useForm({
    mensaje: ''
});

const formMensajeMasivo = useForm({
    mensaje: ''
});

const abrirModalMensaje = (p) => {
    personalSeleccionado.value = p;
    formMensaje.reset();
    formMensaje.clearErrors();
    modalMensaje.value = true;
};

const enviarMensaje = () => {
    formMensaje.post(route('personal.mensaje', personalSeleccionado.value.id), {
        onSuccess: () => {
            modalMensaje.value = false;
            Swal.fire({
                toast: true, 
                position: 'top-end', 
                showConfirmButton: false, 
                timer: 3000,
                icon: 'success', 
                title: 'Mensaje enviado a Telegram'
            });
        }
    });
};

const enviarMensajeMasivo = () => {
    formMensajeMasivo.post(route('personal.mensajeMasivo'), {
        onSuccess: () => {
            modalMensajeMasivo.value = false;
            Swal.fire({
                title: '¡Aviso Emitido!',
                text: 'El aviso masivo ha sido despachado a todo el personal vinculado.',
                icon: 'success',
                confirmButtonColor: '#009FE3',
                customClass: { popup: 'rounded-2xl' }
            });
        }
    });
};

const enviarInvitacion = (p) => {
    const texto = p.email 
        ? `Se enviará un correo a ${p.email} con el enlace de vinculación a Telegram.` 
        : `El docente no tiene correo registrado. Se generará un enlace para compartir.`;

    Swal.fire({
        title: '¿Vincular Telegram?',
        text: texto,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#009FE3',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-popup',
            confirmButton: 'btn-premium',
            cancelButton: 'btn-ghost'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('personal.invitacion', p.id));
        }
    });
};
</script>

<template>
    <Head title="Personal del Plantel — FamilyLock" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-lg text-slate-900 dark:text-white">Personal</h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                        Personal del <span class="text-gradient-secondary">Plantel</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-medium text-xs sm:text-sm mt-1">
                        Directorio de docentes, psicólogos, trabajadores sociales y colaboradores.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <button @click="modalMensajeMasivo = true" class="btn-ghost flex items-center gap-2 text-sky-600 dark:text-sky-400">
                        <i class="fas fa-bullhorn text-xs"></i> <span>Aviso Masivo</span>
                    </button>
                    <button @click="abrirModalNuevo" class="btn-premium flex items-center gap-2">
                        <i class="fas fa-user-plus text-xs"></i> <span>Nuevo Colaborador</span>
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="premium-card-static overflow-hidden">
                <div class="p-5 border-b border-slate-200/70 dark:border-white/[0.08] flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="relative w-full sm:w-80">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input 
                            type="text" 
                            v-model="busqueda" 
                            placeholder="Buscar por nombre o ID..." 
                            class="input-premium w-full pl-9 pr-4 py-2 text-xs"
                        />
                    </div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {{ personalFiltrado.length }} miembros registrados
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>ID / Función</th>
                                <th>Nombre Completo</th>
                                <th>Categoría</th>
                                <th>Contacto</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in personalFiltrado" :key="p.id" class="group">
                                <td>
                                    <span class="font-mono font-bold text-xs text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-500/10 px-2 py-0.5 rounded-md border border-purple-200/50 dark:border-purple-500/20">
                                        {{ p.id }}
                                    </span>
                                    <p class="text-[11px] text-slate-400 font-medium mt-1">
                                        {{ p.funcion || 'Sin función' }}
                                    </p>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center font-bold text-xs uppercase text-slate-600 dark:text-slate-300 shrink-0">
                                            {{ p.nombre_completo?.charAt(0) || 'P' }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase leading-tight">
                                                {{ p.nombre_completo }}
                                            </p>
                                            <p v-if="p.rfc" class="text-[10px] text-slate-400 font-mono mt-0.5 uppercase">
                                                RFC: {{ p.rfc }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-slate">
                                        {{ p.categoria }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-xs">
                                        <span v-if="p.telefono" class="font-mono text-slate-600 dark:text-slate-300 block">
                                            {{ p.telefono }}
                                        </span>
                                        <div v-if="p.telegram_chat_id" class="inline-flex items-center gap-1.5 text-sky-500 font-semibold text-[11px] mt-0.5">
                                            <i class="fab fa-telegram-plane"></i> <span>Vinculado</span>
                                        </div>
                                        <span v-else class="text-slate-400 text-[11px]">Sin Telegram</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button 
                                            v-if="!p.telegram_chat_id" 
                                            @click="enviarInvitacion(p)" 
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-500/10" 
                                            title="Vincular Telegram"
                                            aria-label="Vincular Telegram"
                                        >
                                            <i class="fas fa-link text-xs"></i>
                                        </button>
                                        <button 
                                            v-if="p.telegram_chat_id" 
                                            @click="abrirModalMensaje(p)" 
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-sky-500 hover:bg-sky-50 dark:hover:bg-sky-500/10" 
                                            title="Enviar Mensaje Directo"
                                            aria-label="Enviar Mensaje Directo"
                                        >
                                            <i class="fab fa-telegram-plane text-xs"></i>
                                        </button>
                                        <button 
                                            @click="abrirModalEditar(p)" 
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-primary hover:bg-sky-50 dark:hover:bg-sky-500/10" 
                                            title="Editar Personal"
                                            aria-label="Editar Personal"
                                        >
                                            <i class="fas fa-pen text-xs"></i>
                                        </button>
                                        <button 
                                            @click="eliminar(p.id)" 
                                            class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" 
                                            title="Dar de Baja"
                                            aria-label="Dar de Baja"
                                        >
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="personalFiltrado.length === 0">
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-chalkboard-teacher text-2xl"></i>
                                        </div>
                                        <p class="text-slate-700 dark:text-slate-300 font-bold text-sm">No se encontraron colaboradores</p>
                                        <p class="text-slate-400 text-xs mt-1">Modifica el término de búsqueda o agrega un nuevo colaborador.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="personal.links" class="p-4 border-t border-slate-200/70 dark:border-white/[0.08] bg-slate-50/30 dark:bg-slate-900/20">
                    <Pagination :links="personal.links" />
                </div>
            </div>

        </div>

        <!-- Modal Registrar / Editar Personal (Apple Modal) -->
        <div v-if="modalAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-md" @click="modalAbierto = false"></div>

            <div class="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-10 max-h-[90vh] flex flex-col">
                <div class="modal-accent-bar"></div>

                <div class="p-5 sm:p-6 border-b border-slate-200/70 dark:border-white/[0.08] flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                    <div>
                        <h3 class="font-display font-black text-lg text-slate-900 dark:text-white tracking-tight">
                            {{ modoEdicion ? 'Editar Colaborador' : 'Registrar Nuevo Colaborador' }}
                        </h3>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">Gestión de datos de personal docente y administrativo</p>
                    </div>
                    <button @click="modalAbierto = false" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition-all">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <form @submit.prevent="guardar" class="p-5 sm:p-6 overflow-y-auto custom-scroll flex-1 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                ID / Matrícula QR *
                            </label>
                            <input 
                                type="text" 
                                v-model="form.id" 
                                placeholder="Ej. DOC-01" 
                                class="input-premium w-full uppercase"
                                :disabled="modoEdicion" 
                                required
                            />
                            <p v-if="form.errors.id" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.id }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                RFC
                            </label>
                            <input 
                                type="text" 
                                v-model="form.rfc" 
                                placeholder="Ej. VECJ880326" 
                                class="input-premium w-full uppercase"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Nombre Completo *
                            </label>
                            <input 
                                type="text" 
                                v-model="form.nombre_completo" 
                                placeholder="Nombre completo" 
                                class="input-premium w-full uppercase" 
                                required
                            />
                            <p v-if="form.errors.nombre_completo" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.nombre_completo }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Correo Electrónico
                            </label>
                            <input 
                                type="email" 
                                v-model="form.email" 
                                placeholder="docente@cam48.gob.mx" 
                                class="input-premium w-full"
                            />
                            <p v-if="form.errors.email" class="text-rose-500 text-xs font-semibold mt-1">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Categoría General
                            </label>
                            <select v-model="form.categoria" class="input-premium w-full cursor-pointer">
                                <option value="Docente">Docente</option>
                                <option value="Directivo">Directivo</option>
                                <option value="Psicología">Psicología</option>
                                <option value="Trabajo Social">Trabajo Social</option>
                                <option value="Administrativo">Administrativo</option>
                                <option value="Intendencia">Intendencia</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Función Específica
                            </label>
                            <input 
                                type="text" 
                                v-model="form.funcion" 
                                placeholder="Ej. Docente de Comunicación" 
                                class="input-premium w-full"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Clave de Plaza
                            </label>
                            <input 
                                type="text" 
                                v-model="form.plaza" 
                                placeholder="Ej. E0281" 
                                class="input-premium w-full"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Grado / Grupo Asignado
                            </label>
                            <input 
                                type="text" 
                                v-model="form.grado_grupo" 
                                placeholder="Ej. 1ro A" 
                                class="input-premium w-full"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Teléfono de Contacto
                            </label>
                            <input 
                                type="tel" 
                                v-model="form.telefono" 
                                placeholder="10 dígitos" 
                                class="input-premium w-full"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                                Telegram Chat ID
                            </label>
                            <input 
                                type="text" 
                                v-model="form.telegram_chat_id" 
                                placeholder="Ej. 123456789" 
                                class="input-premium w-full"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Foto de Perfil (Opcional)
                        </label>
                        <input 
                            type="file" 
                            @change="handleFotoChange" 
                            accept="image/jpeg, image/png, image/jpg" 
                            class="input-premium w-full file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 cursor-pointer"
                        />
                    </div>

                    <div class="pt-4 border-t border-slate-200/70 dark:border-white/[0.08] flex justify-end gap-3">
                        <button type="button" @click="modalAbierto = false" class="btn-ghost">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" class="btn-premium">
                            <i class="fas fa-circle-notch fa-spin text-xs" v-if="form.processing"></i>
                            <i class="fas fa-check text-xs" v-else></i>
                            <span>{{ modoEdicion ? 'Guardar Cambios' : 'Registrar Colaborador' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Mensaje Directo Telegram -->
        <div v-if="modalMensaje" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-md" @click="modalMensaje = false"></div>
            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-10 p-6">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h3 class="font-display font-black text-lg text-slate-900 dark:text-white">Mensaje Directo</h3>
                        <p class="text-xs text-sky-600 dark:text-sky-400 font-semibold mt-0.5">
                            Para: {{ personalSeleccionado?.nombre_completo }}
                        </p>
                    </div>
                    <button @click="modalMensaje = false" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-white flex items-center justify-center">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <form @submit.prevent="enviarMensaje" class="space-y-4">
                    <textarea 
                        v-model="formMensaje.mensaje" 
                        required 
                        rows="4" 
                        class="input-premium w-full resize-none text-xs" 
                        placeholder="Escribe el mensaje o aviso institucional para este docente..."
                    ></textarea>
                    
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="modalMensaje = false" class="btn-ghost">Cancelar</button>
                        <button type="submit" :disabled="formMensaje.processing" class="btn-premium flex items-center gap-2">
                            <i class="fas fa-paper-plane text-xs"></i> <span>Enviar Mensaje</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Mensaje Masivo -->
        <div v-if="modalMensajeMasivo" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-md" @click="modalMensajeMasivo = false"></div>
            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-10 p-6">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h3 class="font-display font-black text-lg text-slate-900 dark:text-white">Aviso Institucional Masivo</h3>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">Se enviará por Telegram a todo el personal vinculado.</p>
                    </div>
                    <button @click="modalMensajeMasivo = false" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-white flex items-center justify-center">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <form @submit.prevent="enviarMensajeMasivo" class="space-y-4">
                    <textarea 
                        v-model="formMensajeMasivo.mensaje" 
                        required 
                        rows="5" 
                        class="input-premium w-full resize-none text-xs" 
                        placeholder="Escribe el comunicado oficial para todo el personal..."
                    ></textarea>
                    
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="modalMensajeMasivo = false" class="btn-ghost">Cancelar</button>
                        <button type="submit" :disabled="formMensajeMasivo.processing" class="btn-premium flex items-center gap-2">
                            <i class="fas fa-bullhorn text-xs"></i> <span>Emitir Comunicado</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
