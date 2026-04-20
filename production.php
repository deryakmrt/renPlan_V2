<?php

/**
 * production.php — Günlük Faaliyet ve Canlı Üretim Raporu
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_login();

$db = pdo();

// --- 1. CANLI ÜRETİM YÜKÜ MOTORU ---
$sqlStats = "SELECT o.status, oi.name, p.sku, SUM(oi.qty) as total_qty FROM order_items oi JOIN orders o ON oi.order_id = o.id LEFT JOIN products p ON oi.product_id = p.id WHERE o.status IN ('tedarik','sac_lazer','sac lazer','boru_lazer','boru lazer','kaynak','boya','elektrik_montaj','elektrik montaj','test','paketleme') GROUP BY o.status, p.sku, oi.name";
$rowsS = $db->query($sqlStats)->fetchAll(PDO::FETCH_ASSOC);

$data_tedarik = [];
$data_uretim = [];
$total_tedarik_qty = 0;
$total_uretim_qty = 0;
$production_steps = ['sac_lazer', 'sac lazer', 'boru_lazer', 'boru lazer', 'kaynak', 'boya', 'elektrik_montaj', 'elektrik montaj', 'test', 'paketleme'];

foreach ($rowsS as $r) {
    $status = $r['status'];
    $qty = (float)$r['total_qty'];
    $raw_sku = trim($r['sku'] ?? '');
    $raw_name = trim($r['name'] ?? '');
    if (empty($raw_sku) && strpos($raw_name, 'RN') === 0) {
        $parts = explode(' ', $raw_name);
        $raw_sku = $parts[0];
    }
    $family_code = 'DİĞER';
    if (!empty($raw_sku)) {
        if (strpos($raw_sku, 'RN-MLS-RAY') === 0) {
            if (strpos($raw_sku, 'TR') !== false) $family_code = 'RN-MLS-RAY (TR)';
            elseif (strpos($raw_sku, 'SR') !== false) $family_code = 'RN-MLS-RAY (SR)';
            elseif (strpos($raw_sku, 'SU') !== false) $family_code = 'RN-MLS-RAY (SU)';
            elseif (strpos($raw_sku, 'SA') !== false) $family_code = 'RN-MLS-RAY (SA)';
            else $family_code = 'RN-MLS-RAY';
        } else {
            $parts = explode('-', $raw_sku);
            $family_code = (count($parts) >= 2) ? ($parts[0] . '-' . $parts[1]) : $raw_sku;
        }
    }
    if ($status === 'tedarik') {
        $data_tedarik[$family_code] = ($data_tedarik[$family_code] ?? 0) + $qty;
        $total_tedarik_qty += $qty;
    } elseif (in_array($status, $production_steps)) {
        $data_uretim[$family_code] = ($data_uretim[$family_code] ?? 0) + $qty;
        $total_uretim_qty += $qty;
    }
}

arsort($data_tedarik);
arsort($data_uretim);
$json_tedarik = json_encode(['labels' => array_keys($data_tedarik), 'data' => array_values($data_tedarik)], JSON_UNESCAPED_UNICODE);
$json_uretim = json_encode(['labels' => array_keys($data_uretim), 'data' => array_values($data_uretim)], JSON_UNESCAPED_UNICODE);

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/assets/reports.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<div class="container-card">
    <div class="container-card" style="margin-bottom: 24px; background: #f0fdf4; border-color: #bbf7d0;">
        <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #166534;">📅 Günlük Faaliyet Raporları</h3>
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <?php for ($i = 0; $i < 5; $i++): $d = date('Y-m-d', strtotime("-$i days"));
                $label = ($i === 0) ? 'Bugün' : (($i === 1) ? 'Dün' : date('d.m', strtotime($d))); ?>
                <a href="/report_daily_print.php?date=<?= $d ?>" target="_blank" class="btn" style="background:#fff; border-color:#86efac; color:#14532d;">📄 <?= $label ?> <small>(<?= date('d.m', strtotime($d)) ?>)</small></a>
            <?php endfor; ?>
            <form action="/report_daily_print.php" method="get" target="_blank" style="display:flex; gap:5px; border-left:1px solid #bbf7d0; padding-left:15px;">
                <input type="date" name="date" class="input" style="padding:5px; width:130px;" required>
                <button type="submit" class="btn btn-primary" style="padding:5px 10px;">Raporla</button>
            </form>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:25px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; color:#312e81; font-size:20px; font-weight:800; display:flex; align-items:center; gap:10px;">
                🏭 Canlı Üretim Sahası
                <span style="background:#e0e7ff; color:#3730a3; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:normal;">Anlık Adet Yükü</span>
            </h3>
            <div style="font-size:12px; color:#999;">
                <span style="display:inline-block; width:8px; height:8px; background:#22c55e; border-radius:50%; margin-right:5px;"></span>
                Canlı Veri
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">

            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:20px; position:relative; overflow:hidden;">
                <div style="position:absolute; top:-10px; right:-10px; font-size:80px; opacity:0.05; color:#15803d;">⏳</div>
                <div style="text-align:center; margin-bottom:15px; position:relative; z-index:2;">
                    <h4 style="margin:0; color:#166534; font-size:15px; text-transform:uppercase; letter-spacing:0.5px;">Üretime Girecekler</h4>
                    <div style="font-size:13px; color:#15803d; opacity:0.8;">(Tedarik Aşaması)</div>
                    <div style="font-size:28px; font-weight:800; color:#15803d; margin-top:5px; text-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <?= number_format($total_tedarik_qty, 0, ',', '.') ?> <span style="font-size:14px;font-weight:600;">Adet</span>
                    </div>
                </div>
                <div style="height:250px; position:relative; z-index:2;">
                    <canvas id="chartTedarik"></canvas>
                </div>
            </div>

            <div style="background:#fff7ed; border:1px solid #ffedd5; border-radius:12px; padding:20px; position:relative; overflow:hidden;">
                <div style="position:absolute; top:-10px; right:-10px; font-size:80px; opacity:0.05; color:#c2410c;">⚙️</div>
                <div style="text-align:center; margin-bottom:15px; position:relative; z-index:2;">
                    <h4 style="margin:0; color:#9a3412; font-size:15px; text-transform:uppercase; letter-spacing:0.5px;">Üretimde Olanlar</h4>
                    <div style="font-size:13px; color:#c2410c; opacity:0.8;">(Lazer'den Paketlemeye)</div>
                    <div style="font-size:28px; font-weight:800; color:#c2410c; margin-top:5px; text-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <?= number_format($total_uretim_qty, 0, ',', '.') ?> <span style="font-size:14px;font-weight:600;">Adet</span>
                    </div>
                </div>
                <div style="height:250px; position:relative; z-index:2;">
                    <canvas id="chartUretim"></canvas>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dTedarik = <?= $json_tedarik ?>;
        const dUretim = <?= $json_uretim ?>;

        function generateColors(count, hueStart) {
            let colors = [];
            for (let i = 0; i < count; i++) {
                let hue = (hueStart + (i * 45)) % 360;
                colors.push(`hsl(${hue}, 70%, 55%)`);
            }
            return colors;
        }

        function renderChart(id, dataObj, startHue) {
            const canvas = document.getElementById(id);
            const container = canvas.parentNode;

            if (dataObj.data.length === 0) {
                container.innerHTML = "<div style='display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#94a3b8;font-style:italic;'><div style='font-size:24px;margin-bottom:5px;'>∅</div>Veri Yok</div>";
                return;
            }

            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: dataObj.labels,
                    datasets: [{
                        data: dataObj.data,
                        backgroundColor: generateColors(dataObj.data.length, startHue),
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                },
                                padding: 15
                            },
                            onClick: function(e, legendItem, legend) {
                                const index = legendItem.index;
                                const ci = legend.chart;
                                ci.toggleDataVisibility(index);
                                ci.update();

                                let visibleTotal = 0;
                                ci.data.datasets[0].data.forEach((val, i) => {
                                    if (ci.getDataVisibility(i) !== false) {
                                        visibleTotal += parseFloat(val);
                                    }
                                });

                                const infoDiv = ci.canvas.parentElement.previousElementSibling;
                                if (infoDiv && infoDiv.lastElementChild) {
                                    infoDiv.lastElementChild.innerHTML = visibleTotal.toLocaleString('tr-TR') + ' <span style="font-size:14px;font-weight:600;">Adet</span>';
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#1e293b',
                            bodyColor: '#1e293b',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.parsed;
                                    return ' ' + label + ': ' + value + ' Adet';
                                }
                            }
                        }
                    },
                    layout: {
                        padding: 10
                    }
                }
            });
        }

        renderChart('chartTedarik', dTedarik, 150);
        renderChart('chartUretim', dUretim, 25);
    });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>