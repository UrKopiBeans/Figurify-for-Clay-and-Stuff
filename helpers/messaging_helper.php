<?php

/* =====================================================================
   MESSAGING HELPER   (helpers/messaging_helper.php)

   Pinagsama sa iisang file ang dating 3 helper:
     mailer_helper, email_template_helper, notification_helper

   Walang pinalitang pangalan ng function, kaya ang mga page na gumagamit
   nito ay hindi kailangang galawin maliban sa require line
   (palitan lang ng "messaging_helper.php").

   INDEX (hanapin ang "SECTION n" para tumalon):
     SECTION 1 - MAILER        (figurify_send_mail: Gmail SMTP gamit ang PHPMailer)
     SECTION 2 - EMAIL LAYOUT  (itsura ng email: Hello + talata + Best regards)
     SECTION 3 - READY EMAILS  (quotation, payment, progress update)
     SECTION 4 - NOTIFICATION  (bell sa system: customer at staff)
     SECTION 5 - NOTICE        (bell + email sa iisang tawag)

   BUOD NG TAKBO (kung paano sila nagtutulungan):
     figurify_notify_user()  ->  sumusulat sa "notifications" table (bell)
                                  -> kung $sendEmail, tatawag sa figurify_email_customer()
     figurify_email_customer() -> hahanapin ang pangalan/email ng customer
                                  -> gagawa ng email gamit ang layout (SECTION 2)
                                  -> ipapadala gamit ang figurify_send_mail() (SECTION 1)
     figurify_customer_notice() -> bell (maikli) + email (buo) sa isang tawag

   Tip: lahat ng email ay "best-effort". Kapag pumalya ang Gmail, hindi
   masisira ang page; may ililista lang sa PHP error log.
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : lahat ng pagpapadala ng mensahe: EMAIL at BELL (notification sa loob ng site).
   PARA SAAN   : para malaman ng customer at staff kapag may nangyari sa order.
   PANO NAGANA : 1) figurify_notify_user() = sumusulat ng bell sa table na notifications.
                 2) figurify_email_customer() = gumagawa ng email at ipinapadala gamit ang Gmail
                    (PHPMailer).
                 3) figurify_customer_notice() = ginagawa ang dalawa sa isang tawag.
                 Kapag pumalya ang email, hindi masisira ang page; maitatala lang sa error log.
   --------------------------------------------------------------------- */

require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;


/* =====================================================================
   SECTION 1 - MAILER
   (galing sa: mailer_helper.php)
   Iisang lugar lang ang Gmail SMTP setup, para hindi na kopyahin sa
   bawat file na nagpapadala ng email.
   ===================================================================== */

/* Kunin ang Gmail login mula sa Admin/mail_config.php
   (hindi ito naka-commit; kopyahin ang mail_config.sample.php kung wala pa) */
if (!function_exists("figurify_mail_config")) {

    function figurify_mail_config(): array
    {
        $configPath = __DIR__ . "/../Admin/mail_config.php";

        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                "Admin/mail_config.php ay wala pa. Kopyahin ang " .
                "Admin/mail_config.sample.php papuntang Admin/mail_config.php " .
                "tapos ilagay doon ang totoong Gmail App Password."
            );
        }

        return require $configPath;
    }
}


/* Magpadala ng HTML email galing sa Gmail ng Clay and Stuff.
   Ibinabalik ang true kung napadala, false kung pumalya.
   $embeddedImages (optional): [ ["path" => "...", "cid" => "..."] ]
   para maglagay ng larawan sa loob ng email gamit ang <img src="cid:..."> */
