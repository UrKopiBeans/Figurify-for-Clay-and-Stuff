<?php

/* Booked-dates helper - ginagamit ng lahat ng calendar (owner, staff,
   commission). Isang date "booked" lang kapag status "processing" na
   pataas - "pending" ay hindi pa naka-block sa ibang customer. */

const FIGURIFY_BOOKED_CALENDAR_STATUSES = [
    "processing",
    /* kasama ang balance stage sa "processing pataas" para hindi mabuksan ulit ang slot */
    "awaiting_balance",
    "to_verify_balance",
    "for_balance_approval",
    "to_ship",
    "shipped",
    "completed"
];


/* Rush: 7-day lead, max 2/araw, max 10/buwan. Non-rush: last day ng
   buwan lang bookable, 7-day lead, max 15/date. "processing" pataas
   lang ang bilang (pagkatapos ma-approve ng owner). */

const FIGURIFY_RUSH_MIN_LEAD_DAYS   = 7;
const FIGURIFY_RUSH_DAILY_CAP       = 2;
const FIGURIFY_RUSH_MONTHLY_CAP     = 10;

/* SLOT = OWNER APPROVAL LANG: walang hold ang Pending, Quotation, Payment
   Submitted / Verification, o For Approval. Papasok lang sa bilang ng slot
   (at sa calendar) ang order kapag na-APPROVE na ng owner ang down payment
   (status "processing" pataas - tingnan ang FIGURIFY_BOOKED_CALENDAR_STATUSES).
   Iniwang walang laman ang listahang ito para walang status na nagho-hold ng slot
   bago ang approval ng owner. */
const FIGURIFY_SLOT_HOLD_STATUSES = [];

const FIGURIFY_NONRUSH_MIN_LEAD_DAYS = 7;
const FIGURIFY_NONRUSH_DATE_CAP      = 15;


/* Rush fee kada figure (pareho ang product names ng Chibi/Funko Pop/Hirono):
   Head Only Keychain = 200, lahat ng iba = 500.
   Dapat pareho sa Commission/commission.js (getRushFeeForProductType). */

const FIGURIFY_RUSH_FEE_HEAD_ONLY     = 200;
const FIGURIFY_RUSH_FEE_DEFAULT       = 500;

function figurify_get_rush_fee_for_product_type(?string $productType): int
{
    if ($productType === null) {
        return 0;
    }

    $normalized = strtolower(trim($productType));

    if ($normalized === "") {
        return 0;
    }

    if (strpos($normalized, "head only") !== false) {
        return FIGURIFY_RUSH_FEE_HEAD_ONLY;
    }

    // ibang product types -> default rush fee
    return FIGURIFY_RUSH_FEE_DEFAULT;
}

// sinusuma ang rush fee ng bawat figure sa order

function figurify_calculate_total_rush_fee(array $figures): int
{
    $total = 0;

    foreach ($figures as $figure) {

        $productType = $figure["product"] ?? "";

        $total += figurify_get_rush_fee_for_product_type($productType);

    }

    return $total;
}


// bilang ng order per date para sa isang order_type (rush/nonrush),
// "processing" status pataas lang - basehan ng pagtukoy kung puno na

function figurify_get_order_type_day_counts(mysqli $conn, string $orderType): array
{
    // Processing pataas lang (approved na ng owner) ang binibilang - walang hold sa Verification / For Approval
    $bookedStatuses = array_merge(FIGURIFY_BOOKED_CALENDAR_STATUSES, FIGURIFY_SLOT_HOLD_STATUSES);

    $placeholders = implode(
        ",",
        array_fill(0, count($bookedStatuses), "?")
    );

    $stmt = $conn->prepare(
        "SELECT booking_date, COUNT(*) AS cnt
         FROM orders
         WHERE order_type = ?
           AND LOWER(status) IN ($placeholders)
           AND booking_date IS NOT NULL
         GROUP BY booking_date"
    );

    $types  = "s" . str_repeat("s", count($bookedStatuses));
    $params = array_merge([$orderType], $bookedStatuses);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();

    $counts = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["booking_date"])) {
            continue;
        }

        $dateKey = date("Y-m-d", strtotime($row["booking_date"]));
        $counts[$dateKey] = (int) $row["cnt"];

    }

    $stmt->close();

    return $counts;
}

function figurify_get_rush_day_counts(mysqli $conn): array
{
    return figurify_get_order_type_day_counts($conn, "rush");
}

