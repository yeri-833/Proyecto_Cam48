<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CredencialCard from '@/Components/CredencialCard.vue';
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
const isCardFlipped = ref(false);
const modoImpresionIndividual = ref('ambas'); // 'ambas' | 'frente' | 'reverso'

const impresionMasiva = ref(false);
const modoImpresionMasiva = ref('ambas'); // 'ambas' | 'frentes' | 'reversos'
const credencialActiva = ref(null);
const qrIndividualSrc = ref('');
const qrMasivoUrls = ref({});
const cicloEscolar = ref('Ciclo Escolar 2025-2026');

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

// Generador de código QR con alta densidad y zona de silencio óptima (320px)
// CONTRATO CRIPTOGRÁFICO INTRACTABLE: id, rol, signature
const generarQrDataUrl = async (item) => {
    try {
        const payload = JSON.stringify({ id: String(item.id), rol: item.rol, signature: item.signature });
        return await QRCode.toDataURL(payload, { 
            width: 320, 
            margin: 1, 
            color: { dark: '#102944', light: '#ffffff' } 
        });
    } catch (e) {
        console.error("Error generando QR", e);
        return '';
    }
};

const abrirVistaPrevia = async (item) => {
    credencialActiva.value = item;
    isCardFlipped.value = false;
    modoImpresionIndividual.value = 'ambas';
    qrIndividualSrc.value = await generarQrDataUrl(item);
    mostrarModal.value = true;
};

const girarCredencial = () => {
    isCardFlipped.value = !isCardFlipped.value;
};

