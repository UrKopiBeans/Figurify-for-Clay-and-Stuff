<?php

/* Patakbuhin bawat oras (Task Scheduler / run_scheduled_tasks.bat o cron).
   Ginagawa nito kahit walang nagbubukas ng site:
   1. cancel ang quotation na lampas 2 araw
   2. paalala sa progress photo na 48 oras nang walang sagot
   3. auto-approve ng progress photo pagkalipas ng 72 oras
   4. complete ang shipped order pagkalipas ng 7 araw
   5. database backup, isang beses kada araw (huling 14 lang ang tinatabi)
   6. linisin ang lumang login / spam attempts
   CLI lang, hindi mabubuksan sa browser. */

if (php_sapi_name() !== "cli") {
    http_response_code(403);
    die("This script can only be run from the command line / Task Scheduler.");
}

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";
require_once __DIR__ . "/../helpers/progress_expire_helper.php";
require_once __DIR__ . "/../helpers/order_complete_helper.php";

$log = function (string $line): void {
    echo "[" . date("Y-m-d H:i:s") . "] " . $line . PHP_EOL;
};

figurify_expire_old_quotes($conn);
$log("Checked expired quotations.");

$reminders = figurify_send_progress_reminders($conn);
$log("Progress reminders sent: " . $reminders);

$approved = figurify_autoapprove_stale_progress_updates($conn);
$log("Progress updates auto-approved: " . $approved);

/* linisin ang lumang login / spam attempts (mas luma sa 2 araw) */
try {
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)");
    $conn->query("DELETE FROM action_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)");
    $log("Old login/spam attempts cleaned.");
} catch (\Throwable $e) {
    // wala pang table
}

$received = figurify_autocomplete_shipped_orders($conn);
$log("Shipped orders auto-completed: " . $received);

$conn->close();

/* daily backup - isang beses lang bawat araw kahit oras-oras tumatakbo */
$backupDir   = __DIR__ . "/backups";
$todayPrefix = "figurify_backup_" . date("Y-m-d");
$hasToday    = is_dir($backupDir) && count(glob($backupDir . "/" . $todayPrefix . "_*.sql") ?: []) > 0;

if (!$hasToday && !in_array("--no-backup", $argv ?? [], true)) {

    $php = PHP_BINARY ?: "php";
    passthru(escapeshellarg($php) . " " . escapeshellarg(__DIR__ . "/backup_database.php"), $exitCode);
    $log("Daily backup " . ($exitCode === 0 ? "done." : "FAILED (exit " . $exitCode . ")."));

    /* itabi lang ang huling 14 na backup */
    $files = glob($backupDir . "/figurify_backup_*.sql") ?: [];
    sort($files);

    foreach (array_slice($files, 0, max(0, count($files) - 14)) as $old) {
        @unlink($old);
    }
}

$log("Done.");
