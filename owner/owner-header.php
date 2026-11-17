<?php

/* ==========================================================================
   OWNER HEADER (LOGIC)   (owner/owner-header.php)
   Session, owner role check at pangalan ng owner para sa lahat ng owner page.
   ========================================================================== */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_helper.php";
require_once __DIR__ . "/../helpers/dressup_helper.php";


// Access guard + owner name/initial, shared sa access_helper.php para sa Owner at Staff panel
[$ownerName, $ownerInitial] = figurify_require_role_page("admin", "Figurify Owner");

// bagong columns ng orders (hal. payment_option) bago mag-query ang page
figurify_wf_ensure_schema($conn);

// Status label/class/colors helpers ginagamit sa lahat ng owner pages, galing order_status_helper.php


// Current page

$currentPage =
    basename($_SERVER["PHP_SELF"]);

/* ==================== END OWNER HEADER (LOGIC) ==================== */

?>
