<script setup>
import { computed } from 'vue';

const props = defineProps({
    /**
     * Variante del logo:
     * - 'horizontal': versión apaisada con isotipo y texto lateral
     * - 'completo' | 'institucional': versión completa vertical con escudo circular y texto inferior
     * - 'isotipo' | 'icono': versión únicamente escudo circular
     */
    variant: {
        type: String,
        default: 'horizontal',
        validator: (value) => ['horizontal', 'completo', 'institucional', 'isotipo', 'icono'].includes(value),
    },
    /**
     * Tamaño preconfigurado: 'xs', 'sm', 'md', 'lg', 'xl', '2xl' o clase de altura personalizada
     */
    size: {
        type: String,
        default: 'md',
    },
    /**
     * Forzar tema si el contenedor tiene fondo fijo:
     * - 'light': siempre logo claro (para fondos claros)
     * - 'dark': siempre logo oscuro (para fondos oscuros / sidebar)
     * - null: reactivo al modo dark/light de Tailwind (dark:hidden / dark:block)
     */
    forceTheme: {
        type: String,
        default: null,
        validator: (value) => [null, 'light', 'dark'].includes(value),
    },
    /**
     * Texto alternativo accesible para lectores de pantalla
     */
    alt: {
        type: String,
        default: 'CAM Tonalpouhqui — Centro de Atención Múltiple #48',
    },
    /**
     * Clases adicionales para las imágenes SVG
     */
    imageClass: {
        type: String,
        default: '',
    },
});

const isHorizontal = computed(() => props.variant === 'horizontal');
const isIsotipo = computed(() => ['isotipo', 'icono'].includes(props.variant));

// Tamaños visuales calibrados según los estándares de Apple Design y especificaciones institucionales
const sizeClass = computed(() => {
    if (isHorizontal.value) {
        switch (props.size) {
            case 'xs': return 'h-8';
            case 'sm': return 'h-10 sm:h-12';
            case 'md': return 'h-16 sm:h-[68px] lg:h-[72px]'; // 60–75px (Sidebar expandido / Encabezado)
            case 'lg': return 'h-20 sm:h-24';
            case 'xl': return 'h-28 sm:h-32';
            case '2xl': return 'h-36 sm:h-40';
            default: return props.size; // Permite pasar clases de Tailwind directas tipo 'h-[72px]'
        }
    } else if (isIsotipo.value) {
        switch (props.size) {
            case 'xs': return 'h-8 w-8';
            case 'sm': return 'h-10 w-10';
            case 'md': return 'h-12 w-12 sm:h-14 sm:h-14'; // 48–56px (Sidebar contraído)
            case 'lg': return 'h-16 w-16';
            case 'xl': return 'h-20 w-20';
            case '2xl': return 'h-24 w-24';
            default: return props.size;
        }
    } else {
        // Institucional / Completo
        switch (props.size) {
            case 'xs': return 'h-28';
            case 'sm': return 'h-36 sm:h-40';
            case 'md': return 'h-44 sm:h-52 md:h-60 lg:h-64'; // 176–256px (Login móvil 150-190px, escritorio 220-280px)
            case 'lg': return 'h-56 sm:h-64 md:h-72 lg:h-80'; // 224–320px
            case 'xl': return 'h-72 sm:h-80 md:h-96';
            case '2xl': return 'h-96';
            default: return props.size;
        }
    }
});

// Rutas de los logos
const lightLogoSrc = computed(() => {
    if (isIsotipo.value) return '/favicon.svg';
    if (isHorizontal.value) return '/logo-horizontal-claro.svg';
    return '/logo-claro.svg';
});

const darkLogoSrc = computed(() => {
    if (isIsotipo.value) return '/favicon.svg';
    if (isHorizontal.value) return '/logo-horizontal-oscuro.svg';
    return '/logo-oscuro.svg';
});
</script>

<template>
    <div class="inline-flex items-center justify-center select-none shrink-0" role="img" :aria-label="alt">
        <!-- Caso 1: Forzado a modo oscuro (ej. sidebar con fondo dark continuo) -->
        <img
            v-if="forceTheme === 'dark'"
            :src="darkLogoSrc"
            :alt="alt"
            :class="[sizeClass, imageClass, 'w-full max-w-full object-contain drop-shadow-sm']"
            loading="eager"
            decoding="async"
        />

        <!-- Caso 2: Forzado a modo claro (ej. superficies blancas fijas) -->
        <img
            v-else-if="forceTheme === 'light'"
            :src="lightLogoSrc"
            :alt="alt"
            :class="[sizeClass, imageClass, 'w-full max-w-full object-contain drop-shadow-sm']"
            loading="eager"
            decoding="async"
        />

        <!-- Caso 3: Dinámico y reactivo a Tailwind CSS (dark:hidden / dark:block sin parpadeo) -->
        <template v-else>
            <img
                :src="lightLogoSrc"
                :alt="alt"
                :class="[sizeClass, imageClass, 'w-full max-w-full object-contain drop-shadow-sm dark:hidden block']"
                loading="eager"
                decoding="async"
            />
            <img
                :src="darkLogoSrc"
                :alt="alt"
                :class="[sizeClass, imageClass, 'w-full max-w-full object-contain drop-shadow-sm hidden dark:block']"
                loading="eager"
                decoding="async"
            />
        </template>
    </div>
</template>
