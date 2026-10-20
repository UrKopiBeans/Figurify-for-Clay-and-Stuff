<?php

require_once __DIR__ . "/staff-header.php";


require_once __DIR__ . "/../helpers/booking_helper.php";

/* Full na petsa lang ang kinukulayan sa dashboard calendar (hindi ang may 1 order lang) */
$bookedDates = figurify_get_full_dates($conn);


// Same label mapping ng All Bookings page (reference -> Image
// Submission, create_style -> Dress Up)
function orderMethodLabel($method)
{
    $labels = [
        "reference"    => "Image Submission",
        "create_style" => "Dress Up",
    ];

    return $labels[$method] ?? "Image Submission";
}


/* DEFAULT DASHBOARD DATA */

$allBookings       = 0;
$pendingBookings   = 0;
$forApproval       = 0;
$activeOrders      = 0;
$upcomingShipments = 0;

$upcomingShipmentOrders = [];

$shipmentSummaryOrders = [];

$recentOrders = [];


// renders the Shipment Summary widget - iisang grid na lang ng order cards
function figurify_render_shipment_summary(array $orders, string $emptyText, int $limit = 12): void
{

    $count = count($orders);

    /* "Today" (walang oras) - para ma-compute ilang araw na lang bago mag-due. */
    $todayTs = strtotime(date("Y-m-d"));

    ?>

    <div class="shipment-summary-grid">

        <?php if ($count === 0): ?>

            <div class="shipment-bucket-empty"><?php echo e($emptyText); ?></div>

        <?php else: ?>

            <?php foreach (array_slice($orders, 0, $limit) as $order): ?>

                <?php
                // Isang badge bawat card - Overdue / Shipping Tomorrow /
                // Due in Xd / Ready to Ship.
                $cardClass  = "shipment-bucket-item";
                $badgeClass = "shipment-bucket-item-badge-ready";
                $badgeLabel = "Ready to Ship";

                if (!empty($order["booking_date"])) {

                    $orderDateTs = strtotime($order["booking_date"]);
                    $daysUntil   = (int) floor(($orderDateTs - $todayTs) / 86400);

                    if ($daysUntil < 0) {
                        $cardClass  = "shipment-bucket-item shipment-bucket-item-overdue";
                        $badgeClass = "shipment-bucket-item-badge-overdue";
                        $badgeLabel = ($daysUntil === -1)
                            ? "1 day overdue"
                            : abs($daysUntil) . " days overdue";
                    } elseif ($daysUntil === 1) {
                        $cardClass  = "shipment-bucket-item shipment-bucket-item-duesoon";
                        $badgeClass = "shipment-bucket-item-badge-tomorrow";
                        $badgeLabel = "Shipping Tomorrow";
                    } elseif ($daysUntil >= 0 && $daysUntil <= 3) {
                        $cardClass  = "shipment-bucket-item shipment-bucket-item-duesoon";
                        $badgeClass = "shipment-bucket-item-badge-soon";
                        $badgeLabel = ($daysUntil === 0) ? "Due Today" : "Due in " . $daysUntil . "d";
                    }

                }
                ?>

                <div
                    class="<?php echo e($cardClass); ?>"
                >
                    <div class="shipment-bucket-item-top">
                        <span class="shipment-bucket-item-id">
                            Order <?php echo (int) $order["order_id"]; ?>
                        </span>

                        <span class="shipment-bucket-item-badge <?php echo e($badgeClass); ?>">
                            <?php echo e($badgeLabel); ?>
                        </span>
                    </div>

                    <div class="shipment-bucket-item-details">
                        <div class="shipment-bucket-item-field">
                            <span class="shipment-bucket-item-label">Order Type</span>
                            <span class="shipment-bucket-item-value">
                                <?php echo ($order["order_type"] ?? "") === "rush" ? "Rush" : "Non-Rush"; ?>
                            </span>
                        </div>

                        <div class="shipment-bucket-item-field">
                            <span class="shipment-bucket-item-label">Order Method</span>
                            <span class="shipment-bucket-item-value">
                                <?php echo e(orderMethodLabel($order["order_method"] ?? "")); ?>
                            </span>
                        </div>

                        <div class="shipment-bucket-item-field">
                            <span class="shipment-bucket-item-label">Booking Date</span>
                            <span class="shipment-bucket-item-value">
                                <?php
                                    echo !empty($order["booking_date"])
                                        ? e(date("M d, Y", strtotime($order["booking_date"])))
                                        : "—";
                                ?>
                            </span>
                        </div>

                        <div class="shipment-bucket-item-field">
                            <span class="shipment-bucket-item-label">Courier</span>
                            <span class="shipment-bucket-item-value">
                                <?php
                                    echo !empty($order["courier"])
                                        ? e($order["courier"])
                                        : "No courier yet";
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

            <?php if ($count > $limit): ?>
                <div class="shipment-bucket-more">
                    +<?php echo ($count - $limit); ?> more
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <?php
}


/* CHECK ORDERS TABLE */

$ordersTableExists = false;

$checkTable = $conn->query("SHOW TABLES LIKE 'orders'");

if ($checkTable && $checkTable->num_rows > 0) {

    $ordersTableExists = true;


    /* TOTAL BOOKINGS */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $allBookings = (int)($row["total"] ?? 0);
    }


    /* PENDING BOOKINGS */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE LOWER(status) = 'pending'
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $pendingBookings = (int)($row["total"] ?? 0);
    }


    /* FOR APPROVAL */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE LOWER(status) IN (
            'forapproval',
            'for approval'
        )
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $forApproval = (int)($row["total"] ?? 0);
    }


    /* ACTIVE ORDERS */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE LOWER(status) IN (
            'processing',
            'on process',
            'active',
            'paid'
        )
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $activeOrders = (int)($row["total"] ?? 0);
    }


    // Upcoming shipments - "to_ship" orders na 2 days na lang o mas
    // malapit ang booking_date (kasama nakaraan na). See booking_helper.php.

    $upcomingShipmentOrders = figurify_get_upcoming_shipments($conn);

    $upcomingShipments = count($upcomingShipmentOrders);


    // Shipment summary widget - lahat ng "to_ship" orders, pinaka-
    // malapit nang due muna. Same figurify_get_all_shipments helper
    // ng shipments.php.

    $shipmentSummaryOrders = figurify_get_all_shipments($conn, "to_ship");

    $shipmentSummaryToday = strtotime(date("Y-m-d"));

    // isang listahan, pinakamalapit na booking_date muna
    usort($shipmentSummaryOrders, function ($a, $b) use ($shipmentSummaryToday) {
        $aDays = !empty($a["booking_date"])
            ? (int) floor((strtotime($a["booking_date"]) - $shipmentSummaryToday) / 86400)
            : PHP_INT_MAX;
        $bDays = !empty($b["booking_date"])
            ? (int) floor((strtotime($b["booking_date"]) - $shipmentSummaryToday) / 86400)
            : PHP_INT_MAX;

        return $aDays <=> $bDays;
    });


    /* RECENT ORDERS */

    $recentResult = $conn->query("
        SELECT
            o.order_id,
            o.order_type,
            o.order_method,
            o.booking_date,
            o.status,
            o.total_amount,
            o.created_at,
            u.full_name AS customer_name
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        ORDER BY o.created_at DESC
        LIMIT 6
    ");

    if ($recentResult) {

        while ($row = $recentResult->fetch_assoc()) {

            $recentOrders[] = $row;
        }
    }
}




