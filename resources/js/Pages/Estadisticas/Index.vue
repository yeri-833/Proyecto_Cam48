<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';
import { Bar, Doughnut } from 'vue-chartjs';
import ExcelJS from 'exceljs';
import { saveAs } from 'file-saver';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    kpis: {
        type: Object,
        default: () => ({ diasActivos: 0, promedioAsistenciaHoy: 0, tutoresInactivos: 0 }),
    },
    graficaDias: {
        type: Array,
        default: () => [],
    },
    graficaRoles: {
        type: Array,
        default: () => [],
    },
    rankingTutores: {
        type: Array,
        default: () => [],
    }
});

// Reactividad del tema para Chart.js
const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    const observer = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    onUnmounted(() => observer.disconnect());
});

// Exportar a Excel Institucional
const exportarExcel = async () => {
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'Sistema FamilyLock — CAM #48';
    workbook.created = new Date();
    
    const sheet = workbook.addWorksheet('Ranking Padres', {
        views: [{ showGridLines: false }]
    });

    // 1. Título Institucional
    sheet.mergeCells('A1:E2');
    const titleCell = sheet.getCell('A1');
    titleCell.value = 'CAM #48 - TOP PADRES COMPROMETIDOS';
    titleCell.font = { name: 'Arial', size: 16, bold: true, color: { argb: 'FFFFFFFF' } };
    titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF009FE3' } };
    titleCell.alignment = { vertical: 'middle', horizontal: 'center' };

    // Subtítulo
    sheet.mergeCells('A3:E3');
    const subtitleCell = sheet.getCell('A3');
    subtitleCell.value = `Reporte generado el: ${new Date().toLocaleDateString()}`;
    subtitleCell.font = { name: 'Arial', size: 10, italic: true, color: { argb: 'FF555555' } };
    subtitleCell.alignment = { horizontal: 'right' };
    
    sheet.addRow([]);

    // 2. Encabezados de la Tabla
    const headerRow = sheet.addRow(['Rank', 'Nombre del Tutor', 'Contacto / Teléfono', 'Alumno Vinculado', 'Asistencias']);
    headerRow.height = 25;
    headerRow.eachCell((cell) => {
        cell.font = { name: 'Arial', bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E293B' } };
        cell.alignment = { vertical: 'middle', horizontal: 'center' };
        cell.border = {
            top: { style: 'thin', color: { argb: 'FF94A3B8' } },
            bottom: { style: 'thin', color: { argb: 'FF94A3B8' } }
        };
    });

    // 3. Llenado de Datos
    props.rankingTutores.forEach((tutor, index) => {
        const row = sheet.addRow([
            index + 1,
            tutor.nombre_tutor,
            tutor.telefono,
            tutor.nombre_alumno,
            tutor.asistencias
        ]);
        
        row.height = 20;
        row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
        row.getCell(1).font = { bold: true, color: { argb: 'FF64748B' } };
        
        row.getCell(5).alignment = { horizontal: 'center', vertical: 'middle' };
        row.getCell(5).font = { bold: true, color: { argb: 'FF059669' } };
        
        row.eachCell((cell) => {
            if (cell.col !== 1 && cell.col !== 5) cell.alignment = { vertical: 'middle', indent: 1 };
            cell.border = { bottom: { style: 'hair', color: { argb: 'FFCBD5E1' } } };
        });
        
        if (index === 0) row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFDE68A' } };
        if (index === 1) row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2E8F0' } };
        if (index === 2) row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFEDD5' } };
    });

    sheet.columns = [
        { width: 8 },
        { width: 36 },
        { width: 20 },
        { width: 36 },
        { width: 15 }
    ];

    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    saveAs(blob, 'Ranking_Padres_CAM48.xlsx');
};

// Exportar a PDF (Diálogo de impresión con formato institucional)
const exportarPDF = () => {
    window.print();
};

// Gráfica de Barras (Accesos por Día)
const barData = computed(() => ({
    labels: props.graficaDias.map(d => d.dia),
    datasets: [{
        label: 'Accesos Registrados',
        data: props.graficaDias.map(d => d.total),
        backgroundColor: '#009FE3',
        borderRadius: { topLeft: 8, topRight: 8, bottomLeft: 0, bottomRight: 0 },
        borderWidth: 0,
        hoverBackgroundColor: '#0284c7'
    }]
}));

const barOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: {
        duration: 850,
        easing: 'easeOutQuart',
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: isDark.value ? '#0f172a' : '#ffffff',
            titleColor: isDark.value ? '#f8fafc' : '#0f172a',
            bodyColor: isDark.value ? '#94a3b8' : '#475569',
            borderColor: isDark.value ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)',
            borderWidth: 1,
            titleFont: { family: 'Outfit, Inter, sans-serif', size: 13, weight: 'bold' },
            bodyFont: { family: 'Inter, sans-serif', size: 12 },
            padding: 12,
            cornerRadius: 12,
            boxPadding: 6,
            callbacks: {
                label: (context) => ` ${context.parsed.y} asistencias registradas`
            }
        }
    },
    scales: {
        y: { 
            beginAtZero: true, 
            grid: { 
                color: isDark.value ? 'rgba(255, 255, 255, 0.06)' : 'rgba(148, 163, 184, 0.15)', 
                drawBorder: false 
            },
            ticks: { 
                font: { family: 'Inter', weight: 'bold', size: 11 }, 
                color: isDark.value ? '#94a3b8' : '#64748b',
                precision: 0
            }
        },
        x: {
            grid: { display: false },
            ticks: { 
                font: { family: 'Inter', weight: '600', size: 11 }, 
                color: isDark.value ? '#94a3b8' : '#64748b' 
            }
        }
    }
}));

