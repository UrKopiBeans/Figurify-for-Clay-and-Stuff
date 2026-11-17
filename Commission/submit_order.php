<?php

/* =====================================================================
   SUBMIT ORDER - isinasave ang cart (mga figure) sa database.
   ISANG FILE LANG ito para sa Image Submission AT Dress Up.
   Laging JSON ang sagot: { success: true/false, message: "..." }

   PANTANDAAN (hanapin ang mga tag na ito sa file):
     [COMMON]            = ginagamit ng PAREHONG Image Submission at Dress Up
     [IMAGE SUBMISSION]  = Image Submission lang  (order_method = "reference")
     [DRESS UP]          = Dress Up lang          (order_method = "create_style")

   MAPA NG FILE (mula taas hanggang baba):
     A. SETUP                      [COMMON]  session, JSON na sagot kahit may error
     B. MGA FUNCTION               [COMMON]  save figure, images, box, total
     C. DRESS UP FUNCTIONS         [DRESS UP] linis ng design, presyo ng server
     D. submit_reference_order()   [IMAGE SUBMISSION]  ang buong proseso nito
     E. submit_dressup_order()     [DRESS UP]          ang buong proseso nito
     F. MAIN                       [COMMON]  mga tseke, tapos pipiliin kung D o E

   PAGKAKAIBA NG DALAWA:
     Image Submission -> status "pending",          staff ang magbibigay ng quotation
     Dress Up         -> status "awaiting_payment", kuwenta ng server ang presyo, diretso bayad
   ===================================================================== */


/* #####################################################################
   A. SETUP   [COMMON]
   ##################################################################### */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/messaging_helper.php";
require_once __DIR__ . "/../helpers/order_helper.php";

error_reporting(E_ALL);
ini_set("display_errors", "0");
ini_set("log_errors", "1");


/* buffer ang output kasi kailangan laging JSON lang ang response,
   kahit may warning o mag-die ang database.php */

ob_start();

register_shutdown_function(function () {

    $buffered = ob_get_contents();

    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header("Content-Type: application/json");
    }

    /* valid JSON na - ipasa as-is */

    if (json_decode($buffered, true) !== null) {
        echo $buffered;
        return;
    }

    /* hindi valid JSON - ilog ang totoong error, generic message na lang sa customer */

    if ($buffered !== "") {
        error_log("submit_order.php raw output: " . $buffered);
    }

    echo json_encode([
        "success" => false,
        "message" => "Something went wrong while saving your order. Please try again."
    ]);
});

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/booking_helper.php";
require_once __DIR__ . "/../helpers/content_helper.php";   /* figurify_site_prices() - presyo galing sa owner */


/* ibalik ang error bilang JSON at itigil ang script */

function submit_order_fail(string $message): void
{
    echo json_encode(["success" => false, "message" => $message]);
    exit();
}


/* #####################################################################
   B. MGA FUNCTION   [COMMON]
   Ginagamit ng Image Submission at Dress Up
   ##################################################################### */

/* box add-ons ng isang figure -> mga value na ilalagay sa order_figures.
   Null ang lahat kapag walang napiling box. */

function submit_order_read_box(array $figure): array
{
    $box = [
        "type"            => null,
        "price"           => floatval($figure["boxAddonPrice"] ?? 0),
        "funko_type"      => null,
        "funko_name"      => null,
        "funko_number"    => null,
        "funko_color"     => null,
        "hirono_type"     => null,
        "hirono_design"   => null,
        "hirono_color"    => null,
        "hirono_letter"   => null,
        "hirono_nickname" => null,
        "hirono_date"     => null,
        "hirono_items"    => null,
        "hirono_items_total" => 0,
    ];

    $details = $figure["boxDetails"] ?? null;

    /* boxDetails ay laging nandoon sa JS kahit walang box na napili, kaya tingnan muna kung may boxType/blindType */

    if (!is_array($details)) {
        return $box;
    }

    $addonType = $details["addonType"] ?? "";
    $boxType   = trim($details["boxType"] ?? "");
    $blindType = trim($details["blindType"] ?? "");

    if ($addonType === "funko_box" && $boxType !== "") {

        $box["type"]         = "funko_box";
        $box["funko_type"]   = $boxType;
        $box["funko_name"]   = $details["boxName"] ?? "";
        $box["funko_number"] = $details["boxNumber"] ?? "";
        $box["funko_color"]  = $details["boxColor"] ?? "";

    } elseif ($addonType === "hirono_blind_box" && $blindType !== "") {

        $months = [
            "01" => "January",   "02" => "February", "03" => "March",
            "04" => "April",     "05" => "May",       "06" => "June",
            "07" => "July",      "08" => "August",    "09" => "September",
            "10" => "October",   "11" => "November",  "12" => "December",
        ];

        $month = $details["dateMonth"] ?? "";
        $day   = $details["dateDay"] ?? "";

        $box["type"]            = "hirono_blind_box";
        $box["hirono_type"]     = $blindType;
        $box["hirono_design"]   = $details["boxDesign"] ?? "";
        $box["hirono_color"]    = $details["boxColor"] ?? "";
        $box["hirono_letter"]   = $details["letter"] ?? "";
        $box["hirono_nickname"] = $details["nickname"] ?? "";

        if ($month !== "" && $day !== "") {
            $box["hirono_date"] = ($months[$month] ?? $month) . " " . ltrim($day, "0");
        }

        /* optional extras, naka-save bilang JSON e.g. { "tear_blind_paper": 50 } */

        $extras = $details["blindItems"] ?? [];

        if (is_array($extras) && count($extras) > 0) {

            $cleanExtras = [];

            foreach ($extras as $extraKey => $extraPrice) {
                $cleanExtras[(string) $extraKey] = floatval($extraPrice);
                $box["hirono_items_total"] += floatval($extraPrice);
            }

            $box["hirono_items"] = json_encode($cleanExtras);
        }
    }

    return $box;
}


