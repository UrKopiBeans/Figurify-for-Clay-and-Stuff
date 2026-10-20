<?php

/* Payment & Shipping details ng Owner/Staff pages at My Orders:
   pin address, proof ng down payment at balance, shipping receipt.
   May sariling DB connection kasi may mga page na sinasara na ang $conn bago mag-render. */

require_once __DIR__ . "/proof_helper.php";

if (!function_exists("figurify_payment_view_connection")) {

    function figurify_payment_view_connection()
    {
        static $connection = null;

        if ($connection !== null) {
            return $connection;
        }

        $connection = false;

        try {
            $candidate = @new mysqli("localhost", "root", "", "figurify_db");

            if ($candidate && !$candidate->connect_error) {
                $candidate->set_charset("utf8mb4");
                $connection = $candidate;
            }
        } catch (Throwable $error) {
            $connection = false;
        }

        return $connection;
    }


    /* pin address, mga proof at shipping receipt ng isang order */

    function figurify_payment_extras(int $orderId): array
    {
        static $cache = [];

        if ($orderId <= 0) {
            return [];
        }

        if (isset($cache[$orderId])) {
            return $cache[$orderId];
        }

        $row = [];
        $connection = figurify_payment_view_connection();

        if ($connection) {

            $queries = [
                "SELECT pin_address, payment_proof, balance_proof, balance_payment_reference, balance_paid_at, shipping_receipt
                 FROM orders WHERE order_id = ? LIMIT 1",
                "SELECT pin_address, payment_proof, balance_proof, balance_payment_reference, balance_paid_at
                 FROM orders WHERE order_id = ? LIMIT 1",
                "SELECT payment_proof FROM orders WHERE order_id = ? LIMIT 1",
            ];

            foreach ($queries as $sql) {
                try {
                    $stmt = $connection->prepare($sql);

                    if (!$stmt) {
                        continue;
                    }

                    $stmt->bind_param("i", $orderId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc() ?: [];
                    $stmt->close();
                    break;
                } catch (Throwable $error) {
                    $row = [];
                }
            }
        }

        $cache[$orderId] = $row;

        return $row;
    }


    /* "Pin Address:" na row sa loob ng .staff-payment-details-grid (owner/staff) */

    function figurify_render_pin_row(int $orderId): void
    {
        $extras = figurify_payment_extras($orderId);
        $pin = trim((string) ($extras["pin_address"] ?? ""));

        if ($pin === "") {
            return;
        }
        ?>
                                <span>
                                    <strong>Pin Address:</strong>
                                    <?php echo htmlspecialchars($pin, ENT_QUOTES, "UTF-8"); ?>
                                </span>
        <?php
    }


    /* Proof of Payment - Down Payment at Balance Payment, magkatabi.
       $variant: "staff" (owner/staff pages) o "customer" (My Orders) */

    function figurify_render_payment_proofs(int $orderId, string $variant = "staff"): void
    {
        $extras = figurify_payment_extras($orderId);

        $proofs = [];

        if (!empty($extras["payment_proof"])) {
            $proofs[] = ["Down Payment", (string) $extras["payment_proof"]];
        }

        if (!empty($extras["balance_proof"])) {
            $proofs[] = ["Balance Payment", (string) $extras["balance_proof"]];
        }

        if (empty($proofs)) {
            return;
        }

        $wrapClass  = $variant === "customer" ? "customer-payment-proof customer-payment-proof-inline" : "staff-payment-proof";
        $titleClass = $variant === "customer" ? "customer-payment-proof-title" : "staff-payment-proof-title";
        $thumbClass = $variant === "customer" ? "customer-payment-proof-thumb" : "staff-payment-proof-thumb";
        ?>
        <div class="<?php echo $wrapClass; ?> pv-proofs">
            <div class="<?php echo $titleClass; ?>">Proof of Payment</div>
            <div class="pv-proof-row">
                <?php foreach ($proofs as [$label, $path]): ?>
                    <?php $url = figurify_proof_url($path); ?>
                    <figure class="pv-proof">
                        <img
                            src="<?php echo htmlspecialchars($url, ENT_QUOTES, "UTF-8"); ?>"
                            alt="<?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?> proof for order <?php echo (int) $orderId; ?>"
                            class="<?php echo $thumbClass; ?>"
                            onclick="openImageLightbox('<?php echo htmlspecialchars($url, ENT_QUOTES, "UTF-8"); ?>', '<?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?> — Order <?php echo (int) $orderId; ?>')"
                        >
                        <figcaption><?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        figurify_payment_view_styles_once();
    }


    /* Shipping receipt (resibo ng courier) - kung meron */

    function figurify_render_shipping_receipt(int $orderId, string $variant = "staff"): void
    {
        $extras = figurify_payment_extras($orderId);
        $path = (string) ($extras["shipping_receipt"] ?? "");

        if ($path === "") {
            return;
        }

        $url = figurify_proof_url($path);
        $thumbClass = $variant === "customer" ? "customer-payment-proof-thumb" : "staff-payment-proof-thumb";
        ?>
        <div class="pv-proofs pv-receipt">
            <div class="<?php echo $variant === "customer" ? "customer-payment-proof-title" : "staff-payment-proof-title"; ?>">Shipping Receipt</div>
            <div class="pv-proof-row">
                <figure class="pv-proof">
                    <img
                        src="<?php echo htmlspecialchars($url, ENT_QUOTES, "UTF-8"); ?>"
                        alt="Shipping receipt for order <?php echo (int) $orderId; ?>"
                        class="<?php echo $thumbClass; ?>"
                        onclick="openImageLightbox('<?php echo htmlspecialchars($url, ENT_QUOTES, "UTF-8"); ?>', 'Shipping Receipt — Order <?php echo (int) $orderId; ?>')"
                    >
                </figure>
            </div>
        </div>
        <?php
        figurify_payment_view_styles_once();
    }


    function figurify_payment_view_styles_once(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;
        ?>
<style>
.pv-proofs{ margin-top:12px !important; padding:0 !important; border:0 !important; background:none !important; box-shadow:none !important; }
.pv-proof-row{ display:flex; flex-wrap:wrap; gap:12px; }
.pv-proof{ margin:0; display:flex; flex-direction:column; gap:4px; }
.pv-proof img{ width:120px !important; height:120px !important; max-width:none !important; max-height:none !important; object-fit:cover; border-radius:10px; border:1px solid #f1dbe7; cursor:zoom-in; background:#faf5f8; }
.pv-proof figcaption{ font-size:11px; font-weight:700; color:#8a6878; text-align:center; }
</style>
        <?php
    }
}