function figurify_get_nonrush_date_counts(mysqli $conn): array
{
    return figurify_get_order_type_day_counts($conn, "nonrush");
}


// rush availability check sa server side (huwag lang umasa sa JS)

function figurify_check_rush_date(mysqli $conn, string $dateStr): array
{
    $bookingDate = DateTime::createFromFormat("Y-m-d", $dateStr);

    if (!$bookingDate) {
        return ["ok" => false, "reason" => "Invalid booking date."];
    }

    $today = new DateTime("today");

    $minDate = (clone $today)->modify("+" . FIGURIFY_RUSH_MIN_LEAD_DAYS . " day");

    if ($bookingDate < $minDate) {
        return [
            "ok"     => false,
            "reason" => "Rush orders must be booked at least " .
                        FIGURIFY_RUSH_MIN_LEAD_DAYS . " days from today."
        ];
    }

    $dayCounts = figurify_get_rush_day_counts($conn);

    /* Monthly cap */
    $monthKey    = $bookingDate->format("Y-m");
    $monthTotal  = 0;

    foreach ($dayCounts as $d => $c) {
        if (strpos($d, $monthKey) === 0) {
            $monthTotal += $c;
        }
    }

    if ($monthTotal >= FIGURIFY_RUSH_MONTHLY_CAP) {
        return [
            "ok"     => false,
            "reason" => "Rush order slots for that month are already full (" .
                        FIGURIFY_RUSH_MONTHLY_CAP . "/month)."
        ];
    }

    /* Daily cap */
    $dayCount = $dayCounts[$dateStr] ?? 0;

    if ($dayCount >= FIGURIFY_RUSH_DAILY_CAP) {
        return [
            "ok"     => false,
            "reason" => "That date already has the maximum of " .
                        FIGURIFY_RUSH_DAILY_CAP . " rush orders."
        ];
    }

    return ["ok" => true, "reason" => null];
}


// non-rush availability check sa server side

function figurify_check_nonrush_date(mysqli $conn, string $dateStr): array
{
    $bookingDate = DateTime::createFromFormat("Y-m-d", $dateStr);

    if (!$bookingDate) {
        return ["ok" => false, "reason" => "Invalid booking date."];
    }

    $lastDay = (int) $bookingDate->format("t");

    if ((int) $bookingDate->format("j") !== $lastDay) {
        return [
            "ok"     => false,
            "reason" => "Non-rush orders may only be booked on the last day of a month."
        ];
    }

    $today  = new DateTime("today");
    $cutoff = (clone $bookingDate)->modify("-" . FIGURIFY_NONRUSH_MIN_LEAD_DAYS . " day");

    if ($today > $cutoff) {
        return [
            "ok"     => false,
            "reason" => "Too late to book this month's non-rush date. " .
                        "Please choose next month's last day instead."
        ];
    }

    $counts = figurify_get_nonrush_date_counts($conn);
    $count  = $counts[$dateStr] ?? 0;

    if ($count >= FIGURIFY_NONRUSH_DATE_CAP) {
        return [
            "ok"     => false,
            "reason" => "That date is already fully booked (" .
                        FIGURIFY_NONRUSH_DATE_CAP . " orders). " .
                        "Please choose next month's last day instead."
        ];
    }

    return ["ok" => true, "reason" => null];
}

function figurify_get_booked_dates(mysqli $conn): array
{
    $bookedStatuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $placeholders = implode(
        ",",
        array_fill(0, count($bookedStatuses), "?")
    );

    $stmt = $conn->prepare(
        "SELECT DISTINCT booking_date
         FROM orders
         WHERE LOWER(status) IN ($placeholders)
           AND booking_date IS NOT NULL"
    );

    $types = str_repeat("s", count($bookedStatuses));
    $stmt->bind_param($types, ...$bookedStatuses);
    $stmt->execute();

    $result = $stmt->get_result();

    $dates = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["booking_date"])) {
            continue;
        }

        $dates[] = date("Y-m-d", strtotime($row["booking_date"]));
    }

    $stmt->close();

    return array_values(array_unique($dates));
}


// bookings grouped by date, para sa calendar pages - pag na-click
// ang isang booked na araw, ito ang listahan ng order(s) doon

