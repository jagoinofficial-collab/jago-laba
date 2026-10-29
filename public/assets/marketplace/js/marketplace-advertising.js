/**
 * JAGO LABA — Marketplace Advertising Center JavaScript
 * Shopee Ads + TikTok Ads + GMV Max
 * Versi: 2026-10-29
 */

document.addEventListener('DOMContentLoaded', function () {
    initAdsPerformanceChart();
    initAdsTooltips();
});

/**
 * Inisialisasi Grafik Performa Biaya vs Pendapatan Iklan
 */
function initAdsPerformanceChart() {
    const canvas = document.getElementById('adsPerformanceCanvas');
    if (!canvas) return;

    // Pastikan Chart.js tersedia
    if (typeof Chart === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        script.onload = () => renderChart(canvas);
        document.head.appendChild(script);
    } else {
        renderChart(canvas);
    }
}

function renderChart(canvas) {
    const rawDataEl = document.getElementById('adsChartDataJson');
    let chartData = {
        labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        spend: [0, 0, 0, 0, 0, 0, 0],
        revenue: [0, 0, 0, 0, 0, 0, 0]
    };

    if (rawDataEl) {
        try {
            const parsed = JSON.parse(rawDataEl.textContent);
            if (parsed && Array.isArray(parsed.labels) && parsed.labels.length > 0) {
                chartData = parsed;
            }
        } catch (e) {
            console.warn('Gagal membaca data JSON grafik iklan:', e);
        }
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    if (window.jlAdsChartInstance) {
        window.jlAdsChartInstance.destroy();
    }

    window.jlAdsChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Pendapatan Teratribusi (Rp)',
                    data: chartData.revenue,
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderColor: '#2563eb',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    order: 2
                },
                {
                    label: 'Biaya Iklan (Rp)',
                    data: chartData.spend,
                    backgroundColor: 'rgba(225, 29, 72, 0.75)',
                    borderColor: '#e11d48',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    order: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        boxHeight: 12,
                        usePointStyle: true,
                        font: {
                            family: 'Inter, sans-serif',
                            size: 11,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += 'Rp ' + Number(context.parsed.y).toLocaleString('id-ID');
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Inter, sans-serif', size: 10 }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(226, 232, 240, 0.6)' },
                    ticks: {
                        font: { family: 'Inter, sans-serif', size: 10 },
                        callback: function (val) {
                            if (val >= 1000000) return 'Rp ' + (val / 1000000).toFixed(1) + 'M';
                            if (val >= 1000) return 'Rp ' + (val / 1000).toFixed(0) + 'k';
                            return 'Rp ' + val;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Tooltip Helper
 */
function initAdsTooltips() {
    document.querySelectorAll('.jl-ads-kpi-info-trigger').forEach(trigger => {
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            const text = this.getAttribute('title') || this.getAttribute('data-tooltip');
            if (text) {
                alert(text);
            }
        });
    });
}

/**
 * Filter Switcher Tab Iklan
 */
function switchAdsTab(subTab) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'marketing');
    url.searchParams.set('mkt_sub', subTab);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

/**
 * Toggle Demo Simulation Mode
 */
function toggleAdsDemoMode() {
    const url = new URL(window.location.href);
    const isDemo = url.searchParams.get('mkt_demo') === '1';
    if (isDemo) {
        url.searchParams.delete('mkt_demo');
    } else {
        url.searchParams.set('mkt_demo', '1');
    }
    window.location.href = url.toString();
}

/**
 * Detail Drawer Iklan Produk
 */
function openProductAdDetail(p) {
    const modal = document.getElementById('productAdModal');
    if (!modal) return;

    const titleEl = document.getElementById('adModalTitle');
    const skuEl = document.getElementById('adModalSku');
    const bodyEl = document.getElementById('adModalBody');

    if (titleEl) titleEl.textContent = p.product_name || 'Detail Iklan Produk';
    if (skuEl) skuEl.textContent = 'SKU: ' + (p.sku || '-') + ' · ID: ' + (p.marketplace_item_id || '-');

    const isAman = p.is_aman;
    const isLoss = (p.net_profit || 0) < 0;

    if (bodyEl) {
        bodyEl.innerHTML = `
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                <img src="${p.image_url || 'assets/images/default-product.png'}" class="w-12 h-12 rounded-lg object-cover bg-white border border-slate-200 shrink-0" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'48\\' height=\\'48\\' fill=\\'%23cbd5e1\\'><rect width=\\'48\\' height=\\'48\\'/></svg>'">
                <div class="flex-1 min-w-0">
                    <span class="font-bold text-slate-900 block truncate">${p.product_name || '-'}</span>
                    <span class="text-[11px] text-slate-500">${p.orders_count || 0} Pesanan (${p.qty_sold || 0} Unit) · Biaya/Pesanan: Rp ${Number(p.cost_per_order || 0).toLocaleString('id-ID')}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 text-center">
                <div class="p-3 bg-rose-50 border border-rose-100 rounded-xl">
                    <span class="text-slate-500 block text-[11px]">Biaya Iklan</span>
                    <span class="font-extrabold text-rose-600 text-base">Rp ${Number(p.ad_spend || 0).toLocaleString('id-ID')}</span>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl">
                    <span class="text-slate-500 block text-[11px]">Pendapatan Teratribusi</span>
                    <span class="font-extrabold text-blue-600 text-base">Rp ${Number(p.gross_revenue || 0).toLocaleString('id-ID')}</span>
                </div>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5 text-xs">
                <div class="flex justify-between font-medium">
                    <span class="text-slate-600">Gross ROAS</span>
                    <span class="font-bold text-slate-900">${Number(p.gross_roas || 0).toFixed(2)}x</span>
                </div>
                <div class="flex justify-between font-medium">
                    <span class="text-slate-600">Net ROAS (Setelah Potongan Platform)</span>
                    <span class="font-bold text-slate-700">${Number(p.net_roas || 0).toFixed(2)}x</span>
                </div>
                <div class="flex justify-between font-medium">
                    <span class="text-slate-600">Actual ROAS (Setelah HPP Konveksi)</span>
                    <span class="font-bold text-slate-800">${Number(p.actual_roas || 0).toFixed(2)}x</span>
                </div>
                <div class="flex justify-between font-medium pt-1 border-t border-slate-200">
                    <span class="text-slate-600">Min. ROAS (Titik Impas)</span>
                    <span class="font-bold ${isAman ? 'text-emerald-600' : 'text-rose-600'}">${Number(p.min_roas || 0).toFixed(2)}x (${isAman ? '✓ AMAN' : '✗ RUGI'})</span>
                </div>
            </div>

            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 space-y-1 text-xs">
                <div class="flex justify-between font-semibold">
                    <span class="text-emerald-900">Profit Riil Sebelum Iklan</span>
                    <span class="text-emerald-700 font-bold">Rp ${Number(p.profit_riil || 0).toLocaleString('id-ID')} (${p.profit_riil_pct || 0}%)</span>
                </div>
                <div class="flex justify-between font-semibold">
                    <span class="text-rose-800">Beban Belanja Iklan</span>
                    <span class="text-rose-600 font-bold">-Rp ${Number(p.ad_spend || 0).toLocaleString('id-ID')}</span>
                </div>
                <div class="flex justify-between text-sm font-extrabold pt-1.5 border-t border-emerald-200">
                    <span class="text-slate-900">Net Profit Akhir</span>
                    <span class="${isLoss ? 'text-rose-600' : 'text-emerald-700'}">${isLoss ? '-' : ''}Rp ${Math.abs(p.net_profit || 0).toLocaleString('id-ID')} (${p.net_profit_pct || 0}%)</span>
                </div>
            </div>
        `;
    }

    if (typeof openModal === 'function') {
        openModal('productAdModal');
    } else {
        modal.classList.remove('hidden');
    }
}