// Gráfica de Dona (Roles)
const doughnutData = computed(() => {
    const colores = {
        'Alumnos': '#009FE3',
        'Personal': '#A64B9D',
        'Tutores': '#f59e0b',
    };
    
    return {
        labels: props.graficaRoles.map(r => r.tipo),
        datasets: [{
            data: props.graficaRoles.map(r => r.total),
            backgroundColor: props.graficaRoles.map(r => colores[r.tipo] || '#cbd5e1'),
            hoverOffset: 8,
            borderWidth: 3,
            borderColor: isDark.value ? '#0f172a' : '#ffffff'
        }]
    };
});

const doughnutOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    animation: {
        animateRotate: true,
        animateScale: true,
        duration: 950,
        easing: 'easeOutQuart',
    },
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 18,
                font: { family: 'Inter', weight: 'bold', size: 12 },
                color: isDark.value ? '#cbd5e1' : '#475569'
            }
        },
        tooltip: {
            backgroundColor: isDark.value ? '#0f172a' : '#ffffff',
            titleColor: isDark.value ? '#f8fafc' : '#0f172a',
            bodyColor: isDark.value ? '#94a3b8' : '#475569',
            borderColor: isDark.value ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)',
            borderWidth: 1,
            bodyFont: { family: 'Inter', size: 12, weight: 'bold' },
            padding: 12,
            cornerRadius: 12,
            boxPadding: 6,
            callbacks: {
                label: (context) => {
                    const label = context.label || '';
                    const val = context.raw || 0;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                    return ` ${label}: ${val} (${pct}%)`;
                }
            }
        }
    }
}));
</script>

