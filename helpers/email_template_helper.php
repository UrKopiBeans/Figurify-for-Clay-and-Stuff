<?php

/* Lahat ng Gmail na pinapadala sa customer.
   Simpleng email lang: "Hello <pangalan>," + mga talata + "Best regards, Clay and Stuff Team".
   Maikli ang notification sa system, mas buo ang nasa Gmail.

   Gamit:
     figurify_customer_notice($conn, $userId, $orderId, "maikling mensahe", "Subject", ["talata 1", "talata 2"]); */

require_once __DIR__ . "/mailer_helper.php";

if (!function_exists("figurify_email_e")) {

    function figurify_email_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }


    /* karaniwang pangwakas na linya ng halos lahat ng email */

    function figurify_email_status_line(): string
    {
        return "You can check your order status anytime by logging in to your Clay and Stuff account and visiting the "
            . "<strong>My Orders</strong> page. You can also view this notification on your <strong>Notifications</strong> page.";
    }


    /* simpleng email: Hello + mga talata + pirma (walang container) */

    function figurify_email_plain_layout(string $customerName, array $paragraphs): string
    {
        $name = figurify_email_e(trim($customerName) !== "" ? trim($customerName) : "there");

        $html = '<div style="font-family:Plus Jakarta Sans,Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#222222;">';
        $html .= '<p style="margin:0 0 14px;">Hello ' . $name . ',</p>';

        foreach ($paragraphs as $paragraph) {
            if ($paragraph === null || $paragraph === "") {
                continue;
            }
            $html .= (strpos($paragraph, "<img") === 0)
                ? $paragraph
                : '<p style="margin:0 0 14px;">' . $paragraph . '</p>';
        }

        $html .= '<p style="margin:18px 0 0;">Best regards,<br><strong>Clay and Stuff Team</strong></p>';
        $html .= '</div>';

        return $html;
    }


    /* plain-text na bersyon (para sa email app na walang HTML) */

    function figurify_email_plain_text(string $customerName, array $paragraphs): string
    {
        $lines = ["Hello " . (trim($customerName) !== "" ? trim($customerName) : "there") . ","];

        foreach ($paragraphs as $paragraph) {
            if ($paragraph === null || $paragraph === "" || strpos($paragraph, "<img") === 0) {
                continue;
            }
            $lines[] = html_entity_decode(strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $paragraph)), ENT_QUOTES, "UTF-8");
        }

        $lines[] = "Best regards,\nClay and Stuff Team";

        return implode("\n\n", $lines);
    }


    /* kinukuha ang pangalan/email ng customer saka ipinapadala */

    function figurify_email_customer(
        mysqli $conn,
        int $userId,
        string $subject,
        array $paragraphs,
        array $embeddedImages = []
    ): bool {
        try {
            $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");

            if (!$stmt) {
                return false;
            }

            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $customer = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (Throwable $error) {
            return false;
        }

        if (!$customer || empty($customer["email"])) {
            return false;
        }

        $name = (string) ($customer["full_name"] ?? "");

        return figurify_send_mail(
            (string) $customer["email"],
            $name,
            $subject,
            figurify_email_plain_layout($name, $paragraphs),
            figurify_email_plain_text($name, $paragraphs),
            $embeddedImages
        );
    }


    /* system notification (maikli) + Gmail (pormal) sa isang tawag */

    function figurify_customer_notice(
        mysqli $conn,
        int $userId,
        ?int $orderId,
        string $systemMessage,
        string $emailSubject,
        array $emailParagraphs,
        array $embeddedImages = [],
        int $isRead = 0
    ): void {
        require_once __DIR__ . "/notification_helper.php";

        figurify_notify_user($conn, $userId, $orderId, $systemMessage, $isRead, null, false);

        figurify_email_customer($conn, $userId, $emailSubject, $emailParagraphs, $embeddedImages);
    }


    /* Mga email na may sariling function (ginagamit sa maraming lugar) */

    /* 1. QUOTATION READY */
    function figurify_email_quotation_ready(mysqli $conn, int $userId, int $orderId, float $total): bool
    {
        return figurify_email_customer($conn, $userId, "Your quotation for Order #" . $orderId . " is ready", [
            "Good news! Your order details have been verified, and your quotation is now ready.",
            "<strong>Order Number:</strong> #" . $orderId . "<br><strong>Total Quoted Price:</strong> ₱" . number_format($total, 2),
            "Please log in to your account and visit the <strong>My Orders</strong> page to review your quotation. "
                . "You have <strong>2 days</strong> to accept or cancel the quotation. If you do not respond within this period, "
                . "your order will be automatically cancelled.",
            "You can also view this notification anytime on your <strong>Notifications</strong> page after logging in to your account.",
            "Thank you for choosing Clay and Stuff! We look forward to creating something special for you.",
        ]);
    }

    /* 2. PAYMENT RECEIVED */
    function figurify_email_payment_received(mysqli $conn, int $userId, int $orderId): bool
    {
        return figurify_email_customer($conn, $userId, "We received your payment for Order #" . $orderId, [
            "We have received your payment details for <strong>Order #" . $orderId . "</strong>.",
            "Your payment is currently awaiting verification by our owner. You will receive an update once your payment has been reviewed and verified.",
            figurify_email_status_line(),
            "Thank you for choosing Clay and Stuff!",
        ]);
    }

    /* 3. PAYMENT DETAILS VERIFIED */
    function figurify_email_payment_verified(mysqli $conn, int $userId, int $orderId): bool
    {
        return figurify_email_customer($conn, $userId, "Your payment details for Order #" . $orderId . " have been reviewed", [
            "Your payment details for <strong>Order #" . $orderId . "</strong> have been reviewed by our staff and are now awaiting final approval from the owner.",
            "You will receive another notification once the owner has made a decision regarding your payment.",
            figurify_email_status_line(),
            "Thank you for your patience and for choosing Clay and Stuff!",
        ]);
    }

    /* 4. PAYMENT APPROVED */
    function figurify_email_payment_approved(mysqli $conn, int $userId, int $orderId): bool
    {
        return figurify_email_customer($conn, $userId, "Your payment for Order #" . $orderId . " has been approved", [
            "Great news! Your payment for <strong>Order #" . $orderId . "</strong> has been approved by the owner, and your order is now processing.",
            "Our team will begin creating your customized figure based on the details of your order. We’ll keep you updated as your order progresses.",
            figurify_email_status_line(),
            "Thank you for choosing Clay and Stuff! We appreciate your trust in us and look forward to bringing your design to life.",
        ]);
    }

    /* 5. PROGRESS UPDATE (may larawan) - normal, revised, o final */
    function figurify_email_progress_update(
        mysqli $conn,
        int $userId,
        int $orderId,
        string $imageAbsPath,
        string $staffMessage = "",
        bool $isRevision = false,
        bool $isFinal = false
    ): bool {
        $photo = '<img src="cid:progressimg" alt="Order Progress Photo" style="display:block;max-width:100%;width:480px;height:auto;margin:0 0 14px;">';
        $note  = ($staffMessage !== "" && !$isFinal) ? ("Message from our team: “" . nl2br(figurify_email_e($staffMessage)) . "”") : "";

        if ($isFinal) {
            $subject = "Your Order #" . $orderId . " is finished";
            $paras = [
                "Great news! Your customized figure for <strong>Order #" . $orderId . "</strong> is finished.",
                "<strong>Here’s the final photo of your order.</strong>",
                $photo,
                "Your order will be shipped out soon. We’ll notify you once it has been shipped.",
                figurify_email_status_line(),
                "Thank you for choosing Clay and Stuff!",
            ];
        } elseif ($isRevision) {
            $subject = "Your revised figure for Order #" . $orderId . " is ready for review";
            $paras = [
                "We’ve finished the revision you requested for <strong>Order #" . $orderId . "</strong>!",
                "<strong>Here’s a photo of your revised figure.</strong>",
                $photo,
                $note,
                "Please log in to your Clay and Stuff account and visit the <strong>My Orders</strong> page to review and approve the photo.",
                "Thank you for choosing Clay and Stuff! We look forward to completing your customized figure.",
            ];
        } else {
            $subject = "Progress update on your Order #" . $orderId;
            $paras = [
                "We’re happy to share a progress update on <strong>Order #" . $orderId . "</strong>!",
                "<strong>Here’s a photo of your customized figure so far.</strong>",
                $photo,
                $note,
                "Please log in to your Clay and Stuff account and visit the <strong>My Orders</strong> page to review the photo. "
                    . "Once you’ve reviewed it, you can approve the progress or request your <strong>one (1) revision</strong> if changes are needed.",
                "Thank you for choosing Clay and Stuff! We look forward to completing your customized figure.",
            ];
        }

        return figurify_email_customer($conn, $userId, $subject, $paras, [["path" => $imageAbsPath, "cid" => "progressimg"]]);
    }
}
