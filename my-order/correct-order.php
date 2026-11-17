<?php
/* ==========================================================================
   FIX MY ORDER PAGE   (my-order/correct-order.php)
   Style: my-order/correct-order.css      Script: nasa dulo ng file na ito ([3] SCRIPT)
   Bukas lang kapag "needs_correction" ang order (ibinalik ng staff o owner mula sa
   Quotation page). Isinusumite sa submit_correction.php; babalik sa Pending ang order.

   LAMAN (taas pababa):
     [1] SETUP + DATA   helpers, session, order ng customer, mga figure at larawan, dahilan
     [2] HTML           navbar, page hero, form (bawat figure), footer
     [3] SCRIPT         bilang ng larawan (1 hanggang 5 bawat figure), submit
   ========================================================================== */


/* ==========================================================================
   [1] SETUP + DATA
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_helper.php";

/* customer lang ang puwede */

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "customer") {
    header("Location: ../Home/Home.php?auth=login");
    exit();
}

$user_id  = (int) $_SESSION["user_id"];
$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;

const FIX_MAX_IMAGES_PER_FIGURE = 5;


/* order ng customer na ito lang */

$stmt = $conn->prepare("SELECT order_id, status, order_method FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $conn->close();
    header("Location: my-orders.php?notice=order_not_found");
    exit();
}

/* naipasa na ulit o hindi na kailangang ayusin -> balik sa order */

if (figurify_status_key($order["status"]) !== "needs_correction") {
    $conn->close();
    header("Location: my-orders.php?order_id=" . $order_id);
    exit();
}


/* mga figure at ang kani-kanilang reference images */

$stmt = $conn->prepare(
    "SELECT figure_id, figure_style, product_type, size_label, figure_name, notes
     FROM order_figures
     WHERE order_id = ?
     ORDER BY figure_id"
);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$figures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$imageStmt = $conn->prepare("SELECT image_id, image_path FROM order_figure_images WHERE figure_id = ? ORDER BY image_id");