/* I-save ang mga image ng isang figure.
   $fileKey = pangalan ng file field na ipinadala ng JS (figure_0_images, figure_0_box_images)
   $table   = saan ilalagay ang path
   $tag     = bahagi ng filename ("img" o "box") */

function submit_order_save_images(
    $conn,
    int $orderId,
    int $figureId,
    string $fileKey,
    string $table,
    string $tag,
    string $uploadDir
): void {

    if (!isset($_FILES[$fileKey])) {
        return;
    }

    $allowedExt = ["jpg", "jpeg", "png", "gif", "webp"];
    $fileCount  = count($_FILES[$fileKey]["name"]);

    for ($i = 0; $i < $fileCount; $i++) {

        if ($_FILES[$fileKey]["error"][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpPath      = $_FILES[$fileKey]["tmp_name"][$i];
        $originalName = basename($_FILES[$fileKey]["name"][$i]);
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        /* image lang, at dapat totoong image ang laman */

        if (!in_array($ext, $allowedExt, true) || !figurify_is_real_image($tmpPath)) {
            continue;
        }

        $safeName = "fig" . $figureId . "_" . $tag . $i . "_" . time() . "." . $ext;

        if (!move_uploaded_file($tmpPath, $uploadDir . $safeName)) {
            continue;
        }

        $relativePath = "uploads/orders/" . $orderId . "/" . $safeName;

        $stmt = $conn->prepare("INSERT INTO $table (figure_id, image_path) VALUES (?, ?)");
        $stmt->bind_param("is", $figureId, $relativePath);
        $stmt->execute();
        $stmt->close();
    }
}

/* [COMMON] Quantity: ang isang cart item na may quantity N ay IISANG figure lang sa order
   (may "quantity" = N), hindi hinahati sa Figure 1, Figure 2. Dito lang sinisiguro na
   1 pataas ang bawat quantity at hindi lalampas sa FIGURIFY_MAX_ORDER_UNITS ang buong order. */

const FIGURIFY_MAX_ORDER_UNITS = 100;

function submit_order_check_quantities(array $figures): array
{
    $checked = [];
    $pieces  = 0;

    foreach (array_values($figures) as $figure) {

        $quantity = max(1, (int) ($figure["quantity"] ?? 1));
        $pieces  += $quantity;

        if ($pieces > FIGURIFY_MAX_ORDER_UNITS) {
            submit_order_fail("An order can have up to " . FIGURIFY_MAX_ORDER_UNITS . " pieces only.");
        }

        $checked[] = array_merge($figure, ["quantity" => $quantity]);
    }

    return $checked;
}

/* [COMMON] I-save ang isang figure sa order_figures.
   Ibinabalik ang figure_id at kung anong box ang napili. */

function submit_order_save_figure($conn, int $orderId, int $index, $figure): array
{
    $style        = $figure["style"] ?? "";
    $product      = $figure["product"] ?? "";
    $size         = $figure["size"] ?? "";
    $sizeLabel    = $figure["sizeLabel"] ?? "";
    $name         = $figure["name"] ?? "";
    $notes        = figurify_db_text((string) ($figure["notes"] ?? ""));
    $productPrice = floatval($figure["productPrice"] ?? 0);
    $nameFee      = floatval($figure["nameFee"] ?? 0);
    $figureTotal  = floatval($figure["total"] ?? 0);
    $quantity     = max(1, (int) ($figure["quantity"] ?? 1));

    /* style at product type ang required */

    if ($style === "" || $product === "") {

        $missing = [];

        if ($style === "")   $missing[] = "style";
        if ($product === "") $missing[] = "product type";

        throw new Exception(
            "Incomplete figure data on figure " . ($index + 1) .
            ". Missing: " . implode(", ", $missing) .
            ". (Received style='" . $style . "', product='" . $product . "')"
        );
    }

    /* optional ang name sa frontend, placeholder na lang kung wala */

    if ($name === "") {
        $name = $style . " Figure " . ($index + 1);
    }

    $box = submit_order_read_box($figure);

    $stmt = $conn->prepare(
        "INSERT INTO order_figures
            (order_id, figure_style, product_type, size_value, size_label,
             figure_name, notes, product_price, name_fee, figure_total, quantity,
             box_addon_type, box_addon_price,
             funko_box_type, funko_box_name, funko_box_number, funko_box_color,
             hirono_blind_type, hirono_box_design, hirono_box_color, hirono_letter,
             hirono_nickname, hirono_date, hirono_blind_items, hirono_blind_items_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Prepare failed (order_figures): " . $conn->error);
    }

    $stmt->bind_param(
        "issssssdddisdsssssssssssd",
        $orderId,
        $style,
        $product,
        $size,
        $sizeLabel,
        $name,
        $notes,
        $productPrice,
        $nameFee,
        $figureTotal,
        $quantity,
        $box["type"],
        $box["price"],
        $box["funko_type"],
        $box["funko_name"],
        $box["funko_number"],
        $box["funko_color"],
        $box["hirono_type"],
        $box["hirono_design"],
        $box["hirono_color"],
        $box["hirono_letter"],
        $box["hirono_nickname"],
        $box["hirono_date"],
        $box["hirono_items"],
        $box["hirono_items_total"]
    );

    $stmt->execute();

    $figureId = $stmt->insert_id;

    $stmt->close();

    return [$figureId, $box["type"]];
}


/* [COMMON] Kuwentahin ang total (sa server, hindi sa browser).
   Rush fee ay kada figure, base sa product type. */

function submit_order_totals(array $figures, string $orderType): array
{
    $rushFee = ($orderType === "rush") ? figurify_calculate_total_rush_fee($figures) : 0;

    $subtotal = 0;

    foreach ($figures as $figure) {
        $subtotal += floatval($figure["total"] ?? 0) * max(1, (int) ($figure["quantity"] ?? 1));
    }

    return ["rush_fee" => $rushFee, "subtotal" => $subtotal, "total" => $subtotal + $rushFee];
}


/* [COMMON] I-save ang buong order: orders -> figures -> images.
   Transaction ito: kapag may pumalya, walang mase-save (lahat o wala).
   Ibinabalik ang order_id. */

function submit_order_create(
    $conn,
    $userId,
    string $orderType,
    string $orderMethod,
    string $bookingDate,
    array $figures,
    array $totals,
    string $status
): int {

    $isDressUp = ($orderMethod === "create_style");

    figurify_wf_ensure_schema($conn);   /* may order_figures.quantity na */

    $conn->begin_transaction();

    /* 1. orders */

    $stmt = $conn->prepare(
        "INSERT INTO orders
            (user_id, order_type, order_method, ship_out_date, rush_fee, subtotal, total_amount, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Prepare failed (orders): " . $conn->error);
    }

    $stmt->bind_param(
        "isssddds",
        $userId,
        $orderType,
        $orderMethod,
        $bookingDate,
        $totals["rush_fee"],
        $totals["subtotal"],
        $totals["total"],
        $status
    );

    $stmt->execute();

    $orderId = $stmt->insert_id;

    $stmt->close();

    /* 2. folder para sa mga image ng order na ito */

    $uploadDir = __DIR__ . "/../uploads/orders/" . $orderId . "/";

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new Exception("Could not create upload folder.");
    }

    /* 3. bawat figure */

    foreach ($figures as $index => $figure) {

        [$figureId, $boxType] = submit_order_save_figure($conn, $orderId, (int) $index, $figure);

        /* [DRESS UP] i-save ang design (gender, skin, hair, top, bottom, shoes, kulay, 3D paths) */

        if ($isDressUp) {
            submit_dressup_save_design($conn, $figureId, (int) $index, $figure);
        }

        /* mga image - JS ang nagpapadala sa figure_{index}_images[] (isang set lang kahit may quantity) */

        $imageSets = [["images", "order_figure_images", "img"]];

        /* box reference images - Hirono blind box lang, sa figure_{index}_box_images[] */

        if ($boxType === "hirono_blind_box") {
            $imageSets[] = ["box_images", "order_figure_box_images", "box"];
        }

        foreach ($imageSets as [$fileSuffix, $table, $tag]) {
            submit_order_save_images(
                $conn, $orderId, $figureId,
                "figure_" . $index . "_" . $fileSuffix, $table, $tag, $uploadDir
            );
        }
    }

    $conn->commit();

    return (int) $orderId;
}


/* [COMMON] Pagkatapos ma-save: itala ang rate limit at ang unang history ng order.
   history: DRAFT -> PENDING (Image Submission) o DRAFT -> AWAITING_PAYMENT (Dress Up) */

function submit_order_finish($conn, int $orderId, $userId, string $status): void
{
    figurify_record_action_attempt($conn, "commission_order", "user_" . $userId);

    figurify_wf_record_order_created($conn, $orderId, figurify_wf_session_actor(), $status);
}


/* #####################################################################
   C. DRESS UP FUNCTIONS   [DRESS UP]
   Para sa Dress Up lang. Hindi ito ginagamit ng Image Submission.

   Mga bahagi:
     C1. Linisin ang design data galing browser
     C2. Presyo ng bawat card (binabasa mula sa dress-up.php)
     C3. Kuwenta ng server (size, items, box) - hindi pinagkakatiwalaan ang browser
     C4. I-save ang design sa database
   ##################################################################### */

/* productKey => [category, Figure Style, Product Type] (kapareho ng PRODUCT_SIZE_PRICES sa Commission/dress-up.js).
   Ang presyo mismo ay galing sa owner: figurify_site_prices() (helpers/content_helper.php SECTION 8). */

const FIGURIFY_DRESSUP_PRODUCTS = [
    "funkoBoy"           => ["funko",  "Funko Pop", "Full Body Standee"],
    "funkoGirl"          => ["funko",  "Funko Pop", "Full Body Standee"],
    "chibiBoy"           => ["chibi",  "Chibi",     "Full Body Standee"],
    "chibiGirl"          => ["chibi",  "Chibi",     "Full Body Standee"],
    "chibiBoyKeychain"   => ["chibi",  "Chibi",     "Full Body Keychain"],
    "chibiGirlKeychain"  => ["chibi",  "Chibi",     "Full Body Keychain"],
    "hironoStandee"      => ["hirono", "Hirono",    "Full Body Standee"],
    "hironoKeychain"     => ["hirono", "Hirono",    "Full Body Keychain"],
    "hironoHeadKeychain" => ["hirono", "Hirono",    "Head Only Keychain"],
];

/* dagdag-presyo ng bawat item kada size: 1st size 100%, 2nd 125%, 3rd 150%, 4th 175% */
const FIGURIFY_DRESSUP_SIZE_PERCENTS = [100, 125, 150, 175];



/* =====================================================
   C1. LINISIN ANG DESIGN DATA   [DRESS UP]
   Tinatanggap lang ang kailangan para maipakita ulit ang 3D figure
   at listahan ng napili. Puro string/number lang, may size limit.
===================================================== */

function figurify_dressup_clean_text($value, int $max = 200): string
{
    $text = trim((string) $value);

    return function_exists("mb_substr") ? mb_substr($text, 0, $max) : substr($text, 0, $max);
}


/* 3D files lang sa loob ng dressup-assets, o "placeholder:" (wala pang 3D) */

function figurify_dressup_clean_path($value): string
{
    $path = figurify_dressup_clean_text($value, 255);

    if (strpos($path, "placeholder:") === 0) {
        return $path;
    }

    if (!preg_match('#^dressup-assets/[A-Za-z0-9 _()./+-]+\.glb$#', $path) || strpos($path, "..") !== false) {
        return "";
    }

    return $path;
}


/* larawan ng card sa loob ng dressup-assets lang (png / jpg / webp) */

function figurify_dressup_clean_image($value): string
{
    $path = figurify_dressup_clean_text($value, 255);

    if (!preg_match('#^dressup-assets/[A-Za-z0-9 _()./+-]+\.(png|jpe?g|webp)$#i', $path) || strpos($path, "..") !== false) {
        return "";
    }

    return $path;
}


function figurify_dressup_clean_color($value): string
{
    $color = trim((string) $value);

    return preg_match('/^#[0-9A-Fa-f]{3,8}$/', $color) ? $color : "";
}


function figurify_dressup_clean_item($item): ?array
{
    if (!is_array($item)) {
        return null;
    }

    return [
        "name"     => figurify_dressup_clean_text($item["name"] ?? ""),
        "model"    => figurify_dressup_clean_path($item["model"] ?? ""),
        "color"    => figurify_dressup_clean_color($item["color"] ?? ""),
        "price"    => max(0, (float) ($item["price"] ?? 0)),
        "billable" => !empty($item["billable"]),
    ];
}


/* ibinabalik ang JSON na ise-save sa database, o null kung sira ang data */

function figurify_clean_dressup_payload($raw): ?string
{
    if (!is_array($raw)) {
        return null;
    }

    $category = figurify_dressup_clean_text($raw["category"] ?? "", 20);

    if (!in_array($category, ["funko", "hirono", "chibi"], true)) {
        return null;
    }

    /* hair / top / bottom / shoes / hat - hanggang 20 lang */

    $slots = [];

    foreach ((is_array($raw["slots"] ?? null) ? $raw["slots"] : []) as $slotRow) {

        if (!is_array($slotRow) || count($slots) >= 20) {
            continue;
        }

        $item = figurify_dressup_clean_item($slotRow["item"] ?? null);

        if ($item === null) {
            continue;
        }

        $slots[] = [
            "slot"       => figurify_dressup_clean_text($slotRow["slot"] ?? "", 40),
            "label"      => figurify_dressup_clean_text($slotRow["label"] ?? "", 60),
            "item"       => $item,
            "image"      => figurify_dressup_clean_image($slotRow["image"] ?? ""),
            "colorName"  => figurify_dressup_clean_text($slotRow["colorName"] ?? "", 60),
            "colorValue" => figurify_dressup_clean_color($slotRow["colorValue"] ?? ""),
            "isBottom"   => !empty($slotRow["isBottom"]),
        ];
    }

    /* kailangan ng figure model */

    $model = figurify_dressup_clean_item($raw["model"] ?? null);

    if ($model === null || $model["model"] === "") {
        return null;
    }

    $clean = [
        "version"     => 1,
        "category"    => $category,
        "productKey"  => figurify_dressup_clean_text($raw["productKey"] ?? "", 40),
        "productType" => figurify_dressup_clean_text($raw["productType"] ?? "", 60),
        "genderLabel" => figurify_dressup_clean_text($raw["genderLabel"] ?? "", 60),
        "model"       => $model,
        "skin"        => figurify_dressup_clean_item($raw["skin"] ?? null),
        "slots"       => $slots,
        "size"        => figurify_dressup_clean_text($raw["size"] ?? "", 40),
        "sizePrice"   => max(0, (float) ($raw["sizePrice"] ?? 0)),
        "nameFee"     => max(0, (float) ($raw["nameFee"] ?? 0)),
        "designTotal" => max(0, (float) ($raw["designTotal"] ?? 0)),
    ];

    $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return ($json !== false && strlen($json) <= 60000) ? $json : null;
}


/* =====================================================
   C2. PRESYO NG BAWAT CARD (galing sa Commission/dress-up-options.php)   [DRESS UP]
===================================================== */

/* Binabasa ang bawat <button ... data-price="..."> sa HTML na ginawa ng
   du_section() (Commission/dress-up-options.php).
   Ibinabalik ang listahan ng attributes ng bawat card. */

function figurify_dressup_read_cards(string $source): array
{
    $cards = [];

    preg_match_all('/<button\b([^>]*)>/i', $source, $buttons);

    foreach ($buttons[1] as $attrText) {

        preg_match_all('/data-([a-z-]+)="([^"]*)"/i', $attrText, $pairs, PREG_SET_ORDER);

        $card = [];

        foreach ($pairs as $pair) {
            $card[strtolower($pair[1])] = html_entity_decode($pair[2], ENT_QUOTES);
        }

        $cards[] = $card;
    }

    return $cards;
}


/* ["funko"]["girlHair"]["dressup-assets/....glb"] = presyo */

function figurify_dressup_card_prices(): array
{
    static $prices = null;

    if ($prices !== null) {
        return $prices;
    }

    $prices = [];

    /* Nasa dress-up-options.php na ang lahat ng card ($duFunko, $duChibi, $duHirono),
       hindi na sa dress-up.php. Ire-render dito ang parehong HTML na nakikita ng
       customer, tapos babasahin ang data-price / data-model ng bawat <button>. */

    $optionsFile = __DIR__ . "/dress-up-options.php";

    if (!is_file($optionsFile)) {
        return $prices;
    }

    require_once $optionsFile;

    $source = "";

    /* local kapag dito unang na-require, global kapag na-require na sa labas */
    $panels = [
        $duFunko  ?? $GLOBALS["duFunko"]  ?? [],
        $duChibi  ?? $GLOBALS["duChibi"]  ?? [],
        $duHirono ?? $GLOBALS["duHirono"] ?? [],
    ];

    foreach ($panels as $panel) {
        foreach ($panel as $section) {
            $source .= du_section($section);
        }
    }

    foreach (figurify_dressup_read_cards($source) as $card) {

        if (empty($card["figure"]) || empty($card["slot"]) || empty($card["model"])) {
            continue;
        }

        $billable = ($card["billable"] ?? "true") !== "false";

        $prices[$card["figure"]][$card["slot"]][$card["model"]] = $billable ? (float) ($card["price"] ?? 0) : 0.0;
    }

    return $prices;
}


/* =====================================================
   C3. KUWENTA NG SERVER   [DRESS UP]
   Ang mga function sa baba ay nagbabalik ng resulta, o ng error message (string).
===================================================== */

/* size -> [presyo ng size, percent para sa bawat item].
   Walang size (hal. Hirono Head Only Keychain) = iisang presyo ng product type. */

function figurify_dressup_size_info(string $productKey, string $size)
{
    [, $style, $type] = FIGURIFY_DRESSUP_PRODUCTS[$productKey];
    $product = figurify_site_prices()["products"][$style][$type] ?? [];

    if (empty($product["sizes"])) {
        return [(float) ($product["price"] ?? 0), 100];
    }

    $sizeTable = [];
    foreach ($product["sizes"] as $sizeName => $sizePrice) {
        $sizeTable[$sizeName . " inches"] = $sizePrice;
    }

    if (!isset($sizeTable[$size])) {
        return "Please choose a valid figure size.";
    }

    $sizeIndex = array_search($size, array_keys($sizeTable), true);
    $percents  = FIGURIFY_DRESSUP_SIZE_PERCENTS;

    return [(float) $sizeTable[$size], $percents[min((int) $sizeIndex, count($percents) - 1)]];
}


/* hair / top / bottom / shoes / hat - presyo ng card × size (naka-round sa ₱5),
   kapareho ng scaleDressUpPrice sa Commission/dress-up.js */

function figurify_dressup_slot_prices(array $design, array $cardPrices, int $sizePercent)
{
    $slotPrices = [];

    foreach ((array) ($design["slots"] ?? []) as $slotIndex => $slotRow) {

        $slot = (string) ($slotRow["slot"] ?? "");
        $path = (string) ($slotRow["item"]["model"] ?? "");

        if (!isset($cardPrices[$slot][$path])) {
            return "One of the selected items is no longer available.";
        }

        $basePrice = (float) $cardPrices[$slot][$path];

        $slotPrices[$slotIndex] = $basePrice > 0 ? (float) (round(($basePrice * $sizePercent) / 500) * 5) : 0.0;
    }

    return $slotPrices;
}


/* box / add-ons */

function figurify_dressup_box_price(array $figure, string $category)
{
    $boxDetails = $figure["boxDetails"] ?? null;

    if (!is_array($boxDetails)) {
        return 0.0;
    }

    $addonType = $boxDetails["addonType"] ?? "";
    $prices    = figurify_site_prices(); /* presyo galing sa owner (Content Management > Prices) */

    if ($addonType === "funko_box") {

        $boxType = (string) ($boxDetails["boxType"] ?? "");

        if ($category !== "funko" || !isset($prices["funko_box"][$boxType])) {
            return "Invalid box selection.";
        }

        return (float) $prices["funko_box"][$boxType];
    }

    if ($addonType === "hirono_blind_box") {

        $blindType = (string) ($boxDetails["blindType"] ?? "");

        if ($category !== "hirono" || !isset($prices["blind_box"][$blindType])) {
            return "Invalid blind box selection.";
        }

        $price = (float) $prices["blind_box"][$blindType];

        foreach (array_keys((array) ($boxDetails["blindItems"] ?? [])) as $extraKey) {

            if (!isset($prices["blind_extras"][$extraKey])) {
                return "Invalid blind box extra.";
            }

            $price += $prices["blind_extras"][$extraKey];
        }

        return $price;
    }

    return 0.0;
}


/* presyong kinuwenta ng server para sa isang figure, o error message (string) */

function figurify_dressup_server_price(array $figure)
{
    $design = $figure["dressup"] ?? null;

    if (!is_array($design)) {
        return "Missing Dress Up design data.";
    }

    $category   = (string) ($design["category"] ?? "");
    $productKey = (string) ($design["productKey"] ?? "");

    if ((FIGURIFY_DRESSUP_PRODUCTS[$productKey][0] ?? null) !== $category) {
        return "Unknown Dress Up product.";
    }

    $cardPrices = figurify_dressup_card_prices()[$category] ?? [];

    if (empty($cardPrices)) {
        return "Dress Up prices could not be loaded.";
    }

    /* gender/model - dapat totoong card */

    $modelPath = (string) ($design["model"]["model"] ?? "");

    if (!isset($cardPrices["model"][$modelPath])) {
        return "Unknown figure model.";
    }

    /* size */

    $sizeInfo = figurify_dressup_size_info($productKey, (string) ($figure["size"] ?? ""));

    if (is_string($sizeInfo)) {
        return $sizeInfo;
    }

    [$sizePrice, $sizePercent] = $sizeInfo;

    /* mga item (hair, top, ...) */

    $slotPrices = figurify_dressup_slot_prices($design, $cardPrices, $sizePercent);

    if (is_string($slotPrices)) {
        return $slotPrices;
    }

    $designTotal = array_sum($slotPrices);

    /* walang Figure Name sa Dress Up, kaya walang name fee */

    $nameFee = 0.0;

    /* box */

    $boxPrice = figurify_dressup_box_price($figure, $category);

    if (is_string($boxPrice)) {
        return $boxPrice;
    }

    return [
        "designTotal" => (float) $designTotal,
        "sizePrice"   => $sizePrice,
        "nameFee"     => $nameFee,
        "boxPrice"    => $boxPrice,
        "slotPrices"  => $slotPrices,
        "total"       => $designTotal + $sizePrice + $nameFee + $boxPrice,
    ];
}


/* lumang GLB path (bago ang mga folder) -> bagong folder, para tanggap pa
   rin ang mga lumang Dress Up draft na naka-save sa browser */

function figurify_dressup_relocate_paths(array $figures): array
{
    require_once __DIR__ . "/../helpers/dressup_helper.php"; /* dito na ang figurify_relocate_glb_path() */

    foreach ($figures as $figureIndex => $figure) {

        if (isset($figure["dressup"]["model"]["model"])) {
            $figures[$figureIndex]["dressup"]["model"]["model"] =
                figurify_relocate_glb_path((string) $figure["dressup"]["model"]["model"]);
        }

        foreach ((array) ($figure["dressup"]["slots"] ?? []) as $slotIndex => $slotRow) {
            if (isset($slotRow["item"]["model"])) {
                $figures[$figureIndex]["dressup"]["slots"][$slotIndex]["item"]["model"] =
                    figurify_relocate_glb_path((string) $slotRow["item"]["model"]);
            }
        }
    }

    return $figures;
}


/* Pinapalitan ng presyong galing sa server ang presyo ng bawat figure.
   Ibinabalik ang error message kapag may mali, o null kapag ayos lahat. */

function figurify_dressup_apply_server_prices(array &$figures): ?string
{
    foreach ($figures as $figureIndex => $figure) {

        $serverPrice = is_array($figure)
            ? figurify_dressup_server_price($figure)
            : "Invalid figure data.";

        if (is_string($serverPrice)) {
            return $serverPrice . " Please go back to your design and try again.";
        }

        /* dapat tugma sa presyong ipinakita sa customer */

        if (abs($serverPrice["total"] - floatval($figure["total"] ?? -1)) > 0.009) {

            error_log(
                "submit_order.php dress up price mismatch: client=" .
                ($figure["total"] ?? "none") . " server=" . $serverPrice["total"]
            );

            return "The price of your design has changed. Please check your design and try again.";
        }

        $figures[$figureIndex]["productPrice"]  = $serverPrice["sizePrice"];
        $figures[$figureIndex]["nameFee"]       = $serverPrice["nameFee"];
        $figures[$figureIndex]["boxAddonPrice"] = $serverPrice["boxPrice"];
        $figures[$figureIndex]["total"]         = $serverPrice["total"];
        $figures[$figureIndex]["dressup"]["designTotal"] = $serverPrice["designTotal"];
        $figures[$figureIndex]["dressup"]["sizePrice"]   = $serverPrice["sizePrice"];
        $figures[$figureIndex]["dressup"]["nameFee"]     = $serverPrice["nameFee"];

        /* presyo ng bawat item sa design - galing din sa server */

        foreach ($serverPrice["slotPrices"] as $slotIndex => $slotPrice) {
            $figures[$figureIndex]["dressup"]["slots"][$slotIndex]["item"]["price"]    = $slotPrice;
            $figures[$figureIndex]["dressup"]["slots"][$slotIndex]["item"]["billable"] = $slotPrice > 0;
        }
    }

    return null;
}


/* =====================================================
   C4. I-SAVE ANG DESIGN SA DATABASE   [DRESS UP]
===================================================== */

/* Fixed ang presyo ng Dress Up kaya quoted_price = figure_total agad */

function submit_dressup_save_design($conn, int $figureId, int $index, $figure): void
{
    $dressupJson = figurify_clean_dressup_payload($figure["dressup"] ?? null);

    if ($dressupJson === null) {
        throw new Exception("Missing Dress Up design data on figure " . ($index + 1) . ".");
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE order_figures SET dressup_data = ?, quoted_price = figure_total WHERE figure_id = ?"
        );
    } catch (Throwable $prepareError) {
        $stmt = false;
    }

    if (!$stmt) {
        throw new Exception("The dressup_data column is missing. Please run Admin/add_dressup_orders.sql in phpMyAdmin.");
    }

    $stmt->bind_param("si", $dressupJson, $figureId);
    $stmt->execute();
    $stmt->close();
}


/* Siguraduhing may "dressup_data" column. Kapag hindi pa na-run ang
   Admin/add_dressup_orders.sql, kusa itong idadagdag dito (isang beses lang).
   Ginagawa BAGO ang transaction dahil nag-a-auto-commit ang ALTER TABLE. */

function submit_dressup_ensure_column($conn): void
{
    try {

        $check = $conn->query("SHOW COLUMNS FROM order_figures LIKE 'dressup_data'");

        if (!$check || $check->num_rows === 0) {
            $conn->query(
                "ALTER TABLE order_figures
                    ADD COLUMN dressup_data LONGTEXT NULL
                    COMMENT 'JSON ng Dress Up design: gender, skin, hair, top, bottom, shoes, colors, 3D model paths'"
            );
        }

    } catch (Throwable $columnError) {
        error_log("submit_order.php could not add dressup_data column: " . $columnError->getMessage());
    }
}


/* #####################################################################
   D. submit_reference_order()   [IMAGE SUBMISSION]
   Image Submission lang: customer ang nag-upload ng reference images,
   staff ang magve-verify at magbibigay ng quotation.
   Status: "pending"
   ##################################################################### */

function submit_reference_order($conn, $userId, string $orderType, string $bookingDate, array $figures): array
{
    /* 0. quantity: isang figure lang kahit may qty 2 pataas */

    $figures = submit_order_check_quantities($figures);

    /* 1. total */

    $totals = submit_order_totals($figures, $orderType);

    /* 2. i-save (orders, figures, images) - status "pending" */

    $status  = "pending";
    $orderId = submit_order_create($conn, $userId, $orderType, "reference", $bookingDate, $figures, $totals, $status);

    submit_order_finish($conn, $orderId, $userId, $status);

    /* 3. abisuhan ang staff, may bagong order na dapat i-verify */

    figurify_notify_staff($conn, $orderId, "New order " . $orderId . " was placed and needs verification.");

    /* 4. abisuhan ang customer (bell + Gmail, sabay) */

    figurify_customer_notice(
        $conn,
        (int) $userId,
        $orderId,
        "We received your order " . $orderId . ". Our staff will review your order details and send your quotation soon.",
        "We received your Order #" . $orderId,
        [
            "We received your <strong>Order #" . $orderId . "</strong>.",
            "We’ll email your quotation soon.",
        ]
    );

    /* 5. sagot sa browser: pupunta sa My Orders */

    return [
        "success"     => true,
        "order_id"    => $orderId,
        "status"      => $status,
        "payment_url" => null,
        "orders_url"  => "../my-order/my-orders.php?order_id=" . $orderId
    ];
}


/* #####################################################################
   E. submit_dressup_order()   [DRESS UP]
   Dress Up lang: kita na ang presyo, kaya diretso na sa bayad.
   Status: "awaiting_payment"
   ##################################################################### */

function submit_dressup_order($conn, $userId, string $orderType, string $bookingDate, array $figures): array
{
    /* 1. ayusin ang lumang GLB paths (para tanggap pa ang lumang draft sa browser) */

    $figures = figurify_dressup_relocate_paths($figures);

    /* 2. kuwentahin ulit ang presyo sa server. Kapag iba sa ipinakita sa customer, hihinto */

    $priceError = figurify_dressup_apply_server_prices($figures);

    if ($priceError !== null) {
        submit_order_fail($priceError);
    }

    /* 3. quantity: isang figure lang kahit may qty 2 pataas, 1 hanggang 10 piraso lang (lahat ng qty pinagsama) */

    $figures = submit_order_check_quantities($figures);

    $pieceCount = array_sum(array_map(function ($figure) { return (int) $figure["quantity"]; }, $figures));

    if ($pieceCount < 1 || $pieceCount > 10) {
        submit_order_fail("A Dress Up order can contain 1 to 10 figures.");
    }

    /* 4. total */

    $totals = submit_order_totals($figures, $orderType);

    /* 5. i-save (orders, figures, design) - status "awaiting_payment" */

    submit_dressup_ensure_column($conn);

    $status  = "awaiting_payment";
    $orderId = submit_order_create($conn, $userId, $orderType, "create_style", $bookingDate, $figures, $totals, $status);

    submit_order_finish($conn, $orderId, $userId, $status);

    /* 6. abisuhan ang customer. Ang staff ay aabisuhan pagka-submit ng payment, hindi dito */

    figurify_customer_notice(
        $conn,
        (int) $userId,
        $orderId,
        "Your Dress Up order " . $orderId . " is waiting for payment. Please complete your payment and shipping details to confirm it.",
        "Your Order #" . $orderId . " is waiting for payment",
        [
            "Your Dress Up <strong>Order #" . $orderId . "</strong> is waiting for payment.",
            "Complete your payment and shipping details in <strong>My Orders</strong>.",
        ]
    );

    /* 7. sagot sa browser: diretso sa bayad at shipping */

    return [
        "success"     => true,
        "order_id"    => $orderId,
        "status"      => $status,
        "payment_url" => "../my-order/payment-shipping.php?order_id=" . $orderId,
        "orders_url"  => "../my-order/my-orders.php?order_id=" . $orderId
    ];
}


/* #####################################################################
   F. MAIN   [COMMON]
   Ito ang unang tumatakbo kapag may nag-submit ng order.
   ##################################################################### */


/* ---------- F1. MGA TSEKE   [COMMON] ---------- */

/* kailangang naka-login */

if (!isset($_SESSION["user_id"])) {
    submit_order_fail("Please log in first before submitting an order.");
}

$user_id = $_SESSION["user_id"];

if (!isset($conn) || $conn->connect_error) {
    submit_order_fail("Could not connect to the database. Please try again later.");
}


/* rate limit: max 5 order submissions kada 10 minuto kada account, laban sa spam */

$waitSeconds = figurify_action_rate_limit_seconds_remaining($conn, "commission_order", "user_" . $user_id, 5, 10);

if ($waitSeconds > 0) {
    submit_order_fail("You've submitted several orders recently. Please wait a bit before submitting another.");
}


/* max 3 order na hindi pa nababayaran nang sabay */

$openStmt = $conn->prepare(
    "SELECT COUNT(*) AS c FROM orders
     WHERE user_id = ? AND status IN ('pending', 'needs_correction', 'quoted', 'awaiting_payment')"
);
$openStmt->bind_param("i", $user_id);
$openStmt->execute();
$openUnpaid = (int) ($openStmt->get_result()->fetch_assoc()["c"] ?? 0);
$openStmt->close();

if ($openUnpaid >= 3) {
    submit_order_fail("You have 3 unpaid orders. Pay or cancel one in My Orders first.");
}


/* existing pa ba ang user? baka luma na ang session pero na-reset na ang DB */

$userStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userExists = $userStmt->get_result()->num_rows === 1;
$userStmt->close();

if (!$userExists) {

    session_unset();
    session_destroy();

    submit_order_fail("Your session has expired. Please log out, log in again, then submit your order.");
}


/* ---------- F2. BASAHIN AT I-VALIDATE ANG IPINADALA NG BROWSER   [COMMON] ---------- */

$order_type   = $_POST["order_type"] ?? "";
$order_method = $_POST["order_method"] ?? "reference";
$ship_out_date = $_POST["ship_out_date"] ?? "";
$figures      = json_decode($_POST["figures"] ?? "[]", true);

if (!in_array($order_type, ["rush", "nonrush"], true)) {
    submit_order_fail("Invalid order type.");
}

/* "reference" ang default kung hindi kilalang value */

if (!in_array($order_method, ["reference", "create_style"], true)) {
    $order_method = "reference";
}

if ($ship_out_date === "" || !strtotime($ship_out_date)) {
    submit_order_fail("Invalid or missing ship out date.");
}

if (!is_array($figures) || count($figures) === 0) {
    submit_order_fail("Please add at least 1 figure to your order.");
}


/* tingnan ulit ang booking limits sa server, hindi puwedeng umasa sa calendar ng JS */

$normalizedBookingDate = date("Y-m-d", strtotime($ship_out_date));

/* lock: isa lang ang makakakuha ng huling slot (nare-release kapag natapos ang request) */

figurify_slot_lock_acquire($conn);

$bookingCheck = ($order_type === "rush")
    ? figurify_check_rush_date($conn, $normalizedBookingDate)
    : figurify_check_nonrush_date($conn, $normalizedBookingDate);

if (!$bookingCheck["ok"]) {
    submit_order_fail($bookingCheck["reason"] ?? "That ship out date is no longer available.");
}


/* ---------- F3. PILIIN KUNG ALIN ANG GAGAMITIN   [COMMON] ----------
   order_method = "create_style" -> Dress Up           (E. submit_dressup_order)
   order_method = "reference"    -> Image Submission   (D. submit_reference_order) */

try {

    if ($order_method === "create_style") {
        $response = submit_dressup_order($conn, $user_id, $order_type, $ship_out_date, $figures);
    } else {
        $response = submit_reference_order($conn, $user_id, $order_type, $ship_out_date, $figures);
    }

    /* pop up pagkatapos mag-submit: lalabas sa susunod na page (My Orders o Payment & Shipping) */

    if (!empty($response["success"])) {
        /* parehong numero ng nasa My Orders (pang-ilang order ng customer, hindi ang order_id sa database) */
        $seqStmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $seqStmt->bind_param("i", $user_id);
        $seqStmt->execute();
        $orderNumber = (int) ($seqStmt->get_result()->fetch_row()[0] ?? $response["order_id"]);
        $seqStmt->close();

        figurify_flash(
            $order_method === "create_style"
                ? "Order #" . $orderNumber . " saved! Complete your payment and shipping details to confirm it."
                : "Order #" . $orderNumber . " submitted! We will send your quotation soon.",
            "success"
        );

        /* bagong order marker: ang lumang napili sa browser (lahat ng tab) ay mabubura sa susunod na page (Shared/navbar.php) */
        $_SESSION["order_owner"] = bin2hex(random_bytes(8));
    }

    echo json_encode($response);

} catch (Throwable $e) {

    /* may pumalya - i-undo ang lahat ng na-save sa transaction */

    $conn->rollback();

    error_log("submit_order.php exception: " . $e->getMessage());

    $response = [
        "success" => false,
        "message" => "Something went wrong while saving your order. Please try again."
    ];

    /* ipakita ang totoong dahilan kapag localhost/XAMPP lang, nakatago sa live server */

    $serverAddr = $_SERVER["SERVER_ADDR"] ?? "";
    $serverName = $_SERVER["SERVER_NAME"] ?? "";

    if (in_array($serverAddr, ["127.0.0.1", "::1"], true) || in_array($serverName, ["localhost", "127.0.0.1"], true)) {
        $response["debug_reason"] = $e->getMessage();
    }

    echo json_encode($response);
}
/* ====================== END SUBMIT ORDER ====================== */
