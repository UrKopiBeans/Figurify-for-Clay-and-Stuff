<?php

/* =====================================================================
   content_helper.php  -  PINAGSAMANG HELPER PARA SA EDITING CONTENT
   =====================================================================
   Dating apat na file, isa na lang ngayon:
     - content_helper.php       (SECTION 1)
     - collection_helper.php    (SECTION 2)
     - avatar_helper.php        (SECTION 3)
     - payment_view_helper.php  (SECTION 4)
   Puwede nang i-delete ang collection_helper.php, avatar_helper.php at
   payment_view_helper.php.

   PAANO SILA GUMAGANA
   ---------------------------------------------------------------------
   SECTION 1 - SITE CONTENT (text at larawan ng Home page)
     Table: site_content (content_key => content_value).
     Ine-edit ng owner sa owner/content-management.php, ipinapakita sa Home.
     figurify_get_all_content() -> figurify_content($content, "key", "default")
     figurify_save_content() = save ng isang key
     figurify_resolve_image_src() = tamang src ng larawan
     figurify_sync_category_images() = tanggalin ang mga larawang hindi na ginagamit

   SECTION 2 - COLLECTION PAGE
     Table: collection_items (gagawin at lalagyan ng default na produkto kung wala pa).
     figurify_collection_get_grouped() = lahat ng produkto (normal / special)
     figurify_collection_filter_category() = salain by category
     figurify_collection_build_filter_tag() = tag ng card (hal. "normal chibi bestseller")
     figurify_collection_display_price() = presyo na may ₱ (tanggal ang "Starts at")

   SECTION 3 - AVATAR (profile picture)
     figurify_render_avatar() = larawan, o bilog na may kulay + unang letra kapag
     walang larawan o hindi ma-load.

   SECTION 4 - PAYMENT / SHIPPING VIEW (owner/staff pages at My Orders)
     figurify_render_pin_row(), figurify_render_payment_proofs(),
     figurify_render_shipping_receipt(). May sariling DB connection dahil may mga
     page na nagsasara na ng $conn bago mag-render.

   SECTION 5 - LEGAL PAGES (Terms and Conditions + Privacy Policy)
     Ine-edit ng owner sa owner/content-management.php?tab=legal (naka-save din sa site_content).
     figurify_legal_page() = laman ng page (naka-save o default)
     figurify_legal_keys() = lahat ng key na pwedeng i-save (para sa save_content.php)
     figurify_legal_html() = text -> HTML (**bold**, [text](link), {{naka-highlight}}, "- " = listahan)

   SECTION 6 - FAQ (Frequently Asked Questions sa Home)
     Ine-edit ng owner sa owner/content-management.php (naka-save din sa site_content).
     figurify_faq() = heading at 20 tanong (naka-save o default; blangkong tanong = nakatago)
     figurify_faq_keys() = lahat ng key na pwedeng i-save (para sa save_content.php)

   SECTION 7 - SECTION FILTER (owner/collection-management.php at owner/user-management.php)
     cs_section_filter() = HTML ng puting dropdown na pumipili kung aling section ang ipapakita.
     Ang JS nito ay nasa Shared/sidebar.php ([4] SECTION FILTER).

   SECTION 8 - PRESYO (owner/content-management.php?tab=prices)
     Iisang listahan ng presyo para sa Dress Up, Image Submission, server, FAQ at Home.
     figurify_site_prices() = lahat ng presyo | figurify_part_price() = presyo ng Dress Up item

   PAANO GAMITIN
     require_once __DIR__ . "/../helpers/content_helper.php";   (isang require lang, safe kahit ulit-ulitin)

   PAALALA: puro function lang ang file na ito - walang inilalabas at walang
   kumokonekta sa database hangga't hindi tinatawag ang function. Ang
   security_helper.php ay nilo-load lang sa loob ng SECTION 4 kapag kailangan na
   (para hindi madamay ang Home at Collection sa settings nito).
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : lahat ng nakikitang laman ng site na pwedeng i-edit: text ng Home,
                 listahan ng Collection, profile picture, at proof of payment.
   PARA SAAN   : para hindi na mag-code ang owner kapag may babaguhin sa itsura ng site.
   PANO NAGANA : ang text/larawan ay naka-save sa database. Kapag binuksan ang page,
                 kukunin ito ng helper; kapag wala pa, gagamitin ang default na nakasulat sa code.
   4 NA BAHAGI : SECTION 1 = Home content  | SECTION 2 = Collection page
                 SECTION 3 = Avatar        | SECTION 4 = Payment/Shipping proof view
   --------------------------------------------------------------------- */


/* =====================================================================
   SECTION 1 - SITE CONTENT (Home page)
   (dating content_helper.php)
   ===================================================================== */

/* ANO ITO: gumagawa ng table na site_content kung wala pa (safety net kung di pa na-run ang figurify_db.sql).
   Isang beses lang tatakbo kada page. */
function figurify_ensure_content_table(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS site_content (
            content_key    VARCHAR(60) NOT NULL PRIMARY KEY,
            content_value  TEXT NOT NULL,
            updated_by     INT NULL,
            updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    figurify_content_cleanup_legacy($conn);

    $ensured = true;
}


/* ===== LUMANG TEXT (kusa, isang beses lang) =====
   ANO ITO: binubura nito ang LUMANG wording na naka-save na sa database (galing sa dating bersyon ng Home
   text, carousel, FAQ at presyo) para ang bagong wording ng code ang lumabas. Hindi ginagalaw ang text na
   pinalitan mismo ng owner. Nagtatala ng marker na "legacy_cleanup_v1" sa site_content, kaya isang beses
   lang tatakbo kada database. Dahil dito, hindi na kailangang patakbuhin ng kahit sino ang
   update_home_text.sql at update_carousel_prices.sql. Sa bagong database ay walang matatamaan. */
function figurify_content_cleanup_legacy(mysqli $conn): void
{
    $marker = $conn->query("SELECT 1 FROM site_content WHERE content_key = 'legacy_cleanup_v1' LIMIT 1");

    if (!$marker || $marker->num_rows > 0) {
        return;
    }

    $oldValues = [
        "about_sign"     => ["From custom couple figures to life's special moments, we turn your stories into tiny works of art you can keep forever."],
        "about_text"     => ["We believe the best memories live in the smallest details. Every piece is shaped by hand, one at a time, and made to be a gift that is truly one of a kind for you and the people you love.", "The best memories live in small details. Each piece is shaped by hand, one at a time - a one-of-a-kind gift for you and the people you love."],
        "footer_tagline" => ["Made with little bits of joy. Every piece is sculpted by hand, with love and care, from your favorite moments."],
        "hero_tagline"   => ["Hand-sculpted polymer clay pieces made from your favorite moments - no two are ever exactly alike.", "Handmade clay figures from your favorite moments - no two are the same."],
        "how_note_text"  => ["to place an order - you can follow everything under My Orders."],
        "how_subtitle"   => ["Walang kailangang hulaan - we guide you from start to finish.", "No guesswork - we guide you from start to finish."],
        "slide2_tagline" => ["Send a photo, tell us your story, and we'll shape it into a little keepsake you can hold onto forever."],
        "social_facebook"  => ["https://facebook.com"],
        "social_instagram" => ["https://instagram.com"],
        "social_tiktok"    => ["https://tiktok.com"],
    ];

    try {
        $delete = $conn->prepare("DELETE FROM site_content WHERE content_key = ? AND content_value = ?");

        foreach ($oldValues as $key => $values) {
            foreach ($values as $value) {
                $delete->bind_param("ss", $key, $value);
                $delete->execute();
            }
        }

        $delete->close();

        $conn->query("DELETE FROM site_content WHERE content_key IN ('hero_image', 'slide2_image', 'slide3_image', 'style1_price', 'style2_price', 'style3_price')");

        $conn->query("
            UPDATE site_content
            SET content_value = REPLACE(REPLACE(REPLACE(content_value, 'costs PHP 50.', 'costs {figure_name}.'), 'costs ₱50.', 'costs {figure_name}.'),
                'costs {figure_name}.', 'costs {figure_name} for up to 8 letters, plus {figure_name_letter} for each extra letter.')
            WHERE content_key LIKE 'faq%\\_answer' AND (content_value LIKE '%costs ₱50.%' OR content_value LIKE '%costs PHP 50.%')
        ");

        $conn->query("UPDATE collection_items SET style = 'Box & Add-ons' WHERE style = 'Custom Box'");
    }
    catch (Throwable $error) {
        /* kapag wala pa ang collection_items o iba pang table, wala ring lumang data na lilinisin */
    }

    $conn->query("INSERT IGNORE INTO site_content (content_key, content_value) VALUES ('legacy_cleanup_v1', '1')");
}
/* ===== END LUMANG TEXT ===== */


/* GINAGAMIT NG: Home/Home.php, Shared/footer.php, owner/content-management.php
   Kinukuha lahat ng content bilang array (content_key => content_value). */
function figurify_get_all_content(mysqli $conn): array
{
    figurify_ensure_content_table($conn);

    $content = [];

    $result = $conn->query("SELECT content_key, content_value FROM site_content");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $content[$row["content_key"]] = figurify_show_text($row["content_value"]);
        }
    }

    return $content;
}


