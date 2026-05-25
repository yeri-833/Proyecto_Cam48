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
const fechaActual = ref('');
const estado = ref('esperando'); // esperando, exito, alerta, error

// --- Estilos Visuales del Kiosco ---
const bordePrincipal = ref('border-white/10');
const colorIconoCentro = ref('text-slate-500');
const tituloKiosco = ref('Pase su Credencial');
const fondoFeedback = ref('bg-slate-800/40');

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
        horaActual.value = ahora.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        fechaActual.value = ahora.toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
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
            // Intento secundario con cámara frontal si falla la principal (ej: laptops)
            html5QrCode.start(
                { facingMode: "user" }, 
                config, 
                onScanSuccessCamara, 
                () => {}
            ).catch(() => {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin Cámara',
                    text: 'Por favor, conecte una cámara web o cambie al Modo USB.',
                    customClass: { popup: 'swal2-popup' }
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
    estado.value = 'esperando';
    
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
            aplicarEstilosVisuales('alerta', result.persona || 'Aviso', result.msg);
        } else {
            reproducirSonido('error');
            aplicarEstilosVisuales('error', 'Código Inválido', result.msg);
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
        bordePrincipal.value = 'border-emerald-500 shadow-emerald-500/20 pulse-success';
        colorIconoCentro.value = 'text-emerald-400';
        tituloKiosco.value = '¡ACCESO CONCEDIDO!';
        fondoFeedback.value = 'bg-emerald-500/10 border border-emerald-500/20';
        mensajeEstado.value.icono = '<i class="fas fa-check-circle"></i>';
        mensajeEstado.value.claseIcono = 'text-emerald-400';
        mensajeEstado.value.claseTextoTitulo = 'text-emerald-300';
        mensajeEstado.value.claseTextoDesc = 'text-emerald-400';
    } else if (tipo === 'alerta') {
        bordePrincipal.value = 'border-amber-400 shadow-amber-400/20';
        colorIconoCentro.value = 'text-amber-400';
        tituloKiosco.value = 'ATENCIÓN REQUERIDA';
        fondoFeedback.value = 'bg-amber-500/10 border border-amber-500/20';
        mensajeEstado.value.icono = '<i class="fas fa-exclamation-triangle"></i>';
        mensajeEstado.value.claseIcono = 'text-amber-400';
        mensajeEstado.value.claseTextoTitulo = 'text-amber-300';
        mensajeEstado.value.claseTextoDesc = 'text-amber-400';
    } else if (tipo === 'error') {
        bordePrincipal.value = 'border-red-500 shadow-red-500/20 pulse-error';
        colorIconoCentro.value = 'text-red-400';
        tituloKiosco.value = 'ACCESO DENEGADO';
        fondoFeedback.value = 'bg-red-500/10 border border-red-500/20';
        mensajeEstado.value.icono = '<i class="fas fa-times-circle"></i>';
        mensajeEstado.value.claseIcono = 'text-red-400';
        mensajeEstado.value.claseTextoTitulo = 'text-red-300';
        mensajeEstado.value.claseTextoDesc = 'text-red-400';
    }
};

const limpiarFeedback = () => {
    estado.value = 'esperando';
    bordePrincipal.value = 'border-white/10';
    colorIconoCentro.value = 'text-slate-500';
    tituloKiosco.value = 'Pase su Credencial';
    fondoFeedback.value = 'bg-slate-800/40';
    mensajeEstado.value = { texto: '', persona: '', icono: '', claseIcono: '', claseTextoTitulo: '', claseTextoDesc: '' };
};

