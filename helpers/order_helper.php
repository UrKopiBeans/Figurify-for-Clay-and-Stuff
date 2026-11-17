<?php

/* =====================================================================
   ORDER HELPER   (helpers/order_helper.php)

   Pinagsama sa iisang file ang dating 4 helper:
     order_status_helper, order_workflow, review_helper, order_complete_helper

   Walang pinalitang pangalan ng function o constant, kaya ang mga page
   na gumagamit nito ay hindi kailangang galawin maliban sa require line
   (palitan lang ng "order_helper.php").

   INDEX (hanapin ang "SECTION n" para tumalon):
     SECTION 1 - STATUS     Listahan ng status + label + kulay (badge)
     SECTION 2 - WORKFLOW   Ang TANGING daan para magpalit ng status ng order
     SECTION 3 - REVIEW     Review ng completed order (token, link, larawan)
     SECTION 4 - COMPLETE   SHIPPED -> COMPLETED (customer / staff / auto 7 araw)
     SECTION 5 - PROGRESS UPDATE RESPONSE (It's Good / Revision)
     SECTION 6 - OWNER / STAFF PAGE DISPLAY (tabs, progress history, quotation tab, payment badge)

   BUOD NG TAKBO (paano sila nagtutulungan):
     1) Ang page ay nagpapakita ng status gamit ang figurify_status_badge()
        (SECTION 1). Walang binabago dito, display lang.
     2) Kapag may aksyon (hal. approve_payment, ship_order), ang page ay
        tumatawag ng figurify_wf_perform($conn, $orderId, "action", [...])
        (SECTION 2). Hindi tumatanggap ng status mula sa browser, action lang.
     3) Ang perform ang nagche-check: role -> kanino ang order -> tamang
        status. Naka-transaction at naka-lock ang order row para hindi
        mag-banggaan ang sabay na request. Bawat hakbang ay naka-log sa
        order_status_history, pati ang mga tinanggihan.
     4) Kapag SHIPPED na, ang SECTION 4 ang gumagawa ng COMPLETED at
        nagpapadala ng email na may review link (SECTION 3).

   Tip: kung may order na hindi gumagalaw, tingnan ang order_status_history
   table: nakalista doon kung sino, anong action, at bakit tinanggihan.
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : ang UTAK ng order. Dito nakasulat ang lahat ng status ng order at kung paano
                 nagpapalit ang status.
   PARA SAAN   : para walang makapagpalit ng status nang basta-basta. Isa lang ang daan:
                 figurify_wf_perform().
   PANO NAGANA : 1) Ang page ay magpapadala ng ACTION (hal. approve_payment), hindi ng status.
                 2) Titingnan kung tama ang role, tama ang may-ari ng order, at tama ang
                    kasalukuyang status.
                 3) Kung pasado, ina-update ang status at isinusulat sa history ang nangyari.
                    Kung hindi, tatanggihan at isusulat din sa history.
   4 NA BAHAGI : SECTION 1 = status at kulay | SECTION 2 = workflow (ang tagapagpasya)
                 SECTION 3 = review         | SECTION 4 = pag-complete ng order
   --------------------------------------------------------------------- */

require_once __DIR__ . "/reports_logs_helper.php";
require_once __DIR__ . "/booking_helper.php";
require_once __DIR__ . "/security_helper.php";


/* =====================================================================
   SECTION 1 - STATUS
   (galing sa: order_status_helper.php)
   Listahan ng lahat ng status, kasama ang label at kulay. Dito kumukuha
   ang lahat ng page, kaya iisang lugar lang ang papalitan kapag may
   gustong baguhin sa kulay o pangalan ng status.
   Ang pagpapalit ng status ay nasa SECTION 2 lang.

   Flow: pending -> (needs_correction) -> quoted -> awaiting_payment -> to_verify
   -> for approval -> processing -> awaiting_balance -> for_balance_approval
   -> to_ship -> shipped -> completed
   Refund (owner lang): for_refund -> refunded

   Mga gamit:
     figurify_status_key()    - gawing tamang status ang lumang text (hal. "paid")
     statusLabel()            - friendly na pangalan (hal. "For Approval")
     figurify_status_class()  - CSS class (hal. "status-for_approval")
     figurify_status_colors() - hex colors (para sa JS/calendar)
     figurify_status_badge()  - handa nang kulay na badge (<span>)
   ===================================================================== */

const FIGURIFY_ORDER_STATUSES = [

    "pending" => [
        "label" => "Pending",
        "bg"    => "#FFF3C4",
        "text"  => "#29252A",
    ],

    "needs_correction" => [
        "label" => "Needs Correction",
        "bg"    => "#FDE2D2",
        "text"  => "#29252A",
    ],

    "quoted" => [
        "label" => "Quoted",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "awaiting_payment" => [
        "label" => "Awaiting Payment",
        "bg"    => "#FBE8D6",
        "text"  => "#29252A",
    ],

    "to_verify" => [
        "label" => "Payment Submitted",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "for_approval" => [
        "label" => "For Approval",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "processing" => [
        "label" => "Processing",
        "bg"    => "#FBE8D6",
        "text"  => "#29252A",
    ],

    /* Bago mag-Ship Out, kailangan munang bayaran ng customer ang
       balance - 3 baitang ito ang bagong sub-flow bago maging
       "Ready to Ship" (gaya ng downpayment: awaiting_payment ->
       to_verify -> for_approval, pero para na ito sa balance). */

    "awaiting_balance" => [
        "label" => "Awaiting Balance",
        "bg"    => "#FBE8D6",
        "text"  => "#29252A",
    ],

    "to_verify_balance" => [
        "label" => "Balance Submitted",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "for_balance_approval" => [
        "label" => "Balance Submitted",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "to_ship" => [
        "label" => "Ready to Ship",
        "bg"    => "#E8EDFF",
        "text"  => "#29252A",
    ],

    /* hindi mahanap ng courier ang address -> ibinalik sa customer para ayusin ang shipping details */
    "address_returned" => [
        "label" => "Address Returned",
        "bg"    => "#FDE2D2",
        "text"  => "#29252A",
    ],

    "shipped" => [
        "label" => "Shipped",
        "bg"    => "#E8EDFF",
        "text"  => "#29252A",
    ],

    "completed" => [
        "label" => "Completed",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "cancelled" => [
        "label" => "Cancelled",
        "bg"    => "#FADCDC",
        "text"  => "#29252A",
    ],

    "for_refund" => [
        "label" => "For Refund",
        "bg"    => "#FBDADA",
        "text"  => "#29252A",
    ],

    "refunded" => [
        "label" => "Refunded",
        "bg"    => "#EFE3F3",
        "text"  => "#29252A",
    ],
];


/* Mga lumang/alternatibong status text -> tamang status key.
   Iisang listahan ito para sa figurify_status_key() (SECTION 1) at
   figurify_wf_normalize_status() (SECTION 2), dati ay may dalawang kopya. */

const FIGURIFY_STATUS_SYNONYMS = [
        "forapproval" => "for_approval",

        "for_payment" => "awaiting_payment",
        "forpayment"  => "awaiting_payment",

        "verified" => "to_verify",
        "paid"     => "to_verify",

        "on_process" => "processing",
        "active"     => "processing",
        "confirmed"  => "processing",
        "approved"   => "processing",

        "canceled" => "cancelled",
        "declined" => "cancelled",
        "forrefund" => "for_refund",
        "refund"    => "for_refund",
];


// normalize raw DB status papuntang canonical key - kasama ang
// paglilinis ng "for approval" (may space) at mapping ng mga
// lumang status text (paid, approved, atbp.) sa tamang status

function figurify_status_key(?string $rawStatus): string
{
    $status = strtolower(trim((string) $rawStatus));
    $status = str_replace(["-", " "], "_", $status);

    if (isset(FIGURIFY_STATUS_SYNONYMS[$status])) {
        return FIGURIFY_STATUS_SYNONYMS[$status];
    }

    if (isset(FIGURIFY_ORDER_STATUSES[$status])) {
        return $status;
    }

    return "pending";
}


// friendly label, e.g. "for approval" -> "For Approval"

function statusLabel(?string $rawStatus): string
{
    $key = figurify_status_key($rawStatus);

    return FIGURIFY_ORDER_STATUSES[$key]["label"];
}


// CSS-safe class name, e.g. "status-for_approval" - gamitin ito
// sa halip na i-print ang raw status (may space, nasisira ang class)

function figurify_status_class(?string $rawStatus): string
{
    return "status-" . figurify_status_key($rawStatus);
}


// hex colors, para sa JS-generated calendar badges atbp.

function figurify_status_colors(?string $rawStatus): array
{
    $key = figurify_status_key($rawStatus);

    return [
        "bg"   => FIGURIFY_ORDER_STATUSES[$key]["bg"],
        "text" => FIGURIFY_ORDER_STATUSES[$key]["text"],
    ];
}


// ready-to-print badge, e.g. <span class="order-status status-quoted">Quoted</span>

function figurify_status_badge(?string $rawStatus, string $baseClass = "order-status"): string
{
    $key   = figurify_status_key($rawStatus);
    $class = $baseClass . " status-" . $key;
    $label = FIGURIFY_ORDER_STATUSES[$key]["label"];

    /* kulay ng badge = CSS ng page (.status-for_refund, .status-refunded, .status-needs_correction ...),
       walang inline style */
    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, "UTF-8") . '">'
        . htmlspecialchars($label, ENT_QUOTES, "UTF-8")
        . '</span>';
}


/* =====================================================================
   SECTION 2 - WORKFLOW
   (galing sa: order_workflow.php, ang pinakamalaki)
   Lahat ng pagbabago ng status ay dito dumadaan:
     $result = figurify_wf_perform($conn, $orderId, "approve_payment", [...]);

   Hindi tumatanggap ng status galing sa browser, action lang
   (approve_payment, ship_order, ...). Sinusuri muna ang kasalukuyang
   status, role ng user, kung kanya ang order at iba pang kondisyon.
   Kapag may hindi pumasa, walang binabago at error lang ang balik.

   States -> orders.status:
     PENDING_STAFF_VERIFICATION = pending
     STAFF_CORRECTION_REQUIRED  = needs_correction
     QUOTATION_SENT             = quoted
     AWAITING_PAYMENT           = awaiting_payment
     PAYMENT_SUBMITTED          = to_verify
     PAYMENT_DETAILS_VERIFIED   = for approval
     PROCESSING                 = processing
     PROGRESS_SUBMITTED         = processing + progress "pending"
     REVISION_REQUESTED         = processing + progress "revision"
     REVISION_IN_PROGRESS       = processing + progress "in_progress"
     CUSTOMER_APPROVED          = processing + progress "approved"
     AWAITING_BALANCE           = awaiting_balance
     BALANCE_SUBMITTED          = for_balance_approval
     READY_TO_SHIP              = to_ship
     ADDRESS_RETURNED           = address_returned (mali ang address, aayusin ng customer)
     SHIPPED / COMPLETED        = shipped / completed
     CANCELLED                  = cancelled
     REFUND_REQUIRED / REFUNDED = for_refund / refunded
   Ang mga dinadaanang hakbang ay naka-log sa order_status_history.

   MAPA NG SECTION NA ITO (hanapin ang pamagat sa loob ng file):
     ROLES + ACTOR            sino ang gumagawa (customer / staff / owner / system)
     STATUS NORMALIZATION     DB status -> canonical state (hal. "to_verify" -> PAYMENT_SUBMITTED)
     LABELS + SCHEMA          pangalan ng state; awtomatikong gumagawa ng table/column
     SMALL HELPERS            halaga ng downpayment/balance, set_status, atbp.
     TRANSITION MAP           aling role at aling state ang pwede sa bawat action
     READ-ONLY CHECK          figurify_wf_can: tingnan lang kung pwede (para sa UI)
     PERFORM                  figurify_wf_perform: ang tunay na bantay (transaction + lock)
     HISTORY + LOGGING        order_status_history at activity log
     APPLY FUNCTIONS          isang function bawat action (ang tunay na gagawin)
                              - correction / quotation / cancel
                              - ship out date
                              - payment (downpayment)
                              - progress + revision + final photo
                              - balance, ship, complete
                              - refund

   Ang Rules: 1 revision lang bawat order (FIGURIFY_WF_MAX_REVISIONS),
   kusang ma-approve ang progress photo pagkalipas ng 2 araw (48 oras)
   (FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS), at ang refund flow ay
   for_refund -> refunded (owner lang).
   ===================================================================== */

/* ROLES */

const FIGURIFY_WF_CUSTOMER = "customer";
const FIGURIFY_WF_STAFF    = "staff";
const FIGURIFY_WF_OWNER    = "owner";
const FIGURIFY_WF_SYSTEM   = "system";

const FIGURIFY_WF_MAX_REVISIONS = 1;

/* Ilang oras bago kusang ma-approve ang progress photo (48 = 2 araw).
   May paalala muna pagkalipas ng 24 oras (1 araw). */
const FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS = 48;
const FIGURIFY_PROGRESS_REMINDER_HOURS     = 24;


/* Kunin ang actor mula sa kasalukuyang session. Sa DB, "admin" ang
   role ng owner - dito ginagawang "owner" para malinaw. */

function figurify_wf_session_actor(): array
{
    $rawRole = strtolower((string) ($_SESSION["role"] ?? ""));

    $role = [
        "customer" => FIGURIFY_WF_CUSTOMER,
        "staff"    => FIGURIFY_WF_STAFF,
        "admin"    => FIGURIFY_WF_OWNER,
    ][$rawRole] ?? "";

    return [
        "id"   => isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null,
        "role" => $role,
        "name" => (string) ($_SESSION["full_name"] ?? "Unknown"),
    ];
}

/* ANO ITO: "actor" na SYSTEM (walang tao) - para sa mga kusang nangyayari tulad ng auto-expire o auto-approve. */
function figurify_wf_system_actor(string $name = "System"): array
{
    return ["id" => null, "role" => FIGURIFY_WF_SYSTEM, "name" => $name];
}


/* STATUS NORMALIZATION (DB value -> canonical key)
   Hindi gumagamit ng figurify_status_key dahil iyon ay nagbabalik ng
   "pending" sa hindi kilalang value - dito, ang hindi kilala ay
   "unknown" at walang kahit anong action na papayagan. */

function figurify_wf_normalize_status(?string $rawStatus): string
{
    $status = strtolower(trim((string) $rawStatus));
    $status = str_replace(["-", " "], "_", $status);

    if (isset(FIGURIFY_STATUS_SYNONYMS[$status])) {
        return FIGURIFY_STATUS_SYNONYMS[$status];
    }

    if (isset(FIGURIFY_ORDER_STATUSES[$status])) {
        return $status;
    }

    return "unknown";
}


/* canonical state mula sa DB status + latest progress update row */

function figurify_wf_canonical_from(?string $rawStatus, ?array $latestProgress): string
{
    $status = figurify_wf_normalize_status($rawStatus);

    switch ($status) {

        case "pending":
            return "PENDING_STAFF_VERIFICATION";

        case "needs_correction":
            return "STAFF_CORRECTION_REQUIRED";

        case "quoted":
            return "QUOTATION_SENT";

        case "awaiting_payment":
            return "AWAITING_PAYMENT";

        case "to_verify":
            return "PAYMENT_SUBMITTED";

        case "for_approval":
            return "PAYMENT_DETAILS_VERIFIED";

        case "processing":

            $response = $latestProgress["customer_response"] ?? null;

            if ($response === "pending" && empty($latestProgress["is_final"])) {
                return "PROGRESS_SUBMITTED";
            }
            if ($response === "revision") {
                return "REVISION_REQUESTED";
            }
            if ($response === "in_progress") {
                return "REVISION_IN_PROGRESS";
            }
            if ($response === "approved") {
                return "CUSTOMER_APPROVED";
            }

            // walang update pa, o "declined" ang revision -> balik sa production
            return "PROCESSING";

        case "awaiting_balance":
            return "AWAITING_BALANCE";

        case "to_verify_balance":
        case "for_balance_approval":
            return "BALANCE_SUBMITTED";

        case "to_ship":
            return "READY_TO_SHIP";

        case "address_returned":
            return "ADDRESS_RETURNED";

        case "shipped":
            return "SHIPPED";

        case "completed":
            return "COMPLETED";

        case "cancelled":
            return "CANCELLED";

        case "for_refund":
            return "REFUND_REQUIRED";

        case "refunded":
            return "REFUNDED";
    }

    return "UNKNOWN";
}


/* LABELS - iisang listahan para sa buong website */

const FIGURIFY_WF_CUSTOMER_LABELS = [
    "DRAFT"                      => "Draft",
    "PENDING_STAFF_VERIFICATION" => "Pending",
    "STAFF_CORRECTION_REQUIRED"  => "Needs Correction",
    "CUSTOMER_CORRECTED"         => "Corrected",
    "STAFF_VERIFIED"             => "Verified",
    "QUOTATION_PENDING"          => "For Quotation",
    "QUOTATION_SENT"             => "Quoted",
    "AWAITING_PAYMENT"           => "Awaiting Payment",
    "PAYMENT_SUBMITTED"          => "Payment Submitted",
    "PAYMENT_DETAILS_VERIFIED"   => "Payment Verified",
    "PAYMENT_APPROVED"           => "Payment Approved",
    "PAYMENT_REJECTED"           => "Payment Rejected",
    "PROCESSING"                 => "Processing",
    "PROGRESS_SUBMITTED"         => "Progress Photo Sent",
    "REVISION_REQUESTED"         => "Revision Requested",
    "REVISION_IN_PROGRESS"       => "Revision Accepted",
    "CUSTOMER_APPROVED"          => "Photo Approved",
    "FINALIZED"                  => "Finalized",
    "AWAITING_BALANCE"           => "Awaiting Balance",
    "BALANCE_SUBMITTED"          => "Balance Submitted",
    "BALANCE_APPROVED"           => "Balance Approved",
    "BALANCE_REJECTED"           => "Balance Rejected",
    "READY_TO_SHIP"              => "Ready to Ship",
    "ADDRESS_RETURNED"           => "Fix Shipping Details",
    "SHIPPED"                    => "Shipped",
    "COMPLETED"                  => "Completed",
    "CANCELLATION_REQUESTED"     => "For Cancellation",
    "CANCELLED"                  => "Cancelled",
    "REFUND_REQUIRED"            => "For Refund",
    "REFUNDED"                   => "Refunded",
    "UNKNOWN"                    => "Unknown",
];

/* ANO ITO: ang salitang makikita ng customer para sa isang state (hal. PROCESSING = "In Production"). */
function figurify_wf_state_label(string $canonicalState): string
{
    return FIGURIFY_WF_CUSTOMER_LABELS[$canonicalState] ?? ucwords(strtolower(str_replace("_", " ", $canonicalState)));
}



/* SCHEMA (self-migrating, gaya ng activity_log)
   - orders.revision_count: 0 o 1 (one-time revision)
   - orders.payment_option: "half" (50% downpayment) o "full" (buong bayad agad)
   - order_figures.quantity: ilang piraso ng figure na iyon (default 1)
   - order_status_history: hindi nae-edit na kasaysayan ng bawat transition
   Ligtas paulit-ulit; walang binubura. Tingnan din ang
   Admin/migrations/2026_10_order_workflow.sql para sa manual na paraan. */

function figurify_wf_ensure_schema(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS order_status_history (
            history_id      INT AUTO_INCREMENT PRIMARY KEY,
            order_id        INT NOT NULL,
            from_state      VARCHAR(40) NULL,
            to_state        VARCHAR(40) NOT NULL,
            from_status     VARCHAR(30) NULL,
            to_status       VARCHAR(30) NULL,
            action          VARCHAR(50) NOT NULL,
            actor_id        INT NULL,
            actor_role      VARCHAR(20) NOT NULL,
            actor_name      VARCHAR(100) NULL,
            reason          VARCHAR(1000) NULL,
            details         TEXT NULL,
            created_at      DATETIME NOT NULL,
            INDEX idx_history_order (order_id, history_id)
        )"
    );


    $colStmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'orders'
           AND COLUMN_NAME = 'revision_count'"
    );
    $colStmt->execute();
    $hasColumn = (int) ($colStmt->get_result()->fetch_assoc()["c"] ?? 0) > 0;
    $colStmt->close();

    if (!$hasColumn) {

        $conn->query(
            "ALTER TABLE orders
             ADD COLUMN revision_count TINYINT UNSIGNED NOT NULL DEFAULT 0"
        );

        /* kung may revision request na, gamit na ang isang revision */
        $conn->query(
            "UPDATE orders
             SET revision_count = 1
             WHERE order_id IN (
                 SELECT order_id FROM order_progress_updates
                 WHERE customer_response IN ('revision', 'in_progress', 'declined')
             )"
        );
    }

    /* paalala bago ang auto-approve (isang beses lang bawat progress photo) */
    $remStmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'order_progress_updates'
           AND COLUMN_NAME = 'reminder_sent_at'"
    );
    $remStmt->execute();
    $hasReminder = (int) ($remStmt->get_result()->fetch_assoc()["c"] ?? 0) > 0;
    $remStmt->close();

    if (!$hasReminder) {
        $conn->query("ALTER TABLE order_progress_updates ADD COLUMN reminder_sent_at DATETIME NULL");
    }

    /* Payment page: Half (50% downpayment) o Full payment */
    $optStmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'orders'
           AND COLUMN_NAME = 'payment_option'"
    );
    $optStmt->execute();
    $hasPaymentOption = (int) ($optStmt->get_result()->fetch_assoc()["c"] ?? 0) > 0;
    $optStmt->close();

    if (!$hasPaymentOption) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_option VARCHAR(10) NOT NULL DEFAULT 'half'");
    }

    /* Quantity ng figure: isang row lang kada figure kahit may qty na 2 pataas (dating hinahati sa Figure 1, Figure 2...) */
    $qtyStmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'order_figures'
           AND COLUMN_NAME = 'quantity'"
    );
    $qtyStmt->execute();
    $hasQuantity = (int) ($qtyStmt->get_result()->fetch_assoc()["c"] ?? 0) > 0;
    $qtyStmt->close();

    if (!$hasQuantity) {
        $conn->query("ALTER TABLE order_figures ADD COLUMN quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1");
    }

    /* Delivery problem na ni-report ng customer (hal. sira ang dumating): note + proof + petsa */
    foreach (["delivery_problem_note" => "TEXT NULL", "delivery_problem_proof" => "VARCHAR(255) NULL", "delivery_problem_at" => "DATETIME NULL"] as $problemColumn => $problemType) {

        $problemStmt = $conn->prepare(
            "SELECT COUNT(*) AS c
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = ?"
        );
        $problemStmt->bind_param("s", $problemColumn);
        $problemStmt->execute();
        $hasProblemColumn = (int) ($problemStmt->get_result()->fetch_assoc()["c"] ?? 0) > 0;
        $problemStmt->close();

        if (!$hasProblemColumn) {
            $conn->query("ALTER TABLE orders ADD COLUMN $problemColumn $problemType");
        }
    }

    $ensured = true;
}


