<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    alumnos: {
        type: Array,
        default: () => [],
    },
    personal: {
        type: Array,
        default: () => [],
    },
    tutores: {
        type: Array,
        default: () => [],
    }
});

const filtro = ref('alumnos');
const busqueda = ref('');
const mostrarModal = ref(false);
const impresionMasiva = ref(false);
const credencialActiva = ref(null);
const qrIndividualSrc = ref('');
const qrMasivoUrls = ref({});

// Computar lista filtrada según el rol y la búsqueda
const listaActual = computed(() => {
    if (filtro.value === 'alumnos') return props.alumnos;
    if (filtro.value === 'personal') return props.personal;
    if (filtro.value === 'tutores') return props.tutores;
    return [];
});

const listaFiltrada = computed(() => {
    if (!busqueda.value) return listaActual.value;
    const lowerB = busqueda.value.toLowerCase().trim();
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
        return await QRCode.toDataURL(payload, { 
            width: 180, 
            margin: 1, 
            color: { dark: '#0f172a', light: '#ffffff' } 
        });
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
    if (rol === 'Alumno') return 'bg-sky-500 text-white';
    if (rol === 'Tutor') return 'bg-amber-500 text-white';
    return 'bg-purple-600 text-white';
};

const getRolBarColor = (rol) => {
    if (rol === 'Alumno') return 'bg-sky-500';
    if (rol === 'Tutor') return 'bg-amber-500';
    return 'bg-purple-600';
};
</script>

<template>
    <Head title="Credenciales Escolares — FamilyLock" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-lg text-slate-900 dark:text-white">Credenciales QR</h1>
        </Teleport>

        <!-- Main Workspace (Hidden during mass print) -->
        <div v-show="!impresionMasiva" class="p-6 md:p-8 max-w-7xl mx-auto no-print">
            
            <!-- Header Section -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                        Credenciales <span class="text-gradient-primary">Institucionales</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-medium text-xs sm:text-sm mt-1">
                        Generación e impresión de tarjetas de identidad escolar con código QR.
                    </p>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Segmented Role Selector (Apple Segmented Control) -->
                    <div class="inline-flex p-1 rounded-2xl bg-slate-200/70 dark:bg-slate-800 border border-slate-300/60 dark:border-white/10 text-xs font-bold shadow-inner">
                        <button 
                            @click="filtro = 'alumnos'" 
                            :class="filtro === 'alumnos' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            class="px-3.5 py-1.5 rounded-xl transition-all"
                        >
                            Alumnos
                        </button>
                        <button 
                            @click="filtro = 'personal'" 
                            :class="filtro === 'personal' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            class="px-3.5 py-1.5 rounded-xl transition-all"
                        >
                            Personal
                        </button>
                        <button 
                            @click="filtro = 'tutores'" 
                            :class="filtro === 'tutores' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            class="px-3.5 py-1.5 rounded-xl transition-all"
                        >
                            Tutores
                        </button>
                    </div>
                    
                    <button @click="prepararImpresionMasiva" class="btn-premium flex items-center gap-2">
                        <i class="fas fa-print text-xs"></i> 
                        <span>Imprimir {{ listaFiltrada.length }} Tarjetas</span>
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
                            placeholder="Buscar por nombre o identificador..." 
                            class="input-premium w-full pl-9 pr-4 py-2 text-xs"
                        />
                    </div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {{ listaFiltrada.length }} registros disponibles
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Identificador</th>
                                <th>Nombre Completo</th>
                                <th>Rol Institucional</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in listaFiltrada" :key="item.id" class="group">
                                <td>
                                    <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs bg-sky-50 dark:bg-sky-500/10 px-2 py-1 rounded-md border border-sky-200/50 dark:border-sky-500/20">
                                        {{ item.id }}
                                    </span>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase leading-tight">
                                        {{ item.nombre }} {{ item.apellido }}
                                    </p>
                                </td>
                                <td>
                                    <span 
                                        class="badge" 
                                        :class="{
                                            'badge-blue': item.rol === 'Alumno', 
                                            'badge-slate': item.rol !== 'Alumno' && item.rol !== 'Tutor', 
                                            'badge-amber': item.rol === 'Tutor'
                                        }"
                                    >
                                        {{ item.rol }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex justify-center">
                                        <button 
                                            @click="abrirVistaPrevia(item)" 
                                            class="btn-ghost"
                                        >
                                            <i class="fas fa-id-card text-sky-500"></i>
                                            <span>Vista Previa</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="listaFiltrada.length === 0">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-id-card text-2xl"></i>
                                        </div>
                                        <p class="text-slate-700 dark:text-slate-300 font-bold text-sm">No hay registros coincidentes</p>
                                        <p class="text-slate-400 text-xs mt-1">Ajusta el término de búsqueda o cambia la categoría.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Single Preview Modal (Apple Presentation Card) -->
        <div v-show="mostrarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" v-cloak>
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-md no-print" @click="mostrarModal = false"></div>
            
            <div class="flex flex-col items-center z-10">
                <!-- Tarjeta PVC Diseño Apple School -->
                <div class="bg-white w-[236px] h-[370px] rounded-3xl flex flex-col overflow-hidden mb-6 border border-slate-200 shadow-2xl relative select-none">
                    <!-- Accent bar -->
                    <div class="h-3.5 w-full shrink-0" :class="getRolBarColor(credencialActiva?.rol)"></div>
                    
                    <div class="p-4 flex-1 flex flex-col items-center justify-between text-center w-full">
                        <!-- Header -->
                        <div class="flex flex-col items-center w-full">
                            <img src="/Logo del CAM.png" alt="Logo CAM #48" class="h-10 mb-1.5 object-contain">
                            <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest leading-tight m-0">
                                Centro de Atención Múltiple #48
                            </p>
                            <p class="text-[7.5px] font-bold text-slate-400 uppercase tracking-wider mt-0.5 m-0">
                                Nanchital, Veracruz
                            </p>
                        </div>

                        <!-- Portrait & QR -->
                        <div class="my-auto w-full flex flex-col justify-center items-center py-2 relative">
                            <div v-if="credencialActiva?.foto_perfil" class="w-24 h-24 rounded-2xl overflow-hidden border-2 border-slate-100 shadow-md bg-slate-50 z-10">
                                <img :src="`/storage/${credencialActiva.foto_perfil}`" class="w-full h-full object-cover" alt="Foto">
                            </div>
                            <div v-else class="w-24 h-24 rounded-2xl border-2 border-slate-100 shadow-md bg-slate-50 flex items-center justify-center text-slate-300 z-10">
                                <i class="fas fa-user text-4xl"></i>
                            </div>
                            
                            <div class="absolute -bottom-3 -right-1 p-1 bg-white border border-slate-200 shadow-md rounded-xl z-20">
                                <img :src="qrIndividualSrc" class="w-11 h-11 object-contain" alt="QR Code">
                            </div>
                        </div>

                        <!-- Person Details -->
                        <div class="w-full flex flex-col items-center mb-0.5">
                            <h2 class="text-xs font-black uppercase leading-tight text-slate-800 px-2 m-0 line-clamp-2">
                                {{ credencialActiva?.nombre }} {{ credencialActiva?.apellido }}
                            </h2>
                            <p class="text-[9px] font-mono font-bold text-slate-500 mt-1 mb-1.5 m-0">
                                {{ credencialActiva?.id }}
                            </p>
                            <span class="px-3.5 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider inline-block shadow-sm" :class="getRolColor(credencialActiva?.rol)">
                                {{ credencialActiva?.rol }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-slate-50 py-2 text-center border-t border-slate-100 shrink-0 w-full">
                        <p class="text-[7.5px] font-bold text-slate-400 uppercase tracking-widest m-0">Ciclo Escolar 2025-2026</p>
                    </div>
                </div>
                
                <div class="flex gap-3 no-print">
                    <button @click="mostrarModal = false" class="btn-ghost bg-white/90 dark:bg-slate-800 text-slate-700 dark:text-slate-200">
                        Cerrar
                    </button>
                    <button @click="imprimirPantalla" class="btn-premium">
                        <i class="fas fa-print text-xs"></i> Imprimir Credencial
                    </button>
                </div>
            </div>
        </div>

        <!-- Mass Print View (Takes over the entire screen when active) -->
        <div v-show="impresionMasiva" class="fixed inset-0 bg-slate-100 dark:bg-slate-900 z-[200] overflow-y-auto print:static print:bg-white print:overflow-visible">
            
            <div class="bg-white dark:bg-slate-800 p-5 shadow-sm sticky top-0 z-50 flex flex-col sm:flex-row justify-between items-center gap-4 no-print border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h2 class="text-xl font-black text-slate-800 dark:text-white font-display">Cola de Impresión</h2>
                    <p class="text-xs font-medium text-slate-400 mt-0.5">Se han preparado {{ listaFiltrada.length }} credenciales con códigos QR.</p>
                </div>
                <div class="flex gap-3">
                    <button @click="cerrarImpresionMasiva" class="btn-ghost">
                        Volver a la Lista
                    </button>
                    <button @click="imprimirPantalla" class="btn-premium">
                        <i class="fas fa-print text-xs"></i> Imprimir Todo (A4)
                    </button>
                </div>
            </div>
            
            <div class="max-w-6xl mx-auto p-8 print:p-0 print:max-w-none print:w-full">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 print:grid-cols-3 print:gap-4 print:block">
                    <template v-for="item in listaFiltrada" :key="'masivo-' + item.id">
                        <div class="bg-white w-[236px] h-[370px] rounded-3xl flex flex-col overflow-hidden border border-slate-200 shadow-md relative mx-auto print:float-left print:m-2 print:shadow-none print:border-slate-300 print:break-inside-avoid">
                            <div class="h-3.5 w-full shrink-0" :class="getRolBarColor(item.rol)"></div>
                            
                            <div class="p-4 flex-1 flex flex-col items-center justify-between text-center w-full">
                                <div class="flex flex-col items-center w-full">
                                    <img src="/Logo del CAM.png" alt="Logo CAM #48" class="h-10 mb-1.5 object-contain">
                                    <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest leading-tight m-0">
                                        Centro de Atención Múltiple #48
                                    </p>
                                    <p class="text-[7.5px] font-bold text-slate-400 uppercase tracking-wider mt-0.5 m-0">
                                        Nanchital, Veracruz
                                    </p>
                                </div>
                                <div class="my-auto w-full flex flex-col justify-center items-center py-2 relative">
                                    <div v-if="item.foto_perfil" class="w-24 h-24 rounded-2xl overflow-hidden border-2 border-slate-100 shadow-md bg-slate-50 z-10 print:border-slate-200 print:shadow-none">
                                        <img :src="`/storage/${item.foto_perfil}`" class="w-full h-full object-cover" alt="Foto">
                                    </div>
                                    <div v-else class="w-24 h-24 rounded-2xl border-2 border-slate-100 shadow-md bg-slate-50 flex items-center justify-center text-slate-300 z-10 print:border-slate-200 print:shadow-none">
                                        <i class="fas fa-user text-4xl"></i>
                                    </div>
                                    
                                    <div class="absolute -bottom-3 -right-1 p-1 bg-white border border-slate-200 shadow-md rounded-xl z-20 print:shadow-none">
                                        <img :src="qrMasivoUrls[item.id]" class="w-11 h-11 object-contain" alt="QR" v-if="qrMasivoUrls[item.id]">
                                        <div v-else class="w-11 h-11 bg-slate-100 animate-pulse rounded-lg"></div>
                                    </div>
                                </div>
                                <div class="w-full flex flex-col items-center mb-0.5">
                                    <h2 class="text-xs font-black uppercase leading-tight text-slate-800 px-2 m-0 line-clamp-2">
                                        {{ item.nombre }} {{ item.apellido }}
                                    </h2>
                                    <p class="text-[9px] font-mono font-bold text-slate-500 mt-1 mb-1.5 m-0">
                                        {{ item.id }}
                                    </p>
                                    <span class="px-3.5 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider inline-block print:border print:border-slate-300" :class="getRolColor(item.rol)">
                                        {{ item.rol }}
                                    </span>
                                </div>
                            </div>
                            <div class="bg-slate-50 py-2 text-center border-t border-slate-100 shrink-0 w-full print:bg-white">
                                <p class="text-[7.5px] font-bold text-slate-400 uppercase tracking-widest m-0">Ciclo Escolar 2025-2026</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style>
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
