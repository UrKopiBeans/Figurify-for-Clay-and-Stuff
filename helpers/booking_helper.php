<?php

/* =====================================================================
   booking_helper.php  (PINAGSAMANG booking_helper + rush_split_helper)
   =====================================================================

   ISANG FILE NA LANG ITO para sa:
     A. Booking slots at calendar rules (rush / non-rush)
     B. Rush fee ng bawat figure
     C. Pagpapakita ng pangalan ng figure ("None" kapag placeholder lang)
     D. Dashboard ng owner at staff (bilang ng order, ilang araw pa, listahan ng ipapadala)

   ---------------------------------------------------------------------
   PANO SILA NAGANA (maikli)
   ---------------------------------------------------------------------
   1) BILANG NG SLOT
      - Isang order ay "nakakakuha" ng slot LANG kapag na-approve na ng
        owner ang down payment = status "processing" pataas
        (tingnan ang FIGURIFY_BOOKED_CALENDAR_STATUSES sa baba).
      - Pending / Quotation / Payment Submitted / For Approval = WALANG
        hold. Ibig sabihin, hindi pa naka-block ang petsa sa ibang customer.

   2) RULES
      RUSH     : 7 araw na lead time, max 2 order kada araw, max 10 kada buwan.
      NON-RUSH : last day ng buwan lang pwede, 7 araw na lead time
                 (bago mag-last day), max 15 order kada petsa.

   3) LOCK (GET_LOCK)
      - Bago i-check + i-save ang slot, kunin muna ang
        figurify_slot_lock_acquire(). Isang customer lang ang makakapasok
        nang sabay, kaya hindi makukuha ng dalawa ang huling slot.

   4) CALENDAR / SHIPMENTS QUERIES
      - Mga query para sa calendar ng owner/staff/commission, sa shipments
        page, at "mga susunod na bakanteng petsa".

   5) RUSH FEE
      - Head Only Keychain = P200, lahat ng iba = P500 (kada figure).
      - Dapat PAREHO sa Commission/commission.js (getRushFeeForProductType)
        at Commission/dress-up.js.
      - figurify_rush_split() ang naghahati ng fee kada figure para sa
        display (quotation, my-orders, owner/staff pages).

   6) FIGURE NAME DISPLAY
      - Kapag walang inilagay na pangalan ang customer, "Chibi Figure 2"
        (placeholder) ang naka-save sa DB. Sa display, "None" ang lalabas.

   ---------------------------------------------------------------------
   MGA FILE NA NAGRE-REQUIRE NITO
   ---------------------------------------------------------------------
   Dating dalawa (booking_helper.php at rush_split_helper.php). Ngayon
   booking_helper.php na lang ang dapat i-require ng lahat.
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : taga-bantay ng petsa at presyo ng rush.
   PARA SAAN   : sinasabi nito kung bukas pa ang isang petsa, ilang slot ang natitira,
                 at magkano ang rush fee ng bawat figure.
   PANO NAGANA : 1) Bibilangin ang mga order na nakuha na ang slot (processing pataas).
                 2) Ikukumpara sa limit (rush: 2 kada araw / 10 kada buwan, non-rush: 15 kada petsa).
                 3) Kung puno na, sasabihin ng figurify_check_booking_date() na hindi pwede.
   HANAPIN DITO: figurify_check_booking_date()   = pwede pa ba ang petsa?
                 figurify_next_available_dates() = mga susunod na bakanteng petsa
                 figurify_rush_split()           = hati ng rush fee kada figure
                 figurify_slot_lock_acquire()    = lock para hindi magkasabay ang dalawang customer
   --------------------------------------------------------------------- */


/* =====================================================================
   A. CONSTANTS
   ===================================================================== */

// Mga status na "booked" na sa calendar (processing pataas).
// Kasama ang balance stage para hindi mabuksan ulit ang slot.
const FIGURIFY_BOOKED_CALENDAR_STATUSES = [
    "processing",
    "awaiting_balance",
    "to_verify_balance",
    "for_balance_approval",
    "to_ship",
    "address_returned",
    "shipped",
    "completed"
];

