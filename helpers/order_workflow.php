<?php

/* ORDER WORKFLOW
   Lahat ng pagbabago ng status ay dito dumadaan:
     $result = figurify_wf_perform($conn, $orderId, "approve_payment", [...]);

   Hindi tumatanggap ng status galing sa browser, action lang (approve_payment, ship_order, ...).
   Sinusuri muna ang kasalukuyang status, role ng user, kung kanya ang order at iba pang kondisyon.
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
     SHIPPED / COMPLETED        = shipped / completed
     CANCELLED                  = cancelled
     REFUND_REQUIRED / REFUNDED = for_refund / refunded
   Ang mga dinadaanang hakbang ay naka-log sa order_status_history. */

require_once __DIR__ . "/order_status_helper.php";
require_once __DIR__ . "/activity_log_helper.php";
require_once __DIR__ . "/booking_helper.php";
require_once __DIR__ . "/csrf_helper.php";


/* ROLES */

const FIGURIFY_WF_CUSTOMER = "customer";
const FIGURIFY_WF_STAFF    = "staff";
const FIGURIFY_WF_OWNER    = "owner";
const FIGURIFY_WF_SYSTEM   = "system";

const FIGURIFY_WF_MAX_REVISIONS = 1;

/* Ilang oras bago kusang ma-approve ang progress photo. May paalala muna bago ito. */
const FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS = 72;
const FIGURIFY_PROGRESS_REMINDER_HOURS     = 48;


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

    $synonyms = [
        "forapproval" => "for_approval",
        "for_payment" => "awaiting_payment",
        "forpayment"  => "awaiting_payment",
        "verified"    => "to_verify",
        "paid"        => "to_verify",
        "on_process"  => "processing",
        "active"      => "processing",
        "confirmed"   => "processing",
        "approved"    => "processing",
        "canceled"    => "cancelled",
        "declined"    => "cancelled",
        "forrefund"   => "for_refund",
        "refund"      => "for_refund",
    ];

    if (isset($synonyms[$status])) {
        return $synonyms[$status];
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
    "PROGRESS_SUBMITTED"         => "For Progress Approval",
    "REVISION_REQUESTED"         => "For Revision",
    "REVISION_IN_PROGRESS"       => "Revising",
    "CUSTOMER_APPROVED"          => "Progress Approved",
    "FINALIZED"                  => "Finalized",
    "AWAITING_BALANCE"           => "Awaiting Balance",
    "BALANCE_SUBMITTED"          => "Balance Submitted",
    "BALANCE_APPROVED"           => "Balance Approved",
    "BALANCE_REJECTED"           => "Balance Rejected",
    "READY_TO_SHIP"              => "Ready to Ship",
    "SHIPPED"                    => "Shipped",
    "COMPLETED"                  => "Completed",
    "CANCELLATION_REQUESTED"     => "For Cancellation",
    "CANCELLED"                  => "Cancelled",
    "REFUND_REQUIRED"            => "For Refund",
    "REFUNDED"                   => "Refunded",
    "UNKNOWN"                    => "Unknown",
];

function figurify_wf_state_label(string $canonicalState): string
{
    return FIGURIFY_WF_CUSTOMER_LABELS[$canonicalState] ?? ucwords(strtolower(str_replace("_", " ", $canonicalState)));
}



/* SCHEMA (self-migrating, gaya ng activity_log)
   - orders.revision_count: 0 o 1 (one-time revision)
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

    $ensured = true;
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

function figurify_wf_fail(string $message, string $code = "condition"): void
{
    throw new FigurifyWorkflowException($message, $code);
}

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

/* 50% downpayment (kapareho ng payment-shipping.php at process_refund.php) */

function figurify_wf_downpayment_amount(array $order): float
{
    return round(((float) ($order["total_amount"] ?? 0)) / 2, 2);
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
        /* CUSTOMER: palitan ang booking date ng parehong order (hal. napuno na ang napili niya) -
           hanggang hindi pa naaaprubahan ng owner ang down payment. Hindi nagbabago ang status. */
        "change_date"           => ["roles" => [FIGURIFY_WF_CUSTOMER],     "from" => ["PENDING_STAFF_VERIFICATION", "STAFF_CORRECTION_REQUIRED", "QUOTATION_SENT", "AWAITING_PAYMENT", "PAYMENT_SUBMITTED", "PAYMENT_DETAILS_VERIFIED"]],
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
        /* customer = "Order Received"; system = auto pagkalipas ng 7 araw */
        "complete_order"        => ["roles" => array_merge($staffOrOwner, [FIGURIFY_WF_CUSTOMER, FIGURIFY_WF_SYSTEM]), "from" => ["SHIPPED"]],

        /* refund (OWNER LANG). Ang start_refund mula SHIPPED/COMPLETED
           ay ang tahasang, owner-only na exception workflow (hal. sira ang
           dumating, defective) - kailangan ng dahilan at may bayad. */
        "start_refund"          => ["roles" => [FIGURIFY_WF_OWNER],        "from" => array_merge(
                                        FIGURIFY_WF_PRODUCTION_STATES,
                                        ["AWAITING_BALANCE", "BALANCE_SUBMITTED", "READY_TO_SHIP", "SHIPPED", "COMPLETED"]
                                    )],
        "complete_refund"       => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["REFUND_REQUIRED"]],
        "refund_no_payment"     => ["roles" => [FIGURIFY_WF_OWNER],        "from" => ["REFUND_REQUIRED"]],
    ];
}