const prepararImpresionMasiva = async () => {
    impresionMasiva.value = true;
    modoImpresionMasiva.value = 'ambas';
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
                        Credenciales PVC CR80 con doble cara, giro 3D interactivo y código QR de acceso seguro.
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
                                    <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase leading-tight font-display">
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
                                            <span>Vista Previa 3D</span>
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

        <!-- Single Preview Modal (Apple Identification Viewer with 3D Flip) -->
        <div v-show="mostrarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" v-cloak>
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-xl no-print transition-opacity duration-300" @click="mostrarModal = false"></div>
            
            <div class="relative z-10 w-full max-w-lg bg-white dark:bg-slate-900 rounded-[2.5rem] border border-slate-200 dark:border-white/10 shadow-2xl overflow-hidden flex flex-col items-center p-5 sm:p-6 no-print animate-scale-in">
                
                <!-- Modal Top Header -->
                <div class="w-full flex items-center justify-between pb-3 mb-2 border-b border-slate-100 dark:border-white/[0.08]">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-sm shadow-xs">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white font-display">
                                Vista Previa de Credencial
                            </h3>
                            <p class="text-[10px] font-medium text-slate-400">
                                Estándar CR80 doble cara • CAM #48 Tonalpouhqui
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="mostrarModal = false"
                        class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-white flex items-center justify-center transition-colors"
                        aria-label="Cerrar modal"
                    >
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                <!-- Refined Controls Bar (No Text Wrapping, Clear Visual Separation) -->
                <div class="w-full py-2 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-white/5 mb-3 flex flex-col gap-1.5">
                    <div class="flex items-center justify-between">
                        <!-- Current Face Indicator Badge -->
                        <span 
                            class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 whitespace-nowrap shadow-xs"
                            :class="isCardFlipped ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'"
                        >
                            <i :class="isCardFlipped ? 'fas fa-qrcode text-xs' : 'fas fa-user text-xs'"></i>
                            {{ isCardFlipped ? 'Cara Posterior' : 'Cara Frontal' }}
                        </span>

                        <!-- Flip Button with Adequate Spacing -->
                        <button 
                            @click="girarCredencial"
                            class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold shadow-xs border border-slate-200/80 dark:border-white/10 flex items-center gap-1.5 transition-all active:scale-95 whitespace-nowrap"
                        >
                            <i class="fas fa-sync-alt text-[11px] text-sky-500 transition-transform duration-500" :class="{ 'rotate-180': isCardFlipped }"></i>
                            <span>Girar credencial</span>
                        </button>
                    </div>

                    <!-- Subtle helper caption -->
                    <p class="text-[9.5px] text-slate-400 text-center m-0 leading-tight">
                        Haz clic sobre la tarjeta o presiona <kbd class="px-1 py-0.5 text-[8.5px] font-mono bg-white dark:bg-slate-700 rounded border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300">Espacio</kbd> para girar 180°
                    </p>
                </div>

                <!-- Credential Card 3D Preview Component -->
                <div class="my-1 flex justify-center w-full">
                    <CredencialCard 
                        v-if="credencialActiva" 
                        :item="credencialActiva" 
                        :qr-src="qrIndividualSrc" 
                        :ciclo="cicloEscolar"
                        :is-flipped="isCardFlipped"
                        mode="interactive"
                        @update:is-flipped="isCardFlipped = $event"
                    />
                </div>

                <!-- Print Options Segmented Controller -->
                <div class="w-full mt-3 pt-2.5 border-t border-slate-100 dark:border-white/[0.08]">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            Modo de Impresión Individual:
                        </span>
                        <span class="text-[9px] font-mono font-medium text-slate-400">
                            CR80: 53.98 × 85.60 mm
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-[10.5px] font-bold">
                        <button 
                            @click="modoImpresionIndividual = 'ambas'"
                            :class="modoImpresionIndividual === 'ambas' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500'"
                            class="py-1.5 rounded-lg transition-all"
                        >
                            Ambas Caras
                        </button>
                        <button 
                            @click="modoImpresionIndividual = 'frente'"
                            :class="modoImpresionIndividual === 'frente' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500'"
                            class="py-1.5 rounded-lg transition-all"
                        >
                            Solo Frente
                        </button>
                        <button 
                            @click="modoImpresionIndividual = 'reverso'"
                            :class="modoImpresionIndividual === 'reverso' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500'"
                            class="py-1.5 rounded-lg transition-all"
                        >
                            Solo Reverso
                        </button>
                    </div>
                </div>
                
                <!-- Modal Action Buttons -->
                <div class="w-full flex items-center justify-between gap-3 pt-3 mt-1">
                    <button 
                        @click="mostrarModal = false" 
                        class="btn-secondary text-xs flex-1"
                    >
                        Cerrar
                    </button>
                    <button 
                        @click="imprimirPantalla" 
                        class="btn-premium text-xs flex-1 flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-print text-xs"></i> 
                        <span>Imprimir {{ modoImpresionIndividual === 'ambas' ? 'Doble Cara' : modoImpresionIndividual === 'frente' ? 'Frente' : 'Reverso' }}</span>
                    </button>
                </div>
            </div>

            <!-- Single Print Shell (Strictly Flat CR80 Cards for Physical Paper Output) -->
            <div class="hidden print:block print:fixed print:inset-0 print:m-0 print:p-0 print:bg-white z-[9999]">
                <!-- Caso 1: Ambas caras (Página 1 Frente, Página 2 Reverso) -->
                <template v-if="modoImpresionIndividual === 'ambas'">
                    <div class="print:block page-break">
                        <CredencialCard 
                            v-if="credencialActiva" 
                            :item="credencialActiva" 
                            :qr-src="qrIndividualSrc" 
                            :ciclo="cicloEscolar" 
                            mode="frente"
                        />
                    </div>
                    <div class="print:block">
                        <CredencialCard 
                            v-if="credencialActiva" 
                            :item="credencialActiva" 
                            :qr-src="qrIndividualSrc" 
                            :ciclo="cicloEscolar" 
                            mode="reverso"
                        />
                    </div>
                </template>

                <!-- Caso 2: Solo Frente -->
                <template v-else-if="modoImpresionIndividual === 'frente'">
                    <div class="print:block">
                        <CredencialCard 
                            v-if="credencialActiva" 
                            :item="credencialActiva" 
                            :qr-src="qrIndividualSrc" 
                            :ciclo="cicloEscolar" 
                            mode="frente"
                        />
                    </div>
                </template>

                <!-- Caso 3: Solo Reverso -->
                <template v-else-if="modoImpresionIndividual === 'reverso'">
                    <div class="print:block">
                        <CredencialCard 
                            v-if="credencialActiva" 
                            :item="credencialActiva" 
                            :qr-src="qrIndividualSrc" 
                            :ciclo="cicloEscolar" 
                            mode="reverso"
                        />
                    </div>
                </template>
            </div>
        </div>

        <!-- Mass Print View (Takes over the entire screen when active) -->
        <div v-show="impresionMasiva" class="fixed inset-0 bg-slate-100 dark:bg-slate-900 z-[200] overflow-y-auto print:static print:bg-white print:overflow-visible">
            
            <!-- Controls bar (No Print) -->
            <div class="bg-white dark:bg-slate-800 p-5 shadow-sm sticky top-0 z-50 flex flex-col md:flex-row justify-between items-center gap-4 no-print border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg">
                        <i class="fas fa-copy"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-800 dark:text-white font-display">Impresión Masiva Doble Cara</h2>
                        <p class="text-xs font-medium text-slate-400 mt-0.5">
                            {{ listaFiltrada.length }} credenciales preparadas para hojas A4 (cuadrícula 3 × 3).
                        </p>
                    </div>
                </div>

                <!-- Print Mode Selector -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-bold">
                        <button 
                            @click="modoImpresionMasiva = 'ambas'"
                            :class="modoImpresionMasiva === 'ambas' ? 'bg-white dark:bg-slate-600 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-300'"
                            class="px-3 py-1.5 rounded-lg transition-all"
                        >
                            Frentes y Reversos
                        </button>
                        <button 
                            @click="modoImpresionMasiva = 'frentes'"
                            :class="modoImpresionMasiva === 'frentes' ? 'bg-white dark:bg-slate-600 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-300'"
                            class="px-3 py-1.5 rounded-lg transition-all"
                        >
                            Solo Frentes
                        </button>
                        <button 
                            @click="modoImpresionMasiva = 'reversos'"
                            :class="modoImpresionMasiva === 'reversos' ? 'bg-white dark:bg-slate-600 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-300'"
                            class="px-3 py-1.5 rounded-lg transition-all"
                        >
                            Solo Reversos
                        </button>
                    </div>

                    <button @click="cerrarImpresionMasiva" class="btn-ghost">
                        Volver
                    </button>
                    <button @click="imprimirPantalla" class="btn-premium flex items-center gap-2">
                        <i class="fas fa-print text-xs"></i> Imprimir en A4
                    </button>
                </div>
            </div>
            
            <!-- Cards Container -->
            <div class="max-w-6xl mx-auto p-8 print:p-0 print:max-w-none print:w-full">
                
                <!-- SECCIÓN DE FRENTES (Se muestra si modo es 'ambas' o 'frentes') -->
                <div 
                    v-if="modoImpresionMasiva === 'ambas' || modoImpresionMasiva === 'frentes'"
                    class="mb-12 print:mb-0"
                    :class="{ 'page-break': modoImpresionMasiva === 'ambas' }"
                >
                    <div class="mb-4 no-print flex items-center justify-between border-b pb-2">
                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider font-display">
                            Páginas de Frentes (Identidad y Fotografías)
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">Cuadrícula 3x3 por hoja A4</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 print:grid-cols-3 print:gap-2 print:block">
                        <template v-for="item in listaFiltrada" :key="'frente-' + item.id">
                            <CredencialCard 
                                :item="item" 
                                :qr-src="qrMasivoUrls[item.id]" 
                                :ciclo="cicloEscolar" 
                                mode="frente"
                                :is-mass-print="true" 
                            />
                        </template>
                    </div>
                </div>

                <!-- SECCIÓN DE REVERSOS (Se muestra si modo es 'ambas' o 'reversos') -->
                <div 
                    v-if="modoImpresionMasiva === 'ambas' || modoImpresionMasiva === 'reversos'"
                    class="mb-12 print:mb-0"
                >
                    <div class="mb-4 no-print flex items-center justify-between border-b pb-2">
                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider font-display">
                            Páginas de Reversos (Códigos QR e Instrucciones)
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">Mismo orden emparejado para doble cara</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 print:grid-cols-3 print:gap-2 print:block">
                        <template v-for="item in listaFiltrada" :key="'reverso-' + item.id">
                            <CredencialCard 
                                :item="item" 
                                :qr-src="qrMasivoUrls[item.id]" 
                                :ciclo="cicloEscolar" 
                                mode="reverso"
                                :is-mass-print="true" 
                            />
                        </template>
                    </div>
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
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    @page {
        size: A4 portrait;
        margin: 8mm 6mm;
    }
    .page-break {
        page-break-after: always !important;
        break-after: page !important;
    }
}
</style>