?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Figurify — Staff Dashboard</title>

<style>

/* dashboard styles */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --pink: #f78fd4;
    --pink-dark: #b23e82;
    --pink-light: #ffc4e8;
    --pink-soft: #fff0f9;

    --black: #29252a;
    --gray: #81777d;
    --gray-light: #eee7ea;

    --white: #ffffff;
    --background: #f8f4f5;

    --green: #65a77e;
    --green-light: #e1f3e7;

    --yellow: #d59b2b;
    --yellow-light: #fff1cc;

    --blue: #6689dd;
    --blue-light: #e8edff;

    --red: #d96b78;
    --red-light: #ffe4e8;
}

html {
    scroll-behavior: smooth;
}

body {
    
    color: var(--black);
    min-height: 100vh;


    

}

button,
input,
a {
    
}

button,
a {
    cursor: pointer;
}

a {
    text-decoration: none;
    color: inherit;
}

/* APP */

.app {
    min-height: 100vh;
}

/* SIDEBAR */








/* MAIN */

.main {
    margin-left: 230px;

    width: calc(100% - 230px);

    padding: 22px 22px 38px 14px;
}

/* TOPBAR */

.topbar {
    display: flex;

    align-items: flex-start;
    justify-content: space-between;

    margin-bottom: 16px;
}

.welcome-label {
    color: var(--pink-dark);

    font-size: 9px;

    font-weight: 900;

    letter-spacing: 1.7px;

    margin-bottom: 5px;
}

.welcome h2 {
    font-size: 27px;

    letter-spacing: -1px;
}

.welcome p {
    color: var(--gray);

    font-size: 12px;

    margin-top: 5px;
}

/* date time - puting container (kasya lang sa laman), walang icon */
.datetime {
    display: inline-flex;
    align-items: center;

    gap: 9px;

    margin-top: 12px;
    padding: 8px 16px;

    background: #ffffff;
    border: 1px solid #f3e4ec;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(74,48,76,.06);

    color: #8b747d;

    font-size: 12px;

    font-weight: 700;
}

#currentDate {
    color: #765f68;
}

#currentTime {
    color: #a4476d;
    font-weight: 900;
}

.datetime-dot {
    color: #c7aeb8;
}

/* TOP ACTIONS */

.top-actions {
    display: flex;
    align-items: center;

    gap: 9px;
}

.search-box {
    width: 220px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    padding: 11px 13px;

    outline: none;

    font-size: 11px;

    transition: .2s;
}

.search-box:focus {
    border-color: var(--pink);

    box-shadow:
        0 0 0 3px var(--pink-light);
}

.notification-btn {
    position: relative;

    width: 42px;
    height: 42px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    font-size: 17px;

    transition: .2s;
}

.notification-btn:hover {
    background: var(--pink-soft);
}

.notification-badge {
    position: absolute;

    top: -5px;
    right: -5px;

    min-width: 17px;
    height: 17px;

    padding: 0 4px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--pink);

    color: white;

    border-radius: 50%;

    border: 2px solid var(--background);

    font-size: 8px;
    font-weight: 900;
}

/* CARDS */

.card {
    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 18px;

    padding: 17px;

    box-shadow:
        0 5px 16px rgba(71,45,55,.04);
}

/* STATISTICS */

.stats {
    display: grid;

    grid-template-columns:
        3fr 1.1fr;

    align-items: start;

    gap: 14px;

    margin-bottom: 18px;
}

.stats-left {
    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    align-items: stretch;

    gap: 14px;
}

.stats-left .stat-card {
    height: 100%;
}

.stat-card {
    position: relative;

    overflow: hidden;
}

.stat-card::after {
    content: "";

    position: absolute;

    width: 80px;
    height: 80px;

    border-radius: 50%;

    background: var(--pink-light);

    right: -28px;
    top: -30px;
}

.pending-card::after {
    background: var(--blue-light);
}

.approval-card::after {
    background: var(--yellow-light);
}

/* Kulay ng bilog = kulay ng status (helpers/order_status_helper.php)
   Pending = Pending, For Approval = For Approval, Active Orders = Processing,
   Shipment Summary = Ready to Ship. Ang Total Bookings ay walang iisang status,
   kaya brand pink pa rin. Laging nasa likod ang bilog (z-index 0) at nasa
   ibabaw ang laman ng card, kaya walang natatakpan na text. */
.stat-card::after {
    z-index: 0;
    pointer-events: none;
}

.stat-card > * {
    position: relative;
    z-index: 1;
}

.pending-card::after {
    background: <?php echo FIGURIFY_ORDER_STATUSES["pending"]["bg"]; ?>;
}

.approval-card::after {
    background: <?php echo FIGURIFY_ORDER_STATUSES["for_approval"]["bg"]; ?>;
}

.active-card::after {
    background: <?php echo FIGURIFY_ORDER_STATUSES["processing"]["bg"]; ?>;
}

.shipment-stat-card::after {
    background: <?php echo FIGURIFY_ORDER_STATUSES["to_ship"]["bg"]; ?>;
}

.stat-label {
    color: var(--black);

    font-size: 10px;

    font-weight: 900;
}

.stat-number {
    font-size: 30px;

    font-weight: 900;

    margin-top: 6px;
}

.stat-trend {
    margin-top: 5px;

    font-size: 10px;

    font-weight: 800;

    color: var(--green);
}

.attention {
    color: var(--yellow);
}

.shipment-stat-card {
    z-index: 1;

    display: flex;

    flex-direction: column;
}

.shipment-stat-card .stat-label {
    margin-bottom: 12px;
}

.shipment-stat-card .shipment-list {
    flex: 1;
}

/* Dashboard layout: left = stats + calendar + recent bookings,
   right = tall shipment card */

.dashboard-layout {
    display: grid;

    grid-template-columns:
        2.3fr 1fr;

    gap: 18px;

    align-items: stretch;

    margin-bottom: 18px;
}

.dashboard-left {
    display: flex;
    flex-direction: column;

    gap: 18px;
}

.dashboard-right {
    display: flex;

    height: 100%;
}

.dashboard-right .shipment-stat-card {
    flex: 1;

    width: 100%;
    height: 100%;
}

.bottom-row {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: stretch;
}

