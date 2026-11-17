<?php

/* ==========================================================
   staff - STATISTICS / REPORTS PAGE
   Dating nasa Shared/reports-page.php (iisang kopya para sa owner at staff).
   Ngayon ay may sariling kopya na ang owner at staff, kaya dito na ang buong page.
   $figurifyRole = "staff" ang pumipili ng header (staff-header.php).
   ========================================================== */

$figurifyRole    = "staff";
$figurifyPageDir = __DIR__;
require_once $figurifyPageDir . "/" . $figurifyRole . "-header.php";
require_once __DIR__ . "/../helpers/reports_logs_helper.php";

/* RANGE: 7 / 30 / 90 araw o "month" (unang araw ng buwan hanggang ngayon). Default 30. */

$allowedRanges = ["7", "30", "90", "month"];
$rangeKey      = (string) ($_GET["range"] ?? "30");

if (!in_array($rangeKey, $allowedRanges, true)) {
    $rangeKey = "30";
}

$rangeDays  = $rangeKey === "month" ? (int) date("j") : (int) $rangeKey;
$monthLabel = date("M 1") . " to " . date("M j");
$rangeText  = $rangeKey === "month" ? "this month (" . $monthLabel . ")" : "last " . $rangeDays . " days";

$rangeOptions = [
    "7"     => "7 days",
    "30"    => "30 days",
    "90"    => "90 days",
    "month" => $monthLabel,
];


/* DATA */

$salesSummary       = figurify_get_sales_summary($conn);
$lastMonthRevenue   = figurify_get_last_month_revenue($conn);
$statusCounts       = figurify_get_orders_by_status($conn);
$topProducts        = figurify_get_top_products($conn, 5);
$salesOverTime      = figurify_get_sales_over_time($conn, $rangeDays);
$styleMix           = figurify_get_figure_style_mix($conn);
$completedThisMonth = figurify_get_completed_this_month($conn);


// this month vs last month (porsyento ng pagbabago)
$monthDeltaPct = null;

if ($lastMonthRevenue > 0) {
    $monthDeltaPct = (($salesSummary["month_revenue"] - $lastMonthRevenue) / $lastMonthRevenue) * 100;
}


// buod ng napiling range
$rangeRevenue = 0.0;
$rangeOrders  = 0;
$bestDay      = null;

foreach ($salesOverTime as $day) {
    $rangeRevenue += $day["revenue"];
    $rangeOrders  += $day["count"];

    if ($day["revenue"] > 0 && ($bestDay === null || $day["revenue"] > $bestDay["revenue"])) {
        $bestDay = $day;
    }
}

$rangeAvgPerDay = $rangeDays > 0 ? $rangeRevenue / $rangeDays : 0;


// orders by status - tatlong grupo: Active / Completed / Cancelled
// (kasama sa Cancelled ang for_refund at refunded)
$totalOrders     = 0;
$completedOrders = 0;
$cancelledOrders = 0;

foreach ($statusCounts as $status) {
    $totalOrders += $status["count"];

    if ($status["key"] === "completed") {
        $completedOrders += $status["count"];
    }

    if (in_array($status["key"], ["cancelled", "for_refund", "refunded"], true)) {
        $cancelledOrders += $status["count"];
    }
}

$activeOrders   = max(0, $totalOrders - $completedOrders - $cancelledOrders);
$completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;

$statusGroups = [
    ["key" => "active", "label" => "Active",    "count" => $activeOrders,    "color" => "#C2325F"],
    ["key" => "completed", "label" => "Completed", "count" => $completedOrders, "color" => "#2F8F67"],
    ["key" => "cancelled", "label" => "Cancelled", "count" => $cancelledOrders, "color" => "#B83227"],
];


// top products - pinakamataas na bilang (para sa haba ng bar)
$maxProductCount = 0;

foreach ($topProducts as $product) {
    $maxProductCount = max($maxProductCount, (int) $product["total_ordered"]);
}


