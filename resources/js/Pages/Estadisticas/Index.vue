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
    graficaRoles: Array,
    rankingTutores: Array
});

import ExcelJS from 'exceljs';
import { saveAs } from 'file-saver';

// Exportar a Excel Institucional
const exportarExcel = async () => {
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'Sistema CAM #48';
    workbook.created = new Date();
    
    const sheet = workbook.addWorksheet('Ranking Padres', {
        views: [{ showGridLines: false }]
    });

    // 1. Título Institucional
    sheet.mergeCells('A1:E2');
    const titleCell = sheet.getCell('A1');
    titleCell.value = 'CAM #48 - TOP PADRES COMPROMETIDOS';
    titleCell.font = { name: 'Arial', size: 16, bold: true, color: { argb: 'FFFFFFFF' } };
    titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF009FE3' } }; // Azul Primary CAM48
    titleCell.alignment = { vertical: 'middle', horizontal: 'center' };

    // Subtítulo (Fecha de corte)
    sheet.mergeCells('A3:E3');
    const subtitleCell = sheet.getCell('A3');
    subtitleCell.value = `Reporte generado el: ${new Date().toLocaleDateString()}`;
    subtitleCell.font = { name: 'Arial', size: 10, italic: true, color: { argb: 'FF555555' } };
    subtitleCell.alignment = { horizontal: 'right' };
    
    // Espacio en blanco
    sheet.addRow([]);

    // 2. Encabezados de la Tabla
    const headerRow = sheet.addRow(['Rank', 'Nombre del Tutor', 'Contacto / Teléfono', 'Alumno Vinculado', 'Asistencias']);
    headerRow.height = 25;
    headerRow.eachCell((cell, colNumber) => {
        cell.font = { name: 'Arial', bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF334155' } }; // Slate 700
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
        
        // Estilos para la celda de Rank y Asistencias (Centrados)
        row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
        row.getCell(1).font = { bold: true, color: { argb: 'FF64748B' } };
        
        row.getCell(5).alignment = { horizontal: 'center', vertical: 'middle' };
        row.getCell(5).font = { bold: true, color: { argb: 'FF059669' } }; // Emerald 600
        
        // Alineación vertical para todos
        row.eachCell((cell) => {
            if(cell.col !== 1 && cell.col !== 5) cell.alignment = { vertical: 'middle', indent: 1 };
            cell.border = {
                bottom: { style: 'hair', color: { argb: 'FFCBD5E1' } } // Líneas divisoras sutiles
            };
        });
        
        // Resaltar Top 3
        if (index === 0) row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFDE68A' } }; // Oro
        if (index === 1) row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2E8F0' } }; // Plata
        if (index === 2) row.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFEDD5' } }; // Bronce
    });

    // 4. Ajustar Ancho de Columnas Automáticamente
    sheet.columns = [
        { width: 8 },  // Rank
        { width: 40 }, // Nombre
        { width: 20 }, // Contacto
        { width: 40 }, // Alumno
        { width: 15 }  // Asistencias
    ];

    // 5. Descargar Archivo
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    saveAs(blob, 'Ranking_Padres_CAM48.xlsx');
};


// Exportar a PDF
const exportarPDF = () => {
    const element = document.getElementById('tabla-ranking-padres');
    
    // Configuración para el PDF
    const opt = {
        margin:       0.5,
        filename:     'Top_Padres_Comprometidos_CAM48.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save();
};

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

            <!-- Tabla Ranking Tutores -->
            <div class="mt-8 premium-card p-6 md:p-8 relative overflow-hidden">
                <!-- Decoración de fondo -->
                <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full bg-primary/5 blur-3xl pointer-events-none"></div>
                <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-64 h-64 rounded-full bg-secondary/5 blur-3xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 relative z-10">
                    <div>
                        <h2 class="font-display font-black text-2xl text-slate-800 dark:text-white uppercase tracking-tight flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white flex items-center justify-center shadow-lg shadow-orange-500/30">
                                <i class="fas fa-trophy"></i>
                            </div>
                            Top Padres Comprometidos
                        </h2>
                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-2">Ranking en vivo de los tutores con mayor asistencia registrada.</p>
                    </div>
                    <div class="flex gap-3 print-hidden">
                        <button @click="exportarExcel" class="px-5 py-2.5 rounded-xl font-black text-sm flex items-center gap-2 text-white bg-gradient-to-r from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/30 hover:-translate-y-1 hover:shadow-emerald-500/50 active:translate-y-0 transition-all" v-tippy="'Descargar Excel'">
                            <i class="fas fa-file-excel text-lg"></i> <span class="hidden md:inline">Excel</span>
                        </button>
                        <button @click="exportarPDF" class="btn-premium flex items-center gap-2 shadow-primary/30" v-tippy="'Generar Reporte PDF'">
                            <i class="fas fa-file-pdf text-lg"></i> <span class="hidden md:inline">PDF</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto relative z-10" id="tabla-ranking-padres">
                    <table class="table-premium w-full">
                        <thead>
                            <tr>
                                <th class="text-center w-16">Rank</th>
                                <th>Nombre del Tutor</th>
                                <th>Contacto</th>
                                <th>Alumno Vinculado</th>
                                <th class="text-center">Asistencias</th>
                            </tr>
                        </thead>
                        <tbody v-auto-animate>
                            <tr v-for="(tutor, index) in rankingTutores" :key="tutor.telefono" class="group">
                                <td>
                                    <div class="flex justify-center">
                                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-black text-base shadow-sm transition-transform group-hover:scale-110"
                                             :class="index === 0 ? 'bg-gradient-to-br from-amber-200 to-amber-400 text-amber-900 shadow-amber-500/40' : (index === 1 ? 'bg-gradient-to-br from-slate-200 to-slate-400 text-slate-800 shadow-slate-500/40' : (index === 2 ? 'bg-gradient-to-br from-orange-200 to-orange-400 text-orange-900 shadow-orange-500/40' : 'bg-slate-100 dark:bg-slate-800/50 text-slate-400'))">
                                            <i v-if="index === 0" class="fas fa-crown text-sm mr-1"></i>
                                            {{ index + 1 }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-bold text-slate-700 dark:text-slate-200 text-sm uppercase">{{ tutor.nombre_tutor }}</p>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 font-bold text-xs">
                                        <i class="fas fa-phone-alt"></i>
                                        <span class="font-mono">{{ tutor.telefono }}</span>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-bold text-primary text-xs uppercase bg-primary/10 inline-block px-3 py-1 rounded-lg">{{ tutor.nombre_alumno }}</p>
                                </td>
                                <td>
                                    <div class="flex justify-center">
                                        <span class="inline-flex items-center justify-center min-w-[3rem] h-10 px-3 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-black text-lg shadow-sm">
                                            {{ tutor.asistencias }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rankingTutores.length === 0">
                                <td colspan="5">
                                    <div class="empty-state py-12">
                                        <div class="empty-state-icon bg-slate-50 dark:bg-slate-800/50 text-slate-300 dark:text-slate-600">
                                            <i class="fas fa-medal text-4xl"></i>
                                        </div>
                                        <p class="text-slate-500 dark:text-slate-400 font-bold text-lg mt-4">Aún no hay tutores en el ranking</p>
                                        <p class="text-slate-400 dark:text-slate-500 text-sm mt-1">Registra asistencias de tutores para ver el Top 20.</p>
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
