<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import Swal from 'sweetalert2';
import axios from 'axios';

// --- Estados reactivos ---
const usandoCamara = ref(false);
const procesando = ref(false);
const bloqueado = ref(false); // Para detener auto-enfoque USB durante modales
const codigoEscaneado = ref('');
const horaActual = ref('');
const horaDigitos = ref('');
const horaMeridiano = ref('');
const fechaActual = ref('');
const estado = ref('esperando'); // esperando, exito, alerta, error

// --- Estilos Visuales del Kiosco ---
const bordePrincipal = ref('border-white/10');
const colorIconoCentro = ref('text-sky-400');
const tituloKiosco = ref('Pase su Credencial');
const fondoFeedback = ref('bg-slate-900/40');

const mensajeEstado = ref({
    texto: '',
    persona: '',
    icono: '',
    claseIcono: '',
    claseTextoTitulo: '',
    claseTextoDesc: '',
});

// Elemento input phantom
const inputLector = ref(null);
let html5QrCode = null;
let relojInterval = null;

// --- Reloj en tiempo real ---
const iniciarReloj = () => {
    const actualizar = () => {
        const ahora = new Date();
        const rawHora = ahora.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        const partes = rawHora.split(/\s+/);
        if (partes.length >= 2) {
            horaDigitos.value = partes[0];
            horaMeridiano.value = partes.slice(1).join(' ').replace(/\./g, '').toUpperCase();
        } else {
            horaDigitos.value = rawHora;
            horaMeridiano.value = '';
        }
        horaActual.value = rawHora;
        const rawFecha = ahora.toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        fechaActual.value = rawFecha.charAt(0).toUpperCase() + rawFecha.slice(1);
    };
    actualizar();
    relojInterval = setInterval(actualizar, 1000);
};

// --- Enfoque del Lector de Códigos USB ---
const mantenerFocoVirtual = () => {
    if (!usandoCamara.value && !procesando.value && !bloqueado.value) {
        nextTick(() => {
            if (inputLector.value) {
                inputLector.value.focus();
            }
        });
    }
};

const procesarCodigoDesdeInput = () => {
    const codigo = codigoEscaneado.value.trim();
    codigoEscaneado.value = '';
    if (!codigo || procesando.value) return;
    enviarDatosServidor(codigo);
};

// --- Manejo del Escáner con Cámara Web ---
const iniciarCamara = () => {
    nextTick(() => {
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("lector-qr");
        }
        
        const config = { 
            fps: 15, 
            qrbox: { width: 280, height: 280 }, 
            aspectRatio: 1.777778 
        };

        html5QrCode.start(
            { facingMode: "environment" }, 
            config, 
            onScanSuccessCamara, 
            () => {}
        ).catch(() => {
            html5QrCode.start(
                { facingMode: "user" }, 
                config, 
                onScanSuccessCamara, 
                () => {}
            ).catch(() => {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin Cámara Disponible',
                    text: 'Conecte una cámara web o utilice el modo Lector USB.',
                    customClass: { popup: 'rounded-2xl' }
                });
                toggleModoCamara();
            });
        });
    });
};

const detenerCamara = () => {
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop().catch(e => console.warn("Error al detener cámara:", e));
    }
};

const toggleModoCamara = () => {
    usandoCamara.value = !usandoCamara.value;
    limpiarFeedback();
    
    if (usandoCamara.value) {
        iniciarCamara();
    } else {
        detenerCamara();
        mantenerFocoVirtual();
    }
};

const onScanSuccessCamara = (decodedText) => {
    if (procesando.value) return;
    enviarDatosServidor(decodedText);
};