if (!function_exists("figurify_send_mail")) {

    function figurify_send_mail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody,
        array $embeddedImages = []
    ): bool {

        try {
            $mailConfig = figurify_mail_config();
        } catch (\Throwable $e) {
            error_log("figurify_send_mail failed (config): " . $e->getMessage());

            return false;
        }

        /* SMTP setup galing sa Admin/mail_config.php.
           Kapag walang "host" sa config = Gmail SMTP (port 587 + STARTTLS), para gumana pa rin sa XAMPP.
           Sa GoDaddy: mail.clayandstuff.com, port 465, auth true, encryption "ssl" (login ng no-reply@clayandstuff.com).
           Pwede pa rin: encryption "none" (relay, walang login) o "starttls" (Gmail sa XAMPP). */
        $smtpHost = $mailConfig["host"] ?? "smtp.gmail.com";
        $smtpAuth = $mailConfig["auth"] ?? true;
        $smtpMode = $mailConfig["encryption"] ?? "starttls";
        $smtpTls  = ($smtpMode === "starttls");
        $smtpPort = (int) ($mailConfig["port"] ?? 587);

        /* Sa SSL (port 465): 1st try = buong SSL check. Kung hindi makakonekta at sariling
           mail server ng clayandstuff.com ang kausap, 2nd try = hindi i-check ang pangalan ng
           certificate (madalas hindi tugma ang certificate name sa shared hosting).
           Sa Gmail (XAMPP): 2 try din, kasi minsan pumapalya ang unang koneksyon pero tumatama ang pangalawa. */
        $passes = ($smtpMode === "ssl" && strpos($smtpHost, "clayandstuff.com") !== false) ? [true, false] : [true, true];

        foreach ($passes as $verifyCert) {

            $startedAt = microtime(true);
            $debugLog  = "";
            $mail      = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->CharSet     = "UTF-8";
                $mail->Encoding    = "base64";
                $mail->Host        = $smtpHost;
                $mail->Port        = $smtpPort;
                $mail->SMTPAuth    = (bool) $smtpAuth;
                $mail->SMTPAutoTLS = $smtpTls;
                $mail->Timeout     = 15;   // segundo - bumibigay kung hindi maabot ang mail server (dati 300)

                // detalye ng koneksyon, para makita sa error_log kung bakit pumalya (hindi kasama ang password)
                $mail->SMTPDebug   = 2;
                $mail->Debugoutput = function ($str, $level) use (&$debugLog) {
                    $debugLog .= trim((string) $str) . " | ";
                };

                if ($smtpAuth) {
                    $mail->Username = $mailConfig["username"];
                    $mail->Password = $mailConfig["app_password"];
                }

                if ($smtpMode === "ssl") {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;   // port 465
                } elseif ($smtpTls) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }

                if (!$verifyCert) {
                    $mail->SMTPOptions = [
                        "ssl" => [
                            "verify_peer"       => false,
                            "verify_peer_name"  => false,
                            "allow_self_signed" => true,
                        ],
                    ];
                }

                $fromEmail = $mailConfig["from_email"] ?? ($mailConfig["username"] ?? "");

                $mail->setFrom($fromEmail, $mailConfig["from_name"] ?? "Clay and Stuff");
                $mail->addAddress($toEmail, $toName);

                // mga larawan sa loob ng email (laktawan kung wala ang file)
                foreach ($embeddedImages as $embed) {
                    if (!empty($embed["path"]) && file_exists($embed["path"])) {
                        $mail->addEmbeddedImage($embed["path"], $embed["cid"] ?? "embeddedimage");
                    }
                }

                // awtomatikong ikabit ang logo kung ginamit sa email (cid:cslogo)
                if (strpos($htmlBody, "cid:cslogo") !== false) {
                    $logoPath = __DIR__ . "/../Image/brand/email-logo.png";

                    if (file_exists($logoPath)) {
                        $mail->addEmbeddedImage($logoPath, "cslogo", "clay-and-stuff-logo.png");
                    }
                }

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = $altBody;

                $mail->send();

                if (!$verifyCert) {
                    error_log("figurify_send_mail: naipadala, pero kinailangan ang relaxed certificate check ({$smtpHost}:{$smtpPort})");
                }

                return true;

            } catch (\Throwable $e) {
                error_log(sprintf(
                    "figurify_send_mail failed (%s:%d, %s, cert check: %s): %s | %s",
                    $smtpHost,
                    $smtpPort,
                    $smtpMode,
                    $verifyCert ? "yes" : "no",
                    $e->getMessage(),
                    substr($debugLog, 0, 1500)
                ));

                // kung natagalan na (timeout), huwag nang ulitin para hindi lalo bumagal
                if ((microtime(true) - $startedAt) >= 14) {
                    break;
                }
            }
        }

        return false;
    }
}