// RUSH rules
const FIGURIFY_RUSH_MIN_LEAD_DAYS = 7;
const FIGURIFY_RUSH_DAILY_CAP     = 2;
const FIGURIFY_RUSH_MONTHLY_CAP   = 10;

// NON-RUSH rules
const FIGURIFY_NONRUSH_MIN_LEAD_DAYS = 7;
const FIGURIFY_NONRUSH_DATE_CAP      = 15;

// Rush fee kada figure
const FIGURIFY_RUSH_FEE_HEAD_ONLY = 200;
const FIGURIFY_RUSH_FEE_DEFAULT   = 500;


/* =====================================================================
   B. INTERNAL HELPER (para hindi paulit-ulit ang code)
   ===================================================================== */

// gumagawa ng "?,?,?" para sa SQL "IN (...)" - isang ? kada status
function figurify_in_placeholders(array $values): string
{
    return implode(",", array_fill(0, count($values), "?"));
}

// gawing "Y-m-d" ang kahit anong date value mula sa DB
function figurify_date_key(string $value): string
{
    return date("Y-m-d", strtotime($value));
}


/* =====================================================================
   C. RUSH FEE
   ===================================================================== */

// Rush fee ng ISANG product type.
// "head only" sa pangalan = 200, blanko = 0, iba pa = 500.
function figurify_get_rush_fee_for_product_type(?string $productType): int
{
    $normalized = strtolower(trim((string) $productType));

    if ($normalized === "") {
        return 0;
    }

    if (strpos($normalized, "head only") !== false) {
        return FIGURIFY_RUSH_FEE_HEAD_ONLY;
    }

    return FIGURIFY_RUSH_FEE_DEFAULT;
}

// Kabuuang rush fee ng order (sinusuma ang bawat figure).
// Ginagamit ng Commission/submit_order.php. Key ng figure: "product".
function figurify_calculate_total_rush_fee(array $figures): int
{
    $total = 0;

    foreach ($figures as $figure) {
        $total += figurify_get_rush_fee_for_product_type($figure["product"] ?? "") * max(1, (int) ($figure["quantity"] ?? 1));
    }

    return $total;
}

// Rush fee ng ISANG figure para sa display. Key ng figure: "product_type".
// Kasama ang quantity: 2 piraso ng ₱500 = ₱1,000.
// (Iisang formula na lang sa taas ang ginagamit - dating may kopya.)
function figurify_rush_fee_for_figure(array $figure): float
{
    return (float) figurify_get_rush_fee_for_product_type($figure["product_type"] ?? "") * figurify_figure_quantity($figure);
}

// Ilang piraso ng figure na ito (laging at least 1; walang quantity column = 1)
function figurify_figure_quantity(array $figure): int
{
    return max(1, (int) ($figure["quantity"] ?? 1));
}

// Presyo ng figure para sa display/total: quoted_price kung meron na, kung wala figure_total (estimate).
// Ang presyong ito ay para sa ISANG piraso, i-multiply sa quantity para sa buong figure.
function figurify_figure_unit_price(array $figure): float
{
    return (isset($figure["quoted_price"]) && $figure["quoted_price"] !== null)
        ? (float) $figure["quoted_price"]
        : (float) ($figure["figure_total"] ?? 0);
}

// Buong presyo ng figure (isang piraso x quantity), hindi pa kasama ang rush fee
function figurify_figure_line_price(array $figure): float
{
    return figurify_figure_unit_price($figure) * figurify_figure_quantity($figure);
}

/* Hinahati ang rush fee kada figure.

     $split = figurify_rush_split($order, $figures);
     $split["per"][0]  -> rush fee ng Figure 1
     $split["legacy"]  -> buong rush fee kapag hindi mahati
     $split["total"]   -> kabuuang rush fee

   Kung hindi tugma ang kinuwenta sa naka-save na rush_fee (lumang order),
   isang "Rush Fee" line na lang ang ipapakita (yung "legacy"). */
