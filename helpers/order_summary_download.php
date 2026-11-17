<?php

/* =====================================================================
   order_summary_download.php  -  DOWNLOAD NG ORDER PDF (OWNER AT STAFF)
   =====================================================================
   ANO ITO     : ibinibigay (JSON) ang laman ng PDF ng ISANG order:
                 header (customer, email, total items | order type, method,
                 ship out date), figure details, custom box (kung meron),
                 at payment information.
   PARA SAAN   : sa Active Booking page - ang Shared/order-summary-pdf.js
                 ang gumagawa nito bilang PDF na dina-download ng user.
   PANO NAGANA : gaya ng order_actions.php - ang owner/download_order_summary.php
                 at staff/download_order_summary.php ay maliit na wrapper na
                 nagse-set ng $figurifyPortalRole, tapos nire-require ito.
                 Iisang logic para sa owner at staff, walang dalawang kopya.
   ===================================================================== */

if (!isset($figurifyPortalRole) || !in_array($figurifyPortalRole, ["admin", "staff"], true)) {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/order_helper.php";
require_once __DIR__ . "/booking_helper.php";
require_once __DIR__ . "/dressup_helper.php";

figurify_require_role($figurifyPortalRole);


/* mga status na nasa Active Booking (approved na) - dito lang pwedeng mag-download */
const FIGURIFY_SUMMARY_STATUSES = [
    "processing", "awaiting_balance", "to_verify_balance",
    "for_balance_approval", "to_ship", "address_returned", "shipped",
];

function figurify_summary_money($amount): string
{
    return "₱" . number_format((float) $amount, 2);
}

/* tinatanggal ang mga row na walang laman; ["Label", "value", "price"] ang bawat row (price ay optional) */
function figurify_summary_clean(array $rows): array
{
    $clean = [];

    foreach ($rows as $row) {
        $value = trim((string) ($row[1] ?? ""));
        $price = trim((string) ($row[2] ?? ""));

        if ($value !== "" || $price !== "") {
            $clean[] = [$row[0], $value, $price];
        }
    }

    return $clean;
}

/* presyo na ipapakita sa ikatlong column; walang presyo (0) = walang ilalabas */
function figurify_summary_price($amount): string
{
    return (float) $amount > 0 ? figurify_summary_money($amount) : "";
}

/* lahat ng figure ng order = ilan ang inorder; bawat figure ay isang item.
   Kasama ang reference photos at box photos (URL na, handa nang i-load ng browser). */
function figurify_summary_figures(mysqli $conn, int $orderId): array
{
    $stmt = $conn->prepare("SELECT * FROM order_figures WHERE order_id = ? ORDER BY figure_id ASC");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $figures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $siteBase = figurify_dressup_site_base();

    foreach ($figures as &$fig) {
        foreach (["images" => "order_figure_images", "box_images" => "order_figure_box_images"] as $key => $table) {
            $fig[$key] = [];

            /* walang table = walang larawan (hindi mag-eerror ang download) */
            $imgStmt = $conn->prepare("SELECT image_path FROM $table WHERE figure_id = ?");

            if ($imgStmt === false) {
                continue;
            }

            $imgStmt->bind_param("i", $fig["figure_id"]);
            $imgStmt->execute();

            foreach ($imgStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $imgRow) {
                $fig[$key][] = $siteBase . $imgRow["image_path"];
            }

            $imgStmt->close();
        }
    }

    unset($fig);

    return $figures;
}

/* mga section ng isang item: Dress Up Design (kung dress up), Figure Details,
   at Custom Box (LANG kung may inorder na box). Bawat row: label | value | price. */
function figurify_summary_figure_sections(array $fig, int $number, int $total, float $rushFee): array
{
    $label  = "Item " . $number . " of " . $total;
    $quantity = figurify_figure_quantity($fig);
    $price    = figurify_figure_line_price($fig);   /* presyo ng isang piraso x quantity */

    $design   = figurify_dressup_for_figure($fig);
    $boxType  = (string) ($fig["box_addon_type"] ?? "");
    $hasSize  = !empty($fig["size_label"]);
    $sections = [];

    /* Dress Up: 3D preview (iba't ibang anggulo) + napiling damit at presyo */
    if ($design !== null) {
        $genderRaw = (string) ($design["genderLabel"] ?? ($design["model"]["name"] ?? ""));
        $rows      = [["Gender", preg_match('/girl|female/i', $genderRaw) ? "Girl" : (preg_match('/boy|male|default/i', $genderRaw) ? "Boy" : $genderRaw)]];
        $rows[]    = ["Skin Tone", figurify_dressup_color_text((string) ($design["skin"]["name"] ?? ""), (string) ($design["skin"]["color"] ?? ""))];

        foreach (($design["slots"] ?? []) as $slotRow) {
            $priceLabel = figurify_dressup_price_label($slotRow["item"] ?? null);
            /* kulay + color code (hal. "Custom (#7A3B1F)") para hindi manghula */
            $color      = figurify_dressup_color_text((string) ($slotRow["colorName"] ?? ""), (string) ($slotRow["colorValue"] ?? ""));

            $rows[] = [
                $slotRow["label"] ?? "",
                implode(", ", array_filter([$slotRow["item"]["name"] ?? "", $color])),
                $priceLabel !== "" ? $priceLabel : "Included",
            ];
        }

        $sections[] = [
            "heading" => $label . ": Dress Up Design",
            "viewer"  => json_decode(figurify_dressup_viewer_json($design), true),
            "assets"  => figurify_dressup_site_base() . "Commission/",
            "rows"    => figurify_summary_clean($rows),
        ];
    }

    $sections[] = [
        "heading" => $label . ": Figure Details",
        "rows"    => figurify_summary_clean([
            ["Figure Style", $fig["figure_style"] ?? ""],
            ["Product Type", $fig["product_type"] ?? "", $hasSize ? "" : figurify_summary_price($fig["product_price"] ?? 0)],
            ["Size", $fig["size_label"] ?? "", $hasSize ? figurify_summary_price($fig["product_price"] ?? 0) : ""],
            /* walang Figure Name sa Dress Up */
            ["Figure Name", $design !== null ? "" : figurify_figure_name_display($fig), figurify_summary_price($fig["name_fee"] ?? 0)],
            ["Quantity", $quantity . ($quantity === 1 ? " piece" : " pieces")],
            ["Rush Fee", $rushFee > 0 ? "Rush Order" : "", figurify_summary_price($rushFee)],
            /* Dress Up: nasa rows pa rin ang Notes (walang reference photo) */
            ["Notes", $design !== null ? ($fig["notes"] ?? "") : ""],
            ["Figure Price", "", figurify_summary_price($price)],
        ]),
        /* Image Submission: lalabas ang Notes sa itaas mismo ng Reference Photo, hindi sa gitna ng Rush Fee at Figure Price */
        "notes"   => $design !== null ? "" : trim((string) ($fig["notes"] ?? "")),
        "images"  => $fig["images"],
        "caption" => $label . ": Reference Photo",
    ];

    /* walang custom box na inorder = walang Custom Box section sa PDF */
    if ($boxType === "funko_box") {
        $boxRows = [
            ["Box", "Funko Box"],
            ["Box Type", ($fig["funko_box_type"] ?? "") === "solo" ? "Solo Box" : "Couple Box", figurify_summary_price($fig["box_addon_price"] ?? 0)],
            ["Name on Box", $fig["funko_box_name"] ?? ""],
            ["Box Number", $fig["funko_box_number"] ?? ""],
            ["Box Color", $fig["funko_box_color"] ?? ""],
        ];
    } elseif ($boxType === "hirono_blind_box") {
        $boxRows = [
            ["Box", "Hirono Blind Box"],
            ["Box Type", ($fig["hirono_blind_type"] ?? "") === "regular" ? "Regular Blind Box" : "Blind Box Set", figurify_summary_price($fig["box_addon_price"] ?? 0)],
            ["Box Design", ucwords(str_replace("_", " ", (string) ($fig["hirono_box_design"] ?? "")))],
            ["Box Color", $fig["hirono_box_color"] ?? ""],
            ["Nickname", $fig["hirono_nickname"] ?? ""],
            ["Date", !empty($fig["hirono_date"]) ? date("F j", strtotime($fig["hirono_date"])) : ""],
            ["Letter", $fig["hirono_letter"] ?? ""],
        ];
    } else {
        return $sections;
    }

    $sections[] = [
        "heading" => $label . ": Box & Add-ons",
        "rows"    => figurify_summary_clean($boxRows),
        "images"  => $fig["box_images"],
        "caption" => $label . ": Box Photo",
    ];

    return $sections;
}


/* ---------- ORDER ---------- */

$orderId = (int) ($_GET["order_id"] ?? 0);

$stmt = $conn->prepare(
    "SELECT o.*, u.full_name, u.email
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     WHERE o.order_id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* wala o hindi pa approved / hindi na active = hindi pwedeng i-download */
if (!$order || !in_array(figurify_status_key($order["status"]), FIGURIFY_SUMMARY_STATUSES, true)) {
    http_response_code(404);
    exit("Order not found in Active Booking.");
}

$figures   = figurify_summary_figures($conn, $orderId);
$rushSplit = figurify_rush_split($order, $figures);
$conn->close();


/* ---------- LAMAN NG PDF (binubuo ng Shared/order-summary-pdf.js) ---------- */

$methodLabels = ["reference" => "Image Submission", "create_style" => "Dress Up"];
$methodLabel  = $methodLabels[$order["order_method"] ?? ""] ?? "Image Submission";

/* header: kaliwa = customer, email, total items | kanan = order type, method, ship out date */
$header = [
    "title"     => "Order #" . $orderId,
    "generated" => "Generated " . date("F j, Y, g:i A"),
    "left"      => figurify_summary_clean([
        ["Customer", $order["full_name"]],
        ["Email", $order["email"]],
        ["Total Items", array_sum(array_map("figurify_figure_quantity", $figures))],
    ]),
    "right"     => figurify_summary_clean([
        ["Order Type", $order["order_type"] === "rush" ? "Rush" : "Non-Rush"],
        ["Order Method", $methodLabel],
        ["Ship Out Date", !empty($order["ship_out_date"]) ? date("F j, Y", strtotime($order["ship_out_date"])) : ""],
    ]),
];

$sections = [];

foreach ($figures as $index => $fig) {
    $sections = array_merge(
        $sections,
        figurify_summary_figure_sections($fig, $index + 1, count($figures), (float) ($rushSplit["per"][$index] ?? 0))
    );
}

$sections[] = [
    "heading" => "Payment Information",
    "rows"    => figurify_summary_clean([
        ["Ship To", $order["shipping_name"] ?? ""],
        ["Address", $order["shipping_address"] ?? ""],
        ["Contact", $order["shipping_contact"] ?? ""],
        ["Courier", strtoupper((string) ($order["courier"] ?? ""))],
        ["Tracking No.", $order["tracking_number"] ?? ""],
        ["Rush Fee", figurify_summary_price($rushSplit["total"])],
        ["Total Amount", figurify_summary_money($order["total_amount"])],
        [figurify_wf_downpayment_label($order), figurify_summary_money(figurify_wf_downpayment_amount($order))],
        ["Balance", figurify_summary_money(figurify_wf_balance_amount($order))],
        ["Reference No.", $order["payment_reference"] ?? ""],
        ["Paid On", !empty($order["paid_at"]) ? date("F j, Y, g:i A", strtotime($order["paid_at"])) : ""],
    ]),
];

header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff");

echo json_encode([
    "order_id" => $orderId,
    "header"   => $header,
    "sections" => $sections,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
exit();