// para sa chart (JS)
$chartData = array_map(function ($day) {
    return [
        "label"   => date("M j", strtotime($day["date"])),
        "weekday" => date("D", strtotime($day["date"])),
        "revenue" => round((float) $day["revenue"], 2),
        "orders"  => (int) $day["count"],
    ];
}, $salesOverTime);

/* ORDER SUMMARY REPORT (audit) - filter: petsa ng order (from / to) at grupo ng status.
   Default: unang araw ng buwan hanggang ngayon, lahat ng order.
   Data: figurify_get_order_report() sa helpers/reports_logs_helper.php */

$reportFrom  = (string) ($_GET["from"] ?? date("Y-m-01"));
$reportTo    = (string) ($_GET["to"] ?? date("Y-m-d"));
$reportGroup = (string) ($_GET["group"] ?? "all");

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportFrom)) {
    $reportFrom = date("Y-m-01");
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportTo)) {
    $reportTo = date("Y-m-d");
}

if ($reportFrom > $reportTo) {
    [$reportFrom, $reportTo] = [$reportTo, $reportFrom];
}

if (!isset(FIGURIFY_REPORT_GROUPS[$reportGroup])) {
    $reportGroup = "all";
}

$orderReport      = figurify_get_order_report($conn, $reportFrom, $reportTo, $reportGroup);
$reportTotals     = $orderReport["totals"];
$reportRangeText  = date("M j, Y", strtotime($reportFrom)) . " to " . date("M j, Y", strtotime($reportTo));
$reportPreparedBy = $staffName . " (Staff)";