function figurify_rush_split(array $order, array $figures): array
{
    $stored = (float) ($order["rush_fee"] ?? 0);
    $isRush = ($stored > 0) || (strtolower((string) ($order["order_type"] ?? "")) === "rush");

    // hindi rush = lahat zero
    if (!$isRush) {
        return [
            "per"    => array_fill(0, count($figures), 0.0),
            "legacy" => 0.0,
            "total"  => 0.0
        ];
    }

    $per = [];

    foreach (array_values($figures) as $index => $figure) {
        $per[$index] = figurify_rush_fee_for_figure($figure);
    }

    $sum = array_sum($per);

    // may naka-save na fee pero hindi tugma ang hati -> isang line na lang
    if ($stored > 0 && abs($sum - $stored) > 0.009) {
        return [
            "per"    => array_fill(0, count($per), 0.0),
            "legacy" => $stored,
            "total"  => $stored
        ];
    }

    return [
        "per"    => $per,
        "legacy" => 0.0,
        "total"  => ($stored > 0) ? $stored : $sum
    ];
}


/* =====================================================================
   D. FIGURE NAME DISPLAY
   ===================================================================== */

/* "None" ang ipapakita kapag:
     - walang pangalan, O
     - placeholder lang ("Chibi Figure 2") at walang name_fee
   Kapag may name_fee (nagbayad para sa pangalan), ipapakita ang pangalan. */
function figurify_figure_name_display(array $figure): string
{
    $name = trim((string) ($figure["figure_name"] ?? ""));

    if ($name === "") {
        return "None";
    }

    $hasFee = isset($figure["name_fee"]) && ((float) $figure["name_fee"] > 0);
    $style  = trim((string) ($figure["figure_style"] ?? ""));

    // placeholder = "<style> Figure <number>"
    $pattern = ($style !== "")
        ? '/^' . preg_quote($style, '/') . ' Figure \d+$/u'
        : '/^[A-Za-z ]+ Figure \d+$/u';

    $isPlaceholder = (bool) preg_match($pattern, $name);

    return ($isPlaceholder && !$hasFee) ? "None" : $name;
}



/* ANO ITO: mga kulay na pinili sa Dress Up ng isang figure (para sa card sa Calendar page)
   Hal. [{"label":"Skin Tone","name":"Light","text":"Light (#FFE1CA)","code":"#FFE1CA"}, {"label":"Hair",...}]
   Walang Dress Up / walang kulay = []. */
function figurify_calendar_dressup_colors($raw): array
{
    require_once __DIR__ . "/dressup_helper.php"; /* figurify_dressup_color_text() */

    $design = is_string($raw) && $raw !== "" ? json_decode($raw, true) : null;

    if (!is_array($design)) {
        return [];
    }

    $colors = [];

    if (!empty($design["skin"]["color"])) {
        $colors[] = ["label" => "Skin Tone", "name" => "", "text" => figurify_dressup_color_text((string) ($design["skin"]["name"] ?? ""), (string) $design["skin"]["color"]), "code" => (string) $design["skin"]["color"]];
    }

    foreach (($design["slots"] ?? []) as $slotRow) {
        if (empty($slotRow["colorValue"])) {
            continue;
        }

        $colors[] = [
            "label" => (string) ($slotRow["label"] ?? ""),
            "name"  => (string) ($slotRow["item"]["name"] ?? ""),
            "text"  => figurify_dressup_color_text((string) ($slotRow["colorName"] ?? ""), (string) $slotRow["colorValue"]),
            "code"  => (string) $slotRow["colorValue"],
        ];
    }

    return $colors;
}
/* =====================================================================
   E. BILANG NG ORDER KADA PETSA
   ===================================================================== */

// Bilang ng order kada date para sa isang order_type ("rush" / "nonrush").
// Processing pataas lang ang binibilang. Return: ["2026-11-30" => 3, ...]
function figurify_get_order_type_day_counts(mysqli $conn, string $orderType): array
{
    $statuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $stmt = $conn->prepare(
        "SELECT ship_out_date, COUNT(*) AS cnt
         FROM orders
         WHERE order_type = ?
           AND LOWER(status) IN (" . figurify_in_placeholders($statuses) . ")
           AND ship_out_date IS NOT NULL
         GROUP BY ship_out_date"
    );

    $params = array_merge([$orderType], $statuses);
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    $stmt->execute();

    $result = $stmt->get_result();
    $counts = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["ship_out_date"])) {
            continue;
        }

        $counts[figurify_date_key($row["ship_out_date"])] = (int) $row["cnt"];
    }

    $stmt->close();

    return $counts;
}