foreach ($figures as &$figure) {
    $figureId = (int) $figure["figure_id"];
    $imageStmt->bind_param("i", $figureId);
    $imageStmt->execute();
    $figure["images"] = $imageStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
unset($figure);

$imageStmt->close();

$correctionReason = figurify_wf_latest_reason($conn, $order_id, "STAFF_CORRECTION_REQUIRED");

$conn->close();

function fix_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
/* ====================== END SETUP + DATA ====================== */


/* ==========================================================================
   [2] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fix My Order | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="correct-order.css">
</head>

<body>

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->


<!-- ====================== PAGE HERO (css: correct-order.css [2]) ====================== -->
<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">ORDER #<?php echo (int) $order_id; ?></span>
        <h1>Fix My Order</h1>
        <p>Fix the details we asked for, then send your order back. We will check it again.</p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->


<!-- ====================== FIX FORM (css: correct-order.css [3]-[6]) ====================== -->
<main class="fix-page">

    <div class="fix-card">

        <form
            method="POST"
            action="submit_correction.php"
            class="fix-form"
            enctype="multipart/form-data"
            onsubmit="return submitCorrection(this)"
        >

            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">

            <div class="fix-card-header">
                <h2>What to correct</h2>
                <p>You can replace or add reference images and update the notes of each figure.</p>
            </div>

            <div class="fix-reason">
                <strong>Message from our staff</strong>
                <?php echo nl2br(fix_e($correctionReason ?: "Please review your order details.")); ?>
            </div>

            <?php foreach ($figures as $index => $figure): ?>
                <?php $figureId = (int) $figure["figure_id"]; ?>

                <!-- ===== FIGURE <?php echo $index + 1; ?> ===== -->
                <section class="fix-figure" data-figure="<?php echo $figureId; ?>">

                    <div>
                        <div class="fix-figure-title">Figure <?php echo $index + 1; ?></div>
                        <div class="fix-figure-meta">
                            <?php echo fix_e(ucwords(str_replace("_", " ", (string) $figure["figure_style"]))); ?>
                            · <?php echo fix_e(ucwords(str_replace("_", " ", (string) $figure["product_type"]))); ?>
                            <?php if (!empty($figure["size_label"])): ?> · <?php echo fix_e($figure["size_label"]); ?><?php endif; ?>
                        </div>
                    </div>

                    <!-- ===== REFERENCE IMAGES (dropzone gaya ng Image Submission) - css: correct-order.css [4] ===== -->
                    <div class="fix-group">
                        <label>
                            Reference images
                            <span class="fix-limit">(Maximum <?php echo FIX_MAX_IMAGES_PER_FIGURE; ?> images)</span>
                        </label>

                        <div class="fix-dropzone" data-dropzone>

                            <input
                                type="file"
                                class="fix-file-input"
                                name="new_images[<?php echo $figureId; ?>][]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                            >

                            <span class="fix-count" data-count>0 / <?php echo FIX_MAX_IMAGES_PER_FIGURE; ?></span>

                            <div class="fix-preview-wrap">
                                <div class="fix-drag-text" data-drag-text>Drag and drop your images here</div>

                                <div class="fix-preview-grid" data-grid>
                                    <?php foreach ($figure["images"] as $image): ?>
                                        <div class="fix-image">
                                            <img src="../<?php echo fix_e($image["image_path"]); ?>" alt="Reference image">
                                            <input type="checkbox" name="remove_images[]" value="<?php echo (int) $image["image_id"]; ?>" hidden>
                                            <button type="button" class="fix-image-delete" onclick="removeCurrentImage(this)" aria-label="Remove image"></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>

                        <span class="fix-hint">Each figure can have 1 to <?php echo FIX_MAX_IMAGES_PER_FIGURE; ?> reference images (JPG, PNG or WEBP, max 5MB each).</span>
                    </div>
                    <!-- ===== END REFERENCE IMAGES ===== -->

                    <div class="fix-group">
                        <label for="figureNotes<?php echo $figureId; ?>">Notes for this figure</label>
                        <textarea
                            id="figureNotes<?php echo $figureId; ?>"
                            name="figure_notes[<?php echo $figureId; ?>]"
                            maxlength="2000"
                            placeholder="Describe the design details…"
                        ><?php echo fix_e($figure["notes"] ?? ""); ?></textarea>
                    </div>

                </section>
                <!-- ===== END FIGURE <?php echo $index + 1; ?> ===== -->

            <?php endforeach; ?>

            <div class="fix-group">
                <label for="correctionNote">Message to our staff (optional)</label>
                <textarea
                    id="correctionNote"
                    name="correction_note"
                    maxlength="1000"
                    placeholder="Tell us what you changed…"
                ></textarea>
            </div>

            <div class="fix-actions">
                <button type="submit" class="btn-submit">Send Back to Staff</button>
                <a href="my-orders.php?order_id=<?php echo (int) $order_id; ?>" class="btn-back">Back to My Orders</a>
            </div>

        </form>

    </div>

</main>
<!-- ====================== END FIX FORM ====================== -->


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== [3] SCRIPT ====================== -->
<script>

var FIX_MAX_IMAGES = <?php echo FIX_MAX_IMAGES_PER_FIGURE; ?>;

/* ==========================================================================
   [3a] LARAWAN  (dropzone: click o drag and drop; X para tanggalin; bilang na "n / 5")
   Ang bagong larawan ay nasa zone.fixFiles at ini-sync sa input para sumama sa form.
   ========================================================================== */
var FIX_MAX_BYTES = 5 * 1024 * 1024;
var FIX_ALLOWED = ["image/jpeg", "image/png", "image/webp"];

function fixNotice(message) {
    if (window.csToast) {
        csToast(message, "error");
    } else {
        alert(message);
    }
}

// ilang larawan ang meron ngayon sa figure na ito (kasalukuyan na hindi tinanggal + bago)
function fixCountImages(zone) {
    var current = zone.querySelectorAll(".fix-image:not(.is-removed)").length;

    return current + zone.fixFiles.length;
}

// ilagay ulit sa input ang listahan ng bagong larawan, at i-update ang preview at bilang
function fixRefresh(zone) {
    var input    = zone.querySelector(".fix-file-input");
    var grid     = zone.querySelector("[data-grid]");
    var transfer = new DataTransfer();

    zone.fixFiles.forEach(function (file) { transfer.items.add(file); });
    input.files = transfer.files;

    grid.querySelectorAll(".fix-new").forEach(function (card) { card.remove(); });

    zone.fixFiles.forEach(function (file, position) {
        var card   = document.createElement("div");
        var image  = document.createElement("img");
        var remove = document.createElement("button");

        card.className = "fix-image fix-new";
        image.src = URL.createObjectURL(file);
        image.alt = "New reference image";
        remove.type = "button";
        remove.className = "fix-image-delete";
        remove.setAttribute("aria-label", "Remove image");
        remove.onclick = function (event) {
            event.stopPropagation();
            zone.fixFiles.splice(position, 1);
            fixRefresh(zone);
        };

        card.appendChild(image);
        card.appendChild(remove);
        grid.appendChild(card);
    });

    var total = fixCountImages(zone);

    zone.querySelector("[data-count]").textContent = total + " / " + FIX_MAX_IMAGES;
    zone.querySelector("[data-drag-text]").hidden = total > 0;
}

function fixAddFiles(zone, files) {
    Array.prototype.forEach.call(files, function (file) {
        if (FIX_ALLOWED.indexOf(file.type) === -1) {
            fixNotice("Only JPG, PNG or WEBP images are allowed.");
        } else if (file.size > FIX_MAX_BYTES) {
            fixNotice(file.name + " is more than 5MB.");
        } else if (fixCountImages(zone) >= FIX_MAX_IMAGES) {
            fixNotice("Maximum of " + FIX_MAX_IMAGES + " images per figure.");
        } else {
            zone.fixFiles.push(file);
        }
    });

    fixRefresh(zone);
}

// X sa kasalukuyang larawan: ituturing na tinanggal (ipapadala sa remove_images[])
function removeCurrentImage(button) {
    var card = button.closest(".fix-image");
    var zone = button.closest("[data-dropzone]");

    card.classList.add("is-removed");
    card.querySelector("input[type=checkbox]").checked = true;
    fixRefresh(zone);
}

document.querySelectorAll("[data-dropzone]").forEach(function (zone) {
    var input = zone.querySelector(".fix-file-input");

    zone.fixFiles = [];
    fixRefresh(zone);

    zone.addEventListener("click", function (event) {
        if (event.target !== input && !event.target.closest(".fix-image-delete")) {
            input.click();
        }
    });

    input.addEventListener("change", function () {
        fixAddFiles(zone, input.files);
    });

    zone.addEventListener("dragover", function (event) {
        event.preventDefault();
        zone.classList.add("dragover");
    });

    zone.addEventListener("dragleave", function () {
        zone.classList.remove("dragover");
    });

    zone.addEventListener("drop", function (event) {
        event.preventDefault();
        zone.classList.remove("dragover");
        fixAddFiles(zone, event.dataTransfer.files);
    });
});
/* ====================== END LARAWAN ====================== */


/* ==========================================================================
   [3b] SUBMIT  (1 hanggang 5 larawan bawat figure, confirm muna, isang beses lang)
   ========================================================================== */
function submitCorrection(form) {

    if (form.dataset.submitting) {
        return false;
    }

    var blocks = form.querySelectorAll(".fix-figure");

    for (var i = 0; i < blocks.length; i++) {

        var count = fixCountImages(blocks[i].querySelector("[data-dropzone]"));

        if (count < 1 || count > FIX_MAX_IMAGES) {
            if (window.csToast) {
                csToast("Figure " + (i + 1) + " needs 1 to " + FIX_MAX_IMAGES + " reference images.", "error");
            } else {
                alert("Figure " + (i + 1) + " needs 1 to " + FIX_MAX_IMAGES + " reference images.");
            }
            return false;
        }
    }

    if (window.csConfirmSubmit && !csConfirmSubmit(form, "Send your order back?\nOur staff will review it again.", { okText: "Send", cancelText: "Back" })) {
        return false;
    }

    form.dataset.submitting = "1";

    var button = form.querySelector("button[type=submit]");

    if (button) {
        button.disabled = true;
        button.textContent = "Sending…";
    }

    return true;
}
/* ====================== END SUBMIT ====================== */
</script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
