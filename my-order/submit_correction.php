<?php
/* ==========================================================================
   SUBMIT CORRECTION (customer)   (my-order/submit_correction.php)
   POST galing sa correct-order.php: bagong notes, bagong reference images, at mga
   larawang tatanggalin. NEEDS CORRECTION -> PENDING (babalik sa Quotation ng staff).
   Bawat figure ay dapat may 1 hanggang 5 reference image pagkatapos ng pagbabago.
   ========================================================================== */

require_once __DIR__ . "/_customer_bootstrap.php";

const FIX_MAX_IMAGES_PER_FIGURE = 5;

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$backUrl  = "correct-order.php?order_id=" . $order_id;

figurify_wf_require_post_csrf($conn, $backUrl, $order_id);


/* ===== 1. PUWEDE PA BA? (bago mag-upload, para walang naiiwang file) ===== */
$can = figurify_wf_can($conn, $order_id, "submit_correction");

if (!$can["ok"]) {
    figurify_wf_perform($conn, $order_id, "submit_correction");
    figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, $can["error"], "error");
}


/* ===== 2. MGA FIGURE AT LARAWAN NG ORDER NA ITO LANG ===== */
$stmt = $conn->prepare(
    "SELECT f.figure_id
     FROM order_figures f
     JOIN orders o ON o.order_id = f.order_id
     WHERE f.order_id = ? AND o.user_id = ?"
);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$figureIds = array_map("intval", array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "figure_id"));
$stmt->close();

$imagesByFigure = [];   // figure_id => [image_id => image_path]

if ($figureIds) {
    $placeholders = implode(",", array_fill(0, count($figureIds), "?"));
    $stmt = $conn->prepare("SELECT image_id, figure_id, image_path FROM order_figure_images WHERE figure_id IN ($placeholders)");
    $stmt->bind_param(str_repeat("i", count($figureIds)), ...$figureIds);
    $stmt->execute();

    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $imagesByFigure[(int) $row["figure_id"]][(int) $row["image_id"]] = $row["image_path"];
    }

    $stmt->close();
}


/* ===== 3. TATANGGALING LARAWAN (galing lang sa order na ito) ===== */
$removeIds   = array_map("intval", is_array($_POST["remove_images"] ?? null) ? $_POST["remove_images"] : []);
$removePaths = [];
$keptCount   = [];

foreach ($figureIds as $figureId) {
    $keptCount[$figureId] = 0;

    foreach (($imagesByFigure[$figureId] ?? []) as $imageId => $path) {
        if (in_array($imageId, $removeIds, true)) {
            $removePaths[$imageId] = $path;
        } else {
            $keptCount[$figureId]++;
        }
    }
}


/* ===== 4. BAGONG LARAWAN (1 hanggang 5 bawat figure) ===== */
$files     = $_FILES["new_images"] ?? null;
$newImages = [];

foreach ($figureIds as $figureId) {

    $added = 0;

    if ($files && isset($files["name"][$figureId]) && is_array($files["name"][$figureId])) {

        foreach ($files["name"][$figureId] as $i => $name) {

            if (($files["error"][$figureId][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($keptCount[$figureId] + $added >= FIX_MAX_IMAGES_PER_FIGURE) {
                break;
            }

            $path = figurify_customer_save_image([
                "name"     => $name,
                "type"     => $files["type"][$figureId][$i] ?? "",
                "tmp_name" => $files["tmp_name"][$figureId][$i] ?? "",
                "error"    => $files["error"][$figureId][$i] ?? UPLOAD_ERR_NO_FILE,
                "size"     => $files["size"][$figureId][$i] ?? 0,
            ], "uploads/orders/" . $order_id, "fig" . $figureId . "_fix");

            if ($path === null) {
                foreach ($newImages as $saved) {
                    figurify_customer_delete_upload($saved["path"]);
                }
                figurify_customer_done($conn, $backUrl, "Please upload valid images only (JPG, PNG or WEBP, max 5MB each).", "error");
            }

            $newImages[] = ["figure_id" => $figureId, "path" => $path];
            $added++;
        }
    }

    if ($keptCount[$figureId] + $added < 1) {
        foreach ($newImages as $saved) {
            figurify_customer_delete_upload($saved["path"]);
        }
        figurify_customer_done($conn, $backUrl, "Each figure needs at least one reference image.", "error");
    }
}


/* ===== 5. I-SAVE SA WORKFLOW (NEEDS CORRECTION -> PENDING) ===== */
$result = figurify_wf_perform($conn, $order_id, "submit_correction", [
    "note"          => (string) ($_POST["correction_note"] ?? ""),
    "figure_notes"  => is_array($_POST["figure_notes"] ?? null) ? $_POST["figure_notes"] : [],
    "new_images"    => $newImages,
    "remove_images" => array_keys($removePaths),
]);

if (!$result["ok"]) {
    foreach ($newImages as $saved) {
        figurify_customer_delete_upload($saved["path"]);
    }
    figurify_customer_done($conn, $backUrl, $result["error"], "error");
}

/* natanggal na sa database -> burahin na rin ang file */
foreach ($removePaths as $path) {
    figurify_customer_delete_upload($path);
}

figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " was corrected by the customer and is back in Pending for quotation.");

figurify_customer_done($conn, "my-orders.php?order_id=" . $order_id, "Thank you! Your order was sent back to our staff for review.");
/* ====================== END SUBMIT CORRECTION (customer) ====================== */
