<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    alumnos: Array,
    personal: Array,
    tutores: Array
});

const filtro = ref('alumnos');
const busqueda = ref('');
const mostrarModal = ref(false);
const impresionMasiva = ref(false);
const credencialActiva = ref(null);
const qrIndividualSrc = ref('');
const qrMasivoUrls = ref({}); // { 'id': 'data:image/png...' }

// Computar lista filtrada según el rol y la búsqueda
const listaActual = computed(() => {
    if (filtro.value === 'alumnos') return props.alumnos;
    if (filtro.value === 'personal') return props.personal;
    if (filtro.value === 'tutores') return props.tutores;
    return [];
});

const listaFiltrada = computed(() => {
    if (!busqueda.value) return listaActual.value;
    const lowerB = busqueda.value.toLowerCase();
    return listaActual.value.filter(item => 
        String(item.id).toLowerCase().includes(lowerB) || 
        String(item.nombre).toLowerCase().includes(lowerB) || 
        String(item.apellido).toLowerCase().includes(lowerB)
    );
});

// Generador de código QR (Retorna Data URL)
const generarQrDataUrl = async (item) => {
    try {
        const payload = JSON.stringify({ id: String(item.id), rol: item.rol });
        return await QRCode.toDataURL(payload, { width: 180, margin: 1, color: { dark: '#0f172a', light: '#ffffff' } });
    } catch (e) {
        console.error("Error generando QR", e);
        return '';
    }
};

const abrirVistaPrevia = async (item) => {
    credencialActiva.value = item;
    qrIndividualSrc.value = await generarQrDataUrl(item);
    mostrarModal.value = true;
};

const prepararImpresionMasiva = async () => {
    impresionMasiva.value = true;
    qrMasivoUrls.value = {};
    
    // Generar QRs de forma asíncrona para la vista de impresión
    for (const item of listaFiltrada.value) {
        qrMasivoUrls.value[item.id] = await generarQrDataUrl(item);
    }
};

const cerrarImpresionMasiva = () => {
    impresionMasiva.value = false;
};

const imprimirPantalla = () => {
    window.print();
};

const getRolColor = (rol) => {
    if (rol === 'Alumno') return 'bg-primary text-white';
    if (rol === 'Tutor') return 'bg-amber-500 text-white';
    return 'bg-secondary text-white'; // Personal
};

const getRolBarColor = (rol) => {
    if (rol === 'Alumno') return 'bg-primary';
    if (rol === 'Tutor') return 'bg-amber-500';
    return 'bg-secondary';
};
</script>