<template>
    <Head title="Estadísticas y Análisis — FamilyLock" />

    <AuthenticatedLayout>
        <div class="p-6 md:p-8 max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden animate-fade-in-up">
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="badge badge-blue">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse mr-1"></span>
                            Módulo de Analítica
                        </span>
                        <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                            CAM #48 Tonalpouhqui
                        </span>
                    </div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight leading-tight">
                        Análisis <span class="text-gradient-primary">Estadístico</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-medium mt-1">
                        Monitoreo del flujo escolar, tendencias e indicadores de asistencia en tiempo real.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button 
                        @click="exportarPDF" 
                        class="btn-secondary flex items-center gap-2.5 shadow-sm"
                        title="Imprimir o guardar reporte estadístico en PDF"
                    >
                        <i class="fas fa-file-pdf text-rose-500 text-sm"></i> 
                        <span>Exportar a PDF</span>
                    </button>
                </div>
            </div>

            <!-- KPIs Summary Cards (Staggered Animation & Flagship Hierarchy) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                <!-- Tarjeta 1: Días con Actividad -->
                <div class="kpi-card group animate-fade-in-up" style="animation-delay: 80ms;">
                    <div class="flex-1 min-w-0 pr-3">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-wide uppercase">
                            Días con Actividad
                        </p>
                        <h3 class="kpi-number text-emerald-600 dark:text-emerald-400">
                            {{ kpis.diasActivos }}
                        </h3>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                Jornadas escolares
                            </span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/25 shadow-sm group-hover:bg-emerald-100 dark:group-hover:bg-emerald-500/20">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 2: Asistencia Promedio Hoy -->
                <div class="kpi-card group animate-fade-in-up" style="animation-delay: 160ms;">
                    <div class="flex-1 min-w-0 pr-3">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-wide uppercase">
                            Asistencia Promedio Hoy
                        </p>
                        <h3 class="kpi-number text-sky-600 dark:text-sky-400">
                            {{ kpis.promedioAsistenciaHoy }}%
                        </h3>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span>
                            <span class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                Tasa calculada hoy
                            </span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-500/25 shadow-sm group-hover:bg-sky-100 dark:group-hover:bg-sky-500/20">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 3: Tutores Pendientes -->
                <div class="kpi-card group animate-fade-in-up" style="animation-delay: 240ms;">
                    <div class="flex-1 min-w-0 pr-3">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-wide uppercase">
                            Tutores Pendientes
                        </p>
                        <h3 class="kpi-number text-amber-600 dark:text-amber-400">
                            {{ kpis.tutoresInactivos }}
                        </h3>
                        <div class="flex items-center gap-1.5 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                                Sin registro reciente
                            </span>
                        </div>
                    </div>
                    <div class="kpi-icon bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-500/25 shadow-sm group-hover:bg-amber-100 dark:group-hover:bg-amber-500/20">
                        <svg class="w-6 h-6 stroke-[2]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Charts Section (Apple Clean Analytics Layout) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8 animate-fade-in-up" style="animation-delay: 200ms;">
                
                <!-- Bar Chart: Volúmen de Accesos -->
                <div class="lg:col-span-2 premium-card-static p-6 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="font-display font-black text-slate-900 dark:text-white text-base">Accesos Registrados por Jornada</h2>
                            <p class="text-xs text-slate-400 mt-0.5 font-medium">Historial de asistencias en los días con actividad escolar</p>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-sm shadow-sm">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                    
                    <div class="h-64 sm:h-72 w-full flex-1">
                        <Bar v-if="graficaDias.length > 0" :data="barData" :options="barOptions" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center text-slate-400">
                            <i class="fas fa-chart-bar text-3xl mb-2 text-slate-300"></i>
                            <p class="text-xs font-semibold">Sin datos de asistencias recientes</p>
                        </div>
                    </div>
                </div>

                <!-- Doughnut Chart: Participación por Rol -->
                <div class="premium-card-static p-6 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="font-display font-black text-slate-900 dark:text-white text-base">Distribución por Rol</h2>
                            <p class="text-xs text-slate-400 mt-0.5 font-medium">Participación en el total escolar</p>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shadow-sm">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                    </div>
                    
                    <div class="flex-1 flex items-center justify-center min-h-[220px] relative">
                        <Doughnut v-if="graficaRoles.length > 0" :data="doughnutData" :options="doughnutOptions" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center text-slate-400">
                            <i class="fas fa-chart-pie text-3xl mb-2 text-slate-300"></i>
                            <p class="text-xs font-semibold">Sin registros clasificados</p>
                        </div>
                        
                        <!-- Central Value Indicator -->
                        <div v-if="graficaRoles.length > 0" class="absolute top-[40%] left-1/2 -translate-x-1/2 -translate-y-1/2 text-center pointer-events-none">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                            <p class="text-2xl font-black text-slate-900 dark:text-white font-mono leading-none mt-0.5">
                                {{ graficaRoles.reduce((a, b) => a + b.total, 0) }}
                            </p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Top Parents Ranking Table -->
            <div class="premium-card-static overflow-hidden animate-fade-in-up" style="animation-delay: 280ms;">
                <div class="p-5 sm:p-6 border-b border-slate-200/70 dark:border-white/[0.08] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 rounded-2xl bg-amber-500/15 text-amber-500 flex items-center justify-center text-lg shrink-0 shadow-sm border border-amber-500/20">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div>
                            <h2 class="font-display font-black text-lg text-slate-900 dark:text-white">
                                Top Padres Comprometidos
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                Ranking de tutores con mayor frecuencia de asistencia y puntualidad escolar.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 print:hidden">
                        <button @click="exportarExcel" class="btn-ghost flex items-center gap-2 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">
                            <i class="fas fa-file-excel text-sm"></i> <span>Descargar Excel</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto" id="tabla-ranking-padres">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th class="text-center w-16">Lugar</th>
                                <th>Nombre del Tutor</th>
                                <th>Teléfono / Contacto</th>
                                <th>Alumno Vinculado</th>
                                <th class="text-center">Asistencias</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(tutor, index) in rankingTutores" :key="tutor.telefono || index" class="group transition-colors duration-150">
                                <td>
                                    <div class="flex justify-center">
                                        <div 
                                            class="w-7 h-7 rounded-xl flex items-center justify-center text-xs font-black shadow-sm"
                                            :class="{
                                                'bg-gradient-to-br from-amber-300 to-amber-500 text-amber-950 shadow-amber-500/20': index === 0,
                                                'bg-gradient-to-br from-slate-200 to-slate-400 dark:from-slate-600 dark:to-slate-700 text-slate-800 dark:text-slate-100': index === 1,
                                                'bg-gradient-to-br from-amber-600/30 to-amber-700/40 text-amber-800 dark:text-amber-300': index === 2,
                                                'bg-slate-100 dark:bg-slate-800 text-slate-500': index > 2
                                            }"
                                        >
                                            <i v-if="index === 0" class="fas fa-crown text-[8px] mr-0.5"></i>
                                            {{ index + 1 }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase tracking-tight">
                                        {{ tutor.nombre_tutor }}
                                    </p>
                                </td>
                                <td>
                                    <span class="font-mono text-xs text-slate-500 dark:text-slate-400">
                                        {{ tutor.telefono || '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="font-bold text-xs text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 px-2 py-0.5 rounded-md uppercase">
                                        {{ tutor.nombre_alumno }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex justify-center">
                                        <span class="font-bold text-xs px-2.5 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-500/30">
                                            {{ tutor.asistencias }} asistencias
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rankingTutores.length === 0">
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-medal text-2xl"></i>
                                        </div>
                                        <p class="text-slate-700 dark:text-slate-300 font-bold text-sm">Aún no hay tutores en el ranking</p>
                                        <p class="text-slate-400 text-xs mt-1">Registra asistencias diarias para comenzar a tabular el ranking.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
