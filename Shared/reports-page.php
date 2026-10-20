<?php

/* SHARED STATISTICS PAGE - iisang kopya para sa Owner at Staff (wrapper sa owner/ at staff/reports.php). */
if (!isset($figurifyRole, $figurifyPageDir) || !in_array($figurifyRole, ["owner", "staff"], true)) {
    http_response_code(404);
    exit();
}

require_once $figurifyPageDir . "/" . $figurifyRole . "-header.php";
require_once __DIR__ . "/../helpers/reports_helper.php";

/* RANGE: 7 / 30 / 90 araw o "month" (unang araw ng buwan hanggang ngayon). Default 30. */

$allowedRanges = ["7", "30", "90", "month"];
$rangeKey      = (string) ($_GET["range"] ?? "30");

if (!in_array($rangeKey, $allowedRanges, true)) {
    $rangeKey = "30";
}

$rangeDays  = $rangeKey === "month" ? (int) date("j") : (int) $rangeKey;
$monthLabel = date("M 1") . " – " . date("M j");
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
    ["label" => "Active",    "count" => $activeOrders,    "color" => "#C2325F"],
    ["label" => "Completed", "count" => $completedOrders, "color" => "#2F8F67"],
    ["label" => "Cancelled", "count" => $cancelledOrders, "color" => "#B83227"],
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