/* Pila ng email na ipapadala pagkatapos ng page.
   Una, ibabalik na ang sagot sa browser (fastcgi_finish_request / litespeed_finish_request),
   tapos saka ipapadala ang mga email. Kung walang ganung function sa server,
   ipapadala pa rin sa dulo ng page (hindi lalala kumpara sa dati). */
if (!function_exists("figurify_defer_mail")) {

    function figurify_defer_mail(callable $job): void
    {
        static $queue = [];
        static $registered = false;

        $queue[] = $job;

        if ($registered) {
            return;
        }

        $registered = true;

        register_shutdown_function(function () use (&$queue) {

            if (function_exists("session_status") && session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            if (function_exists("fastcgi_finish_request")) {
                fastcgi_finish_request();
            } elseif (function_exists("litespeed_finish_request")) {
                litespeed_finish_request();
            }

            ignore_user_abort(true);
            @set_time_limit(90);

            foreach ($queue as $job) {
                try {
                    $job();
                } catch (\Throwable $e) {
                    error_log("figurify_defer_mail failed: " . $e->getMessage());
                }
            }

            $queue = [];
        });
    }
}


/* =====================================================================
   SECTION 2 - EMAIL LAYOUT
   (galing sa: email_template_helper.php)
   Simpleng email lang: "Hello <pangalan>," + mga talata + "Best regards,
   Clay and Stuff Team". Maikli ang notification sa system, mas buo ang
   nasa Gmail.

   Gamit:
     figurify_customer_notice($conn, $userId, $orderId, "maikling mensahe",
                              "Subject", ["talata 1", "talata 2"]);
   ===================================================================== */

if (!function_exists("figurify_email_e")) {

    /* i-escape ang text bago ilagay sa email (proteksyon laban sa XSS) */
    function figurify_email_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }


    /* karaniwang pangwakas na linya ng halos lahat ng email */
    function figurify_email_status_line(): string
    {
        return "Check your order anytime in <strong>My Orders</strong>.";
    }


    /* note para sa customer na 50% down payment pa lang ang bayad (hindi ginagamit sa Full Payment) */
    function figurify_email_balance_note(): string
    {
        return "We ship your order once your balance is paid and approved.";
    }


    /* HTML na bersyon: Hello + mga talata + pirma (walang container).
       Ang talatang nagsisimula sa "<img" ay hindi nilalagyan ng <p>.
       Ang itsura ay nasa iisang <style> sa <head> ng email (walang inline style). */
    function figurify_email_plain_layout(string $customerName, array $paragraphs): string
    {
        $name = figurify_email_e(trim($customerName) !== "" ? trim($customerName) : "there");

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            . '.cs-mail { font-family: Plus Jakarta Sans, Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #222222; }'
            . '.cs-mail p { margin: 0 0 14px; }'
            . '.cs-mail .cs-mail-sign { margin: 18px 0 0; }'
            . '.cs-mail .cs-mail-logo { display: block; width: 120px; height: auto; margin: 0 0 14px; }'
            . '.cs-mail .cs-mail-photo { display: block; max-width: 100%; width: 480px; height: auto; margin: 0 0 14px; }'
            . '.cs-mail .cs-mail-code { font-size: 22px; letter-spacing: 6px; }'
            . '.cs-mail .cs-mail-btn { display: inline-block; margin: 0 8px 8px 0; padding: 12px 24px; border-radius: 999px; font-size: 15px; font-weight: 700; text-decoration: none; }'
            . '.cs-mail .cs-mail-btn-good { background: #c2325f; color: #ffffff; }'
            . '.cs-mail .cs-mail-btn-rev { background: #ffffff; color: #c2325f; border: 2px solid #c2325f; }'
            . '.cs-mail .cs-mail-stars a { font-size: 34px; line-height: 1; margin-right: 4px; color: #e8707e; text-decoration: none; }'
            . '</style></head><body>';
        $html .= '<div class="cs-mail">';
        // logo ng Clay and Stuff sa taas ng email (naka-embed, kaya lumalabas kahit PUBLIC o LOCALHOST)
        if (file_exists(__DIR__ . "/../Image/brand/email-logo.png")) {
            $html .= '<img src="cid:cslogo" alt="Clay and Stuff" width="120" class="cs-mail-logo">';
        }

        $html .= '<p>Hello ' . $name . ',</p>';

        foreach ($paragraphs as $paragraph) {
            if ($paragraph === null || $paragraph === "") {
                continue;
            }

            $html .= (strpos($paragraph, "<img") === 0)
                ? $paragraph
                : '<p>' . $paragraph . '</p>';
        }

        $html .= '<p class="cs-mail-sign">Best regards,<br><strong>Clay and Stuff Team</strong></p>';
        $html .= '</div></body></html>';

        return $html;
    }


    /* plain-text na bersyon (para sa email app na walang HTML) */
    function figurify_email_plain_text(string $customerName, array $paragraphs): string
    {
        $lines = ["Hello " . (trim($customerName) !== "" ? trim($customerName) : "there") . ","];

        foreach ($paragraphs as $paragraph) {
            // walang laman o larawan -> hindi isasama sa plain text
            if ($paragraph === null || $paragraph === "" || strpos($paragraph, "<img") === 0 || strpos($paragraph, '<span class="cs-mail-stars"') === 0) {
                continue;
            }

            // <a href="X">label</a>  ->  "label: X" (para may link pa rin sa plain text)
            $paragraph = preg_replace('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/i', '$2: $1', $paragraph);

            $lines[] = html_entity_decode(
                strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $paragraph)),
                ENT_QUOTES,
                "UTF-8"
            );
        }

        $lines[] = "Best regards,\nClay and Stuff Team";

        return implode("\n\n", $lines);
    }


    /* Kinukuha ang pangalan/email ng customer saka ipinapadala ang email.
       false kung walang customer, walang email, o pumalya ang pagpapadala. */
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

        $toEmail   = (string) $customer["email"];
        $htmlBody  = figurify_email_plain_layout($name, $paragraphs);
        $altBody   = figurify_email_plain_text($name, $paragraphs);

        // ipadala ang email PAGKATAPOS maibalik ang page sa browser, para hindi maghintay ang user
        figurify_defer_mail(function () use ($toEmail, $name, $subject, $htmlBody, $altBody, $embeddedImages) {
            figurify_send_mail($toEmail, $name, $subject, $htmlBody, $altBody, $embeddedImages);
        });

        return true;   // nasa pila na ang email (best-effort, walang gumagamit ng resulta)
    }
}


