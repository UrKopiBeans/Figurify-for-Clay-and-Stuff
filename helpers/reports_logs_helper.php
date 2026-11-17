<?php

/* =====================================================================
   reports_logs_helper.php  -  PINAGSAMANG HELPER PARA SA REPORTS AT LOGS
   =====================================================================
   Dating dalawang file, isa na lang ngayon:
     - reports_helper.php       (SECTION 1)
     - activity_log_helper.php  (SECTION 2)
   Puwede nang i-delete ang dalawang yan.

   PAANO SILA GUMAGANA
   ---------------------------------------------------------------------
   SECTION 1 - REPORTS / STATISTICS (para sa Reports page at dashboard)
     Puro aggregation query (SUM / COUNT / GROUP BY) sa orders at order_figures.
     Ang KITA ay binibilang lang sa mga order na bayad na (paid_at IS NOT NULL)
     at hindi cancelled, dahil dun pa lang totoong kumita ang negosyo.
       figurify_get_sales_summary()         = paid orders, total kita, average, kita ngayong buwan
       figurify_get_orders_by_status()      = bilang ng order sa bawat status
       figurify_get_top_products()          = pinaka-benta na figure style + product type
       figurify_get_completed_this_month()  = natapos ngayong buwan (Staff dashboard)
       figurify_get_sales_over_time()       = kita kada araw (bar chart)
       figurify_get_last_month_revenue()    = kita nakaraang buwan ("vs last month")
       figurify_get_order_mix()             = Dress Up vs Image Submission, Rush vs Non-Rush
       figurify_get_figure_style_mix()      = Hirono / Funko Pop / Chibi
       figurify_get_order_report()          = Order Summary Report (audit): bawat order + bayad, balance, refund

   SECTION 2 - ACTIVITY LOG (sino ang gumawa ng importanteng aksyon, kailan, at sa anong order)
     Table: activity_log (gagawin kung wala pa).
       figurify_log_activity()        = mag-record ng aksyon (actor galing sa session)
       figurify_get_activity_log()    = basahin ang listahan (pinagkukunan ng owner/activity-log.php)
       figurify_count_activity_log()  = total, para sa pagination

   PAANO GAMITIN
     require_once __DIR__ . "/../helpers/reports_logs_helper.php";   (isang require lang, safe kahit ulit-ulitin)

   PAALALA: puro function lang ang file na ito - walang inilalabas at walang
   kumokonekta sa database hangga't hindi tinatawag ang function. Lahat ng
   function ay tumatanggap ng $conn (mysqli) mula sa page.
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : bilangan ng benta (Reports) at talaan ng ginawa ng staff/owner (Activity Log).
   PARA SAAN   : dito kumukuha ng numero ang Reports page at dashboard, at ang activity-log page.
   PANO NAGANA : SECTION 1 = mga query na nagbibilang (SUM/COUNT). Kita ay bilang lang sa order
                 na bayad na at hindi cancelled.
                 SECTION 2 = kapag may importanteng ginawa, isusulat ito ng figurify_log_activity()
                 (sino, ano, kailan, anong order) sa table na activity_log.
   --------------------------------------------------------------------- */


/* =====================================================================
   SECTION 1 - REPORTS / STATISTICS (dating reports_helper.php)
   ===================================================================== */

/* GINAGAMIT NG: owner/reports.php at staff/reports.php (Reports at dashboard stat cards)
   Sales summary: bilang ng bayad na order, total na kita, average kada order, at kita ngayong buwan. */
