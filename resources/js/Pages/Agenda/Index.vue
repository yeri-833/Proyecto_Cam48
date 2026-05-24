<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    eventos: Array
});

const mostrarModal = ref(false);
const modoEdicion = ref(false);

const form = useForm({
    id: null,
    nombre_evento: '',
    fecha_hora: '',
    ubicacion: ''
});

const abrirModalNuevo = () => {
    form.reset();
    modoEdicion.value = false;
    mostrarModal.value = true;
};

const abrirModalEditar = (evento) => {
    form.id = evento.id;
    form.nombre_evento = evento.nombre_evento;
    // Format to YYYY-MM-DDTHH:MM for datetime-local input
    form.fecha_hora = new Date(evento.fecha_hora).toISOString().slice(0, 16);
    form.ubicacion = evento.ubicacion;
    modoEdicion.value = true;
    mostrarModal.value = true;
};

const guardarEvento = () => {
    form.post(route('eventos.store'), {
        onSuccess: () => {
            mostrarModal.value = false;
            Swal.fire({
                title: '¡Guardado!',
                text: 'El evento se ha agendado exitosamente.',
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });
};

const cancelarEvento = (id) => {
    Swal.fire({
        title: '¿Cancelar evento?',
        text: "Se eliminará de la agenda para todos los usuarios.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener',
        customClass: {
            popup: 'rounded-3xl',
            confirmButton: 'rounded-xl font-bold px-6 py-3',
            cancelButton: 'rounded-xl font-bold px-6 py-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('eventos.destroy', id));
        }
    });
};
</script>

<template>
    <Head title="Agenda y Eventos" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-xl text-slate-800 dark:text-white tracking-tight">Agenda <span class="text-primary">Escolar</span></h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto animate-fade-in-up">
            
            <div class="mb-8 hidden md:flex items-center justify-between">
                <div>
                    <h1 class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight">
                        Agenda <span class="text-primary">Escolar</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-bold text-sm mt-1">Próximos eventos, reuniones y suspensiones de clases.</p>
                </div>
                
                <button @click="abrirModalNuevo" class="btn-premium">
                    <i class="fas fa-calendar-plus"></i> Nuevo Evento
                </button>
            </div>

            <!-- Listado de Eventos (Timeline Premium) -->
            <div class="bg-slate-50/50 backdrop-blur-md rounded-[32px] border border-slate-100 p-6 md:p-10 shadow-xl shadow-slate-100/40">
                
                <div v-if="eventos.length === 0" class="py-20 text-center animate-fade-in">
                    <div class="inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-blue-50 text-primary mb-8 shadow-inner">
                        <i class="fas fa-calendar-alt text-5xl animate-bounce"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-800 font-display mb-3 tracking-tight">No hay eventos agendados</h3>
                    <p class="text-slate-500 font-bold max-w-md mx-auto text-sm leading-relaxed">Tu agenda escolar está despejada. Presiona el botón de "Nuevo Evento" para programar una actividad o junta general de padres.</p>
                </div>

                <div v-else class="space-y-12 relative before:absolute before:inset-0 before:left-5 md:before:left-1/2 before:-translate-x-px before:h-full before:w-1 before:bg-gradient-to-b before:from-primary/20 before:via-blue-500/30 before:to-transparent">
                    
                    <div v-for="evento in eventos" :key="evento.id" class="relative flex flex-col md:flex-row items-start md:items-center justify-between md:justify-normal md:odd:flex-row-reverse group animate-fade-in-up">
                        
                        <!-- Timeline Icon with dynamic glow -->
                        <div class="flex items-center justify-center w-12 h-12 rounded-2xl border-4 border-white dark:border-slate-900 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-lg z-20 transition-all duration-300 group-hover:scale-110" 
                             :class="evento.es_hoy ? 'bg-gradient-to-tr from-primary to-blue-400 text-white shadow-primary/30 ring-4 ring-primary/10' : 'bg-white dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:text-primary dark:hover:text-primary'">
                            <i class="fas text-sm" :class="evento.es_hoy ? 'fa-star animate-pulse' : 'fa-calendar-day'"></i>
                        </div>
                        
                        <!-- Event Card Premium -->
                        <div class="w-full pl-16 md:pl-0 md:w-[calc(50%-3rem)] transition-all duration-500 group-hover:-translate-y-1">
                            <div class="relative p-6 rounded-[24px] border bg-white dark:bg-slate-800/50 shadow-sm transition-all duration-300 group-hover:shadow-xl group-hover:shadow-slate-100/80 dark:group-hover:shadow-none overflow-hidden"
                                 :class="evento.es_hoy ? 'border-primary/40 ring-1 ring-primary/5 bg-gradient-to-b from-white to-blue-50/10 dark:from-slate-800 dark:to-blue-900/10' : 'border-slate-100 dark:border-slate-700/50 hover:border-slate-200 dark:hover:border-slate-600'">
                                
                                <!-- Decorative left border tag -->
                                <div class="absolute left-0 top-0 bottom-0 w-1.5" :class="evento.es_hoy ? 'bg-primary' : 'bg-slate-200 group-hover:bg-primary/50 transition-colors'"></div>

                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center gap-2">
                                        <span class="px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm" 
                                              :class="evento.es_hoy ? 'bg-gradient-to-r from-primary/10 to-blue-500/10 text-primary border border-primary/20' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border border-slate-100 dark:border-slate-600'">
                                            {{ evento.fecha_formateada }}
                                        </span>
                                        <span v-if="evento.es_hoy" class="flex h-2.5 w-2.5 relative">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                                        </span>
                                    </div>
                                    
                                    <!-- Floating Actions -->
                                    <div class="flex gap-1.5 opacity-80 group-hover:opacity-100 transition-opacity">
                                        <button @click="abrirModalEditar(evento)" class="table-action-btn bg-slate-50 dark:bg-slate-700/50 hover:bg-primary/10 text-slate-400 dark:text-slate-500 hover:text-primary dark:hover:text-primary transition-all duration-200 flex items-center justify-center" v-tippy="'Editar Evento'">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="cancelarEvento(evento.id)" class="table-action-btn bg-slate-50 dark:bg-slate-700/50 hover:bg-red-50 text-slate-400 dark:text-slate-500 hover:text-red-500 transition-all duration-200 flex items-center justify-center" v-tippy="'Cancelar Evento'">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>

                                <h3 class="font-display font-black text-slate-800 dark:text-slate-200 text-lg uppercase mb-3 leading-snug tracking-tight group-hover:text-primary transition-colors">
                                    {{ evento.nombre_evento }}
                                </h3>

                                <div class="flex flex-wrap items-center gap-4 pt-3 border-t border-slate-50 dark:border-slate-700/50 text-xs font-bold text-slate-500">
                                    <span class="flex items-center gap-2 bg-slate-50 dark:bg-slate-700/30 px-2.5 py-1.5 rounded-lg text-slate-600 dark:text-slate-400">
                                        <i class="far fa-clock text-primary"></i> 
                                        {{ evento.hora_formateada }}
                                    </span>
                                    <span v-if="evento.ubicacion" class="flex items-center gap-2 bg-slate-50 dark:bg-slate-700/30 px-2.5 py-1.5 rounded-lg text-slate-600 dark:text-slate-400 max-w-[200px] truncate">
                                        <i class="fas fa-map-marker-alt text-red-400"></i> 
                                        {{ evento.ubicacion }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- Modal Formulario -->
        <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="mostrarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="mostrarModal = false"></div>
                
                <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md w-full max-w-md rounded-3xl shadow-2xl z-10 relative overflow-hidden border border-white/20 dark:border-slate-700/50" @click.stop>
                    <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-primary to-secondary"></div>
                    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="font-black text-slate-800 dark:text-white text-xl font-display tracking-tight">{{ modoEdicion ? 'Editar Evento' : 'Agendar Evento' }}</h3>
                        <button @click="mostrarModal = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center transition-colors" v-tippy="'Cerrar'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <form @submit.prevent="guardarEvento">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Título del Evento</label>
                                    <input type="text" v-model="form.nombre_evento" required autofocus class="input-premium w-full text-sm uppercase" placeholder="Ej. REUNIÓN DE TUTORES">
                                    <p v-if="form.errors.nombre_evento" class="text-red-500 text-xs font-bold mt-1">{{ form.errors.nombre_evento }}</p>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Fecha y Hora</label>
                                    <input type="datetime-local" v-model="form.fecha_hora" required class="input-premium w-full text-sm">
                                    <p v-if="form.errors.fecha_hora" class="text-red-500 text-xs font-bold mt-1">{{ form.errors.fecha_hora }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Ubicación (Opcional)</label>
                                    <input type="text" v-model="form.ubicacion" class="input-premium w-full text-sm" placeholder="Ej. Patio Central">
                                    <p v-if="form.errors.ubicacion" class="text-red-500 text-xs font-bold mt-1">{{ form.errors.ubicacion }}</p>
                                </div>
                            </div>
                            
                            <div class="mt-8 flex justify-end gap-3">
                                <button type="button" @click="mostrarModal = false" class="btn-secondary">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="btn-premium">
                                    <i class="fas fa-spinner fa-spin mr-2" v-if="form.processing"></i> {{ modoEdicion ? 'Actualizar Evento' : 'Guardar en Agenda' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>
