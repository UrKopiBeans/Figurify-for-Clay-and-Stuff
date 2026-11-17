<?php
/* ==========================================================================
   ACTIVITY LOG   (owner/activity-log.php)
   Iisang page para sa Owner AT Staff. Ang staff/activity-log.php ay
   nagse-set lang ng $figurifyRole = "staff" tapos ito ang ginagamit.
   ========================================================================== */

$figurifyRole = $figurifyRole ?? "owner";
require_once __DIR__ . "/../" . $figurifyRole . "/" . $figurifyRole . "-header.php";
require_once __DIR__ . "/../helpers/reports_logs_helper.php";

// sariling pagination ng Activity Log (galing sa database) - 12 entries kada page
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$logEntries = figurify_get_activity_log($conn, $perPage, $offset);
$totalLogs  = figurify_count_activity_log($conn);
$totalPages = max(1, (int) ceil($totalLogs / $perPage));

// mas friendly na label para sa bawat action_type
$actionLabels = [
    "order_verified"        => "Verified Order",
    "order_rejected"        => "Rejected Order",
    "order_status_updated"  => "Updated Order Status",
    "quotation_sent"        => "Sent Quotation",
    "revision_accepted"     => "Accepted Revision",
    "revision_declined"     => "Declined Revision",
    "user_created"          => "Created Account",
    "user_role_updated"     => "Changed User Role",
    "user_deleted"          => "Deleted Account",
    "user_archived"         => "Archived Account",
    "user_restored"         => "Restored Account",
    "user_banned"           => "Banned Account",
    "user_unbanned"         => "Unbanned Account",
    "unauthorized_attempt"  => "Blocked Unauthorized Attempt",
    "transition_denied"     => "Blocked Invalid Status Change",
];

/* workflow actions ("wf_approve_payment", ...) - iisang label list sa order_workflow.php */
require_once __DIR__ . "/../helpers/order_helper.php";

foreach (FIGURIFY_WF_ACTION_LABELS as $wfKey => $wfLabel) {
    $actionLabels["wf_" . $wfKey] = $wfLabel;
}

function figurify_activity_label(string $actionType, array $labels): string
{
    return $labels[$actionType] ?? ucwords(str_replace("_", " ", $actionType));
}


/* kulay ng action chip (Design System): berde = tapos/ok, dilaw = naghihintay/quote,
   pula = tinanggihan/binura, asul = shipping, pink = iba pa */
function figurify_activity_tone(string $actionType): string
{
    $t = strtolower($actionType);

    $rules = [
        "bad"  => ["reject", "decline", "delete", "archiv", "ban", "unauthorized", "denied", "cancel", "expire"],
        "ship" => ["ship"],
        "ok"   => ["verif", "approv", "accept", "complete", "paid", "payment", "balance", "unban", "restor", "created", "final"],
        "warn" => ["quot", "pending", "revision", "correction", "date"],
    ];

    // "unban" muna bago "ban" para hindi maging pula
    if (strpos($t, "unban") !== false) {
        return "ok";
    }

    foreach ($rules as $tone => $needles) {
        foreach ($needles as $needle) {
            if (strpos($t, $needle) !== false) {
                return $tone;
            }
        }
    }

    return "pink";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Figurify | Activity Log</title>
<!-- ===================== STYLE NG PAGE NA ITO: activity-log.css ===================== -->
<link rel="stylesheet" href="activity-log.css">

    </head>

<body>

<div class="app">

        <?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>

    <main class="main">

        <header class="topbar">

            <div class="welcome">
                <h2 class="portal-title">Activity Log</h2>
            </div>

            <div class="top-actions">
<!-- ==========================================================
     [TOPBAR-DATETIME]  (dating Shared/topbar-datetime.php - nasa page na ito na)
     ANO ITO: petsa at oras sa taas ng page, sa kaliwa ng notification bell.
     PAANO GUMAGANA: ang CSS nito ay nasa main style block sa itaas (.cs-tb-datetime); ang script sa ibaba ay nagpapalit ng .cs-tb-date at .cs-tb-time
     kada segundo (en-PH). Self-contained: hindi kailangan ng #currentDate / #currentTime.
     ========================================================== -->
<div class="cs-tb-datetime" aria-label="Current date and time">
    <span class="cs-tb-date">Loading date...</span>
    <span class="cs-tb-dot">•</span>
    <span class="cs-tb-time">Loading time...</span>
</div>

<!-- [END TOPBAR-DATETIME] -->

                <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>
            </div>

        </header>

        <section class="card al-card">

            <?php if (empty($logEntries)): ?>

                <div class="al-empty">No activity yet.</div>

            <?php else: ?>

                <div class="al-scroll">

                    <table class="al-table">

                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Date &amp; Time</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($logEntries as $entry): ?>

                                <tr>
                                    <td class="al-order">
                                        <?php echo $entry["order_id"] ? ("#" . (int) $entry["order_id"]) : "N/A"; ?>
                                    </td>
                                    <td class="al-date">
                                        <?php echo date("M j, g:i A", strtotime($entry["created_at"])); ?>
                                    </td>
                                    <td class="al-actor" title="<?php echo e(ucfirst((string) $entry["actor_role"])); ?>">
                                        <?php echo e(!empty($entry["actor_email"]) ? $entry["actor_email"] : $entry["actor_name"]); ?>
                                    </td>
                                    <td>
                                        <span class="al-chip tone-<?php echo figurify_activity_tone((string) $entry["action_type"]); ?>">
                                            <?php echo e(figurify_activity_label($entry["action_type"], $actionLabels)); ?>
                                        </span>
                                    </td>
                                    <td class="al-details"><?php echo e(figurify_show_text($entry["description"], " to")); ?></td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <nav class="al-pager" aria-label="Activity log pages">

                    <div>
                        <a
                            href="activity-log.php?page=<?php echo max(1, $page - 1); ?>"
                            class="al-pager-btn<?php echo $page <= 1 ? " is-disabled" : ""; ?>"
                            <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ""; ?>
                        >Previous</a>
                    </div>

                    <span class="al-pager-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                    <div class="al-pager-next">
                        <a
                            href="activity-log.php?page=<?php echo min($totalPages, $page + 1); ?>"
                            class="al-pager-btn<?php echo $page >= $totalPages ? " is-disabled" : ""; ?>"
                            <?php echo $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : ""; ?>
                        >Next</a>
                    </div>

                </nav>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>

</html>