/* Delivery problem ng isang order (note, proof, petsa) - para sa My Orders at Shipments */

function figurify_delivery_problem(mysqli $conn, int $orderId): ?array
{
    try {

        figurify_wf_ensure_schema($conn);

        $stmt = $conn->prepare("SELECT delivery_problem_note AS note, delivery_problem_proof AS proof, delivery_problem_at AS reported_at FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ($row && !empty($row["reported_at"])) ? $row : null;

    } catch (\Throwable $e) {
        return null;
    }
}


/* HTML ng report (iisang kopya para sa customer, staff at owner) */

function figurify_delivery_problem_html(?array $problem, string $title): string
{
    if (!$problem) {
        return "";
    }

    $html = '<div class="mo-received" style="margin-top:16px;padding:12px 0;border-top:1px dashed #f0d3de"><strong>' . htmlspecialchars($title, ENT_QUOTES) . '</strong>'
          . '<p>' . nl2br(htmlspecialchars((string) $problem["note"], ENT_QUOTES)) . '</p>';

    if (!empty($problem["proof"])) {
        $url   = htmlspecialchars(figurify_proof_url($problem["proof"]), ENT_QUOTES);
        $html .= '<img src="' . $url . '" alt="Delivery problem proof" style="max-width:160px;border-radius:12px;cursor:zoom-in" onclick="openImageLightbox(this.src, \'Delivery Problem Proof\')">';
    }

    return $html . '<p>Reported ' . htmlspecialchars(date("M j, Y g:i A", strtotime((string) $problem["reported_at"])), ENT_QUOTES) . '</p></div>';
}


/* SMALL HELPERS */

class FigurifyWorkflowException extends RuntimeException
{
    public string $errorCode;

    public function __construct(string $message, string $errorCode = "condition")
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}

/* ANO ITO: ihinto ang aksyon at magpakita ng error. Ang figurify_wf_perform() ang sasalo at magro-rollback. */
function figurify_wf_fail(string $message, string $code = "condition"): void
{
    throw new FigurifyWorkflowException($message, $code);
}

/* ANO ITO: numbers lang (Tracking Number, Reference Number) - walang maikling limit,
   hanggang 100 lang dahil iyon ang laki ng column sa database */
function figurify_wf_is_number_text(string $value): bool
{
    return preg_match('/^[0-9]{1,100}$/', $value) === 1;
}

/* ANO ITO: kunin ang PINAKABAGONG progress update ng order. $forUpdate = true para i-lock ang row habang nag-e-edit. */
function figurify_wf_latest_progress(mysqli $conn, int $orderId, bool $forUpdate = false): ?array
{
    $stmt = $conn->prepare(
        "SELECT update_id, order_id, customer_response, is_final, revision_note, image_path, created_at
         FROM order_progress_updates
         WHERE order_id = ?
         ORDER BY created_at DESC, update_id DESC
         LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "")
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/* unang bayad ng customer: buong total kapag "full", 50% downpayment kapag "half" (default).
   Iisang kwenta ito ng Payment page, My Orders, staff/owner pages at refund. */

function figurify_wf_is_full_payment(array $order): bool
{
    return ($order["payment_option"] ?? "half") === "full";
}

function figurify_wf_downpayment_amount(array $order): float
{
    $total = (float) ($order["total_amount"] ?? 0);

    return figurify_wf_is_full_payment($order) ? round($total, 2) : round($total / 2, 2);
}

/* pangalan ng unang bayad (label sa Payment page, My Orders at staff/owner pages) */

function figurify_wf_downpayment_label(array $order): string
{
    return figurify_wf_is_full_payment($order) ? "Full Payment" : "50% Downpayment";
}

/* natitirang balance base sa totoong quotation total - HINDI na mano-mano */

function figurify_wf_balance_amount(array $order): float
{
    $total = (float) ($order["total_amount"] ?? 0);

    return max(0.0, round($total - figurify_wf_downpayment_amount($order), 2));
}

/* kabuuang naibayad na (para sa refund) */

function figurify_wf_amount_paid(array $order): float
{
    $paid = 0.0;

    if (!empty($order["payment_proof"]) || !empty($order["paid_at"])) {
        $paid += figurify_wf_downpayment_amount($order);
    }

    if (!empty($order["balance_paid_at"])) {
        $paid += (float) ($order["balance_to_pay"] ?? 0);
    }

    return round($paid, 2);
}

/* ANO ITO: nililinis ang dahilan (tanggal sobrang space, putol kapag lampas sa $max na letra). */
function figurify_wf_clean_reason(?string $reason, int $max = 500): string
{
    $reason = trim((string) $reason);
    $reason = preg_replace('/\s+/u', " ", $reason) ?? "";

    return mb_substr($reason, 0, $max);
}

/* UPDATE na may check sa lumang status; kapag may naunang ibang request, 0 rows at rollback */

function figurify_wf_set_status(
    mysqli $conn,
    array $order,
    string $newStatus,
    array $extraSets = [],
    string $extraTypes = "",
    array $extraValues = []
): void {

    $sql = "UPDATE orders SET status = ?"
        . (empty($extraSets) ? "" : ", " . implode(", ", $extraSets))
        . " WHERE order_id = ? AND status = ?";

    $stmt   = $conn->prepare($sql);
    $types  = "s" . $extraTypes . "is";
    $values = array_merge([$newStatus], $extraValues, [(int) $order["order_id"], (string) $order["status"]]);

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected !== 1) {
        figurify_wf_fail("This order was just updated. Please refresh.", "conflict");
    }
}

/* ANO ITO: pang-execute ng UPDATE/INSERT query nang ligtas (prepared statement).
   Ibinabalik ang bilang ng nabagong row. Ginagamit ito pang-check kung may nauna nang nagbago (conflict). */
function figurify_wf_exec(mysqli $conn, string $sql, string $types, array $values): int
{
    $stmt = $conn->prepare($sql);

    if ($types !== "") {
        $stmt->bind_param($types, ...$values);
    }

    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected;
}


/* TRANSITION MAP
   action => sinong role at saang states pwede.
   Ang susunod na status ay ang apply function ang nagpapasya. */

const FIGURIFY_WF_PRODUCTION_STATES = [
    "PROCESSING", "PROGRESS_SUBMITTED", "REVISION_REQUESTED",
    "REVISION_IN_PROGRESS", "CUSTOMER_APPROVED",
];

/* ANO ITO: ang LISTAHAN ng bawat action: sino ang pwede (customer/staff/owner/system) at sa anong state.
   Dito mo hahanapin kung bakit "hindi pwede" ang isang aksyon. */
function figurify_wf_transition_map(): array
{
    $staffOrOwner = [FIGURIFY_WF_STAFF, FIGURIFY_WF_OWNER];

    return [

        /* order details / verification */
        "return_for_correction" => ["roles" => $staffOrOwner,              "from" => ["PENDING_STAFF_VERIFICATION"]],
        "submit_correction"     => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["STAFF_CORRECTION_REQUIRED"]],
        "verify_and_quote"      => ["roles" => $staffOrOwner,              "from" => ["PENDING_STAFF_VERIFICATION"]],
        "decline_order"         => ["roles" => $staffOrOwner,              "from" => ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED"]],

        /* quotation */
        "accept_quotation"      => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["QUOTATION_SENT"]],
        "cancel_order"          => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED", "QUOTATION_SENT", "AWAITING_PAYMENT"]],
        /* CUSTOMER: palitan ang ship out date ng parehong order (hal. napuno na ang napili niya) -
           hanggang hindi pa naaaprubahan ng owner ang down payment. Hindi nagbabago ang status. */
        "change_date"           => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED", "QUOTATION_SENT", "AWAITING_PAYMENT", "PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"]],
        /* CUSTOMER: nabayaran na pero napuno ang petsa at ayaw lumipat -> For Refund (owner ang magre-refund) */
        "cancel_full_date"      => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"]],
        /* STAFF/OWNER (Verification): mali ang ORDER DETAILS kahit bayad na -> pwedeng i-edit ng customer, tapos babalik sa Verification */
        "return_paid_for_correction" => ["roles" => $staffOrOwner,         "from" => ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"]],
        "expire_quote"          => ["roles" => [FIGURIFY_WF_SYSTEM],       "from" => ["QUOTATION_SENT"]],
        /* napuno na ang petsa ng mga nakabayad -> cancel ang hindi pa nakabayad */
        "release_full_date"     => ["roles" => [FIGURIFY_WF_SYSTEM],       "from" => ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED", "QUOTATION_SENT", "AWAITING_PAYMENT"]],
        /* lumang Dress Up order na naka-"pending" pa (bago ang direct-payment
           update) -> ilipat sa bayad; walang quotation ang Dress Up */
        "dressup_to_payment"    => ["roles" => [FIGURIFY_WF_SYSTEM],       "from" => ["PENDING_STAFF_VERIFICATION"]],

        /* downpayment (OWNER LANG ang nagpapasya) */
        "submit_payment"        => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["AWAITING_PAYMENT"]],
        /* STAFF (o OWNER kung wala si staff): sinusuri kung tama ang order
           details, customer info at payment info (reference no., proof,
           halaga). HINDI ito pag-approve ng bayad - ipinapasa lang sa owner. */
        "verify_payment_details" => ["roles" => $staffOrOwner,             "from" => ["PAYMENT_SUBMITTED"]],
        /* STAFF/OWNER: mali ang details/info -> DECLINE, babalik sa customer
           (Payment Required) kasama ang dahilan na mababasa niya sa My Orders */
        "decline_payment_details" => ["roles" => $staffOrOwner,            "from" => ["PAYMENT_SUBMITTED"]],
        /* OWNER LANG: siya ang nagkukumpirma kung talagang pumasok ang pera */
        "approve_payment"       => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["PAYMENT_DETAILS_VERIFIED"]],
        "reject_payment"        => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"]],
        "cancel_paid_order"     => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"]],

        /* production / one-time revision */
        "submit_progress"       => ["roles" => $staffOrOwner,              "from" => ["PROCESSING", "REVISION_IN_PROGRESS"]],
        "approve_progress"      => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["PROGRESS_SUBMITTED"]],
        "auto_approve_progress" => ["roles" => [FIGURIFY_WF_SYSTEM],       "from" => ["PROGRESS_SUBMITTED"]],
        "request_revision"      => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["PROGRESS_SUBMITTED"]],
        "accept_revision"       => ["roles" => $staffOrOwner,              "from" => ["REVISION_REQUESTED"]],
        "decline_revision"      => ["roles" => $staffOrOwner,              "from" => ["REVISION_REQUESTED"]],
        /* para sa mga lumang order na approved na pero processing pa, at lumang to_ship na hindi dumaan sa balance */
        "finalize_order"        => ["roles" => $staffOrOwner,              "from" => ["CUSTOMER_APPROVED", "READY_TO_SHIP"]],
        "submit_final_photo"    => ["roles" => $staffOrOwner,              "from" => ["READY_TO_SHIP"]],

        /* balance (OWNER LANG ang nagpapasya) */
        "submit_balance"        => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["AWAITING_BALANCE"]],
        "approve_balance"       => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["BALANCE_SUBMITTED"]],
        "reject_balance"        => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["BALANCE_SUBMITTED"]],

        /* shipment */
        "ship_order"            => ["roles" => $staffOrOwner,              "from" => ["READY_TO_SHIP"]],
        /* hindi mahanap ng LBC / Lalamove ang address -> ibalik sa customer (may dahilan) */
        "return_shipping_details" => ["roles" => $staffOrOwner,            "from" => ["READY_TO_SHIP"]],
        /* CUSTOMER: inayos ang shipping details -> balik sa Ready to Ship */
        "update_shipping_details" => ["roles" => [FIGURIFY_WF_CUSTOMER],   "from" => ["ADDRESS_RETURNED"]],
        /* customer = "Order Received"; system = auto pagkalipas ng 7 araw */
        "complete_order"        => ["roles" => array_merge($staffOrOwner, [FIGURIFY_WF_CUSTOMER, FIGURIFY_WF_SYSTEM]), "from" => ["SHIPPED"]],

        /* refund (OWNER LANG). Ang start_refund mula SHIPPED/COMPLETED
           ay ang tahasang, owner-only na exception workflow (hal. sira ang
           dumating, defective) - kailangan ng dahilan at may bayad. */
        "start_refund"          => ["roles" => [FIGURIFY_WF_OWNER],        "from" => array_merge(
                                        FIGURIFY_WF_PRODUCTION_STATES,
                                        ["AWAITING_BALANCE", "BALANCE_SUBMITTED", "READY_TO_SHIP", "ADDRESS_RETURNED", "SHIPPED", "COMPLETED"]
                                    )],
        "complete_refund"       => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["REFUND_REQUIRED"]],
        "refund_no_payment"     => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["REFUND_REQUIRED"]],
    ];
}

