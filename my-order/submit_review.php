<?php

/* leave a review page - pwede galing My Orders (naka-login) or sa email link
   (walang login, token na lang). one review per order lang, read-only na
   pag may nasumite na. */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/review_helper.php";
require_once __DIR__ . "/../helpers/upload_helper.php";
require_once __DIR__ . "/../helpers/notification_helper.php";


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


/* handle submit */

if ($order && !$existingReview && $_SERVER["REQUEST_METHOD"] === "POST") {

    $rating      = isset($_POST["rating"]) ? (int) $_POST["rating"] : 0;
    $reviewText  = trim($_POST["review_text"] ?? "");

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
<link rel="icon" type="image/png" href="../Image/brand/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave a Review — Clay and Stuff</title>
<link rel="stylesheet" href="submit_review.css?v=2">
</head>
<body class="page-review">

<?php include __DIR__ . "/../Shared/navbar.php"; ?>

<?php $backHref = isset($_SESSION["user_id"]) ? "my-orders.php?order_id=" . (int) $order_id : "../Home/Home.php"; ?>
<?php $backText = isset($_SESSION["user_id"]) ? "Back to My Orders" : "Go to Home"; ?>

<main class="review-page">
<div class="card">

    <?php if ($pageError !== ""): ?>

        <h1>Leave a Review</h1>
        <div class="error-box"><?php echo htmlspecialchars($pageError); ?></div>
        <div class="form-actions"><a class="btn-back" href="<?php echo htmlspecialchars($backHref); ?>"><?php echo $backText; ?></a></div>

    <?php elseif ($existingReview): ?>

        <h1>Your Review</h1>
        <div class="sub">Order #<?php echo (int) $order_id; ?> — thank you for your feedback!</div>

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
            Submitted <?php echo date("F j, Y — g:i A", strtotime($existingReview["created_at"])); ?>.
            You can only submit one review per order.
        </p>

        <div class="form-actions"><a class="btn-back" href="<?php echo htmlspecialchars($backHref); ?>"><?php echo $backText; ?></a></div>

    <?php else: ?>

        <h1>Leave a Review</h1>
        <div class="sub">Order #<?php echo (int) $order_id; ?> — how was your figure?</div>

        <?php if ($formError !== "") { echo figurify_toast_now($formError, "error"); } ?>

        <form method="POST" enctype="multipart/form-data" action="submit_review.php">
            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <label class="field-label">Your Rating</label>
            <div class="stars">
                <input type="radio" name="rating" id="star5" value="5"><label for="star5">★</label>
                <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
                <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
                <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
                <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
            </div>

            <label class="field-label" for="review_text">Your Review</label>
            <textarea name="review_text" id="review_text" placeholder="Tell us about your experience..."></textarea>

            <label class="field-label">Add Photos (optional)</label>
            <input type="file" class="photo-input" name="review_images[]" id="review_images" accept="image/*" multiple>
            <label class="photo-drop" for="review_images">
                <span class="photo-drop-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h1.6l1.4-2h5l1.4 2h1.6A2.5 2.5 0 0 1 20 8.5v8a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5Z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
                </span>
                <span class="photo-drop-text">Add photos of your figure</span>
                <small>Up to 5 photos — JPG, PNG, GIF, or WEBP</small>
            </label>
            <div class="photo-preview" id="photoPreview"></div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">Submit Review</button>
            </div>
        </form>

        <script>
        (function () {
            var input = document.getElementById("review_images");
            var preview = document.getElementById("photoPreview");
            var files = [];

            function sync() {
                var dt = new DataTransfer();
                files.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;

                preview.innerHTML = "";
                files.forEach(function (f, i) {
                    var fig = document.createElement("figure");
                    var img = document.createElement("img");
                    img.src = URL.createObjectURL(f);
                    img.alt = "Selected photo " + (i + 1);
                    var rm = document.createElement("button");
                    rm.type = "button";
                    rm.title = "Remove";
                    rm.textContent = "×";
                    rm.addEventListener("click", function () { files.splice(i, 1); sync(); });
                    fig.appendChild(img);
                    fig.appendChild(rm);
                    preview.appendChild(fig);
                });
            }

            input.addEventListener("change", function () {
                Array.prototype.forEach.call(input.files, function (f) {
                    if (files.length < 5 && f.type.indexOf("image/") === 0) { files.push(f); }
                });
                sync();
            });
        })();
        </script>

    <?php endif; ?>

</div>
</main>

<?php include __DIR__ . "/../Shared/footer.php"; ?>

</body>
</html>