/* =====================================================================
   SECTION 3 - READY EMAILS
   (galing sa: email_template_helper.php)
   Mga email na may sariling function dahil ginagamit sa maraming lugar.
   Lahat ay tumatawag lang sa figurify_email_customer() sa itaas.
   ===================================================================== */

if (!function_exists("figurify_email_quotation_ready")) {

    /* 1. QUOTATION READY */
    function figurify_email_quotation_ready(mysqli $conn, int $userId, int $orderId, float $total): bool
    {
        return figurify_email_customer($conn, $userId, "Your quotation for Order #" . $orderId . " is ready", [
            "Your quotation is ready.",
            "<strong>Order Number:</strong> #" . $orderId . "<br><strong>Total Quoted Price:</strong> ₱" . number_format($total, 2),
            "Accept or cancel it in <strong>My Orders</strong> within <strong>2 days</strong>, or the order will be cancelled.",
        ]);
    }


    /* 2. PAYMENT RECEIVED */
    function figurify_email_payment_received(mysqli $conn, int $userId, int $orderId): bool
    {
        return figurify_email_customer($conn, $userId, "We received your payment for Order #" . $orderId, [
            "We received your payment for <strong>Order #" . $orderId . "</strong>.",
            "We’ll email you once it’s verified.",
            figurify_email_status_line(),
        ]);
    }


    /* 3. PAYMENT DETAILS VERIFIED (ni-review ng staff, hinihintay ang owner) */
    function figurify_email_payment_verified(mysqli $conn, int $userId, int $orderId): bool
    {
        return figurify_email_customer($conn, $userId, "Your payment details for Order #" . $orderId . " have been reviewed", [
            "Your payment details for <strong>Order #" . $orderId . "</strong> were checked and are waiting for the owner’s approval.",
            figurify_email_status_line(),
        ]);
    }


    /* 4. PAYMENT APPROVED */
    function figurify_email_payment_approved(mysqli $conn, int $userId, int $orderId): bool
    {
        return figurify_email_customer($conn, $userId, "Your payment for Order #" . $orderId . " has been approved", [
            "Your payment for <strong>Order #" . $orderId . "</strong> is approved. We’re now making your figure.",
            figurify_email_status_line(),
        ]);
    }


    /* 5. PROGRESS UPDATE (may larawan) - normal, revised, o final.
       Ang larawan ay naka-embed gamit ang cid "progressimg".
       May buttons na "It's Good" at "Request Revision" (papunta sa my-order/progress_response.php,
       walang login). $updateId = id ng bagong progress update. */
    function figurify_email_progress_update(
        mysqli $conn,
        int $userId,
        int $orderId,
        string $imageAbsPath,
        string $staffMessage = "",
        bool $isRevision = false,
        bool $isFinal = false,
        int $updateId = 0
    ): bool {
        $photo = '<img src="cid:progressimg" alt="Order Progress Photo" class="cs-mail-photo">';

        // mensahe ng staff (hindi ipinapakita sa final na email)
        $note = ($staffMessage !== "" && !$isFinal)
            ? ("Message from our team: “" . nl2br(figurify_email_e($staffMessage)) . "”")
            : "";

        if ($isFinal) {
            // tapos na ang figure
            $subject = "Your Order #" . $orderId . " is finished";
            $paras = [
                "Your figure for <strong>Order #" . $orderId . "</strong> is finished.",
                $photo,
                "We’ll email you once it ships.",
                figurify_email_status_line(),
            ];
        } elseif ($isRevision) {
            // revised photo: approved na agad (tapos na ang revision), kaya walang button - balance na ang susunod
            $subject = "Your revised photo for Order #" . $orderId . " is ready";
            $paras = [
                "Your revised figure for <strong>Order #" . $orderId . "</strong> is ready.",
                $photo,
                $note,
                "Please pay the remaining balance in <strong>My Orders</strong> so we can ship it.",
                figurify_email_status_line(),
            ];
        } else {
            // normal na progress photo: tanong + buttons (It's Good / Request Revision)
            $subject = "New progress photo for Order #" . $orderId;

            $buttons = "";

            if ($updateId > 0) {
                $buttons = '<a class="cs-mail-btn cs-mail-btn-good" href="' . figurify_email_e(figurify_progress_response_url($conn, $orderId, $updateId, "good")) . '">It’s Good</a>'
                    . '<a class="cs-mail-btn cs-mail-btn-rev" href="' . figurify_email_e(figurify_progress_response_url($conn, $orderId, $updateId, "revision")) . '">Request Revision</a>';
            }

            $days = (defined("FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS") ? FIGURIFY_PROGRESS_AUTO_APPROVE_HOURS : 48) / 24;

            $paras = [
                "Here’s the latest photo of your figure for <strong>Order #" . $orderId . "</strong>.",
                $photo,
                $note,
                $buttons !== "" ? "Is it good?" : "Check it in <strong>My Orders</strong>.",
                $buttons,
                "No reply in " . $days . " days means it’s approved.",
            ];
        }

        return figurify_email_customer($conn, $userId, $subject, $paras, [["path" => $imageAbsPath, "cid" => "progressimg"]]);
    }
}


