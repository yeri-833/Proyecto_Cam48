<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    personal: Array,
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
    foto_perfil: null,
    _method: 'post'
});

const personalFiltrado = computed(() => {
    if (!busqueda.value) return props.personal;
    const q = busqueda.value.toLowerCase();
    return props.personal.filter(p =>
        p.nombre_completo.toLowerCase().includes(q) ||
        p.id.toLowerCase().includes(q) ||
        (p.categoria && p.categoria.toLowerCase().includes(q))
    );
});

const abrirModalNuevo = () => {
    modoEdicion.value = false;
    form.reset();
    form.clearErrors();
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
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                    icon: 'success', title: 'Personal actualizado'
                });
            }
        });
    } else {
        form.post(route('personal.store'), {
            forceFormData: true,
            onSuccess: () => {
                modalAbierto.value = false;
                Swal.fire({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                    icon: 'success', title: 'Personal registrado'
                });
            }
        });
    }
};

const eliminar = (id) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "El registro será dado de baja del sistema.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-popup', title: 'swal2-title',
            confirmButton: 'bg-red-500 rounded-2xl text-white font-bold px-6 py-3 shadow-lg',
            cancelButton: 'bg-slate-100 rounded-2xl text-slate-500 font-bold px-6 py-3',
            icon: 'swal2-icon'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('personal.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                        icon: 'success', title: 'Baja exitosa'
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
                toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                icon: 'success', title: 'Mensaje enviado exitosamente'
            });
        }
    });
};

const enviarMensajeMasivo = () => {
    formMensajeMasivo.post(route('personal.mensajeMasivo'), {
        onSuccess: () => {
            modalMensajeMasivo.value = false;
            Swal.fire({
                title: '¡Aviso Enviado!',
                text: 'El aviso masivo ha sido enviado a todo el personal vinculado.',
                icon: 'success',
                confirmButtonColor: '#0ea5e9'
            });
        }
    });
};

const enviarInvitacion = (p) => {
    const texto = p.email 
        ? `Se enviará un correo electrónico a ${p.email} con el enlace mágico para vincular su cuenta.` 
        : `El docente no tiene correo registrado. El sistema generará un enlace mágico para que se lo envíes por WhatsApp.`;

    Swal.fire({
        title: '¿Generar Link de Telegram?',
        text: texto,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f97316',
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('personal.invitacion', p.id));
        }
    });
};
</script>

