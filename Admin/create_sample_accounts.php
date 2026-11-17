<?php

/* Sample accounts. Sa terminal lang ito pinapatakbo:
   php Admin/create_sample_accounts.php */
if (php_sapi_name() !== "cli") {
    http_response_code(403);
    die("This script can only be run from the command line.");
}

require_once __DIR__ . "/database.php";

$accounts = [
    ["Figurify", "Owner", "owner@gmail.com", "clayandstuffowner12345", "admin"],
    ["Figurify", "Staff", "staff@gmail.com", "clayandstuffstaff12345", "staff"]
];

$created = 0;

foreach ($accounts as $account) {
    [$firstName, $lastName, $email, $plainPassword, $role] = $account;
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (first_name, last_name, email, password, role) 
         VALUES (?, ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), password = VALUES(password), role = VALUES(role)"
    );

    $stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $role);

    if ($stmt->execute()) {
        $created++;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1400">
    <title>Sample Accounts Created</title>
    <style>
/* ==========================================================================
   FONT AT BACKGROUND - sariling kopya ng page na ito
   (dating nasa shared file; ngayon ay hindi na shared)
   ========================================================================== */

@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ----- FONT - Fraunces (heading) + Plus Jakarta Sans (body) ----- */

html,
body {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
    background: #FFF8F6;
}

/* form controls - hindi sila nagmamana ng font by default */

button,
input,
select,
textarea,
optgroup {
    font-family: inherit;
}

/* ----- BACKGROUND - cream #FFF8F6 ----- */


/* ===== katapusan ng sariling kopya ===== */


        body {


            color: #7a3157;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .box {
            background: white;
            padding: 24px 28px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .box h1 { margin-top: 0; }
        code { background: #f8edf4; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Sample accounts are ready</h1>
        <p>These accounts were created or updated successfully:</p>
        <ul>
            <li><strong>Owner:</strong> <code>owner@gmail.com</code> / <code>clayandstuffowner12345</code></li>
            <li><strong>Staff:</strong> <code>staff@gmail.com</code> / <code>clayandstuffstaff12345</code></li>
        </ul>
        <p>You can now log in from the login page.</p>
    </div>
</body>
</html>
