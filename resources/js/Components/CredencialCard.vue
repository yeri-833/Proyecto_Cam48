<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    qrSrc: {
        type: String,
        default: '',
    },
    ciclo: {
        type: String,
        default: 'Ciclo Escolar 2025-2026',
    },
    mode: {
        type: String,
        default: 'interactive', // 'interactive' | 'frente' | 'reverso'
    },
    isFlipped: {
        type: Boolean,
        default: false,
    },
    isMassPrint: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['update:isFlipped', 'flip']);

const internalFlipped = ref(props.isFlipped);

watch(() => props.isFlipped, (val) => {
    internalFlipped.value = val;
});

const toggleFlip = () => {
    if (props.mode !== 'interactive') return;
    internalFlipped.value = !internalFlipped.value;
    emit('update:isFlipped', internalFlipped.value);
    emit('flip', internalFlipped.value);
};

const handleKeyDown = (e) => {
    if (props.mode !== 'interactive') return;
    if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
        e.preventDefault();
        toggleFlip();
    }
};

defineExpose({
    toggleFlip,
    isFlipped: internalFlipped,
});

// Estilos dinámicos por Rol Institucional
const rolBadgeStyle = computed(() => {
    const rol = props.item?.rol;
    if (rol === 'Alumno') {
        return {
            badge: 'bg-[#009FE3] text-white border-[#0284c7]',
            accent: '#009FE3',
            tag: 'ALUMNO REGULAR'
        };
    }
    if (rol === 'Tutor') {
        return {
            badge: 'bg-[#D97706] text-white border-[#b45309]',
            accent: '#D97706',
            tag: 'TUTOR AUTORIZADO'
        };
    }
    return {
        badge: 'bg-[#7C3AED] text-white border-[#6d28d9]',
        accent: '#7C3AED',
        tag: 'PERSONAL INSTITUCIONAL'
    };
});
</script>

