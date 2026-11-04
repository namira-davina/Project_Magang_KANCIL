document.addEventListener('DOMContentLoaded', function () {
    const barCanvas = document.getElementById('laporanBarChartCanvas');
    const btnExportExcel = document.getElementById('btnExportExcel');
    const btnExportPDF = document.getElementById('btnExportPDF');
    const btnTampilkan = document.getElementById('btnTampilkanLaporan');

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt'];
    const values = [820, 910, 990, 870, 1040, 1120, 1080, 1190, 1150, 1250];

    // Background color: lighter blue for Jan-Sep, dark navy for Okt
    const barColors = [
        '#b3cbe8', '#b3cbe8', '#b3cbe8', '#b3cbe8', '#b3cbe8',
        '#b3cbe8', '#b3cbe8', '#b3cbe8', '#b3cbe8', '#0b1a30'
    ];

    let laporanChart = null;

    if (barCanvas && typeof Chart !== 'undefined') {
        // Plugin to draw value above each bar
        const topLabelsPlugin = {
            id: 'topLabelsPlugin',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    meta.data.forEach((bar, index) => {
                        const val = dataset.data[index];
                        const formattedVal = val.toLocaleString('id-ID');
                        ctx.fillStyle = '#64748b';
                        ctx.font = '500 11px Poppins, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(formattedVal, bar.x, bar.y - 6);
                    });
                });
            }
        };

        laporanChart = new Chart(barCanvas, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Jumlah Pertanyaan',
                    data: values,
                    backgroundColor: barColors,
                    borderRadius: 3,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0b1a30',
                        titleFont: { family: 'Poppins', size: 12 },
                        bodyFont: { family: 'Poppins', size: 12 },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function (context) {
                                return ` ${context.parsed.y.toLocaleString('id-ID')} pertanyaan`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        display: false,
                        max: 1400
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: 'Poppins', size: 12, weight: '500' },
                            color: '#475569'
                        }
                    }
                }
            },
            plugins: [topLabelsPlugin]
        });
    }

    if (btnTampilkan) {
        btnTampilkan.addEventListener('click', function () {
            const bulan = document.getElementById('filterBulan')?.value || 'Oktober';
            const tahun = document.getElementById('filterTahun')?.value || '2025';
            alert(`Laporan untuk periode ${bulan} ${tahun} berhasil diperbarui.`);
        });
    }

    // Export Excel using SheetJS
    if (btnExportExcel && typeof XLSX !== 'undefined') {
        btnExportExcel.addEventListener('click', function () {
            const table = document.getElementById('tableFaqLaporan');
            if (!table) return;

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(table);

            XLSX.utils.book_append_sheet(wb, ws, 'FAQ Populer');
            XLSX.writeFile(wb, 'Laporan-KANCIL-SAPA.xlsx');
        });
    }

    // Export PDF using jsPDF
    if (btnExportPDF && typeof window.jspdf !== 'undefined') {
        btnExportPDF.addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');

            doc.setFontSize(16);
            doc.text('Laporan Bulanan KANCIL SAPA', 14, 18);
            doc.setFontSize(10);
            doc.text('Kantor Imigrasi Kelas I TPI Cilacap - Rekap Layanan Informasi', 14, 25);

            if (typeof doc.autoTable === 'function') {
                doc.autoTable({
                    html: '#tableFaqLaporan',
                    startY: 32,
                    styles: { font: 'helvetica', fontSize: 9 },
                    headStyles: { fillColor: [11, 26, 48], textColor: 255 }
                });
            }

            doc.save('Laporan-KANCIL-SAPA.pdf');
        });
    }
});