/* ANO ITO: bilang ng RUSH order kada araw (galing sa figurify_get_order_type_day_counts).
   GINAGAMIT PARA: i-check kung puno na ang rush na petsa. */
function figurify_get_rush_day_counts(mysqli $conn): array
{
    return figurify_get_order_type_day_counts($conn, "rush");
}

/* ANO ITO: bilang ng NON-RUSH order kada petsa (galing sa figurify_get_order_type_day_counts).
   GINAGAMIT PARA: i-check kung abot na sa max ang non-rush na petsa. */
function figurify_get_nonrush_date_counts(mysqli $conn): array
{
    return figurify_get_order_type_day_counts($conn, "nonrush");
}


/* =====================================================================
   F. AVAILABILITY CHECKS (server side - huwag lang umasa sa JS)
   ===================================================================== */

// Return ng ["ok" => bool, "reason" => ?string]

// Rush CAPACITY lang (monthly cap + daily cap). Ginagamit ng dalawang
// function sa ibaba para iisa ang logic.
// $counts galing sa figurify_get_rush_day_counts()
function figurify_rush_capacity_result(array $counts, string $dateStr): array
{
    // monthly cap - bilangin lahat ng rush sa buwan ng petsa
    $monthKey   = substr($dateStr, 0, 7);
    $monthTotal = 0;

    foreach ($counts as $d => $c) {
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

    // daily cap
    if (($counts[$dateStr] ?? 0) >= FIGURIFY_RUSH_DAILY_CAP) {
        return [
            "ok"     => false,
            "reason" => "That date already has the maximum of " .
                        FIGURIFY_RUSH_DAILY_CAP . " rush orders."
        ];
    }

    return ["ok" => true, "reason" => null];
}

// Non-rush CAPACITY lang (max 15 kada petsa).
function figurify_nonrush_capacity_result(array $counts, string $dateStr, string $extraHint = ""): array
{
    if (($counts[$dateStr] ?? 0) >= FIGURIFY_NONRUSH_DATE_CAP) {
        return [
            "ok"     => false,
            "reason" => "That date is already fully booked (" .
                        FIGURIFY_NONRUSH_DATE_CAP . " orders)." . $extraHint
        ];
    }

    return ["ok" => true, "reason" => null];
}

// BUONG rush check (para sa customer pagkapili ng petsa):
// valid na date -> 7 araw na lead -> monthly/daily cap
function figurify_check_rush_date(mysqli $conn, string $dateStr): array
{
    $bookingDate = DateTime::createFromFormat("Y-m-d", $dateStr);

    if (!$bookingDate) {
        return ["ok" => false, "reason" => "Invalid ship out date."];
    }

    $minDate = (new DateTime("today"))->modify("+" . FIGURIFY_RUSH_MIN_LEAD_DAYS . " day");

    if ($bookingDate < $minDate) {
        return [
            "ok"     => false,
            "reason" => "Rush orders must have a ship out date at least " .
                        FIGURIFY_RUSH_MIN_LEAD_DAYS . " days from today."
        ];
    }

    return figurify_rush_capacity_result(figurify_get_rush_day_counts($conn), $dateStr);
}

// BUONG non-rush check:
// valid na date -> last day ng buwan lang -> 7 araw na lead -> max 15
function figurify_check_nonrush_date(mysqli $conn, string $dateStr): array
{
    $bookingDate = DateTime::createFromFormat("Y-m-d", $dateStr);

    if (!$bookingDate) {
        return ["ok" => false, "reason" => "Invalid ship out date."];
    }

    // last day ng buwan lang
    if ((int) $bookingDate->format("j") !== (int) $bookingDate->format("t")) {
        return [
            "ok"     => false,
            "reason" => "Non-rush orders may only be shipped out on the last day of a month."
        ];
    }

    // 7 araw na lead - kapag lampas na ang cutoff, next month na
    $cutoff = (clone $bookingDate)->modify("-" . FIGURIFY_NONRUSH_MIN_LEAD_DAYS . " day");

    if (new DateTime("today") > $cutoff) {
        return [
            "ok"     => false,
            "reason" => "Too late to book this month's non-rush date. " .
                        "Please choose next month's last day instead."
        ];
    }

    return figurify_nonrush_capacity_result(
        figurify_get_nonrush_date_counts($conn),
        $dateStr,
        " Please choose next month's last day instead."
    );
}

// Pipili ng tamang check base sa order type
function figurify_check_booking_date(mysqli $conn, string $orderType, string $dateStr): array
{
    return ($orderType === "rush")
        ? figurify_check_rush_date($conn, $dateStr)
        : figurify_check_nonrush_date($conn, $dateStr);
}

/* CAPACITY LANG ang tinitingnan (walang lead-time / last-day rule).
   Ginagamit sa pag-approve ng owner - hindi na dapat ma-block ang order
   dahil lang lumapit na ang petsa. */
function figurify_date_has_capacity(mysqli $conn, string $orderType, string $dateStr): array
{
    $ts = strtotime($dateStr);

    if (!$ts) {
        return ["ok" => false, "reason" => "Invalid ship out date."];
    }

    $dateStr = date("Y-m-d", $ts);

    if ($orderType === "rush") {
        return figurify_rush_capacity_result(figurify_get_rush_day_counts($conn), $dateStr);
    }

    return figurify_nonrush_capacity_result(figurify_get_nonrush_date_counts($conn), $dateStr);
}


/* =====================================================================
   G. LOCK - para hindi makuha ng dalawang customer ang huling slot
   ===================================================================== */

/* Gamitin ito BAGO mag-check + mag-save ng slot.
   Return true = nakuha ang lock. Return false = may ibang customer na
   nagbo-book ngayon (hintay hanggang 5 segundo). */
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


/* =====================================================================
   H. CALENDAR QUERIES (owner, staff, commission)
   ===================================================================== */

// Listahan ng petsang may booked na order (processing pataas).
// Return: ["2026-11-30", ...]
function figurify_get_booked_dates(mysqli $conn): array
{
    $statuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $stmt = $conn->prepare(
        "SELECT DISTINCT ship_out_date
         FROM orders
         WHERE LOWER(status) IN (" . figurify_in_placeholders($statuses) . ")
           AND ship_out_date IS NOT NULL"
    );

    $stmt->bind_param(str_repeat("s", count($statuses)), ...$statuses);
    $stmt->execute();

    $result = $stmt->get_result();
    $dates  = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["ship_out_date"])) {
            continue;
        }

        $dates[] = figurify_date_key($row["ship_out_date"]);
    }

    $stmt->close();

    return array_values(array_unique($dates));
}