<template>
    <!-- MODO 1: TARJETA INTERACTIVA 3D (Vista Previa en Modal) -->
    <div 
        v-if="mode === 'interactive'"
        class="perspective-container select-none cursor-pointer group outline-none focus-visible:ring-4 focus-visible:ring-sky-400 rounded-3xl"
        tabindex="0"
        role="button"
        :aria-label="`Credencial de ${item?.nombre || 'titular'}. Cara visible: ${internalFlipped ? 'Reverso con QR' : 'Frente con foto'}. Presiona Enter o Espacio para girar.`"
        @click="toggleFlip"
        @keydown="handleKeyDown"
    >
        <div 
            class="card-flipper"
            :class="{ 'is-flipped': internalFlipped }"
        >
            <!-- ============================================== -->
            <!-- CARA FRONTAL (FRENTE CON IDENTIDAD Y FOTO)     -->
            <!-- ============================================== -->
            <div class="card-face card-face-front bg-white text-slate-900 border border-slate-300 shadow-2xl flex flex-col justify-between overflow-hidden relative">
                
                <!-- Borde Superior de Acento de Rol -->
                <div class="h-1.5 w-full shrink-0" :style="{ backgroundColor: rolBadgeStyle.accent }"></div>

                <!-- Cabecera Institucional Azul Marino + Detalle Cian -->
                <div class="bg-[#102944] text-white px-3.5 pt-2.5 pb-2.5 shrink-0 relative overflow-hidden border-b-2 border-[#009FE3]">
                    <!-- Fondo geométrico tenue de ondas -->
                    <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-[#009FE3]/15 rounded-full blur-sm pointer-events-none"></div>
                    <div class="absolute -right-2 -top-4 w-16 h-16 bg-white/5 rounded-full pointer-events-none"></div>

                    <div class="flex items-center gap-2.5 relative z-10">
                        <img 
                            src="/isotipo-cam.svg" 
                            alt="Emblema CAM #48" 
                            class="w-11 h-11 shrink-0 object-contain drop-shadow"
                        />
                        <div class="flex-1 min-w-0 text-left">
                            <h4 class="text-[9.5px] font-black uppercase tracking-tight text-white leading-tight truncate font-display">
                                Centro de Atención Múltiple #48
                            </h4>
                            <p class="text-[8.5px] font-black uppercase tracking-wider text-[#38BDF8] leading-none mt-0.5">
                                Tonalpouhqui
                            </p>
                            <p class="text-[7px] font-semibold uppercase tracking-widest text-slate-300 leading-none mt-0.5">
                                Nanchital, Veracruz
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Cuerpo Central con Curvas y Espaciado Equilibrado -->
                <div class="px-4 py-2 flex-1 flex flex-col items-center justify-between text-center w-full bg-white relative overflow-hidden">
                    
                    <!-- Marca de Agua Gráfica Geométrica Institucional (Inspirada en el libro del CAM) -->
                    <svg class="absolute inset-0 w-full h-full pointer-events-none opacity-[0.035]" viewBox="0 0 256 300" fill="none">
                        <path d="M20 180 Q128 140 236 180" stroke="#102944" stroke-width="24" />
                        <path d="M40 210 Q128 175 216 210" stroke="#009FE3" stroke-width="16" />
                        <circle cx="128" cy="80" r="70" stroke="#102944" stroke-width="12" />
                    </svg>

                    <!-- Elemento 1: Fotografía Protagónica con Marco Doble Oficial -->
                    <div class="w-full flex justify-center mt-1 z-10">
                        <div class="w-[92px] h-[104px] rounded-2xl overflow-hidden border-2 border-[#102944] p-0.5 bg-gradient-to-b from-[#009FE3]/30 to-slate-200 shadow-md shrink-0 flex items-center justify-center">
                            <div class="w-full h-full rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center">
                                <img 
                                    v-if="item?.foto_perfil" 
                                    :src="`/storage/${item.foto_perfil}`" 
                                    class="w-full h-full object-cover" 
                                    :alt="item.nombre"
                                />
                                <div v-else class="text-slate-300 flex flex-col items-center justify-center w-full h-full bg-slate-50">
                                    <i class="fas fa-user text-3xl text-slate-300"></i>
                                    <span class="text-[6.5px] font-bold text-slate-400 uppercase tracking-widest mt-1">Sin Foto</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Elemento 2: Nombre Completo con Jerarquía Fuerte -->
                    <div class="w-full px-1 my-1 z-10">
                        <h3 class="text-[12.5px] font-black uppercase leading-snug text-[#102944] tracking-tight line-clamp-2 m-0 font-display">
                            {{ item?.nombre }} {{ item?.apellido }}
                        </h3>
                    </div>

                    <!-- Elemento 3: Matrícula / Identificador en Bloque Azul Claro -->
                    <div class="z-10 inline-flex items-center gap-2 px-3 py-0.5 rounded-lg bg-sky-50 border border-sky-200/90 shadow-xs">
                        <span class="text-[7.5px] font-extrabold uppercase tracking-wider text-sky-700">MATRÍCULA / ID:</span>
                        <span class="text-[10px] font-mono font-black text-[#102944]">{{ item?.id }}</span>
                    </div>

                    <!-- Elemento 4: Rol Institucional -->
                    <div class="z-10 mt-1 mb-0.5">
                        <span 
                            class="px-4 py-1 rounded-full text-[8.5px] font-black uppercase tracking-wider inline-block shadow-sm border" 
                            :class="rolBadgeStyle.badge"
                        >
                            {{ item?.rol }}
                        </span>
                    </div>

                </div>

                <!-- Pie Institucional con Franja Bicolor y Ciclo Escolar -->
                <div class="bg-[#102944] text-white px-3 py-1.5 shrink-0 w-full border-t border-[#009FE3] flex items-center justify-between relative z-10">
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-[#009FE3]"></div>
                        <span class="text-[7.5px] font-bold text-slate-200 uppercase tracking-wider leading-none">
                            {{ ciclo }}
                        </span>
                    </div>
                    <span class="text-[7px] font-extrabold text-[#38BDF8] uppercase tracking-widest leading-none">
                        FAMILYLOCK
                    </span>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- CARA POSTERIOR (REVERSO CON QR E INSTRUCCIONES) -->
            <!-- ============================================== -->
            <div class="card-face card-face-back bg-white text-slate-900 border border-slate-300 shadow-2xl flex flex-col justify-between overflow-hidden relative">
                
                <!-- Borde Superior de Acento de Rol -->
                <div class="h-1.5 w-full shrink-0" :style="{ backgroundColor: rolBadgeStyle.accent }"></div>

                <!-- Cabecera Reverso Azul Marino con Detalle Cian -->
                <div class="bg-[#102944] text-white px-3.5 pt-2.5 pb-2 shrink-0 border-b-2 border-[#009FE3] flex items-center justify-between relative overflow-hidden">
                    <div class="flex items-center gap-2 relative z-10">
                        <img 
                            src="/isotipo-cam.svg" 
                            alt="CAM #48" 
                            class="w-7 h-7 shrink-0 object-contain drop-shadow"
                        />
                        <div class="text-left">
                            <h5 class="text-[8.5px] font-black uppercase text-white leading-tight font-display">
                                CAM #48 Tonalpouhqui
                            </h5>
                            <p class="text-[6.5px] font-bold uppercase tracking-wider text-[#38BDF8] leading-none mt-0.5">
                                Identificación y Control de Acceso
                            </p>
                        </div>
                    </div>
                    <span class="text-[7px] font-black text-white uppercase tracking-widest bg-white/10 border border-white/20 px-2 py-0.5 rounded-md relative z-10">
                        REVERSO
                    </span>
                </div>

                <!-- Zona Principal: Código QR Amplio con Zona de Silencio y Alto Contraste -->
                <div class="px-4 py-2.5 flex-1 flex flex-col items-center justify-center text-center w-full bg-white relative overflow-hidden">
                    
                    <!-- Curvas decorativas de fondo sutiles -->
                    <svg class="absolute inset-0 w-full h-full pointer-events-none opacity-[0.035]" viewBox="0 0 256 300" fill="none">
                        <circle cx="128" cy="150" r="110" stroke="#102944" stroke-width="16" />
                        <path d="M20 250 Q128 200 236 250" stroke="#009FE3" stroke-width="20" />
                    </svg>

                    <!-- Caja del Código QR (Garantiza 4 módulos de silencio y alto contraste) -->
                    <div class="p-2 bg-white border-2 border-[#102944] rounded-2xl shadow-sm mb-1.5 z-10 relative">
                        <img 
                            v-if="qrSrc" 
                            :src="qrSrc" 
                            class="w-[136px] h-[136px] object-contain block" 
                            alt="Código QR Institucional de Acceso"
                        />
                        <div v-else class="w-[136px] h-[136px] bg-slate-50 animate-pulse rounded-xl flex items-center justify-center text-slate-300">
                            <i class="fas fa-qrcode text-4xl"></i>
                        </div>
                    </div>

                    <!-- Título de Acceso -->
                    <h4 class="text-[10px] font-black uppercase tracking-wider text-[#102944] m-0 leading-tight font-display z-10">
                        Acceso Digital Escolar
                    </h4>

                    <!-- Instrucción Breve de Escaneo -->
                    <p class="text-[7.5px] font-medium text-slate-600 mt-1 max-w-[215px] leading-tight z-10">
                        Presenta este código en el lector óptico de FamilyLock para validar tu ingreso al plantel escolar.
                    </p>

                    <!-- Identificador de Respaldo -->
                    <div class="mt-1.5 text-[7px] font-mono text-slate-500 font-bold bg-slate-100 px-2.5 py-0.5 rounded-full z-10 border border-slate-200">
                        TITULAR: {{ item?.id }} • {{ item?.rol }}
                    </div>
                </div>

                <!-- Pie Institucional Reverso -->
                <div class="bg-[#102944] text-white px-3 py-1.5 shrink-0 w-full border-t border-[#009FE3] flex items-center justify-between relative z-10">
                    <span class="text-[7px] font-bold text-slate-300 uppercase tracking-wider">
                        Documento Oficial Intransferible
                    </span>
                    <span class="text-[7px] font-bold text-[#38BDF8] uppercase tracking-wider">
                        {{ ciclo }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODO 2: FRENTE PLANO ESTÁTICO (Para Impresión Física o Export)  -->
    <!-- ============================================================== -->
    <div 
        v-else-if="mode === 'frente'"
        class="credencial-card-shell bg-white text-slate-900 rounded-3xl flex flex-col justify-between overflow-hidden border border-slate-300 relative select-none"
        :class="{
            'print:float-left print:m-[3mm] print:shadow-none print:border-slate-300 print:break-inside-avoid': isMassPrint,
            'print:m-0 print:shadow-none print:border-none': !isMassPrint
        }"
    >
        <!-- Borde Superior de Acento de Rol -->
        <div class="h-1.5 w-full shrink-0" :style="{ backgroundColor: rolBadgeStyle.accent }"></div>

        <!-- Cabecera Institucional Azul Marino + Detalle Cian -->
        <div class="bg-[#102944] text-white px-3.5 pt-2.5 pb-2.5 shrink-0 relative overflow-hidden border-b-2 border-[#009FE3]">
            <div class="flex items-center gap-2.5 relative z-10">
                <img 
                    src="/isotipo-cam.svg" 
                    alt="Emblema CAM #48" 
                    class="w-11 h-11 shrink-0 object-contain drop-shadow"
                />
                <div class="flex-1 min-w-0 text-left">
                    <h4 class="text-[9.5px] font-black uppercase tracking-tight text-white leading-tight truncate font-display">
                        Centro de Atención Múltiple #48
                    </h4>
                    <p class="text-[8.5px] font-black uppercase tracking-wider text-[#38BDF8] leading-none mt-0.5">
                        Tonalpouhqui
                    </p>
                    <p class="text-[7px] font-semibold uppercase tracking-widest text-slate-300 leading-none mt-0.5">
                        Nanchital, Veracruz
                    </p>
                </div>
            </div>
        </div>

        <!-- Cuerpo Central -->
        <div class="px-4 py-2 flex-1 flex flex-col items-center justify-between text-center w-full bg-white relative overflow-hidden">
            <!-- Fotografía -->
            <div class="w-full flex justify-center mt-1 z-10">
                <div class="w-[92px] h-[104px] rounded-2xl overflow-hidden border-2 border-[#102944] p-0.5 bg-gradient-to-b from-[#009FE3]/30 to-slate-200 shadow-md shrink-0 flex items-center justify-center">
                    <div class="w-full h-full rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center">
                        <img 
                            v-if="item?.foto_perfil" 
                            :src="`/storage/${item.foto_perfil}`" 
                            class="w-full h-full object-cover" 
                            :alt="item.nombre"
                        />
                        <div v-else class="text-slate-300 flex flex-col items-center justify-center w-full h-full bg-slate-50">
                            <i class="fas fa-user text-3xl text-slate-300"></i>
                            <span class="text-[6.5px] font-bold text-slate-400 uppercase tracking-widest mt-1">Sin Foto</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nombre Completo -->
            <div class="w-full px-1 my-1 z-10">
                <h3 class="text-[12.5px] font-black uppercase leading-snug text-[#102944] tracking-tight line-clamp-2 m-0 font-display">
                    {{ item?.nombre }} {{ item?.apellido }}
                </h3>
            </div>

            <!-- Matrícula -->
            <div class="z-10 inline-flex items-center gap-2 px-3 py-0.5 rounded-lg bg-sky-50 border border-sky-200/90 shadow-xs">
                <span class="text-[7.5px] font-extrabold uppercase tracking-wider text-sky-700">MATRÍCULA / ID:</span>
                <span class="text-[10px] font-mono font-black text-[#102944]">{{ item?.id }}</span>
            </div>

            <!-- Rol -->
            <div class="z-10 mt-1 mb-0.5">
                <span 
                    class="px-4 py-1 rounded-full text-[8.5px] font-black uppercase tracking-wider inline-block shadow-sm border" 
                    :class="rolBadgeStyle.badge"
                >
                    {{ item?.rol }}
                </span>
            </div>
        </div>

        <!-- Pie Institucional -->
        <div class="bg-[#102944] text-white px-3 py-1.5 shrink-0 w-full border-t border-[#009FE3] flex items-center justify-between relative z-10">
            <div class="flex items-center gap-1.5">
                <div class="w-1.5 h-1.5 rounded-full bg-[#009FE3]"></div>
                <span class="text-[7.5px] font-bold text-slate-200 uppercase tracking-wider leading-none">
                    {{ ciclo }}
                </span>
            </div>
            <span class="text-[7px] font-extrabold text-[#38BDF8] uppercase tracking-widest leading-none">
                FAMILYLOCK
            </span>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODO 3: REVERSO PLANO ESTÁTICO (Para Impresión Física o Export) -->
    <!-- ============================================================== -->
    <div 
        v-else-if="mode === 'reverso'"
        class="credencial-card-shell bg-white text-slate-900 rounded-3xl flex flex-col justify-between overflow-hidden border border-slate-300 relative select-none"
        :class="{
            'print:float-left print:m-[3mm] print:shadow-none print:border-slate-300 print:break-inside-avoid': isMassPrint,
            'print:m-0 print:shadow-none print:border-none': !isMassPrint
        }"
    >
        <!-- Borde Superior de Acento de Rol -->
        <div class="h-1.5 w-full shrink-0" :style="{ backgroundColor: rolBadgeStyle.accent }"></div>

        <!-- Cabecera Reverso Azul Marino con Detalle Cian -->
        <div class="bg-[#102944] text-white px-3.5 pt-2.5 pb-2 shrink-0 border-b-2 border-[#009FE3] flex items-center justify-between relative overflow-hidden">
            <div class="flex items-center gap-2 relative z-10">
                <img 
                    src="/isotipo-cam.svg" 
                    alt="CAM #48" 
                    class="w-7 h-7 shrink-0 object-contain drop-shadow"
                />
                <div class="text-left">
                    <h5 class="text-[8.5px] font-black uppercase text-white leading-tight font-display">
                        CAM #48 Tonalpouhqui
                    </h5>
                    <p class="text-[6.5px] font-bold uppercase tracking-wider text-[#38BDF8] leading-none mt-0.5">
                        Identificación y Control de Acceso
                    </p>
                </div>
            </div>
            <span class="text-[7px] font-black text-white uppercase tracking-widest bg-white/10 border border-white/20 px-2 py-0.5 rounded-md relative z-10">
                REVERSO
            </span>
        </div>

        <!-- Zona Principal: Código QR Amplio -->
        <div class="px-4 py-2.5 flex-1 flex flex-col items-center justify-center text-center w-full bg-white relative overflow-hidden">
            <div class="p-2 bg-white border-2 border-[#102944] rounded-2xl shadow-sm mb-1.5 z-10 relative">
                <img 
                    v-if="qrSrc" 
                    :src="qrSrc" 
                    class="w-[136px] h-[136px] object-contain block" 
                    alt="Código QR Institucional de Acceso"
                />
                <div v-else class="w-[136px] h-[136px] bg-slate-50 animate-pulse rounded-xl flex items-center justify-center text-slate-300">
                    <i class="fas fa-qrcode text-4xl"></i>
                </div>
            </div>

            <!-- Título de Acceso -->
            <h4 class="text-[10px] font-black uppercase tracking-wider text-[#102944] m-0 leading-tight font-display z-10">
                Acceso Digital Escolar
            </h4>

            <!-- Instrucción Breve de Escaneo -->
            <p class="text-[7.5px] font-medium text-slate-600 mt-1 max-w-[215px] leading-tight z-10">
                Presenta este código en el lector óptico de FamilyLock para validar tu ingreso al plantel escolar.
            </p>

            <!-- Identificador de Respaldo -->
            <div class="mt-1.5 text-[7px] font-mono text-slate-500 font-bold bg-slate-100 px-2.5 py-0.5 rounded-full z-10 border border-slate-200">
                TITULAR: {{ item?.id }} • {{ item?.rol }}
            </div>
        </div>

        <!-- Pie Institucional Reverso -->
        <div class="bg-[#102944] text-white px-3 py-1.5 shrink-0 w-full border-t border-[#009FE3] flex items-center justify-between relative z-10">
            <span class="text-[7px] font-bold text-slate-300 uppercase tracking-wider">
                Documento Oficial Intransferible
            </span>
            <span class="text-[7px] font-bold text-[#38BDF8] uppercase tracking-wider">
                {{ ciclo }}
            </span>
        </div>
    </div>
</template>

<style scoped>
/* Contenedor de Perspectiva 3D con Dimensiones CR80 */
.perspective-container {
    perspective: 1200px;
    width: 256px;
    height: 406px;
}

/* Flipper 3D Suave */
.card-flipper {
    position: relative;
    width: 256px;
    height: 406px;
    transform-style: preserve-3d;
    transition: transform 0.65s cubic-bezier(0.4, 0.2, 0.2, 1);
}

.card-flipper.is-flipped {
    transform: rotateY(180deg);
}

/* Caras 3D de la Credencial */
.card-face {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    border-radius: 1.5rem; /* rounded-3xl */
    overflow: hidden;
}

.card-face-front {
    z-index: 2;
    transform: rotateY(0deg);
}

.card-face-back {
    z-index: 1;
    transform: rotateY(180deg);
}

/* Shell Estático Plano para impresión */
.credencial-card-shell {
    width: 256px;
    height: 406px;
}

/* Accesibilidad: Movimiento Reducido */
@media (prefers-reduced-motion: reduce) {
    .card-flipper {
        transition: none !important;
    }
    .card-flipper.is-flipped .card-face-front {
        opacity: 0 !important;
        pointer-events: none !important;
    }
    .card-flipper:not(.is-flipped) .card-face-back {
        opacity: 0 !important;
        pointer-events: none !important;
    }
    .card-flipper.is-flipped {
        transform: none !important;
    }
    .card-face-back {
        transform: none !important;
    }
}

/* Dimensiones Exactas CR80 para Impresión (53.98mm x 85.60mm) */
@media print {
    .credencial-card-shell {
        width: 53.98mm !important;
        height: 85.60mm !important;
        max-width: 53.98mm !important;
        max-height: 85.60mm !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        border: 1px solid #94a3b8 !important;
        border-radius: 3.2mm !important;
        background-color: #ffffff !important;
        color: #0f172a !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