const reproducirSonido = (tipo) => {
    const audio = document.getElementById(tipo === 'ok' ? 'beep-ok' : 'beep-error');
    if (audio) {
        audio.currentTime = 0;
        audio.play().catch(e => console.warn("Auto-play de audio bloqueado por navegador", e));
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

// --- Cerrar Modo Kiosco de Forma Segura (Candado) ---
const intentarSalir = async () => {
    bloqueado.value = true; // Detener auto-enfoque

    const { value: pass } = await Swal.fire({
        title: '<i class="fas fa-lock text-slate-400 text-4xl mb-4"></i><br><span class="text-slate-200">Kiosco Bloqueado</span>',
        text: 'Ingrese la contraseña de Administrador para regresar al Panel.',
        input: 'password',
        inputPlaceholder: 'Contraseña...',
        background: '#1e293b',
        color: '#f8fafc',
        showCancelButton: true,
        confirmButtonColor: '#009FE3',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Desbloquear',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-popup',
            title: 'font-display font-black',
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
                    title: 'Acceso Denegado',
                    text: 'La contraseña es incorrecta.',
                    background: '#1e293b',
                    color: '#f8fafc',
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Hubo un error de verificación en el servidor.',
                background: '#1e293b',
                color: '#f8fafc',
            });
        }
    }
};