// --- Procesar Asistencia (API Post) ---
const enviarDatosServidor = async (codigo) => {
    procesando.value = true;
    estado.value = 'procesando';
    
    try {
        const response = await axios.post(route('asistencia.procesarQr'), {
            qr_data: codigo
        });

        const result = response.data;

        if (result.status === 'success') {
            reproducirSonido('ok');
            aplicarEstilosVisuales('exito', result.persona, result.msg);
        } else if (result.status === 'warning') {
            reproducirSonido('error');
            aplicarEstilosVisuales('alerta', result.persona || 'Aviso Escolar', result.msg);
        } else {
            reproducirSonido('error');
            aplicarEstilosVisuales('error', 'Código Inválido', result.msg || 'No se reconoce esta credencial');
        }

    } catch (error) {
        reproducirSonido('error');
        aplicarEstilosVisuales('error', 'Fallo del Sistema', 'Ocurrió un error al procesar el código.');
    } finally {
        setTimeout(() => {
            limpiarFeedback();
            procesando.value = false;
            if (!usandoCamara.value && !bloqueado.value) {
                mantenerFocoVirtual();
            }
        }, 2500);
    }
};

// --- Estilos Dinámicos ---
const aplicarEstilosVisuales = (tipo, titulo, descripcion) => {
    estado.value = tipo;
    mensajeEstado.value.persona = titulo;
    mensajeEstado.value.texto = descripcion;

    if (tipo === 'exito') {
        bordePrincipal.value = 'border-emerald-500 shadow-emerald-500/20';
        colorIconoCentro.value = 'text-emerald-400';
        tituloKiosco.value = '¡ACCESO CONCEDIDO!';
        fondoFeedback.value = 'bg-emerald-500/15 border border-emerald-500/30';
        mensajeEstado.value.icono = '<i class="fas fa-check-circle"></i>';
        mensajeEstado.value.claseIcono = 'text-emerald-400';
        mensajeEstado.value.claseTextoTitulo = 'text-emerald-200';
        mensajeEstado.value.claseTextoDesc = 'text-emerald-300';
    } else if (tipo === 'alerta') {
        bordePrincipal.value = 'border-amber-400 shadow-amber-400/20';
        colorIconoCentro.value = 'text-amber-400';
        tituloKiosco.value = 'ATENCIÓN REQUERIDA';
        fondoFeedback.value = 'bg-amber-500/15 border border-amber-500/30';
        mensajeEstado.value.icono = '<i class="fas fa-exclamation-triangle"></i>';
        mensajeEstado.value.claseIcono = 'text-amber-400';
        mensajeEstado.value.claseTextoTitulo = 'text-amber-200';
        mensajeEstado.value.claseTextoDesc = 'text-amber-300';
    } else if (tipo === 'error') {
        bordePrincipal.value = 'border-rose-500 shadow-rose-500/20';
        colorIconoCentro.value = 'text-rose-400';
        tituloKiosco.value = 'ACCESO DENEGADO';
        fondoFeedback.value = 'bg-rose-500/15 border border-rose-500/30';
        mensajeEstado.value.icono = '<i class="fas fa-times-circle"></i>';
        mensajeEstado.value.claseIcono = 'text-rose-400';
        mensajeEstado.value.claseTextoTitulo = 'text-rose-200';
        mensajeEstado.value.claseTextoDesc = 'text-rose-300';
    }
};

const limpiarFeedback = () => {
    estado.value = 'esperando';
    bordePrincipal.value = 'border-white/10';
    colorIconoCentro.value = 'text-sky-400';
    tituloKiosco.value = 'Pase su Credencial';
    fondoFeedback.value = 'bg-slate-900/40';
    mensajeEstado.value = { texto: '', persona: '', icono: '', claseIcono: '', claseTextoTitulo: '', claseTextoDesc: '' };
};

const reproducirSonido = (tipo) => {
    const audio = document.getElementById(tipo === 'ok' ? 'beep-ok' : 'beep-error');
    if (audio) {
        audio.currentTime = 0;
        audio.play().catch(e => console.warn("Auto-play bloqueado por navegador", e));
    }
};