function figurify_get_bookings_by_date(mysqli $conn): array
{
    $bookedStatuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $placeholders = implode(
        ",",
        array_fill(0, count($bookedStatuses), "?")
    );

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_method, o.booking_date, o.status,
                u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE LOWER(o.status) IN ($placeholders)
           AND o.booking_date IS NOT NULL
         ORDER BY o.booking_date ASC, o.created_at ASC"
    );

    $types = str_repeat("s", count($bookedStatuses));
    $stmt->bind_param($types, ...$bookedStatuses);
    $stmt->execute();

    $result = $stmt->get_result();

    $byDate = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["booking_date"])) {
            continue;
        }

        $dateKey = date("Y-m-d", strtotime($row["booking_date"]));

        if (!isset($byDate[$dateKey])) {
            $byDate[$dateKey] = [];
        }

        $byDate[$dateKey][] = [
            "order_id"     => (int) $row["order_id"],
            "order_type"   => $row["order_type"],
            "order_method" => $row["order_method"],
            "status"       => $row["status"],
            "full_name"    => $row["full_name"],
            "email"        => $row["email"]
        ];
    }

    $stmt->close();

    return $byDate;
}


// orders na "to_ship" na status at malapit/nasa booking_date na -
// walang hiwalay na shipping_date column, booking_date ang basehan.
// default 2 days ($withinDays) bago i-flag bilang "upcoming"

function figurify_get_upcoming_shipments(mysqli $conn, int $withinDays = 2): array
{
    $shipments = [];

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.booking_date, o.total_amount,
                u.full_name
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE LOWER(o.status) = 'to_ship'
           AND o.booking_date IS NOT NULL
           AND o.booking_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY o.booking_date ASC"
    );

    $stmt->bind_param("i", $withinDays);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $shipments[] = $row;
    }

    $stmt->close();

    return $shipments;
}


// lahat ng shipments (to_ship + shipped), para sa "View All
// Shipments" page - hindi limitado sa $withinDays gaya ng function
// sa itaas. $onlyStatus para hiwalayin sa To Ship / Shipped tab

function figurify_get_all_shipments(mysqli $conn, ?string $onlyStatus = null): array
{
    $shipments = [];

    $statusFilter = "LOWER(o.status) IN ('to_ship', 'shipped')";
    $params       = [];
    $types        = "";

    if ($onlyStatus !== null && in_array($onlyStatus, ["to_ship", "shipped"], true)) {
        $statusFilter = "LOWER(o.status) = ?";
        $params[]     = $onlyStatus;
        $types       .= "s";
    }

    try {

        $sql = "SELECT o.order_id, o.order_type, o.order_method, o.status, o.booking_date,
                       o.shipped_at, o.courier, o.tracking_number, o.total_amount,
                       u.full_name, u.email
                FROM orders o
                JOIN users u ON u.user_id = o.user_id
                WHERE {$statusFilter}
                ORDER BY o.booking_date ASC";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw new \mysqli_sql_exception("prepare() returned false");
        }

        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $shipments[] = $row;
        }

        $stmt->close();

    } catch (\Throwable $e) {

        // fallback kung wala pang shipped_at column (di pa na-migrate)

        $sql = "SELECT o.order_id, o.order_type, o.order_method, o.status, o.booking_date,
                       o.courier, o.tracking_number, o.total_amount,
                       u.full_name, u.email
                FROM orders o
                JOIN users u ON u.user_id = o.user_id
                WHERE {$statusFilter}
                ORDER BY o.booking_date ASC";

        $stmt = $conn->prepare($sql);

        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row["shipped_at"] = null;
            $shipments[] = $row;
        }

        $stmt->close();

    }

    return $shipments;
}


/* "FULL" na mga petsa lang - ito lang ang dapat kulayan/i-block sa calendar.
   Rush: umabot na sa daily cap (2). Non-rush: umabot na sa 15. Ang petsang
   may 1 order pa lang (o may natitira pang slot) ay HINDI full. */

function figurify_get_full_dates(mysqli $conn): array
{
    $full = [];

    foreach (figurify_get_rush_day_counts($conn) as $d => $c) {
        if ($c >= FIGURIFY_RUSH_DAILY_CAP) {
            $full[$d] = true;
        }
    }

    foreach (figurify_get_nonrush_date_counts($conn) as $d => $c) {
        if ($c >= FIGURIFY_NONRUSH_DATE_CAP) {
            $full[$d] = true;
        }
    }

    return array_keys($full);
}


/* Capacity lang ang tinitingnan (walang lead-time / last-day rule) - ginagamit
   sa pag-approve ng owner, kung saan hindi na dapat ma-block ang order dahil
   lang lumapit na ang petsa. */