// Mga booking na naka-group kada petsa - pag na-click ang booked na araw
// sa calendar, ito ang listahan ng order(s) doon.
function figurify_get_bookings_by_date(mysqli $conn): array
{
    $statuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_method, o.ship_out_date, o.status, o.courier,
                u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE LOWER(o.status) IN (" . figurify_in_placeholders($statuses) . ")
           AND o.ship_out_date IS NOT NULL
         ORDER BY o.ship_out_date ASC, o.created_at ASC"
    );

    $stmt->bind_param(str_repeat("s", count($statuses)), ...$statuses);
    $stmt->execute();

    $result = $stmt->get_result();
    $orders = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["ship_out_date"])) {
            continue;
        }

        $orders[(int) $row["order_id"]] = [
            "order_id"     => (int) $row["order_id"],
            "order_type"   => $row["order_type"],
            "order_method" => $row["order_method"],
            "ship_out_date" => figurify_date_key($row["ship_out_date"]),
            "status"       => $row["status"],
            "courier"      => (string) ($row["courier"] ?? ""),
            "full_name"    => $row["full_name"],
            "email"        => $row["email"],
            "figures"      => [],   // Figure Style / Product Type / Size (para sa card sa Calendar page)
        ];
    }

    $stmt->close();

    /* mga figure ng bawat order (isang query lang para sa lahat) */
    if ($orders) {

        $ids = array_keys($orders);

        $figStmt = $conn->prepare(
            "SELECT order_id, figure_style, product_type, size_label, dressup_data
             FROM order_figures
             WHERE order_id IN (" . figurify_in_placeholders($ids) . ")
             ORDER BY order_id ASC, figure_id ASC"
        );
        $figStmt->bind_param(str_repeat("i", count($ids)), ...$ids);
        $figStmt->execute();
        $figResult = $figStmt->get_result();

        while ($fig = $figResult->fetch_assoc()) {
            $orders[(int) $fig["order_id"]]["figures"][] = [
                "style"   => (string) ($fig["figure_style"] ?? ""),
                "product" => (string) ($fig["product_type"] ?? ""),
                "size"    => (string) ($fig["size_label"] ?? ""),
                "colors"  => figurify_calendar_dressup_colors($fig["dressup_data"] ?? null),
            ];
        }

        $figStmt->close();
    }

    $byDate = [];

    foreach ($orders as $order) {
        $byDate[$order["ship_out_date"]][] = $order;
    }

    return $byDate;
}