/* Shipments page "Return to Customer" - hindi maipadala dahil sa shipping details;
   babalik sa customer (ADDRESS_RETURNED) para ayusin niya sa My Orders. */
const FIGURIFY_RETURN_ADDRESS_REASONS = [
    "address_not_found"  => "The courier (LBC / Lalamove) cannot find the address",
    "incomplete_address" => "The address is incomplete or unclear",
    "contact_unreachable" => "The contact number cannot be reached",
];

/* Verification page "Decline" - mali ang details/info; babalik sa customer
   (Payment Required) at mababasa niya ang dahilan sa My Orders. */
const FIGURIFY_WF_DETAILS_DECLINE_REASONS = [
    "reference_mismatch"         => "Reference Number Does Not Match the Proof",
    "unclear_payment_proof"      => "Payment Proof Is Unclear or Invalid",
    "incorrect_amount"           => "Amount Paid Does Not Match the Down Payment",
    "incorrect_shipping_details" => "Incorrect or Incomplete Shipping / Contact Details",
];

/* Verification page "Decline" - mali ang ORDER DETAILS. Kahit bayad na, ibabalik sa customer para ma-edit
   (hindi ang payment page); pagkatapos ay babalik sa Verification. */
const FIGURIFY_WF_ORDER_FIX_REASONS = [
    "incomplete_order_details" => "Incomplete Order Details",
    "incorrect_order_details"  => "Incorrect Order Details",
];

const FIGURIFY_WF_ACTION_LABELS = [
    "submit_order"          => "Order Submitted",
    "return_for_correction" => "Returned for Correction",
    "submit_correction"     => "Customer Submitted Corrections",
    "verify_and_quote"      => "Order Details Verified & Quotation Sent",
    "decline_order"         => "Order Declined",
    "accept_quotation"      => "Quotation Accepted",
    "cancel_order"          => "Order Cancelled by Customer",
    "cancel_full_date"      => "Cancelled (Ship Out Date Full)",
    "return_paid_for_correction" => "Returned for Correction (Paid Order)",
    "expire_quote"          => "Quotation Expired",
    "release_full_date"     => "Ship Out Date Full",
    "change_date"           => "Ship Out Date Changed",
    "dressup_to_payment"    => "Ready for Payment (Dress Up)",
    "submit_payment"        => "Payment Submitted",
    "verify_payment_details" => "Payment Details Verified",
    "decline_payment_details" => "Payment Details Declined",
    "approve_payment"       => "Payment Approved",
    "reject_payment"        => "Payment Rejected",
    "cancel_paid_order"     => "Order Cancelled (Refund Required)",
    "submit_progress"       => "Progress Photo Sent",
    "approve_progress"      => "Progress Photo Approved",
    "auto_approve_progress" => "Progress Photo Auto-Approved",
    "request_revision"      => "Revision Requested",
    "accept_revision"       => "Revision Accepted",
    "decline_revision"      => "Revision Declined",
    "finalize_order"        => "Order Finalized",
    "submit_final_photo"    => "Final Photo Sent",
    "submit_balance"        => "Balance Submitted",
    "approve_balance"       => "Balance Approved",
    "reject_balance"        => "Balance Rejected",
    "ship_order"            => "Order Shipped",
    "return_shipping_details" => "Shipping Details Returned",
    "update_shipping_details" => "Shipping Details Updated",
    "complete_order"        => "Order Completed",
    "start_refund"          => "Refund Started",
    "complete_refund"       => "Refund Completed",
    "refund_no_payment"     => "Cancelled: No Payment Received",
];

/* ANO ITO: ang pangalan ng action para sa history/log (hal. approve_payment = "Payment Approved"). */
function figurify_wf_action_label(string $action): string
{
    return FIGURIFY_WF_ACTION_LABELS[$action] ?? ucwords(str_replace("_", " ", $action));
}


/* READ-ONLY CHECK (para sa UI: ipakita lang ang mga action na pwede)
   Hindi ito security - ang figurify_wf_perform ang laging nagpapasya. */

function figurify_wf_allowed_actions(string $canonicalState, string $role): array
{
    $allowed = [];

    foreach (figurify_wf_transition_map() as $action => $rule) {
        if (in_array($role, $rule["roles"], true) && in_array($canonicalState, $rule["from"], true)) {
            $allowed[] = $action;
        }
    }

    return $allowed;
}

/* check muna bago mag-save ng file (walang lock, perform pa rin ang huling sasala) */

function figurify_wf_can(mysqli $conn, int $orderId, string $action, ?array $actor = null): array
{
    figurify_wf_ensure_schema($conn);

    $actor = $actor ?? figurify_wf_session_actor();
    $map   = figurify_wf_transition_map();

    if (!isset($map[$action])) {
        return ["ok" => false, "error" => "Unknown action."];
    }

    if (!in_array($actor["role"], $map[$action]["roles"], true)) {
        return ["ok" => false, "error" => "You are not allowed to perform this action."];
    }

    $stmt = $conn->prepare("SELECT order_id, user_id, status FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return ["ok" => false, "error" => "Order not found."];
    }

    if ($actor["role"] === FIGURIFY_WF_CUSTOMER && (int) $order["user_id"] !== (int) $actor["id"]) {
        return ["ok" => false, "error" => "Order not found."];
    }

    $state = figurify_wf_canonical_from($order["status"], figurify_wf_latest_progress($conn, $orderId));

    if (!in_array($state, $map[$action]["from"], true)) {
        return ["ok" => false, "error" => "This action isn't available for the order's current status (" . figurify_wf_state_label($state) . ")."];
    }

    return ["ok" => true, "error" => null, "state" => $state];
}


/* PERFORM - ang tanging daan para magbago ang status */

function figurify_wf_perform(
    mysqli $conn,
    int $orderId,
    string $action,
    array $params = [],
    ?array $actor = null
): array {

    figurify_wf_ensure_schema($conn);

    /* lahat ng text na ise-save (reason, notes, message) - ligtas sa database (walang "?") */
    array_walk_recursive($params, function (&$value) {
        if (is_string($value)) {
            $value = figurify_db_text($value);
        }
    });

    $actor = $actor ?? figurify_wf_session_actor();
    $map   = figurify_wf_transition_map();

    $deny = function (string $message, string $code, ?string $state = null, ?array $order = null) use ($conn, $orderId, $action, $actor) {

        figurify_wf_log_denied($conn, $orderId, $action, $actor, $message, $code, $state);

        return [
            "ok"         => false,
            "error"      => $message,
            "code"       => $code,
            "from_state" => $state,
            "order"      => $order,
        ];
    };

    if ($orderId <= 0 || !isset($map[$action])) {
        return $deny("Invalid request.", "invalid_action");
    }

    /* 1. ROLE - bago pa man basahin ang order */

    if ($actor["role"] === "" || !in_array($actor["role"], $map[$action]["roles"], true)) {
        return $deny("You are not allowed to perform this action.", "forbidden");
    }

    $applyFn = "figurify_wf_apply_" . $action;

    if (!function_exists($applyFn)) {
        return $deny("Invalid request.", "invalid_action");
    }

    try {

        $conn->begin_transaction();

        /* 2. I-lock ang order row - sabay na request ay maghihintay */

        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? FOR UPDATE");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            $conn->rollback();
            return $deny("Order not found.", "not_found");
        }

        /* 3. OWNERSHIP (IDOR) - customer sa sarili niyang order lang */

        if ($actor["role"] === FIGURIFY_WF_CUSTOMER && (int) $order["user_id"] !== (int) $actor["id"]) {
            $conn->rollback();
            return $deny("Order not found.", "not_found");
        }

        /* 4. CURRENT STATE */

        $latest = figurify_wf_latest_progress($conn, $orderId, true);
        $state  = figurify_wf_canonical_from($order["status"], $latest);

        if (!in_array($state, $map[$action]["from"], true)) {
            $conn->rollback();
            return $deny(
                "This action isn't available for the order's current status (" . figurify_wf_state_label($state) . ").",
                "invalid_state",
                $state,
                $order
            );
        }

        /* 5. CONDITIONS + WRITES (pareho sa iisang transaction) */

        $ctx = [
            "state"  => $state,
            "latest" => $latest,
            "actor"  => $actor,
        ];

        $outcome = $applyFn($conn, $order, $params, $ctx);

        /* 6. HISTORY (bawat hakbang, kasama ang mga transient state) */

        $steps     = $outcome["steps"] ?? [];
        $toStatus  = $outcome["to_status"] ?? $order["status"];
        $reason    = $outcome["reason"] ?? null;
        $details   = $outcome["details"] ?? null;
        $stepCount = count($steps);

        foreach ($steps as $i => $step) {

            $isLast = ($i === $stepCount - 1);

            figurify_wf_record_history(
                $conn,
                $orderId,
                $step[0],
                $step[1],
                ($i === 0) ? (string) $order["status"] : null,
                $isLast ? (string) $toStatus : null,
                $action,
                $actor,
                $reason,
                $details
            );
        }

        $conn->commit();

    } catch (FigurifyWorkflowException $e) {

        $conn->rollback();

        return $deny($e->getMessage(), $e->errorCode, $state ?? null, $order ?? null);

    } catch (\Throwable $e) {

        try { $conn->rollback(); } catch (\Throwable $ignored) {}

        error_log("[figurify workflow] " . $action . " #" . $orderId . ": " . $e->getMessage());

        return $deny("Something went wrong. Nothing was changed. Please try again.", "error", $state ?? null, $order ?? null);
    }

    $finalState = !empty($steps) ? end($steps)[1] : $state;

    /* activity log (hiwalay sa history; para sa Activity Log page) */
    figurify_wf_log_activity_row(
        $conn,
        $actor,
        "wf_" . $action,
        figurify_wf_action_label($action) . " for order " . $orderId
            . (($toStatus !== $order["status"]) ? (" (" . figurify_wf_state_label($state) . " to " . figurify_wf_state_label($finalState) . ")") : "")
            . ($reason ? (": " . $reason) : ""),
        $orderId
    );

    return [
        "ok"          => true,
        "error"       => null,
        "code"        => "ok",
        "from_state"  => $state,
        "to_state"    => $finalState,
        "from_status" => $order["status"],
        "to_status"   => $toStatus,
        "order"       => $order,
        "data"        => $outcome["data"] ?? [],
    ];
}