if (!function_exists("figurify_get_sales_summary")) {
    function figurify_get_sales_summary(mysqli $conn): array
    {
        $result = $conn->query(
            "SELECT
                COUNT(*) AS paid_orders,
                COALESCE(SUM(total_amount), 0) AS total_revenue,
                COALESCE(AVG(total_amount), 0) AS avg_order_value
             FROM orders
             WHERE paid_at IS NOT NULL AND status != 'cancelled'"
        );
        $summary = $result->fetch_assoc();

        $monthResult = $conn->query(
            "SELECT COALESCE(SUM(total_amount), 0) AS month_revenue
             FROM orders
             WHERE paid_at IS NOT NULL AND status != 'cancelled'
               AND MONTH(paid_at) = MONTH(CURDATE())
               AND YEAR(paid_at) = YEAR(CURDATE())"
        );
        $monthRow = $monthResult->fetch_assoc();

        return [
            "paid_orders"     => (int) $summary["paid_orders"],
            "total_revenue"   => (float) $summary["total_revenue"],
            "avg_order_value" => (float) $summary["avg_order_value"],
            "month_revenue"   => (float) $monthRow["month_revenue"],
        ];
    }
}


/* Bilang ng orders sa bawat status, gamit ang parehong grouping/label na ginagamit na ng buong system.
   KAILANGAN: figurify_status_key() at FIGURIFY_ORDER_STATUSES (galing sa order status helper, naka-load na sa order pages). */

if (!function_exists("figurify_get_orders_by_status")) {
    function figurify_get_orders_by_status(mysqli $conn): array
    {
        $result = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");

        $counts = [];

        while ($row = $result->fetch_assoc()) {
            $key = figurify_status_key($row["status"]);
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row["total"];
        }

        $breakdown = [];

        foreach (FIGURIFY_ORDER_STATUSES as $key => $info) {
            $breakdown[] = [
                "key"   => $key,
                "label" => $info["label"],
                "bg"    => $info["bg"],
                "text"  => $info["text"],
                "count" => $counts[$key] ?? 0,
            ];
        }

        return $breakdown;
    }
}


/* Pinaka-madalas na naa-order na figure style + product type, sa mga order na bayad na (top $limit). */

if (!function_exists("figurify_get_top_products")) {
    function figurify_get_top_products(mysqli $conn, int $limit = 5): array
    {
        $stmt = $conn->prepare(
            "SELECT f.figure_style, f.product_type, SUM(f.quantity) AS total_ordered,
                    COALESCE(SUM(f.figure_total * f.quantity), 0) AS total_revenue
             FROM order_figures f
             JOIN orders o ON o.order_id = f.order_id
             WHERE o.paid_at IS NOT NULL AND o.status != 'cancelled'
             GROUP BY f.figure_style, f.product_type
             ORDER BY total_ordered DESC
             LIMIT ?"
        );

        $stmt->bind_param("i", $limit);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}


/* Bilang ng orders na na-completed ngayong buwan - ginagamit ng Staff dashboard bilang non-financial na "progress" stat card. */

if (!function_exists("figurify_get_completed_this_month")) {
    function figurify_get_completed_this_month(mysqli $conn): int
    {
        // basehan ang shipped_at (pinakamalapit na may timestamp na
        // "natapos" na ang order), hindi created_at
        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM orders
             WHERE status = 'completed'
               AND shipped_at IS NOT NULL
               AND MONTH(shipped_at) = MONTH(CURDATE())
               AND YEAR(shipped_at) = YEAR(CURDATE())"
        );
        $row = $result->fetch_assoc();

        return (int) ($row["total"] ?? 0);
    }
}


/* Revenue/order count kada araw sa huling $days na araw - para sa bar chart ng Reports page.
   Ang mga araw na walang benta ay nilalagyan ng zero para tuloy-tuloy ang chart. */