/* "FULL" na mga petsa lang - ito lang ang kukulayan/i-block sa calendar.
   Rush: umabot na sa daily cap (2). Non-rush: umabot na sa 15.
   Ang petsang may natitira pang slot ay HINDI full. */
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


/* =====================================================================
   I. SHIPMENTS
   ===================================================================== */

/* Lahat ng shipments (to_ship + address_returned + shipped) para sa "View All Shipments" page.
   $onlyStatus = "to_ship" (kasama ang address_returned - hindi pa naipapadala) o "shipped"; null = lahat. */
function figurify_get_all_shipments(mysqli $conn, ?string $onlyStatus = null): array
{
    $statusFilter = "LOWER(o.status) IN ('to_ship', 'address_returned', 'shipped')";
    $params       = [];

    if ($onlyStatus === "to_ship") {
        $statusFilter = "LOWER(o.status) IN ('to_ship', 'address_returned')";
    } elseif ($onlyStatus === "shipped") {
        $statusFilter = "LOWER(o.status) = ?";
        $params[]     = $onlyStatus;
    }

    // Unang try: kasama ang shipped_at column.
    // Fallback: kung wala pang shipped_at (di pa na-migrate), wala ang column
    // at null ang shipped_at sa bawat row.
    foreach ([true, false] as $withShippedAt) {

        try {

            $shippedAtCol = $withShippedAt ? "o.shipped_at," : "";

            $stmt = $conn->prepare(
                "SELECT o.order_id, o.order_type, o.order_method, o.status, o.ship_out_date,
                        {$shippedAtCol} o.courier, o.tracking_number, o.total_amount,
                        u.full_name, u.email
                 FROM orders o
                 JOIN users u ON u.user_id = o.user_id
                 WHERE {$statusFilter}
                 ORDER BY o.ship_out_date ASC"
            );

            if ($stmt === false) {
                throw new \mysqli_sql_exception("prepare() returned false");
            }

            if ($params) {
                $stmt->bind_param(str_repeat("s", count($params)), ...$params);
            }

            $stmt->execute();

            $result    = $stmt->get_result();
            $shipments = [];

            while ($row = $result->fetch_assoc()) {

                if (!$withShippedAt) {
                    $row["shipped_at"] = null;
                }

                $shipments[] = $row;
            }

            $stmt->close();

            return $shipments;

        } catch (\Throwable $e) {

            // sa fallback pa rin nag-error = ibato (parang dati)
            if (!$withShippedAt) {
                throw $e;
            }
        }
    }

    return [];
}


/* =====================================================================
   J. MGA SUSUNOD NA BAKANTENG PETSA
   ===================================================================== */