/* =====================================================================
   SECTION 4 - NOTIFICATION
   (galing sa: notification_helper.php)
   Sumusulat sa "notifications" table (ito ang lumalabas sa bell).
     figurify_notify_user  = para sa customer (bell + optional na email)
     figurify_notify_staff = para sa lahat ng admin at staff (bell lang)
   ===================================================================== */

if (!function_exists("figurify_notify_user")) {

    /* Mag-save ng notification para sa isang user, at by default
       mag-e-email din. Ilagay ang $sendEmail = false kung may sariling
       email design na ang page (para hindi doble).
       Ang ibinabalik (true/false) ay para lang sa pag-save sa database;
       ang email ay best-effort. */
    function figurify_notify_user(
        mysqli $conn,
        int $userId,
        ?int $orderId,
        string $message,
        int $isRead = 0,
        ?string $emailSubject = null,
        bool $sendEmail = true
    ): bool {

        // 1) i-save sa notifications table (in-system na bell)
        $notifyStmt = $conn->prepare(
            "INSERT INTO notifications (user_id, order_id, message, is_read)
             VALUES (?, ?, ?, ?)"
        );

        if ($notifyStmt === false) {
            return false;
        }

        $savedMessage = figurify_db_text($message);
        $notifyStmt->bind_param("iisi", $userId, $orderId, $savedMessage, $isRead);
        $saved = $notifyStmt->execute();
        $notifyStmt->close();

        // 2) email (kung hindi pinatay). Ang figurify_email_customer na
        //    ang naghahanap ng pangalan/email at nagsasabi kung wala.
        if ($sendEmail) {

            $subject = $emailSubject ?? (
                $orderId
                    ? ("Update on your Order #" . $orderId)
                    : "A new update from Clay and Stuff"
            );

            // simpleng email (walang kahon): mensahe + status line
            figurify_email_customer($conn, $userId, $subject, [
                nl2br(htmlspecialchars($message, ENT_QUOTES, "UTF-8")),
                figurify_email_status_line(),
            ]);
        }

        return $saved;
    }
}