<template>
    <Head title="Generador de Credenciales QR" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-xl text-slate-800 dark:text-white tracking-tight">Tarjetas de <span class="text-primary">Identidad</span></h1>
        </Teleport>

        <!-- Main Workspace (Hidden during mass print) -->
        <div v-show="!impresionMasiva" class="p-6 md:p-8 max-w-6xl mx-auto animate-fade-in-up no-print">
            
            <div class="mb-8 hidden md:flex items-center justify-between">
                <div>
                    <h1 class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight">
                        Tarjetas de <span class="text-primary">Identidad Institucional</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-bold text-sm mt-1">Generación e impresión de credenciales QR PVC.</p>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3 bg-white dark:bg-slate-800/50 p-2 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Ver:</label>
                        <select v-model="filtro" class="border-none bg-transparent font-black text-slate-700 dark:text-slate-200 outline-none cursor-pointer focus:ring-0 uppercase text-sm">
                            <option value="alumnos">Alumnos</option>
                            <option value="personal">Personal del Plantel</option>
                            <option value="tutores">Tutores</option>
                        </select>
                    </div>
                    
                    <button @click="prepararImpresionMasiva" class="bg-primary hover:bg-blue-600 text-white px-5 py-3.5 rounded-2xl font-black text-sm shadow-md shadow-primary/20 transition-all flex items-center gap-2">
                        <i class="fas fa-print"></i> Imprimir {{ listaFiltrada.length }} Registros
                    </button>
                </div>
            </div>

            <!-- Controles de búsqueda -->
            <div class="premium-card overflow-hidden">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700/50 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/30">
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
                                <th>Identificador</th>
                                <th>Nombre Completo</th>
                                <th>Rol</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in listaFiltrada" :key="item.id" class="group">
                                <td>
                                    <p class="font-mono font-black text-primary text-sm">{{ item.id }}</p>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800 dark:text-slate-200 text-sm uppercase">{{ item.nombre }} {{ item.apellido }}</p>
                                </td>
                                <td>
                                    <span class="px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider shadow-sm border border-transparent" 
                                          :class="{
                                              'bg-blue-50 text-primary dark:bg-blue-500/10 dark:text-blue-400': item.rol === 'Alumno', 
                                              'bg-purple-50 text-secondary dark:bg-purple-500/10 dark:text-purple-400': item.rol !== 'Alumno' && item.rol !== 'Tutor', 
                                              'bg-orange-50 text-orange-500 dark:bg-orange-500/10 dark:text-orange-400': item.rol === 'Tutor'
                                          }">
                                        {{ item.rol }}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button @click="abrirVistaPrevia(item)" class="bg-slate-50 text-slate-500 hover:bg-secondary hover:text-white dark:bg-slate-700/50 dark:text-slate-300 px-5 py-2 rounded-xl font-bold text-xs transition-all mx-auto flex items-center gap-2 shadow-sm border border-slate-100 dark:border-slate-600/50 hover:border-transparent dark:hover:border-transparent">
                                            <i class="fas fa-eye"></i> Vista Previa
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="listaFiltrada.length === 0">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-search text-2xl"></i>
                                        </div>
                                        <p class="text-slate-500 font-bold">No se encontraron registros para generar credenciales.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Single Preview Modal -->
        <div v-show="mostrarModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4" v-cloak>
            <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm no-print" @click="mostrarModal = false"></div>
            
            <div class="flex flex-col items-center z-10 animate-fade-in-up">
                <!-- Tarjeta PVC Diseño -->
                <div class="bg-white w-[230px] h-[360px] rounded-2xl flex flex-col overflow-hidden mb-6 border border-slate-200 shadow-2xl relative">
                    <div class="h-4 w-full shrink-0" :class="getRolBarColor(credencialActiva?.rol)"></div>
                    <div class="p-5 flex-1 flex flex-col items-center justify-between text-center w-full">
                        <div class="flex flex-col items-center w-full">
                            <img src="/Logo del CAM.png" class="h-12 mb-2 object-contain">
                            <p class="text-[10px] font-black text-slate-800 uppercase tracking-widest leading-tight m-0">Centro de Atención Múltiple #48</p>
                            <p class="text-[8px] font-bold text-slate-400 mt-1 uppercase tracking-[0.2em] m-0">Nanchital, Veracruz</p>
                        </div>
                        <div class="my-auto w-full flex flex-col justify-center items-center py-2 relative">
                            <div v-if="credencialActiva?.foto_perfil" class="w-28 h-28 rounded-2xl overflow-hidden border-4 border-white shadow-lg bg-slate-100 z-10">
                                <img :src="`/storage/${credencialActiva.foto_perfil}`" class="w-full h-full object-cover" alt="Foto de perfil">
                            </div>
                            <div v-else class="w-28 h-28 rounded-2xl border-4 border-white shadow-lg bg-slate-100 flex items-center justify-center text-slate-300 z-10">
                                <i class="fas fa-user text-5xl"></i>
                            </div>
                            
                            <div class="absolute -bottom-4 -right-2 p-1.5 bg-white border border-slate-200 shadow-md rounded-xl z-20">
                                <img :src="qrIndividualSrc" class="w-12 h-12 object-contain" alt="QR Code">
                            </div>
                        </div>
                        <div class="w-full flex flex-col items-center mb-1">
                            <h2 class="text-sm font-black uppercase leading-tight text-slate-800 px-2 m-0">{{ credencialActiva?.nombre }} {{ credencialActiva?.apellido }}</h2>
                            <p class="text-[10px] font-mono font-bold text-slate-500 mt-1 mb-2 m-0">{{ credencialActiva?.id }}</p>
                            <span class="px-5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest shadow-md inline-block" :class="getRolColor(credencialActiva?.rol)">
                                {{ credencialActiva?.rol }}
                            </span>
                        </div>
                    </div>
                    <div class="bg-slate-50 py-2.5 text-center border-t border-slate-100 shrink-0 w-full">
                        <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest m-0">Ciclo Escolar 2025-2026</p>
                    </div>
                </div>
                
                <div class="flex gap-4 no-print">
                    <button @click="mostrarModal = false" class="bg-white text-slate-600 px-6 py-3 rounded-2xl font-bold shadow-lg hover:bg-slate-50 transition-all">Cancelar</button>
                    <button @click="imprimirPantalla" class="bg-primary text-white px-8 py-3 rounded-2xl font-black shadow-lg hover:bg-blue-600 transition-all flex items-center gap-2">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>

        <!-- Mass Print View (Takes over the entire screen when active) -->
        <div v-show="impresionMasiva" class="fixed inset-0 bg-slate-100 z-[200] overflow-y-auto print:static print:bg-white print:overflow-visible">
            
            <div class="bg-white p-6 shadow-sm sticky top-0 z-50 flex flex-col md:flex-row justify-between items-center gap-4 no-print border-b border-slate-200">
                <div>
                    <h2 class="text-2xl font-black text-slate-800 font-display">Cola de Impresión</h2>
                    <p class="text-sm font-bold text-slate-400 mt-1">Revisa las credenciales ({{ listaFiltrada.length }}) y presiona Imprimir.</p>
                </div>
                <div class="flex gap-4">
                    <button @click="cerrarImpresionMasiva" class="bg-slate-100 text-slate-600 px-6 py-3 rounded-2xl font-bold hover:bg-slate-200 transition-all border border-slate-200">Volver a la Tabla</button>
                    <button @click="imprimirPantalla" class="bg-secondary text-white px-8 py-3 rounded-2xl font-black shadow-lg shadow-purple-500/20 hover:bg-purple-600 transition-all flex items-center gap-2">
                        <i class="fas fa-print"></i> Imprimir Todo
                    </button>
                </div>
            </div>
            
            <div class="max-w-6xl mx-auto p-8 print:p-0 print:max-w-none print:w-full">
                <!-- CSS Grid designed for A4 Print (usually ~3 columns) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 print:grid-cols-3 print:gap-4 print:block">
                    
                    <template v-for="item in listaFiltrada" :key="'masivo-' + item.id">
                        <!-- Identical Card Design for Mass Print -->
                        <div class="bg-white w-[230px] h-[360px] rounded-2xl flex flex-col overflow-hidden border border-slate-200 shadow-md relative mx-auto print:float-left print:m-2 print:shadow-none print:border-slate-300 print:break-inside-avoid">
                            <div class="h-4 w-full shrink-0" :class="getRolBarColor(item.rol)"></div>
                            <div class="p-5 flex-1 flex flex-col items-center justify-between text-center w-full">
                                <div class="flex flex-col items-center w-full">
                                    <img src="/Logo del CAM.png" class="h-12 mb-2 object-contain">
                                    <p class="text-[10px] font-black text-slate-800 uppercase tracking-widest leading-tight m-0">Centro de Atención Múltiple #48</p>
                                    <p class="text-[8px] font-bold text-slate-400 mt-1 uppercase tracking-[0.2em] m-0">Nanchital, Veracruz</p>
                                </div>
                                <div class="my-auto w-full flex flex-col justify-center items-center py-2 relative">
                                    <div v-if="item.foto_perfil" class="w-28 h-28 rounded-2xl overflow-hidden border-4 border-white shadow-lg bg-slate-100 z-10 print:border-slate-200 print:shadow-none">
                                        <img :src="`/storage/${item.foto_perfil}`" class="w-full h-full object-cover" alt="Foto de perfil">
                                    </div>
                                    <div v-else class="w-28 h-28 rounded-2xl border-4 border-white shadow-lg bg-slate-100 flex items-center justify-center text-slate-300 z-10 print:border-slate-200 print:shadow-none">
                                        <i class="fas fa-user text-5xl"></i>
                                    </div>
                                    
                                    <div class="absolute -bottom-4 -right-2 p-1.5 bg-white border border-slate-200 shadow-md rounded-xl z-20 print:shadow-none">
                                        <img :src="qrMasivoUrls[item.id]" class="w-12 h-12 object-contain" alt="QR Code" v-if="qrMasivoUrls[item.id]">
                                        <div v-else class="w-12 h-12 bg-slate-100 animate-pulse rounded-lg"></div>
                                    </div>
                                </div>
                                <div class="w-full flex flex-col items-center mb-1">
                                    <h2 class="text-sm font-black uppercase leading-tight text-slate-800 px-2 m-0">{{ item.nombre }} {{ item.apellido }}</h2>
                                    <p class="text-[10px] font-mono font-bold text-slate-500 mt-1 mb-2 m-0">{{ item.id }}</p>
                                    <span class="px-5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest shadow-md inline-block print:border print:border-slate-300" :class="getRolColor(item.rol)">
                                        {{ item.rol }}
                                    </span>
                                </div>
                            </div>
                            <div class="bg-slate-50 py-2.5 text-center border-t border-slate-100 shrink-0 w-full print:bg-white">
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest m-0">Ciclo Escolar 2025-2026</p>
                            </div>
                        </div>
                    </template>
                    
                </div>
            </div>
            
        </div>

    </AuthenticatedLayout>
</template>

<style>
/* Utilities for printing */
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background-color: white !important;
    }
    @page {
        margin: 0.5cm;
    }
}
</style>