/* Value ng isang key, o $default kung wala pang na-save.
   Hal. figurify_content($content, "home_title", "Welcome") */
function figurify_content(array $content, string $key, string $default): string
{
    $value = $content[$key] ?? "";

    return $value !== "" ? $value : $default;
}


/* GINAGAMIT NG: owner/save_content.php
   Insert o update ng isang content key (kapag meron na ang key, update lang). */
function figurify_save_content(mysqli $conn, string $key, string $value, ?int $updatedBy = null): bool
{
    figurify_ensure_content_table($conn);

    $stmt = $conn->prepare("
        INSERT INTO site_content (content_key, content_value, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            content_value = VALUES(content_value),
            updated_by = VALUES(updated_by)
    ");

    $value = figurify_db_text($value);
    $stmt->bind_param("ssi", $key, $value, $updatedBy);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}


/* GINAGAMIT NG: Home page at Collection page (lahat ng larawang galing sa database)
   Pwedeng filename lang from /Image folder o buong URL (hal. Unsplash); $fallback kung walang laman.
   Ang lumang pangalan ng larawan ay inaayos ng figurify_image_path() (nasa dressup_helper.php). */
function figurify_resolve_image_src(string $value, string $fallback): string
{
    $value = trim($value);

    if ($value === "") {
        return $fallback;
    }

    if (preg_match("#^(https?:)?//#i", $value)) {
        return $value;
    }

    require_once __DIR__ . "/dressup_helper.php"; /* dito na ang figurify_image_path() */

    return "../Image/" . figurify_image_path($value);
}


/* GINAGAMIT NG: owner/save_content.php at owner/save_collection.php
   Nililinis ang Image/carousel o Image/collection - tinatanggal ang mga file na wala nang gamit sa DB.
   Safety net ito, tumatakbo tuwing Save; may 30 segundong grace period para di matanggal ang bagong upload lang. */
function figurify_sync_category_images(string $category, array $referencedValues): void
{
    $allowedCategories = ["carousel", "collection"];

    if (!in_array($category, $allowedCategories, true)) {
        return;
    }

    $dir = __DIR__ . "/../Image/" . $category;

    if (!is_dir($dir)) {
        return;
    }

    // listahan ng filenames na kasalukuyang ginagamit (base sa DB) - huwag tanggalin ang mga ito
    $keepBasenames = [];
    $prefix = $category . "/";

    foreach ($referencedValues as $value) {

        $value = trim((string) $value);

        if ($value === "" || preg_match("#^(https?:)?//#i", $value)) {
            continue;
        }

        $value = str_replace("\\", "/", $value);

        if (strpos($value, $prefix) === 0) {
            $keepBasenames[basename($value)] = true;
        }
    }

    $entries = @scandir($dir);

    if ($entries === false) {
        return;
    }

    $gracePeriod = 30; // segundo
    $now = time();

    // dito na tinatanggal - laktawan ang nasa listahan sa itaas at ang bagong-bago pa lang
    foreach ($entries as $entry) {

        if ($entry === "." || $entry === "..") {
            continue;
        }

        $path = $dir . "/" . $entry;

        if (!is_file($path) || isset($keepBasenames[$entry])) {
            continue;
        }

        $mtime = @filemtime($path);

        if ($mtime !== false && ($now - $mtime) < $gracePeriod) {
            continue; // baka kakabago lang i-upload, huwag pang galawin
        }

        @unlink($path);
    }
}


/* =====================================================================
   SECTION 2 - COLLECTION PAGE
   (dating collection_helper.php)
   ===================================================================== */

/* ANO ITO: gumagawa ng table na collection_items kung wala pa at nag-seed ng default na produkto at presyo
   (isang beses lang, kapag walang laman ang table). */
function figurify_collection_ensure_table(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS collection_items (
            item_id        INT AUTO_INCREMENT PRIMARY KEY,
            section        VARCHAR(20)  NOT NULL DEFAULT 'normal',
            category       VARCHAR(30)  NOT NULL DEFAULT 'chibi',
            style          VARCHAR(60)  NOT NULL DEFAULT '',
            type           VARCHAR(60)  NOT NULL DEFAULT '',
            size           VARCHAR(30)  NOT NULL DEFAULT '',
            price          VARCHAR(30)  NOT NULL DEFAULT '',
            image          VARCHAR(255) NOT NULL DEFAULT '',
            is_bestseller  TINYINT(1)   NOT NULL DEFAULT 0,
            sort_order     INT          NOT NULL DEFAULT 0,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $ensured = true;

    // seed default products kung bagong gawa pa lang ang table
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM collection_items");
    $count = $countResult ? (int) $countResult->fetch_assoc()["total"] : 0;

    if ($count > 0) {
        return;
    }

    $defaults = [
        // CHIBI
        ["normal",  "chibi",   "Chibi",       "Head-only Keychain", "2\"",   "₱450",   "figures/chibi/chibi-head-only-keychain.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Half-body Keychain", "2\"",   "₱500",   "figures/chibi/chibi-half-body-keychain.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Full-body Keychain", "2\"",   "₱650",   "figures/chibi/chibi-full-body-keychain.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Full-body Standee",  "5\"",   "₱900",   "figures/chibi/chibi-standee-5in.jpg", 1],
        ["normal",  "chibi",   "Chibi",       "Wedding Standee",    "5\"",   "₱1,200", "figures/chibi/chibi-full-body-standee.jpg", 0],
        // HIRONO
        ["normal",  "hirono",  "Hirono",      "Keychain",           "2\"",   "₱600",   "figures/hirono/hirono-keychain-2in-01.jpg", 0],
        ["normal",  "hirono",  "Hirono",      "Standee",            "3.5\"", "₱750",   "figures/hirono/hirono-standee-3.5in-04.jpg", 1],
        ["normal",  "hirono",  "Hirono",      "Couple Standee",     "3.5\"", "₱1,800", "figures/hirono/hirono-couple-standee-1.jpg", 0],
        ["normal",  "hirono",  "Hirono",      "Blind Box Set",      "3.5\"", "₱800",   "figures/hirono/hirono-blind-box-set-02.jpg", 0],
        // FUNKO POP
        ["normal",  "funko",   "Funko Pop",   "Full-body Standee",  "5\"",   "₱600",   "figures/funko-pop/funko-pop-full-body-standee.jpg", 0],
        ["normal",  "funko",   "Funko Pop",   "Boxed Solo Figure",  "5\"",   "₱1,000", "figures/funko-pop/funko-pop-17.jpg", 1],
        ["normal",  "funko",   "Funko Pop",   "Boxed Couple Set",   "5\"",   "₱1,500", "figures/funko-pop/funko-pop-12.jpg", 0],
        // SPECIAL
        ["special", "funko",   "Funko Pop",   "Custom Solo Figure", "Custom", "650",    "figures/funko-pop/funko-pop-09.jpg", 0],
        ["special", "special", "Pop Culture", "Custom Figure",      "Custom", "700",    "figures/chibi/chibi-head-keychain-spiderman.jpg", 0],
        ["special", "special", "Pets",        "Custom Pet Figure",  "Custom", "700",    "figures/funko-pop/funko-pop-pet.jpg", 0],
        ["special", "custom",  "Box & Add-ons",  "3D Box",             "Custom", "500",    "boxes/hirono-box-checkered.jpg", 0],
    ];

    $stmt = $conn->prepare("
        INSERT INTO collection_items
            (section, category, style, type, size, price, image, is_bestseller, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($defaults as $i => $row) {
        [$section, $category, $style, $type, $size, $price, $image, $bestseller] = $row;
        $sortOrder = $i;

        $stmt->bind_param(
            "sssssssii",
            $section,
            $category,
            $style,
            $type,
            $size,
            $price,
            $image,
            $bestseller,
            $sortOrder
        );

        $stmt->execute();
    }

    $stmt->close();
}


/* GINAGAMIT NG: Collection/collection.php at owner/collection-management.php
   Kinukuha lahat ng produkto, grouped by "section" (normal/special), naka-sort. */
function figurify_collection_get_grouped(mysqli $conn): array
{
    figurify_collection_ensure_table($conn);

    $grouped = ["normal" => [], "special" => []];

    $result = $conn->query("
        SELECT * FROM collection_items
        ORDER BY section ASC, sort_order ASC, item_id ASC
    ");

    if (!$result) {
        return $grouped;
    }

    while ($row = $result->fetch_assoc()) {
        $row["price"] = figurify_show_text((string) $row["price"]);
        $section = $row["section"] === "special" ? "special" : "normal";
        $grouped[$section][] = $row;
    }

    return $grouped;
}


/* Filter ng items base sa category (chibi/hirono/funko). */
function figurify_collection_filter_category(array $items, string $category): array
{
    return array_values(array_filter(
        $items,
        fn(array $item) => $item["category"] === $category
    ));
}


/* data-filter tag string (hal. "normal chibi bestseller"), ginagamit ng dropdown filter + JS sa Collection page. */
function figurify_collection_build_filter_tag(array $item): string
{
    $tags = [];
    $tags[] = $item["section"] === "special" ? "special" : "normal";

    if ($item["category"] !== "" && $item["category"] !== $tags[0]) {
        $tags[] = $item["category"];
    }

    if (!empty($item["is_bestseller"])) {
        $tags[] = "bestseller";
    }

    return implode(" ", array_unique($tags));
}


/* Presyo lang ang ipapakita (tanggal ang "Starts at" / "Base Price:"), laging may ₱ sa unahan. */
function figurify_collection_display_price(string $price): string
{
    $clean = preg_replace('/^\s*(?:starts?\s+at|starting\s+at|base\s+price\s*:?)\s*/iu', "", $price);
    $clean = str_replace(["₱", "&#8369;", "PHP", "Php"], "", (string) $clean);
    $clean = trim((string) preg_replace('/\s+/u', " ", $clean));
    // Ayusin ang lumang presyo sa database na nagsisimula sa ? (hal. ?450).
    $clean = preg_replace('/^\?\s*(?=\d)/', "", $clean);

    if ($clean !== "" && preg_match('/^[0-9]/', $clean)) {
        $clean = "₱" . $clean;
    }

    return $clean;
}


/* =====================================================================
   SECTION 3 - AVATAR / PROFILE PICTURE
   (dating avatar_helper.php)
   ===================================================================== */

if (!function_exists("figurify_avatar_initial")) {

    /* unang letra ng pangalan (malaki); "?" kung walang pangalan */
    function figurify_avatar_initial(string $name): string
    {
        $name = trim($name);

        if ($name === "") {
            return "?";
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }
}

if (!function_exists("figurify_avatar_color_class")) {

    /* kulay ng bilog - pareho palagi para sa parehong pangalan (galing sa crc32 ng pangalan).
       Class lang ang ibinabalik (avatar-color-0 ... avatar-color-7); nasa CSS ng page ang kulay:
       0 #e77fbd, 1 #b9a0ef, 2 #f472b6, 3 #9b3f72, 4 #db2777, 5 #a88be5, 6 #831843, 7 #c026d3 */
    function figurify_avatar_color_class(string $name): string
    {
        return "avatar-color-" . (crc32($name) % 8);
    }
}

if (!function_exists("figurify_render_avatar")) {

    /* GINAGAMIT NG: Shared/navbar.php, Home/Home.php, Profile/profile.php
       Nagbabalik ng HTML (img o initials div) para sa .profile-avatar.
       FLOW: may picture = <img>; kung hindi ma-load, awtomatikong napapalitan ng initials;
             walang picture = initials agad.
       $siteBase = prefix na idadagdag sa local uploaded photo paths. */
    function figurify_render_avatar(string $name, ?string $picture, string $altText = "Profile", string $siteBase = ""): string
    {
        $picture = trim((string) $picture);

        $initial = figurify_avatar_initial($name);
        $colorClass = figurify_avatar_color_class($name);

        if ($picture !== "") {

            $isFullUrl = (stripos($picture, "http://") === 0 || stripos($picture, "https://") === 0);
            $src       = $isFullUrl ? $picture : ($siteBase . $picture);

            /* Kung hindi ma-load ang naka-save na larawan (na-expire,
               blocked, o burado na), awtomatikong papalitan ng
               colored-initials avatar sa halip na broken image icon.
               (gumagawa ng div gamit ang JS para hindi masira ang quotes sa onerror) */
            $fallbackJs = "var d=document.createElement('div');"
                . "d.className=" . json_encode("avatar-initials " . $colorClass) . ";"
                . "d.textContent=" . json_encode($initial) . ";"
                . "this.onerror=null;this.replaceWith(d);";

            return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($altText) . '" '
                 . 'referrerpolicy="no-referrer" '
                 . 'onerror="' . htmlspecialchars($fallbackJs, ENT_QUOTES) . '">';
        }

        return '<div class="avatar-initials ' . $colorClass . '">'
             . htmlspecialchars($initial)
             . '</div>';
    }
}


/* =====================================================================
   SECTION 4 - PAYMENT / SHIPPING VIEW
   (dating payment_view_helper.php)
   ===================================================================== */

if (!function_exists("figurify_payment_view_connection")) {

    /* ANO ITO: sariling DB connection (isang beses lang bubuksan). Para sa mga page na nagsasara na ng $conn bago mag-render. */
    function figurify_payment_view_connection()
    {
        static $connection = null;

        if ($connection !== null) {
            return $connection;
        }

        /* iisang database login para sa buong system (FIGURIFY_DB_* sa security_helper.php) */
        require_once __DIR__ . "/security_helper.php";

        $connection = figurify_security_db() ?? false;

        return $connection;
    }


    /* ANO ITO: kinukuha ang pin address, proof ng payment/balance at shipping receipt ng isang order.
       Tatlong query ang sinusubukan (mula pinakakumpleto) kung sakaling wala pa ang ibang column sa DB.
       Naka-cache kada order kaya isang beses lang mag-query. */

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
                "SELECT pin_address, payment_proof, balance_proof, balance_payment_reference, balance_paid_at, shipping_receipt, payment_option
                 FROM orders WHERE order_id = ? LIMIT 1",
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


    /* GINAGAMIT NG: owner/staff order pages - "Pin Address:" na row sa loob ng .staff-payment-details-grid */

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


    /* GINAGAMIT NG: My Orders at owner/staff order pages
       Proof of Payment - Down Payment at Balance Payment, magkatabi.
       $variant: "staff" (owner/staff pages) o "customer" (My Orders) */

    function figurify_render_payment_proofs(int $orderId, string $variant = "staff"): void
    {
        require_once __DIR__ . "/security_helper.php"; /* para sa figurify_proof_url() */

        $extras = figurify_payment_extras($orderId);

        $proofs = [];

        if (!empty($extras["payment_proof"])) {
            $proofs[] = [(($extras["payment_option"] ?? "half") === "full") ? "Full Payment" : "Down Payment", (string) $extras["payment_proof"]];
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
                            onclick="openImageLightbox('<?php echo htmlspecialchars($url, ENT_QUOTES, "UTF-8"); ?>', '<?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?> for Order #<?php echo (int) $orderId; ?>')"
                        >
                        <figcaption><?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }


    /* GINAGAMIT NG: My Orders at owner/staff order pages
       Shipping receipt (resibo ng courier) - kung meron. */

    function figurify_render_shipping_receipt(int $orderId, string $variant = "staff"): void
    {
        require_once __DIR__ . "/security_helper.php"; /* para sa figurify_proof_url() */

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
                        onclick="openImageLightbox('<?php echo htmlspecialchars($url, ENT_QUOTES, "UTF-8"); ?>', 'Shipping Receipt for Order #<?php echo (int) $orderId; ?>')"
                    >
                </figure>
            </div>
        </div>
        <?php
    }


}


/* =====================================================================
   SECTION 5 - LEGAL PAGES (Terms and Conditions + Privacy Policy)
   Ginagamit ng: Legal/terms-and-conditions.php, Legal/privacy-policy.php,
   owner/content-management.php?tab=legal, owner/save_content.php
   Key ng bawat field: "<page>_eyebrow", "<page>_title", "<page>_subtitle", "<page>_intro",
   "<page>_note", "<page>_sec<N>_title", "<page>_sec<N>_body"   (page = terms / privacy)
   ===================================================================== */

/* ANO ITO: orihinal na laman ng dalawang legal page (ginagamit hangga't walang na-save ang owner).
   Sa body: blank line = bagong talata, "- " = listahan, **bold**, [text](link), {{highlight}} */
function figurify_legal_defaults(): array
{
    return [
            'terms' => [
                'eyebrow' => 'ACCOUNT, ORDERS & PAYMENTS',
                'title' => 'Terms and Conditions',
                'subtitle' => 'The rules for your account, the website, and every order you place.',
                'intro' => 'Please read these terms before you sign up or place an order with Clay and Stuff. For how we handle your personal information, see the [Privacy Policy](privacy-policy.php).',
                'note' => 'This page explains how the shop and the website work. It is not legal advice.',
                'sections' => [
                    [
                        'id' => 'sec-1',
                        'title' => 'Acceptance of Terms',
                        'body' => 'When you create an account, place an order, or use this website, you agree to these Terms and Conditions and to the [Privacy Policy](privacy-policy.php). If you do not agree, please do not use the website.',
                    ],
                    [
                        'id' => 'sec-2',
                        'title' => 'Who Can Use This Website',
                        'body' => 'You must be at least 18 years old to create an account and place an order. If you are younger, a parent or guardian must agree to it first.',
                    ],
                    [
                        'id' => 'sec-3',
                        'title' => 'Your Account',
                        'body' => 'Sign up with your real name and an email address that you use. Keep these details updated in My Profile. One person can only have one account.',
                    ],
                    [
                        'id' => 'sec-4',
                        'title' => 'Account Security',
                        'body' => 'Keep your password to yourself. Anything done using your account is your responsibility. After several wrong password attempts, the account is locked for a short time to keep it safe. If you think someone else used your account, email Clay and Stuff right away.',
                    ],
                    [
                        'id' => 'sec-5',
                        'title' => 'Acceptable Use',
                        'body' => 'Do not try to open another person\'s account, send fake or repeated orders, upload edited proof of payment, or do anything that stops the website from working normally. To avoid spam, you can only have three (3) unpaid orders at the same time.',
                    ],
                    [
                        'id' => 'orders',
                        'title' => 'Placing a Commission Order',
                        'body' => '- First, choose the order type. A **Rush Order** can be booked on any open date at least 7 days of processing time from today and has a rush fee. A **Non-Rush Order** ships on the last day of the month.
- Next, choose how to order. In **Dress Up**, you design the figure on the 3D model and the price shows right away. In **Image Submission**, you upload reference photos and the staff checks your request first.
- For Image Submission, the staff will either send a price quotation or decline the request with a reason. A quotation is good for **2 days**. If you do not continue within 2 days, the order is cancelled.
- Slots are limited and go to whoever pays first. Your slot is only reserved after you send the down payment.
- **Rush Orders** are limited to 2 per day and 10 per month, with a rush fee of ₱500 per figure, or ₱200 for a Head Only Keychain. **Non-Rush Orders** are limited to 15 per ship out date. If your date becomes fully booked before your payment is approved, you can pick a new date in My Orders.
- The prices on the website are **starting prices**. The final price still depends on the design, size, and extras you pick.
- Send a clear reference photo and complete details so the figure comes out the way you want it.
- You can cancel your own order in **My Orders** as long as it is not paid yet.',
                    ],
                    [
                        'id' => 'sec-7',
                        'title' => 'Payment',
                        'body' => '- Before work starts, you can pay a **50% down payment** or the **full amount**. If you pay the down payment, the system computes the **remaining balance**, and you pay it before the order ships. If you pay in full, there is nothing left to pay.
- You can pay through GCash or BPI. Every payment needs the reference number and a clear screenshot or photo of the receipt.
- The staff first checks if your order details and payment details are complete. If something is wrong, the reason will show in My Orders and you can send it again.
- After that, the owner confirms that the money was received before the order moves forward. Sending fake or edited proof of payment will get the order cancelled.',
                    ],
                    [
                        'id' => 'sec-8',
                        'title' => 'Production, Progress Updates and Revision',
                        'body' => 'Every figure is handmade from your references and choices, so it will not be a perfect copy. Small differences in color, size, or tiny details are normal and are not considered defects. While your figure is being made, you will get progress photos. You can approve a photo or ask for **one (1) revision** for the whole order, and the staff will accept it if the change can still be done. If you do not answer a progress photo within **2 days**, it is approved automatically. If the figure is based on a character or artwork you do not own, it is for personal use only and not for selling.',
                    ],
                    [
                        'id' => 'sec-9',
                        'title' => 'Shipping and Receiving Your Order',
                        'body' => 'Clay and Stuff is in Calamba, Laguna. Orders are sent through LBC, or Lalamove for same-day delivery. The shipping fee is **not included** in the order total and is paid to the courier. When your order is shipped, My Orders will show the final photo, tracking number, shipping fee, and courier receipt. If the courier cannot find your address, the staff will send the order back to you in My Orders so you can fix the shipping details. When the order arrives, click **Order Received**. If you forget, the order is marked completed 7 days after it was shipped. Delivery time depends on the courier, but if your order gets damaged on the way, we will help you file a claim.',
                    ],
                    [
                        'id' => 'sec-10',
                        'title' => 'Cancellations and Refunds',
                        'body' => '- **Before the payment is approved:** you can cancel an unpaid order anytime in My Orders. If a payment cannot be accepted because the order cannot be made, the order is cancelled and moved to refund.
- **Down payment:** the down payment holds your schedule and the materials for your figure, so it is **not refundable** if you cancel after the order is approved. It will be refunded if we cannot make the order, if the ship out date becomes full, or if there is a delivery problem.
- **After work has started:** the order can no longer be cancelled for a full refund. A partial refund may be given, minus the materials and work already done.
- **Wrong or defective order:** send photos within 7 days after you receive it. The shop may repair it, make a new one, or give a refund.
- **Damaged during shipping:** send photos of the box and the figure within 48 hours after delivery.
- Refunds are sent to the refund account you entered on the payment page, with a reference number and proof of refund. This usually takes {{7 to 14 business days}}. Clay and Stuff is not responsible for refunds sent to a wrong account that you entered.',
                    ],
                    [
                        'id' => 'sec-11',
                        'title' => 'Reviews',
                        'body' => 'Only customers with a completed order can leave a review for that order, either in My Orders or through the review link sent by email. Please keep reviews honest. Reviews that are rude, fake, or not about a real order may be removed.',
                    ],
                    [
                        'id' => 'sec-12',
                        'title' => 'Intellectual Property',
                        'body' => 'The Clay and Stuff name, logo, and the content of this website (catalog photos, figure style designs, text, and layout) belong to Clay and Stuff or are used with permission. Do not copy them for business use without written permission. Reference photos you upload are still yours and are only used to make your order.',
                    ],
                    [
                        'id' => 'sec-13',
                        'title' => 'Suspension of Accounts',
                        'body' => 'An account that breaks these terms, for example through fraud, spam orders, abuse, or fake information, may be suspended or banned. A banned email cannot log in or sign up again. An order that is already being made is not cancelled because of this, except as explained in Section 10.',
                    ],
                    [
                        'id' => 'sec-14',
                        'title' => 'Limitation of Liability',
                        'body' => 'As far as the law allows, the most Clay and Stuff will pay for a problem with an order is the amount you paid for that order. The shop is not liable for other indirect losses.',
                    ],
                    [
                        'id' => 'sec-15',
                        'title' => 'Governing Law',
                        'body' => 'These terms follow the laws of the Philippines, including the Consumer Act of the Philippines (RA 7394) and the Electronic Commerce Act (RA 8792). Your rights under the Data Privacy Act of 2012 (RA 10173) are explained in the [Privacy Policy](privacy-policy.php).',
                    ],
                    [
                        'id' => 'sec-16',
                        'title' => 'Changes to These Terms',
                        'body' => 'These terms may be updated from time to time. If you keep using the website after a change is posted, it means you accept the new terms.',
                    ],
                    [
                        'id' => 'sec-17',
                        'title' => 'Contact',
                        'body' => 'For questions about these terms or about an order, email {{clayandstuff@gmail.com}}.',
                    ],
                ],
            ],
            'privacy' => [
                'eyebrow' => 'YOUR INFORMATION',
                'title' => 'Privacy Policy',
                'subtitle' => 'What we collect, why we need it, and how we keep it safe.',
                'intro' => '',
                'note' => 'This page explains how the shop and the website work. It is not legal advice.',
                'sections' => [
                    [
                        'id' => 'sec-1',
                        'title' => 'Who We Are',
                        'body' => 'Clay and Stuff ("we") runs this website to take custom clay figure commissions from customers in the Philippines. This page explains what personal information we collect, why we need it, how we use and protect it, and what you can ask Clay and Stuff to do with it.

Business address: {{Brgy. Palingon, Calamba City, Laguna}} · Email: {{clayandstuff@gmail.com}} · Contact number: {{+63 910 281 4331}}',
                    ],
                    [
                        'id' => 'sec-2',
                        'title' => 'Information We Collect',
                        'body' => 'When you sign up, order, or message the shop, we may collect:

- **Account details:** your full name, email address, and password. The password is saved in hashed form, never as plain text.
- **Order details:** the figure style, size, add-ons, box options, and the reference photos you upload.
- **Shipping details:** the receiver\'s name, address, and contact number.
- **Payment details:** the reference number and the screenshot or photo of your GCash or bank receipt. We do **not** ask for or keep your card number, CVV, or online banking password. Those stay with your bank or e-wallet.
- **Refund details:** the account name, account number, and method (for example GCash or BPI) where you want a refund sent.
- **Reviews:** the rating, comment, and photos you post about a completed order.
- **Security records:** failed log-in attempts, verification code requests, and a record of important actions on orders. These are only used to protect accounts and stop spam or fake orders.
- **Emails:** messages sent to you for account verification, order updates, and notifications.',
                    ],
                    [
                        'id' => 'sec-3',
                        'title' => 'How We Use Your Information',
                        'body' => '- To create and manage your account.
- To make and ship your order.
- To check your payment and send a refund when needed.
- To send you updates about your order and your account.
- To answer your questions.
- To improve the website, the catalog, and the ordering process.',
                    ],
                    [
                        'id' => 'sec-4',
                        'title' => 'How We Store and Protect Your Information',
                        'body' => 'Your information is saved in the shop\'s database. Only the owner and the staff can open it, and only for the reasons listed above. Passwords are hashed before they are saved. Uploaded photos (reference photos and payment screenshots) are kept on the server and can only be seen in your own account or by the staff checking your order.',
                    ],
                    [
                        'id' => 'sec-5',
                        'title' => 'Who We Share Information With',
                        'body' => 'We do not sell your personal information. Some of it is shared only when needed:

- The email service, only to send verification codes and order notifications.
- The courier (LBC or Lalamove), only the name, address, and contact number needed to deliver your order.
- Government offices, only when the law requires it.',
                    ],
                    [
                        'id' => 'sec-6',
                        'title' => 'Cookies',
                        'body' => 'The website uses a basic session cookie to keep you logged in while you browse. If you tick **Remember me** when you log in, a secure cookie keeps you logged in on that device for up to 30 days. Logging out removes it. There are no advertising or tracking cookies from other companies. If that changes, this page will be updated first, and you will be asked to agree when needed.',
                    ],
                    [
                        'id' => 'sec-7',
                        'title' => 'Your Rights',
                        'body' => 'Under the Data Privacy Act of 2012 (Republic Act No. 10173), you have the right to:

- Know that your personal data is being collected and used (this page).
- See the personal data we have about you.
- Ask to correct data that is wrong.
- Ask to delete your data, except records that must be kept, like order and payment records.
- Say no to some uses of your data.

To do any of these, email {{clayandstuff@gmail.com}}.',
                    ],
                    [
                        'id' => 'sec-8',
                        'title' => 'How Long We Keep Data',
                        'body' => 'Account and order information is kept while your account is active, and for a reasonable time after that for records, disputes, and legal requirements.',
                    ],
                    [
                        'id' => 'sec-9',
                        'title' => 'Children\'s Privacy',
                        'body' => 'This website is not made for children under 13. If we find out that a child under 13 gave personal information without a parent\'s permission, it will be deleted.',
                    ],
                    [
                        'id' => 'sec-10',
                        'title' => 'Changes to This Policy',
                        'body' => 'This Privacy Policy may be updated from time to time. When there is a big change, the "Last updated" date at the top of this page will change too.',
                    ],
                ],
            ],
        ];
}


/* ANO ITO: laman ng isang legal page = naka-save sa Content Management, o default kung wala pa */
function figurify_legal_page(array $content, string $page): array
{
    $data = figurify_legal_defaults()[$page];

    foreach (["eyebrow", "title", "subtitle", "intro", "note"] as $field) {
        $data[$field] = figurify_content($content, $page . "_" . $field, $data[$field]);
    }

    foreach ($data["sections"] as $i => $section) {
        $n = $i + 1;
        $data["sections"][$i]["title"] = figurify_content($content, $page . "_sec" . $n . "_title", $section["title"]);
        $data["sections"][$i]["body"]  = figurify_content($content, $page . "_sec" . $n . "_body", $section["body"]);
    }

    return $data;
}


/* ANO ITO: lahat ng key ng legal pages - listahan ng pinapayagang i-save sa save_content.php */
function figurify_legal_keys(): array
{
    $keys = [];

    foreach (figurify_legal_defaults() as $page => $data) {
        foreach (["eyebrow", "title", "subtitle", "intro", "note"] as $field) {
            $keys[] = $page . "_" . $field;
        }
        foreach (array_keys($data["sections"]) as $i) {
            $keys[] = $page . "_sec" . ($i + 1) . "_title";
            $keys[] = $page . "_sec" . ($i + 1) . "_body";
        }
    }

    return $keys;
}


/* ANO ITO: isang linya ng text -> ligtas na HTML: **bold**, {{highlight}}, [text](link) */
function figurify_legal_inline(string $text): string
{
    $html = htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\{\{(.+?)\}\}/', '<span class="legal-placeholder">$1</span>', $html);

    /* link: http(s), mailto, o page sa loob ng site lang (walang javascript:) */
    return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $url = $m[2];
        if (!preg_match('#^(https?://|mailto:|[A-Za-z0-9_\-./]+(\#[A-Za-z0-9_\-]+)?$|\#[A-Za-z0-9_\-]+$)#', html_entity_decode($url))) {
            return $m[1];
        }
        return '<a href="' . $url . '">' . $m[1] . '</a>';
    }, $html);
}


/* ANO ITO: buong body ng section -> HTML. Blank line = bagong talata; mga linyang "- " = listahan */
function figurify_legal_html(string $text): string
{
    $out = "";

    foreach (preg_split('/\n\s*\n/', trim(str_replace("\r", "", $text))) as $block) {
        $lines = array_values(array_filter(array_map("trim", explode("\n", $block)), "strlen"));

        if (!$lines) {
            continue;
        }

        if (count(array_filter($lines, fn($line) => strpos($line, "- ") === 0)) === count($lines)) {
            $out .= "<ul>";
            foreach ($lines as $line) {
                $out .= "<li>" . figurify_legal_inline(substr($line, 2)) . "</li>";
            }
            $out .= "</ul>";
        } else {
            $out .= "<p>" . figurify_legal_inline(implode(" ", $lines)) . "</p>";
        }
    }

    return $out;
}
/* ====================== END SECTION 5 - LEGAL PAGES ====================== */


/* =====================================================================
   SECTION 6 - FAQ (Frequently Asked Questions sa Home)
   Ginagamit ng: Home/Home.php, owner/content-management.php, owner/save_content.php
   Key ng bawat field: "faq_eyebrow", "faq_heading", "faq_subtitle",
   "faq<N>_question", "faq<N>_answer"   (N = 1 hanggang 20)
   Blangkong tanong = hindi lalabas sa Home (puwedeng burahin o idagdag ng owner).
   ===================================================================== */

/* ANO ITO: orihinal na laman ng FAQ (ginagamit hangga't walang na-save ang owner).
   20 puwesto: 18 may laman (batay sa system, Chapter 1 at Terms and Conditions), 2 bakante para sa bagong tanong ng owner. */
function figurify_faq_defaults(): array
{
    return [
        "eyebrow"  => "FAQ",
        "heading"  => "Frequently asked questions",
        "subtitle" => "Everything you need to know about ordering, payment, production and shipping.",
        "items"    => [
            /* ACCOUNT AT PAG-ORDER */
            ["Do I need an account to order?", "You can view the Overview and Collections pages without an account. To order, sign up with your name and email, enter the verification code sent to your email, and log in. If you forget your password, use Forgot Password on the Log In form."],
            ["How do I place an order?", "Log in, open Commission, and choose a Rush or Non-Rush order. Then order through Dress Up or Image Submission. You can follow every step of your order in My Orders. Each account can have up to 3 unpaid orders at a time, so pay or cancel one in My Orders before placing another."],
            ["What is the difference between Dress Up and Image Submission?", "In Dress Up, you design your figure on a 3D model and see the price right away. In Image Submission, you upload up to 5 reference images per figure, and our staff sends you a price quotation."],
            ["What styles and types can I order?", "You can order Funko Pop as a Full Body Standee. Hirono and Chibi come as a Full Body Standee, or as a Full Body, Half Body or Head Only Keychain. See the Collections page for the starting prices."],
            ["What is the difference between Rush and Non-Rush orders?", "A Rush order ships on any open date you choose, at least 7 days from today, and has a rush fee of ₱500 per figure, or ₱200 for a Head Only Keychain. A Non-Rush order ships on the last day of the month, must be booked at least 7 days before, and has no rush fee."],
            ["Are there extra fees?", "Listed prices are starting rates; the final price depends on your design, size, and extras. The rush fee is ₱500 per figure, or ₱200 for a Head Only Keychain. In Image Submission, adding a name to your figure costs {figure_name} for up to 8 letters, plus {figure_name_letter} for each extra letter. The shipping fee is shown in My Orders when your order ships."],
            ["Why can't I choose some dates?", "Rush orders are limited to 2 per day and 10 per month. Non-Rush orders are limited to 15 per ship-out date. Slots are first come, first served and are only held once you submit your down payment. If your date becomes full before you pay, you can pick a new date in My Orders. If it becomes full after you pay, you can pick a new date or cancel and request a refund."],
            ["How long is the price quotation valid?", "The quotation is valid for 2 days. If you do not accept it in time, the order is cancelled automatically."],
            ["Why was my order returned or declined?", "If your details are wrong or unclear, our staff returns the order as Needs Correction so you can fix it in My Orders. If the request cannot be made, it is declined with a reason that you can view in My Orders. Even after you have paid, you can fix wrong order details in My Orders; your payment is kept."],
            /* BAYAD */
            ["How do I pay?", "Pay through GCash or BPI by scanning the QR code, then enter the reference number and upload a clear screenshot of the receipt. You can pay a 50% down payment or the full amount. The balance must be paid before shipping."],
            ["How is my payment checked?", "Our staff first checks that your details and payment information are complete. The owner then confirms that the payment was received. If something is wrong, you will see the reason in My Orders and can submit it again."],
            ["Can I cancel my order?", "You can cancel an unpaid order anytime in My Orders. After you pay, only the owner can cancel your order, except when your ship-out date becomes full, where you can cancel and request a refund yourself. After production starts, an order can no longer be cancelled for a full refund."],
            /* PAGGAWA AT PAGPAPADALA */
            ["Can I see my figure while it is being made?", "Yes. You will receive progress photos in My Orders. You can approve a photo or request one (1) revision per order. If you do not respond within 2 days, the photo is approved automatically."],
            ["How will my order be shipped?", "Orders are shipped through LBC or Lalamove. You will see the final photo, tracking number, shipping fee and courier receipt in My Orders. Click Order Received when it arrives, or Report a problem with a note and a photo if something is wrong. If you do nothing, it is completed automatically 7 days after shipping."],
            ["How do refunds work?", "The down payment reserves your schedule and materials, so it is non-refundable if you cancel after your order is approved. It is refunded when we cannot make your order, when your ship-out date becomes full, or when there is a delivery problem. Refunds are sent to the refund account you gave on the payment page, with a reference number and proof of refund, usually within 7 to 14 business days. For a defective or incorrect order, contact us within 7 days of receiving it; for damage during shipping, within 48 hours. Please include photos."],
            /* IBA PA */
            ["How will I get updates about my order?", "Every change in your order is shown in My Orders and in your notifications. Important updates, such as your quotation, payment status and shipping details, are also sent to your email."],
            ["Can I leave a review?", "Yes. Once your order is completed, you can rate it and add up to 5 photos in My Orders or through the review link sent to your email."],
            ["I have other questions. How can I contact you?", "Send us a message on our Facebook page and we will reply as soon as we can."],
            ["", ""],
            ["", ""],
        ],
    ];
}


/* ANO ITO: laman ng FAQ = naka-save sa Content Management, o default kung wala pa.
   Ang tanong at sagot ay kinukuha kahit blangko (para maitago ng owner ang tanong). */
function figurify_faq(array $content): array
{
    $data = figurify_faq_defaults();

    foreach (["eyebrow", "heading", "subtitle"] as $field) {
        $data[$field] = figurify_content($content, "faq_" . $field, $data[$field]);
    }

    foreach ($data["items"] as $i => [$question, $answer]) {
        $n = $i + 1;
        $data["items"][$i] = [
            "question" => $content["faq{$n}_question"] ?? $question,
            "answer"   => $content["faq{$n}_answer"] ?? $answer,
        ];
    }

    return $data;
}


/* ANO ITO: lahat ng key ng FAQ - listahan ng pinapayagang i-save sa save_content.php */
function figurify_faq_keys(): array
{
    $keys = ["faq_eyebrow", "faq_heading", "faq_subtitle"];

    foreach (array_keys(figurify_faq_defaults()["items"]) as $i) {
        $keys[] = "faq" . ($i + 1) . "_question";
        $keys[] = "faq" . ($i + 1) . "_answer";
    }

    return $keys;
}
/* ====================== END SECTION 6 - FAQ ====================== */


/* =====================================================================
   SECTION 7 - SECTION FILTER (Collection at User Management)
   ANO ITO: puting dropdown na pumipili kung aling section ang ipapakita (isa lang bawat oras).
   Gamit:
     1. <body class="sf-fixed">
     2. echo cs_section_filter("pangalan-ng-page", [ ["section-id", "Label", bilang], ... ]);
   Ang JS na nagtatago/nagpapakita ng section ay nasa Shared/sidebar.php ([4] SECTION FILTER).
   ===================================================================== */
function cs_section_filter(string $key, array $options, string $extraHtml = ""): string
{
    $esc = function ($v) {
        return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8");
    };

    $html  = '<div class="sf-bar">';
    $html .= '<div class="sf-dropdown" data-sf-key="' . $esc($key) . '">';
    $html .= '<button type="button" class="sf-trigger" aria-haspopup="listbox" aria-expanded="false">';
    $html .= '<span class="sf-label"></span>';
    $html .= '<svg class="sf-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>';
    $html .= '</button>';
    $html .= '<div class="sf-menu" role="listbox">';

    foreach ($options as $i => $opt) {
        $text = $opt[1] . " (" . (int) $opt[2] . ")";
        $html .= '<button type="button" class="sf-option' . ($i === 0 ? ' active' : '') . '"'
            . ' role="option" data-target="' . $esc($opt[0]) . '" data-text="' . $esc($text) . '">'
            . $esc($text) . '</button>';
    }

    $html .= '</div></div>';
    $html .= $extraHtml;
    $html .= '</div>';

    return $html;
}
/* ====================== END SECTION 7 - SECTION FILTER ====================== */


/* =====================================================================
   SECTION 8 - PRESYO (Figure Style, Product Type, Size, Figure Name, Box & Add-ons, Dress Up outfit)
   Ine-edit ng owner sa owner/content-management.php?tab=prices (naka-save sa site_content).
   Iisang listahan lang ito, kaya kapag binago ng owner, sabay-sabay nagbabago ang:
     Dress Up (Commission/dress-up.php + dress-up.js), Image Submission (image-submission.php + .js),
     kuwenta ng server (Commission/submit_order.php), FAQ ({figure_name} atbp.) at "Starting at" sa Home.
   Mga function:
     figurify_price_fields()      = lahat ng presyo: key => [grupo, label, default, lugar sa config]
     figurify_price_value()       = presyo ng isang key (naka-save, o default kung wala / mali)
     figurify_price_config()      = presyo na naka-ayos (products / figure_name / funko_box / blind_box / blind_extras)
     figurify_price_tokens()      = {figure_name} => "₱50" atbp. (para sa FAQ sa Home)
     figurify_style_start_price() = pinakamababang presyo ng isang Figure Style ("Starting at" sa Home)
     figurify_site_prices()       = figurify_price_config() galing database (isang beses lang kada page)
     figurify_price_json_tag()    = <script id="figurifyPrices"> para sa dress-up.js at image-submission.js
     figurify_part_price_key() / figurify_part_prices() / figurify_part_price()
                                  = presyo ng hair / top / bottom / shoes / hat sa Dress Up
                                    (blangko = walang presyo; 0 sa code, walang lumalabas sa UI)
   ===================================================================== */

/* ANO ITO: lahat ng presyo. Ang huling laman = kung saan ilalagay sa figurify_price_config(). */
function figurify_price_fields(): array
{
    return [
        /* FUNKO POP - Full Body Standee lang ang meron */
        "price_funko_standee_3"    => ["Funko Pop", "Full Body Standee - 3 inches",   900,  ["products", "Funko Pop", "Full Body Standee", "sizes", "3"]],
        "price_funko_standee_4"    => ["Funko Pop", "Full Body Standee - 4 inches",   1200, ["products", "Funko Pop", "Full Body Standee", "sizes", "4"]],
        "price_funko_standee_5"    => ["Funko Pop", "Full Body Standee - 5 inches",   1500, ["products", "Funko Pop", "Full Body Standee", "sizes", "5"]],

        /* HIRONO */
        "price_hirono_standee_2"   => ["Hirono", "Full Body Standee - 2 inches",      600,  ["products", "Hirono", "Full Body Standee", "sizes", "2"]],
        "price_hirono_standee_3_5" => ["Hirono", "Full Body Standee - 3.5 inches",    950,  ["products", "Hirono", "Full Body Standee", "sizes", "3.5"]],
        "price_hirono_keychain_2"  => ["Hirono", "Full Body Keychain - 2 inches",     600,  ["products", "Hirono", "Full Body Keychain", "sizes", "2"]],
        "price_hirono_half"        => ["Hirono", "Half Body Keychain",                400,  ["products", "Hirono", "Half Body Keychain", "price"]],
        "price_hirono_head"        => ["Hirono", "Head Only Keychain",                300,  ["products", "Hirono", "Head Only Keychain", "price"]],

        /* CHIBI */
        "price_chibi_standee_2"    => ["Chibi", "Full Body Standee - 2 inches",       500,  ["products", "Chibi", "Full Body Standee", "sizes", "2"]],
        "price_chibi_standee_3"    => ["Chibi", "Full Body Standee - 3 inches",       680,  ["products", "Chibi", "Full Body Standee", "sizes", "3"]],
        "price_chibi_standee_4"    => ["Chibi", "Full Body Standee - 4 inches",       900,  ["products", "Chibi", "Full Body Standee", "sizes", "4"]],
        "price_chibi_standee_5"    => ["Chibi", "Full Body Standee - 5 inches",       1200, ["products", "Chibi", "Full Body Standee", "sizes", "5"]],
        "price_chibi_keychain_2"   => ["Chibi", "Full Body Keychain - 2 inches",      500,  ["products", "Chibi", "Full Body Keychain", "sizes", "2"]],
        "price_chibi_half"         => ["Chibi", "Half Body Keychain",                 350,  ["products", "Chibi", "Half Body Keychain", "price"]],
        "price_chibi_head"         => ["Chibi", "Head Only Keychain",                 250,  ["products", "Chibi", "Head Only Keychain", "price"]],

        /* FIGURE NAME (Image Submission lang) */
        "price_figure_name"        => ["Figure Name", "Figure Name (up to 8 letters)",  50, ["figure_name"]],
        "price_figure_name_letter" => ["Figure Name", "Each letter after 8",            2,  ["figure_name_letter"]],

        /* CUSTOM BOX: Funko box, Hirono blind box at extras */
        "price_box_solo"           => ["Box & Add-ons", "Funko Solo Box",                500,  ["funko_box", "solo"]],
        "price_box_couple"         => ["Box & Add-ons", "Funko Couple Box",              800,  ["funko_box", "couple"]],
        "price_blind_regular"      => ["Box & Add-ons", "Hirono Regular Blind Box",      150,  ["blind_box", "regular"]],
        "price_blind_set"          => ["Box & Add-ons", "Hirono Blind Box Set",          350,  ["blind_box", "set"]],
        "price_extra_tear_paper"   => ["Box & Add-ons", "Extra: Tear Blind Paper",       50,   ["blind_extras", "tear_blind_paper"]],
        "price_extra_pouch"        => ["Box & Add-ons", "Extra: Pouch",                  120,  ["blind_extras", "pouch"]],
        "price_extra_digital_art"  => ["Box & Add-ons", "Extra: Digital Art w/ Photo Card", 150, ["blind_extras", "digital_art"]],
    ];
}


/* presyo ng isang key: naka-save ng owner, o default kapag wala pa o hindi numero */
function figurify_price_value(array $content, string $key): float
{
    $value = $content[$key] ?? "";

    return is_numeric($value) ? max(0, (float) $value) : (float) figurify_price_fields()[$key][2];
}


/* ["products"]["Hirono"]["Full Body Standee"]["sizes"]["3.5"] = 950, ["figure_name"] = 50, atbp. */
function figurify_price_config(array $content): array
{
    $config = [];

    foreach (figurify_price_fields() as $key => $field) {
        $spot = &$config;
        foreach ($field[3] as $part) {
            $spot = &$spot[$part];
        }
        $spot = figurify_price_value($content, $key);
        unset($spot);
    }

    return $config;
}


/* para sa FAQ: "{figure_name}" => "₱50" (key na walang "price_") */
function figurify_price_tokens(array $content): array
{
    $tokens = [];

    foreach (array_keys(figurify_price_fields()) as $key) {
        $tokens["{" . substr($key, 6) . "}"] = "₱" . number_format(figurify_price_value($content, $key), 2);
    }

    return $tokens;
}


/* pinakamababang presyo ng isang Figure Style (hal. "Chibi" => 250) */
function figurify_style_start_price(array $config, string $style): float
{
    $prices = [];

    foreach ($config["products"][$style] ?? [] as $product) {
        $prices = array_merge($prices, isset($product["sizes"]) ? array_values($product["sizes"]) : [$product["price"]]);
    }

    return $prices ? (float) min($prices) : 0.0;
}


/* laman ng site_content (isang beses lang kinukuha kada page) - gamit ang $conn ng Admin/database.php */
function figurify_cached_content(): array
{
    static $content = null;

    if ($content === null) {
        $conn = $GLOBALS["conn"] ?? null;
        $content = $conn instanceof mysqli ? figurify_get_all_content($conn) : [];
    }

    return $content;
}


function figurify_site_prices(): array
{
    return figurify_price_config(figurify_cached_content());
}


/* para sa JS (dress-up.js at image-submission.js): <script id="figurifyPrices"> na may JSON ng presyo */
function figurify_price_json_tag(): string
{
    return '<script type="application/json" id="figurifyPrices">'
        . json_encode(figurify_site_prices(), JSON_HEX_TAG | JSON_HEX_AMP)
        . "</script>";
}


/* key ng isang item sa Dress Up (maikli, para sa JSON na "dressup_part_prices") */
function figurify_part_price_key(string $figure, string $slot, string $model): string
{
    return substr(md5($figure . "|" . $slot . "|" . $model), 0, 12);
}


/* lahat ng presyong na-save ng owner para sa Dress Up items: key => "80" o "" (walang presyo) */
function figurify_part_prices(array $content): array
{
    $saved = json_decode($content["dressup_part_prices"] ?? "", true);

    return is_array($saved) ? $saved : [];
}


/* presyo ng isang item: na-save ng owner (blangko = "0"), o $default (nasa dress-up-options.php) kung hindi pa na-save */
function figurify_part_price(string $figure, string $slot, string $model, string $default): string
{
    static $saved = null;
    $saved ??= figurify_part_prices(figurify_cached_content());

    $key   = figurify_part_price_key($figure, $slot, $model);
    $value = array_key_exists($key, $saved) ? (string) $saved[$key] : $default;

    return is_numeric($value) ? (string) max(0, (float) $value) : "0";
}
/* ====================== END SECTION 8 - PRESYO ====================== */
