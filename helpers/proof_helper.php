<?php

/* Proof of payment / refund URLs.
   Hindi direktang mabubuksan ang /uploads/ (bawal sa .htaccess), dumadaan sa Shared/proof.php:
   - owner / staff: pwede
   - customer: sariling order lang
   Gamit: <img src="<?php echo e(figurify_proof_url($order["payment_proof"])); ?>"> */

if (!function_exists("figurify_proof_url")) {

    function figurify_proof_url(?string $relativePath): string
    {
        $relativePath = (string) $relativePath;

        $documentRoot = rtrim(str_replace("\\", "/", (string) realpath($_SERVER["DOCUMENT_ROOT"] ?? "")), "/");
        $projectRoot  = rtrim(str_replace("\\", "/", (string) realpath(__DIR__ . "/..")), "/");
        $siteBase     = ($documentRoot !== "" && strpos($projectRoot, $documentRoot) === 0)
            ? substr($projectRoot, strlen($documentRoot)) . "/"
            : "/";

        $script = str_replace("\\", "/", $_SERVER["SCRIPT_FILENAME"] ?? "");
        $portal = (strpos($script, "/owner/") !== false) ? "owner"
            : ((strpos($script, "/staff/") !== false) ? "staff" : "customer");

        return $siteBase . "Shared/proof.php?portal=" . $portal . "&f=" . rawurlencode($relativePath);
    }
}