// --- Ciclo de vida ---
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
    <Head title="Terminal de Accesos QR" />

    <div class="bg-slate-900 font-sans flex flex-col min-h-screen text-white select-none" @click="mantenerFocoVirtual">
        
        <!-- Header -->
        <nav class="bg-slate-950/80 backdrop-blur-md border-b border-white/5 p-4 shadow-lg flex justify-between items-center sticky top-0 z-40 px-6 md:px-8 shrink-0">
            <div class="flex items-center gap-4">
                <img src="/Logo del CAM.png" alt="Logo CAM" class="h-10 object-contain drop-shadow-md">
                <div>
                    <h1 class="font-black text-xl tracking-tight text-white font-display">Terminal de <span class="text-primary">Accesos</span></h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none">Centro de Atención Múltiple #48</p>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button aria-label="Alternar pantalla completa" @click="togglePantallaCompleta" class="bg-slate-800 hover:bg-slate-700 text-slate-300 w-11 h-11 flex items-center justify-center rounded-xl transition-all border border-white/5" title="Pantalla Completa">
                    <i class="fas fa-expand"></i>
                </button>

                <button :aria-label="usandoCamara ? 'Cambiar a lector USB' : 'Cambiar a cámara web'" @click="toggleModoCamara" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 border border-white/5">
                    <i class="fas fa-camera text-secondary"></i> 
                    <span>{{ usandoCamara ? 'Lector USB' : 'Cámara Web' }}</span>
                </button>
                
                <button aria-label="Salir del modo kiosco y regresar al panel de control" @click="intentarSalir" class="bg-red-500/10 hover:bg-red-500 hover:text-white text-red-400 px-5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 border border-red-500/20">
                    <i class="fas fa-lock"></i> Salir
                </button>
            </div>
        </nav>

        <!-- Main Body -->
        <main class="flex-1 flex flex-col items-center justify-center p-6 relative overflow-hidden">
            
            <!-- Real-time Clock (Kiosk display) -->
            <div class="text-center mb-6 z-10 animate-fade-in-up" v-show="!usandoCamara">
                <h2 class="text-8xl md:text-9xl font-black text-white tracking-tighter drop-shadow-2xl font-display">{{ horaActual }}</h2>
                <p class="text-lg md:text-xl font-bold text-primary uppercase tracking-widest mt-2">{{ fechaActual }}</p>
            </div>

            <!-- Scanner Box -->
            <div class="w-full max-w-2xl bg-slate-950/40 backdrop-blur-2xl rounded-[3rem] shadow-2xl overflow-hidden border-4 transition-all duration-300 relative z-10 flex flex-col" :class="bordePrincipal">
                
                <!-- USB Reader View (Phantom input active) -->
                <div v-show="!usandoCamara" class="p-10 text-center flex flex-col items-center justify-center min-h-[380px]">
                    
                    <div class="mb-6" :class="{ 'animate-bounce': estado === 'esperando' }">
                        <i class="fas fa-qrcode text-7xl mb-5 transition-colors duration-300" :class="colorIconoCentro"></i>
                        <h3 class="text-3xl font-black text-slate-200 font-display">{{ tituloKiosco }}</h3>
                        <p class="text-slate-400 font-bold text-sm mt-1">Coloque su credencial frente al escáner USB.</p>
                    </div>

                    <!-- Input Fantasma / Hidden focused input for USB scanners -->
                    <input type="text" 
                           ref="inputLector"
                           v-model="codigoEscaneado" 
                           @keyup.enter="procesarCodigoDesdeInput" 
                           @blur="mantenerFocoVirtual"
                           class="opacity-0 w-1 h-1 absolute -z-10 outline-none"
                           autocomplete="off"
                           :disabled="procesando">

                    <!-- Visual Feedback Box -->
                    <div class="w-full h-24 flex items-center justify-center rounded-2xl transition-colors duration-300 mt-4" :class="fondoFeedback">
                        <div v-if="mensajeEstado.texto" class="flex items-center gap-5 text-left w-full px-6">
                            <div class="text-4xl" :class="mensajeEstado.claseIcono" v-html="mensajeEstado.icono"></div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-xl uppercase tracking-tight leading-none mb-1 truncate" :class="mensajeEstado.claseTextoTitulo">{{ mensajeEstado.persona }}</p>
                                <p class="font-bold text-xs" :class="mensajeEstado.claseTextoDesc">{{ mensajeEstado.texto }}</p>
                            </div>
                        </div>
                        <div v-else class="text-slate-500 font-black uppercase tracking-widest text-xs flex items-center gap-3">
                            <i class="fas fa-barcode fa-fade text-lg"></i> Esperando lectura del escáner...
                        </div>
                    </div>
                </div>

                <!-- Web Camera View -->
                <div v-show="usandoCamara" class="relative flex-1 flex flex-col min-h-[480px]">
                    <div class="bg-slate-900/80 text-slate-400 px-4 py-3 text-center text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 border-b border-white/5 z-10">
                        <i class="fas fa-video text-primary"></i> Enfoque su código QR en el recuadro
                    </div>
                    
                    <div class="flex-1 bg-black relative flex items-center justify-center overflow-hidden">
                        <div id="lector-qr" class="w-full h-full min-h-[400px]"></div>
                    </div>
                    
                    <!-- Overlay floating feedback -->
                    <div class="absolute bottom-0 left-0 w-full p-6 bg-slate-950/90 backdrop-blur-md border-t border-white/5 transition-transform duration-300" v-show="mensajeEstado.texto">
                        <div class="flex items-center gap-5">
                            <div class="text-4xl" :class="mensajeEstado.claseIcono" v-html="mensajeEstado.icono"></div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-xl uppercase leading-none mb-1 truncate" :class="mensajeEstado.claseTextoTitulo">{{ mensajeEstado.persona }}</p>
                                <p class="font-bold text-xs" :class="mensajeEstado.claseTextoDesc">{{ mensajeEstado.texto }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Glowing lights in background -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-primary/5 rounded-full blur-[120px] z-0 pointer-events-none"></div>
            <div class="absolute top-1/3 left-1/3 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-secondary/5 rounded-full blur-[120px] z-0 pointer-events-none"></div>
        </main>

        <!-- CDNs for Beeps -->
        <audio id="beep-ok" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
        <audio id="beep-error" src="https://assets.mixkit.co/active_storage/sfx/2572/2572-preview.mp3" preload="auto"></audio>
    </div>
</template>

<style>
/* CSS Animations specific to Kiosk Feedbacks */
@keyframes pulse-green { 
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 
    70% { box-shadow: 0 0 0 25px rgba(16, 185, 129, 0); } 
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } 
}
@keyframes pulse-red { 
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 
    70% { box-shadow: 0 0 0 25px rgba(239, 68, 68, 0); } 
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } 
}
.pulse-success { animation: pulse-green 1s ease-out; }
.pulse-error { animation: pulse-red 1s ease-out; }

/* html5-qrcode override styles for camera design fit */
#lector-qr video {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}
#lector-qr img {
    display: none !important;
}
</style>