// --- Pantalla Completa ---
const togglePantallaCompleta = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.log(`Error al intentar pantalla completa: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
};

// --- Cerrar Modo Kiosco de Forma Segura ---
const intentarSalir = async () => {
    bloqueado.value = true;

    const { value: pass } = await Swal.fire({
        title: '<i class="fas fa-lock text-sky-400 text-3xl mb-2"></i><br><span class="text-slate-100 text-xl font-bold">Kiosco Protegido</span>',
        text: 'Ingresa la contraseña de Administrador para regresar al Panel.',
        input: 'password',
        inputPlaceholder: 'Contraseña...',
        background: '#0f172a',
        color: '#f8fafc',
        showCancelButton: true,
        confirmButtonColor: '#009FE3',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Desbloquear',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'rounded-3xl border border-white/10 shadow-2xl',
            confirmButton: 'btn-premium',
            cancelButton: 'btn-ghost'
        },
        didClose: () => {
            bloqueado.value = false;
            mantenerFocoVirtual();
        }
    });

    if (pass) {
        try {
            const response = await axios.post(route('asistencia.desbloquear'), {
                password: pass
            });

            if (response.data.status === 'success') {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                }
                detenerCamara();
                router.visit(route('dashboard'));
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Contraseña Incorrecta',
                    text: 'Verifique sus credenciales.',
                    background: '#0f172a',
                    color: '#f8fafc',
                    customClass: { popup: 'rounded-2xl border border-white/10' }
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Servidor',
                text: 'No se pudo validar la contraseña.',
                background: '#0f172a',
                color: '#f8fafc',
                customClass: { popup: 'rounded-2xl border border-white/10' }
            });
        }
    }
};

onMounted(() => {
    iniciarReloj();
    mantenerFocoVirtual();
});

onUnmounted(() => {
    clearInterval(relojInterval);
    detenerCamara();
});
</script>

<template>
    <Head title="Terminal de Pase de Lista — FamilyLock" />

    <div class="bg-[#070b14] font-sans flex flex-col min-h-screen text-white select-none relative overflow-hidden" @click="mantenerFocoVirtual">
        
        <!-- Subtle Ambient Lighting -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
            <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[70vw] h-[50vh] bg-sky-500/[0.06] rounded-full blur-[140px]"></div>
            <div class="absolute -bottom-40 left-1/2 -translate-x-1/2 w-[60vw] h-[40vh] bg-blue-600/[0.05] rounded-full blur-[140px]"></div>
        </div>

        <!-- Terminal Top Bar -->
        <header class="bg-slate-950/80 backdrop-blur-2xl border-b border-white/[0.08] px-5 sm:px-8 md:px-10 py-3.5 sm:py-4 flex justify-between items-center sticky top-0 z-40 shrink-0 min-h-[76px] md:min-h-[86px]">
            <div class="flex items-center gap-4">
                <img 
                    src="/logo-horizontal-oscuro.svg" 
                    alt="CAM #48 Tonalpouhqui" 
                    class="h-10 sm:h-11 md:h-12 w-auto max-w-[220px] sm:max-w-[250px] md:max-w-[280px] object-contain drop-shadow"
                />
                <div class="hidden lg:block h-6 w-px bg-white/10"></div>
                <div class="hidden lg:flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                    <span class="text-xs font-bold text-slate-300 uppercase tracking-widest">
                        Terminal de Accesos QR
                    </span>
                </div>
            </div>
            
            <!-- Controls (Touch Friendly min 44px hit-areas) -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Toggle Mode Button -->
                <button 
                    @click="toggleModoCamara" 
                    class="bg-white/10 hover:bg-white/15 text-slate-200 px-3.5 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 border border-white/10 active:scale-95 shadow-sm min-h-[44px]"
                    :aria-label="usandoCamara ? 'Cambiar a lector USB' : 'Cambiar a cámara web'"
                >
                    <i class="fas text-sky-400 text-sm" :class="usandoCamara ? 'fa-barcode' : 'fa-camera'"></i> 
                    <span class="hidden sm:inline">{{ usandoCamara ? 'Modo USB' : 'Cámara Web' }}</span>
                </button>

                <!-- Fullscreen Button -->
                <button 
                    @click="togglePantallaCompleta" 
                    class="bg-white/10 hover:bg-white/15 text-slate-300 w-11 h-11 flex items-center justify-center rounded-xl transition-all border border-white/10 active:scale-95 min-h-[44px] min-w-[44px]" 
                    title="Alternar Pantalla Completa"
                    aria-label="Alternar Pantalla Completa"
                >
                    <i class="fas fa-expand text-xs"></i>
                </button>
                
                <!-- Exit Lock Button -->
                <button 
                    @click="intentarSalir" 
                    class="bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-3.5 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 border border-rose-500/25 active:scale-95 shadow-sm min-h-[44px]"
                    aria-label="Salir del kiosco"
                >
                    <i class="fas fa-lock text-xs"></i> 
                    <span class="hidden sm:inline">Salir</span>
                </button>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="flex-1 flex flex-col items-center justify-center p-4 sm:p-6 md:p-8 relative z-10 w-full max-w-5xl mx-auto">
            
            <!-- Balanced Compact Clock Display -->
            <div class="text-center mb-5 md:mb-6" v-show="!usandoCamara">
                <div class="inline-flex items-baseline justify-center tracking-tight">
                    <span class="text-5xl sm:text-6xl md:text-[68px] font-black text-white font-mono tracking-tight tabular-nums drop-shadow-md leading-none">
                        {{ horaDigitos || horaActual }}
                    </span>
                    <span v-if="horaMeridiano" class="text-base sm:text-lg md:text-xl font-bold font-mono text-sky-400 ml-2 tracking-wider uppercase select-none">
                        {{ horaMeridiano }}
                    </span>
                </div>
                <p class="text-xs sm:text-sm md:text-[15px] font-semibold text-slate-400 tracking-wide mt-1.5 flex items-center justify-center gap-2 capitalize">
                    <i class="far fa-calendar-alt text-sky-400/80 text-xs"></i>
                    <span>{{ fechaActual }}</span>
                </p>
            </div>

            <!-- Terminal Center Card (Viewfinder Reticle Design) -->
            <div 
                class="w-full max-w-2xl lg:max-w-[720px] bg-slate-950/75 backdrop-blur-2xl rounded-[2.5rem] border-2 transition-all duration-300 relative flex flex-col shadow-2xl" 
                :class="bordePrincipal"
            >
                <!-- Viewfinder Corner Markers (Apple Camera Reticle) -->
                <div class="absolute -top-1 -left-1 w-7 h-7 border-t-2 border-l-2 rounded-tl-2xl border-sky-400 pointer-events-none"></div>
                <div class="absolute -top-1 -right-1 w-7 h-7 border-t-2 border-r-2 rounded-tr-2xl border-sky-400 pointer-events-none"></div>
                <div class="absolute -bottom-1 -left-1 w-7 h-7 border-b-2 border-l-2 rounded-bl-2xl border-sky-400 pointer-events-none"></div>
                <div class="absolute -bottom-1 -right-1 w-7 h-7 border-b-2 border-r-2 rounded-br-2xl border-sky-400 pointer-events-none"></div>

                <!-- USB Scanner View -->
                <div v-show="!usandoCamara" class="p-7 sm:p-10 text-center flex flex-col items-center justify-center min-h-[350px]">
                    
                    <div class="mb-6">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-sky-500/10 border border-sky-400/25 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-sky-500/5 transition-transform duration-300 backdrop-blur-md" :class="{ 'scale-105': estado === 'esperando' }">
                            <i class="fas fa-qrcode text-4xl sm:text-5xl" :class="colorIconoCentro"></i>
                        </div>
                        <h3 class="text-2xl sm:text-3xl font-black text-white font-display tracking-tight">{{ tituloKiosco }}</h3>
                        <p class="text-slate-300 text-sm sm:text-base font-medium mt-1.5 max-w-md mx-auto">
                            Acerque el código QR de su credencial al lector óptico.
                        </p>
                    </div>

                    <!-- Hidden Focused Input for Barcode/QR Hardware Scanners -->
                    <input 
                        type="text" 
                        ref="inputLector"
                        v-model="codigoEscaneado" 
                        @keyup.enter="procesarCodigoDesdeInput" 
                        @blur="mantenerFocoVirtual"
                        class="opacity-0 w-1 h-1 absolute -z-10 outline-none"
                        autocomplete="off"
                        :disabled="procesando"
                    />

                    <!-- Visual Response Feedback Strip -->
                    <div class="w-full min-h-[76px] flex items-center justify-center rounded-2xl transition-colors duration-300 px-5 py-3.5 border border-white/5" :class="fondoFeedback">
                        <div v-if="mensajeEstado.texto" class="flex items-center gap-4 text-left w-full">
                            <div class="text-3xl sm:text-4xl shrink-0" :class="mensajeEstado.claseIcono" v-html="mensajeEstado.icono"></div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-base sm:text-lg uppercase tracking-tight leading-tight truncate" :class="mensajeEstado.claseTextoTitulo">
                                    {{ mensajeEstado.persona }}
                                </p>
                                <p class="text-xs sm:text-sm font-semibold mt-0.5 leading-tight" :class="mensajeEstado.claseTextoDesc">
                                    {{ mensajeEstado.texto }}
                                </p>
                            </div>
                        </div>
                        <div v-else class="text-slate-300 font-bold uppercase tracking-wider text-xs sm:text-sm flex items-center gap-3">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-sky-500"></span>
                            </span>
                            <i class="fas fa-barcode text-sky-400 text-base"></i> 
                            <span>Listo para escanear...</span>
                        </div>
                    </div>
                </div>

                <!-- Web Camera View -->
                <div v-show="usandoCamara" class="relative flex-1 flex flex-col min-h-[460px] overflow-hidden rounded-[2.5rem]">
                    <div class="bg-slate-900/90 text-slate-300 px-4 py-2.5 text-center text-xs font-bold flex items-center justify-center gap-2 border-b border-white/10 z-10">
                        <i class="fas fa-video text-sky-400"></i>
                        <span>Enfoque el código QR en el centro del recuadro</span>
                    </div>
                    
                    <div class="flex-1 bg-black relative flex items-center justify-center overflow-hidden">
                        <div id="lector-qr" class="w-full h-full min-h-[380px]"></div>
                    </div>
                    
                    <!-- Floating Camera Feedback Drawer -->
                    <div 
                        v-show="mensajeEstado.texto"
                        class="absolute bottom-0 left-0 w-full p-4 bg-slate-950/90 backdrop-blur-xl border-t border-white/10 transition-transform duration-300 z-20"
                    >
                        <div class="flex items-center gap-3.5">
                            <div class="text-3xl shrink-0" :class="mensajeEstado.claseIcono" v-html="mensajeEstado.icono"></div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-base uppercase leading-tight truncate" :class="mensajeEstado.claseTextoTitulo">
                                    {{ mensajeEstado.persona }}
                                </p>
                                <p class="text-xs font-semibold mt-0.5" :class="mensajeEstado.claseTextoDesc">
                                    {{ mensajeEstado.texto }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom Status Indicator -->
            <div class="mt-6 flex items-center gap-2 text-xs text-slate-400 font-medium select-none">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span>Terminal en Línea &bull; Modo Kiosco Activo</span>
            </div>
        </main>

        <!-- Preloaded Sounds -->
        <audio id="beep-ok" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
        <audio id="beep-error" src="https://assets.mixkit.co/active_storage/sfx/2572/2572-preview.mp3" preload="auto"></audio>
    </div>
</template>

<style>
/* Html5-qrcode video cover overrides */
#lector-qr video {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}
#lector-qr img {
    display: none !important;
}
</style>