/* HISTORY + LOGGING */

function figurify_wf_record_history(
    mysqli $conn,
    int $orderId,
    ?string $fromState,
    string $toState,
    ?string $fromStatus,
    ?string $toStatus,
    string $action,
    array $actor,
    ?string $reason = null,
    $details = null
): void {

    $detailsJson = ($details === null) ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $actorId     = $actor["id"];
    $actorRole   = $actor["role"];
    $actorName   = mb_substr((string) $actor["name"], 0, 100);
    $reason      = ($reason === null) ? null : mb_substr(figurify_db_text($reason), 0, 1000);

    $stmt = $conn->prepare(
        "INSERT INTO order_status_history
            (order_id, from_state, to_state, from_status, to_status, action,
             actor_id, actor_role, actor_name, reason, details, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    $stmt->bind_param(
        "isssssissss",
        $orderId,
        $fromState,
        $toState,
        $fromStatus,
        $toStatus,
        $action,
        $actorId,
        $actorRole,
        $actorName,
        $reason,
        $detailsJson
    );

    $stmt->execute();
    $stmt->close();
}

/* ANO ITO: nagsusulat ng isang linya sa activity log (para makita ng owner kung sino ang gumawa ng ano).
   Kapag pumalya ang pagsulat, hindi nito sisirain ang pag-update ng order. */
function figurify_wf_log_activity_row(mysqli $conn, array $actor, string $type, string $description, ?int $orderId): void
{
    try {

        figurify_ensure_activity_log_table($conn);

        $actorId   = $actor["id"];
        $actorName = mb_substr((string) $actor["name"], 0, 100);
        $actorRole = ($actor["role"] === FIGURIFY_WF_OWNER) ? "admin" : (string) $actor["role"];
        $description = figurify_db_text($description);

        $stmt = $conn->prepare(
            "INSERT INTO activity_log
                (actor_id, actor_name, actor_role, action_type, description, order_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("issssi", $actorId, $actorName, $actorRole, $type, $description, $orderId);
        $stmt->execute();
        $stmt->close();

    } catch (\Throwable $e) {
        error_log("[figurify workflow] activity log failed: " . $e->getMessage());
    }
}

/* itala ang mga tinanggihang pagtatangka (lalo na forbidden) */

function figurify_wf_log_denied(
    mysqli $conn,
    int $orderId,
    string $action,
    array $actor,
    string $message,
    string $code,
    ?string $state
): void {

    // ordinaryong stale/double-click ay hindi na kailangang itala
    if (in_array($code, ["conflict"], true)) {
        return;
    }

    figurify_wf_log_activity_row(
        $conn,
        $actor,
        ($code === "forbidden" || $code === "csrf") ? "unauthorized_attempt" : "transition_denied",
        "Denied " . $action . " on order " . $orderId
            . " by " . ($actor["role"] !== "" ? $actor["role"] : "unknown role")
            . ($state ? (" (state: " . $state . ")") : "")
            . ": " . $message,
        $orderId > 0 ? $orderId : null
    );
}

/* kasaysayan para sa Order Details / Staff / Owner pages (pinakaluma sa itaas) */

function figurify_wf_history(mysqli $conn, int $orderId): array
{
    try {

        figurify_wf_ensure_schema($conn);

        $stmt = $conn->prepare(
            "SELECT history_id, from_state, to_state, from_status, to_status, action,
                    actor_role, actor_name, reason, details, created_at
             FROM order_status_history
             WHERE order_id = ?
             ORDER BY history_id ASC"
        );
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;

    } catch (\Throwable $e) {
        return [];
    }
}

/* Verification pages: ipakita ang mga naunang dahilan ng pag-decline -
   1) quotation / order details (ibinalik para i-correct)  2) payment (na-decline ang bayad o details) */

function figurify_decline_notes_html(mysqli $conn, int $orderId): string
{
    $notes = [
        "Quotation / Order Details Reason" => figurify_wf_latest_reason($conn, $orderId, "STAFF_CORRECTION_REQUIRED"),
    ];

    $paymentReason = null;

    foreach (array_reverse(figurify_wf_history($conn, $orderId)) as $row) {
        if (in_array($row["action"], ["reject_payment", "decline_payment_details"], true) && !empty($row["reason"])) {
            $paymentReason = $row["reason"];
            break;
        }
    }

    $notes["Payment Reason"] = $paymentReason;

    $html = "";

    foreach ($notes as $label => $text) {
        if ($text !== null && $text !== "") {
            $html .= '<div class="qv-notes-row"><div class="qv-spec-label">Previously Declined: ' . htmlspecialchars($label, ENT_QUOTES) . '</div>'
               . '<div class="qv-note-box">' . nl2br(htmlspecialchars($text, ENT_QUOTES)) . '</div></div>';
        }
    }

    return $html;
}

/* pinakahuling dahilan para sa isang state (hal. bakit ibinalik para i-correct,
   bakit na-reject ang bayad) - galing sa history, hindi sa hiwalay na column */

function figurify_wf_latest_reason(mysqli $conn, int $orderId, string $toState): ?string
{
    try {

        figurify_wf_ensure_schema($conn);

        $stmt = $conn->prepare(
            "SELECT reason FROM order_status_history
             WHERE order_id = ? AND to_state = ?
             ORDER BY history_id DESC LIMIT 1"
        );
        $stmt->bind_param("is", $orderId, $toState);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ($row && $row["reason"] !== null && $row["reason"] !== "") ? $row["reason"] : null;

    } catch (\Throwable $e) {
        return null;
    }
}


/* para sa submit_order.php - unang row ng kasaysayan (DRAFT -> PENDING) */

function figurify_wf_record_order_created(mysqli $conn, int $orderId, array $actor, string $initialStatus = "pending"): void
{
    try {
        figurify_wf_ensure_schema($conn);
        // Dress Up: diretso sa bayad (walang quotation)
        $toState = ($initialStatus === "awaiting_payment") ? "AWAITING_PAYMENT" : "PENDING_STAFF_VERIFICATION";
        figurify_wf_record_history($conn, $orderId, "DRAFT", $toState, null, $initialStatus, "submit_order", $actor);
    } catch (\Throwable $e) {
        error_log("[figurify workflow] could not record order creation: " . $e->getMessage());
    }
}


/* CSRF para sa lahat ng order action endpoints. Ang token ay awtomatikong
   idinadagdag sa bawat POST form ng Shared/popup-and-security.php. */

function figurify_wf_check_post_and_csrf(): bool
{
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
        return false;
    }

    return figurify_verify_csrf($_POST["csrf_token"] ?? null);
}

/* Gamitin sa simula ng redirect-type na action file. */

function figurify_wf_require_post_csrf(mysqli $conn, string $redirectTo, int $orderId = 0): void
{
    if (figurify_wf_check_post_and_csrf()) {
        return;
    }

    figurify_wf_log_activity_row(
        $conn,
        figurify_wf_session_actor(),
        "unauthorized_attempt",
        "Rejected request to " . basename($_SERVER["SCRIPT_NAME"] ?? "") . " (missing/invalid security token or not POST)",
        $orderId > 0 ? $orderId : null
    );

    if (function_exists("figurify_flash")) {
        figurify_flash("Your session expired. Please try again.", "error");
    }

    header("Location: " . $redirectTo);
    exit();
}


/* APPLY FUNCTIONS - isa bawat action. Tumatakbo sa loob ng transaction
   na may lock na sa order row. Mag-throw (figurify_wf_fail) para
   tanggihan; walang mase-save.
   Ibinabalik: to_status, steps [[from_state, to_state], ...], reason,
   details, data. */


/* STAFF: ibalik para i-correct */

function figurify_wf_apply_return_for_correction(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 500);

    if ($reason === "") {
        figurify_wf_fail("Please tell the customer what needs to be corrected.");
    }

    figurify_wf_set_status($conn, $order, "needs_correction");

    return [
        "to_status" => "needs_correction",
        "steps"     => [["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED"]],
        "reason"    => $reason,
    ];
}


/* STAFF/OWNER: mali ang order details ng bayad na order -> ibalik sa customer para ma-edit */

function figurify_wf_apply_return_paid_for_correction(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 500);

    if ($reason === "") {
        figurify_wf_fail("Please tell the customer what needs to be corrected.");
    }

    figurify_wf_set_status($conn, $order, "needs_correction");

    return [
        "to_status" => "needs_correction",
        "steps"     => [[$ctx["state"], "STAFF_CORRECTION_REQUIRED"]],
        "reason"    => $reason,
    ];
}


/* CUSTOMER: isumite ang corrections */

function figurify_wf_apply_submit_correction(mysqli $conn, array $order, array $p, array $ctx): array
{
    $note = figurify_wf_clean_reason($p["note"] ?? "", 1000);

    $newDate     = trim((string) ($p["ship_out_date"] ?? ""));
    $figureNotes = is_array($p["figure_notes"] ?? null) ? $p["figure_notes"] : [];
    $newImages   = is_array($p["new_images"] ?? null) ? $p["new_images"] : [];

    $changes = [];

    /* ship out date - dumaan pa rin sa parehong booking cap rules */
    if ($newDate !== "" && $newDate !== (string) $order["ship_out_date"]) {

        $normalized = date("Y-m-d", strtotime($newDate) ?: 0);

        if ($normalized !== $newDate) {
            figurify_wf_fail("Invalid ship out date.");
        }

        $check = ($order["order_type"] === "rush")
            ? figurify_check_rush_date($conn, $normalized)
            : figurify_check_nonrush_date($conn, $normalized);

        if (!$check["ok"]) {
            figurify_wf_fail($check["reason"] ?? "That ship out date is not available.");
        }

        figurify_wf_exec($conn, "UPDATE orders SET ship_out_date = ? WHERE order_id = ?", "si", [$normalized, (int) $order["order_id"]]);

        $changes["ship_out_date"] = ["from" => $order["ship_out_date"], "to" => $normalized];
    }

    /* figure notes - figure ng order na ito lang */
    foreach ($figureNotes as $figureId => $noteText) {

        $figureId = (int) $figureId;
        $noteText = mb_substr(trim((string) $noteText), 0, 2000);

        $affected = figurify_wf_exec(
            $conn,
            "UPDATE order_figures SET notes = ? WHERE figure_id = ? AND order_id = ? AND COALESCE(notes, '') <> ?",
            "siis",
            [$noteText, $figureId, (int) $order["order_id"], $noteText]
        );

        if ($affected > 0) {
            $changes["notes"][] = $figureId;
        }
    }

    /* tinanggal na reference images - larawan ng figure ng order na ito lang
       (ang file mismo ay binubura ng endpoint kapag tagumpay na ang workflow) */
    $removeIds = is_array($p["remove_images"] ?? null) ? array_map("intval", $p["remove_images"]) : [];

    foreach ($removeIds as $imageId) {

        $removed = figurify_wf_exec(
            $conn,
            "DELETE i FROM order_figure_images i
             JOIN order_figures f ON f.figure_id = i.figure_id
             WHERE i.image_id = ? AND f.order_id = ?",
            "ii",
            [$imageId, (int) $order["order_id"]]
        );

        if ($removed > 0) {
            $changes["removed_images"][] = $imageId;
        }
    }

    /* bagong reference images (na-validate at na-save na ng endpoint) */
    foreach ($newImages as $img) {

        $figureId = (int) ($img["figure_id"] ?? 0);
        $path     = (string) ($img["path"] ?? "");

        $belongs = figurify_wf_exec(
            $conn,
            "INSERT INTO order_figure_images (figure_id, image_path)
             SELECT figure_id, ? FROM order_figures WHERE figure_id = ? AND order_id = ?",
            "sii",
            [$path, $figureId, (int) $order["order_id"]]
        );

        if ($belongs > 0) {
            $changes["images"][] = $path;
        }
    }

    if (empty($changes) && $note === "") {
        figurify_wf_fail("Make the changes or add a note first.");
    }

    /* bayad na ang order (may payment proof) -> babalik sa Verification, hindi sa Quotation */
    $isPaid   = !empty($order["payment_proof"]);
    $toStatus = $isPaid ? "to_verify" : "pending";

    figurify_wf_set_status($conn, $order, $toStatus);

    return [
        "to_status" => $toStatus,
        "steps"     => [
            ["STAFF_CORRECTION_REQUIRED", "CUSTOMER_CORRECTED"],
            ["CUSTOMER_CORRECTED", $isPaid ? "PAYMENT_SUBMITTED" : "PENDING_STAFF_VERIFICATION"],
        ],
        "reason"    => ($note !== "") ? $note : null,
        "details"   => $changes ?: null,
    ];
}


/* STAFF/OWNER: verified -> quotation (iisang aksyon, pero
   nakatala ang bawat lohikal na hakbang) */

function figurify_wf_apply_verify_and_quote(mysqli $conn, array $order, array $p, array $ctx): array
{
    $prices = is_array($p["prices"] ?? null) ? $p["prices"] : [];

    $figStmt = $conn->prepare("SELECT figure_id, quantity FROM order_figures WHERE order_id = ?");
    $orderId = (int) $order["order_id"];
    $figStmt->bind_param("i", $orderId);
    $figStmt->execute();
    $figureRows = $figStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $figStmt->close();

    if (empty($figureRows)) {
        figurify_wf_fail("This order has no figures to quote.");
    }

    /* figure_id => quantity (ang presyong tina-type ng staff/owner ay para sa ISANG piraso) */
    $quantities = [];

    foreach ($figureRows as $figureRow) {
        $quantities[(int) $figureRow["figure_id"]] = max(1, (int) $figureRow["quantity"]);
    }

    $figuresTotal = 0.0;

    foreach ($quantities as $figureId => $quantity) {

        if (!isset($prices[$figureId]) || !is_numeric($prices[$figureId]) || (float) $prices[$figureId] < 0) {
            figurify_wf_fail("Please enter a valid price for every figure.");
        }

        $price = round((float) $prices[$figureId], 2);

        figurify_wf_exec($conn, "UPDATE order_figures SET quoted_price = ? WHERE figure_id = ? AND order_id = ?", "dii", [$price, $figureId, $orderId]);

        $figuresTotal += $price * $quantity;
    }

    $rushFee = ($order["order_type"] === "rush") ? (float) $order["rush_fee"] : 0.0;
    $total   = round($figuresTotal + $rushFee, 2);

    if ($total <= 0) {
        figurify_wf_fail("The quotation total must be greater than zero.");
    }

    $expiresAt = date("Y-m-d H:i:s", strtotime("+2 days"));

    figurify_wf_set_status($conn, $order, "quoted", ["total_amount = ?", "quote_expires_at = ?"], "ds", [$total, $expiresAt]);

    return [
        "to_status" => "quoted",
        "steps"     => [
            ["PENDING_STAFF_VERIFICATION", "STAFF_VERIFIED"],
            ["STAFF_VERIFIED", "QUOTATION_PENDING"],
            ["QUOTATION_PENDING", "QUOTATION_SENT"],
        ],
        "details"   => ["total" => $total, "rush_fee" => $rushFee, "expires_at" => $expiresAt],
        "data"      => ["total" => $total, "expires_at" => $expiresAt],
    ];
}


/* STAFF/OWNER: tanggihan bago pa magbayad */

function figurify_wf_apply_decline_order(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 255);

    if ($reason === "") {
        figurify_wf_fail("Pick a reason first.");
    }

    figurify_wf_set_status($conn, $order, "cancelled", ["decline_reason = ?", "quote_expires_at = NULL"], "s", [$reason]);

    return [
        "to_status" => "cancelled",
        "steps"     => [[$ctx["state"], "CANCELLED"]],
        "reason"    => $reason,
    ];
}


