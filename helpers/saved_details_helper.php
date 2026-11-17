<?php
/* ==========================================================================
   SAVED DETAILS HELPER   (helpers/saved_details_helper.php)
   Naka-save na Shipping at Refund details ng customer (isang row bawat user),
   para hindi na paulit-ulit mag-fill up sa Payment page.
   Ang Full Name ay hindi dito: ang pangalan sa account (Sign Up / Account Information) ang gamit.

   GINAGAMIT NG:
     my-order/payment-shipping.php   auto-fill ng form
     my-order/submit_payment.php     auto-save ng nilagay sa form pagkatapos magbayad
     Profile/profile.php             makita at ma-edit bago umorder

   LAMAN:
     [1] TABLE   figurify_saved_details_table()      gagawin ang table kung wala pa
     [2] GET     figurify_saved_details_get()        laman (blangko kung wala pa)
     [3] CLEAN   figurify_saved_details_from_post()  linisin at i-check ang galing sa form
     [4] SAVE    figurify_saved_details_save()       insert o update
   ========================================================================== */


/* mga column at pinakamahabang laman (kapareho ng figurify_db.sql) */
const FIGURIFY_SAVED_DETAIL_FIELDS = [
    "address_line1"         => 150,
    "address_line2"         => 150,
    "pin_address"           => 255,
    "shipping_contact"      => 20,
    "courier"               => 30,
    "refund_account_name"   => 150,
    "refund_account_number" => 50,
    "refund_method"         => 50,
];


/* ==========================================================================
   [1] TABLE
   ========================================================================== */
function figurify_saved_details_table($conn)
{
    static $ready = false;

    if ($ready) {
        return;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS customer_saved_details (
            user_id               INT PRIMARY KEY,
            address_line1         VARCHAR(150) NULL,
            address_line2         VARCHAR(150) NULL,
            pin_address           VARCHAR(255) NULL,
            shipping_contact      VARCHAR(20)  NULL,
            courier               VARCHAR(30)  NULL,
            refund_account_name   VARCHAR(150) NULL,
            refund_account_number VARCHAR(50)  NULL,
            refund_method         VARCHAR(50)  NULL,
            updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_saved_details_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )"
    );

    $ready = true;
}
/* ====================== END TABLE ====================== */


/* ==========================================================================
   [2] GET  - laging kumpleto ang keys (blangko kapag wala pang naka-save)
   ========================================================================== */
function figurify_saved_details_get($conn, $user_id)
{
    figurify_saved_details_table($conn);

    $details = array_fill_keys(array_keys(FIGURIFY_SAVED_DETAIL_FIELDS), "");

    $stmt = $conn->prepare("SELECT * FROM customer_saved_details WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    foreach ($details as $key => $value) {
        $details[$key] = (string) ($row[$key] ?? "");
    }

    return $details;
}
/* ====================== END GET ====================== */


/* ==========================================================================
   [3] CLEAN  - galing sa Payment form o sa Profile form (parehong pangalan ng field)
       $required = true  : Payment (lahat kailangan, maliban sa pin address)
       $required = false : Profile (puwedeng kulang, pero tama ang format ng nilagay)
       Ibinabalik: ["data" => [...], "error" => ""]
   ========================================================================== */
function figurify_saved_details_from_post(array $post, $required)
{
    $text = function ($key) use ($post) {
        return trim((string) ($post[$key] ?? ""));
    };

    $method      = $text("refund_method");
    $methodOther = mb_substr($text("refund_method_other"), 0, 50);

    $data = [
        "address_line1"         => $text("shipping_address_line1"),
        "address_line2"         => $text("shipping_address_line2"),
        "pin_address"           => $text("pin_address"),
        "shipping_contact"      => $text("shipping_contact"),
        "courier"               => $text("courier"),
        "refund_account_name"   => $text("refund_account_name"),
        "refund_account_number" => $text("refund_account_number"),
        "refund_method"         => ($method === "other") ? $methodOther : $method,
    ];

    foreach (FIGURIFY_SAVED_DETAIL_FIELDS as $key => $max) {
        $data[$key] = mb_substr($data[$key], 0, $max);
    }

    $error = "";

    if ($data["shipping_contact"] !== "" && !preg_match('/^[0-9]{11}$/', $data["shipping_contact"])) {
        $error = "Contact number must be 11 digits.";
    } elseif ($data["refund_account_number"] !== "" && !preg_match('/^[0-9]+$/', $data["refund_account_number"])) {
        $error = "Account number must be numbers only.";
    } elseif ($data["courier"] !== "" && !in_array($data["courier"], ["lbc", "lalamove"], true)) {
        $error = "Please choose a courier.";
    } elseif ($method !== "" && (!in_array($method, ["gcash", "bpi", "other"], true) || ($method === "other" && $methodOther === ""))) {
        $error = "Please specify your refund bank / method.";
    } elseif ($required) {
        foreach ($data as $key => $value) {
            if ($key !== "pin_address" && $value === "") {
                $error = "Please complete all required fields.";
                break;
            }
        }
    }

    return ["data" => $data, "error" => $error];
}
/* ====================== END CLEAN ====================== */


/* ==========================================================================
   [4] SAVE  - isang row bawat customer (insert kung wala pa, update kung meron)
   ========================================================================== */
function figurify_saved_details_save($conn, $user_id, array $data)
{
    figurify_saved_details_table($conn);

    $columns = array_keys(FIGURIFY_SAVED_DETAIL_FIELDS);
    $values  = [];

    foreach ($columns as $key) {
        $values[] = (string) ($data[$key] ?? "");
    }

    $updates = implode(", ", array_map(fn($key) => $key . " = VALUES(" . $key . ")", $columns));

    $stmt = $conn->prepare(
        "INSERT INTO customer_saved_details (user_id, " . implode(", ", $columns) . ")
         VALUES (?" . str_repeat(", ?", count($columns)) . ")
         ON DUPLICATE KEY UPDATE " . $updates
    );
    $stmt->bind_param("i" . str_repeat("s", count($columns)), $user_id, ...$values);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
/* ====================== END SAVE ====================== */