function figurify_peso($amount, $decimals = 0)
{
    return "₱" . number_format((float) $amount, $decimals);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Figurify — Statistics</title>
    <link rel="stylesheet" href="../Shared/dashboard.css">

    <style>
        /* ==========================================================
           STATISTICS - Design System (Fraunces + Plus Jakarta Sans, galing panel-pages.css)
           ========================================================== */

        .st-page { display: flex; flex-direction: column; gap: 22px; }

        /* range pills (kanan, sa ilalim ng topbar) */
        .st-range { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
        .st-range a {
            display: inline-flex;
            align-items: center;
            height: 40px;
            padding: 0 20px;
            border-radius: 999px;
            background: var(--p-blush, #ffedf2);
            color: var(--p-primary, #c2325f);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: background .15s ease;
        }
        .st-range a:hover { background: #ffd9e3; }
        .st-range a.active { background: var(--p-primary, #c2325f); color: #ffffff; }

        .st-card { padding: 22px 24px !important; min-width: 0; }

        .st-card-title {
            margin: 0;
            
            font-size: 21px;
            font-weight: 700;
            color: var(--p-plum, #4a1d3b);
        }
        .st-card-sub { margin: 4px 0 0; font-size: 14px; color: var(--p-muted, #7c6670); }

        .st-empty {
            display: grid;
            place-items: center;
            min-height: 160px;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--p-muted, #7c6670);
        }

        /* ---------- KPI cards ---------- */
        .st-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; }

        .st-kpi { display: flex; flex-direction: column; gap: 6px; }
        .st-kpi-label { font-size: 13px; font-weight: 600; color: var(--p-muted, #7c6670); }
        .st-kpi-value {
            
            font-size: 34px;
            font-weight: 700;
            line-height: 1.15;
            color: var(--p-plum, #4a1d3b);
            word-break: break-word;
        }
        .st-kpi-foot { font-size: 12.5px; line-height: 1.5; color: var(--p-muted, #7c6670); }

        .st-pill {
            display: inline-block;
            align-self: flex-start;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
        }
        .st-pill-up   { background: var(--p-ok-bg, #dff3e8);  color: var(--p-ok, #2f8f67); }
        .st-pill-down { background: var(--p-bad-bg, #fbdada); color: var(--p-bad, #b83227); }
        .st-pill-flat { background: var(--p-blush, #ffedf2);  color: var(--p-muted, #7c6670); }

        body .app .st-card.st-kpi-hero {
            background: var(--p-plum, #4a1d3b) !important;
            border-color: var(--p-plum, #4a1d3b) !important;
        }
        .st-kpi-hero .st-kpi-label,
        .st-kpi-hero .st-kpi-value { color: #ffffff; }
        .st-kpi-hero .st-pill { background: #ffffff; color: var(--p-plum, #4a1d3b); }

        /* ---------- rows ---------- */
        .st-grid-main { display: grid; grid-template-columns: minmax(0, 1.75fr) minmax(0, 1fr); gap: 22px; }
        .st-grid-bottom { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 22px; }

        /* ---------- sales overview ---------- */
        .st-metric-tabs { display: flex; gap: 8px; margin-top: 14px; }
        .st-metric-tabs button {
            height: 38px;
            padding: 0 16px;
            border: 1px solid var(--p-line, #f3dce3);
            border-radius: 999px;
            background: #ffffff;
            color: var(--p-plum, #4a1d3b);
            
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
        }
        .st-metric-tabs button:hover { border-color: #e9a6bd; }
        .st-metric-tabs button.active {
            background: var(--p-primary, #c2325f);
            border-color: var(--p-primary, #c2325f);
            color: #ffffff;
        }

        .st-sales-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 22px;
        }
        .st-sum-label { display: block; font-size: 12.5px; color: var(--p-muted, #7c6670); }
        .st-sum-value { display: block; margin-top: 4px; font-size: 15px; font-weight: 800; color: var(--p-plum, #4a1d3b); }

        .st-chart-wrap { position: relative; height: 200px; margin-top: 18px; }
        .st-chart-wrap svg { display: block; width: 100%; height: 100%; overflow: visible; }
        .st-chart-line { fill: none; stroke: var(--p-primary, #c2325f); stroke-width: 2.5; stroke-linejoin: round; stroke-linecap: round; }
        .st-chart-area { fill: var(--p-blush, #ffedf2); }
        .st-chart-guide { stroke: #e9c3d0; stroke-width: 1; stroke-dasharray: 3 3; }
        .st-chart-dot { fill: #ffffff; stroke: var(--p-primary, #c2325f); stroke-width: 2.5; }

        .st-tooltip {
            position: absolute;
            z-index: 2;
            min-width: 150px;
            padding: 10px 12px;
            border-radius: 12px;
            background: var(--p-plum, #4a1d3b);
            color: #ffffff;
            font-size: 12px;
            pointer-events: none;
            opacity: 0;
            transform: translate(-50%, calc(-100% - 12px));
            transition: opacity .12s ease;
        }
        .st-tooltip.show { opacity: 1; }
        .st-tooltip b { display: block; margin-bottom: 4px; font-size: 12.5px; }
        .st-tt-row { display: flex; justify-content: space-between; gap: 14px; opacity: .9; }

        .st-table-toggle { margin-top: 10px; font-size: 12.5px; color: var(--p-muted, #7c6670); }
        .st-table-toggle summary { cursor: pointer; width: fit-content; }
        .st-table-toggle summary:hover { color: var(--p-primary, #c2325f); }
        .st-data-table { width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 13px; }
        .st-data-table th,
        .st-data-table td { padding: 8px 6px; border-bottom: 1px solid #f6e8ed; text-align: left; color: var(--p-plum, #4a1d3b); }
        .st-data-table th { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--p-muted, #7c6670); }
        .st-data-table .num { text-align: right; }
        .st-data-scroll { max-height: 240px; overflow-y: auto; }

        /* ---------- bars (order mix + top products) ---------- */
        .st-bar-list { display: flex; flex-direction: column; gap: 22px; margin: 22px 0 0; padding: 0; list-style: none; }
        .st-bar-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--p-plum, #4a1d3b);
        }
        .st-bar-top b { font-weight: 800; white-space: nowrap; }
        .st-bar-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .st-bar-name strong { font-weight: 800; }
        .st-bar { height: 10px; border-radius: 999px; background: var(--p-blush, #ffedf2); overflow: hidden; }
        .st-bar span { display: block; height: 100%; border-radius: 999px; background: var(--p-primary, #c2325f); }

        /* ---------- orders by status ---------- */
        .st-status-body { display: flex; align-items: center; gap: 32px; margin-top: 18px; }
        .st-donut { flex: 0 0 auto; width: 150px; height: 150px; }
        .st-donut svg { display: block; width: 100%; height: 100%; transform: rotate(-90deg); }
        .st-legend { flex: 1 1 auto; display: flex; flex-direction: column; gap: 14px; margin: 0; padding: 0; list-style: none; }
        .st-legend li { display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--p-plum, #4a1d3b); }
        .st-legend i { flex: 0 0 auto; width: 9px; height: 9px; border-radius: 50%; }
        .st-legend span { flex: 1 1 auto; }
        .st-legend b { font-weight: 800; }
        .st-legend .st-legend-rate { padding-left: 19px; }

        /* ---------- responsive ---------- */
        @media (max-width: 1200px) {
            .st-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 1000px) {
            .st-grid-main,
            .st-grid-bottom { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .st-kpis { grid-template-columns: 1fr; }
            .st-sales-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .st-status-body { flex-direction: column; align-items: stretch; }
            .st-donut { align-self: center; }
            .st-range { justify-content: flex-start; }
        }
    </style>

</head>

<body>

<div class="app">

    <link rel="stylesheet" href="../Shared/receipt-panels.css?v=7">
    <style>
/* ==========================================================
   Figurify redesign - Owner at Staff PAGE STYLES (hindi sidebar)
   (dating Shared/panel-pages.css - may sariling kopya na ang bawat owner/staff page): iisang topbar,
   font, kulay, cards, table, buttons at status chips.
   Sundin ang Design System: Fraunces (headings) + Plus Jakarta Sans (body)
   Primary #C2325F | Plum #4A1D3B | Blush #FFEDF2 | Cream #FFF8F6 | Coral #E8707E
   Success #2F8F67 | Warning #9A6408 | Danger #B83227

   Font at background ng page: nasa Shared/all-in-one.php (hindi dito). Kapag offline,
   awtomatikong Georgia / system font ang gagamitin - hindi masisira ang layout.
   ========================================================== */


:root {
    --p-pad: clamp(14px, 2.2vw, 28px);
    --p-primary: #c2325f;
    --p-primary-dark: #a82a52;
    --p-plum: #4a1d3b;
    --p-blush: #ffedf2;
    --p-cream: #fff6f4;
    --p-line: #f3dce3;
    --p-muted: #7c6670;
    --p-ok: #2f8f67;   --p-ok-bg: #dff3e8;
    --p-warn: #9a6408; --p-warn-bg: #fdefbf;
    --p-bad: #b83227;  --p-bad-bg: #fbdada;
    --p-ship: #3552a8; --p-ship-bg: #e3eafd;
}

html body {
    
    color: var(--p-plum);
    
}
body .app :is(button, input, select, textarea) {  }

/* ---------- TOPBAR (pareho sa lahat ng page) ---------- */
body .app > main.main { padding: 0 var(--p-pad) 22px !important; }

body .app > main.main > .topbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 14px;
    margin: 0 calc(var(--p-pad) * -1) 16px !important;
    padding: 8px var(--p-pad) !important;
    min-height: 56px !important;
    background: #ffffff;
    border-bottom: 1px solid var(--p-line);
    flex: 0 0 auto !important;   /* hindi lumiliit ang topbar kahit mahaba ang laman ng page - walang "galaw" kapag pumili ng row */
}

body .portal-title {
    margin: 0 !important;
    padding: 0 !important;
    
    font-size: 24px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    color: var(--p-plum) !important;
}
body .portal-subtitle { margin: 4px 0 0 !important; font-size: 12.5px; color: var(--p-muted) !important; }

body .app .top-actions { display: flex; align-items: center; gap: 14px; }

@media (max-width: 800px) {
    body .portal-title { font-size: 20px !important; }
}

/* ---------- CARDS ---------- */
body .app :is(.card:not(.qv-workspace), .st-card) {
    background: #ffffff;
    border: 1px solid var(--p-line) !important;
    border-radius: 22px !important;
    box-shadow: 0 10px 28px -18px rgba(74, 29, 59, .28) !important;
}

/* ---------- TABLES (qv / user / activity) ---------- */
body .app :is(.user-table, .activity-table) thead th {
    background: transparent !important;
    color: var(--p-muted) !important;
    border: 0 !important;
    border-bottom: 1.5px solid #ecd9e0 !important;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
body .app :is(.user-table, .activity-table) tbody td {
    border: 0 !important;
    border-bottom: 1px solid #f6e8ed !important;
    color: var(--p-plum);
}
body .app :is(.user-table, .activity-table) tbody tr:hover td { background: #fff8fa; }

/* pare-parehong taas ng header/row ng listahan (hindi gumagalaw kapag lumipat ng page) */
body .app .qv-table thead th {
    height: 36px;
    padding: 0 12px !important;
    line-height: 1.2;
    vertical-align: middle;
}
body .app .qv-table tbody td {
    height: 46px;
    padding: 0 12px !important;
    line-height: 1.3;
    vertical-align: middle;
}
body .app .qv-table tbody td :is(.order-status, .pay-badge) {
    display: inline-block;
    padding: 4px 10px !important;
    font-size: 11px !important;
    line-height: 1.2;
    white-space: nowrap;
}

/* ---------- STATUS CHIPS (Design System: Paid / Pending / Overdue / Shipped) ---------- */
body .app :is(.order-status, .status) { border-radius: 999px; font-weight: 700; box-shadow: none !important; }

body .app :is(.order-status, .status):is(.status-pending, .status-awaiting_payment, .status-awaiting_balance) { background: var(--p-warn-bg) !important; color: var(--p-warn) !important; }
body .app :is(.order-status, .status):is(.status-quoted, .status-to_verify, .status-for_approval, .status-to_verify_balance, .status-for_balance_approval, .status-completed) { background: var(--p-ok-bg) !important; color: var(--p-ok) !important; }
body .app :is(.order-status, .status):is(.status-processing) { background: var(--p-blush) !important; color: var(--p-primary) !important; }
body .app :is(.order-status, .status):is(.status-to_ship, .status-shipped) { background: var(--p-ship-bg) !important; color: var(--p-ship) !important; }
body .app :is(.order-status, .status):is(.status-cancelled, .status-for_refund) { background: var(--p-bad-bg) !important; color: var(--p-bad) !important; }
body .app :is(.order-status, .status):is(.status-refunded) { background: #efe3f3 !important; color: var(--p-plum) !important; }

/* ---------- FILTER + SEARCH ---------- */
body .app .rf-filter-trigger {
    height: 44px;
    padding: 0 16px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 14px !important;
    background: #ffffff !important;
    color: var(--p-muted) !important;
    font-size: 14px;
    font-weight: 600;
    box-shadow: none !important;
}
body .app main input.user-search {
    width: 240px;
    height: 44px;
    padding: 0 16px 0 40px;
    border: 1px solid var(--p-line);
    border-radius: 14px;
    background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239a8c94' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat 14px center / 16px 16px;
    font-size: 14px;
    color: var(--p-plum);
    outline: none;
}
body .app main input.user-search:focus { border-color: var(--p-primary); }

/* ---------- TABS (Content Management) - pill, kapareho ng Figure tabs ---------- */
body .app .cm-tabs .cm-tab {
    padding: 8px 18px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    color: var(--p-muted) !important;
    font-size: 13px;
    font-weight: 700;
    box-shadow: none !important;
    text-decoration: none;
}
body .app .cm-tabs .cm-tab:hover { border-color: #e9a6bd !important; color: var(--p-primary) !important; transform: none; }
body .app .cm-tabs .cm-tab.active { background: var(--p-primary) !important; border-color: var(--p-primary) !important; color: #ffffff !important; }

/* ---------- RIGHT PANEL BUTTONS: Primary (tuloy) + Secondary (tanggihan) ---------- */
body .app .qv-right-col .verify-actions { display: flex; flex-wrap: wrap; gap: 10px; }
body .app .qv-right-col .verify-actions > * { flex: 1 1 calc(50% - 5px); min-width: 0; }
body .app .qv-right-col .verify-actions > form > button,
body .app .qv-right-col .verify-actions > button { width: 100%; }
body .app .qv-right-col .verify-actions > .revision-decision-actions { flex-basis: 100%; display: flex; gap: 10px; }
body .app .qv-right-col .revision-decision-actions > * { flex: 1 1 0; }
body .app .qv-right-col .revision-decision-actions > form > button { width: 100%; }

body .app .qv-right-col :is(.verify-btn-approve, .qv-send-btn, .qv-ship-form-submit, .qv-manage-btn, .rf-btn-primary) {
    height: 44px;
    border: 1.5px solid var(--p-primary) !important;
    border-radius: 999px !important;
    background: var(--p-primary) !important;
    color: #ffffff !important;
    font-size: 14px;
    font-weight: 700;
}
body .app .qv-right-col :is(.verify-btn-approve, .qv-send-btn, .qv-ship-form-submit, .qv-manage-btn, .rf-btn-primary):hover {
    background: var(--p-primary-dark) !important;
    border-color: var(--p-primary-dark) !important;
}
body .app .qv-right-col :is(.verify-btn-reject, .rf-btn-ghost) {
    height: 44px;
    border: 1.5px solid var(--p-primary) !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    color: var(--p-primary) !important;
    font-size: 14px;
    font-weight: 700;
}
body .app .qv-right-col :is(.verify-btn-reject, .rf-btn-ghost):hover { background: var(--p-blush) !important; }

/* ---------- PAYMENT & SHIPPING / REFUND rows: label kaliwa, value kanan ---------- */
body .app .staff-payment-details { padding: 0 !important; border: 0 !important; background: transparent !important; box-shadow: none !important; }
body .app .staff-payment-details-title {
    margin: 0 0 8px;
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--p-primary);
}
body .app .staff-payment-details-grid { display: flex !important; flex-direction: column !important; gap: 0 !important; }
body .app .staff-payment-details-grid > span {
    display: grid;
    grid-template-columns: minmax(96px, 36%) 1fr;
    gap: 10px;
    padding: 7px 0;
    font-size: 14px;
    font-weight: 500;
    color: var(--p-plum);
    word-break: break-word;
}
body .app .staff-payment-details-grid > span > strong { font-weight: 500; color: var(--p-muted); }

/* ---------- pare-parehong pagitan ng mga container sa kanang panel ---------- */
body .app .qv-right-col .qv-detail-col { padding: 0; }

/* ==========================================================
   LISTA (kaliwa) + DETALYE (kanan) - All Bookings, Quotation, Verification, atbp.
   ========================================================== */

body .app .qv-workspace { padding: 0 !important; }
body .app .qv-shell {
    grid-template-columns: minmax(0, 1fr) minmax(400px, 470px);
    gap: 20px;
}
@media (max-width: 1180px) {
    body .app .qv-shell { grid-template-columns: 1fr; }
}

/* ---------- two white cards ---------- */
body .app .qv-list-panel,
body .app .qv-right-col {
    background: #ffffff !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 22px !important;
    box-shadow: 0 10px 28px -18px rgba(74, 29, 59, .28) !important;
}
body .app .qv-list-panel { padding: 18px !important; }
body .app .qv-right-col { padding: 22px 24px 24px !important; gap: 14px !important; }

/* ---------- left: toolbar ---------- */
body .app .qv-toolbar { gap: 12px; margin-bottom: 14px; align-items: stretch; }

body .app .qv-filter-trigger {
    height: 44px;
    padding: 0 16px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 14px !important;
    background: #ffffff !important;
    color: var(--p-muted) !important;
    font-size: 14px;
    font-weight: 600;
    box-shadow: none !important;
}
body .app .qv-filter-trigger:hover { border-color: #e9a6bd !important; }

body .app .qv-search {
    height: 44px;
    padding: 0 16px;
    border: 1px solid var(--p-line);
    border-radius: 14px;
    background: #ffffff;
}
body .app .qv-search input { font-size: 14px; color: var(--p-plum); }

/* ---------- left: table ---------- */
body .app .qv-table thead th {
    background: transparent !important;
    color: var(--p-muted) !important;
    border: 0 !important;
    border-bottom: 1.5px solid #ecd9e0 !important;
    font-size: 11px !important;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
body .app .qv-table tbody td {
    border: 0 !important;
    border-bottom: 1px solid #f6e8ed !important;
    font-size: 12.5px !important;
    color: var(--p-plum);
}
body .app .qv-table tbody tr:hover td { background: #fff8fa; }
body .app .qv-table tbody tr.active td { background: #ffe9ee !important; }
body .app .qv-cell-id { font-weight: 800; }
body .app .qv-table tbody td :is(.order-status, .pay-badge) {
    border-radius: 999px !important;
    font-weight: 700;
}
body .app .qv-list-footer { padding-top: 12px; font-size: 12px; color: var(--p-muted); }
body .app .qv-empty { color: var(--p-muted); }

/* ---------- right: head (title + status) ---------- */
body .app .qv-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
body .app .qv-panel-title {
    margin: 0;
    
    font-size: 26px;
    font-weight: 700;
    color: var(--p-plum);
}
body .app .qv-panel-head .order-status {
    padding: 5px 14px !important;
    border-radius: 999px !important;
    font-size: 11.5px !important;
    font-weight: 700;
}

/* inner cards become plain sections of the single right card */
body .app .qv-right-col .qv-header-card,
body .app .qv-right-col .qv-figure-card,
body .app .qv-right-col .qv-box-card,
body .app .qv-right-col .qv-box-card-empty,
body .app .qv-right-col .qv-summary {
    background: transparent !important;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
}
body .app .qv-right-col .qv-detail-col { padding: 0 !important; gap: 14px !important; }
body .app .qv-right-col .qv-box-card,
body .app .qv-right-col .qv-summary {
    padding-top: 16px !important;
    border-top: 1px solid #f3e6ea !important;
}

/* section headings */
body .app .qv-details-head,
body .app .qv-box-info-head,
body .app .qv-summary-head h2 {
    margin: 0 0 8px;
    
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--p-primary);
}
body .app .qv-summary-head {
    background: transparent !important;
    padding: 0 !important;
    border-radius: 0 !important;
}
body .app .qv-summary-body { padding: 0 !important; }

/* figure tabs */
body .app .qv-figure-tabs { gap: 8px; margin-bottom: 12px; }
body .app .qv-figure-tab-pill {
    padding: 8px 18px;
    border: 1px solid var(--p-line);
    border-radius: 999px;
    background: #ffffff;
    color: var(--p-muted);
    font-size: 13px;
    font-weight: 700;
}
body .app .qv-figure-tab-pill:hover { border-color: #e9a6bd; color: var(--p-primary); }
body .app .qv-figure-tab-pill.active {
    background: var(--p-primary);
    border-color: var(--p-primary);
    color: #ffffff;
}

/* ---------- key / value rows ---------- */
body .app .qv-detail-item-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
}
body .app .qv-detail-item-card {
    display: grid !important;
    grid-template-columns: auto 1fr auto;
    align-items: center !important;
    gap: 0 10px !important;
    padding: 9px 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
}
body .app .qv-detail-item-image { display: none !important; }
body .app .qv-detail-item-body { display: contents !important; }
body .app .qv-detail-item-label {
    grid-column: 1;
    font-size: 14px;
    font-weight: 500;
    color: var(--p-muted);
}
body .app .qv-detail-item-value {
    grid-column: 2;
    justify-self: end;
    text-align: right;
    font-size: 14px;
    font-weight: 800;
    color: var(--p-plum);
}
body .app .qv-detail-item-price {
    grid-column: 3;
    padding: 2px 9px;
    border: 1px solid #ecd9e0;
    border-radius: 999px;
    background: #fffafb;
    font-size: 11px;
    font-weight: 700;
    color: var(--p-muted);
}

/* box spec rows */
body .app .qv-box-spec-grid {
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
}
body .app .qv-box-spec-grid > div {
    display: flex !important;
    align-items: baseline;
    gap: 8px;
    padding: 8px 0;
    grid-column: auto !important;
}
body .app .qv-spec-label {
    margin-right: auto;
    font-size: 14px;
    font-weight: 500;
    color: var(--p-muted);
}
body .app .qv-spec-value {
    font-size: 14px;
    font-weight: 800;
    color: var(--p-plum);
    text-align: right;
}
body .app .qv-spec-price { font-size: 13px; font-weight: 800; color: var(--p-plum); }

/* reference images */
body .app .qv-reference-label,
body .app .qv-notes-row .qv-spec-label {
    margin: 10px 0 8px;
    font-size: 13px;
    font-weight: 800;
    color: var(--p-plum);
}
body .app .qv-reference-thumbs { gap: 8px; }
body .app .qv-ref-thumb {
    width: 70px !important;
    height: 70px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 14px !important;
    overflow: hidden;
}

/* customer notes */
body .app .qv-note-box {
    padding: 14px 16px !important;
    border: 0 !important;
    border-radius: 14px !important;
    background: #fff4f6 !important;
    font-size: 14px;
    color: var(--p-plum);
}

/* ---------- quotation lines ---------- */
body .app .qv-line-list { gap: 10px; font-weight: 500; }
body .app .qv-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
body .app .qv-line-label { font-size: 14px; font-weight: 500; color: var(--p-muted); }
body .app .qv-line-value { font-size: 14px; font-weight: 800; color: var(--p-plum); }

body .app .qv-line-input-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: 150px;
    height: 46px;
    padding: 0 14px;
    border: 1px solid #ecd9e0;
    border-radius: 14px;
    background: #ffffff;
    color: var(--p-muted);
}
body .app .qv-line-input-wrap:focus-within { border-color: var(--p-primary); }
body .app .qv-line-input {
    width: 100%;
    border: 0 !important;
    outline: none;
    background: transparent !important;
    font-size: 15px;
    color: var(--p-muted);
}

body .app .qv-divider {
    border: 0;
    border-top: 1.5px dashed #ecd9e0;
    margin: 14px 0;
}
body .app .qv-total-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
body .app .qv-total-row > span { font-size: 14px; font-weight: 800; color: var(--p-plum); }
body .app .qv-total-row > strong {
    
    font-size: 30px;
    font-weight: 700;
    color: var(--p-primary);
}
body .app .qv-summary-hint {
    margin: 12px 0 0;
    font-size: 12px;
    line-height: 1.6;
    color: var(--p-muted);
}

/* ---------- buttons: Decline (outline) + Send Quotation (filled) ---------- */
body .app .qv-quote-actions {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-start;
    gap: 10px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f3e6ea;
}
body .app .qv-right-col .qv-quote-actions > * { flex: 0 0 auto !important; width: auto !important; }
body .app .qv-right-col .qv-quote-actions button {
    height: 44px;
    padding: 0 22px !important;
    border-radius: 999px !important;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
body .app .qv-right-col .qv-send-btn {
    background: var(--p-primary) !important;
    border: 1.5px solid var(--p-primary) !important;
    color: #ffffff !important;
}
body .app .qv-right-col .qv-send-btn:hover { background: #a82a52 !important; border-color: #a82a52 !important; }
body .app .qv-right-col .verify-btn-reject {
    background: #ffffff !important;
    border: 1.5px solid var(--p-primary) !important;
    color: var(--p-primary) !important;
}
body .app .qv-right-col .verify-btn-reject:hover { background: var(--p-blush) !important; }

/* ---------- decline pop-up ---------- */
body .decline-modal-box {
    border-radius: 22px;
    padding: 28px;
    box-shadow: 0 24px 60px -18px rgba(45, 14, 32, .5);
}
body .decline-modal-title {
    
    font-size: 24px;
    color: var(--p-plum);
}
body .decline-modal-desc { font-size: 13px; color: var(--p-muted); }
body .decline-reason-select,
body .decline-reason-other {
    height: 48px;
    border: 1px solid #ecd9e0;
    border-radius: 14px;
    background: #ffffff;
    color: var(--p-plum);
    font-size: 14px;
}
body .decline-reason-other { height: auto; min-height: 48px; }
body .decline-modal-actions { justify-content: flex-end; gap: 10px; }
body .decline-modal-btn { height: 40px; padding: 0 20px; border-radius: 12px; font-weight: 700; }
body .decline-modal-btn-cancel { background: var(--p-blush); border: 0; color: var(--p-primary); }
body .decline-modal-btn-send { background: var(--p-primary); border: 0; color: #ffffff; }


/* ==========================================================
   LISTAHAN SA KALIWA - pare-pareho sa LAHAT ng owner/staff page
   (All Bookings, Quotation, Verification, For Approval, Active Bookings,
   Shipments, Completed, Cancelled, Refunds, Payments)
   - Filter sa kaliwa, Search sa kanan (sakto ang haba)
   - Kasya ang buong table sa card: walang pahalang na scroll
   ========================================================== */

/* toolbar */
body .app .qv-list-panel .qv-toolbar {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 10px !important;
    margin: 0 0 14px !important;
}
body .app .qv-list-panel .qv-toolbar > :is(.qv-filter-dropdown, .qv-tabs, .ab-status-tabs, .qv-status-tabs) {
    flex: 0 0 auto;
    margin: 0 auto 0 0 !important;   /* filter / tabs: laging nasa kaliwa */
}
body .app .qv-list-panel .qv-filter-trigger {
    min-width: 0 !important;
    height: 42px !important;
    padding: 0 14px !important;
    gap: 8px;
    border: 1px solid #ecd9e0 !important;
    border-radius: 12px !important;
    background: #ffffff !important;
    color: var(--p-plum) !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    white-space: nowrap;
}
body .app .qv-list-panel .qv-search {
    flex: 0 1 280px !important;      /* search: laging nasa kanan, sakto ang haba */
    width: 280px !important;
    max-width: 55% !important;
    min-width: 160px;
    height: 42px !important;
    margin: 0 0 0 auto !important;
    padding: 0 14px !important;
    display: flex !important;
    align-items: center;
    gap: 8px;
    border: 1px solid #ecd9e0 !important;
    border-radius: 12px !important;
    background: #ffffff !important;
}
body .app .qv-list-panel .qv-search svg { flex: 0 0 auto; width: 14px; height: 14px; color: #9a8c94; }
body .app .qv-list-panel .qv-search input {
    flex: 1 1 auto;
    width: 100% !important;
    min-width: 0;
    height: 100%;
    padding: 0 !important;
    border: 0 !important;
    outline: none;
    background: transparent !important;
    font-size: 13.5px !important;
    color: var(--p-plum) !important;
}
body .app .qv-list-panel .qv-search input::placeholder { color: #8f7d86; }
body .app .qv-list-panel .qv-search:focus-within { border-color: var(--p-primary) !important; }

/* table - kasya sa card */
body .app .qv-list-panel .qv-list {
    overflow-x: hidden !important;
    overflow-y: auto;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}
body .app .qv-list-panel .qv-table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: auto !important;
    border-collapse: collapse;
}
body .app .qv-list-panel .qv-table :is(th, td) {
    width: auto !important;
    min-width: 0 !important;
    max-width: none !important;
    white-space: normal !important;   /* bumababa sa 2 linya kapag kulang ang lapad */
    overflow-wrap: anywhere;
    overflow: visible !important;
    text-overflow: clip !important;
}
body .app .qv-list-panel .qv-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    height: 34px !important;
    padding: 0 5px !important;
    background: #ffffff !important;
    border: 0 !important;
    border-bottom: 1.5px solid #ecd9e0 !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    letter-spacing: .04em !important;
    line-height: 1.2;
    color: var(--p-muted) !important;
    text-transform: uppercase;
    overflow-wrap: normal;
}
body .app .qv-list-panel .qv-table tbody td {
    height: 40px !important;
    padding: 6px 5px !important;
    border: 0 !important;
    border-bottom: 1px solid #f6e8ed !important;
    font-size: 11.5px !important;
    font-weight: 500;
    line-height: 1.3 !important;
    color: var(--p-plum) !important;
    vertical-align: middle;
}
body .app .qv-list-panel .qv-table tbody td:first-child { font-weight: 800 !important; white-space: nowrap !important; }
body .app .qv-list-panel .qv-table tbody td :is(.order-status, .status, .op-chip, .pay-badge) {
    display: inline-block;
    max-width: none !important;
    padding: 3px 8px !important;
    font-size: 10.5px !important;
    line-height: 1.3 !important;
    white-space: nowrap !important;
}
body .app .qv-list-panel .qv-table tbody tr { cursor: pointer; }
body .app .qv-list-panel .qv-table tbody tr:hover td { background: #fff8fa !important; }
body .app .qv-list-panel .qv-table tbody tr.active td { background: #ffe9ee !important; }

body .app .qv-list-panel .qv-list-footer {
    padding: 12px 0 0 !important;
    text-align: left !important;
    font-size: 12px !important;
    color: var(--p-muted) !important;
}

@media (max-width: 600px) {
    body .app .qv-list-panel .qv-toolbar { flex-wrap: wrap !important; }
    body .app .qv-list-panel .qv-search { flex: 1 1 100% !important; width: 100% !important; max-width: none !important; }
}
</style>
    <?php require_once __DIR__ . "/sidebar.php"; ?>

    <main class="main">

        <header class="topbar">

            <div class="welcome">
                <h2 class="portal-title">Statistics</h2>
            </div>

            <div class="top-actions">
                <?php include __DIR__ . "/notification-bell.php"; ?>
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

                <div class="card st-card st-kpi st-kpi-hero">
                    <div class="st-kpi-label">Total revenue</div>
                    <div class="st-kpi-value"><?php echo figurify_peso($salesSummary["total_revenue"]); ?></div>
                    <span class="st-pill">All-time · from paid orders</span>
                </div>

                <div class="card st-card st-kpi">
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

                <div class="card st-card st-kpi">
                    <div class="st-kpi-label">Paid orders</div>
                    <div class="st-kpi-value"><?php echo number_format($salesSummary["paid_orders"]); ?></div>
                    <div class="st-kpi-foot">Confirmed &amp; paid · all-time</div>
                </div>

                <div class="card st-card st-kpi">
                    <div class="st-kpi-label">Avg order value</div>
                    <div class="st-kpi-value"><?php echo figurify_peso($salesSummary["avg_order_value"]); ?></div>
                    <div class="st-kpi-foot">
                        Avg per day <?php echo figurify_peso($rangeAvgPerDay); ?> · Completed this month <?php echo number_format($completedThisMonth); ?>
                    </div>
                </div>

            </section>


            <!-- SALES OVERVIEW + ORDER MIX -->

            <div class="st-grid-main">

                <section class="card st-card">

                    <h3 class="st-card-title">Sales overview</h3>
                    <p class="st-card-sub">Daily sales from paid orders — <?php echo e($rangeText); ?></p>

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
                            <span class="st-sum-value"><?php echo $bestDay ? date("M j", strtotime($bestDay["date"])) : "—"; ?></span>
                        </div>
                    </div>

                    <?php if ($rangeRevenue <= 0): ?>

                        <div class="st-empty">Wala pang benta sa <?php echo $rangeKey === "month" ? "buwang ito" : "huling " . $rangeDays . " araw"; ?>.</div>

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


                <section class="card st-card">

                    <h3 class="st-card-title">Order mix</h3>
                    <p class="st-card-sub"><?php echo number_format($styleMix["orders"]); ?> active &amp; completed order<?php echo $styleMix["orders"] === 1 ? "" : "s"; ?></p>

                    <?php if ($styleMix["figures"] === 0): ?>

                        <div class="st-empty">Wala pang order.</div>

                    <?php else: ?>

                        <ul class="st-bar-list">
                            <?php foreach ($styleMix["styles"] as $style): ?>
                                <?php $stylePct = $style["count"] / $styleMix["figures"] * 100; ?>
                                <li title="<?php echo e($style["label"]) . ": " . $style["count"] . " figure" . ($style["count"] === 1 ? "" : "s"); ?>">
                                    <div class="st-bar-top">
                                        <span class="st-bar-name"><?php echo e($style["label"]); ?></span>
                                        <b><?php echo number_format($stylePct, 0); ?>%</b>
                                    </div>
                                    <div class="st-bar"><span style="width: <?php echo round($stylePct, 1); ?>%;"></span></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                    <?php endif; ?>

                </section>

            </div>


            <!-- ORDERS BY STATUS + TOP PRODUCTS -->

            <div class="st-grid-bottom">

                <section class="card st-card">

                    <h3 class="st-card-title">Orders by status</h3>
                    <p class="st-card-sub"><?php echo number_format($totalOrders); ?> order<?php echo $totalOrders === 1 ? "" : "s"; ?> in total</p>

                    <?php if ($totalOrders === 0): ?>

                        <div class="st-empty">Wala pang order.</div>

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
                                        <i style="background: <?php echo $group["color"]; ?>;"></i>
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


                <section class="card st-card">

                    <h3 class="st-card-title">Top products</h3>
                    <p class="st-card-sub">Most ordered figures from paid orders</p>

                    <?php if (empty($topProducts)): ?>

                        <div class="st-empty">Wala pang paid na order.</div>

                    <?php else: ?>

                        <ol class="st-bar-list">
                            <?php foreach ($topProducts as $i => $product): ?>
                                <?php
                                $productCount = (int) $product["total_ordered"];
                                $productWidth = $maxProductCount > 0 ? max(3, $productCount / $maxProductCount * 100) : 0;
                                $productName  = trim($product["figure_style"] . " · " . $product["product_type"], " ·");
                                ?>
                                <li title="<?php echo e($productName) . " — " . figurify_peso($product["total_revenue"] ?? 0); ?>">
                                    <div class="st-bar-top">
                                        <span class="st-bar-name"><strong>#<?php echo $i + 1; ?></strong> <?php echo e($productName); ?></span>
                                        <b><?php echo number_format($productCount); ?> sold</b>
                                    </div>
                                    <div class="st-bar"><span style="width: <?php echo round($productWidth, 1); ?>%;"></span></div>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                    <?php endif; ?>

                </section>

            </div>

        </div>

    </main>

</div>


<?php if ($rangeRevenue > 0): ?>
<script>
/* SALES CHART - area line (SVG, walang library)
   - Revenue / Orders na tab
   - hover/tap = guide line + tuldok + tooltip */
(function () {

    var data    = <?php echo json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var wrap    = document.getElementById("stSalesChart");
    var tooltip = document.getElementById("stSalesTooltip");
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
            tooltip.style.left = Math.max(80, Math.min(rect.width - 80, p.x * (rect.width / W))) + "px";
            tooltip.style.top = (p.y * (rect.height / H)) + "px";
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
</script>
<?php endif; ?>

</body>
</html>