if (!function_exists("figurify_notify_staff")) {

    /* Ipadala ang parehong notification sa lahat ng admin at staff
       (bell lang, walang email; ang email ay para sa customer).
       $excludeUserId = laktawan kung sino ang gumawa ng aksyon. */
    function figurify_notify_staff(
        mysqli $conn,
        ?int $orderId,
        string $message,
        ?int $excludeUserId = null
    ): bool {

        // 1) kunin ang lahat ng admin/staff (maliban sa $excludeUserId)
        $staffStmt = $conn->prepare("SELECT user_id FROM users WHERE role IN ('admin', 'staff')");

        if ($staffStmt === false) {
            return false;
        }

        $staffStmt->execute();
        $result = $staffStmt->get_result();

        $staffIds = [];

        while ($row = $result->fetch_assoc()) {
            $staffId = (int) $row["user_id"];

            if ($excludeUserId === null || $staffId !== $excludeUserId) {
                $staffIds[] = $staffId;
            }
        }

        $staffStmt->close();

        if (empty($staffIds)) {
            return false;
        }

        // 2) isang notification kada staff (unread)
        $notifyStmt = $conn->prepare(
            "INSERT INTO notifications (user_id, order_id, message, is_read)
             VALUES (?, ?, ?, 0)"
        );

        if ($notifyStmt === false) {
            return false;
        }

        $savedAny     = false;
        $savedMessage = figurify_db_text($message);

        foreach ($staffIds as $staffId) {
            $notifyStmt->bind_param("iis", $staffId, $orderId, $savedMessage);

            if ($notifyStmt->execute()) {
                $savedAny = true;
            }
        }

        $notifyStmt->close();

        return $savedAny;
    }
}


/* =====================================================================
   SECTION 5 - NOTICE (bell + email sa isang tawag)
   (galing sa: email_template_helper.php)
   Maikli ang mensahe sa bell, pormal at buo ang nasa Gmail.
   Ang bell ay isinusulat nang walang email (sendEmail = false) para
   hindi doble, tapos ang buong email ay hiwalay na ipinapadala.
   ===================================================================== */

if (!function_exists("figurify_customer_notice")) {

    /* ANO ITO: isang tawag lang para sa BELL (maikli) + EMAIL (buo) sa customer.
       PANO: gumagawa muna ng bell gamit ang figurify_notify_user(), tapos email gamit ang figurify_email_customer(). */
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
        // 1) bell (maikli)
        figurify_notify_user($conn, $userId, $orderId, $systemMessage, $isRead, null, false);

        // 2) email (buo)
        figurify_email_customer($conn, $userId, $emailSubject, $emailParagraphs, $embeddedImages);
    }
}
