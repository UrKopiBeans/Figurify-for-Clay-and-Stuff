<?php

/* ==========================================================================
   STAFF HEADER (LOGIC)   (staff/staff-header.php)
   Session, staff role check at pangalan ng staff para sa lahat ng staff page.
   ========================================================================== */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_helper.php";
require_once __DIR__ . "/../helpers/dressup_helper.php";


// staff access check + staff info, same pattern as owner-header.php

[$staffName, $staffInitial] = figurify_require_role_page("staff", "Figurify Staff");

// bagong columns ng orders (hal. payment_option) bago mag-query ang page
figurify_wf_ensure_schema($conn);


// current page (for active nav highlight)
$currentPage =
    basename($_SERVER["PHP_SELF"]);

/* ==================== END STAFF HEADER (LOGIC) ==================== */

?>
