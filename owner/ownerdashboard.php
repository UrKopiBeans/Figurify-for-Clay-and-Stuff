<?php
require_once __DIR__ . "/owner-header.php";
require_once __DIR__ . "/../helpers/booking_helper.php";

/* Full na petsa lang ang kinukulayan sa dashboard calendar */
$bookedDates = figurify_get_full_dates($conn);

// Default values
$allBookings           = 0;
$pendingBookings       = 0;
$forApproval           = 0;
$activeOrders          = 0;
$shipmentSummaryOrders = [];
$recentOrders          = [];

// Kunin ang data mula sa DB
$checkTable = $conn->query("SHOW TABLES LIKE 'orders'");
if ($checkTable && $checkTable->num_rows > 0) {
    $allBookings     = countOrders($conn);
    $pendingBookings = countOrders($conn, "LOWER(TRIM(status)) = 'pending'");

    // FOR APPROVAL = bayad na hinihintay ang desisyon ng OWNER:
    // downpayment na na-verify na ("for approval") at balance
    // (for_balance_approval + lumang to_verify_balance)
    $forApproval = countOrders(
        $conn,
        "LOWER(REPLACE(REPLACE(TRIM(status), ' ', ''), '_', ''))
         IN ('forapproval', 'approval', 'forbalanceapproval', 'toverifybalance')"
    );

    // Active orders (processing, on process, active, paid)
    $activeOrders = countOrders(
        $conn,
        "LOWER(REPLACE(TRIM(status), ' ', '')) IN ('processing', 'onprocess', 'active', 'paid')"
    );

    $shipmentSummaryOrders = figurify_get_all_shipments($conn, "to_ship");
    $shipmentSummaryToday = strtotime(date("Y-m-d"));
    usort($shipmentSummaryOrders, function ($a, $b) use ($shipmentSummaryToday) {
        $aDays = daysUntilBooking($a, $shipmentSummaryToday) ?? PHP_INT_MAX;
        $bDays = daysUntilBooking($b, $shipmentSummaryToday) ?? PHP_INT_MAX;
        return $aDays <=> $bDays;
    });

    $recentResult = $conn->query("
        SELECT o.order_id, o.order_type, o.order_method, o.ship_out_date, o.status, o.created_at
        FROM orders o
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Figurify | Owner Dashboard</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
<!-- ===================== STYLE NG PAGE NA ITO: ownerdashboard.css ===================== -->
<link rel="stylesheet" href="ownerdashboard.css">
</head>
<body class="cs-dash">
<div class="app">
<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>

<main class="main">
    <header class="topbar">
        <div class="welcome">
            <h2 class="portal-title">
                <?php
                $greetingHour = (int) date("G");
                echo $greetingHour < 12 ? "Good morning," : ($greetingHour < 18 ? "Good afternoon," : "Good evening,");
                ?>
                <?php echo e($ownerName); ?>
            </h2>
        </div>
        <div class="top-actions">
            <div class="datetime">
                <span id="currentDate">Loading date...</span>
                <span class="datetime-dot">•</span>
                <span id="currentTime">Loading time...</span>
            </div>
            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>
        </div>
    </header>

    <div class="dashboard-layout">
        <div class="dashboard-left">
            <div class="stats-left">
                <a href="allbookings.php" class="card stat-card">
                    <div class="stat-label">TOTAL BOOKINGS</div>
                    <div class="stat-number"><?php echo $allBookings; ?></div>
                    <div class="stat-trend">● All customer orders</div>
                </a>
                <a href="quotation.php?qfilter=pending" class="card stat-card pending-card">
                    <div class="stat-label">PENDING</div>
                    <div class="stat-number"><?php echo $pendingBookings; ?></div>
                    <div class="stat-trend attention">● Awaiting quotation</div>
                </a>
                <a href="for-approval.php" class="card stat-card approval-card">
                    <div class="stat-label">FOR APPROVAL</div>
                    <div class="stat-number"><?php echo $forApproval; ?></div>
                    <div class="stat-trend attention">● Needs attention</div>
                </a>
                <a href="active-booking.php" class="card stat-card active-card">
                    <div class="stat-label">ACTIVE ORDERS</div>
                    <div class="stat-number"><?php echo $activeOrders; ?></div>
                    <div class="stat-trend">● Currently processing</div>
                </a>
            </div>

            <div class="bottom-row">
                <section class="card">
                    <div class="card-header">
                        <h3>Calendar</h3>
                    </div>
                    <div class="calendar-controls">
                        <button type="button" id="previousMonth" class="calendar-arrow-btn" aria-label="Previous month" disabled>
                            <svg class="calendar-arrow-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <strong id="calendarMonth">Loading...</strong>
                        <button type="button" id="nextMonth" class="calendar-arrow-btn" aria-label="Next month">
                            <svg class="calendar-arrow-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="calendar-week">
                        <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
                    </div>
                    <div class="calendar-days" id="calendarDays"></div>
                    <div class="calendar-legend">
                        <span><i class="legend-dot available-dot"></i> Available</span>
                        <span><i class="legend-dot today-dot"></i> Today</span>
                        <span><i class="legend-dot booked-dot"></i> Fully booked</span>
                        <span><i class="legend-dot unavailable-dot"></i> Unavailable</span>
                    </div>
                </section>

                <section class="card">
                    <div class="card-header">
                        <h3>Recent Bookings</h3>
                        <a href="allbookings.php" class="view-link">View all</a>
                    </div>
                    <div id="orders">
                        <?php if (!empty($recentOrders)): ?>
                        <table class="recent-table">
                            <thead>
                                <tr><th>Order</th><th>Type</th><th>Method</th><th>Ship Out Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <?php
                                $statusClass   = figurify_status_class($order["status"] ?? "pending");
                                $displayStatus = statusLabel($order["status"] ?? "pending");
                                $bookingDate   = !empty($order["ship_out_date"]) ? date("M d", strtotime($order["ship_out_date"])) : "N/A";
                                $orderType     = strtolower(trim($order["order_type"] ?? ""));
                                $orderTypeDisplay = match ($orderType) {
                                    "rush"    => "Rush",
                                    "nonrush" => "Non-Rush",
                                    default   => ucwords(str_replace("_", " ", $orderType))
                                };
                                ?>
                                <tr>
                                    <td class="recent-order-id">#<?php echo (int)$order["order_id"]; ?></td>
                                    <td><?php echo e($orderTypeDisplay); ?></td>
                                    <td class="recent-method"><?php echo e(orderMethodLabel($order["order_method"] ?? "")); ?></td>
                                    <td><?php echo e($bookingDate); ?></td>
                                    <td><span class="status <?php echo $statusClass; ?>"><?php echo e($displayStatus); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <div class="empty-state">No orders yet.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <div class="dashboard-right">
            <div class="card shipment-stat-card">
                <div class="ship-head">
                    <h3 class="ship-title">Upcoming Shipment</h3>
                    <a class="ship-link" href="shipments.php">View all</a>
                </div>
                <?php figurify_render_shipment_summary($shipmentSummaryOrders, "No shipments to prepare right now."); ?>
            </div>
        </div>
    </div>
</main>
</div>

<script>
// Live Date/Time
function updateDateTime() {
    const dateEl = document.getElementById("currentDate");
    const timeEl = document.getElementById("currentTime");
    if (!dateEl || !timeEl) return;
    const now = new Date();
    dateEl.textContent = now.toLocaleDateString("en-PH", {
        weekday: "long", year: "numeric", month: "long", day: "numeric"
    });
    timeEl.textContent = now.toLocaleTimeString("en-PH", {
        hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: true
    });
}
updateDateTime();
setInterval(updateDateTime, 1000);


// Calendar — data, elements, state
const bookingDates = <?php echo json_encode($bookedDates); ?>;
const calendarDays = document.getElementById("calendarDays");
const calendarMonth = document.getElementById("calendarMonth");
const previousMonth = document.getElementById("previousMonth");
const nextMonth = document.getElementById("nextMonth");
let currentCalendarDate = new Date();

// Calendar — helpers
function formatDate(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

function isCurrentMonth(date) {
    const now = new Date();
    return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
}

function renderCalendar() {
    if (!calendarDays || !calendarMonth) return;
    const year = currentCalendarDate.getFullYear();
    const month = currentCalendarDate.getMonth();
    calendarMonth.textContent = currentCalendarDate.toLocaleDateString("en-US", {
        month: "long", year: "numeric"
    });

    const atCurrent = isCurrentMonth(currentCalendarDate);
    if (previousMonth) {
        previousMonth.disabled = atCurrent;
    }

    calendarDays.innerHTML = "";
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement("div");
        empty.className = "day empty";
        calendarDays.appendChild(empty);
    }

    const today = new Date();
    const todayMid = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    for (let day = 1; day <= daysInMonth; day++) {
        const de = document.createElement("div");
        de.className = "day";
        de.textContent = day;
        const ds = formatDate(year, month, day);
        de.dataset.date = ds;

        const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
        if (isToday) de.classList.add("today");

        if (bookingDates.includes(ds)) {
            de.classList.add("booked");
        } else {
            const cellDate = new Date(year, month, day);
            if (cellDate < todayMid) {
                de.classList.add("unavailable");
            } else {
                de.classList.add("available");
            }
        }
        calendarDays.appendChild(de);
    }
}

// Calendar — controls
function slideCalendar(amount) {
    const slide = amount > 0 ? "days-next" : "days-prev";
    calendarDays.classList.add(slide);
    setTimeout(() => {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + amount);
        renderCalendar();
        calendarDays.classList.remove(slide);
    }, 350);
}

previousMonth?.addEventListener("click", () => {
    if (isCurrentMonth(currentCalendarDate)) return;
    slideCalendar(-1);
});

nextMonth?.addEventListener("click", () => slideCalendar(1));

// Calendar — initial render
renderCalendar();
</script>
</body>
</html>