/* CUSTOMER: tanggapin ang quotation */

function figurify_wf_apply_accept_quotation(mysqli $conn, array $order, array $p, array $ctx): array
{
    if (!empty($order["quote_expires_at"]) && strtotime($order["quote_expires_at"]) < time()) {
        figurify_wf_fail("This quotation has already expired.");
    }

    /* Slot hold: i-check kung bakante pa ang petsa bago pumunta sa bayad */
    $slotCheck = figurify_date_has_capacity($conn, (string) $order["order_type"], date("Y-m-d", strtotime((string) $order["ship_out_date"])));

    if (!$slotCheck["ok"]) {
        figurify_wf_fail("Your ship out date is no longer available. " . ($slotCheck["reason"] ?? "") . " Please choose a new date.", "date_full");
    }

    figurify_wf_set_status($conn, $order, "awaiting_payment", ["quote_expires_at = NULL"], "", []);

    return [
        "to_status" => "awaiting_payment",
        "steps"     => [["QUOTATION_SENT", "AWAITING_PAYMENT"]],
    ];
}


/* CUSTOMER: kanselahin bago magbayad */

function figurify_wf_apply_cancel_order(mysqli $conn, array $order, array $p, array $ctx): array
{
    /* bayad na (naibalik para i-correct) -> For Refund; owner ang magre-refund */
    if (!empty($order["payment_proof"])) {

        $paidReason = "Customer cancelled the order";

        figurify_wf_set_status($conn, $order, "for_refund", ["refund_reason = ?", "refund_requested_at = NOW()"], "s", [$paidReason]);

        return [
            "to_status" => "for_refund",
            "steps"     => [[$ctx["state"], "CANCELLED"], ["CANCELLED", "REFUND_REQUIRED"]],
            "reason"    => $paidReason,
        ];
    }

    // wala pang bayad sa mga state na ito
    figurify_wf_set_status($conn, $order, "cancelled", ["quote_expires_at = NULL"]);

    return [
        "to_status" => "cancelled",
        "steps"     => [
            [$ctx["state"], "CANCELLATION_REQUESTED"],
            ["CANCELLATION_REQUESTED", "CANCELLED"],
        ],
        "reason"    => "Cancelled by customer",
    ];
}


/* SYSTEM: lumang Dress Up order -> diretso sa bayad */

function figurify_wf_apply_dressup_to_payment(mysqli $conn, array $order, array $p, array $ctx): array
{
    if (($order["order_method"] ?? "") !== "create_style") {
        figurify_wf_fail("Only Dress Up orders go straight to payment.");
    }

    figurify_wf_set_status($conn, $order, "awaiting_payment", ["quote_expires_at = NULL"]);

    return [
        "to_status" => "awaiting_payment",
        "steps"     => [["PENDING_STAFF_VERIFICATION", "AWAITING_PAYMENT"]],
        "reason"    => "Dress Up orders have a fixed price, so no quotation is needed",
    ];
}


/* SYSTEM: na-expire ang quotation */

function figurify_wf_apply_expire_quote(mysqli $conn, array $order, array $p, array $ctx): array
{
    if (empty($order["quote_expires_at"]) || strtotime($order["quote_expires_at"]) >= time()) {
        figurify_wf_fail("This quotation has not expired yet.");
    }

    figurify_wf_set_status($conn, $order, "cancelled", ["quote_expires_at = NULL"]);

    return [
        "to_status" => "cancelled",
        "steps"     => [["QUOTATION_SENT", "CANCELLED"]],
        "reason"    => "Quotation was not confirmed within 2 days",
    ];
}


/* CUSTOMER: palitan ang ship out date (same order, walang bagong bayad). Dumadaan sa
   parehong rules ng bagong order (lead time, last day para sa non-rush, at slot cap). */

function figurify_wf_apply_change_date(mysqli $conn, array $order, array $p, array $ctx): array
{
    $newDate = trim((string) ($p["ship_out_date"] ?? ""));
    $parsed  = DateTime::createFromFormat("Y-m-d", $newDate);

    if (!$parsed || $parsed->format("Y-m-d") !== $newDate) {
        figurify_wf_fail("Please pick a valid ship out date.");
    }

    $oldDate = !empty($order["ship_out_date"]) ? date("Y-m-d", strtotime((string) $order["ship_out_date"])) : "";

    if ($newDate === $oldDate) {
        figurify_wf_fail("That is already your ship out date. Please pick a different one.");
    }

    $check = figurify_check_booking_date($conn, (string) $order["order_type"], $newDate);

    if (!$check["ok"]) {
        figurify_wf_fail($check["reason"] ?? "That ship out date is not available.", "date_full");
    }

    figurify_wf_exec($conn, "UPDATE orders SET ship_out_date = ? WHERE order_id = ?", "si", [$newDate, (int) $order["order_id"]]);

    return [
        "to_status" => $order["status"],
        "steps"     => [[$ctx["state"], $ctx["state"]]],
        "details"   => ["ship_out_date" => ["from" => $oldDate, "to" => $newDate]],
    ];
}


/* CUSTOMER: nabayaran na pero napuno ang petsa at hindi lilipat -> For Refund (owner ang magre-refund o magde-decide) */

function figurify_wf_apply_cancel_full_date(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = "Customer cancelled: the ship out date became full";

    figurify_wf_set_status($conn, $order, "for_refund", ["refund_reason = ?", "refund_requested_at = NOW()"], "s", [$reason]);

    return [
        "to_status" => "for_refund",
        "steps"     => [[$ctx["state"], "CANCELLED"], ["CANCELLED", "REFUND_REQUIRED"]],
        "reason"    => $reason,
    ];
}


/* SYSTEM: napuno ang petsa -> cancel ang hindi pa nakabayad */

function figurify_wf_apply_release_full_date(mysqli $conn, array $order, array $p, array $ctx): array
{
    figurify_wf_set_status($conn, $order, "cancelled", ["quote_expires_at = NULL"]);

    return [
        "to_status" => "cancelled",
        "steps"     => [[$ctx["state"], "CANCELLED"]],
        "reason"    => "Ship out date became full before payment was made",
    ];
}


/* CUSTOMER: magsumite ng downpayment */

function figurify_wf_apply_submit_payment(mysqli $conn, array $order, array $p, array $ctx): array
{
    $required = ["shipping_name", "shipping_address", "shipping_contact", "courier",
                 "payment_reference", "payment_proof", "refund_account_name",
                 "refund_account_number", "refund_method"];

    foreach ($required as $field) {
        if (!isset($p[$field]) || trim((string) $p[$field]) === "") {
            figurify_wf_fail("Please complete all required payment and shipping details.");
        }
    }

    if (!figurify_wf_is_number_text(trim((string) $p["payment_reference"]))) {
        figurify_wf_fail("Reference / Transaction Number must be numbers only.");
    }

    $pin = (isset($p["pin_address"]) && trim((string) $p["pin_address"]) !== "") ? (string) $p["pin_address"] : null;

    $paymentOption = (($p["payment_option"] ?? "half") === "full") ? "full" : "half";

    figurify_wf_set_status(
        $conn,
        $order,
        "to_verify",
        [
            "shipping_name = ?", "shipping_address = ?", "pin_address = ?", "shipping_contact = ?",
            "courier = ?", "payment_reference = ?", "payment_proof = ?",
            "refund_account_name = ?", "refund_account_number = ?", "refund_method = ?",
            "payment_option = ?", "paid_at = NOW()",
        ],
        "sssssssssss",
        [
            $p["shipping_name"], $p["shipping_address"], $pin, $p["shipping_contact"],
            $p["courier"], $p["payment_reference"], $p["payment_proof"],
            $p["refund_account_name"], $p["refund_account_number"], $p["refund_method"],
            $paymentOption,
        ]
    );

    return [
        "to_status" => "to_verify",
        "steps"     => [["AWAITING_PAYMENT", "PAYMENT_SUBMITTED"]],
        "details"   => ["payment_reference" => $p["payment_reference"], "payment_proof" => $p["payment_proof"], "payment_option" => $paymentOption],
    ];
}


/* STAFF/OWNER: tama ang details -> ipasa sa owner */

function figurify_wf_apply_verify_payment_details(mysqli $conn, array $order, array $p, array $ctx): array
{
    if (empty($order["payment_proof"]) || empty($order["payment_reference"])) {
        figurify_wf_fail("This order has no payment details to verify yet.");
    }

    // "for approval" (may space) - ito ang value na ginagamit ng For Approval page at dashboards
    figurify_wf_set_status($conn, $order, "for approval");

    return [
        "to_status" => "for approval",
        "steps"     => [["PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"]],
        "details"   => ["payment_reference" => $order["payment_reference"]],
    ];
}


/* STAFF/OWNER: mali ang details -> ibalik sa customer na may dahilan */

function figurify_wf_apply_decline_payment_details(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 255);

    if ($reason === "") {
        figurify_wf_fail("Pick a reason first.");
    }

    figurify_wf_set_status($conn, $order, "awaiting_payment", ["quote_expires_at = NULL"]);

    return [
        "to_status" => "awaiting_payment",
        "steps"     => [["PAYMENT_SUBMITTED", "AWAITING_PAYMENT"]],
        "reason"    => $reason,
        // naiiwan ang lumang proof sa history kahit mapalitan ng bagong upload
        "details"   => ["payment_reference" => $order["payment_reference"], "payment_proof" => $order["payment_proof"]],
    ];
}


/* OWNER: aprubahan ang downpayment */

function figurify_wf_apply_approve_payment(mysqli $conn, array $order, array $p, array $ctx): array
{
    if (empty($order["payment_proof"]) || empty($order["payment_reference"])) {
        figurify_wf_fail("There is no payment proof to approve.");
    }

    /* Dito pa lang papasok sa slot ang order (pag-approve ng owner). I-check kung
       may natitira pang slot sa petsa para hindi ma-overbook. */
    if (!empty($order["ship_out_date"])) {

        $capacity = figurify_date_has_capacity(
            $conn,
            (string) $order["order_type"],
            date("Y-m-d", strtotime((string) $order["ship_out_date"]))
        );

        if (!$capacity["ok"]) {
            figurify_wf_fail("Cannot approve. The ship out date is already full. " . ($capacity["reason"] ?? ""), "date_full");
        }
    }

    figurify_wf_set_status($conn, $order, "processing", ["decline_reason = NULL"]);

    return [
        "to_status" => "processing",
        "steps"     => [
            ["PAYMENT_DETAILS_VERIFIED", "PAYMENT_APPROVED"],
            ["PAYMENT_APPROVED", "PROCESSING"],
        ],
        "details"   => [
            "payment_reference" => $order["payment_reference"],
            "amount"            => figurify_wf_downpayment_amount($order),
        ],
    ];
}


/* OWNER: i-reject ang downpayment (makakapag-upload ulit) */

function figurify_wf_apply_reject_payment(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 255);

    if ($reason === "") {
        figurify_wf_fail("Pick a reason first.");
    }

    figurify_wf_set_status($conn, $order, "awaiting_payment", ["quote_expires_at = NULL"]);

    return [
        "to_status" => "awaiting_payment",
        "steps"     => [
            [$ctx["state"], "PAYMENT_REJECTED"],
            ["PAYMENT_REJECTED", "AWAITING_PAYMENT"],
        ],
        "reason"    => $reason,
        // naiiwan ang lumang proof sa history kahit mapalitan ng bagong upload
        "details"   => ["payment_reference" => $order["payment_reference"], "payment_proof" => $order["payment_proof"]],
    ];
}