/* Para sa "Pumili ng bagong petsa" kapag puno na ang napili.
   Isang query lang para sa bilang, tapos kinukwenta sa memory (hindi
   bawat petsa may query).
   Rush    : araw-araw simula lead time, laktaw ang puno (daily/monthly).
   Non-rush: last day ng bawat buwan na lampas pa sa cutoff at hindi pa 15. */
function figurify_next_available_dates(mysqli $conn, string $orderType, int $limit = 8): array
{
    $found = [];
    $today = new DateTime("today");

    // ---------- RUSH ----------
    if ($orderType === "rush") {

        $counts      = figurify_get_rush_day_counts($conn);
        $monthTotals = [];

        foreach ($counts as $d => $c) {
            $m = substr($d, 0, 7);
            $monthTotals[$m] = ($monthTotals[$m] ?? 0) + $c;
        }

        // magsisimula sa araw bago ang unang pwede (may "+1 day" sa loop)
        $day = (clone $today)->modify("+" . (FIGURIFY_RUSH_MIN_LEAD_DAYS - 1) . " day");

        // max 400 araw ang titingnan para hindi mag-infinite loop
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

    // ---------- NON-RUSH ----------
    $counts = figurify_get_nonrush_date_counts($conn);
    $month  = new DateTime("first day of this month");

    // max 24 buwan ang titingnan
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


/* =====================================================================
   D. DASHBOARD - bilang ng order at listahan ng ipapadala
   Iisang kopya para sa owner/ownerdashboard.php at staff/staffdashboard.php.
   Dati ay nakakopya ito sa dalawang page.
   ===================================================================== */

function countOrders($conn, string $condition = "1"): int
{
    $result = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE $condition");
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return (int) ($row["total"] ?? 0);
}

function daysUntilBooking(array $order, int $todayTs): ?int
{
    if (empty($order["ship_out_date"])) return null;
    return (int) floor((strtotime($order["ship_out_date"]) - $todayTs) / 86400);
}

function figurify_render_shipment_summary(array $orders, string $emptyText, int $limit = 12): void
{
    $count = count($orders);
    $todayTs = strtotime(date("Y-m-d"));
    ?>
    <div class="shipment-summary-grid">
        <?php if ($count === 0): ?>
            <div class="shipment-bucket-empty"><?php echo e($emptyText); ?></div>
        <?php else: ?>
            <?php foreach (array_slice($orders, 0, $limit) as $order): ?>
                <?php
                $cardClass  = "shipment-bucket-item";
                $badgeClass = "shipment-bucket-item-badge-ready";
                $badgeLabel = "Ready to Ship";
                $daysUntil  = daysUntilBooking($order, $todayTs);

                if ($daysUntil !== null) {
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
                    } elseif ($daysUntil <= 3) { // 0, 2, 3 lang ito: negative at 1 ay nahuli na sa taas
                        $cardClass  = "shipment-bucket-item shipment-bucket-item-duesoon";
                        $badgeClass = "shipment-bucket-item-badge-soon";
                        $badgeLabel = ($daysUntil === 0) ? "Due Today" : "Due in " . $daysUntil . "d";
                    }
                }
                ?>
                <div class="<?php echo e($cardClass); ?>">
                    <div class="shipment-bucket-item-top">
                        <span class="shipment-bucket-item-id">Order #<?php echo (int) $order["order_id"]; ?></span>
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
                            <span class="shipment-bucket-item-label">Ship Out Date</span>
                            <span class="shipment-bucket-item-value">
                                <?php echo !empty($order["ship_out_date"])
                                    ? e(date("M d, Y", strtotime($order["ship_out_date"])))
                                    : "N/A"; ?>
                            </span>
                        </div>
                        <div class="shipment-bucket-item-field">
                            <span class="shipment-bucket-item-label">Courier</span>
                            <span class="shipment-bucket-item-value">
                                <?php echo !empty($order["courier"]) ? e($order["courier"]) : "No courier yet"; ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($count > $limit): ?>
                <div class="shipment-bucket-more">+<?php echo ($count - $limit); ?> more</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
/* ====================== END D. DASHBOARD ====================== */