if (!function_exists("figurify_get_sales_over_time")) {
    function figurify_get_sales_over_time(mysqli $conn, int $days = 14): array
    {
        $stmt = $conn->prepare(
            "SELECT DATE(paid_at) AS sale_date,
                    COUNT(*) AS orders_count,
                    COALESCE(SUM(total_amount), 0) AS revenue
             FROM orders
             WHERE paid_at IS NOT NULL AND status != 'cancelled'
               AND paid_at >= (CURDATE() - INTERVAL ? DAY)
             GROUP BY DATE(paid_at)
             ORDER BY sale_date ASC"
        );

        $stmt->bind_param("i", $days);
        $stmt->execute();

        $rowsByDate = [];

        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $rowsByDate[$row["sale_date"]] = $row;
        }

        $stmt->close();

        // punuin ang mga araw na walang benta ng zero, para tuloy-tuloy ang chart
        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {

            $date = date("Y-m-d", strtotime("-{$i} days"));

            $series[] = [
                "date"    => $date,
                "count"   => (int) ($rowsByDate[$date]["orders_count"] ?? 0),
                "revenue" => (float) ($rowsByDate[$date]["revenue"] ?? 0),
            ];

        }

        return $series;
    }
}


/* Kita noong nakaraang buwan - para sa "vs last month" sa Statistics page. */

if (!function_exists("figurify_get_last_month_revenue")) {
    function figurify_get_last_month_revenue(mysqli $conn): float
    {
        $start = date("Y-m-01", strtotime("first day of last month"));
        $end   = date("Y-m-01");

        $stmt = $conn->prepare(
            "SELECT COALESCE(SUM(total_amount), 0) AS revenue
             FROM orders
             WHERE paid_at IS NOT NULL AND status != 'cancelled'
               AND paid_at >= ? AND paid_at < ?"
        );

        $stmt->bind_param("ss", $start, $end);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float) ($row["revenue"] ?? 0);
    }
}


/* Order mix - ilan ang Dress Up vs Image Submission at Rush vs Non-Rush (hindi kasama ang cancelled).
   Revenue = paid orders lang. */

if (!function_exists("figurify_get_order_mix")) {
    function figurify_get_order_mix(mysqli $conn): array
    {
        $mix = [
            "method" => [
                "create_style" => ["label" => "Dress Up",         "count" => 0, "revenue" => 0.0],
                "reference"    => ["label" => "Image Submission", "count" => 0, "revenue" => 0.0],
            ],
            "type" => [
                "rush"    => ["label" => "Rush",     "count" => 0, "revenue" => 0.0],
                "nonrush" => ["label" => "Non-Rush", "count" => 0, "revenue" => 0.0],
            ],
            "total" => 0,
        ];

        $result = $conn->query(
            "SELECT order_method, order_type,
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN paid_at IS NOT NULL THEN total_amount ELSE 0 END), 0) AS revenue
             FROM orders
             WHERE status != 'cancelled'
             GROUP BY order_method, order_type"
        );

        if (!$result) {
            return $mix;
        }

        while ($row = $result->fetch_assoc()) {
            $count   = (int) $row["total"];
            $revenue = (float) $row["revenue"];

            $method = strtolower((string) $row["order_method"]) === "create_style" ? "create_style" : "reference";
            $type   = strtolower((string) $row["order_type"]) === "rush" ? "rush" : "nonrush";

            $mix["method"][$method]["count"]   += $count;
            $mix["method"][$method]["revenue"] += $revenue;
            $mix["type"][$type]["count"]       += $count;
            $mix["type"][$type]["revenue"]     += $revenue;
            $mix["total"]                      += $count;
        }

        return $mix;
    }
}


/* Order mix ayon sa figure style (Hirono / Funko Pop / Chibi) - bilang ng figures sa mga order na hindi
   cancelled/refund; "orders" = ilang order ang kasama. */

