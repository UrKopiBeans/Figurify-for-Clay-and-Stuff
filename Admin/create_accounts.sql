-- default login accounts - hiwalay ito sa figurify_db.sql (schema lang yun)
-- paano gamitin: patakbuhin muna ang figurify_db.sql, tapos i-run ito sa phpMyAdmin (SQL tab, paste, Go)
-- ligtas paulit-ulit patakbuhin (ON DUPLICATE KEY UPDATE na lang, hindi mag-eerror)
-- kapag existing na ang owner at staff, napapalitan lang ang password nila
--
-- accounts:
--   owner@gmail.com -> admin  (password: clayandstuffowner12345)
--   staff@gmail.com -> staff  (password: clayandstuffstaff12345)

USE figurify_db;

INSERT INTO users (first_name, last_name, email, password, role)
VALUES
(
    'Figurify',
    'Owner',
    'owner@gmail.com',
    '$2y$10$/1xxeiM5aOTVTxXiJna4weHnws8tkhesENVswAgOEw28n7pYimc2O',
    'admin'
),
(
    'Figurify',
    'Staff',
    'staff@gmail.com',
    '$2y$10$5VZWpUx2BNtpvoYvo5YqDOqn2mALHOrXXljhVr3is9TuAa3WN9XF2',
    'staff'
)
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name  = VALUES(last_name),
    password   = VALUES(password),
    role       = VALUES(role);

-- tanggal na ang test customer account
DELETE FROM users WHERE email IN ('customer@gmail.com', 'customer@figurify.com');