<template>
    <Head title="Gestión de Personal" />

    <AuthenticatedLayout>
        <!-- Teleporting header title for Mobile Nav if needed -->
        <Teleport to="#portal-header-title" v-if="mounted">
            <h1 class="font-display font-black text-xl text-slate-800 dark:text-white tracking-tight">Administración de <span class="text-secondary">Personal</span></h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto animate-fade-in-up">
            
            <div class="mb-8 hidden md:block">
                <h1 class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight">
                    Administración de <span class="text-secondary">Personal</span>
                </h1>
            </div>

            <div class="premium-card overflow-hidden">
                <div class="p-6 md:p-8 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 dark:border-slate-700/50">
                    <div class="flex items-center gap-4">
                        <h2 class="text-2xl font-black text-slate-700 dark:text-slate-200">Personal del Plantel</h2>
                        <button @click="modalMensajeMasivo = true" class="bg-sky-500 text-white px-4 py-2 rounded-xl font-bold text-xs shadow-lg shadow-sky-500/20 hover:bg-sky-600 transition-all flex items-center gap-2">
                            <i class="fas fa-bullhorn"></i> Aviso Masivo
                        </button>
                        <button @click="abrirModalNuevo" class="bg-secondary text-white px-4 py-2 rounded-xl font-bold text-xs shadow-lg shadow-purple-500/20 hover:bg-purple-700 transition-all flex items-center gap-2">
                            <i class="fas fa-user-plus"></i> Nuevo Registro
                        </button>
                    </div>
                    <div class="relative w-full md:w-96">
                        <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 dark:text-slate-500"></i>
                        <input type="text" v-model="busqueda" placeholder="Buscar por nombre o ID..." 
                               class="input-premium w-full pl-12 text-sm">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>ID / Función</th>
                                <th>Nombre Completo</th>
                                <th>Categoría</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody v-auto-animate>
                            <tr v-for="p in personalFiltrado" :key="p.id" class="group">
                                <td>
                                    <p class="font-mono font-black text-secondary">{{ p.id }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 mt-1">{{ p.funcion || 'Sin función' }}</p>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-700 dark:text-slate-200 uppercase text-sm">{{ p.nombre_completo }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase">{{ p.rfc }}</p>
                                    <div v-if="p.telegram_chat_id" class="flex items-center gap-2 text-sky-500 font-bold text-xs mt-1">
                                        <i class="fab fa-telegram-plane text-sky-400"></i> Vinculado
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-slate">
                                        {{ p.categoria }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex justify-center gap-3">
                                        <button v-if="!p.telegram_chat_id" @click="enviarInvitacion(p)" class="table-action-btn bg-orange-50 text-orange-500 hover:bg-orange-500 hover:text-white dark:bg-orange-500/10 dark:text-orange-400 transition-all" v-tippy="'Vincular Telegram'">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button v-if="p.telegram_chat_id" @click="abrirModalMensaje(p)" class="table-action-btn bg-sky-50 text-sky-500 hover:bg-sky-500 hover:text-white dark:bg-sky-500/10 dark:text-sky-400 transition-all" v-tippy="'Enviar Mensaje'">
                                            <i class="fab fa-telegram-plane"></i>
                                        </button>
                                        <button @click="abrirModalEditar(p)" class="table-action-btn bg-slate-100 text-slate-500 hover:text-white hover:bg-secondary dark:bg-slate-700/50 dark:text-slate-400 transition-all" v-tippy="'Editar Personal'">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="eliminar(p.id)" class="table-action-btn bg-red-50 text-red-500 hover:bg-red-500 hover:text-white dark:bg-red-500/10 dark:text-red-400 transition-all" v-tippy="'Dar de Baja'">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="personalFiltrado.length === 0">
                                <td colspan="4" class="px-8 py-10 text-center text-slate-400 font-bold">
                                    No se encontraron registros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-show="modalAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="modalAbierto = false"></div>
            
            <div class="bg-white w-full max-w-2xl rounded-[2.5rem] p-8 shadow-2xl z-10 animate-fade-in-up relative overflow-hidden max-h-[95vh] overflow-y-auto custom-scroll">
                
                <div class="absolute top-0 left-0 w-full h-3 bg-gradient-to-r from-secondary to-purple-500"></div>
                
                <div class="flex justify-between items-center mb-6 mt-2">
                    <div>
                        <h2 class="text-2xl font-black text-slate-800">{{ modoEdicion ? 'Editar Personal' : 'Nuevo Integrante' }}</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Gestión administrativa del personal</p>
                    </div>
                    <button @click="modalAbierto = false" class="text-slate-400 hover:text-slate-600 bg-slate-100 w-8 h-8 rounded-full flex items-center justify-center transition-all" v-tippy="'Cerrar'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form @submit.prevent="guardar" class="space-y-4">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ID (Matrícula QR)</label>
                            <div class="relative">
                                <input type="text" v-model="form.id" placeholder="Ej. DOC-01" 
                                       class="w-full border-2 rounded-2xl px-5 py-3 font-bold uppercase transition-all outline-none" 
                                       :class="modoEdicion ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-slate-50 border-slate-100 focus:border-secondary text-slate-700'"
                                       :disabled="modoEdicion" required>
                                <i v-show="modoEdicion" class="fas fa-lock absolute right-5 top-1/2 -translate-y-1/2 text-slate-300"></i>
                            </div>
                            <p v-if="form.errors.id" class="text-red-500 text-xs font-bold mt-1">{{ form.errors.id }}</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">RFC</label>
                            <input type="text" v-model="form.rfc" placeholder="Ej. VECJ880326" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 uppercase outline-none focus:border-secondary transition-all">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Nombre Completo</label>
                            <input type="text" v-model="form.nombre_completo" placeholder="Nombre completo" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 uppercase outline-none focus:border-secondary transition-all" required>
                            <p v-if="form.errors.nombre_completo" class="text-red-500 text-xs font-bold mt-1">{{ form.errors.nombre_completo }}</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Correo Electrónico</label>
                            <input type="email" v-model="form.email" placeholder="ejemplo@correo.com" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 outline-none focus:border-secondary transition-all">
                            <p v-if="form.errors.email" class="text-red-500 text-xs font-bold mt-1">{{ form.errors.email }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Categoría (Rol General)</label>
                            <select v-model="form.categoria" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 outline-none focus:border-secondary transition-all cursor-pointer">
                                <option value="Docente">Docente</option>
                                <option value="Directivo">Directivo</option>
                                <option value="Psicología">Psicología</option>
                                <option value="Trabajo Social">Trabajo Social</option>
                                <option value="Administrativo">Administrativo</option>
                                <option value="Intendencia">Intendencia</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Función Específica</label>
                            <input type="text" v-model="form.funcion" placeholder="Ej. Maestra de Comunicación" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 outline-none focus:border-secondary transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Clave de Plaza</label>
                            <input type="text" v-model="form.plaza" placeholder="Ej. E0281" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 outline-none focus:border-secondary transition-all">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Grado / Grupo Asignado</label>
                            <input type="text" v-model="form.grado_grupo" placeholder="Ej. 1ro A" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 outline-none focus:border-secondary transition-all">
                        </div>
                    </div>
                    
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Teléfono de Contacto</label>
                        <input type="tel" v-model="form.telefono" placeholder="10 dígitos" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 outline-none focus:border-secondary transition-all">
                    </div>
                    
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Foto de Perfil (Opcional)</label>
                        <input type="file" @change="handleFotoChange" accept="image/jpeg, image/png, image/jpg" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-5 py-3 font-bold text-slate-700 outline-none focus:border-secondary transition-all file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-secondary/10 file:text-secondary hover:file:bg-secondary/20 cursor-pointer">
                    </div>
                    
                    <div class="flex gap-3 pt-6 border-t border-slate-100 mt-2">
                        <button type="button" @click="modalAbierto = false" class="w-1/3 bg-slate-100 text-slate-500 py-3.5 rounded-2xl font-bold hover:bg-slate-200 transition-all shadow-sm">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="w-2/3 bg-secondary text-white py-3.5 rounded-2xl font-black shadow-lg shadow-purple-500/30 hover:bg-purple-600 transition-all flex justify-center items-center gap-2" :class="{'opacity-75': form.processing}">
                            <i class="fas fa-spinner fa-spin" v-if="form.processing"></i>
                            <i class="fas fa-save" v-else></i> 
                            {{ modoEdicion ? 'Guardar Cambios' : 'Crear Registro' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Mensaje Directo Telegram -->
        <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="modalMensaje" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="modalMensaje = false"></div>
                <div class="bg-white w-full max-w-lg rounded-3xl p-8 shadow-2xl z-10 relative overflow-hidden" @click.stop>
                    <div class="absolute top-0 left-0 w-full h-2 bg-sky-500"></div>
                    
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-black text-slate-800">Mensaje Directo</h2>
                            <p class="text-xs font-bold text-slate-400 mt-1">Para: <span class="text-sky-500">{{ personalSeleccionado?.nombre_completo }}</span></p>
                        </div>
                        <button @click="modalMensaje = false" class="text-slate-400 hover:bg-slate-100 w-8 h-8 rounded-xl flex items-center justify-center transition-colors" v-tippy="'Cerrar'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form @submit.prevent="enviarMensaje">
                        <div class="mb-4 bg-sky-50 p-4 rounded-2xl border border-sky-100 flex gap-3">
                            <div class="w-10 h-10 rounded-full bg-white text-sky-500 flex items-center justify-center font-black shadow-sm shrink-0">
                                <i class="fab fa-telegram-plane"></i>
                            </div>
                            <div class="w-full">
                                <textarea v-model="formMensaje.mensaje" required rows="4" class="w-full bg-transparent border-none p-0 text-sm outline-none resize-none placeholder-slate-400 text-slate-700" placeholder="Escriba aquí el comunicado para el docente..."></textarea>
                                <p v-if="formMensaje.errors.mensaje" class="text-red-500 text-xs font-bold mt-1">{{ formMensaje.errors.mensaje }}</p>
                            </div>
                        </div>
                        
                        <button type="submit" :disabled="formMensaje.processing" class="w-full bg-sky-500 hover:bg-sky-600 text-white font-bold py-3 rounded-2xl transition-all shadow-lg shadow-sky-500/30 flex items-center justify-center gap-2">
                            <i class="fas fa-spinner fa-spin" v-if="formMensaje.processing"></i>
                            <span v-else>Enviar por Telegram <i class="fas fa-paper-plane"></i></span>
                        </button>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Modal Mensaje Masivo -->
        <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="modalMensajeMasivo" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="modalMensajeMasivo = false"></div>
                <div class="bg-white w-full max-w-lg rounded-3xl p-8 shadow-2xl z-10 relative overflow-hidden" @click.stop>
                    <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-sky-400 to-indigo-500"></div>
                    
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-black text-slate-800">Aviso Institucional</h2>
                            <p class="text-xs font-bold text-slate-400 mt-1">Se enviará a <span class="text-sky-500">Todo el personal vinculado</span></p>
                        </div>
                        <button @click="modalMensajeMasivo = false" class="text-slate-400 hover:bg-slate-100 w-8 h-8 rounded-xl flex items-center justify-center transition-colors" v-tippy="'Cerrar'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form @submit.prevent="enviarMensajeMasivo">
                        <div class="mb-4 bg-slate-50 p-4 rounded-2xl border border-slate-100 flex gap-3">
                            <div class="w-10 h-10 rounded-full bg-white text-indigo-500 flex items-center justify-center font-black shadow-sm shrink-0">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="w-full">
                                <textarea v-model="formMensajeMasivo.mensaje" required rows="5" class="w-full bg-transparent border-none p-0 text-sm outline-none resize-none placeholder-slate-400 text-slate-700" placeholder="Escriba aquí el comunicado oficial que será enviado a todos los docentes..."></textarea>
                                <p v-if="formMensajeMasivo.errors.mensaje" class="text-red-500 text-xs font-bold mt-1">{{ formMensajeMasivo.errors.mensaje }}</p>
                            </div>
                        </div>
                        
                        <button type="submit" :disabled="formMensajeMasivo.processing" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-3 rounded-2xl transition-all shadow-lg shadow-indigo-500/30 flex items-center justify-center gap-2">
                            <i class="fas fa-spinner fa-spin" v-if="formMensajeMasivo.processing"></i>
                            <span v-else>Emitir Aviso Oficial <i class="fas fa-bullhorn"></i></span>
                        </button>
                    </form>
                </div>
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>

<script>
export default {
    data() {
        return { mounted: false }
    },
    mounted() {
        this.mounted = true
    }
}
</script>
