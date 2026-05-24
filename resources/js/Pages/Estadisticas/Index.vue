<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js'
import { Bar, Doughnut } from 'vue-chartjs'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement)

const props = defineProps({
    kpis: Object,
    graficaDias: Array,
    graficaRoles: Array
});

// Configuración de Gráfica de Barras (Últimos Días)
const barData = computed(() => ({
    labels: props.graficaDias.map(d => d.dia),
    datasets: [{
        label: 'Accesos Registrados',
        data: props.graficaDias.map(d => d.total),
        backgroundColor: '#009FE3',
        borderRadius: 8,
        borderWidth: 0,
        hoverBackgroundColor: '#0284c7'
    }]
}));

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { family: 'Inter', size: 13 },
            bodyFont: { family: 'Inter', size: 14, weight: 'bold' },
            padding: 12,
            cornerRadius: 12
        }
    },
    scales: {
        y: { 
            beginAtZero: true, 
            grid: { color: '#f1f5f9', drawBorder: false },
            ticks: { font: { family: 'Inter', weight: 'bold' }, color: '#94a3b8' }
        },
        x: {
            grid: { display: false },
            ticks: { font: { family: 'Inter', weight: 'bold' }, color: '#64748b' }
        }
    }
};

// Configuración de Gráfica de Dona (Roles)
const doughnutData = computed(() => {
    // Colores corporativos del CAM #48
    const colores = {
        'Alumnos': '#009FE3', // Azul Primary
        'Personal': '#A64B9D', // Morado Secondary
        'Tutores': '#f59e0b', // Naranja/Ambar
    };
    
    return {
        labels: props.graficaRoles.map(r => r.tipo),
        datasets: [{
            data: props.graficaRoles.map(r => r.total),
            backgroundColor: props.graficaRoles.map(r => colores[r.tipo] || '#cbd5e1'),
            hoverOffset: 10,
            borderWidth: 0
        }]
    };
});

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '75%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: { family: 'Inter', weight: 'bold', size: 13 },
                color: '#64748b'
            }
        },
        tooltip: {
            backgroundColor: '#1e293b',
            bodyFont: { family: 'Inter', size: 14, weight: 'bold' },
            padding: 12,
            cornerRadius: 12
        }
    }
};

</script>

<template>
    <Head title="Estadísticas y Análisis" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-xl text-slate-800 dark:text-white tracking-tight">Análisis <span class="text-primary">Estadístico</span></h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto animate-fade-in-up">
            
            <div class="mb-8 hidden md:flex justify-between items-end print-hidden">
                <div>
                    <h1 class="font-display font-black text-3xl text-slate-800 dark:text-white tracking-tight">
                        Análisis <span class="text-primary">Estadístico</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 font-bold text-sm mt-1">Monitoreo del flujo escolar e indicadores de asistencia.</p>
                </div>
                <button onclick="window.print()" class="btn-premium flex items-center gap-2">
                    <i class="fas fa-file-pdf"></i> Exportar a PDF
                </button>
            </div>

            <!-- KPIs Superiores -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Tarjeta 1 -->
                <div class="bg-white dark:bg-slate-800/50 p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700/50 flex items-center gap-5 hover:-translate-y-1 transition-transform">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 flex items-center justify-center text-2xl shadow-sm border border-emerald-100/50 dark:border-emerald-500/20">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest leading-tight">Días con Actividad</p>
                        <div class="flex items-end gap-2 mt-1">
                            <h3 class="text-3xl font-black text-slate-800 dark:text-white leading-none font-display">{{ kpis.diasActivos }}</h3>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta 2 -->
                <div class="bg-gradient-to-br from-primary to-blue-500 p-6 rounded-3xl shadow-md shadow-primary/20 flex items-center gap-5 hover:-translate-y-1 transition-transform relative overflow-hidden">
                    <div class="w-14 h-14 rounded-2xl bg-white/20 text-white flex items-center justify-center text-2xl backdrop-blur-sm z-10">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="z-10">
                        <p class="text-[10px] font-black uppercase text-blue-100 tracking-widest leading-tight">Asistencia Promedio Hoy</p>
                        <div class="flex items-end gap-2 mt-1">
                            <h3 class="text-3xl font-black text-white leading-none font-display">{{ kpis.promedioAsistenciaHoy }}%</h3>
                        </div>
                    </div>
                    <!-- Decoración -->
                    <div class="absolute -right-6 -bottom-6 text-white/10 text-8xl rotate-12 pointer-events-none">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>

                <!-- Tarjeta 3 -->
                <div class="bg-white dark:bg-slate-800/50 p-6 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700/50 flex items-center gap-5 hover:-translate-y-1 transition-transform">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-500/10 text-amber-500 dark:text-amber-400 flex items-center justify-center text-2xl shadow-sm border border-amber-100/50 dark:border-amber-500/20">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest leading-tight">Tutores Sin Asignar</p>
                        <div class="flex items-end gap-2 mt-1">
                            <h3 class="text-3xl font-black text-slate-800 dark:text-white leading-none font-display">{{ kpis.tutoresInactivos }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficas Principales -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Gráfica de Barras -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-800/50 rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 dark:border-slate-700/50 relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="font-black text-slate-800 dark:text-slate-200 text-lg uppercase tracking-tight">Accesos (Últimos Días)</h2>
                            <p class="text-xs font-bold text-slate-400 mt-1">Volumen de asistencias registradas</p>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-700 text-slate-400 dark:text-slate-300 w-10 h-10 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                    
                    <div class="h-72 w-full">
                        <Bar v-if="graficaDias.length > 0" :data="barData" :options="barOptions" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center text-slate-300">
                            <i class="fas fa-chart-bar text-4xl mb-3"></i>
                            <p class="font-bold">No hay datos suficientes</p>
                        </div>
                    </div>
                </div>

                <!-- Gráfica de Dona -->
                <div class="bg-white dark:bg-slate-800/50 rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 dark:border-slate-700/50 flex flex-col">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="font-black text-slate-800 dark:text-slate-200 text-lg uppercase tracking-tight">Distribución Total</h2>
                            <p class="text-xs font-bold text-slate-400 mt-1">Participación por tipo de usuario</p>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-700 text-slate-400 dark:text-slate-300 w-10 h-10 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                    </div>
                    
                    <div class="flex-1 flex items-center justify-center min-h-[280px] relative">
                        <Doughnut v-if="graficaRoles.length > 0" :data="doughnutData" :options="doughnutOptions" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center text-slate-300">
                            <i class="fas fa-circle-notch text-4xl mb-3"></i>
                            <p class="font-bold">No hay datos</p>
                        </div>
                        
                        <!-- Etiqueta Central Dona -->
                        <div v-if="graficaRoles.length > 0" class="absolute top-[40%] left-1/2 -translate-x-1/2 -translate-y-1/2 text-center pointer-events-none">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total</p>
                            <p class="text-2xl font-black text-slate-800 dark:text-white font-display leading-none mt-1">
                                {{ graficaRoles.reduce((a, b) => a + b.total, 0) }}
                            </p>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </AuthenticatedLayout>
</template>