/* Pinapantay ang taas ng Recent Bookings card sa Calendar card sa
   tabi nito, flex column para umabot sa buong height ng grid cell */
.bottom-row > .card {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.bottom-row > .card #orders {
    flex: 1;
    overflow-y: auto;
}

/* DASHBOARD GRID */

.dashboard-grid {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* CARD HEADER */

.card-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 15px;
}

.card-header h3 {
    font-size: 15px;
}

.view-link {
    border: none;

    background: transparent;

    color: #a4476d;

    font-size: 11px;

    font-weight: 800;
}

.view-link:hover {
    text-decoration: underline;
}

/* ORDERS */

.order-row {
    display: grid;

    grid-template-columns:
        1.2fr .9fr .75fr .75fr;

    gap: 8px;

    align-items: center;

    padding: 13px 3px;

    border-top: 1px solid #f0e7eb;

    font-size: 11px;
}

.order-row.header {
    border-top: none;

    color: var(--black);

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}

.customer {
    display: flex;

    align-items: center;

    gap: 8px;
}

.customer-icon {
    width: 31px;
    height: 31px;

    border-radius: 10px;

    background: #fbe5ed;

    display: flex;
    align-items: center;
    justify-content: center;
}

.customer strong {
    display: block;

    font-size: 11px;
}

.customer small {
    display: block;

    color: #9a8c94;

    font-size: 9px;

    margin-top: 2px;
}

/* STATUS */

.status {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
}

/* Status colors - pareho sa Dashboard, Calendar, at My Orders,
   iisang source ang mga kulay: Admin/order_status_helper.php */

.status-pending {
    background: #FFF3C4;
    color: #29252A;
}

.status-quoted {
    background: #E1F3E7;
    color: #29252A;
}

.status-awaiting_payment {
    background: #FBE8D6;
    color: #29252A; white-space: nowrap; }

.status-to_verify {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-processing {
    background: #FBE8D6;
    color: #29252A;
}

.status-awaiting_balance {
    background: #FBE8D6;
    color: #29252A;
}

.status-to_verify_balance {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_balance_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-to_ship {
    background: #E8EDFF;
    color: #29252A;
}

.status-shipped {
    background: #E8EDFF;
    color: #29252A;
}

.status-completed {
    background: #E1F3E7;
    color: #29252A;
}

.status-cancelled {
    background: #FADCDC;
    color: #29252A;
}

/* CALENDAR CONTROLS */

.calendar-controls {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 10px;
}

.calendar-controls button {
    width: 30px;
    height: 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 50%;

    font-size: 16px;
    line-height: 1;
    font-weight: 900;

    cursor: pointer;

    transition: .2s;
}

.calendar-controls button:hover {
    background: var(--pink);
    color: white;

    transform: scale(1.06);
}

.calendar-controls strong {
    font-size: 12px;
}

.calendar-arrow-icon {
    display: block;
}

/* CALENDAR */

.calendar-week {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;

    margin-bottom: 3px;
}

.calendar-week div {
    text-align: center;

    color: var(--black);

    font-size: 8px;
    font-weight: 900;

    padding: 4px 0;
}

.calendar-days {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;
}

.day {
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;

    border-radius: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 10px;

    transition: .2s;
}

.day:hover {
    background: #F1F0F4;
    cursor: pointer;
}

.day.empty {
    background: transparent;
}

.day.available {
    background: #fffbed;
}

.day.booked {
    background: #f6dede;

    color: #c98080;

    font-weight: 800;

    text-decoration: line-through;
}

.day.unavailable {
    background: #f4f0f3;

    color: #c5b9c0;
}

.day.selected {
    background: #f4a4ca;

    color: white;

    outline: 2px solid #c9689a;
    outline-offset: 1px;

    font-weight: 800;
}

/* Current day - pastel pink fill (mas mapusyaw kaysa .selected),
   walang border/glow. Dapat nasa dulo para hindi ma-override. */
.day.today {
    background: #ffe3f1;

    color: #b6608f;

    font-weight: 900;
}

.day.selected.today {
    background: #f4a4ca;

    color: white;

    outline: 2px solid #c9689a;
    outline-offset: 1px;

    font-weight: 900;
}

/* Dashboard calendar (#calendarDays) is read-only preview lang -
   disabled ang hover/click sa bawat araw */
#calendarDays .day {
    pointer-events: none;
}

#calendarDays .day:hover {
    background: inherit;
    cursor: default;
}

/* CALENDAR LEGEND */

.calendar-legend {
    display: flex;

    gap: 15px;

    margin-top: 13px;

    font-size: 9px;

    color: #95878e;
}

.calendar-legend span {
    display: flex;

    align-items: center;

    gap: 5px;
}

.legend-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;
}

.available-dot {
    background: #fffbed;

    border: 1px solid #e9d9a0;
}

.booked-dot {
    background: #f6dede;

    border: 1px solid #e0a6a6;
}

.unavailable-dot {
    background: #f4f0f3;

    border: 1px solid #d8cdd4;
}

.selected-dot {
    background: #f4a4ca;

    border: 1px solid #c9689a;
}

.today-dot {
    background: #ffe3f1;

    border: 1px solid #f6c8de;
}

/* SHIPMENTS */

.shipment-list {
    display: grid;
    gap: 10px;
}

.shipment {
    display: flex;

    align-items: center;
    justify-content: space-between;

    background: #fff8fa;

    border: 1px solid #f3e4e9;

    padding: 10px 11px;

    border-radius: 12px;
}

.shipment strong {
    font-size: 11px;
}

.shipment small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 3px;
}

.shipment-icon {
    font-size: 18px;
}

.empty-shipment {
    min-height: 55px;
}

/* Shipment summary - isang card kada order */
.shipment-group-heading{
    grid-column: 1 / -1;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
    color: #6D28D9;
    padding: 4px 2px 0;
}

.shipment-summary-grid {
    display: grid;
    gap: 8px;

    flex: 1;
}

.shipment-bucket-empty {
    color: #a0959b;
    font-size: 10px;
    padding: 4px 0;
}

.shipment-bucket-item {
    display: flex;
    flex-direction: column;
    gap: 4px;

    background: #fff8fa;
    border: 1px solid #f3e4e9;

    padding: 8px 10px;

    border-radius: 10px;

    text-decoration: none;
    color: inherit;

    font-size: 10.5px;
}