/* Verification page "Decline" - mali ang details/info; babalik sa customer
   (Payment Required) at mababasa niya ang dahilan sa My Orders. */
const FIGURIFY_WF_DETAILS_DECLINE_REASONS = [
    "reference_mismatch"         => "Reference Number Does Not Match the Proof",
    "unclear_payment_proof"      => "Payment Proof Is Unclear or Invalid",
    "incorrect_amount"           => "Amount Paid Does Not Match the Down Payment",
    "incorrect_shipping_details" => "Incorrect or Incomplete Shipping / Contact Details",
];

const FIGURIFY_WF_ACTION_LABELS = [
    "submit_order"          => "Order Submitted",
    "return_for_correction" => "Returned for Correction",
    "submit_correction"     => "Customer Submitted Corrections",
    "verify_and_quote"      => "Order Details Verified & Quotation Sent",
    "decline_order"         => "Order Declined",
    "accept_quotation"      => "Quotation Accepted",
    "cancel_order"          => "Order Cancelled by Customer",
    "expire_quote"          => "Quotation Expired",
    "release_full_date"     => "Booking Date Full",
    "change_date"           => "Booking Date Changed",
    "dressup_to_payment"    => "Ready for Payment (Dress Up)",
    "submit_payment"        => "Payment Submitted",
    "verify_payment_details" => "Payment Details Verified",
    "decline_payment_details" => "Payment Details Declined",
    "approve_payment"       => "Payment Approved",
    "reject_payment"        => "Payment Rejected",
    "cancel_paid_order"     => "Order Cancelled (Refund Required)",
    "submit_progress"       => "Progress Uploaded",
    "approve_progress"      => "Progress Approved",
    "auto_approve_progress" => "Progress Auto-Approved",
    "request_revision"      => "Revision Requested",
    "accept_revision"       => "Revision Accepted",
    "decline_revision"      => "Revision Declined",
    "finalize_order"        => "Order Finalized",
    "submit_final_photo"    => "Final Photo Sent",
    "submit_balance"        => "Balance Submitted",
    "approve_balance"       => "Balance Approved",
    "reject_balance"        => "Balance Rejected",
    "ship_order"            => "Order Shipped",
    "complete_order"        => "Order Completed",
    "start_refund"          => "Refund Started",
    "complete_refund"       => "Refund Completed",
    "refund_no_payment"     => "Cancelled — No Payment Received",
];

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

        return $deny("Something went wrong. Nothing was changed — please try again.", "error", $state ?? null, $order ?? null);
    }

    $finalState = !empty($steps) ? end($steps)[1] : $state;

    /* activity log (hiwalay sa history; para sa Activity Log page) */
    figurify_wf_log_activity_row(
        $conn,
        $actor,
        "wf_" . $action,
        figurify_wf_action_label($action) . " — order " . $orderId
            . (($toStatus !== $order["status"]) ? (" (" . figurify_wf_state_label($state) . " → " . figurify_wf_state_label($finalState) . ")") : "")
            . ($reason ? (" — " . $reason) : ""),
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
    $reason      = ($reason === null) ? null : mb_substr($reason, 0, 1000);

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

function figurify_wf_log_activity_row(mysqli $conn, array $actor, string $type, string $description, ?int $orderId): void
{
    try {

        figurify_ensure_activity_log_table($conn);

        $actorId   = $actor["id"];
        $actorName = mb_substr((string) $actor["name"], 0, 100);
        $actorRole = ($actor["role"] === FIGURIFY_WF_OWNER) ? "admin" : (string) $actor["role"];

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
            . " — " . $message,
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
   idinadagdag sa bawat POST form ng Shared/all-in-one.php. */

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


/* CUSTOMER: isumite ang corrections */

function figurify_wf_apply_submit_correction(mysqli $conn, array $order, array $p, array $ctx): array
{
    $note = figurify_wf_clean_reason($p["note"] ?? "", 1000);

    $newDate     = trim((string) ($p["booking_date"] ?? ""));
    $figureNotes = is_array($p["figure_notes"] ?? null) ? $p["figure_notes"] : [];
    $newImages   = is_array($p["new_images"] ?? null) ? $p["new_images"] : [];

    $changes = [];

    /* booking date - dumaan pa rin sa parehong booking cap rules */
    if ($newDate !== "" && $newDate !== (string) $order["booking_date"]) {

        $normalized = date("Y-m-d", strtotime($newDate) ?: 0);

        if ($normalized !== $newDate) {
            figurify_wf_fail("Invalid booking date.");
        }

        $check = ($order["order_type"] === "rush")
            ? figurify_check_rush_date($conn, $normalized)
            : figurify_check_nonrush_date($conn, $normalized);

        if (!$check["ok"]) {
            figurify_wf_fail($check["reason"] ?? "That booking date is not available.");
        }

        figurify_wf_exec($conn, "UPDATE orders SET booking_date = ? WHERE order_id = ?", "si", [$normalized, (int) $order["order_id"]]);

        $changes["booking_date"] = ["from" => $order["booking_date"], "to" => $normalized];
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

    figurify_wf_set_status($conn, $order, "pending");

    return [
        "to_status" => "pending",
        "steps"     => [
            ["STAFF_CORRECTION_REQUIRED", "CUSTOMER_CORRECTED"],
            ["CUSTOMER_CORRECTED", "PENDING_STAFF_VERIFICATION"],
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

    $figStmt = $conn->prepare("SELECT figure_id FROM order_figures WHERE order_id = ?");
    $orderId = (int) $order["order_id"];
    $figStmt->bind_param("i", $orderId);
    $figStmt->execute();
    $figureIds = array_map("intval", array_column($figStmt->get_result()->fetch_all(MYSQLI_ASSOC), "figure_id"));
    $figStmt->close();

    if (empty($figureIds)) {
        figurify_wf_fail("This order has no figures to quote.");
    }

    $figuresTotal = 0.0;

    foreach ($figureIds as $figureId) {

        if (!isset($prices[$figureId]) || !is_numeric($prices[$figureId]) || (float) $prices[$figureId] < 0) {
            figurify_wf_fail("Please enter a valid price for every figure.");
        }

        $price = round((float) $prices[$figureId], 2);

        figurify_wf_exec($conn, "UPDATE order_figures SET quoted_price = ? WHERE figure_id = ? AND order_id = ?", "dii", [$price, $figureId, $orderId]);

        $figuresTotal += $price;
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
    $slotCheck = figurify_date_has_capacity($conn, (string) $order["order_type"], date("Y-m-d", strtotime((string) $order["booking_date"])));

    if (!$slotCheck["ok"]) {
        figurify_wf_fail("Your booking date is no longer available. " . ($slotCheck["reason"] ?? "") . " Please choose a new date.", "date_full");
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
    // walang naaprubahang bayad sa mga state na ito (tingnan ang map)
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
        "reason"    => "Dress Up orders have a fixed price — no quotation needed",
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


/* CUSTOMER: palitan ang booking date (same order, walang bagong bayad). Dumadaan sa
   parehong rules ng bagong order (lead time, last day para sa non-rush, at slot cap). */

function figurify_wf_apply_change_date(mysqli $conn, array $order, array $p, array $ctx): array
{
    $newDate = trim((string) ($p["booking_date"] ?? ""));
    $parsed  = DateTime::createFromFormat("Y-m-d", $newDate);

    if (!$parsed || $parsed->format("Y-m-d") !== $newDate) {
        figurify_wf_fail("Please pick a valid booking date.");
    }

    $oldDate = !empty($order["booking_date"]) ? date("Y-m-d", strtotime((string) $order["booking_date"])) : "";

    if ($newDate === $oldDate) {
        figurify_wf_fail("That is already your booking date. Please pick a different one.");
    }

    $check = figurify_check_booking_date($conn, (string) $order["order_type"], $newDate);

    if (!$check["ok"]) {
        figurify_wf_fail($check["reason"] ?? "That booking date is not available.", "date_full");
    }

    figurify_wf_exec($conn, "UPDATE orders SET booking_date = ? WHERE order_id = ?", "si", [$newDate, (int) $order["order_id"]]);

    return [
        "to_status" => $order["status"],
        "steps"     => [[$ctx["state"], $ctx["state"]]],
        "details"   => ["booking_date" => ["from" => $oldDate, "to" => $newDate]],
    ];
}


/* SYSTEM: napuno ang petsa -> cancel ang hindi pa nakabayad */

function figurify_wf_apply_release_full_date(mysqli $conn, array $order, array $p, array $ctx): array
{
    figurify_wf_set_status($conn, $order, "cancelled", ["quote_expires_at = NULL"]);

    return [
        "to_status" => "cancelled",
        "steps"     => [[$ctx["state"], "CANCELLED"]],
        "reason"    => "Booking date became full before payment was made",
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

    $pin = (isset($p["pin_address"]) && trim((string) $p["pin_address"]) !== "") ? (string) $p["pin_address"] : null;

    figurify_wf_set_status(
        $conn,
        $order,
        "to_verify",
        [
            "shipping_name = ?", "shipping_address = ?", "pin_address = ?", "shipping_contact = ?",
            "courier = ?", "payment_reference = ?", "payment_proof = ?",
            "refund_account_name = ?", "refund_account_number = ?", "refund_method = ?",
            "paid_at = NOW()",
        ],
        "ssssssssss",
        [
            $p["shipping_name"], $p["shipping_address"], $pin, $p["shipping_contact"],
            $p["courier"], $p["payment_reference"], $p["payment_proof"],
            $p["refund_account_name"], $p["refund_account_number"], $p["refund_method"],
        ]
    );

    return [
        "to_status" => "to_verify",
        "steps"     => [["AWAITING_PAYMENT", "PAYMENT_SUBMITTED"]],
        "details"   => ["payment_reference" => $p["payment_reference"], "payment_proof" => $p["payment_proof"]],
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
    if (!empty($order["booking_date"])) {

        $capacity = figurify_date_has_capacity(
            $conn,
            (string) $order["order_type"],
            date("Y-m-d", strtotime((string) $order["booking_date"]))
        );

        if (!$capacity["ok"]) {
            figurify_wf_fail("Cannot approve - the booking date is already full. " . ($capacity["reason"] ?? ""), "date_full");
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

    figurify_wf_exec(
        $conn,
        "INSERT INTO order_progress_updates
            (order_id, sent_by_user_id, sent_by_role, image_path, staff_message, customer_response, is_final)
         VALUES (?, ?, ?, ?, ?, 'pending', 0)",
        "iisss",
        [$orderId, $userId, $sentBy, $imagePath, $message]
    );

    $updateId = (int) $conn->insert_id;

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

function figurify_wf_apply_auto_approve_progress(mysqli $conn, array $order, array $p, array $ctx): array
{
    $latest = $ctx["latest"];

    if (strtotime((string) $latest["created_at"]) > strtotime("-" . FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS . " hours")) {
        figurify_wf_fail("This progress update is not yet due for auto-approval.");
    }

    return figurify_wf_approve_progress_common($conn, $order, $p, $ctx, true);
}

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
        $result["reason"] = "No customer response within " . FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS . " hours";
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

    /* kapareho ng "It's Good" - kapag tinanggap ang revision,
       finalized na ang order at hihingin na ang natitirang balance.
       Ang ginawang revision ang magiging final photo (Ready to Ship). */
    $result = figurify_wf_finalize_into($conn, $order, "REVISION_IN_PROGRESS", [["REVISION_REQUESTED", "REVISION_IN_PROGRESS"]]);
    $result["details"]["update_id"] = $updateId;

    return $result;
}

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
            figurify_wf_fail("This order has no unpaid balance — it can be shipped.");
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
        /* "in_progress" = tinanggap na ang revision at na-finalize na ang order
           (Ready to Ship na) kaya hindi na ito hadlang sa pag-ship */
        "No unresolved revision"         => !($latestResponse === "revision"
                                              || ($latestResponse === "pending" && empty($latest["is_final"]))),
    ];
}

function figurify_wf_apply_ship_order(mysqli $conn, array $order, array $p, array $ctx): array
{
    $tracking = mb_substr(trim((string) ($p["tracking_number"] ?? "")), 0, 100);
    $feeRaw   = trim((string) ($p["shipping_fee"] ?? ""));

    if ($tracking === "" || $feeRaw === "" || !is_numeric($feeRaw) || (float) $feeRaw < 0) {
        figurify_wf_fail("Please fill up a valid Tracking Number and Shipping Fee.");
    }

    foreach (figurify_wf_shipping_checklist($conn, $order, $ctx["latest"]) as $label => $ok) {
        if (!$ok) {
            figurify_wf_fail("Cannot ship yet — check failed: " . $label . ".");
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

function figurify_wf_apply_complete_refund(mysqli $conn, array $order, array $p, array $ctx): array
{
    $type      = (string) ($p["refund_type"] ?? "");
    $reference = mb_substr(trim((string) ($p["refund_reference"] ?? "")), 0, 100);
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

function figurify_wf_apply_refund_no_payment(mysqli $conn, array $order, array $p, array $ctx): array
{
    figurify_wf_set_status($conn, $order, "cancelled", ["refund_amount = 0"]);

    return [
        "to_status" => "cancelled",
        "steps"     => [["REFUND_REQUIRED", "CANCELLED"]],
        "reason"    => "No payment was received — nothing to refund",
    ];
}
