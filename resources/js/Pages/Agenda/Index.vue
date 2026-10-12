<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    eventos: {
        type: Array,
        default: () => [],
    }
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
                timer: 3000,
                customClass: { popup: 'rounded-2xl' }
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
            popup: 'swal2-popup',
            confirmButton: 'bg-rose-600 text-white font-bold px-5 py-2.5 rounded-xl',
            cancelButton: 'btn-ghost'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('eventos.destroy', id));
        }
    });
};
</script>

<template>
    <Head title="Agenda Escolar — FamilyLock" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-lg text-slate-900 dark:text-white">Agenda Escolar</h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-5xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                        Agenda <span class="text-gradient-primary">Escolar</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-medium text-xs sm:text-sm mt-1">
                        Calendario de actividades institucionales, reuniones de padres y suspensiones.
                    </p>
                </div>
                
                <button @click="abrirModalNuevo" class="btn-premium flex items-center gap-2">
                    <i class="fas fa-calendar-plus text-xs"></i> 
                    <span>Nuevo Evento</span>
                </button>
            </div>

            <!-- Listado de Eventos (Apple Calendar Timeline) -->
            <div class="premium-card-static p-6 sm:p-8">
                
                <!-- Empty State -->
                <div v-if="eventos.length === 0" class="py-16 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-sky-50 dark:bg-sky-500/10 text-sky-500 flex items-center justify-center text-3xl mx-auto mb-4">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 font-display">No hay eventos agendados</h3>
                    <p class="text-slate-400 text-xs mt-1 max-w-sm mx-auto">
                        La agenda escolar está despejada. Agrega actividades con el botón superior.
                    </p>
                </div>

                <!-- Event Cards Timeline -->
                <div v-else class="space-y-4">
                    <div 
                        v-for="evento in eventos" 
                        :key="evento.id" 
                        class="p-5 rounded-2xl border transition-all duration-200 group flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                        :class="evento.es_hoy 
                            ? 'bg-sky-500/[0.06] border-sky-400/40 shadow-sm' 
                            : 'bg-white/60 dark:bg-slate-800/40 border-slate-200/70 dark:border-white/[0.08] hover:border-slate-300 dark:hover:border-white/20'"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Date Icon Pill -->
                            <div 
                                class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center shrink-0 border text-center shadow-xs"
                                :class="evento.es_hoy 
                                    ? 'bg-sky-500 text-white border-sky-400 shadow-sky-500/30' 
                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200/80 dark:border-slate-700'"
                            >
                                <span class="text-[9px] uppercase font-bold tracking-wider leading-none">
                                    {{ new Date(evento.fecha_hora).toLocaleDateString('es-MX', { month: 'short' }) }}
                                </span>
                                <span class="text-base font-black leading-tight mt-0.5 font-display">
                                    {{ new Date(evento.fecha_hora).getDate() }}
                                </span>
                            </div>

                            <!-- Title & Info -->
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span 
                                        v-if="evento.es_hoy" 
                                        class="px-2 py-0.5 rounded-md bg-sky-500 text-white text-[9px] font-black uppercase tracking-wider"
                                    >
                                        Hoy
                                    </span>
                                    <span class="text-xs text-slate-400 font-medium">
                                        {{ evento.fecha_formateada }}
                                    </span>
                                </div>

                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 uppercase tracking-tight">
                                    {{ evento.nombre_evento }}
                                </h3>

                                <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    <span class="flex items-center gap-1.5">
                                        <i class="far fa-clock text-sky-500"></i>
                                        <span>{{ evento.hora_formateada }}</span>
                                    </span>
                                    <span v-if="evento.ubicacion" class="flex items-center gap-1.5">
                                        <i class="fas fa-map-pin text-slate-400"></i>
                                        <span>{{ evento.ubicacion }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1.5 self-end sm:self-center">
                            <button 
                                @click="abrirModalEditar(evento)" 
                                class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white"
                                title="Editar Evento"
                                aria-label="Editar Evento"
                            >
                                <i class="fas fa-pen text-xs"></i>
                            </button>
                            <button 
                                @click="cancelarEvento(evento.id)" 
                                class="table-action-btn bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-rose-600 hover:text-white"
                                title="Cancelar Evento"
                                aria-label="Cancelar Evento"
                            >
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- Modal Nuevo / Editar Evento (Apple Modal) -->
        <div v-if="mostrarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-md" @click="mostrarModal = false"></div>

            <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-white/60 dark:border-white/10 overflow-hidden z-10 flex flex-col">
                <div class="modal-accent-bar"></div>

                <div class="p-5 border-b border-slate-200/70 dark:border-white/[0.08] flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50">
                    <h3 class="font-display font-black text-base text-slate-900 dark:text-white tracking-tight">
                        {{ modoEdicion ? 'Editar Evento' : 'Agendar Nuevo Evento' }}
                    </h3>
                    <button 
                        @click="mostrarModal = false" 
                        class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition-all"
                        aria-label="Cerrar modal"
                    >
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <form @submit.prevent="guardarEvento" class="p-5 space-y-4">
                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Título del Evento *
                        </label>
                        <input 
                            type="text" 
                            v-model="form.nombre_evento" 
                            class="input-premium w-full uppercase" 
                            placeholder="Ej. Junta General de Padres" 
                            required 
                            autofocus
                        />
                        <p v-if="form.errors.nombre_evento" class="text-rose-500 text-xs font-semibold mt-1">
                            {{ form.errors.nombre_evento }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Fecha y Hora *
                        </label>
                        <input 
                            type="datetime-local" 
                            v-model="form.fecha_hora" 
                            class="input-premium w-full cursor-pointer" 
                            required
                        />
                        <p v-if="form.errors.fecha_hora" class="text-rose-500 text-xs font-semibold mt-1">
                            {{ form.errors.fecha_hora }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5 block">
                            Ubicación o Lugar
                        </label>
                        <input 
                            type="text" 
                            v-model="form.ubicacion" 
                            class="input-premium w-full" 
                            placeholder="Ej. Aula Magna, Patio Cívico"
                        />
                        <p v-if="form.errors.ubicacion" class="text-rose-500 text-xs font-semibold mt-1">
                            {{ form.errors.ubicacion }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-200/70 dark:border-white/[0.08] flex justify-end gap-3">
                        <button type="button" @click="mostrarModal = false" class="btn-ghost">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" class="btn-premium">
                            <i class="fas fa-circle-notch fa-spin text-xs" v-if="form.processing"></i>
                            <i class="fas fa-check text-xs" v-else></i>
                            <span>{{ modoEdicion ? 'Actualizar Evento' : 'Agendar Evento' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
