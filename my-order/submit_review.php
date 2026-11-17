<?php
/* ==========================================================================
   LEAVE A REVIEW PAGE   (my-order/submit_review.php)
   Style: my-order/submit_review.css      Script: nasa dulo ng file na ito ([4] SCRIPT)
   Puwedeng galing sa My Orders (naka-login) o sa email link (walang login,
   token lang). Isang review lang kada order; read-only na kapag may naisumite.

   LAMAN (taas pababa):
     [1] SETUP + CHECK   helpers, session, database, i-check ang order_id + token
     [2] FORM SAVE       i-save ang rating, text at photos (POST)
     [3] HTML            navbar, card (error / naisumiteng review / form), footer
     [4] SCRIPT          preview ng napiling photos (hanggang 5)
   ========================================================================== */


/* ==========================================================================
   [1] SETUP + CHECK
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_helper.php";
require_once __DIR__ . "/../helpers/messaging_helper.php";


/* read + validate order_id / token */

$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : (int) ($_POST["order_id"] ?? 0);
$token    = trim($_GET["token"] ?? $_POST["token"] ?? "");

$pageError = "";
$order     = null;

if ($order_id <= 0 || $token === "") {

    $pageError = "This review link is invalid.";

} else {

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.user_id, o.status, o.review_token,
                u.full_name
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE o.order_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || empty($order["review_token"]) || !hash_equals($order["review_token"], $token)) {

        $pageError = "This review link is invalid or has expired.";
        $order     = null;

    } elseif (strtolower(trim((string) $order["status"])) !== "completed") {

        $pageError = "Reviews can only be left once your order is completed.";
        $order     = null;

    } elseif (isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] !== (int) $order["user_id"]) {

        // Naka-login sa ibang account - hindi sa kanya ang order na ito.
        $pageError = "This review link isn't for your account.";
        $order     = null;

    }

}


/* kung meron nang naisumiteng review, read-only na lang ipapakita, walang form */

$existingReview = $order ? figurify_get_review($conn, $order_id) : null;

$formError   = "";
$justSubmitted = isset($_GET["done"]) && $_GET["done"] === "1";

// bituing pinili sa Gmail / Notifications (?rating=1-5) - naka-check na pagbukas ng form
$preRating = isset($_GET["rating"]) ? (int) $_GET["rating"] : (int) ($_POST["rating"] ?? 0);
$preRating = ($preRating >= 1 && $preRating <= 5) ? $preRating : 0;


/* ====================== END SETUP + CHECK ====================== */


/* ==========================================================================
   [2] FORM SAVE
   ========================================================================== */

