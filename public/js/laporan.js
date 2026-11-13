document.addEventListener('DOMContentLoaded', function () {
    const barCanvas = document.getElementById('laporanBarChartCanvas');
    const btnExportExcel = document.getElementById('btnExportExcel');
    const btnExportPDF = document.getElementById('btnExportPDF');
    const btnTampilkan = document.getElementById('btnTampilkanLaporan');

    const data = window.KANCIL_LAPORAN || { labels: [], values: [], selectedIndex: -1, periodLabel: '', url: '' };
    const months = data.labels;
    const values = data.values;
    const fileSuffix = (data.periodLabel || '').replace(/\s+/g, '-');

    // Bulan terpilih navy gelap, bulan lain biru muda
    const barColors = months.map((_, i) => i === data.selectedIndex ? '#0b1a30' : '#b3cbe8');
    const maxValue = Math.max(5, ...values);

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
                        max: Math.ceil(maxValue * 1.15)
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
            const bulan = document.getElementById('filterBulan')?.value;
            const tahun = document.getElementById('filterTahun')?.value;
            const params = new URLSearchParams({ bulan: bulan, tahun: tahun });
            window.location.href = `${data.url}?${params.toString()}`;
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

            const tableEskalasi = document.getElementById('tableEskalasiLaporan');
            if (tableEskalasi) {
                XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(tableEskalasi), 'Eskalasi per Kode');
            }

            XLSX.writeFile(wb, `Laporan-KANCIL-SAPA-${fileSuffix}.xlsx`);
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
            doc.text(`Kantor Imigrasi Kelas I TPI Cilacap - Rekap Layanan Informasi (${data.periodLabel})`, 14, 25);

            if (typeof doc.autoTable === 'function') {
                doc.autoTable({
                    html: '#tableFaqLaporan',
                    startY: 32,
                    styles: { font: 'helvetica', fontSize: 9 },
                    headStyles: { fillColor: [11, 26, 48], textColor: 255 }
                });

                if (document.getElementById('tableEskalasiLaporan')) {
                    doc.autoTable({
                        html: '#tableEskalasiLaporan',
                        startY: doc.lastAutoTable.finalY + 10,
                        styles: { font: 'helvetica', fontSize: 9 },
                        headStyles: { fillColor: [11, 26, 48], textColor: 255 }
                    });
                }
            }

            doc.save(`Laporan-KANCIL-SAPA-${fileSuffix}.pdf`);
        });
    }
});
