<?php
require 'config.php'; require_admin();

/* ── Date filter params ─────────────────────────────────────────────── */
$report = $_GET['report'] ?? 'sales';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];
$toEnd = $to . ' 23:59:59';

/* ── Run queries based on active report ─────────────────────────────── */

if ($report === 'sales') {

    $kpi = $pdo->prepare("
        SELECT COUNT(*) AS total_orders,
               COALESCE(SUM(total_amount),0) AS total_revenue,
               COALESCE(AVG(total_amount),0) AS avg_order,
               COALESCE(MAX(total_amount),0) AS max_order,
               SUM(payment_status='Confirmed') AS paid,
               SUM(order_status='Delivered')   AS delivered,
               SUM(order_status='Cancelled')   AS cancelled
        FROM orders WHERE created_at BETWEEN ? AND ?
    ");
    $kpi->execute([$from, $toEnd]); $kpi = $kpi->fetch();

    $salesTrend = $pdo->prepare("
        SELECT DATE(created_at) AS day,
               DATE_FORMAT(created_at,'%d %b') AS label,
               COUNT(*) AS orders,
               SUM(total_amount) AS revenue
        FROM orders
        WHERE created_at BETWEEN ? AND ? AND order_status <> 'Cancelled'
        GROUP BY DATE(created_at) ORDER BY day
    ");
    $salesTrend->execute([$from, $toEnd]); $salesTrend = $salesTrend->fetchAll();

    $byPayment = $pdo->prepare("
        SELECT payment_method, COUNT(*) AS cnt, SUM(total_amount) AS revenue
        FROM orders WHERE created_at BETWEEN ? AND ? AND order_status <> 'Cancelled'
        GROUP BY payment_method ORDER BY revenue DESC
    ");
    $byPayment->execute([$from, $toEnd]); $byPayment = $byPayment->fetchAll();

    $byCategory = $pdo->prepare("
        SELECT COALESCE(p.category,'Unknown') AS category,
               SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
        FROM orders o
        JOIN order_items oi ON oi.order_id=o.id
        LEFT JOIN products p ON p.id=oi.product_id
        WHERE o.created_at BETWEEN ? AND ? AND o.order_status <> 'Cancelled'
        GROUP BY p.category ORDER BY revenue DESC
    ");
    $byCategory->execute([$from, $toEnd]); $byCategory = $byCategory->fetchAll();

    if ($export === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sales_'.$from.'_'.$to.'.csv"');
        $f = fopen('php://output','w');
        fputcsv($f,['Date','Orders','Revenue (UGX)']);
        foreach ($salesTrend as $r) fputcsv($f,[$r['label'],$r['orders'],$r['revenue']]);
        fclose($f); exit;
    }

} elseif ($report === 'orders') {

    $ordersData = $pdo->prepare("
        SELECT o.*, u.full_name
        FROM orders o LEFT JOIN users u ON o.user_id=u.id
        WHERE o.created_at BETWEEN ? AND ?
        ORDER BY o.created_at DESC
    ");
    $ordersData->execute([$from, $toEnd]); $ordersData = $ordersData->fetchAll();

    $statusBreak = $pdo->prepare("
        SELECT order_status, COUNT(*) AS cnt, SUM(total_amount) AS rev
        FROM orders WHERE created_at BETWEEN ? AND ?
        GROUP BY order_status ORDER BY cnt DESC
    ");
    $statusBreak->execute([$from, $toEnd]); $statusBreak = $statusBreak->fetchAll();

    $payBreak = $pdo->prepare("
        SELECT payment_status, COUNT(*) AS cnt, SUM(total_amount) AS total
        FROM orders WHERE created_at BETWEEN ? AND ?
        GROUP BY payment_status ORDER BY cnt DESC
    ");
    $payBreak->execute([$from, $toEnd]); $payBreak = $payBreak->fetchAll();

    if ($export === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="orders_'.$from.'_'.$to.'.csv"');
        $f = fopen('php://output','w');
        fputcsv($f,['#','Date','Customer','Phone','Payment Method','Payment Status','Order Status','Amount (UGX)']);
        foreach ($ordersData as $r) fputcsv($f,[$r['id'],date('d M Y',strtotime($r['created_at'])),$r['full_name']??$r['customer_name'],$r['phone'],$r['payment_method'],$r['payment_status'],$r['order_status'],$r['total_amount']]);
        fclose($f); exit;
    }

} elseif ($report === 'products') {

    $bestSellers = $pdo->prepare("
        SELECT oi.product_name,
               COALESCE(p.category,'Unknown') AS category,
               SUM(oi.quantity) AS units,
               SUM(oi.line_total) AS revenue,
               COALESCE(p.stock_quantity,0) AS stock,
               COALESCE(p.price,oi.unit_price) AS price
        FROM order_items oi
        LEFT JOIN products p ON p.id=oi.product_id
        JOIN orders o ON o.id=oi.order_id
        WHERE o.created_at BETWEEN ? AND ? AND o.order_status <> 'Cancelled'
        GROUP BY oi.product_name ORDER BY units DESC
    ");
    $bestSellers->execute([$from, $toEnd]); $bestSellers = $bestSellers->fetchAll();

    $inventory = $pdo->query("
        SELECT name, category, price, stock_quantity,
               (price*stock_quantity) AS stock_value, active
        FROM products ORDER BY category, name
    ")->fetchAll();

    $invSummary = $pdo->query("
        SELECT SUM(active=1) AS active_count,
               SUM(stock_quantity=0 AND active=1) AS out_of_stock,
               SUM(stock_quantity<=5 AND active=1) AS low_stock,
               COALESCE(SUM(price*stock_quantity),0) AS total_value
        FROM products
    ")->fetch();

    if ($export === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="products_'.$from.'_'.$to.'.csv"');
        $f = fopen('php://output','w');
        fputcsv($f,['Product','Category','Units Sold','Revenue (UGX)','Stock Left','Unit Price']);
        foreach ($bestSellers as $r) fputcsv($f,[$r['product_name'],$r['category'],$r['units'],$r['revenue'],$r['stock'],$r['price']]);
        fclose($f); exit;
    }

} elseif ($report === 'customers') {

    $topCustomers = $pdo->prepare("
        SELECT u.full_name, u.email, u.phone, u.created_at AS joined,
               COUNT(o.id) AS order_count,
               COALESCE(SUM(o.total_amount),0) AS total_spent,
               MAX(o.created_at) AS last_order
        FROM users u
        LEFT JOIN orders o ON o.user_id=u.id AND o.created_at BETWEEN ? AND ?
        WHERE u.role='customer'
        GROUP BY u.id ORDER BY total_spent DESC
    ");
    $topCustomers->execute([$from, $toEnd]); $topCustomers = $topCustomers->fetchAll();

    $custSummary = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) AS total,
               COUNT(DISTINCT o.user_id) AS active
        FROM users u
        LEFT JOIN orders o ON o.user_id=u.id AND o.created_at BETWEEN ? AND ?
        WHERE u.role='customer'
    ");
    $custSummary->execute([$from, $toEnd]); $custSummary = $custSummary->fetch();

    if ($export === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers_'.$from.'_'.$to.'.csv"');
        $f = fopen('php://output','w');
        fputcsv($f,['Name','Email','Phone','Joined','Orders','Total Spent (UGX)','Last Order']);
        foreach ($topCustomers as $r) fputcsv($f,[$r['full_name'],$r['email'],$r['phone'],date('d M Y',strtotime($r['joined'])),$r['order_count'],$r['total_spent'],$r['last_order']?date('d M Y',strtotime($r['last_order'])):'-']);
        fclose($f); exit;
    }
}

$catIcons = ['Improved Seeds'=>'🌽','Fertilizers'=>'🌿','Pesticides'=>'🛡️','Animal Feeds'=>'🐄','Veterinary Supplies'=>'🐾','Farm Tools'=>'🧰'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reports | Agro Input Admin</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    @media print { .no-print{display:none!important} body{background:#fff!important} }
    .rpt-wrap { width:min(1280px,98%); margin:20px auto; padding:0 10px; }
    .rpt-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
    .rpt-tab  { padding:10px 22px; border-radius:6px; font:600 .88rem Inter,sans-serif; border:1.5px solid #ddd; background:#fff; color:#555; text-decoration:none; transition:all .15s; }
    .rpt-tab:hover { border-color:#1565c0; color:#1565c0; text-decoration:none; }
    .rpt-tab.active { background:#1565c0; color:#fff; border-color:#1565c0; }
    .kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
    .kpi { background:#fff; border:1px solid #e8e8e8; border-radius:8px; padding:20px 16px; text-align:center; }
    .kpi.blue  { border-top:3px solid #1565c0; }
    .kpi.green { border-top:3px solid #3cb878; }
    .kpi.amber { border-top:3px solid #f59e0b; }
    .kpi.red   { border-top:3px solid #e02020; }
    .kpi-val { font:800 1.6rem Inter,sans-serif; color:#1565c0; margin:8px 0 4px; }
    .kpi-val.green { color:#1a6b3a; }
    .kpi-val.amber { color:#b36000; }
    .kpi-val.red   { color:#b71c1c; }
    .kpi-lbl { font:600 .75rem Inter,sans-serif; color:#888; text-transform:uppercase; letter-spacing:.04em; }
    .kpi-sub { font-size:.76rem; color:#aaa; margin-top:3px; }
    .rc { background:#fff; border:1px solid #e8e8e8; border-radius:8px; margin-bottom:18px; overflow:hidden; }
    .rc-head { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; background:#fafafa; border-bottom:1px solid #f0f0f0; }
    .rc-head h3 { font:700 .95rem Inter,sans-serif; margin:0; }
    .rc-body { padding:20px; }
    .chart { display:flex; align-items:flex-end; gap:5px; height:130px; overflow-x:auto; padding-bottom:4px; }
    .bar { flex:0 0 auto; min-width:28px; background:#e3f2fd; border-radius:4px 4px 0 0; cursor:pointer; position:relative; transition:background .2s; }
    .bar:hover { background:#1565c0; }
    .bar:hover .tip { display:block; }
    .tip { display:none; position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%); background:#222; color:#fff; font-size:.68rem; padding:4px 8px; border-radius:4px; white-space:nowrap; z-index:9; pointer-events:none; }
    .tip::after { content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%); border:4px solid transparent; border-top-color:#222; }
    .chart-lbl { display:flex; gap:5px; overflow-x:auto; margin-top:6px; }
    .cl { flex:0 0 auto; min-width:28px; font-size:.62rem; color:#aaa; text-align:center; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .prog-wrap { background:#f0f0f0; border-radius:4px; height:9px; flex:1; }
    .prog { background:#1565c0; border-radius:4px; height:9px; min-width:4px; }
    .rt { width:100%; border-collapse:collapse; }
    .rt th { background:#f8f8f8; padding:10px 14px; font:600 .75rem Inter,sans-serif; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e8e8e8; text-align:left; white-space:nowrap; }
    .rt td { padding:10px 14px; border-bottom:1px solid #f4f4f4; font-size:.85rem; vertical-align:middle; }
    .rt tr:last-child td { border-bottom:none; }
    .rt tr:hover td { background:#fafcff; }
    .rank { width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font:700 .75rem Inter,sans-serif; }
    .r1{background:#ffd700;color:#7a5800} .r2{background:#c0c0c0;color:#444} .r3{background:#cd7f32;color:#fff} .rn{background:#f0f0f0;color:#888}
    .filter-bar { background:#fff; border:1px solid #e8e8e8; border-radius:8px; padding:16px 20px; margin-bottom:18px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
    .filter-bar label { font:600 .75rem Inter,sans-serif; color:#888; display:block; margin-bottom:5px; text-transform:uppercase; }
    .filter-bar input, .filter-bar select { padding:8px 10px; font-size:.88rem; border:1.5px solid #ddd; border-radius:6px; background:#fff; }
    .quick-dates { display:flex; gap:6px; flex-wrap:wrap; margin-left:auto; align-items:center; }
    .qd { padding:5px 12px; border-radius:20px; font:600 .75rem Inter,sans-serif; border:1px solid #ddd; background:#f5f5f5; color:#555; text-decoration:none; white-space:nowrap; }
    .qd:hover { border-color:#1565c0; color:#1565c0; text-decoration:none; }
    .qd.active { background:#e3f2fd; color:#0d47a1; border-color:#1565c0; }
    @media(max-width:700px) { .kpi-row{grid-template-columns:1fr 1fr;} }
    @media(max-width:480px) { .kpi-row{grid-template-columns:1fr;} }
  </style>
</head>
<body style="background:#f0f2f5;">

<!-- ── Top bar ──────────────────────────────────────────────────────── -->
<div class="no-print" style="background:#0d2a4a;color:#fff;position:sticky;top:0;z-index:200;box-shadow:0 2px 8px rgba(0,0,0,.2);">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:0 24px;min-height:56px;gap:14px;">
    <div style="display:flex;align-items:center;gap:14px;">
      <a href="admin.php" style="color:rgba(255,255,255,.6);font-size:.82rem;text-decoration:none;">← Dashboard</a>
      <span style="color:rgba(255,255,255,.2);">|</span>
      <span style="color:#fff;font:700 1rem Inter,sans-serif;">🌱 Agro Input <span style="color:#42a5f5;">Reports</span></span>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
      <?= user_avatar(current_user()['avatar'] ?? null, current_user()['full_name'], 30) ?>
      <span style="color:rgba(255,255,255,.7);font-size:.82rem;"><?= e(current_user()['full_name']) ?></span>
      <a href="logout.php" style="color:#f87171;font-size:.82rem;text-decoration:none;">Logout</a>
    </div>
  </div>
</div>

<div class="rpt-wrap">

  <!-- Page title + export buttons -->
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;margin-top:4px;">
    <div>
      <h1 style="font:800 1.4rem Inter,sans-serif;margin:0 0 4px;">📈 Report Generation</h1>
      <p style="color:#888;font-size:.83rem;margin:0;">
        Period: <strong><?= date('d M Y',strtotime($from)) ?></strong> — <strong><?= date('d M Y',strtotime($to)) ?></strong>
        &nbsp;·&nbsp; Generated: <?= date('d M Y H:i') ?> &nbsp;·&nbsp; By: <?= e(current_user()['full_name']) ?>
      </p>
    </div>
    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="?report=<?= e($report) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv"
         class="btn secondary" style="font-size:.82rem;padding:8px 16px;">📥 Export CSV</a>
      <button onclick="window.print()" class="btn secondary" style="font-size:.82rem;padding:8px 16px;">🖨️ Print</button>
      <a href="admin.php" class="btn secondary" style="font-size:.82rem;padding:8px 16px;">← Admin</a>
    </div>
  </div>

  <!-- Report type tabs -->
  <div class="rpt-tabs no-print">
    <?php foreach (['sales'=>'💰 Sales','orders'=>'🧾 Orders','products'=>'📦 Products','customers'=>'👥 Customers'] as $k=>$lbl): ?>
    <a href="?report=<?= $k ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"
       class="rpt-tab <?= $report===$k?'active':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Date filter -->
  <div class="filter-bar no-print">
    <form method="get" style="display:contents;">
      <input type="hidden" name="report" value="<?= e($report) ?>">
      <div>
        <label>From Date</label>
        <input type="date" name="from" value="<?= e($from) ?>">
      </div>
      <div>
        <label>To Date</label>
        <input type="date" name="to" value="<?= e($to) ?>">
      </div>
      <button class="btn" type="submit" style="padding:9px 20px;font-size:.88rem;align-self:flex-end;">🔍 Generate</button>
    </form>
    <!-- Quick date filters -->
    <div class="quick-dates">
      <?php
      $qdates = [
        'Today'      => [date('Y-m-d'),date('Y-m-d')],
        'This Week'  => [date('Y-m-d',strtotime('monday this week')),date('Y-m-d')],
        'This Month' => [date('Y-m-01'),date('Y-m-d')],
        'Last Month' => [date('Y-m-01',strtotime('first day of last month')),date('Y-m-t',strtotime('last day of last month'))],
        'This Year'  => [date('Y-01-01'),date('Y-m-d')],
        'All Time'   => ['2020-01-01',date('Y-m-d')],
      ];
      foreach ($qdates as $ql=>[$qf,$qt]):
        $isActive = $from===$qf && $to===$qt;
      ?>
      <a href="?report=<?= e($report) ?>&from=<?= $qf ?>&to=<?= $qt ?>"
         class="qd <?= $isActive?'active':'' ?>"><?= $ql ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php /* ══════════════ SALES REPORT ══════════════ */ ?>
  <?php if ($report === 'sales'): ?>

  <div class="kpi-row">
    <div class="kpi blue">
      <div class="kpi-lbl">Total Revenue</div>
      <div class="kpi-val"><?= money($kpi['total_revenue']) ?></div>
      <div class="kpi-sub"><?= number_format((int)$kpi['total_orders']) ?> orders</div>
    </div>
    <div class="kpi green">
      <div class="kpi-lbl">Avg Order Value</div>
      <div class="kpi-val green"><?= money($kpi['avg_order']) ?></div>
      <div class="kpi-sub">per order</div>
    </div>
    <div class="kpi amber">
      <div class="kpi-lbl">Payments Confirmed</div>
      <div class="kpi-val amber"><?= number_format((int)$kpi['paid']) ?></div>
      <div class="kpi-sub">of <?= number_format((int)$kpi['total_orders']) ?> orders</div>
    </div>
    <div class="kpi red">
      <div class="kpi-lbl">Cancelled Orders</div>
      <div class="kpi-val red"><?= number_format((int)$kpi['cancelled']) ?></div>
      <div class="kpi-sub"><?= number_format((int)$kpi['delivered']) ?> delivered</div>
    </div>
  </div>

  <!-- Revenue trend chart -->
  <div class="rc">
    <div class="rc-head"><h3>📊 Revenue Trend (Daily)</h3>
      <span style="color:#888;font-size:.8rem;"><?= count($salesTrend) ?> data points</span></div>
    <div class="rc-body">
      <?php if ($salesTrend):
        $maxR = max(array_column($salesTrend,'revenue') ?: [1]); ?>
      <div class="chart">
        <?php foreach ($salesTrend as $r):
          $h = max(6,round(($r['revenue']/$maxR)*130)); ?>
        <div class="bar" style="height:<?= $h ?>px;">
          <div class="tip"><?= e($r['label']) ?><br><?= money($r['revenue']) ?><br><?= $r['orders'] ?> orders</div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="chart-lbl">
        <?php foreach ($salesTrend as $r): ?><div class="cl"><?= e($r['label']) ?></div><?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:40px;color:#aaa;">
        <div style="font-size:3rem;margin-bottom:10px;">📭</div>
        No sales data found for this period.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
    <!-- By payment method -->
    <div class="rc">
      <div class="rc-head"><h3>💳 Revenue by Payment Method</h3></div>
      <div class="rc-body">
        <?php if ($byPayment):
          $maxP = max(array_column($byPayment,'revenue') ?: [1]);
          foreach ($byPayment as $r): ?>
        <div style="margin-bottom:14px;">
          <div style="display:flex;justify-content:space-between;font-size:.83rem;margin-bottom:5px;">
            <span style="font-weight:600;"><?= e($r['payment_method']) ?></span>
            <span style="color:#1565c0;font-weight:700;"><?= money($r['revenue']) ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="prog-wrap"><div class="prog" style="width:<?= round(($r['revenue']/$maxP)*100) ?>%;"></div></div>
            <span style="font-size:.75rem;color:#888;min-width:50px;"><?= $r['cnt'] ?> orders</span>
          </div>
        </div>
        <?php endforeach;
        else: ?><p style="color:#aaa;text-align:center;padding:20px;">No data for this period.</p><?php endif; ?>
      </div>
    </div>
    <!-- By category -->
    <div class="rc">
      <div class="rc-head"><h3>🌾 Revenue by Category</h3></div>
      <div class="rc-body">
        <?php if ($byCategory):
          $maxC = max(array_column($byCategory,'revenue') ?: [1]);
          foreach ($byCategory as $r): ?>
        <div style="margin-bottom:14px;">
          <div style="display:flex;justify-content:space-between;font-size:.83rem;margin-bottom:5px;">
            <span style="font-weight:600;"><?= ($catIcons[$r['category']] ?? '🌱').' '.e($r['category']) ?></span>
            <span style="color:#1565c0;font-weight:700;"><?= money($r['revenue']) ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="prog-wrap"><div class="prog" style="width:<?= round(($r['revenue']/$maxC)*100) ?>%;"></div></div>
            <span style="font-size:.75rem;color:#888;min-width:50px;"><?= number_format((int)$r['units']) ?> units</span>
          </div>
        </div>
        <?php endforeach;
        else: ?><p style="color:#aaa;text-align:center;padding:20px;">No data for this period.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <?php /* ══════════════ ORDERS REPORT ══════════════ */ ?>
  <?php elseif ($report === 'orders'): ?>

  <!-- Status KPIs -->
  <div class="kpi-row">
    <?php
    $sColors = ['Pending'=>'amber','Processing'=>'blue','Ready for delivery'=>'blue','Delivered'=>'green','Cancelled'=>'red'];
    $total   = array_sum(array_column($statusBreak,'cnt')) ?: 1;
    foreach ($statusBreak as $s): ?>
    <div class="kpi <?= $sColors[$s['order_status']] ?? 'blue' ?>">
      <div class="kpi-lbl"><?= e($s['order_status']) ?></div>
      <div class="kpi-val <?= $sColors[$s['order_status']] ?? '' ?>"><?= number_format((int)$s['cnt']) ?></div>
      <div class="kpi-sub"><?= round(($s['cnt']/$total)*100) ?>% · <?= money($s['rev']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Payment status -->
  <div class="rc" style="margin-bottom:18px;">
    <div class="rc-head"><h3>💳 Payment Status Breakdown</h3></div>
    <div class="rc-body" style="display:flex;gap:14px;flex-wrap:wrap;">
      <?php
      $pColors=['Pending'=>'#f59e0b','Confirmed'=>'#3cb878','Failed'=>'#e02020','Refunded'=>'#8b5cf6'];
      foreach ($payBreak as $p): ?>
      <div style="background:#fafafa;border:1px solid #e8e8e8;border-radius:8px;padding:18px 22px;text-align:center;flex:1;min-width:130px;">
        <div style="font:800 1.5rem Inter,sans-serif;color:<?= $pColors[$p['payment_status']] ?? '#1565c0' ?>;"><?= number_format((int)$p['cnt']) ?></div>
        <div style="font:600 .78rem Inter,sans-serif;color:#888;margin-top:4px;"><?= e($p['payment_status']) ?></div>
        <div style="font-size:.75rem;color:#aaa;margin-top:2px;"><?= money($p['total']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Orders table -->
  <div class="rc">
    <div class="rc-head">
      <h3>🧾 All Orders in Period</h3>
      <span style="color:#888;font-size:.8rem;"><?= count($ordersData) ?> orders</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rt">
        <thead><tr>
          <th>#</th><th>Date</th><th>Customer</th><th>Phone</th>
          <th>Payment Method</th><th>Payment</th><th>Status</th>
          <th style="text-align:right;">Amount</th>
        </tr></thead>
        <tbody>
        <?php foreach ($ordersData as $o):
          $oc=match(strtolower($o['order_status'])){
            'pending'=>'status-pending','processing'=>'status-processing',
            'ready for delivery'=>'status-ready','delivered'=>'status-delivered',
            'cancelled'=>'status-cancelled',default=>''};
          $pc=match(strtolower($o['payment_status'])){
            'confirmed'=>'status-confirmed','failed'=>'status-failed',
            'refunded'=>'status-refunded',default=>'status-pending'};
        ?>
        <tr>
          <td><strong style="color:#1565c0;">#<?= $o['id'] ?></strong><br>
              <small style="color:#aaa;"><?= date('d M Y',strtotime($o['created_at'])) ?></small></td>
          <td><?= date('H:i',strtotime($o['created_at'])) ?></td>
          <td><strong><?= e($o['full_name']??$o['customer_name']) ?></strong></td>
          <td><?= e($o['phone']) ?></td>
          <td style="font-size:.8rem;color:#888;"><?= e($o['payment_method']) ?></td>
          <td><span class="status <?= $pc ?>"><?= e($o['payment_status']) ?></span></td>
          <td><span class="status <?= $oc ?>"><?= e($o['order_status']) ?></span></td>
          <td style="text-align:right;font-weight:700;color:#1565c0;"><?= money($o['total_amount']) ?></td>
        </tr>
        <?php endforeach;
        if (!$ordersData): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:#aaa;">No orders in this period.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php /* ══════════════ PRODUCTS REPORT ══════════════ */ ?>
  <?php elseif ($report === 'products'): ?>

  <div class="kpi-row">
    <div class="kpi blue">
      <div class="kpi-lbl">Active Products</div>
      <div class="kpi-val"><?= number_format((int)$invSummary['active_count']) ?></div>
      <div class="kpi-sub">in catalogue</div>
    </div>
    <div class="kpi red">
      <div class="kpi-lbl">Out of Stock</div>
      <div class="kpi-val red"><?= number_format((int)$invSummary['out_of_stock']) ?></div>
      <div class="kpi-sub">need restocking</div>
    </div>
    <div class="kpi amber">
      <div class="kpi-lbl">Low Stock (≤5)</div>
      <div class="kpi-val amber"><?= number_format((int)$invSummary['low_stock']) ?></div>
      <div class="kpi-sub">running low</div>
    </div>
    <div class="kpi green">
      <div class="kpi-lbl">Total Stock Value</div>
      <div class="kpi-val green" style="font-size:1.1rem;"><?= money($invSummary['total_value']) ?></div>
      <div class="kpi-sub">inventory value</div>
    </div>
  </div>

  <!-- Best sellers -->
  <div class="rc" style="margin-bottom:18px;">
    <div class="rc-head">
      <h3>🏆 Best Selling Products</h3>
      <span style="color:#888;font-size:.8rem;"><?= date('d M Y',strtotime($from)) ?> – <?= date('d M Y',strtotime($to)) ?></span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rt">
        <thead><tr><th>Rank</th><th>Product</th><th>Category</th>
          <th style="text-align:center;">Units Sold</th>
          <th style="text-align:right;">Revenue</th>
          <th style="text-align:center;">Stock</th>
          <th style="text-align:right;">Price</th></tr></thead>
        <tbody>
        <?php foreach ($bestSellers as $i=>$p):
          $rc = match($i){0=>'r1',1=>'r2',2=>'r3',default=>'rn'};
          $sq = (int)$p['stock']; ?>
        <tr>
          <td><span class="rank <?= $rc ?>"><?= $i+1 ?></span></td>
          <td><strong><?= e($p['product_name']) ?></strong></td>
          <td><?= ($catIcons[$p['category']]??'🌱').' '.e($p['category']) ?></td>
          <td style="text-align:center;font-weight:700;"><?= number_format((int)$p['units']) ?></td>
          <td style="text-align:right;color:#1565c0;font-weight:700;"><?= money($p['revenue']) ?></td>
          <td style="text-align:center;">
            <span style="background:<?= $sq===0?'#fdeaea':($sq<=5?'#fff3e0':'#e8f7ee') ?>;color:<?= $sq===0?'#b71c1c':($sq<=5?'#b36000':'#1a6b3a') ?>;padding:2px 8px;border-radius:10px;font:700 .74rem Inter,sans-serif;">
              <?= $sq===0?'Out':$sq.' left' ?>
            </span>
          </td>
          <td style="text-align:right;"><?= money($p['price']) ?></td>
        </tr>
        <?php endforeach;
        if(!$bestSellers): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:#aaa;">No sales data for this period.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Inventory snapshot -->
  <div class="rc">
    <div class="rc-head"><h3>📦 Full Inventory Snapshot</h3></div>
    <div style="overflow-x:auto;">
      <table class="rt">
        <thead><tr><th>Product</th><th>Category</th>
          <th style="text-align:right;">Price</th>
          <th style="text-align:center;">Stock</th>
          <th style="text-align:right;">Stock Value</th>
          <th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($inventory as $p): $sq=(int)$p['stock_quantity']; ?>
        <tr>
          <td><strong><?= e($p['name']) ?></strong></td>
          <td><?= ($catIcons[$p['category']]??'🌱').' '.e($p['category']) ?></td>
          <td style="text-align:right;font-weight:600;"><?= money($p['price']) ?></td>
          <td style="text-align:center;">
            <span style="background:<?= $sq===0?'#fdeaea':($sq<=5?'#fff3e0':'#e8f7ee') ?>;color:<?= $sq===0?'#b71c1c':($sq<=5?'#b36000':'#1a6b3a') ?>;padding:2px 8px;border-radius:10px;font:700 .74rem Inter,sans-serif;">
              <?= $sq ?>
            </span>
          </td>
          <td style="text-align:right;"><?= money($p['stock_value']) ?></td>
          <td>
            <?php if(!$p['active']): ?><span class="status" style="background:#fdeaea;color:#b71c1c;">Inactive</span>
            <?php elseif($sq===0): ?><span class="status" style="background:#fdeaea;color:#b71c1c;">Out of Stock</span>
            <?php elseif($sq<=5): ?><span class="status" style="background:#fff3e0;color:#b36000;">Low Stock</span>
            <?php else: ?><span class="status status-confirmed">Active</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php /* ══════════════ CUSTOMERS REPORT ══════════════ */ ?>
  <?php elseif ($report === 'customers'): ?>

  <div class="kpi-row" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi blue">
      <div class="kpi-lbl">Total Customers</div>
      <div class="kpi-val"><?= number_format((int)$custSummary['total']) ?></div>
      <div class="kpi-sub">registered accounts</div>
    </div>
    <div class="kpi green">
      <div class="kpi-lbl">Active Buyers</div>
      <div class="kpi-val green"><?= number_format((int)$custSummary['active']) ?></div>
      <div class="kpi-sub">ordered in period</div>
    </div>
    <div class="kpi amber">
      <div class="kpi-lbl">Total Spent</div>
      <div class="kpi-val amber" style="font-size:1.1rem;"><?= money(array_sum(array_column($topCustomers,'total_spent'))) ?></div>
      <div class="kpi-sub">all customers</div>
    </div>
  </div>

  <div class="rc">
    <div class="rc-head">
      <h3>🏆 Customers by Total Spend</h3>
      <span style="color:#888;font-size:.8rem;"><?= count($topCustomers) ?> customers</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rt">
        <thead><tr><th>Rank</th><th>Name</th><th>Phone</th>
          <th style="text-align:center;">Orders</th>
          <th style="text-align:right;">Total Spent</th>
          <th>Last Order</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($topCustomers as $i=>$c):
          $rc=match($i){0=>'r1',1=>'r2',2=>'r3',default=>'rn'}; ?>
        <tr>
          <td><span class="rank <?= $rc ?>"><?= $i+1 ?></span></td>
          <td>
            <strong><?= e($c['full_name']) ?></strong><br>
            <small style="color:#aaa;"><?= e($c['email']) ?></small>
          </td>
          <td><?= e($c['phone']) ?></td>
          <td style="text-align:center;font-weight:700;"><?= (int)$c['order_count'] ?></td>
          <td style="text-align:right;color:#1565c0;font-weight:700;"><?= money($c['total_spent']) ?></td>
          <td style="color:#888;"><?= $c['last_order']?date('d M Y',strtotime($c['last_order'])):'—' ?></td>
          <td style="color:#aaa;font-size:.8rem;"><?= date('d M Y',strtotime($c['joined'])) ?></td>
        </tr>
        <?php endforeach;
        if(!$topCustomers): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:#aaa;">No customers found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

  <!-- Footer -->
  <div class="no-print" style="text-align:center;padding:20px 0;color:#aaa;font-size:.78rem;">
    📈 Report generated on <?= date('D, d M Y H:i') ?> · Agro Input Dealers · <?= e(current_user()['full_name']) ?>
  </div>

</div><!-- /rpt-wrap -->
</body>
</html>