if ($order && !$existingReview && $_SERVER["REQUEST_METHOD"] === "POST") {

    $rating      = isset($_POST["rating"]) ? (int) $_POST["rating"] : 0;
    $reviewText  = figurify_db_text(trim($_POST["review_text"] ?? ""));

    if ($rating < 1 || $rating > 5) {
        $formError = "Please choose a star rating.";
    }

    // Logged in as the order's own customer = "system", galing sa Gmail link = "email"
    $submittedVia = (isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] === (int) $order["user_id"])
        ? "system"
        : "email";

    $savedImagePaths = [];

    if ($formError === "" && !empty($_FILES["review_images"]["name"][0])) {

        $allowedExt  = ["jpg", "jpeg", "png", "gif", "webp"];
        $uploadDir   = __DIR__ . "/../uploads/orders/" . $order_id . "/reviews/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileCount = count($_FILES["review_images"]["name"]);

        for ($i = 0; $i < $fileCount && $i < 5; $i++) {

            if ($_FILES["review_images"]["error"][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo($_FILES["review_images"]["name"][$i], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true) || !figurify_is_real_image($_FILES["review_images"]["tmp_name"][$i])) {
                continue;
            }

            $safeName = "review_" . $order_id . "_" . time() . "_" . random_int(1000, 9999) . "_" . $i . "." . $ext;
            $destPath = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES["review_images"]["tmp_name"][$i], $destPath)) {
                $savedImagePaths[] = "uploads/orders/" . $order_id . "/reviews/" . $safeName;
            }

        }

    }

    if ($formError === "") {

        // Huling check bago mag-INSERT, sakaling nag-race (sabay-sabay submit sa Gmail at My Orders)
        $recheck = figurify_get_review($conn, $order_id);

        if ($recheck) {

            $existingReview = $recheck;

        } else {

            $insertStmt = $conn->prepare(
                "INSERT INTO reviews (order_id, user_id, rating, review_text, submitted_via)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insertStmt->bind_param(
                "iiiss",
                $order_id,
                $order["user_id"],
                $rating,
                $reviewText,
                $submittedVia
            );

            try {

                $insertStmt->execute();
                $reviewId = $insertStmt->insert_id;
                $insertStmt->close();

                foreach ($savedImagePaths as $imgPath) {

                    $imgStmt = $conn->prepare(
                        "INSERT INTO review_images (review_id, image_path) VALUES (?, ?)"
                    );
                    $imgStmt->bind_param("is", $reviewId, $imgPath);
                    $imgStmt->execute();
                    $imgStmt->close();

                }


                /* notify owner + staff - bagong review na naiwan ng customer */

                figurify_notify_staff(
                    $conn,
                    $order_id,
                    "A customer left a " . $rating . "-star review for order " . $order_id . "."
                );

                header(
                    "Location: submit_review.php?order_id=" . $order_id
                    . "&token=" . urlencode($token) . "&done=1"
                );
                exit();

            } catch (\Throwable $e) {

                // Malamang parehong nagsubmit sabay-sabay - ipakita na lang yung nauna
                $existingReview = figurify_get_review($conn, $order_id);

            }

        }

    }

}

$conn->close();
/* ====================== END FORM SAVE ====================== */