if (!function_exists("figurify_get_figure_style_mix")) {
    function figurify_get_figure_style_mix(mysqli $conn): array
    {
        $mix = ["styles" => [], "figures" => 0, "orders" => 0];

        $result = $conn->query(
            "SELECT f.figure_style, SUM(f.quantity) AS total
             FROM order_figures f
             JOIN orders o ON o.order_id = f.order_id
             WHERE o.status NOT IN ('cancelled', 'for_refund', 'refunded')
             GROUP BY f.figure_style
             ORDER BY total DESC"
        );

        if (!$result) {
            return $mix;
        }

        while ($row = $result->fetch_assoc()) {
            $label = trim((string) $row["figure_style"]);

            $mix["styles"][] = [
                "label" => $label !== "" ? $label : "Other",
                "count" => (int) $row["total"],
            ];
            $mix["figures"] += (int) $row["total"];
        }

        $orderResult = $conn->query(
            "SELECT COUNT(DISTINCT f.order_id) AS total
             FROM order_figures f
             JOIN orders o ON o.order_id = f.order_id
             WHERE o.status NOT IN ('cancelled', 'for_refund', 'refunded')"
        );

        if ($orderResult) {
            $mix["orders"] = (int) ($orderResult->fetch_assoc()["total"] ?? 0);
        }

        return $mix;
    }
}


/* =====================================================================
   ORDER SUMMARY REPORT (audit) - GINAGAMIT NG: owner/reports.php at staff/reports.php
   Listahan ng lahat ng order na ginawa mula $from hanggang $to (petsa ng order),
   kasama ang bayad, balance at refund ng bawat isa, at ang kabuuan sa dulo.
   $group: "all" | "active" | "completed" | "cancelled" (kasama sa cancelled ang for_refund at refunded)
   Ang bayad ay galing sa figurify_wf_amount_paid() (order_helper.php) para pareho
   ang kwenta ng report, ng My Orders at ng refund.
   ===================================================================== */

if (!defined("FIGURIFY_REPORT_GROUPS")) {
    define("FIGURIFY_REPORT_GROUPS", [
        "all"       => "All orders",
        "active"    => "Active",
        "completed" => "Completed",
        "cancelled" => "Cancelled / Refunded",
    ]);
}

if (!function_exists("figurify_get_order_report")) {
    function figurify_get_order_report(mysqli $conn, string $from, string $to, string $group = "all"): array
    {
        $report = [
            "rows"   => [],
            "totals" => ["orders" => 0, "sales" => 0.0, "paid" => 0.0, "balance" => 0.0, "refunded" => 0.0],
        ];

        $stmt = $conn->prepare(
            "SELECT o.order_id, o.created_at, o.ship_out_date, o.order_type, o.order_method,
                    o.payment_option, o.total_amount, o.payment_proof, o.paid_at,
                    o.balance_paid_at, o.balance_to_pay, o.refund_amount, o.refunded_at, o.status,
                    u.full_name, u.email
             FROM orders o
             JOIN users u ON u.user_id = o.user_id
             WHERE DATE(o.created_at) BETWEEN ? AND ?
             ORDER BY o.created_at DESC, o.order_id DESC"
        );

        if (!$stmt) {
            return $report;
        }

        $stmt->bind_param("ss", $from, $to);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($result && ($row = $result->fetch_assoc())) {
            $statusKey = figurify_status_key($row["status"] ?? "");
            $rowGroup  = "active";

            if ($statusKey === "completed") {
                $rowGroup = "completed";
            } elseif (in_array($statusKey, ["cancelled", "for_refund", "refunded"], true)) {
                $rowGroup = "cancelled";
            }

            if ($group !== "all" && $group !== $rowGroup) {
                continue;
            }

            $total    = (float) ($row["total_amount"] ?? 0);
            $paid     = figurify_wf_amount_paid($row);
            $refunded = !empty($row["refunded_at"]) ? (float) ($row["refund_amount"] ?? 0) : 0.0;
            $balance  = $rowGroup === "active" ? max(0.0, round($total - $paid, 2)) : 0.0;

            $row["group"]          = $rowGroup;
            $row["status_label"]   = statusLabel($row["status"] ?? "");
            $row["type_label"]     = strtolower((string) $row["order_type"]) === "rush" ? "Rush" : "Non-Rush";
            $row["method_label"]   = strtolower((string) $row["order_method"]) === "create_style" ? "Dress Up" : "Image Submission";
            $row["payment_label"]  = $total > 0 ? (figurify_wf_is_full_payment($row) ? "Full" : "Half (50%)") : "N/A";
            $row["paid_amount"]    = $paid;
            $row["balance_amount"] = $balance;
            $row["refund_total"]   = $refunded;

            $report["rows"][] = $row;

            $report["totals"]["orders"]++;
            $report["totals"]["paid"]     += $paid;
            $report["totals"]["balance"]  += $balance;
            $report["totals"]["refunded"] += $refunded;

            if ($rowGroup !== "cancelled") {
                $report["totals"]["sales"] += $total;
            }
        }

        $stmt->close();

        return $report;
    }
}