/* OWNER: kanselahin ang order na nagbayad na -> refund */

function figurify_wf_apply_cancel_paid_order(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 255);

    if ($reason === "") {
        figurify_wf_fail("Pick a reason first.");
    }

    figurify_wf_set_status(
        $conn,
        $order,
        "for_refund",
        ["decline_reason = ?", "refund_reason = ?", "refund_requested_at = NOW()"],
        "ss",
        [$reason, $reason]
    );

    return [
        "to_status" => "for_refund",
        "steps"     => [
            [$ctx["state"], "CANCELLED"],
            ["CANCELLED", "REFUND_REQUIRED"],
        ],
        "reason"    => $reason,
    ];
}


/* STAFF/OWNER: mag-upload ng progress */

function figurify_wf_apply_submit_progress(mysqli $conn, array $order, array $p, array $ctx): array
{
    $imagePath = (string) ($p["image_path"] ?? "");
    $message   = mb_substr(trim((string) ($p["message"] ?? "")), 0, 500);
    $sentBy    = ($ctx["actor"]["role"] === FIGURIFY_WF_OWNER) ? "admin" : "staff";
    $userId    = $ctx["actor"]["id"];
    $orderId   = (int) $order["order_id"];

    if ($imagePath === "") {
        figurify_wf_fail("Please choose a photo to send.");
    }

    /* revised photo (pagkatapos ng revision): tapos na ang revision, kaya hindi na kailangan ang
       "It's Good" ng customer - approved agad at diretso na sa balance (o Ready to Ship kung bayad na) */
    $isRevision = ($ctx["state"] === "REVISION_IN_PROGRESS");

    figurify_wf_exec(
        $conn,
        "INSERT INTO order_progress_updates
            (order_id, sent_by_user_id, sent_by_role, image_path, staff_message, customer_response, is_final, responded_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, " . ($isRevision ? "NOW()" : "NULL") . ")",
        "iissss",
        [$orderId, $userId, $sentBy, $imagePath, $message, $isRevision ? "approved" : "pending"]
    );

    $updateId = (int) $conn->insert_id;

    if ($isRevision) {
        $result = figurify_wf_finalize_into($conn, $order, "CUSTOMER_APPROVED", [
            [$ctx["state"], "PROGRESS_SUBMITTED"],
            ["PROGRESS_SUBMITTED", "CUSTOMER_APPROVED"],
        ]);

        $result["details"] += ["update_id" => $updateId, "image" => $imagePath, "is_revision" => true];
        $result["data"]    += ["update_id" => $updateId, "is_revision" => true];

        return $result;
    }

    return [
        "to_status" => $order["status"], // nananatiling "processing"
        "steps"     => [[$ctx["state"], "PROGRESS_SUBMITTED"]],
        "details"   => ["update_id" => $updateId, "image" => $imagePath, "is_revision" => ($ctx["state"] === "REVISION_IN_PROGRESS")],
        "data"      => ["update_id" => $updateId, "is_revision" => ($ctx["state"] === "REVISION_IN_PROGRESS")],
    ];
}


/* FINALIZE (system step): kinukwenta ang balance mula sa totoong quotation */

function figurify_wf_finalize_into(mysqli $conn, array $order, string $fromState, array $leadingSteps): array
{
    $balance = figurify_wf_balance_amount($order);

    $steps   = $leadingSteps;
    $steps[] = [$fromState, "FINALIZED"];

    if ($balance > 0) {

        figurify_wf_set_status($conn, $order, "awaiting_balance", ["balance_to_pay = ?"], "d", [$balance]);

        $steps[] = ["FINALIZED", "AWAITING_BALANCE"];
        $to      = "awaiting_balance";

    } else {

        figurify_wf_set_status($conn, $order, "to_ship", ["balance_to_pay = 0"]);

        $steps[] = ["FINALIZED", "READY_TO_SHIP"];
        $to      = "to_ship";
    }

    return [
        "to_status" => $to,
        "steps"     => $steps,
        "details"   => ["balance_due" => $balance, "total" => (float) $order["total_amount"]],
        "data"      => ["balance_due" => $balance],
    ];
}


/* CUSTOMER / SYSTEM: aprubahan ang progress */

function figurify_wf_apply_approve_progress(mysqli $conn, array $order, array $p, array $ctx): array
{
    return figurify_wf_approve_progress_common($conn, $order, $p, $ctx, false);
}

/* ACTION: auto_approve_progress (SYSTEM) - kusang approve kapag lampas na ang takdang oras at walang sagot ang customer. */
function figurify_wf_apply_auto_approve_progress(mysqli $conn, array $order, array $p, array $ctx): array
{
    $latest = $ctx["latest"];

    if (strtotime((string) $latest["created_at"]) > strtotime("-" . FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS . " hours")) {
        figurify_wf_fail("This progress update is not yet due for auto-approval.");
    }

    return figurify_wf_approve_progress_common($conn, $order, $p, $ctx, true);
}

/* ANO ITO: iisang logic ng approve ng progress. $auto = true kapag system ang nag-approve, false kapag customer. */
function figurify_wf_approve_progress_common(mysqli $conn, array $order, array $p, array $ctx, bool $auto): array
{
    $latest   = $ctx["latest"];
    $updateId = (int) ($p["update_id"] ?? $latest["update_id"]);

    if ($updateId !== (int) $latest["update_id"]) {
        figurify_wf_fail("This isn't the latest progress update anymore. Please refresh.", "conflict");
    }

    $affected = figurify_wf_exec(
        $conn,
        "UPDATE order_progress_updates
         SET customer_response = 'approved', revision_note = NULL, responded_at = NOW()"
            . ($auto ? ", auto_approved = 1" : "") . "
         WHERE update_id = ? AND order_id = ? AND customer_response = 'pending'",
        "ii",
        [$updateId, (int) $order["order_id"]]
    );

    if ($affected !== 1) {
        figurify_wf_fail("This progress update was already answered.", "conflict");
    }

    $result = figurify_wf_finalize_into($conn, $order, "CUSTOMER_APPROVED", [["PROGRESS_SUBMITTED", "CUSTOMER_APPROVED"]]);

    $result["details"]["update_id"] = $updateId;

    if ($auto) {
        $result["reason"] = "No customer response within " . (FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS / 24) . " days";
    }

    return $result;
}


/* CUSTOMER: ISANG revision lang */

function figurify_wf_apply_request_revision(mysqli $conn, array $order, array $p, array $ctx): array
{
    $latest   = $ctx["latest"];
    $updateId = (int) ($p["update_id"] ?? 0);
    $note     = figurify_wf_clean_reason($p["note"] ?? "", 1000);
    $orderId  = (int) $order["order_id"];

    if ($note === "") {
        figurify_wf_fail("Please tell us what you'd like changed.");
    }

    if ($updateId !== (int) $latest["update_id"]) {
        figurify_wf_fail("This isn't the latest progress update anymore. Please refresh.", "conflict");
    }

    if ((int) $order["revision_count"] >= FIGURIFY_WF_MAX_REVISIONS) {
        figurify_wf_fail("You've already used your 1 revision.", "revision_limit");
    }

    /* atomic consume: kahit sabay ang dalawang request, isa lang ang
       makakapag-increment mula 0 -> 1 */
    $consumed = figurify_wf_exec(
        $conn,
        "UPDATE orders
         SET revision_count = revision_count + 1
         WHERE order_id = ? AND status = ? AND revision_count < ?",
        "isi",
        [$orderId, (string) $order["status"], FIGURIFY_WF_MAX_REVISIONS]
    );

    if ($consumed !== 1) {
        figurify_wf_fail("You've already used your 1 revision.", "revision_limit");
    }

    $affected = figurify_wf_exec(
        $conn,
        "UPDATE order_progress_updates
         SET customer_response = 'revision', revision_note = ?, responded_at = NOW()
         WHERE update_id = ? AND order_id = ? AND customer_response = 'pending'",
        "sii",
        [$note, $updateId, $orderId]
    );

    if ($affected !== 1) {
        figurify_wf_fail("This progress update was already answered.", "conflict");
    }

    return [
        "to_status" => $order["status"],
        "steps"     => [["PROGRESS_SUBMITTED", "REVISION_REQUESTED"]],
        "reason"    => $note,
        "details"   => ["update_id" => $updateId, "revision_used" => (int) $order["revision_count"] + 1, "revision_limit" => FIGURIFY_WF_MAX_REVISIONS],
    ];
}


/* STAFF/OWNER: tanggapin / tanggihan ang revision */

function figurify_wf_apply_accept_revision(mysqli $conn, array $order, array $p, array $ctx): array
{
    $updateId = (int) ($p["update_id"] ?? 0);

    $affected = figurify_wf_exec(
        $conn,
        "UPDATE order_progress_updates
         SET customer_response = 'in_progress', responded_at = NOW()
         WHERE update_id = ? AND order_id = ? AND customer_response = 'revision'",
        "ii",
        [$updateId, (int) $order["order_id"]]
    );

    if ($affected !== 1 || $updateId !== (int) $ctx["latest"]["update_id"]) {
        figurify_wf_fail("This revision was already answered.", "conflict");
    }

    /* nananatili sa production: kailangang ipadala muna ng staff/owner ang revised photo
       (submit_progress). Kapag "It's Good" na ang customer (o auto-approve), saka pa lang
       finalized ang order -> balance / Ready to Ship. */
    return [
        "to_status" => $order["status"], // "processing" pa rin
        "steps"     => [["REVISION_REQUESTED", "REVISION_IN_PROGRESS"]],
        "details"   => ["update_id" => $updateId],
    ];
}

/* ACTION: decline_revision - tinanggihan ng staff/owner ang revision request. Babalik sa production ang order. */
function figurify_wf_apply_decline_revision(mysqli $conn, array $order, array $p, array $ctx): array
{
    $updateId = (int) ($p["update_id"] ?? 0);
    $reason   = figurify_wf_clean_reason($p["reason"] ?? "", 500);

    if ($reason === "") {
        figurify_wf_fail("Pick a reason first.");
    }

    $affected = figurify_wf_exec(
        $conn,
        "UPDATE order_progress_updates
         SET customer_response = 'declined', staff_reply = ?, responded_at = NOW()
         WHERE update_id = ? AND order_id = ? AND customer_response = 'revision'",
        "sii",
        [$reason, $updateId, (int) $order["order_id"]]
    );

    if ($affected !== 1 || $updateId !== (int) $ctx["latest"]["update_id"]) {
        figurify_wf_fail("This revision was already answered.", "conflict");
    }

    // ubos pa rin ang revision (revision_count = 1) - babalik sa production
    return [
        "to_status" => $order["status"],
        "steps"     => [["REVISION_REQUESTED", "PROCESSING"]],
        "reason"    => $reason,
        "details"   => ["update_id" => $updateId],
    ];
}


/* STAFF/OWNER: finalize (lumang naka-approve na order, o
   lumang to_ship na hindi pa nababayaran ang balance) */

function figurify_wf_apply_finalize_order(mysqli $conn, array $order, array $p, array $ctx): array
{
    if ($ctx["state"] === "READY_TO_SHIP") {

        $balance = figurify_wf_balance_amount($order);

        if ($balance <= 0 || !empty($order["balance_paid_at"])) {
            figurify_wf_fail("This order has no unpaid balance, so it can be shipped.");
        }

        figurify_wf_set_status($conn, $order, "awaiting_balance", ["balance_to_pay = ?"], "d", [$balance]);

        return [
            "to_status" => "awaiting_balance",
            "steps"     => [["READY_TO_SHIP", "AWAITING_BALANCE"]],
            "reason"    => "Balance was not yet collected for this order",
            "details"   => ["balance_due" => $balance],
            "data"      => ["balance_due" => $balance],
        ];
    }

    return figurify_wf_finalize_into($conn, $order, "CUSTOMER_APPROVED", []);
}


/* STAFF/OWNER: final photo (impormasyon lang, walang status change) */

function figurify_wf_apply_submit_final_photo(mysqli $conn, array $order, array $p, array $ctx): array
{
    $imagePath = (string) ($p["image_path"] ?? "");
    $orderId   = (int) $order["order_id"];

    if ($imagePath === "") {
        figurify_wf_fail("Please choose a photo to send.");
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM order_progress_updates WHERE order_id = ? AND is_final = 1");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $already = (int) $stmt->get_result()->fetch_assoc()["c"];
    $stmt->close();

    if ($already > 0) {
        figurify_wf_fail("The final photo was already sent for this order.");
    }

    $sentBy = ($ctx["actor"]["role"] === FIGURIFY_WF_OWNER) ? "admin" : "staff";
    $userId = $ctx["actor"]["id"];

    figurify_wf_exec(
        $conn,
        "INSERT INTO order_progress_updates
            (order_id, sent_by_user_id, sent_by_role, image_path, staff_message, customer_response, is_final)
         VALUES (?, ?, ?, ?, '', 'approved', 1)",
        "iiss",
        [$orderId, $userId, $sentBy, $imagePath]
    );

    return [
        "to_status" => $order["status"],
        "steps"     => [],
        "data"      => ["update_id" => (int) $conn->insert_id],
    ];
}


/* CUSTOMER: magsumite ng balance */

function figurify_wf_apply_submit_balance(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reference = trim((string) ($p["payment_reference"] ?? ""));
    $proof     = trim((string) ($p["payment_proof"] ?? ""));

    if ($reference === "" || $proof === "") {
        figurify_wf_fail("Please enter the reference number and upload the proof of payment.");
    }

    if (!figurify_wf_is_number_text($reference)) {
        figurify_wf_fail("Reference / Transaction Number must be numbers only.");
    }

    figurify_wf_set_status(
        $conn,
        $order,
        "for_balance_approval",
        ["balance_payment_reference = ?", "balance_proof = ?", "balance_paid_at = NOW()"],
        "ss",
        [$reference, $proof]
    );

    return [
        "to_status" => "for_balance_approval",
        "steps"     => [["AWAITING_BALANCE", "BALANCE_SUBMITTED"]],
        "details"   => ["payment_reference" => $reference, "payment_proof" => $proof, "amount" => (float) $order["balance_to_pay"]],
    ];
}


/* OWNER: aprubahan / i-reject ang balance */

function figurify_wf_apply_approve_balance(mysqli $conn, array $order, array $p, array $ctx): array
{
    if (empty($order["balance_proof"]) || empty($order["balance_payment_reference"])) {
        figurify_wf_fail("There is no balance payment proof to approve.");
    }

    figurify_wf_set_status($conn, $order, "to_ship");

    return [
        "to_status" => "to_ship",
        "steps"     => [
            ["BALANCE_SUBMITTED", "BALANCE_APPROVED"],
            ["BALANCE_APPROVED", "READY_TO_SHIP"],
        ],
        "details"   => ["payment_reference" => $order["balance_payment_reference"], "amount" => (float) $order["balance_to_pay"]],
    ];
}

/* ACTION: reject_balance - hindi tinanggap ang bayad sa balance. Ibabalik sa "awaiting_balance" para magbayad ulit ang customer. */
function figurify_wf_apply_reject_balance(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 255);

    if ($reason === "") {
        figurify_wf_fail("Pick a reason first.");
    }

    // balance_paid_at ibinabalik sa NULL - hindi pa ito bayad
    figurify_wf_set_status($conn, $order, "awaiting_balance", ["balance_paid_at = NULL"]);

    return [
        "to_status" => "awaiting_balance",
        "steps"     => [
            ["BALANCE_SUBMITTED", "BALANCE_REJECTED"],
            ["BALANCE_REJECTED", "AWAITING_BALANCE"],
        ],
        "reason"    => $reason,
        "details"   => ["payment_reference" => $order["balance_payment_reference"], "payment_proof" => $order["balance_proof"]],
    ];
}


