<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/order_stats.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/order_stats.css">
<title>Аналітика</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<?php 
    require_once __DIR__ . '/../Controller/OrderStatsController.php';
    include __DIR__ . '/header.php';
?>

</head>
<body>

<div class="container">
<h1>Аналітика</h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
  
  <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Доход</div>
    <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?= number_format($totalIncome, 2) ?></div>
    <div style="font-size: 14px; opacity: 0.9;">від клієнтів</div>
  </div>

  <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Видатки</div>
    <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?= number_format($totalExpense, 2) ?></div>
    <div style="font-size: 14px; opacity: 0.9;">на закупки</div>
  </div>

  <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Прибуток</div>
    <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px; color: <?= $profit >= 0 ? '#fff' : '#ffcccc' ?>"><?= number_format($profit, 2) ?></div>
    <div style="font-size: 14px; opacity: 0.9;"><?= $profit >= 0 ? '✓ Позитивна' : '✗ Від\'ємна' ?></div>
  </div>

  <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Замовлень</div>
    <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?= $ordersCount ?></div>
    <div style="font-size: 14px; opacity: 0.9;">від клієнтів</div>
  </div>

  <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Закупок</div>
    <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?= $purchasesCount ?></div>
    <div style="font-size: 14px; opacity: 0.9;">у теплицях</div>
  </div>

  <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center;">
    <div style="font-size: 12px; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Рентабельність</div>
    <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?= $totalIncome > 0 ? number_format(($profit / $totalIncome) * 100, 1) : '0' ?>%</div>
    <div style="font-size: 14px; opacity: 0.7;">прибутку від доходу</div>
  </div>

</div>

<div class="card">
<form method="GET">
    <input type="date" name="date_from" value="<?= $date_from ?>">
    <input type="date" name="date_to" value="<?= $date_to ?>">

    <select name="product_id">
        <option value="">Всі квіти</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>>
                <?= $p['flower_name'] ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button>Фільтр</button>
</form>
</div>

<div class="card">
<h3>Статистика по квітам</h3>
<table>
<tr>
    <th>Квітка</th>
    <th>Кількість</th>
    <th>Сума</th>
</tr>

<?php foreach ($stats as $row): ?>
<tr>
    <td><?= $row['flower_name'] ?></td>
    <td><?= $row['total_quantity'] ?></td>
    <td><?= number_format($row['total_sum'],2) ?> грн</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h3>Доходи та Видатки - Динаміка</h3>
<p style="color: #666; font-size: 14px; margin-bottom: 15px;">Порівняння щоденних доходів від продажу квітів та видатків на закупки</p>
<canvas id="chart"></canvas>
</div>

<div class="card">
<h3>Доходи і Видатки по дням</h3>
<table class="analytics-table">
<thead>
<tr>
    <th>Дата</th>
    <th style="text-align: right;">Доход</th>
    <th style="text-align: right;">Видатки</th>
    <th style="text-align: right;">Прибуток</th>
</tr>
</thead>
<tbody>
<?php foreach ($dailyData as $row): 
    $profit = $row['income'] - $row['expense'];
    $profitClass = $profit >= 0 ? 'profit-positive' : 'profit-negative';
?>
<tr>
    <td><?= $row['date'] ?></td>
    <td class="income-cell"><?= number_format($row['income'], 2) ?> грн</td>
    <td class="expense-cell"><?= number_format($row['expense'], 2) ?> грн</td>
    <td class="profit-cell <?= $profitClass ?>"><?= number_format($profit, 2) ?> грн</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="card">
<h3>👤 Топ клієнтів</h3>
<table>
<tr>
    <th>Ім’я</th>
    <th>Замовлення</th>
</tr>

<?php foreach ($topClients as $c): ?>
<tr>
    <td><?= $c['name']." ".$c['lastname'] ?></td>
    <td><?= $c['orders_count'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<div class="card">
<h3>Доходи vs Видатки</h3>
<p style="color: #666; font-size: 14px; margin-bottom: 15px;">Загальне порівняння доходів, видатків та прибутку</p>
<canvas id="incomeExpenseChart"></canvas>
</div>
</div>
<?php include __DIR__ . '/footer.php'; ?>

<script>
// Граф Доходи і Видатки по дням
new Chart(document.getElementById('chart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dailyData,'date')) ?>,
        datasets: [
            {
                label: 'Доход від клієнтів',
                data: <?= json_encode(array_column($dailyData,'income')) ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointRadius: 6,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 8
            },
            {
                label: 'Видатки на закупки',
                data: <?= json_encode(array_column($dailyData,'expense')) ?>,
                borderColor: '#f5576c',
                backgroundColor: 'rgba(245, 87, 108, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointRadius: 6,
                pointBackgroundColor: '#f5576c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 8
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            },
            filler: {
                propagate: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('uk-UA') + ' грн';
                    }
                }
            }
        }
    }
});

// Чарт Доходи таВидатки 
new Chart(document.getElementById('incomeExpenseChart'), {
    type: 'bar',
    data: {
        labels: ['Доход від клієнтів', 'Видатки на закупки', 'Прибуток'],
        datasets: [{
            label: 'Сума (грн)',
            data: [
                <?= $totalIncome ?>,
                <?= $totalExpense ?>,
                <?= $profit ?>
            ],
            backgroundColor: [
                'rgba(102, 126, 234, 0.8)',
                'rgba(245, 87, 108, 0.8)',
                '<?= $profit >= 0 ? "rgba(67, 233, 123, 0.8)" : "rgba(255, 107, 107, 0.8)" ?>'
            ],
            borderColor: [
                '#667eea',
                '#f5576c',
                '<?= $profit >= 0 ? "#43e97b" : "#ff6b6b" ?>'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>