.shipment-bucket-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.shipment-bucket-item-id {
    font-weight: 800;
    color: var(--pink-dark);

    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

/* iisang badge na lang bawat card (Overdue / Shipping Tomorrow /
   Due in Xd / Ready to Ship) - kapalit ng dating status pill +
   urgency label na magkahiwalay pa noon */
.shipment-bucket-item-badge {
    font-size: 8.5px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .2px;
    padding: 3px 7px;
    border-radius: 999px;
    white-space: nowrap;
    flex-shrink: 0;
}

.shipment-bucket-item-badge-ready {
    background: var(--pink-soft);
    color: var(--pink-dark);
}

.shipment-bucket-item-badge-tomorrow,
.shipment-bucket-item-badge-soon {
    background: #FFF3E0;
    color: #9A6400;
}

.shipment-bucket-item-badge-overdue {
    background: var(--red-light, #FADCDC);
    color: var(--red, #C0392B);
}

.shipment-bucket-item-duesoon {
    background: #FFF3E0;
    border-color: #F5C97A;
}

.shipment-bucket-item-overdue {
    background: var(--red-light, #FADCDC);
    border-color: var(--red, #C0392B);
}

.shipment-bucket-item-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;

    color: var(--gray);
    font-size: 9.5px;
}

.shipment-bucket-item-meta span:first-child {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.shipment-bucket-item-date {
    color: var(--gray);
    font-size: 9.5px;
    white-space: nowrap;
}

.shipment-bucket-more {
    font-size: 9.5px;
    color: var(--gray);
    padding: 2px 2px 0;
}



/* OVERVIEW */

.activity-card {
    margin-top: 18px;
}

.online-badge {
    color: var(--green);

    background: var(--green-light);

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;
}

.overview-grid {
    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap: 10px;
}

.overview-box {
    display: flex;

    align-items: center;

    gap: 10px;

    background: var(--pink-soft);

    border: 1px solid #f4e2e8;

    border-radius: 13px;

    padding: 12px;
}

.overview-icon {
    width: 32px;
    height: 32px;

    border-radius: 10px;

    background: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 900;

    color: var(--pink-dark);
}

.overview-box strong {
    display: block;

    font-size: 14px;
}

.overview-box small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 2px;
}

/* QUICK ACTIONS */

.quick-actions {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 10px;

    margin-top: 18px;
}

.quick-btn {
    padding: 12px;

    background: var(--pink-soft);

    border: 1px dashed #d9b6c3;

    border-radius: 12px;

    font-size: 10px;

    font-weight: 800;

    transition: .2s;
}

.quick-btn:hover {
    background: var(--pink-light);

    transform: translateY(-1px);
}

/* EMPTY */

.empty-state {
    padding: 30px 10px;

    text-align: center;

    color: #a0959b;

    font-size: 11px;
}

/* MODAL */

.modal-overlay {
    display: none;

    position: fixed;

    inset: 0;

    background: rgba(35,25,30,.38);

    z-index: 999;

    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: min(420px,90%);

    background: white;

    border-radius: 20px;

    padding: 24px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.22);

    animation: modalIn .2s ease;
}

@keyframes modalIn {

    from {
        opacity: 0;
        transform:
            translateY(10px)
            scale(.97);
    }

    to {
        opacity: 1;
        transform:
            translateY(0)
            scale(1);
    }
}

.modal-top {
    display: flex;

    justify-content: space-between;

    align-items: center;
}

.modal h3 {
    font-size: 18px;
}

.close-modal {
    width: 30px;
    height: 30px;

    border: none;

    background: var(--pink-light);

    border-radius: 9px;

    font-size: 18px;
}

.modal p {
    color: var(--gray);

    font-size: 12px;

    line-height: 1.6;

    margin: 12px 0 18px;
}

.modal-button {
    border: none;

    background: var(--pink);

    color: var(--black);

    padding: 10px 18px;

    border-radius: 10px;

    font-weight: 800;
}

/* RESPONSIVE */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .dashboard-layout {
        grid-template-columns: 1fr;
    }

    .stats-left {
        grid-template-columns:
            repeat(2,1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .bottom-row {
        grid-template-columns: 1fr;
    }

    .overview-grid {
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media (max-width: 800px) {






    .search-box {
        width: 180px;
    }
}

@media (max-width: 600px) {

    .main {
        padding: 18px;
    }

    .topbar {
        flex-direction: column;

        gap: 15px;
    }

    .top-actions {
        width: 100%;
    }

    .search-box {
        flex: 1;
    }

    .stats-left {
        grid-template-columns:
            1fr 1fr;
    }

    .order-row {
        grid-template-columns:
            1.5fr .8fr .7fr;
    }

    .order-row > div:nth-child(2) {
        display: none;
    }

    .datetime {
        flex-wrap: wrap;
    }

    .overview-grid {
        grid-template-columns: 1fr;
    }
}

/* INNER PAGE */

.page-description {
    color: var(--gray);
    font-size: 11px;
    margin-top: 4px;
}

/* BOOKING TABLE */

.booking-table {
    width: 100%;
    overflow-x: auto;
}

.booking-row {
    min-width: 720px;

    display: grid;

    grid-template-columns:
        .8fr
        1fr
        1fr
        .9fr
        .9fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.booking-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.booking-row strong {
    font-size: 11px;
}

/* Completed orders table - hiwalay sa .booking-row kasi 6 columns
   ito (may Customer at Courier), 5 lang ang All Bookings */

.completed-row {
    min-width: 760px;

    display: grid;

    grid-template-columns:
        .7fr
        1fr
        .8fr
        .9fr
        .8fr
        .8fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.completed-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.completed-row strong {
    font-size: 11px;
}

/* FULL CALENDAR */

.full-calendar-card {
    height: fit-content;
}

.calendar-page-controls {
    display: flex;

    align-items: center;

    justify-content: space-between;

    margin: 10px 0 25px;
}

.calendar-page-controls h3 {
    font-size: 18px;
}

.calendar-page-controls button {
    width: 42px;
    height: 42px;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 12px;

    font-size: 25px;

    font-weight: 900;

    transition: .2s;
}

.calendar-page-controls button:hover {
    background: var(--pink);

    color: white;
}

.full-week,
.full-calendar-days {
    width: 100%;
}

.full-week div {
    font-size: 12px;
    font-weight: 800;
}

.full-calendar-days {
    gap: 6px;
}

.large-day {
    height: 46px;

    border: none;

    border-radius: 11px;

    font-size: 12px;
}

.large-day:hover {
    background: var(--pink-soft);
}

.large-day.today {
    background: var(--pink);
}

.large-day.booked {
    background: var(--pink-light);

    color: var(--pink-dark);
}

/* RESPONSIVE INNER PAGES */

@media (max-width: 800px) {

    .quotation-info {
        grid-template-columns: 1fr;
    }

    .full-calendar-card {
        overflow-x: auto;
    }

    .full-calendar-days,
    .full-week {
        min-width: 600px;
    }

}


</style>


<style>
/* Punuin ang screen - walang malaking bakanteng space sa ilalim ng mga
   container, at walang scroll ang mismong browser (desktop) */
@media (min-width: 1101px) and (min-height: 640px) {
    .app > main.main {
        height: 100vh;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        padding-bottom: 22px;
        overflow: hidden;
    }
    .app > main.main > .topbar { flex: 0 0 auto; }
    .app > main.main > .dashboard-layout {
        flex: 1 1 auto;
        min-height: 0;
        margin-bottom: 0;
        grid-template-rows: minmax(0, 1fr);
    }
    .dashboard-left { min-height: 0; }
    .dashboard-left > .bottom-row {
        flex: 1 1 auto;
        min-height: 0;
        grid-template-rows: minmax(0, 1fr);
    }
    .bottom-row > .card { min-height: 0; overflow: hidden; }
    .dashboard-right .shipment-stat-card { min-height: 0; overflow: auto; }
    /* lumalaki ang calendar kasabay ng card */
    .bottom-row > .card .calendar-days {
        flex: 1 1 auto;
        min-height: 0;
        grid-auto-rows: minmax(0, 1fr);
        align-content: stretch;
    }
    .bottom-row > .card .calendar-days .day {
        height: auto !important;
        min-height: 0 !important;
        aspect-ratio: auto !important;
    }
}
</style>

<style>
/* Shipment Summary - walang scroll sa card/baba; ang listahan ng order
   containers ay taas-pababa at nag-i-scroll lang (vertical) kapag di na kasya */
.shipment-stat-card { overflow: hidden !important; }
.shipment-summary-grid {
    display: flex !important;
    flex-direction: column;
    gap: 8px;
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
}
.shipment-bucket-item {
    flex: 0 0 auto;
    gap: 7px;
    padding: 9px 11px;
    min-width: 0;
}
.shipment-bucket-item-top { gap: 8px; }
.shipment-bucket-item-id { font-size: 11.5px; }
.shipment-bucket-item-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 10px;
}
.shipment-bucket-item-field {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}
.shipment-bucket-item-label {
    font-size: 8.5px;
    font-weight: 700;
    color: #9b8f95;
}
.shipment-bucket-item-value {
    font-size: 10px;
    font-weight: 800;
    color: #3a2f35;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.shipment-bucket-more { flex: 0 0 auto; }
</style>

<style>
/* Upcoming Shipment cards at Recent Bookings rows: display lang, hindi clickable */
.shipment-bucket-item, .order-row.searchable-order { cursor: default !important; }

/* Recent Bookings: Order | Type | Method | Date | Status - kasya lahat, walang scroll sa baba */
.order-row {
    grid-template-columns:
        minmax(0,.6fr) minmax(0,.9fr) minmax(0,1.1fr) minmax(0,.8fr) minmax(0,1.5fr) !important;
    gap: 6px !important;
    font-size: 10px !important;
}
.order-row.header { font-size: 8.5px !important; }
.order-row > div {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.order-row > div:nth-child(2) { display: block !important; }
.order-row .customer strong { font-size: 10px; }
.order-row .status {
    display: inline-block;
    max-width: 100%;
    box-sizing: border-box;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 8.5px !important;
    padding: 3px 7px !important;
    vertical-align: middle;
}
</style>
<style>
/* ==========================================================
   DASHBOARD - Figurify redesign (nasa loob na ng dashboard file na ito,
   walang hiwalay na CSS file). Nakalagay pagkatapos ng ibang style
   ng page kaya ito ang nasusunod.
   ========================================================== */


body.cs-dash {
    --cs-pad: clamp(14px, 2.2vw, 28px);
    --cs-primary: #c2325f;
    --cs-plum: #4a1d3b;
    --cs-blush: #ffedf2;
    --cs-line: #f3dce3;
    --cs-muted: #7c6670;
    --cs-ok: #2f8f67;     --cs-ok-bg: #dff3e8;
    --cs-bad: #b83227;    --cs-bad-bg: #fbdada;
    --cs-warn: #9a6408;   --cs-warn-bg: #fdefbf;
    --cs-off: #9a9a9a;    --cs-off-bg: #ececec;

    
    color: var(--cs-plum);
    
}

body.cs-dash .app > main.main {
    padding: 0 var(--cs-pad) 18px !important;
}

/* ---------- topbar ---------- */
body.cs-dash .app > main.main > .topbar {
    margin: 0 calc(var(--cs-pad) * -1) 10px !important;
    padding: 8px var(--cs-pad) !important;
    min-height: 56px !important;
    align-items: center !important;
    background: #ffffff;
    border-bottom: 1px solid var(--cs-line);
}
body.cs-dash .portal-title {
    
    font-size: 24px !important;
    font-weight: 700 !important;
    color: var(--cs-plum) !important;
}
body.cs-dash .top-actions { gap: 14px; }

/* date / time row */
body.cs-dash .top-actions .datetime {
    display: flex !important;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    width: auto;
    margin: 0 !important;
    white-space: nowrap;
    padding: 0 !important;
    background: none !important;
    border: 0 !important;
    box-shadow: none !important;
    font-size: 14px !important;
}
body.cs-dash .datetime-dot { display: inline; color: #c7aeb8; }
body.cs-dash #currentDate { color: var(--cs-muted) !important; font-weight: 500; }
body.cs-dash #currentTime { color: var(--cs-plum) !important; font-weight: 800; }

/* ---------- cards ---------- */
body.cs-dash .card {
    background: #ffffff;
    border: 1px solid var(--cs-line) !important;
    border-radius: 22px !important;
    box-shadow: 0 10px 28px -18px rgba(74, 29, 59, .28) !important;
    padding: 16px !important;
}

body.cs-dash .card-header { margin-bottom: 10px; }
body.cs-dash .card-header h3 {
    
    font-size: 19px;
    font-weight: 700;
    color: var(--cs-plum);
}
body.cs-dash .view-link {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--cs-primary);
}

/* ---------- stat cards ---------- */
body.cs-dash .stats-left { gap: 14px; }
body.cs-dash .stat-card::after { display: none !important; }

body.cs-dash .stat-card .stat-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: var(--cs-plum);
}
body.cs-dash .stat-card .stat-number {
    margin-top: 6px;
    
    font-size: 30px;
    font-weight: 700;
    line-height: 1.1;
    color: var(--cs-plum);
}
body.cs-dash .stat-card .stat-trend {
    display: inline-flex;
    align-items: center;
    margin-top: 6px;
    padding: 3px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: var(--cs-blush);
    color: #7c3a5a;
}
body.cs-dash .pending-card .stat-trend  { background: var(--cs-warn-bg); color: var(--cs-warn); }
body.cs-dash .approval-card .stat-trend { background: #ffe4e8;          color: var(--cs-bad); }
body.cs-dash .active-card .stat-trend   { background: var(--cs-ok-bg);   color: var(--cs-ok); }

/* ---------- recent bookings table ---------- */
body.cs-dash .order-row {
    grid-template-columns: minmax(0, .7fr) minmax(0, .9fr) minmax(0, 1.2fr) minmax(0, .8fr) minmax(0, 1.2fr) !important;
    gap: 10px !important;
    padding: 10px 8px !important;
    border-top: 1px solid #f6e8ed;
    font-size: 12px !important;
    color: var(--cs-plum);
}
body.cs-dash .order-row.header {
    padding: 6px 8px 8px !important;
    border-top: 0;
    border-bottom: 1.5px solid #ecd9e0;
    font-size: 10.5px !important;
    font-weight: 700;
    letter-spacing: .08em;
    color: var(--cs-muted);
}
body.cs-dash .order-row .customer strong { font-size: 12.5px; font-weight: 800; }
body.cs-dash .order-row .status {
    padding: 4px 11px !important;
    border-radius: 999px !important;
    font-size: 10.5px !important;
    font-weight: 700;
}
body.cs-dash .empty-state { padding: 28px 0; text-align: center; color: var(--cs-muted); }

/* ---------- calendar ---------- */
body.cs-dash .calendar-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 8px;
}
body.cs-dash .calendar-controls strong {
    font-size: 15px;
    font-weight: 800;
    color: var(--cs-plum);
}
body.cs-dash .calendar-controls .calendar-arrow-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #f3c9d8;
    border-radius: 50%;
    background: var(--cs-blush);
    color: var(--cs-primary);
    box-shadow: none;
    transition: background .15s, color .15s, border-color .15s;
}
body.cs-dash .calendar-controls .calendar-arrow-btn:hover:not(:disabled) {
    background: var(--cs-primary);
    border-color: var(--cs-primary);
    color: #ffffff;
}
body.cs-dash .calendar-controls .calendar-arrow-btn:disabled { background: #f6f0f2; border-color: #eadfe4; color: #c5b6bd; }
body.cs-dash .calendar-arrow-icon { width: 16px; height: 16px; }

body.cs-dash .calendar-week,
body.cs-dash .calendar-days {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 5px;
}
body.cs-dash .calendar-week { margin-bottom: 5px; text-align: center; }
body.cs-dash .calendar-week div { font-size: 10.5px; font-weight: 700; color: #5b3a4d; }

@media (min-width: 1101px) and (min-height: 640px) {
    /* magkakadikit na hilera (walang malaking puwang sa pagitan ng mga linggo) */
    body.cs-dash .bottom-row > .card .calendar-days { flex: 0 0 auto; grid-auto-rows: auto; align-content: start; }
}
body.cs-dash .calendar-days .day,
body.cs-dash .bottom-row > .card .calendar-days .day {
    height: auto !important;
    min-height: 0 !important;
    aspect-ratio: 1.25 / 1 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0 !important;
    border-radius: 9px !important;
    background: var(--cs-off-bg);
    color: var(--cs-off);
    font-size: 12px;
    font-weight: 700;
    cursor: default;
    box-shadow: none;
}
body.cs-dash .calendar-days .day.empty { background: transparent !important; }
/* kulay ng araw - !important para manalo sa base na gray ng .day */
body.cs-dash .calendar-days .day.available   { background: var(--cs-ok-bg) !important;  color: var(--cs-ok) !important; }
body.cs-dash .calendar-days .day.booked      { background: var(--cs-bad-bg) !important; color: var(--cs-bad) !important; }
body.cs-dash .calendar-days .day.unavailable { background: var(--cs-off-bg) !important; color: var(--cs-off) !important; }
body.cs-dash .calendar-days .day.today       { background: var(--cs-primary) !important; color: #ffffff !important; }

body.cs-dash .calendar-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    margin-top: 8px;
    font-size: 11px;
    color: #6a5460;
}
body.cs-dash .calendar-legend span { display: inline-flex; align-items: center; gap: 6px; }
body.cs-dash .legend-dot { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }
body.cs-dash .available-dot   { background: var(--cs-ok); }
body.cs-dash .booked-dot      { background: var(--cs-bad); }
body.cs-dash .unavailable-dot { background: #b5b5b5; }
body.cs-dash .today-dot       { background: var(--cs-primary); }

/* ---------- upcoming shipment ---------- */
body.cs-dash .shipment-stat-card { display: flex; flex-direction: column; }

body.cs-dash .ship-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}
body.cs-dash .ship-title {
    margin: 0;
    
    font-size: 15px;
    font-style: italic;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: var(--cs-primary);
}
body.cs-dash .ship-link {
    font-size: 12px;
    font-weight: 700;
    color: var(--cs-primary);
    white-space: nowrap;
}
body.cs-dash .ship-link:hover { text-decoration: underline; }

body.cs-dash .shipment-summary-grid { gap: 7px; }

body.cs-dash .shipment-bucket-item {
    padding: 8px 11px !important;
    gap: 6px !important;
    border: 1px solid var(--cs-line);
    border-left: 4px solid var(--cs-ok);
    border-radius: 14px;
    background: #fffafb;
    box-shadow: none;
}
body.cs-dash .shipment-bucket-item-duesoon { border-left-color: #d59b2b; background: #fffaf0; }
body.cs-dash .shipment-bucket-item-overdue { border-left-color: var(--cs-bad); background: #fff6f5; }

body.cs-dash .shipment-bucket-item-id { font-size: 12.5px; font-weight: 800; color: var(--cs-plum); }
body.cs-dash .shipment-bucket-item-badge {
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 9.5px;
    font-weight: 700;
}
body.cs-dash .shipment-bucket-item-details { gap: 5px 10px; }
body.cs-dash .shipment-bucket-item-label {
    font-size: 8.5px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--cs-muted);
}
body.cs-dash .shipment-bucket-item-value { font-size: 11px; font-weight: 700; color: var(--cs-plum); }
body.cs-dash .shipment-bucket-empty { padding: 26px 0; text-align: center; color: var(--cs-muted); font-size: 13.5px; }
</style>
</head>


<body class="cs-dash">


<div class="app">


<!-- SIDEBAR -->

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
<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>




<!-- MAIN -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="welcome">

            <h2 class="portal-title">

                <?php
                // batay sa oras ng Pilipinas (Asia/Manila, naka-set sa session_helper.php)
                $greetingHour = (int) date("G");
                echo $greetingHour < 12 ? "Good morning," : ($greetingHour < 18 ? "Good afternoon," : "Good evening,");
                ?>
                <?php echo e($staffName); ?>

            </h2>

        </div>


        <div class="top-actions">

            <!-- DATE / TIME - kaliwa ng notification bell -->
            <div class="datetime">
                <span id="currentDate">Loading date...</span>
                <span class="datetime-dot">•</span>
                <span id="currentTime">Loading time...</span>
            </div>

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>


    </header>





    <!-- STATISTICS -->


    <div class="dashboard-layout">


        <div class="dashboard-left">


        <div class="stats-left">


            <!-- TOTAL -->

            <div class="card stat-card">

                <div class="stat-label">
                    TOTAL BOOKINGS
                </div>

                <div class="stat-number">
                    <?php echo $allBookings; ?>
                </div>

                <div class="stat-trend">
                    ● All customer orders
                </div>

            </div>


            <!-- PENDING -->

            <div class="card stat-card pending-card">

                <div class="stat-label">
                    PENDING
                </div>

                <div class="stat-number">
                    <?php echo $pendingBookings; ?>
                </div>

                <div class="stat-trend attention">
                    ● Awaiting quotation
                </div>

            </div>


            <!-- FOR APPROVAL -->

            <div class="card stat-card approval-card">

                <div class="stat-label">
                    FOR APPROVAL
                </div>

                <div class="stat-number">
                    <?php echo $forApproval; ?>
                </div>

                <div class="stat-trend attention">
                    ● Needs attention
                </div>

            </div>


            <!-- ACTIVE -->

            <div class="card stat-card active-card">

                <div class="stat-label">
                    ACTIVE ORDERS
                </div>

                <div class="stat-number">
                    <?php echo $activeOrders; ?>
                </div>

                <div class="stat-trend">
                    ● Currently processing
                </div>

            </div>


        </div>





        <div class="bottom-row">


        <!-- CALENDAR -->

            <section class="card">


                <div class="card-header">

                    <h3>
                        Calendar
                    </h3>

                </div>


                <div class="calendar-controls">

                    <button
                        type="button"
                        id="previousMonth"
                        class="calendar-arrow-btn"
                        aria-label="Previous month"
                    >
                        <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <strong id="calendarMonth">
                        Loading...
                    </strong>

                    <button
                        type="button"
                        id="nextMonth"
                        class="calendar-arrow-btn"
                        aria-label="Next month"
                    >
                        <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                </div>


                <div class="calendar-week">

                    <div>SUN</div>
                    <div>MON</div>
                    <div>TUE</div>
                    <div>WED</div>
                    <div>THU</div>
                    <div>FRI</div>
                    <div>SAT</div>

                </div>


                <div
                    class="calendar-days"
                    id="calendarDays"
                ></div>


                <div class="calendar-legend">

                    <span>
                        <i class="legend-dot available-dot"></i>
                        Available
                    </span>

                    <span>
                        <i class="legend-dot booked-dot"></i>
                        Fully booked
                    </span>

                    <span>
                        <i class="legend-dot unavailable-dot"></i>
                        Unavailable
                    </span>

                    <span>
                        <i class="legend-dot today-dot"></i>
                        Today
                    </span>

                </div>


            </section>


        <!-- RECENT BOOKINGS -->

        <section class="card">


            <div class="card-header">

                <h3>
                    Recent Bookings
                </h3>

                <a
                    href="allbookings.php"
                    class="view-link"
                >
                    View all
                </a>

            </div>


            <div class="order-row header">

                <div>
                    Order
                </div>

                <div>
                    Type
                </div>
                <div>
                    Method
                </div>

                <div>
                    Date
                </div>

                <div>
                    Status
                </div>

            </div>


            <div id="orders">


            <?php if (!empty($recentOrders)): ?>


                <?php foreach ($recentOrders as $order): ?>


                    <?php

                    // order_status_helper.php, same status color/label everywhere

                    $statusClass   = figurify_status_class($order["status"] ?? "pending");
                    $displayStatus = statusLabel($order["status"] ?? "pending");


                    $bookingDate =
                        !empty($order["booking_date"])
                        ? date(
                            "M d",
                            strtotime($order["booking_date"])
                        )
                        : "—";


                    // Order type

                    $orderType = strtolower(
                        trim(
                            $order["order_type"] ?? ""
                        )
                    );

                    if ($orderType === "rush") {

                        $orderTypeDisplay = "Rush";

                    }

                    elseif ($orderType === "nonrush") {

                        $orderTypeDisplay = "Non-Rush";

                    }

                    else {

                        $orderTypeDisplay = ucwords(
                            str_replace(
                                "_",
                                " ",
                                $orderType
                            )
                        );

                    }

                    ?>


                    <div
                        class="order-row searchable-order"
                        data-search="<?php
                            echo e(
                                "FG-" .
                                $order["order_id"] .
                                " " .
                                ($order["order_type"] ?? "") .
                                " " .
                                ($order["customer_name"] ?? "") .
                                " " .
                                ($order["status"] ?? "")
                            );
                        ?>"
                        data-order-id="<?php echo (int) $order["order_id"]; ?>"
                        data-order-status="<?php echo e($order["status"] ?? "pending"); ?>"
                    >


                        <div class="customer">

                            <div>

                                <strong>
                                    #<?php echo (int)$order["order_id"]; ?>
                                </strong>

                            </div>

                        </div>


                        <div>

                            <?php
                            echo e(
                                $orderTypeDisplay
                            );
                            ?>

                        </div>

                        <!-- METHOD -->
                        <div>
                            <?php echo e(orderMethodLabel($order["order_method"] ?? "")); ?>
                        </div>



                        <div>

                            <?php echo e($bookingDate); ?>

                        </div>


                        <div>

                            <span
                                class="status <?php echo $statusClass; ?>"
                            >
                                <?php echo e($displayStatus); ?>
                            </span>

                        </div>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-state">

                    No orders yet.

                </div>


            <?php endif; ?>


            </div>


        </section>


        </div>


        </div>


        <div class="dashboard-right">

        <!-- SHIPMENT SUMMARY -->

        <div class="card stat-card shipment-stat-card">

            <div class="ship-head">
                <h3 class="ship-title">Upcoming Shipment</h3>
                <a class="ship-link" href="shipments.php">View all &rarr;</a>
            </div>

            <?php
                figurify_render_shipment_summary(
                    $shipmentSummaryOrders,
                    "No shipments to prepare right now."
                );
            ?>

        </div>

        </div>


    </div>





</main>

</div>



<!-- MODAL -->

<div
    class="modal-overlay"
    id="modalOverlay"
>

    <div class="modal">


        <div class="modal-top">

            <h3 id="modalTitle">
                Figurify
            </h3>

            <button
                type="button"
                class="close-modal"
                id="closeModal"
            >
                ×
            </button>

        </div>


        <p id="modalMessage">
            Message
        </p>


        <button
            type="button"
            class="modal-button"
            id="modalOkay"
        >
            Okay
        </button>


    </div>

</div>



<script src="../Shared/dashboard.js"></script>
<script>
    const figurifyBookedDates = <?php echo json_encode($bookedDates); ?>;

/* Staff dashboard JS (updateDateTime/formatDate are in Shared/dashboard.js, loaded above) */


/* NAVIGATION */

const navButtons =
    document.querySelectorAll(
        ".nav-btn[data-section]"
    );

navButtons.forEach(button => {

    button.addEventListener(
        "click",
        function(event) {

            event.preventDefault();

            navButtons.forEach(item => {

                item.classList.remove(
                    "active"
                );

            });

            document
                .querySelector(
                    ".nav-btn[href='staffdashboard.php']"
                )
                ?.classList.remove("active");

            this.classList.add("active");

            openModal(
                this.dataset.section,
                "The " +
                this.dataset.section +
                " module is ready to connect to your PHP page."
            );

        }
    );

});


/* SEARCH */

const searchInput =
    document.getElementById(
        "searchOrders"
    );

if (searchInput) {

    searchInput.addEventListener(
        "input",
        function() {

            const value =
                this.value
                    .toLowerCase()
                    .trim();

            const orders =
                document.querySelectorAll(
                    ".searchable-order"
                );

            orders.forEach(order => {

                const text =
                    (
                        order.dataset.search || ""
                    ).toLowerCase();

                if (
                    text.includes(value)
                ) {

                    order.style.display =
                        "grid";

                } else {

                    order.style.display =
                        "none";
                }

            });

        }
    );
}


/* MODAL */

const modalOverlay =
    document.getElementById(
        "modalOverlay"
    );

const modalTitle =
    document.getElementById(
        "modalTitle"
    );

const modalMessage =
    document.getElementById(
        "modalMessage"
    );


function openModal(
    title,
    message
) {

    if (!modalOverlay) {
        return;
    }

    if (modalTitle) {
        modalTitle.textContent =
            title;
    }

    if (modalMessage) {
        modalMessage.textContent =
            message;
    }

    modalOverlay.classList.add(
        "show"
    );
}


function closeModal() {

    if (!modalOverlay) {
        return;
    }

    modalOverlay.classList.remove(
        "show"
    );
}


document
    .getElementById("closeModal")
    ?.addEventListener(
        "click",
        closeModal
    );


document
    .getElementById("modalOkay")
    ?.addEventListener(
        "click",
        closeModal
    );


modalOverlay?.addEventListener(
    "click",
    function(event) {

        if (
            event.target === modalOverlay
        ) {

            closeModal();

        }

    }
);


/* NOTIFICATIONS */

document
    .getElementById("notificationButton")
    ?.addEventListener(
        "click",
        function() {

            openModal(
                "Notifications",
                "You currently have staff actions waiting for review."
            );

        }
    );


/* VIEW BOOKINGS */

document
    .getElementById("viewBookings")
    ?.addEventListener(
        "click",
        function() {

            openModal(
                "All Bookings",
                "This button is ready to connect to your All Bookings PHP module."
            );

        }
    );


/* VIEW SHIPMENTS */

document
    .getElementById("viewShipments")
    ?.addEventListener(
        "click",
        function() {

            window.location.href =
                "allbookings.php";

        }
    );


/* CALENDAR */

const calendarDays =
    document.getElementById(
        "calendarDays"
    );

const calendarMonth =
    document.getElementById(
        "calendarMonth"
    );

const previousMonth =
    document.getElementById(
        "previousMonth"
    );

const nextMonth =
    document.getElementById(
        "nextMonth"
    );


let currentCalendarDate =
    new Date();


// real booking dates from DB, same variable name/marker as owner.js

const bookingDates =
    typeof figurifyBookedDates !== "undefined"
    ? figurifyBookedDates
    : [];


// formatDate also shared, one source for both calendars


function renderCalendar() {

    if (
        !calendarDays ||
        !calendarMonth
    ) {
        return;
    }

    const year =
        currentCalendarDate
            .getFullYear();

    const month =
        currentCalendarDate
            .getMonth();

    calendarMonth.textContent =
        currentCalendarDate.toLocaleDateString(
            "en-US",
            {
                month: "long",
                year: "numeric"
            }
        );

    // can't go back before the current month
    const now = new Date();

    const isAtCurrentMonth =
        year === now.getFullYear() &&
        month === now.getMonth();

    if (previousMonth) {

        previousMonth.disabled = isAtCurrentMonth;

        previousMonth.style.opacity =
            isAtCurrentMonth ? "0.4" : "1";

        previousMonth.style.cursor =
            isAtCurrentMonth ? "not-allowed" : "pointer";

    }

    calendarDays.innerHTML = "";


    // Monday-first calendar

    const jsFirstDay =
        new Date(
            year,
            month,
            1
        ).getDay();

    const firstDay = jsFirstDay;   // Sunday-first, kapareho ng Commission calendar


    const daysInMonth =
        new Date(
            year,
            month + 1,
            0
        ).getDate();


    for (
        let i = 0;
        i < firstDay;
        i++
    ) {

        const empty =
            document.createElement(
                "div"
            );

        empty.className =
            "day empty";

        calendarDays.appendChild(
            empty
        );
    }


    const today =
        new Date();


    for (
        let day = 1;
        day <= daysInMonth;
        day++
    ) {

        const dayElement =
            document.createElement(
                "div"
            );

        dayElement.className =
            "day";

        dayElement.textContent =
            day;


        const dateString =
            formatDate(
                year,
                month,
                day
            );


        dayElement.dataset.date = dateString;


        const isToday =
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day;

        if (isToday) {

            dayElement.classList.add(
                "today"
            );

        }


        const isBooked =
            bookingDates.includes(
                dateString
            );

        if (isBooked) {

            dayElement.classList.add(
                "booked"
            );

        }


        // past days marked "Unavailable", same as Commission Calendar
        const cellDate =
            new Date(year, month, day);

        const todayAtMidnight =
            new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate()
            );

        const isPast = cellDate < todayAtMidnight;

        if (isPast) {

            dayElement.classList.add(
                "unavailable"
            );

        } else if (!isBooked) {

            dayElement.classList.add(
                "available"
            );

        }

        // dashboard calendar is read-only preview, no click-to-select,
        // only prev/next month arrows

        calendarDays.appendChild(
            dayElement
        );
    }
}


previousMonth?.addEventListener(
    "click",
    function() {

        const now = new Date();

        const isAtCurrentMonth =
            currentCalendarDate.getFullYear() === now.getFullYear() &&
            currentCalendarDate.getMonth() === now.getMonth();

        if (isAtCurrentMonth) {
            return;
        }

        currentCalendarDate.setMonth(
            currentCalendarDate.getMonth() - 1
        );

        renderCalendar();

    }
);


nextMonth?.addEventListener(
    "click",
    function() {

        currentCalendarDate.setMonth(
            currentCalendarDate.getMonth() + 1
        );

        renderCalendar();

    }
);


renderCalendar();


/* QUICK ACTIONS */

document
    .querySelectorAll(
        ".quick-btn"
    )
    .forEach(button => {

        button.addEventListener(
            "click",
            function() {

                if (
                    this.dataset.action ===
                    "new-booking"
                ) {

                    openModal(
                        "New Booking",
                        "This will later connect to your booking creation module."
                    );

                }


                if (
                    this.dataset.action ===
                    "quotation"
                ) {

                    openModal(
                        "Create Quotation",
                        "This will later connect to your quotation module."
                    );

                }

            }
        );

    });


/* ESCAPE KEY */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            closeModal();

        }

    }
);

</script>

</body>
</html>