/* STAFF/OWNER: ship out (#21 checklist) */

function figurify_wf_shipping_checklist(mysqli $conn, array $order, ?array $latest): array
{
    $balanceDue  = figurify_wf_balance_amount($order);
    $balancePaid = ($balanceDue <= 0) || !empty($order["balance_paid_at"]);

    $latestResponse = $latest["customer_response"] ?? null;

    return [
        "Required payment complete"      => !empty($order["paid_at"]) && $balancePaid,
        "Customer address available"     => trim((string) ($order["shipping_address"] ?? "")) !== "" && trim((string) ($order["shipping_name"] ?? "")) !== "",
        "Customer contact available"     => trim((string) ($order["shipping_contact"] ?? "")) !== "",
        "Courier selected"               => trim((string) ($order["courier"] ?? "")) !== "",
        /* "in_progress" = tinanggap na ang revision (lumang order na na-finalize na bago
           ipadala ang revised photo) kaya hindi na ito hadlang sa pag-ship */
        "No unresolved revision"         => !($latestResponse === "revision"
                                              || ($latestResponse === "pending" && empty($latest["is_final"]))),
    ];
}

/* ACTION: ship_order - kailangan ng tracking number at shipping fee. Ililipat ang order sa SHIPPED. */
function figurify_wf_apply_ship_order(mysqli $conn, array $order, array $p, array $ctx): array
{
    $tracking = trim((string) ($p["tracking_number"] ?? ""));
    $feeRaw   = trim((string) ($p["shipping_fee"] ?? ""));

    /* numbers lang ang dalawa (walang maikling limit); shipping fee puwedeng may decimal (hal. 150.50) */
    if (!figurify_wf_is_number_text($tracking) || !preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $feeRaw)) {
        figurify_wf_fail("Tracking Number and Shipping Fee must be numbers only.");
    }

    foreach (figurify_wf_shipping_checklist($conn, $order, $ctx["latest"]) as $label => $ok) {
        if (!$ok) {
            figurify_wf_fail("Cannot ship yet. Check failed: " . $label . ".");
        }
    }

    $fee = round((float) $feeRaw, 2);

    figurify_wf_set_status($conn, $order, "shipped", ["tracking_number = ?", "shipping_fee = ?", "shipped_at = NOW()"], "sd", [$tracking, $fee]);

    return [
        "to_status" => "shipped",
        "steps"     => [["READY_TO_SHIP", "SHIPPED"]],
        "details"   => ["tracking_number" => $tracking, "shipping_fee" => $fee, "courier" => $order["courier"]],
    ];
}

/* ACTION: return_shipping_details - hindi mahanap ng courier ang address / mali ang details.
   Babalik sa customer (ADDRESS_RETURNED) kasama ang dahilan na mababasa niya sa My Orders. */
function figurify_wf_apply_return_shipping_details(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 255);

    if ($reason === "") {
        figurify_wf_fail("Please choose a reason.");
    }

    figurify_wf_set_status($conn, $order, "address_returned");

    return [
        "to_status" => "address_returned",
        "steps"     => [["READY_TO_SHIP", "ADDRESS_RETURNED"]],
        "reason"    => $reason,
    ];
}

/* ACTION: update_shipping_details - inayos ng customer ang shipping details -> balik sa READY_TO_SHIP */
function figurify_wf_apply_update_shipping_details(mysqli $conn, array $order, array $p, array $ctx): array
{
    $name    = mb_substr(trim((string) ($p["shipping_name"] ?? "")), 0, 150);
    $address = mb_substr(trim((string) ($p["shipping_address"] ?? "")), 0, 255);
    $pin     = mb_substr(trim((string) ($p["pin_address"] ?? "")), 0, 255);
    $contact = trim((string) ($p["shipping_contact"] ?? ""));
    $courier = trim((string) ($p["courier"] ?? ""));

    if ($name === "" || $address === "") {
        figurify_wf_fail("Please enter the receiver's name and full address.");
    }
    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        figurify_wf_fail("Contact number must be 11 digits.");
    }
    if (!in_array($courier, ["lbc", "lalamove"], true)) {
        figurify_wf_fail("Please choose a courier.");
    }

    figurify_wf_set_status(
        $conn,
        $order,
        "to_ship",
        ["shipping_name = ?", "shipping_address = ?", "pin_address = ?", "shipping_contact = ?", "courier = ?"],
        "sssss",
        [$name, $address, ($pin === "" ? null : $pin), $contact, $courier]
    );

    return [
        "to_status" => "to_ship",
        "steps"     => [["ADDRESS_RETURNED", "READY_TO_SHIP"]],
        "details"   => ["shipping_address" => $address, "courier" => $courier],
    ];
}

/* ACTION: complete_order - ilalagay sa COMPLETED ang naipadala nang order. */
function figurify_wf_apply_complete_order(mysqli $conn, array $order, array $p, array $ctx): array
{
    if (empty($order["shipped_at"]) && empty($order["tracking_number"])) {
        figurify_wf_fail("This order has no shipment record yet.");
    }

    figurify_wf_set_status($conn, $order, "completed");

    return [
        "to_status" => "completed",
        "steps"     => [["SHIPPED", "COMPLETED"]],
    ];
}


/* OWNER: refund */

function figurify_wf_apply_start_refund(mysqli $conn, array $order, array $p, array $ctx): array
{
    $reason = figurify_wf_clean_reason($p["reason"] ?? "", 255);

    if ($reason === "") {
        figurify_wf_fail("Please select a reason for the refund.");
    }

    if (figurify_wf_amount_paid($order) <= 0) {
        figurify_wf_fail("This order cannot be refunded (no payment recorded).");
    }

    $steps = [[$ctx["state"], "CANCELLED"], ["CANCELLED", "REFUND_REQUIRED"]];

    figurify_wf_set_status($conn, $order, "for_refund", ["refund_reason = ?", "refund_requested_at = NOW()"], "s", [$reason]);

    return [
        "to_status" => "for_refund",
        "steps"     => $steps,
        "reason"    => $reason,
        "details"   => in_array($ctx["state"], ["SHIPPED", "COMPLETED"], true) ? ["exception_workflow" => "post-shipment refund (owner)"] : null,
    ];
}

/* ACTION: complete_refund - natapos na ang refund. Kailangan ng uri (full/partial), reference number at proof. */
function figurify_wf_apply_complete_refund(mysqli $conn, array $order, array $p, array $ctx): array
{
    $type      = (string) ($p["refund_type"] ?? "");
    $reference = trim((string) ($p["refund_reference"] ?? ""));
    $proof     = (string) ($p["refund_proof"] ?? "");
    $paid      = figurify_wf_amount_paid($order);

    if (!in_array($type, ["full", "partial"], true)) {
        figurify_wf_fail("Select whether this is a full or partial refund.");
    }

    $amount = ($type === "full") ? $paid : round((float) ($p["refund_amount"] ?? 0), 2);

    if ($amount <= 0) {
        figurify_wf_fail("Enter a valid refund amount.");
    }

    if ($paid > 0 && $amount > $paid) {
        figurify_wf_fail("The refund amount cannot be more than the amount paid (₱" . number_format($paid, 2) . ").");
    }

    if ($reference === "" || $proof === "") {
        figurify_wf_fail("Enter the refund reference number and upload the proof of refund.");
    }

    if (!figurify_wf_is_number_text($reference)) {
        figurify_wf_fail("Refund Reference No. must be numbers only.");
    }

    figurify_wf_set_status(
        $conn,
        $order,
        "refunded",
        ["refund_type = ?", "refund_amount = ?", "refund_reference = ?", "refund_proof = ?", "refunded_at = NOW()"],
        "sdss",
        [$type, $amount, $reference, $proof]
    );

    return [
        "to_status" => "refunded",
        "steps"     => [["REFUND_REQUIRED", "REFUNDED"]],
        "details"   => ["refund_type" => $type, "amount" => $amount, "reference" => $reference],
        "data"      => ["amount" => $amount],
    ];
}

/* ACTION: refund_no_payment - cancelled na walang refund dahil wala namang nabayaran. */
function figurify_wf_apply_refund_no_payment(mysqli $conn, array $order, array $p, array $ctx): array
{
    figurify_wf_set_status($conn, $order, "cancelled", ["refund_amount = 0"]);

    return [
        "to_status" => "cancelled",
        "steps"     => [["REFUND_REQUIRED", "CANCELLED"]],
        "reason"    => "No payment was received, so there is nothing to refund",
    ];
}


/* =====================================================================
   SECTION 3 - REVIEW
   (galing sa: review_helper.php)
   Customer review (rating + text + images) ng completed order.
   Ginagamit ng My Orders (naka-login) at ng completion email
   (review_token, walang login) - pareho papunta sa submit_review.php.

   Takbo: figurify_ensure_review_token() gumagawa/kumukuha ng sikretong
   token ng order -> figurify_review_url() ginagawang buong link
   (order_id + token = "susi", kaya gumagana kahit hindi naka-login)
   -> figurify_get_review() kinukuha ang review kasama ang mga larawan.
   ===================================================================== */

if (!function_exists("figurify_ensure_review_token")) {

    // gets the order's review_token, generating and saving one if it's empty
    function figurify_ensure_review_token(mysqli $conn, int $orderId): string
    {
        $stmt = $conn->prepare(
            "SELECT review_token FROM orders WHERE order_id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row["review_token"])) {
            return $row["review_token"];
        }

        $token = bin2hex(random_bytes(20));

        $saveStmt = $conn->prepare(
            "UPDATE orders SET review_token = ? WHERE order_id = ?"
        );
        $saveStmt->bind_param("si", $token, $orderId);
        $saveStmt->execute();
        $saveStmt->close();

        return $token;
    }
}


if (!function_exists("figurify_site_page_url")) {

    // full link to any page of this project (para sa mga link sa email) - gumagana mula
    // staff/, owner/, my-order/ o scheduled task. $query = ["order_id" => 5, "token" => "..."]
    function figurify_site_page_url(string $path, array $query): string
    {
        $queryString = http_build_query($query);

        /* Scheduled task (CLI) - walang HTTP_HOST, kaya default na project URL */
        if (php_sapi_name() === "cli" || empty($_SERVER["HTTP_HOST"])) {
            $base = rtrim((string) (getenv("FIGURIFY_BASE_URL") ?: FIGURIFY_SITE_URL), "/");

            return $base . "/" . $path . "?" . $queryString;
        }

        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $host   = $_SERVER["HTTP_HOST"] ?? "localhost";

        // Kunin ang project root (hal. "/clayandstuff") galing sa
        // kasalukuyang script - gumagana ito mula staff/, owner/,
        // o my-order/, dahil magkakapatid silang lahat sa ilalim
        // ng isang parehong project folder.
        $scriptDir   = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
        $projectRoot = rtrim(dirname($scriptDir), "/");

        return $scheme . "://" . $host . $projectRoot . "/" . $path . "?" . $queryString;
    }
}

if (!function_exists("figurify_review_url")) {

    // full link to the review page - works without login since
    // order_id + token is the "key". $rating (1-5) = naka-pili na ang bituin pagbukas (galing sa email)
    function figurify_review_url(int $orderId, string $token, int $rating = 0): string
    {
        $query = ["order_id" => $orderId, "token" => $token];

        if ($rating >= 1 && $rating <= 5) {
            $query["rating"] = $rating;
        }

        return figurify_site_page_url("my-order/submit_review.php", $query);
    }
}


if (!function_exists("figurify_get_review")) {

    // gets the submitted review + images for an order, null if none yet
    function figurify_get_review(mysqli $conn, int $orderId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT review_id, order_id, user_id, rating, review_text,
                    submitted_via, created_at
             FROM reviews
             WHERE order_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $review = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$review) {
            return null;
        }

        $imgStmt = $conn->prepare(
            "SELECT image_path FROM review_images WHERE review_id = ? ORDER BY review_image_id ASC"
        );
        $imgStmt->bind_param("i", $review["review_id"]);
        $imgStmt->execute();

        $images = [];
        $imgResult = $imgStmt->get_result();

        while ($imgRow = $imgResult->fetch_assoc()) {
            /* larawan na wala na sa server = hindi isasama (walang sirang larawan) */
            if (is_file(__DIR__ . "/../" . $imgRow["image_path"])) {
                $images[] = $imgRow["image_path"];
            }
        }

        $imgStmt->close();

        $review["images"] = $images;

        return $review;
    }
}


/* =====================================================================
   SECTION 4 - COMPLETE
   (galing sa: order_complete_helper.php)
   Order Received / Complete (SHIPPED -> COMPLETED):
     1. customer - pinindot ang "Order Received" sa My Orders
     2. staff/owner - "Complete Order" sa Active Booking o Shipments
     3. system - kusa pagkalipas ng 7 araw mula ma-ship
   Pagka-complete, lalabas ang "Leave a Review" at may email na may
   review link.

   Takbo: ang status ay pinapalitan ng figurify_wf_perform("complete_order")
   (SECTION 2), tapos tinatawag ang figurify_order_completed_notice() para
   sa bell + email (may review link galing sa SECTION 3).
   ===================================================================== */

if (!defined("FIGURIFY_AUTO_RECEIVE_DAYS")) {
    define("FIGURIFY_AUTO_RECEIVE_DAYS", 7);
}

