<?php

require_once __DIR__ . "/../helpers/security_helper.php";

// LOCALHOST (XAMPP) = Gmail
if (FIGURIFY_LOCAL) {
    return [
        "username"     => FIGURIFY_SECRETS["mail_local_username"] ?? "",
        "app_password" => FIGURIFY_SECRETS["mail_local_password"] ?? "",
        "from_name"    => "Clay and Stuff",
    ];
}

// PUBLIC (GoDaddy) = mailbox na no-reply@clayandstuff.com
return [
    "host"         => "mail.clayandstuff.com",
    "port"         => 465,
    "auth"         => true,
    "encryption"   => "ssl",
    "username"     => "no-reply@clayandstuff.com",
    "app_password" => FIGURIFY_SECRETS["mail_public_password"] ?? "",
    "from_email"   => "no-reply@clayandstuff.com",
    "from_name"    => "Clay and Stuff",
];