/* ==========================================================================
   [3] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave a Review | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="submit_review.css">
</head>

<body class="page-review">

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->

<?php $backHref = isset($_SESSION["user_id"]) ? "my-orders.php?order_id=" . (int) $order_id : "../Home/Home.php"; ?>
<?php $backText = isset($_SESSION["user_id"]) ? "Back to My Orders" : "Go to Home"; ?>

<!-- ====================== REVIEW CARD (css: submit_review.css [2]-[5]) ====================== -->
<main class="review-page">
<div class="card">

    <?php if ($pageError !== ""): ?>

        <h1>Leave a Review</h1>
        <div class="error-box"><?php echo htmlspecialchars($pageError); ?></div>
        <div class="form-actions"><a class="btn-back" href="<?php echo htmlspecialchars($backHref); ?>"><?php echo $backText; ?></a></div>

    <?php elseif ($existingReview): ?>

        <h1>Your Review</h1>
        <div class="sub">Order #<?php echo (int) $order_id; ?>. Thank you for your feedback!</div>

        <?php if ($justSubmitted) { echo figurify_toast_now("Thanks for your review!", "success"); } ?>

        <div class="review-stars-readonly">
            <?php
            $rating = (int) $existingReview["rating"];
            echo str_repeat("★", $rating) . str_repeat("☆", 5 - $rating);
            ?>
        </div>

        <?php if (!empty($existingReview["review_text"])): ?>
            <div class="review-text-readonly"><?php echo nl2br(htmlspecialchars($existingReview["review_text"])); ?></div>
        <?php endif; ?>

        <?php if (!empty($existingReview["images"])): ?>
            <div class="review-images-readonly">
                <?php foreach ($existingReview["images"] as $imgPath): ?>
                    <img src="../<?php echo htmlspecialchars($imgPath); ?>" alt="Review photo">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="meta">
            Submitted <?php echo date("F j, Y, g:i A", strtotime($existingReview["created_at"])); ?>.
            You can only submit one review per order.
        </p>

        <div class="form-actions"><a class="btn-back" href="<?php echo htmlspecialchars($backHref); ?>"><?php echo $backText; ?></a></div>

    <?php else: ?>

        <?php if ($formError !== "") { echo figurify_toast_now($formError, "error"); } ?>

        <form method="POST" enctype="multipart/form-data" action="submit_review.php">
            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <label class="field-label">Your Rating</label>
            <div class="stars">
                <input type="radio" name="rating" id="star5" value="5"<?php echo $preRating === 5 ? " checked" : ""; ?>><label for="star5">★</label>
                <input type="radio" name="rating" id="star4" value="4"<?php echo $preRating === 4 ? " checked" : ""; ?>><label for="star4">★</label>
                <input type="radio" name="rating" id="star3" value="3"<?php echo $preRating === 3 ? " checked" : ""; ?>><label for="star3">★</label>
                <input type="radio" name="rating" id="star2" value="2"<?php echo $preRating === 2 ? " checked" : ""; ?>><label for="star2">★</label>
                <input type="radio" name="rating" id="star1" value="1"<?php echo $preRating === 1 ? " checked" : ""; ?>><label for="star1">★</label>
            </div>

            <label class="field-label" for="review_text">Your Review</label>
            <textarea name="review_text" id="review_text" placeholder="Tell us about your experience..."></textarea>

            <label class="field-label">Add Photos (optional)</label>
            <input type="file" class="photo-input" name="review_images[]" id="review_images" accept="image/*" multiple>
            <!-- iisang box: dito rin makikita ang napiling photos (X = tanggalin, nasa loob ng larawan) -->
            <div class="photo-drop">
                <label class="photo-drop-empty" for="review_images" id="photoDropEmpty">
                    <span class="photo-drop-text">Click to add photos of your figure</span>
                    <small>Up to 5 photos (JPG, PNG, GIF, or WEBP)</small>
                </label>
                <div class="photo-preview" id="photoPreview"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">Submit Review</button>
            </div>
        </form>


    <?php endif; ?>

</div>
</main>
<!-- ====================== END REVIEW CARD ====================== -->


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== [4] SCRIPT  (preview ng photos) ====================== -->
<script>
(function () {

    const input   = document.getElementById("review_images");
    const preview = document.getElementById("photoPreview");
    const empty   = document.getElementById("photoDropEmpty");

    // nasa form lang ito (wala kapag error o naisumite na ang review)
    if (!input || !preview) {
        return;
    }

    let files = [];

    // ilagay ulit sa file input ang napiling photos, tapos ipakita ang preview
    function sync() {
        const transfer = new DataTransfer();
        files.forEach(function (file) { transfer.items.add(file); });
        input.files = transfer.files;

        preview.innerHTML = "";

        files.forEach(function (file, index) {
            const figure = document.createElement("figure");
            const image  = document.createElement("img");
            image.src = URL.createObjectURL(file);
            image.alt = "Selected photo " + (index + 1);

            const removeButton = document.createElement("button");
            removeButton.type = "button";
            removeButton.title = "Remove";
            removeButton.textContent = "×";
            removeButton.addEventListener("click", function () { files.splice(index, 1); sync(); });

            figure.appendChild(image);
            figure.appendChild(removeButton);
            preview.appendChild(figure);
        });

        // may photo na: nakatago ang "Click to add", may maliit na "+ Add" box kung kulang pa sa 5
        empty.hidden = files.length > 0;

        if (files.length > 0 && files.length < 5) {
            const addMore = document.createElement("label");
            addMore.className = "photo-add";
            addMore.htmlFor = "review_images";
            addMore.textContent = "+ Add";
            preview.appendChild(addMore);
        }
    }

    // hanggang 5 photo, larawan lang
    input.addEventListener("change", function () {
        Array.prototype.forEach.call(input.files, function (file) {
            if (files.length < 5 && file.type.indexOf("image/") === 0) { files.push(file); }
        });
        sync();
    });

})();
</script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
