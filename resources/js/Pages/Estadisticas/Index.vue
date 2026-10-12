<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
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

// Exportar a PDF
const exportarPDF = () => {
    window.print();
};

// Gráfica de Barras (Últimos Días)
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
            backgroundColor: '#0f172a',
            titleFont: { family: '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Inter"', size: 12 },
            bodyFont: { family: '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Inter"', size: 13, weight: 'bold' },
            padding: 10,
            cornerRadius: 10
        }
    },
    scales: {
        y: { 
            beginAtZero: true, 
            grid: { color: 'rgba(203, 213, 225, 0.3)', drawBorder: false },
            ticks: { font: { family: 'Inter', weight: 'bold', size: 11 }, color: '#94a3b8' }
        },
        x: {
            grid: { display: false },
            ticks: { font: { family: 'Inter', weight: 'bold', size: 11 }, color: '#64748b' }
        }
    }
};

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
            hoverOffset: 6,
            borderWidth: 0
        }]
    };
});

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 16,
                font: { family: 'Inter', weight: 'bold', size: 12 },
                color: '#64748b'
            }
        },
        tooltip: {
            backgroundColor: '#0f172a',
            bodyFont: { family: 'Inter', size: 13, weight: 'bold' },
            padding: 10,
            cornerRadius: 10
        }
    }
};
</script>

<template>
    <Head title="Estadísticas y Análisis — FamilyLock" />

    <AuthenticatedLayout>
        <Teleport to="#portal-header-title" v-if="$el">
            <h1 class="font-display font-black text-lg text-slate-900 dark:text-white">Estadísticas</h1>
        </Teleport>

        <div class="p-6 md:p-8 max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print-hidden">
                <div>
                    <h1 class="font-display font-black text-2xl sm:text-3xl text-slate-900 dark:text-white tracking-tight">
                        Análisis <span class="text-gradient-primary">Estadístico</span>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-medium mt-1">
                        Monitoreo del flujo escolar, tendencias e indicadores de asistencia del CAM #48.
                    </p>
                </div>
                <button @click="exportarPDF" class="btn-ghost flex items-center gap-2">
                    <i class="fas fa-file-pdf text-rose-500"></i> 
                    <span>Exportar a PDF</span>
                </button>
            </div>

            <!-- KPIs Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                <!-- Tarjeta 1: Días con Actividad -->
                <div class="premium-card p-5 sm:p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0">
                        <i class="far fa-calendar-check"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Días con Actividad</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white font-display mt-0.5 leading-none">
                            {{ kpis.diasActivos }}
                        </h3>
                    </div>
                </div>

                <!-- Tarjeta 2: Asistencia Promedio Hoy -->
                <div class="premium-card p-5 sm:p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Asistencia Promedio Hoy</p>
                        <h3 class="text-3xl font-black text-sky-600 dark:text-sky-400 font-display mt-0.5 leading-none">
                            {{ kpis.promedioAsistenciaHoy }}%
                        </h3>
                    </div>
                </div>

                <!-- Tarjeta 3: Tutores Sin Asignar -->
                <div class="premium-card p-5 sm:p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Tutores Pendientes</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white font-display mt-0.5 leading-none">
                            {{ kpis.tutoresInactivos }}
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Charts Section (Apple Clean Analytics Layout) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Bar Chart: Volúmen de Accesos -->
                <div class="lg:col-span-2 premium-card-static p-6 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="font-display font-black text-slate-900 dark:text-white text-base">Accesos Recientes</h2>
                            <p class="text-xs text-slate-400 mt-0.5 font-medium">Volumen diario de asistencias registradas</p>
                        </div>
                        <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xs">
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
                        <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs">
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
                        <div v-if="graficaRoles.length > 0" class="absolute top-[42%] left-1/2 -translate-x-1/2 -translate-y-1/2 text-center pointer-events-none">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                            <p class="text-xl font-black text-slate-900 dark:text-white font-mono leading-none mt-0.5">
                                {{ graficaRoles.reduce((a, b) => a + b.total, 0) }}
                            </p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Top Parents Ranking Table -->
            <div class="premium-card-static overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-200/70 dark:border-white/[0.08] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/15 text-amber-500 flex items-center justify-center text-base shrink-0">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div>
                            <h2 class="font-display font-black text-lg text-slate-900 dark:text-white">
                                Top Padres Comprometidos
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                Ranking de tutores con mayor frecuencia de asistencia y puntualidad.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 print-hidden">
                        <button @click="exportarExcel" class="btn-ghost flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                            <i class="fas fa-file-excel"></i> <span>Descargar Excel</span>
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
                            <tr v-for="(tutor, index) in rankingTutores" :key="tutor.telefono" class="group">
                                <td>
                                    <div class="flex justify-center">
                                        <div 
                                            class="w-7 h-7 rounded-xl flex items-center justify-center text-xs font-black shadow-sm"
                                            :class="{
                                                'bg-amber-400 text-amber-950': index === 0,
                                                'bg-slate-300 dark:bg-slate-700 text-slate-800 dark:text-slate-200': index === 1,
                                                'bg-amber-600/30 text-amber-800 dark:text-amber-300': index === 2,
                                                'bg-slate-100 dark:bg-slate-800 text-slate-500': index > 2
                                            }"
                                        >
                                            <i v-if="index === 0" class="fas fa-crown text-[9px] mr-0.5"></i>
                                            {{ index + 1 }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-800 dark:text-slate-100 text-xs uppercase">
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