function figurify_date_has_capacity(mysqli $conn, string $orderType, string $dateStr): array
{
    $ts = strtotime($dateStr);

    if (!$ts) {
        return ["ok" => false, "reason" => "Invalid booking date."];
    }

    $dateStr = date("Y-m-d", $ts);

    if ($orderType === "rush") {

        $counts     = figurify_get_rush_day_counts($conn);
        $monthKey   = substr($dateStr, 0, 7);
        $monthTotal = 0;

        foreach ($counts as $d => $c) {
            if (strpos($d, $monthKey) === 0) {
                $monthTotal += $c;
            }
        }

        if ($monthTotal >= FIGURIFY_RUSH_MONTHLY_CAP) {
            return ["ok" => false, "reason" => "Rush slots for that month are already full (" . FIGURIFY_RUSH_MONTHLY_CAP . "/month)."];
        }

        if (($counts[$dateStr] ?? 0) >= FIGURIFY_RUSH_DAILY_CAP) {
            return ["ok" => false, "reason" => "That date already has the maximum of " . FIGURIFY_RUSH_DAILY_CAP . " rush orders."];
        }

        return ["ok" => true, "reason" => null];
    }

    $counts = figurify_get_nonrush_date_counts($conn);

    if (($counts[$dateStr] ?? 0) >= FIGURIFY_NONRUSH_DATE_CAP) {
        return ["ok" => false, "reason" => "That date is already fully booked (" . FIGURIFY_NONRUSH_DATE_CAP . " orders)."];
    }

    return ["ok" => true, "reason" => null];
}


/* Isang lock para sa pag-check + pag-reserba ng slot, para hindi
   makakuha ng huling slot ang dalawang customer nang sabay. */

function figurify_slot_lock_acquire(mysqli $conn): bool
{
    try {
        $res = $conn->query("SELECT GET_LOCK('figurify_slot_booking', 5) AS got");
        $row = $res ? $res->fetch_assoc() : null;
        return $row && (int) $row["got"] === 1;
    } catch (\Throwable $e) {
        return false;
    }
}


function figurify_check_booking_date(mysqli $conn, string $orderType, string $dateStr): array
{
    return ($orderType === "rush")
        ? figurify_check_rush_date($conn, $dateStr)
        : figurify_check_nonrush_date($conn, $dateStr);
}

/* mga susunod na bakanteng petsa para sa order type (para sa
   "Pumili ng bagong petsa" kapag puno na ang napili) */

function figurify_next_available_dates(mysqli $conn, string $orderType, int $limit = 8): array
{
    // isang query lang para sa bilang, tapos kinukwenta sa memory (hindi bawat petsa may query)
    $found = [];
    $today = new DateTime("today");

    if ($orderType === "rush") {

        $counts      = figurify_get_rush_day_counts($conn);
        $monthTotals = [];

        foreach ($counts as $d => $c) {
            $m = substr($d, 0, 7);
            $monthTotals[$m] = ($monthTotals[$m] ?? 0) + $c;
        }

        $day = (clone $today)->modify("+" . (FIGURIFY_RUSH_MIN_LEAD_DAYS - 1) . " day");

        for ($i = 0; $i < 400 && count($found) < $limit; $i++) {
            $day->modify("+1 day");
            $dateStr = $day->format("Y-m-d");

            if (($counts[$dateStr] ?? 0) >= FIGURIFY_RUSH_DAILY_CAP) {
                continue;
            }
            if (($monthTotals[$day->format("Y-m")] ?? 0) >= FIGURIFY_RUSH_MONTHLY_CAP) {
                continue;
            }

            $found[] = $dateStr;
        }

        return $found;
    }

    $counts = figurify_get_nonrush_date_counts($conn);
    $month  = new DateTime("first day of this month");

    for ($i = 0; $i < 24 && count($found) < $limit; $i++) {
        $lastDay = (clone $month)->modify("last day of this month");
        $cutoff  = (clone $lastDay)->modify("-" . FIGURIFY_NONRUSH_MIN_LEAD_DAYS . " day");
        $dateStr = $lastDay->format("Y-m-d");

        if ($today <= $cutoff && ($counts[$dateStr] ?? 0) < FIGURIFY_NONRUSH_DATE_CAP) {
            $found[] = $dateStr;
        }

        $month->modify("first day of next month");
    }

    return $found;
}