if (!function_exists("figurify_order_completed_notice")) {

    /* $via: "customer" | "staff" | "auto" */
    function figurify_order_completed_notice(mysqli $conn, int $orderId, int $customerId, string $via, ?int $staffActorId = null): void
    {
        // Gmail + bell helper (SECTION: helpers/messaging_helper.php). Dito lang kailangan,
        // kaya sa oras ng tawag nilo-load para gumaan ang mga page na badge lang ang kailangan.
        require_once __DIR__ . "/messaging_helper.php";

        $reviewToken = figurify_ensure_review_token($conn, $orderId);
        $reviewUrl   = figurify_review_url($orderId, $reviewToken);

        // 5 bituin na link - bawat isa ay bubukas ang review form na naka-pili na ang rating
        $starLinks = '<span class="cs-mail-stars">';

        for ($star = 1; $star <= 5; $star++) {
            $starLinks .= '<a href="' . figurify_email_e(figurify_review_url($orderId, $reviewToken, $star)) . '" title="' . $star . ' star' . ($star > 1 ? "s" : "") . '">★</a>';
        }

        $starLinks .= '</span>';

        if ($via === "customer") {
            $systemMsg = "Thanks for confirming! Order #" . $orderId . " is complete. You can now leave a review.";
            $opening   = "Thanks for confirming! <strong>Order #" . $orderId . "</strong> is complete.";
        } elseif ($via === "auto") {
            $systemMsg = "Order #" . $orderId . " was marked as received and is now complete. You can now leave a review.";
            $opening   = "<strong>Order #" . $orderId . "</strong> is complete (" . FIGURIFY_AUTO_RECEIVE_DAYS . " days after shipping).";
        } else {
            $systemMsg = "Order #" . $orderId . " is complete! You can now leave a review.";
            $opening   = "<strong>Order #" . $orderId . "</strong> is complete.";
        }

        figurify_customer_notice(
            $conn,
            $customerId,
            $orderId,
            $systemMsg,
            "Your Order #" . $orderId . " is complete",
            [
                $opening,
                "How was your figure? Tap a star:",
                $starLinks,
                '<a class="cs-mail-btn cs-mail-btn-good" href="' . figurify_email_e($reviewUrl) . '">Leave a Review</a>',
            ]
        );

        $staffMsg = [
            "customer" => "Customer confirmed they received order " . $orderId . ". Order completed.",
            "auto"     => "Order #" . $orderId . " was automatically completed (" . FIGURIFY_AUTO_RECEIVE_DAYS . " days after shipping).",
        ][$via] ?? ("Order #" . $orderId . " completed.");

        figurify_notify_staff($conn, $orderId, $staffMsg, $staffActorId);
    }


    /* awtomatikong "Order Received" pagkalipas ng 7 araw mula nang ma-ship */
    function figurify_autocomplete_shipped_orders(mysqli $conn): int
    {
        $done = 0;

        try {
            figurify_wf_ensure_schema($conn);

            /* may ni-report na delivery problem = hindi auto-complete; owner ang magpapasya */
            $stmt = $conn->prepare(
                "SELECT order_id, user_id FROM orders
                 WHERE status = 'shipped'
                   AND delivery_problem_at IS NULL
                   AND shipped_at IS NOT NULL
                   AND shipped_at <= (NOW() - INTERVAL " . (int) FIGURIFY_AUTO_RECEIVE_DAYS . " DAY)"
            );

            if (!$stmt) {
                return 0;
            }

            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($rows as $row) {
                $orderId = (int) $row["order_id"];
                $result  = figurify_wf_perform($conn, $orderId, "complete_order", [], figurify_wf_system_actor());

                if (!$result["ok"]) {
                    continue;
                }

                figurify_order_completed_notice($conn, $orderId, (int) $row["user_id"], "auto");
                $done++;
            }
        } catch (\Throwable $e) {
            error_log("[figurify auto-receive] " . $e->getMessage());
        }

        return $done;
    }


    /* petsa kung kailan awtomatikong mamarkahang received */
    function figurify_auto_receive_date(?string $shippedAt): string
    {
        if (empty($shippedAt)) {
            return "";
        }

        return date("F j, Y", strtotime($shippedAt) + FIGURIFY_AUTO_RECEIVE_DAYS * 86400);
    }
}


/* =====================================================================
   SECTION 5 - PROGRESS UPDATE RESPONSE (It's Good / Revision)
   Iisang logic para sa 3 daan ng customer:
     1. My Orders       (my-order/respond_progress.php, naka-login)
     2. Notifications   (Notification/notifications.php -> respond_progress.php)
     3. Gmail           (my-order/progress_response.php, token lang - walang login)
   Ang token ng Gmail link ay galing sa review_token ng order + update_id,
   kaya walang bagong column sa database.
   ===================================================================== */

if (!function_exists("figurify_progress_token")) {

    // secret token para sa isang progress update (para sa Gmail link)
    function figurify_progress_token(mysqli $conn, int $orderId, int $updateId): string
    {
        $secret = figurify_ensure_review_token($conn, $orderId);

        return substr(hash_hmac("sha256", "progress:" . $orderId . ":" . $updateId, $secret), 0, 40);
    }

    // link papunta sa my-order/progress_response.php ($do = "good" o "revision" = aling box ang bukas)
    function figurify_progress_response_url(mysqli $conn, int $orderId, int $updateId, string $do = ""): string
    {
        $query = [
            "order_id"  => $orderId,
            "update_id" => $updateId,
            "token"     => figurify_progress_token($conn, $orderId, $updateId),
        ];

        if ($do !== "") {
            $query["do"] = $do;
        }

        return figurify_site_page_url("my-order/progress_response.php", $query);
    }

    // kapareho ng pag-check ng token sa taas; hash_equals para ligtas
    function figurify_progress_token_ok(mysqli $conn, int $orderId, int $updateId, string $token): bool
    {
        return $token !== "" && hash_equals(figurify_progress_token($conn, $orderId, $updateId), $token);
    }

    /* Ang mismong pag-approve o pag-request ng revision.
       $response = "approved" | "revision". $actor = customer (galing session o galing sa token page).
       Ibinabalik: ["ok" => bool, "message" => string] */
    function figurify_progress_respond(mysqli $conn, int $orderId, int $updateId, string $response, string $note, array $actor): array
    {
        require_once __DIR__ . "/messaging_helper.php";

        $userId = (int) ($actor["id"] ?? 0);

        if ($response === "approved") {

            $result = figurify_wf_perform($conn, $orderId, "approve_progress", ["update_id" => $updateId], $actor);

            if (!$result["ok"]) {
                return ["ok" => false, "message" => $result["error"]];
            }

            if ($result["to_status"] === "awaiting_balance") {

                $balance = (float) $result["data"]["balance_due"];

                figurify_customer_notice(
                    $conn,
                    $userId,
                    $orderId,
                    "Order #" . $orderId . " is finalized. Please pay the remaining balance of ₱" . number_format($balance, 2) . " in My Orders.",
                    "Remaining balance for your Order #" . $orderId,
                    [
                        "Thanks for approving! <strong>Order #" . $orderId . "</strong> is finalized.",
                        "<strong>Remaining Balance:</strong> ₱" . number_format($balance, 2),
                        "Go to <strong>My Orders</strong>, click <strong>Pay Balance</strong>, and upload your proof.",
                        figurify_email_balance_note(),
                    ]
                );

                figurify_notify_staff($conn, $orderId, "Customer approved the progress for order " . $orderId . ". The order is finalized and waiting for the balance payment.");

                return ["ok" => true, "message" => "Order #" . $orderId . " finalized! Please pay the balance of ₱" . number_format($balance, 2) . "."];
            }

            figurify_notify_staff($conn, $orderId, "Customer approved the progress for order " . $orderId . ". It is ready to ship.");

            return ["ok" => true, "message" => "Order #" . $orderId . " finalized and ready to ship!"];
        }

        if ($response === "revision") {

            $result = figurify_wf_perform($conn, $orderId, "request_revision", ["update_id" => $updateId, "note" => $note], $actor);

            if (!$result["ok"]) {
                return ["ok" => false, "message" => $result["error"]];
            }

            figurify_notify_staff($conn, $orderId, "Customer requested their one (1) revision for order " . $orderId . ".");

            return ["ok" => true, "message" => "Revision request sent! We'll get back to you soon."];
        }

        return ["ok" => false, "message" => "Invalid request."];
    }
}


/* =====================================================================
   SECTION 6 - OWNER / STAFF PAGE DISPLAY (tabs, progress history, quotation tab, payment badge)
   Iisang kopya para sa owner at staff na bersyon ng Active Booking, Quotation at Payments.
   Dati ay nakakopya ito sa bawat page.
   ===================================================================== */

function figurify_render_active_booking_tabs($activeFilter, $processingCount, $revisionCount)
{
    $abFilterLabels = [
        "all"        => "All (" . (int) ($processingCount + $revisionCount) . ")",
        "processing" => "Processing (" . (int) $processingCount . ")",
        "revision"   => "Revision (" . (int) $revisionCount . ")",
    ];
    $abFilterCurrentLabel = $abFilterLabels[$activeFilter] ?? $abFilterLabels["all"];
?>
    <div class="qv-filter-dropdown" id="abFilterDropdown">

        <button
            type="button"
            class="qv-filter-trigger"
            onclick="figurifyToggleFilterDropdown('abFilterDropdown')"
        >
            <?php echo e($abFilterCurrentLabel); ?>
            <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
        </button>

        <div class="qv-filter-menu">

            <a
                href="active-booking.php?afilter=all"
                class="qv-filter-option<?php echo ($activeFilter === "all") ? " active" : ""; ?>"
            >
                All (<?php echo (int) ($processingCount + $revisionCount); ?>)
            </a>

            <a
                href="active-booking.php?afilter=processing"
                class="qv-filter-option<?php echo ($activeFilter === "processing") ? " active" : ""; ?>"
            >
                Processing (<?php echo (int) $processingCount; ?>)
            </a>

            <a
                href="active-booking.php?afilter=revision"
                class="qv-filter-option<?php echo ($activeFilter === "revision") ? " active" : ""; ?>"
            >
                Revision (<?php echo (int) $revisionCount; ?>)
            </a>

        </div>

    </div>
<?php
}

function figurify_render_progress_history_block($progressUpdates, $progressError)
{
?>
                    <div class="progress-history-block">


                        <?php
                        /* pop up na (figurify_toast_now - helpers/security_helper.php) sa halip na banner - isang beses lang ipapakita */
                        static $figurifyProgressErrorShown = false;
                        if ($progressError !== "" && !$figurifyProgressErrorShown) {
                            $figurifyProgressErrorShown = true;
                            echo figurify_toast_now($progressError, "error");
                        }
                        ?>

                        <?php if (empty($progressUpdates)): ?>

                            <?php /* walang ipapakitang text kapag wala pang progress photo */ ?>

                        <?php else: ?>

                            <div class="progress-history-list">

                                <?php foreach ($progressUpdates as $pu): ?>

                                    <div class="progress-history-item">

                                        <img
                                            src="../<?php echo htmlspecialchars($pu["image_path"]); ?>"
                                            alt="Progress photo"
                                            class="progress-history-thumb"
                                           
                                            onclick="openImageLightbox('../<?php echo htmlspecialchars($pu["image_path"], ENT_QUOTES); ?>', 'Progress update on <?php echo date("M j, Y", strtotime($pu["created_at"])); ?>')"
                                        >

                                        <div class="progress-history-body">

                                            <?php if (!empty($pu["staff_message"])): ?>
                                                <!-- mensahe na ipinadala: may container + label -->
                                                <p class="progress-history-caption">
                                                    <strong>Message sent to customer</strong>
                                                    <?php echo nl2br(htmlspecialchars($pu["staff_message"])); ?>
                                                </p>
                                            <?php endif; ?>

                                            <span class="progress-history-date">
                                                Sent <?php echo date("M j, Y, g:i A", strtotime($pu["created_at"])); ?>
                                            </span>

                                            <?php if ($pu["customer_response"] === "pending"): ?>

                                                <span class="progress-status-text is-waiting">Waiting for customer</span>

                                            <?php elseif ($pu["customer_response"] === "approved"): ?>

                                                <span class="progress-status-text is-approved"><?php echo !empty($pu["is_final"]) ? "Final photo, ready to ship" : "Customer approved this photo"; ?></span>

                                                <?php if (!empty($pu["auto_approved"])): ?>
                                                    <p class="progress-history-revision-note">
                                                        Auto-approved because the customer didn't respond within 2 days
                                                    </p>
                                                <?php endif; ?>

                                            <?php elseif ($pu["customer_response"] === "in_progress"): ?>

                                                <span class="progress-status-text is-progress">Revision accepted, working on it</span>

                                                <?php if (!empty($pu["revision_note"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Revision request</strong>
                                                        “<?php echo nl2br(htmlspecialchars($pu["revision_note"])); ?>”
                                                    </p>
                                                <?php endif; ?>

                                            <?php elseif ($pu["customer_response"] === "declined"): ?>

                                                <span class="progress-status-text is-declined">Revision declined</span>

                                                <?php if (!empty($pu["revision_note"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Revision request</strong>
                                                        “<?php echo nl2br(htmlspecialchars($pu["revision_note"])); ?>”
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (!empty($pu["staff_reply"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Our reply:</strong>
                                                        <?php echo nl2br(htmlspecialchars($pu["staff_reply"])); ?>
                                                    </p>
                                                <?php endif; ?>

                                            <?php else: ?>

                                                <?php if (!empty($pu["revision_note"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Revision request</strong>
                                                        “<?php echo nl2br(htmlspecialchars($pu["revision_note"])); ?>”
                                                    </p>
                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>
<?php
}

function figurifyQuoteTabFor(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === "quoted") {
        return "quoted";
    }
    if ($status === "needs_correction") {
        return "correction";
    }
    return "pending";
}

function figurifyBalanceState($statusKey)
{
    switch ($statusKey) {
        case "to_ship":
        case "address_returned":
        case "shipped":
        case "completed":
            return ["key" => "fully_paid", "label" => "Paid", "class" => "pay-badge-paid"];
        case "to_verify_balance":
        case "for_balance_approval":
            return ["key" => "balance_review", "label" => "For Approval", "class" => "pay-badge-review"];
        case "awaiting_balance":
            return ["key" => "balance_unpaid", "label" => "Unpaid", "class" => "pay-badge-unpaid"];
        default:
            // processing: ginagawa pa ang figure, hindi pa sinisingil ang balance
            return ["key" => "balance_not_due", "label" => "Not Yet Due", "class" => "pay-badge-pending"];
    }
}

function figurifyPayBadge($label, $class)
{
    return '<span class="pay-badge ' . e($class) . '">' . e($label) . '</span>';
}
/* ====================== END SECTION 6 ====================== */