/* =====================================================================
   SECTION 2 - ACTIVITY LOG (dating activity_log_helper.php)
   Record ng mga importanteng aksyon ng Staff/Owner (verify/approve/reject
   order, quotation, revision, user management, atbp.) para may accountability.
   Self-migrating table gaya ng login_attempts.
   ===================================================================== */

/* ANO ITO: gumagawa ng table na activity_log kung wala pa (isang beses lang kada page). */
if (!function_exists("figurify_ensure_activity_log_table")) {
    function figurify_ensure_activity_log_table(mysqli $conn): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS activity_log (
                log_id      INT AUTO_INCREMENT PRIMARY KEY,
                actor_id    INT NULL,
                actor_name  VARCHAR(100) NOT NULL,
                actor_role  VARCHAR(20) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                order_id    INT NULL,
                created_at  DATETIME NOT NULL,
                INDEX idx_created_at (created_at)
            )"
        );

        $ensured = true;
    }
}


/* GINAGAMIT NG: order_helper.php, order_workflow.php (verify/approve/reject order, quotation, revision)
   at owner/user-management.php.
   Itinatala ang isang aksyon. Ang gumawa (actor) ay kinukuha sa session, kaya kailangan naka-start na ang session.
   Hal. figurify_log_activity($conn, "order_verified", "Verified order #12", 12); */

if (!function_exists("figurify_log_activity")) {
    function figurify_log_activity(
        mysqli $conn,
        string $actionType,
        string $description,
        ?int $orderId = null
    ): void {

        figurify_ensure_activity_log_table($conn);

        $actorId   = isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;
        $actorName = $_SESSION["full_name"] ?? "Unknown";
        $actorRole = $_SESSION["role"] ?? "unknown";
        $description = figurify_db_text($description);

        $stmt = $conn->prepare(
            "INSERT INTO activity_log
                (actor_id, actor_name, actor_role, action_type, description, order_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->bind_param(
            "issssi",
            $actorId,
            $actorName,
            $actorRole,
            $actionType,
            $description,
            $orderId
        );

        $stmt->execute();
        $stmt->close();
    }
}


/* GINAGAMIT NG: owner/activity-log.php
   Kinukuha ang pinakabagong entries, pinaka-bago sa taas; $limit at $offset para sa pagination. */

if (!function_exists("figurify_get_activity_log")) {
    function figurify_get_activity_log(mysqli $conn, int $limit = 50, int $offset = 0): array
    {
        figurify_ensure_activity_log_table($conn);

        $stmt = $conn->prepare(
            "SELECT a.log_id, a.actor_id, a.actor_name, a.actor_role, a.action_type,
                    a.description, a.order_id, a.created_at, u.email AS actor_email
             FROM activity_log a
             LEFT JOIN users u ON u.user_id = a.actor_id
             ORDER BY a.created_at DESC, a.log_id DESC
             LIMIT ? OFFSET ?"
        );

        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}


/* Total count ng log entries, para sa pagination controls ng activity-log.php. */

if (!function_exists("figurify_count_activity_log")) {
    function figurify_count_activity_log(mysqli $conn): int
    {
        figurify_ensure_activity_log_table($conn);

        $result = $conn->query("SELECT COUNT(*) AS total FROM activity_log");
        $row    = $result->fetch_assoc();

        return (int) ($row["total"] ?? 0);
    }
}