function figurify_peso($amount, $decimals = 2)
{
    return "₱" . number_format((float) $amount, $decimals);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Figurify | Statistics</title>
<!-- ===================== STYLE NG PAGE NA ITO: reports.css ===================== -->
<link rel="stylesheet" href="reports.css">

</head>

<body>

<div class="app">

    <?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>

    <main class="main">

        <header class="topbar">

            <div class="welcome">
                <h2 class="portal-title">Statistics</h2>
            </div>

            <div class="top-actions">
<!-- ==========================================================
     [TOPBAR-DATETIME]  (dating Shared/topbar-datetime.php - nasa page na ito na)
     ANO ITO: petsa at oras sa taas ng page, sa kaliwa ng notification bell.
     PAANO GUMAGANA: ang JS sa Shared/sidebar.php ([3] TOPBAR DATE / TIME) ang nagpapalit ng .cs-tb-date at .cs-tb-time
     kada segundo (en-PH). Ang styles ay nasa reports.css (TOPBAR DATE / TIME).
     ========================================================== -->

<div class="cs-tb-datetime" aria-label="Current date and time">
    <span class="cs-tb-date">Loading date...</span>
    <span class="cs-tb-dot">•</span>
    <span class="cs-tb-time">Loading time...</span>
</div>

<!-- [END TOPBAR-DATETIME] -->

                <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>
            </div>

        </header>

        <div class="st-page">

            <!-- RANGE -->

            <nav class="st-range" aria-label="Date range">
                <?php foreach ($rangeOptions as $key => $label): ?>
                    <?php $key = (string) $key; /* "30" -> int sa array key, ibalik sa string */ ?>
                    <a
                        href="?range=<?php echo $key; ?>"
                        class="<?php echo $key === $rangeKey ? "active" : ""; ?>"
                        <?php echo $key === $rangeKey ? 'aria-current="page"' : ""; ?>
                    ><?php echo e($label); ?></a>
                <?php endforeach; ?>
            </nav>


            <!-- KPI CARDS -->

            <section class="st-kpis">

                <div class="st-card st-kpi st-kpi-hero">
                    <div class="st-kpi-label">Total revenue</div>
                    <div class="st-kpi-value"><?php echo figurify_peso($salesSummary["total_revenue"]); ?></div>
                    <span class="st-pill">All-time · from paid orders</span>
                </div>

                <div class="st-card st-kpi">
                    <div class="st-kpi-label">This month</div>
                    <div class="st-kpi-value"><?php echo figurify_peso($salesSummary["month_revenue"]); ?></div>

                    <?php if ($monthDeltaPct === null): ?>
                        <span class="st-pill st-pill-flat">Revenue for <?php echo date("F"); ?></span>
                    <?php elseif ($monthDeltaPct > 0.05): ?>
                        <span class="st-pill st-pill-up">▲ <?php echo number_format($monthDeltaPct, 0); ?>% vs last month</span>
                    <?php elseif ($monthDeltaPct < -0.05): ?>
                        <span class="st-pill st-pill-down">▼ <?php echo number_format(abs($monthDeltaPct), 0); ?>% vs last month</span>
                    <?php else: ?>
                        <span class="st-pill st-pill-flat">Same as last month</span>
                    <?php endif; ?>
                </div>

                <div class="st-card st-kpi">
                    <div class="st-kpi-label">Paid orders</div>
                    <div class="st-kpi-value"><?php echo number_format($salesSummary["paid_orders"]); ?></div>
                    <div class="st-kpi-foot">Confirmed &amp; paid · all-time</div>
                </div>

                <div class="st-card st-kpi">
                    <div class="st-kpi-label">Avg order value</div>
                    <div class="st-kpi-value"><?php echo figurify_peso($salesSummary["avg_order_value"]); ?></div>
                    <div class="st-kpi-foot">
                        Avg per day <?php echo figurify_peso($rangeAvgPerDay); ?> · Completed this month <?php echo number_format($completedThisMonth); ?>
                    </div>
                </div>

            </section>


            <!-- SALES OVERVIEW + ORDER MIX -->

            <div class="st-grid-main">

                <section class="st-card">

                    <h3 class="st-card-title">Sales overview</h3>
                    <p class="st-card-sub">Daily sales from paid orders (<?php echo e($rangeText); ?>)</p>

                    <?php if ($rangeRevenue > 0): ?>
                        <div class="st-metric-tabs" role="tablist" aria-label="Chart metric">
                            <button type="button" class="active" data-metric="revenue" role="tab" aria-selected="true">Revenue</button>
                            <button type="button" data-metric="orders" role="tab" aria-selected="false">Orders</button>
                        </div>
                    <?php endif; ?>

                    <div class="st-sales-summary">
                        <div>
                            <span class="st-sum-label">Revenue</span>
                            <span class="st-sum-value"><?php echo figurify_peso($rangeRevenue); ?></span>
                        </div>
                        <div>
                            <span class="st-sum-label">Paid orders</span>
                            <span class="st-sum-value"><?php echo number_format($rangeOrders); ?></span>
                        </div>
                        <div>
                            <span class="st-sum-label">Avg per day</span>
                            <span class="st-sum-value"><?php echo figurify_peso($rangeAvgPerDay); ?></span>
                        </div>
                        <div>
                            <span class="st-sum-label">Best day</span>
                            <span class="st-sum-value"><?php echo $bestDay ? date("M j", strtotime($bestDay["date"])) : "N/A"; ?></span>
                        </div>
                    </div>

                    <?php if ($rangeRevenue <= 0): ?>

                        <div class="st-empty">No sales <?php echo $rangeKey === "month" ? "this month" : "in the last " . $rangeDays . " days"; ?>.</div>

                    <?php else: ?>

                        <div class="st-chart-wrap" id="stSalesChart" role="img" aria-label="Daily sales, <?php echo e($rangeText); ?>">
                            <div class="st-tooltip" id="stSalesTooltip"></div>
                        </div>

                        <details class="st-table-toggle">
                            <summary>View as table</summary>
                            <div class="st-data-scroll">
                                <table class="st-data-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="num">Paid orders</th>
                                            <th class="num">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_reverse($salesOverTime) as $day): ?>
                                            <tr>
                                                <td><?php echo date("D, M j", strtotime($day["date"])); ?></td>
                                                <td class="num"><?php echo (int) $day["count"]; ?></td>
                                                <td class="num"><?php echo figurify_peso($day["revenue"], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>

                    <?php endif; ?>

                </section>


                <section class="st-card">

                    <h3 class="st-card-title">Order mix</h3>
                    <p class="st-card-sub"><?php echo number_format($styleMix["orders"]); ?> active &amp; completed order<?php echo $styleMix["orders"] === 1 ? "" : "s"; ?></p>

                    <?php if ($styleMix["figures"] === 0): ?>

                        <div class="st-empty">No orders yet.</div>

                    <?php else: ?>

                        <ul class="st-bar-list">
                            <?php foreach ($styleMix["styles"] as $style): ?>
                                <?php $stylePct = $style["count"] / $styleMix["figures"] * 100; ?>
                                <li title="<?php echo e($style["label"]) . ": " . $style["count"] . " figure" . ($style["count"] === 1 ? "" : "s"); ?>">
                                    <div class="st-bar-top">
                                        <span class="st-bar-name"><?php echo e($style["label"]); ?></span>
                                        <b><?php echo number_format($stylePct, 0); ?>%</b>
                                    </div>
                                    <progress class="st-bar" max="100" value="<?php echo round($stylePct, 1); ?>"><?php echo round($stylePct, 1); ?>%</progress>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                    <?php endif; ?>

                </section>

            </div>


            <!-- ORDERS BY STATUS + TOP PRODUCTS -->

            <div class="st-grid-bottom">

                <section class="st-card">

                    <h3 class="st-card-title">Orders by status</h3>
                    <p class="st-card-sub"><?php echo number_format($totalOrders); ?> order<?php echo $totalOrders === 1 ? "" : "s"; ?> in total</p>

                    <?php if ($totalOrders === 0): ?>

                        <div class="st-empty">No orders yet.</div>

                    <?php else: ?>

                        <?php
                        $donutR      = 60;
                        $donutC      = 2 * M_PI * $donutR;
                        $donutOffset = 0;
                        ?>

                        <div class="st-status-body">

                            <div class="st-donut">
                                <svg viewBox="0 0 150 150" aria-hidden="true">
                                    <circle cx="75" cy="75" r="<?php echo $donutR; ?>" fill="none" stroke="#f6eef3" stroke-width="22"/>
                                    <?php foreach ($statusGroups as $group): ?>
                                        <?php if ($group["count"] <= 0) { continue; } ?>
                                        <?php $segLen = $donutC * $group["count"] / $totalOrders; ?>
                                        <circle
                                            cx="75" cy="75" r="<?php echo $donutR; ?>"
                                            fill="none"
                                            stroke="<?php echo $group["color"]; ?>"
                                            stroke-width="22"
                                            stroke-dasharray="<?php echo round($segLen, 2) . " " . round($donutC, 2); ?>"
                                            stroke-dashoffset="<?php echo round(-$donutOffset, 2); ?>"
                                        ><title><?php echo $group["label"] . ": " . $group["count"]; ?></title></circle>
                                        <?php $donutOffset += $segLen; ?>
                                    <?php endforeach; ?>
                                </svg>
                            </div>

                            <ul class="st-legend">
                                <?php foreach ($statusGroups as $group): ?>
                                    <li>
                                        <i class="st-dot-<?php echo $group["key"]; ?>"></i>
                                        <span><?php echo $group["label"]; ?></span>
                                        <b><?php echo number_format($group["count"]); ?></b>
                                    </li>
                                <?php endforeach; ?>
                                <li class="st-legend-rate">
                                    <span>Completion rate</span>
                                    <b><?php echo number_format($completionRate, 0); ?>%</b>
                                </li>
                            </ul>

                        </div>

                    <?php endif; ?>

                </section>


                <section class="st-card">

                    <h3 class="st-card-title">Top products</h3>
                    <p class="st-card-sub">Most ordered figures from paid orders</p>

                    <?php if (empty($topProducts)): ?>

                        <div class="st-empty">No paid orders yet.</div>

                    <?php else: ?>

                        <ol class="st-bar-list">
                            <?php foreach ($topProducts as $i => $product): ?>
                                <?php
                                $productCount = (int) $product["total_ordered"];
                                $productWidth = $maxProductCount > 0 ? max(3, $productCount / $maxProductCount * 100) : 0;
                                $productName  = trim($product["figure_style"] . " · " . $product["product_type"], " ·");
                                ?>
                                <li title="<?php echo e($productName) . ": " . figurify_peso($product["total_revenue"] ?? 0); ?>">
                                    <div class="st-bar-top">
                                        <span class="st-bar-name"><strong>#<?php echo $i + 1; ?></strong> <?php echo e($productName); ?></span>
                                        <b><?php echo number_format($productCount); ?> sold</b>
                                    </div>
                                    <progress class="st-bar" max="100" value="<?php echo round($productWidth, 1); ?>"><?php echo round($productWidth, 1); ?>%</progress>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                    <?php endif; ?>

                </section>

            </div>

            <!-- ==================== ORDER SUMMARY REPORT (audit) ====================
                 Lahat ng order ng customer sa napiling petsa: bayad, balance at status.
                 "Preview & Print" = bubukas ang report na parang papel (#stReportPreview sa ibaba). -->

            <section class="st-card st-report" id="orderReport">

                <div class="st-report-head">
                    <div>
                        <h3 class="st-card-title">Order Summary Report</h3>
                        <p class="st-card-sub">All customer orders with payments and balances (<?php echo e($reportRangeText); ?>)</p>
                    </div>

                    <button type="button" class="st-report-btn" id="stReportPreviewBtn" <?php echo empty($orderReport["rows"]) ? "disabled" : ""; ?>>Preview &amp; Print</button>
                </div>

                <form class="st-report-filter" method="get" action="#orderReport">
                    <input type="hidden" name="range" value="<?php echo e($rangeKey); ?>">

                    <label>
                        <span>From</span>
                        <input type="date" name="from" value="<?php echo e($reportFrom); ?>">
                    </label>

                    <label>
                        <span>To</span>
                        <input type="date" name="to" value="<?php echo e($reportTo); ?>">
                    </label>

                    <label>
                        <span>Status</span>
                        <select name="group">
                            <?php foreach (FIGURIFY_REPORT_GROUPS as $groupKey => $groupLabel): ?>
                                <option value="<?php echo e($groupKey); ?>" <?php echo $groupKey === $reportGroup ? "selected" : ""; ?>><?php echo e($groupLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit" class="st-report-btn">Apply</button>
                </form>

                <div class="st-sales-summary">
                    <div>
                        <span class="st-sum-label">Orders</span>
                        <span class="st-sum-value"><?php echo number_format($reportTotals["orders"]); ?></span>
                    </div>
                    <div>
                        <span class="st-sum-label">Total sales</span>
                        <span class="st-sum-value"><?php echo figurify_peso($reportTotals["sales"], 2); ?></span>
                    </div>
                    <div>
                        <span class="st-sum-label">Amount collected</span>
                        <span class="st-sum-value"><?php echo figurify_peso($reportTotals["paid"], 2); ?></span>
                    </div>
                    <div>
                        <span class="st-sum-label">Balance due</span>
                        <span class="st-sum-value"><?php echo figurify_peso($reportTotals["balance"], 2); ?></span>
                    </div>
                </div>

                <?php if (empty($orderReport["rows"])): ?>

                    <div class="st-empty">No orders for this date and status.</div>

                <?php else: ?>

                    <div class="st-report-scroll">
                        <table class="st-data-table" id="stReportTable">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Ordered On</th>
                                    <th>Ship Out Date</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th>Payment</th>
                                    <th class="num">Total</th>
                                    <th class="num">Paid</th>
                                    <th class="num">Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderReport["rows"] as $reportRow): ?>
                                    <tr>
                                        <td class="st-report-id">#<?php echo (int) $reportRow["order_id"]; ?></td>
                                        <td>
                                            <?php echo e($reportRow["full_name"]); ?>
                                            <small><?php echo e($reportRow["email"]); ?></small>
                                        </td>
                                        <td><?php echo date("M j, Y", strtotime($reportRow["created_at"])); ?></td>
                                        <td><?php echo !empty($reportRow["ship_out_date"]) ? date("M j, Y", strtotime($reportRow["ship_out_date"])) : "N/A"; ?></td>
                                        <td><?php echo e($reportRow["type_label"]); ?></td>
                                        <td><?php echo e($reportRow["method_label"]); ?></td>
                                        <td><?php echo e($reportRow["payment_label"]); ?></td>
                                        <td class="num"><?php echo (float) $reportRow["total_amount"] > 0 ? figurify_peso($reportRow["total_amount"], 2) : "N/A"; ?></td>
                                        <td class="num"><?php echo figurify_peso($reportRow["paid_amount"], 2); ?></td>
                                        <td class="num"><?php echo figurify_peso($reportRow["balance_amount"], 2); ?></td>
                                        <td><span class="st-report-status st-report-status-<?php echo e($reportRow["group"]); ?>"><?php echo e($reportRow["status_label"]); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="7">Total (<?php echo number_format($reportTotals["orders"]); ?> order<?php echo $reportTotals["orders"] === 1 ? "" : "s"; ?>)</td>
                                    <td class="num"><?php echo figurify_peso($reportTotals["sales"], 2); ?></td>
                                    <td class="num"><?php echo figurify_peso($reportTotals["paid"], 2); ?></td>
                                    <td class="num"><?php echo figurify_peso($reportTotals["balance"], 2); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <p class="st-report-note">
                        Sales do not include cancelled or refunded orders.
                        <?php if ($reportTotals["refunded"] > 0): ?>
                            Refunded to customers: <b><?php echo figurify_peso($reportTotals["refunded"], 2); ?></b>
                        <?php endif; ?>
                    </p>

                <?php endif; ?>

            </section>
            <!-- ==================== END ORDER SUMMARY REPORT ==================== -->


        </div>

    </main>

</div>


<!-- ==================== ORDER SUMMARY REPORT - PREVIEW ====================
     Binubuksan ng "Preview & Print". Kinokopya ng JS ang table ng report dito (lahat ng row,
     walang Previous / Next). Kapag nag-print habang bukas ito, ito lang ang lalabas sa papel. -->

<div class="st-preview" id="stReportPreview" hidden>

    <div class="st-preview-bar">
        <strong>Print preview</strong>
        <div class="st-preview-actions">
            <button type="button" class="st-report-btn" id="stReportPrintBtn">Print / Save as PDF</button>
            <button type="button" class="st-report-btn st-report-btn-close" id="stReportCloseBtn">Close</button>
        </div>
    </div>

    <article class="st-paper">

        <header class="st-paper-head">
            <div class="st-paper-brand">
                <img src="../Image/brand/logo.png" alt="">
                <div>
                    <h1>Clay and Stuff</h1>
                    <p>Order Summary Report</p>
                </div>
            </div>

            <dl class="st-paper-meta">
                <div><dt>Period</dt><dd><?php echo e($reportRangeText); ?></dd></div>
                <div><dt>Status</dt><dd><?php echo e(FIGURIFY_REPORT_GROUPS[$reportGroup]); ?></dd></div>
                <div><dt>Generated</dt><dd><?php echo date("M j, Y g:i A"); ?></dd></div>
                <div><dt>Prepared by</dt><dd><?php echo e($reportPreparedBy); ?></dd></div>
            </dl>
        </header>

        <div class="st-paper-totals">
            <div><span>Orders</span><b><?php echo number_format($reportTotals["orders"]); ?></b></div>
            <div><span>Total sales</span><b><?php echo figurify_peso($reportTotals["sales"], 2); ?></b></div>
            <div><span>Amount collected</span><b><?php echo figurify_peso($reportTotals["paid"], 2); ?></b></div>
            <div><span>Balance due</span><b><?php echo figurify_peso($reportTotals["balance"], 2); ?></b></div>
            <div><span>Refunded</span><b><?php echo figurify_peso($reportTotals["refunded"], 2); ?></b></div>
        </div>

        <!-- dito ilalagay ng JS ang kopya ng #stReportTable -->
        <div class="st-paper-table" id="stReportPaperTable"></div>

        <p class="st-paper-note">Sales do not include cancelled or refunded orders. Amounts are in Philippine Peso (₱).</p>

        <footer class="st-paper-sign">
            <div><span></span>Prepared by</div>
            <div><span></span>Checked / Approved by</div>
        </footer>

    </article>

</div>
<!-- ==================== END ORDER SUMMARY REPORT - PREVIEW ==================== -->


<script>
<?php if ($rangeRevenue > 0): ?>
/* SALES CHART - area line (SVG, walang library)
   - Revenue / Orders na tab
   - hover/tap = guide line + tuldok + tooltip */
(function () {

    var data    = <?php echo json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var wrap    = document.getElementById("stSalesChart");
    var tooltip = document.getElementById("stSalesTooltip");

    /* posisyon ng tooltip: isinusulat sa sariling CSS rule ng page (#stSalesTooltip),
       hindi sa inline style ng element - walang inline style */
    var tooltipRule = null;
    function tooltipPositionRule() {
        if (tooltipRule) {
            return tooltipRule;
        }
        for (var s = 0; s < document.styleSheets.length; s++) {
            var sheet = document.styleSheets[s];
            var rules;
            try { rules = sheet.cssRules; } catch (e) { continue; }
            for (var r = 0; r < rules.length; r++) {
                if (rules[r].selectorText === ".st-tooltip") {
                    tooltipRule = sheet.cssRules[sheet.insertRule("#stSalesTooltip {}", rules.length)];
                    return tooltipRule;
                }
            }
        }
        return null;
    }
    var SVG_NS  = "http://www.w3.org/2000/svg";
    var metric  = "revenue";

    if (!wrap || !data.length) {
        return;
    }

    function peso(n) {
        return "₱" + Number(n).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function el(name, attrs) {
        var node = document.createElementNS(SVG_NS, name);
        for (var key in attrs) { node.setAttribute(key, attrs[key]); }
        return node;
    }

    function draw() {

        var old = wrap.querySelector("svg");
        if (old) { wrap.removeChild(old); }
        tooltip.classList.remove("show");

        var W = wrap.clientWidth || 600;
        var H = wrap.clientHeight || 200;
        var top = 10;
        var n = data.length;

        var values = data.map(function (d) { return metric === "orders" ? d.orders : d.revenue; });
        var max = Math.max.apply(null, values) || 1;

        var pts = values.map(function (v, i) {
            return {
                x: n > 1 ? (W * i) / (n - 1) : W / 2,
                y: top + (H - top) * (1 - v / max) - (v > 0 ? 0 : 1)
            };
        });

        var line = "M" + pts[0].x + "," + pts[0].y;
        for (var i = 1; i < n; i++) { line += " L" + pts[i].x + "," + pts[i].y; }
        var area = line + " L" + pts[n - 1].x + "," + H + " L" + pts[0].x + "," + H + " Z";

        var svg = el("svg", { viewBox: "0 0 " + W + " " + H, preserveAspectRatio: "none" });
        svg.appendChild(el("path", { d: area, "class": "st-chart-area" }));
        svg.appendChild(el("path", { d: line, "class": "st-chart-line" }));

        var guide = el("line", { x1: 0, x2: 0, y1: 0, y2: H, "class": "st-chart-guide", visibility: "hidden" });
        var dot   = el("circle", { r: 5, cx: 0, cy: 0, "class": "st-chart-dot", visibility: "hidden" });
        svg.appendChild(guide);
        svg.appendChild(dot);

        var hit = el("rect", { x: 0, y: 0, width: W, height: H, fill: "transparent" });
        svg.appendChild(hit);

        function show(clientX) {
            var rect = svg.getBoundingClientRect();
            var x = (clientX - rect.left) * (W / rect.width);
            var idx = n > 1 ? Math.max(0, Math.min(n - 1, Math.round(x / (W / (n - 1))))) : 0;
            var p = pts[idx];
            var d = data[idx];

            guide.setAttribute("x1", p.x);
            guide.setAttribute("x2", p.x);
            guide.setAttribute("visibility", "visible");
            dot.setAttribute("cx", p.x);
            dot.setAttribute("cy", p.y);
            dot.setAttribute("visibility", "visible");

            tooltip.innerHTML =
                "<b>" + d.weekday + ", " + d.label + "</b>" +
                '<div class="st-tt-row"><span>Revenue</span><span>' + peso(d.revenue) + "</span></div>" +
                '<div class="st-tt-row"><span>Paid orders</span><span>' + d.orders + "</span></div>";
            var position = tooltipPositionRule();
            if (position) {
                position.style.left = Math.max(80, Math.min(rect.width - 80, p.x * (rect.width / W))) + "px";
                position.style.top = (p.y * (rect.height / H)) + "px";
            }
            tooltip.classList.add("show");
        }

        function hide() {
            guide.setAttribute("visibility", "hidden");
            dot.setAttribute("visibility", "hidden");
            tooltip.classList.remove("show");
        }

        hit.addEventListener("mousemove", function (e) { show(e.clientX); });
        hit.addEventListener("mouseleave", hide);
        hit.addEventListener("touchstart", function (e) { if (e.touches[0]) { show(e.touches[0].clientX); } }, { passive: true });

        wrap.insertBefore(svg, tooltip);
    }

    // Revenue / Orders tabs
    document.querySelectorAll(".st-metric-tabs button").forEach(function (btn) {
        btn.addEventListener("click", function () {
            metric = btn.getAttribute("data-metric");
            document.querySelectorAll(".st-metric-tabs button").forEach(function (b) {
                var on = b === btn;
                b.classList.toggle("active", on);
                b.setAttribute("aria-selected", on ? "true" : "false");
            });
            draw();
        });
    });

    draw();

    var resizeTimer;
    window.addEventListener("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(draw, 120);
    });

})();
<?php endif; ?>
/* ==================== ORDER SUMMARY REPORT - PREVIEW AT PRINT ====================
   Preview & Print: kinokopya ang #stReportTable papunta sa papel (lahat ng row,
   tanggal ang Previous / Next ng Shared/sidebar.php), tapos bubukas ang preview.
   Print: window.print() - body.st-preview-open = ang papel lang ang ipi-print. */
(function () {

    var openBtn  = document.getElementById("stReportPreviewBtn");
    var preview  = document.getElementById("stReportPreview");
    var holder   = document.getElementById("stReportPaperTable");
    var source   = document.getElementById("stReportTable");

    if (!openBtn || !preview || !holder || !source) {
        return;
    }

    function open() {
        var copy = source.cloneNode(true);
        copy.removeAttribute("id");
        copy.removeAttribute("data-cs-pager");
        copy.className = "";
        copy.querySelectorAll(".cs-pg-off").forEach(function (row) { row.classList.remove("cs-pg-off"); });

        holder.innerHTML = "";
        holder.appendChild(copy);

        preview.hidden = false;
        document.body.classList.add("st-preview-open");
        preview.scrollTop = 0;
    }

    function close() {
        preview.hidden = true;
        document.body.classList.remove("st-preview-open");
    }

    openBtn.addEventListener("click", open);
    document.getElementById("stReportCloseBtn").addEventListener("click", close);
    document.getElementById("stReportPrintBtn").addEventListener("click", function () { window.print(); });

    preview.addEventListener("click", function (e) {
        if (e.target === preview) { close(); }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && !preview.hidden) { close(); }
    });

})();
/* ==================== END ORDER SUMMARY REPORT - PREVIEW AT PRINT ==================== */
</script>

</body>
</html>