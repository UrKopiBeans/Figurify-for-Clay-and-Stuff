<?php
/* ==========================================================================
   DRESS UP - OPTIONS   (Commission/dress-up-options.php)
   Dito nakalista ang LAHAT ng pagpipilian sa Dress Up (hair, top, bottom,
   shoes, kulay, atbp.). Ang dress-up.php ang gumagawa ng buttons mula dito.

   PAANO MAGDAGDAG NG OPTION:
     1. Hanapin ang section (hal. "funkoHairSection") sa ibaba. Kung "cards" => $du... ang
        nakalagay, nasa listahan na iyon ang options (sa [1b] o sa simula ng [3]).
     2. Kopyahin ang isang linya sa "cards" at palitan ang laman.
          ["Pangalan", "model.glb", "larawan.png", "Label na lalabas"]
        - model.glb  = nasa dressup-assets/GLB_files/
        - larawan    = nasa dressup-assets/images/
        - 5th na laman (optional) = ["data-price" => "40", ...] para sa presyo/extra attribute
          (default lang ang data-price; ang owner ang nagpapalit sa Content Management > Prices)

   LAMAN NG FILE:
     [1] RENDER FUNCTIONS   du_card() / du_section()
     [1b] PAREHONG LISTAHAN ng kulay at skin
     [2] FUNKO POP PANEL
     [3] CHIBI PANEL
     [4] HIRONO PANEL
     [5] LISTAHAN PARA SA PRESYO   du_part_items() (owner/content-management.php?tab=prices)
   ========================================================================== */


/* ====================== [1] RENDER FUNCTIONS ====================== */

require_once __DIR__ . "/../helpers/content_helper.php"; /* figurify_part_price() - presyong pinalitan ng owner */

define("DU_GLB", "dressup-assets/GLB_files/");
define("DU_IMG", "dressup-assets/images/");

function du_h($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8");
}

// ["key" => "value"] -> ' key="value" key2="value2"'
function du_attrs(array $attrs)
{
    $out = "";
    foreach ($attrs as $key => $value) {
        $out .= " " . $key . '="' . du_h($value) . '"';
    }
    return $out;
}

// "Folder/file.png" -> "dressup-assets/images/Folder/file.png" (full path na ang iniiwan)
function du_path($path, $base)
{
    if (strpos($path, "dressup-assets/") === 0 || strpos($path, "../") === 0 || strpos($path, "placeholder:") === 0) {
        return $path;
    }
    return $base . $path;
}

// isang button (card). $row[0] ang uri: opt | default | color | skin | model | type
function du_card(array $row, array $sec)
{
    $kind  = $row[0];
    $extra = is_array(end($row)) ? array_pop($row) : [];
    $name  = $row[1];

    $label = $extra["__label"] ?? null;
    $alt   = $extra["__alt"] ?? $name;
    unset($extra["__label"], $extra["__alt"]);

    $data = ["data-figure" => $sec["figure"], "data-slot" => $sec["slot"]];
    $data = array_merge($data, array_diff_key($extra, ["__src" => 1]));

    if ($kind === "color") {
        $data += ["data-name" => $name, "data-color" => $row[2], "data-price" => "0", "data-billable" => "false"];
        return "<button type=\"button\" class=\"top-color-card\"" . du_attrs($data) . ">"
            . "<span class=\"top-color-swatch top-color-" . du_h($row[3]) . "\"></span>"
            . "<span class=\"top-color-name\">" . du_h($name) . "</span></button>";
    }

    if ($kind === "skin") {
        $data += ["data-name" => $name, "data-color" => $row[2], "data-price" => "0", "data-billable" => "false"];
        return "<button type=\"button\" class=\"skin-card\"" . du_attrs($data) . ">"
            . "<span class=\"skin-swatch skin-" . du_h($row[3]) . "\"></span>"
            . "<span class=\"skin-name\">" . du_h($name) . "</span></button>";
    }

    if ($kind === "default") {
        $model = $data["data-model"] ?? "placeholder:" . $sec["figure"] . "-" . $sec["slot"] . "-" . du_slug($name);
        unset($data["data-model"]);
        $data += ["data-name" => $name, "data-model" => $model, "data-price" => "0", "data-billable" => "false", "data-default" => "true"];
        if (isset($data["data-skin-image"])) {
            $data["data-skin-image"] = du_path($row[2], DU_IMG); // larawan na nagbabago ayon sa balat
        }
        return "<button type=\"button\" class=\"option-card placeholder-card is-default\"" . du_attrs($data) . ">"
            . "<div class=\"option-image placeholder-image\"><img src=\"" . du_h(du_path($row[2], DU_IMG)) . "\" alt=\"" . du_h($alt) . "\"></div>"
            . "<span>" . du_h($label ?? $name) . "</span><small class=\"default-badge\">Default</small>"
            . "<small class=\"option-price\" data-base-price></small></button>";
    }

    if ($kind === "type") {
        $data += ["data-name" => $name, "data-price" => "0", "data-billable" => "false"];
        if (isset($data["data-skin-image"])) {
            $data["data-skin-image"] = du_path($row[2], DU_IMG); // larawan na nagbabago ayon sa balat
        }
        // kapareho ng laki ng Product Type / Gender card ng Hirono at Funko
        return "<button type=\"button\" class=\"figure-model-card\"" . du_attrs($data) . ">"
            . "<div class=\"option-image\"><img src=\"" . du_h(du_path($row[2], DU_IMG)) . "\" alt=\"" . du_h($alt) . "\"></div>"
            . "<span class=\"figure-model-name\">" . du_h($row[3]) . "</span></button>";
    }

    // opt / model: may 3D model + larawan
    $image = du_path($row[3], DU_IMG);
    $src   = $extra["__src"] ?? $image;
    $data  = array_merge($data, ["data-name" => $name, "data-model" => du_path($row[2], DU_GLB), "data-image" => $image]);
    if (isset($data["data-skin-image"])) {
        $data["data-skin-image"] = $image; // larawan na nagbabago ayon sa balat (Light/ at Brown/ folder)
    }
    if ($kind === "model") {
        $data += ["data-price" => "0", "data-billable" => "false"];
        return "<button type=\"button\" class=\"figure-model-card\"" . du_attrs($data) . ">"
            . "<div class=\"option-image\"><img src=\"" . du_h($src) . "\" alt=\"" . du_h($alt) . "\"></div>"
            . "<span class=\"figure-model-name\">" . du_h($row[4]) . "</span></button>";
    }
    $data += ["data-price" => "0", "data-billable" => "true"];
    $data["data-price"] = figurify_part_price($sec["figure"], $sec["slot"], $data["data-model"], $data["data-price"]);
    return "<button type=\"button\" class=\"option-card\"" . du_attrs($data) . ">"
        . "<div class=\"option-image\"><img src=\"" . du_h($src) . "\" alt=\"" . du_h($alt) . "\"></div>"
        . "<span>" . du_h($row[4]) . "</span><small class=\"option-price\" data-base-price></small></button>";
}

function du_slug($text)
{
    return trim(preg_replace("/[^a-z0-9]+/", "-", strtolower($text)), "-");
}

// isang section (title + grid ng cards) o kulay picker
function du_section(array $s)
{
    $attrs = ["class" => $s["class"], "id" => $s["id"]] + ($s["attrs"] ?? []);
    $open  = "<div" . du_attrs($attrs) . (($s["hidden"] ?? true) ? " hidden" : "") . ">";
    $html  = $open;

    if (!empty($s["title"])) {
        $html .= "<div class=\"section-title\"><h2>" . preg_replace('/\*$/', '<span class="required-star">*</span>', du_h($s["title"][0])) . "</h2></div>";
    }

    if (!empty($s["grid"])) {
        $html .= "<div class=\"" . du_h($s["grid"]) . "\"" . du_attrs($s["grid_attrs"] ?? []) . ">";
        foreach ($s["cards"] as $row) {
            $html .= du_card($row, $s);
        }
        $html .= "</div>";
    }

    return $html . "</div>";
}

function du_render_panel(array $sections)
{
    foreach ($sections as $s) {
        echo du_section($s), "\n";
    }
}



/* ====================== [1b] PAREHONG LISTAHAN NG KULAY AT SKIN ======================
   Isang listahan lang para sa mga section na pareho ang pagpipilian.
   Baguhin dito, sabay na magbabago sa lahat ng section na gumagamit nito. */

/* Skin tone (Funko Pop, Chibi, Hirono) - ginagamit ng: funkoSkinSection, chibiSkinSection, hironoSkinSection */
$duSkinTones = [
    ["skin", "Light", "#FFE1CA", "light"],
    ["skin", "Dark", "#D19477", "dark"],
];

/* Kulay: White, Black, Red, Blue - ginagamit ng: funkoGirlTopColorSection, funkoTopColorSection, hironoShoesColorSection */
$duColorsRed = [
    ["color", "White", "#FFFFFF", "white"],
    ["color", "Black", "#1E1E1E", "black"],
    ["color", "Red", "#D94B4B", "red"],
    ["color", "Blue", "#4E7DF0", "blue"],
];

/* Kulay: White, Black, Brown, Blue - ginagamit ng: funkoGirlBottomColorSection, funkoBottomPantsColorSection, chibiHairColorSection, chibiGirlHairColorSection, hironoHairColorSection, hironoPantsColorSection, hironoKeychainHairColorSection */
$duColorsBrown = [
    ["color", "White", "#FFFFFF", "white"],
    ["color", "Black", "#1E1E1E", "black"],
    ["color", "Brown", "#8B5A3C", "brown"],
    ["color", "Blue", "#4E7DF0", "blue"],
];
/* ====================== END PAREHONG LISTAHAN ====================== */


/* ====================== [2] FUNKO POP PANEL ====================== */

$duFunko = [


    /* ---------- funkoModelSection ---------- */
    [
        "id" => "funkoModelSection",
        "class" => "option-section",
        "title" => ["Gender*"],
        "grid" => "figure-model-grid",
        "figure" => "funko",
        "slot" => "model",
        "cards" => [
            ["model", "Funko Pop Default", "Funko Pop Standee Boy/funko boy.glb", "Funko Pop Standee Boy/Body/funko boy.png", "Boy", ["data-skin-image" => "1"]],
            ["model", "Funko Pop-Girl", "Funko Pop Standee Girl/funko girl.glb", "Funko Pop Standee Girl/Body/funko girl.png", "Girl", ["__alt" => "Funko Pop Girl", "data-skin-image" => "1"]],
        ],
    ],

    /* ---------- funkoSkinSection ---------- */
    [
        "id" => "funkoSkinSection",
        "class" => "option-section skin-section",
        "title" => ["Skin Tone*"],
        "grid" => "skin-grid",
        "figure" => "funko",
        "slot" => "skin",
        "cards" => $duSkinTones,
    ],

    /* ---------- funkoGirlHairSection ---------- */
    [
        "id" => "funkoGirlHairSection",
        "class" => "option-section funko-step",
        "title" => ["FUNKO POP HAIR"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "girlHair",
        "cards" => [
            ["default", "Default Hair", "Funko Pop Standee Girl/Defaults/hair.png", ["data-skin-image" => "1"]],
            ["opt", "Hair 01", "Funko Pop Standee Girl/Hairstyle/Funko GirlHair01.glb", "Funko Pop Standee Girl/Hairstyle/Funko GirlHair01.png", "Hair 1", ["data-skin-image" => "1"]],
            ["opt", "Hair 02", "Funko Pop Standee Girl/Hairstyle/Funko GirlHair02.glb", "Funko Pop Standee Girl/Hairstyle/Funko GirlHair02.png", "Hair 2", ["data-skin-image" => "1"]],
            ["opt", "Hair 03", "Funko Pop Standee Girl/Hairstyle/Funko GirlHair03.glb", "Funko Pop Standee Girl/Hairstyle/Funko GirlHair03.png", "Hair 03", ["data-skin-image" => "1"]],
            ["opt", "Hair 04", "Funko Pop Standee Girl/Hairstyle/hair.glb", "Funko Pop Standee Girl/Hairstyle/hair.png", "Hair 04", ["data-skin-image" => "1"]],
            ["opt", "Hair 05", "Funko Pop Standee Girl/Hairstyle/hair1.glb", "Funko Pop Standee Girl/Hairstyle/hair1.png", "Hair 05", ["data-skin-image" => "1"]],
            ["opt", "Hair 06", "Funko Pop Standee Girl/Hairstyle/hair2.glb", "Funko Pop Standee Girl/Hairstyle/hair2.png", "Hair 06", ["data-skin-image" => "1"]],
            ["opt", "Hair 07", "Funko Pop Standee Girl/Hairstyle/hair3.glb", "Funko Pop Standee Girl/Hairstyle/hair3.png", "Hair 07", ["data-skin-image" => "1"]],
            ["opt", "Hair 08", "Funko Pop Standee Girl/Hairstyle/hair4.glb", "Funko Pop Standee Girl/Hairstyle/hair4.png", "Hair 08", ["data-skin-image" => "1"]],
            ["opt", "Hair 09", "Funko Pop Standee Girl/Hairstyle/hair5.glb", "Funko Pop Standee Girl/Hairstyle/hair5.png", "Hair 09", ["data-skin-image" => "1"]],
        ],
    ],

    /* ---------- funkoGirlTopSection ---------- */
    [
        "id" => "funkoGirlTopSection",
        "class" => "option-section top-section",
        "title" => ["FUNKO POP TOP"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "girlTop",
        "cards" => [
            ["default", "Tank Top", "Funko Pop Standee Girl/Defaults/top.png"],
            ["opt", "Girl Shirt 01", "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt.glb", "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt.png", "Girl Shirt 01", ["data-price" => "80"]],
            ["opt", "Girl Shirt 02", "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt1.glb", "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt1.png", "Girl Shirt 02", ["data-price" => "100"]],
            ["opt", "Girl Shirt 03", "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt2.glb", "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt2.png", "Girl Shirt 03", ["data-price" => "100"]],
            ["opt", "T-Shirt", "Funko Pop Standee Girl/Tops/t-shirt.glb", "Funko Pop Standee Girl/Tops/t-shirt.png", "T-Shirt", ["data-price" => "80"]],
            ["opt", "T-Shirt 1", "Funko Pop Standee Girl/Tops/t-shirt1.glb", "Funko Pop Standee Girl/Tops/t-shirt1.png", "T-Shirt 1", ["data-price" => "80"]],
            ["opt", "Sando 1", "Funko Pop Standee Girl/Tops/sando.glb", "Funko Pop Standee Girl/Tops/sando.png", "Sando 1", ["data-price" => "80"]],
            ["opt", "Long Sleeve", "Funko Pop Standee Girl/Tops/long sleeve.glb", "Funko Pop Standee Girl/Tops/long sleeve.png", "Long Sleeve", ["data-price" => "80"]],
            ["opt", "Hoodie", "Funko Pop Standee Girl/Tops/hoodie.glb", "Funko Pop Standee Girl/Tops/hoodie.png", "Hoodie", ["data-price" => "100"]],
            ["opt", "Hoodie 1", "Funko Pop Standee Girl/Tops/hoodie1.glb", "Funko Pop Standee Girl/Tops/hoodie1.png", "Hoodie 1", ["data-price" => "100"]],
            ["opt", "Sweater", "Funko Pop Standee Girl/Tops/sweater.glb", "Funko Pop Standee Girl/Tops/sweater.png", "Sweater", ["data-price" => "100"]],
            ["opt", "Sweater 1", "Funko Pop Standee Girl/Tops/sweater1.glb", "Funko Pop Standee Girl/Tops/sweater1.png", "Sweater 1", ["data-price" => "100"]],
            ["opt", "Dress", "Funko Pop Standee Girl/Tops/dress.glb", "Funko Pop Standee Girl/Tops/dress.png", "Dress", ["data-price" => "100"]],
            ["opt", "CCC Uniform", "Funko Pop Standee Girl/Tops/ccc uniform.glb", "Funko Pop Standee Girl/Tops/ccc uniform.png", "CCC Uniform", ["data-price" => "100"]],
            ["opt", "T-Shirt 2", "Funko Pop Standee Girl/Tops/t-shirt2.glb", "Funko Pop Standee Girl/Tops/t-shirt2.png", "T-Shirt 2", ["data-price" => "80"]],
            ["opt", "NY T-Shirt", "Funko Pop Standee Girl/Tops/ny t-shirt.glb", "Funko Pop Standee Girl/Tops/ny t-shirt.png", "NY T-Shirt", ["data-price" => "80"]],
            ["opt", "Sweater 2", "Funko Pop Standee Girl/Tops/sweater2.glb", "Funko Pop Standee Girl/Tops/sweater2.png", "Sweater 2", ["data-price" => "100"]],
            ["opt", "Fur Jacket", "Funko Pop Standee Girl/Tops/fur jacket.glb", "Funko Pop Standee Girl/Tops/fur jacket.png", "Fur Jacket", ["data-price" => "100"]],
        ],
    ],

    /* ---------- funkoGirlTopColorSection ---------- */
    [
        "id" => "funkoGirlTopColorSection",
        "class" => "top-color-picker funko-step",
        "attrs" => ["data-figure" => "funko", "data-slot" => "girlTopColor"],
        "grid" => "top-color-grid",
        "figure" => "funko",
        "slot" => "girlTopColor",
        "cards" => $duColorsRed,
    ],

    /* ---------- funkoGirlBottomSection ---------- */
    [
        "id" => "funkoGirlBottomSection",
        "class" => "option-section",
        "title" => ["FUNKO POP BOTTOM"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "girlBottom",
        "cards" => [
            ["default", "Shorts", "Funko Pop Standee Girl/Defaults/bottom.png"],
            ["opt", "Girl Bottom", "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom.glb", "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom.png", "Girl Bottom", ["data-price" => "90"]],
            ["opt", "Girl Bottom 1", "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom1.glb", "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom1.png", "Girl Bottom 1", ["data-price" => "90"]],
            ["opt", "Shorts 1", "Funko Pop Standee Girl/Bottoms/shorts.glb", "Funko Pop Standee Girl/Bottoms/shorts.png", "Shorts 1", ["data-price" => "90"]],
            ["opt", "Pants", "Funko Pop Standee Girl/Bottoms/pants.glb", "Funko Pop Standee Girl/Bottoms/pants.png", "Pants", ["data-price" => "90"]],
            ["opt", "Pants 1", "Funko Pop Standee Girl/Bottoms/pants1.glb", "Funko Pop Standee Girl/Bottoms/pants1.png", "Pants 1", ["data-price" => "90"]],
            ["opt", "Pants 2", "Funko Pop Standee Girl/Bottoms/pants2.glb", "Funko Pop Standee Girl/Bottoms/pants2.png", "Pants 2", ["data-price" => "90"]],
            ["opt", "Pants 3", "Funko Pop Standee Girl/Bottoms/pants3.glb", "Funko Pop Standee Girl/Bottoms/pants3.png", "Pants 3", ["data-price" => "90"]],
            ["opt", "Pants 4", "Funko Pop Standee Girl/Bottoms/pants4.glb", "Funko Pop Standee Girl/Bottoms/pants4.png", "Pants 4", ["data-price" => "90"]],
            ["opt", "Pants 5", "Funko Pop Standee Girl/Bottoms/pants5.glb", "Funko Pop Standee Girl/Bottoms/pants5.png", "Pants 5", ["data-price" => "90"]],
            ["opt", "Skirt", "Funko Pop Standee Girl/Bottoms/skirt.glb", "Funko Pop Standee Girl/Bottoms/skirt.png", "Skirt", ["data-price" => "90"]],
            ["opt", "CCC Skirt", "Funko Pop Standee Girl/Bottoms/ccc skirt.glb", "Funko Pop Standee Girl/Bottoms/ccc skirt.png", "CCC Skirt", ["data-price" => "90"]],
            ["opt", "Shorts 2", "Funko Pop Standee Girl/Bottoms/shorts1.glb", "Funko Pop Standee Girl/Bottoms/shorts1.png", "Shorts 2", ["data-price" => "90"]],
            ["opt", "CCC Slacks", "Funko Pop Standee Girl/Bottoms/ccc slacks.glb", "Funko Pop Standee Girl/Bottoms/ccc slacks.png", "CCC Slacks", ["data-price" => "90"]],
        ],
    ],

    /* ---------- funkoGirlBottomColorSection ---------- */
    [
        "id" => "funkoGirlBottomColorSection",
        "class" => "top-color-picker pants-color-picker funko-step",
        "grid" => "top-color-grid",
        "figure" => "funko",
        "slot" => "girlBottomColor",
        "cards" => $duColorsBrown,
    ],

    /* ---------- funkoHairSection ---------- */
    [
        "id" => "funkoHairSection",
        "class" => "option-section funko-step",
        "title" => ["FUNKO POP HAIR"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "hair",
        "cards" => [
            ["default", "Default Hair", "Funko Pop Standee Boy/Defaults/hair.png", ["data-skin-image" => "1"]],
            ["opt", "Hair 1", "Funko Pop Standee Boy/Hairstyle/hair.glb", "Funko Pop Standee Boy/Hairstyle/hair.png", "Hair 1", ["data-skin-image" => "1"]],
            ["opt", "Hair 2", "Funko Pop Standee Boy/Hairstyle/hair1.glb", "Funko Pop Standee Boy/Hairstyle/hair1.png", "Hair 2", ["data-skin-image" => "1"]],
            ["opt", "Hair 3", "Funko Pop Standee Boy/Hairstyle/hair2.glb", "Funko Pop Standee Boy/Hairstyle/hair2.png", "Hair 3", ["data-skin-image" => "1"]],
            ["opt", "Hair 4", "Funko Pop Standee Boy/Hairstyle/hair3.glb", "Funko Pop Standee Boy/Hairstyle/hair3.png", "Hair 4", ["data-skin-image" => "1"]],
            ["opt", "Hair 5", "Funko Pop Standee Boy/Hairstyle/hair5.glb", "Funko Pop Standee Boy/Hairstyle/hair5.png", "Hair 5", ["data-skin-image" => "1"]],
            ["opt", "Hair 6", "Funko Pop Standee Boy/Hairstyle/hair6.glb", "Funko Pop Standee Boy/Hairstyle/hair6.png", "Hair 6", ["data-skin-image" => "1"]],
            ["opt", "Hair 7", "Funko Pop Standee Boy/Hairstyle/hair7.glb", "Funko Pop Standee Boy/Hairstyle/hair7.png", "Hair 7", ["data-skin-image" => "1"]],
            ["opt", "Hair 8", "Funko Pop Standee Boy/Hairstyle/hair8.glb", "Funko Pop Standee Boy/Hairstyle/hair8.png", "Hair 8", ["data-skin-image" => "1"]],
            ["opt", "Hair 9", "Funko Pop Standee Boy/Hairstyle/hair9.glb", "Funko Pop Standee Boy/Hairstyle/hair9.png", "Hair 9", ["data-skin-image" => "1"]],
        ],
    ],

    /* ---------- funkoHairColorSection ---------- */
    [
        "id" => "funkoHairColorSection",
        "class" => "top-color-picker hair-color-picker funko-step",
        "grid" => "top-color-grid",
        "figure" => "funko",
        "slot" => "hairColor",
        "cards" => [
            ["color", "Brown", "#5C3A2E", "brown"],
            ["color", "Black", "#1E1E1E", "black"],
            ["color", "Blonde", "#D8BE7A", "white"],
            ["color", "Blue", "#4E7DF0", "blue"],
        ],
    ],

    /* ---------- funkoTopSection ---------- */
    [
        "id" => "funkoTopSection",
        "class" => "option-section top-section",
        "title" => ["FUNKO POP TOPS"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "top",
        "cards" => [
            ["default", "Sando", "Funko Pop Standee Boy/Defaults/top.png"],
            ["opt", "CCC Uniform", "Funko Pop Standee Boy/Tops/ccc uniform.glb", "Funko Pop Standee Boy/Tops/ccc uniform.png", "CCC Uniform", ["data-price" => "100"]],
            ["opt", "Hoodie", "Funko Pop Standee Boy/Tops/hoodie.glb", "Funko Pop Standee Boy/Tops/hoodie.png", "Hoodie", ["data-price" => "100"]],
            ["opt", "Plain Polo", "Funko Pop Standee Boy/Tops/polo plain.glb", "Funko Pop Standee Boy/Tops/polo plain.png", "Plain Polo", ["data-price" => "80"]],
            ["opt", "Sweater", "Funko Pop Standee Boy/Tops/sweater.glb", "Funko Pop Standee Boy/Tops/sweater.png", "Sweater", ["data-price" => "100"]],
            ["opt", "Hockey Jersey", "Funko Pop Standee Boy/Tops/hockey jersey.glb", "Funko Pop Standee Boy/Tops/hockey jersey.png", "Hockey Jersey", ["data-price" => "100"]],
            ["opt", "Leather Jacket", "Funko Pop Standee Boy/Tops/leather jacket.glb", "Funko Pop Standee Boy/Tops/leather jacket.png", "Leather Jacket", ["data-price" => "100"]],
            ["opt", "New York T-Shirt", "Funko Pop Standee Boy/Tops/new york t-shirt.glb", "Funko Pop Standee Boy/Tops/new york t-shirt.png", "New York T-Shirt", ["data-price" => "80"]],
            ["opt", "Teacher Polo", "Funko Pop Standee Boy/Tops/teacher polo.glb", "Funko Pop Standee Boy/Tops/teacher polo.png", "Teacher Polo", ["data-price" => "80"]],
            ["opt", "Teacher Polo 2", "Funko Pop Standee Boy/Tops/teacher polo1.glb", "Funko Pop Standee Boy/Tops/teacher polo1.png", "Teacher Polo 2", ["data-price" => "80"]],
            ["opt", "Teacher Polo 3", "Funko Pop Standee Boy/Tops/teacher polo2.glb", "Funko Pop Standee Boy/Tops/teacher polo2.png", "Teacher Polo 3", ["data-price" => "80"]],
            ["opt", "Teacher Polo 4", "Funko Pop Standee Boy/Tops/teacher polo3.glb", "Funko Pop Standee Boy/Tops/teacher polo3.png", "Teacher Polo 4", ["data-price" => "80"]],
        ],
    ],

    /* ---------- funkoTopColorSection ---------- */
    [
        "id" => "funkoTopColorSection",
        "class" => "top-color-picker shirt-color-picker funko-step",
        "grid" => "top-color-grid",
        "figure" => "funko",
        "slot" => "topColor",
        "cards" => $duColorsRed,
    ],

    /* ---------- funkoBottomSection ---------- */
    [
        "id" => "funkoBottomSection",
        "class" => "option-section",
        "title" => ["FUNKO POP BOTTOMS"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "bottom",
        "cards" => [
            ["default", "Shorts", "Funko Pop Standee Boy/Defaults/bottom.png"],
            ["opt", "CCC Slacks", "Funko Pop Standee Boy/Bottoms/ccc slacks.glb", "Funko Pop Standee Boy/Bottoms/ccc slacks.png", "CCC Slacks", ["data-price" => "90"]],
            ["opt", "Basketball Shorts", "Funko Pop Standee Boy/Bottoms/basketball shorts.glb", "Funko Pop Standee Boy/Bottoms/basketball shorts.png", "Basketball Shorts", ["data-price" => "90"]],
            ["opt", "Pants 1", "Funko Pop Standee Boy/Bottoms/pants.glb", "Funko Pop Standee Boy/Bottoms/pants.png", "Pants 1", ["data-price" => "90"]],
            ["opt", "Pants 2", "Funko Pop Standee Boy/Bottoms/pants1.glb", "Funko Pop Standee Boy/Bottoms/pants1.png", "Pants 2", ["data-price" => "90"]],
            ["opt", "Pants 3", "Funko Pop Standee Boy/Bottoms/pants2.glb", "Funko Pop Standee Boy/Bottoms/pants2.png", "Pants 3", ["data-price" => "90"]],
            ["opt", "Pants 4", "Funko Pop Standee Boy/Bottoms/pants3.glb", "Funko Pop Standee Boy/Bottoms/pants3.png", "Pants 4", ["data-price" => "90"]],
            ["opt", "Pants 5", "Funko Pop Standee Boy/Bottoms/pants4.glb", "Funko Pop Standee Boy/Bottoms/pants4.png", "Pants 5", ["data-price" => "90"]],
        ],
    ],

    /* ---------- funkoBottomPantsColorSection ---------- */
    [
        "id" => "funkoBottomPantsColorSection",
        "class" => "top-color-picker pants-color-picker funko-step",
        "grid" => "top-color-grid",
        "figure" => "funko",
        "slot" => "pantsColor",
        "cards" => $duColorsBrown,
    ],

    /* ---------- funkoGirlShoesSection ---------- */
    [
        "id" => "funkoGirlShoesSection",
        "class" => "option-section",
        "title" => ["FUNKO POP SHOES"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "shoes",
        "cards" => [
            ["default", "Shoes", "Funko Pop Standee Girl/Defaults/shoes.png", ["data-model" => "placeholder:funko-girlShoes-shoes"]],
            ["opt", "Black Shoes", "Funko Pop Standee Girl/Shoes/black shoes.glb", "Funko Pop Standee Girl/Shoes/black shoes.png", "Black Shoes", ["data-price" => "80"]],
            ["opt", "Crocs", "Funko Pop Standee Girl/Shoes/crocs.glb", "Funko Pop Standee Girl/Shoes/crocs.png", "Crocs", ["data-price" => "80"]],
            ["opt", "Shoes 1", "Funko Pop Standee Girl/Shoes/shoes.glb", "Funko Pop Standee Girl/Shoes/shoes.png", "Shoes 1", ["data-price" => "80"]],
            ["opt", "Shoes 2", "Funko Pop Standee Girl/Shoes/shoes1.glb", "Funko Pop Standee Girl/Shoes/shoes1.png", "Shoes 2", ["data-price" => "80"]],
            ["opt", "Shoes 3", "Funko Pop Standee Girl/Shoes/shoes6.glb", "Funko Pop Standee Girl/Shoes/shoes6.png", "Shoes 3", ["data-price" => "80"]],
            ["opt", "Shoes 4", "Funko Pop Standee Girl/Shoes/shoes8.glb", "Funko Pop Standee Girl/Shoes/shoes8.png", "Shoes 4", ["data-price" => "80"]],
            ["opt", "Slippers", "Funko Pop Standee Girl/Shoes/slippers.glb", "Funko Pop Standee Girl/Shoes/slippers.png", "Slippers", ["data-price" => "80"]],
        ],
    ],

    /* ---------- funkoShoesSection ---------- */
    [
        "id" => "funkoShoesSection",
        "class" => "option-section",
        "title" => ["FUNKO POP SHOES"],
        "grid" => "option-grid",
        "figure" => "funko",
        "slot" => "shoes",
        "cards" => [
            ["default", "Shoes", "Funko Pop Standee Boy/Defaults/shoes.png"],
            ["opt", "Shoes 1", "Funko Pop Standee Boy/Shoes/shoes6.glb", "Funko Pop Standee Boy/Shoes/shoes6.png", "Shoes 1", ["data-price" => "80"]],
            ["opt", "Shoes 2", "Funko Pop Standee Boy/Shoes/shoes7.glb", "Funko Pop Standee Boy/Shoes/shoes7.png", "Shoes 2", ["data-price" => "80"]],
            ["opt", "Shoes 3", "Funko Pop Standee Boy/Shoes/shoes8.glb", "Funko Pop Standee Boy/Shoes/shoes8.png", "Shoes 3", ["data-price" => "80"]],
            ["opt", "Shoes 4", "Funko Pop Standee Boy/Shoes/shoes9.glb", "Funko Pop Standee Boy/Shoes/shoes9.png", "Shoes 4", ["data-price" => "80"]],
            ["opt", "Shoes 5", "Funko Pop Standee Boy/Shoes/shoes10.glb", "Funko Pop Standee Boy/Shoes/shoes10.png", "Shoes 5", ["data-price" => "80"]],
            ["opt", "Shoes 6", "Funko Pop Standee Boy/Shoes/shoes11.glb", "Funko Pop Standee Boy/Shoes/shoes11.png", "Shoes 6", ["data-price" => "80"]],
            ["opt", "Shoes 7", "Funko Pop Standee Boy/Shoes/shoes12.glb", "Funko Pop Standee Boy/Shoes/shoes12.png", "Shoes 7", ["data-price" => "80"]],
            ["opt", "Shoes 8", "Funko Pop Standee Boy/Shoes/shoes13.glb", "Funko Pop Standee Boy/Shoes/shoes13.png", "Shoes 8", ["data-price" => "80"]],
            ["opt", "Shoes 9", "Funko Pop Standee Boy/Shoes/shoes14.glb", "Funko Pop Standee Boy/Shoes/shoes14.png", "Shoes 9", ["data-price" => "80"]],
            ["opt", "Shoes 10", "Funko Pop Standee Boy/Shoes/shoes15.glb", "Funko Pop Standee Boy/Shoes/shoes15.png", "Shoes 10", ["data-price" => "80"]],
        ],
    ],

    /* ---------- funkoShoesColorSection ---------- */
    [
        "id" => "funkoShoesColorSection",
        "class" => "top-color-picker custom-color-only-section",
        "attrs" => ["data-figure" => "funko", "data-slot" => "shoesColor"],
    ],
];
/* ====================== END FUNKO POP PANEL ====================== */


/* ====================== [3] CHIBI PANEL ====================== */

/* Pareho ang pagpipilian ng Chibi Standee at Chibi Keychain - isang listahan lang bawat bahagi */

/* Chibi Boy hair (Standee at Keychain) - ginagamit ng: chibiHairSection, chibiBoyKeychainHairSection */
$duChibiBoyHair = [
    ["default", "Bald (No Hair)", "Chibi Standee Boy/Defaults/hair.png", ["data-model" => "placeholder:chibi-boy-hair-bald", "data-skin-image" => "1"]],
    ["opt", "Hair 1", "Chibi Standee Boy/Hairstyle/hair.glb", "Chibi Standee Boy/Hairstyle/hair.png", "Hair 1", ["data-skin-image" => "1"]],
    ["opt", "Hair 2", "Chibi Standee Boy/Hairstyle/hair1.glb", "Chibi Standee Boy/Hairstyle/hair1.png", "Hair 2", ["data-skin-image" => "1"]],
    ["opt", "Hair 3", "Chibi Standee Boy/Hairstyle/hair2.glb", "Chibi Standee Boy/Hairstyle/hair2.png", "Hair 3", ["data-skin-image" => "1"]],
    ["opt", "Hair 4", "Chibi Standee Boy/Hairstyle/hair3.glb", "Chibi Standee Boy/Hairstyle/hair3.png", "Hair 4", ["data-skin-image" => "1"]],
    ["opt", "Hair 5", "Chibi Standee Boy/Hairstyle/hair4.glb", "Chibi Standee Boy/Hairstyle/hair4.png", "Hair 5", ["data-skin-image" => "1"]],
    ["opt", "Hair 6", "Chibi Standee Boy/Hairstyle/hair5.glb", "Chibi Standee Boy/Hairstyle/hair5.png", "Hair 6", ["data-skin-image" => "1"]],
    ["opt", "Hair 7", "Chibi Standee Boy/Hairstyle/hair6.glb", "Chibi Standee Boy/Hairstyle/hair6.png", "Hair 7", ["data-skin-image" => "1"]],
    ["opt", "Hair 8", "Chibi Standee Boy/Hairstyle/hair7.glb", "Chibi Standee Boy/Hairstyle/hair7.png", "Hair 8", ["data-skin-image" => "1"]],
    ["opt", "Hair 9", "Chibi Standee Boy/Hairstyle/hair8.glb", "Chibi Standee Boy/Hairstyle/hair8.png", "Hair 9", ["data-skin-image" => "1"]],
    ["opt", "Hair 10", "Chibi Standee Boy/Hairstyle/hair9.glb", "Chibi Standee Boy/Hairstyle/hair9.png", "Hair 10", ["data-skin-image" => "1"]],
    ["opt", "Hair 11", "Chibi Standee Boy/Hairstyle/hair10.glb", "Chibi Standee Boy/Hairstyle/hair10.png", "Hair 11", ["data-skin-image" => "1"]],
];

/* Chibi Girl hair (Standee at Keychain) - ginagamit ng: chibiGirlHairSection, chibiGirlKeychainHairSection */
$duChibiGirlHair = [
    ["default", "Default Hair", "Chibi Standee Girl/Defaults/hair.png", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 1", "Chibi Standee Girl/Hairstyle/hair.glb", "Chibi Standee Girl/Hairstyle/hair.png", "Girl Hair 1", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 2", "Chibi Standee Girl/Hairstyle/hair1.glb", "Chibi Standee Girl/Hairstyle/hair1.png", "Girl Hair 2", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 3", "Chibi Standee Girl/Hairstyle/hair2.glb", "Chibi Standee Girl/Hairstyle/hair2.png", "Girl Hair 3", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 4", "Chibi Standee Girl/Hairstyle/hair3.glb", "Chibi Standee Girl/Hairstyle/hair3.png", "Girl Hair 4", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 5", "Chibi Standee Girl/Hairstyle/hair4.glb", "Chibi Standee Girl/Hairstyle/hair4.png", "Girl Hair 5", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 6", "Chibi Standee Girl/Hairstyle/hair5.glb", "Chibi Standee Girl/Hairstyle/hair5.png", "Girl Hair 6", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 7", "Chibi Standee Girl/Hairstyle/hair6.glb", "Chibi Standee Girl/Hairstyle/hair6.png", "Girl Hair 7", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 8", "Chibi Standee Girl/Hairstyle/hair7.glb", "Chibi Standee Girl/Hairstyle/hair7.png", "Girl Hair 8", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 9", "Chibi Standee Girl/Hairstyle/hair8.glb", "Chibi Standee Girl/Hairstyle/hair8.png", "Girl Hair 9", ["data-skin-image" => "1"]],
    ["opt", "Girl Hair 10", "Chibi Standee Girl/Hairstyle/hair9.glb", "Chibi Standee Girl/Hairstyle/hair9.png", "Girl Hair 10", ["data-skin-image" => "1"]],
];

/* Chibi Boy top (Standee at Keychain) - ginagamit ng: chibiTopSection, chibiBoyKeychainTopSection */
$duChibiBoyTop = [
    ["default", "Sando", "Chibi Standee Boy/Defaults/top.png"],
    ["opt", "Coat", "Chibi Standee Boy/Tops/chibi coat.glb", "Chibi Standee Boy/Tops/chibi coat.png", "Coat", ["data-price" => "50"]],
    ["opt", "Coat 2", "Chibi Standee Boy/Tops/chibi coat1.glb", "Chibi Standee Boy/Tops/chibi coat1.png", "Coat 2", ["data-price" => "50"]],
    ["opt", "Plain Polo", "Chibi Standee Boy/Tops/plain polo.glb", "Chibi Standee Boy/Tops/plain polo.png", "Plain Polo", ["data-price" => "50"]],
    ["opt", "RL Polo", "Chibi Standee Boy/Tops/rl polo.glb", "Chibi Standee Boy/Tops/rl polo.png", "RL Polo", ["data-price" => "50"]],
    ["opt", "Pocket T-Shirt", "Chibi Standee Boy/Tops/chibi pocket tee.glb", "Chibi Standee Boy/Tops/chibi pocket tee.png", "Pocket T-Shirt", ["data-price" => "50"]],
    ["opt", "T-Shirt", "Chibi Standee Boy/Tops/t-shirt.glb", "Chibi Standee Boy/Tops/t-shirt.png", "T-Shirt", ["data-price" => "50"]],
    ["opt", "NY T-Shirt", "Chibi Standee Boy/Tops/ny t-shirt.glb", "Chibi Standee Boy/Tops/ny t-shirt.png", "NY T-Shirt", ["data-price" => "50"]],
    ["opt", "Hoodie", "Chibi Standee Boy/Tops/chibi hoodie.glb", "Chibi Standee Boy/Tops/chibi hoodie.png", "Hoodie", ["data-price" => "50"]],
    ["opt", "CCC Uniform", "Chibi Standee Boy/Tops/ccc uniform.glb", "Chibi Standee Boy/Tops/ccc uniform.png", "CCC Uniform", ["data-price" => "50"]],
    ["opt", "Coat 3", "Chibi Standee Boy/Tops/chibi coat2.glb", "Chibi Standee Boy/Tops/chibi coat2.png", "Coat 3", ["data-price" => "50"]],
    ["opt", "Long Sleeve", "Chibi Standee Boy/Tops/chibi long sleeve.glb", "Chibi Standee Boy/Tops/chibi long sleeve.png", "Long Sleeve", ["data-price" => "50"]],
    ["opt", "Red T-Shirt", "Chibi Standee Boy/Tops/chibi t-shirt.glb", "Chibi Standee Boy/Tops/chibi t-shirt.png", "Red T-Shirt", ["data-price" => "50"]],
    ["opt", "Black T-Shirt", "Chibi Standee Boy/Tops/chibi t-shirt1.glb", "Chibi Standee Boy/Tops/chibi t-shirt1.png", "Black T-Shirt", ["data-price" => "50"]],
];

/* Chibi Girl top (Standee at Keychain) - ginagamit ng: chibiGirlTopSection, chibiGirlKeychainTopSection */
$duChibiGirlTop = [
    ["default", "Tank Top", "Chibi Standee Girl/Defaults/top.png"],
    ["opt", "Hoodie", "Chibi Standee Girl/Tops/hoodie.glb", "Chibi Standee Girl/Tops/hoodie.png", "Hoodie", ["data-price" => "50"]],
    ["opt", "Denim Jacket", "Chibi Standee Girl/Tops/denim jacket.glb", "Chibi Standee Girl/Tops/denim jacket.png", "Denim Jacket", ["data-price" => "50"]],
    ["opt", "Sweater", "Chibi Standee Girl/Tops/sweater1.glb", "Chibi Standee Girl/Tops/sweater1.png", "Sweater", ["data-price" => "50"]],
    ["opt", "Coat", "Chibi Standee Girl/Tops/coat.glb", "Chibi Standee Girl/Tops/coat.png", "Coat", ["data-price" => "50"]],
    ["opt", "Dress", "Chibi Standee Girl/Tops/dress.glb", "Chibi Standee Girl/Tops/dress.png", "Dress", ["data-price" => "50"]],
    ["opt", "Leather Jacket", "Chibi Standee Girl/Tops/leather jacket.glb", "Chibi Standee Girl/Tops/leather jacket.png", "Leather Jacket", ["data-price" => "50"]],
    ["opt", "Fur Jacket", "Chibi Standee Girl/Tops/fur jacket.glb", "Chibi Standee Girl/Tops/fur jacket.png", "Fur Jacket", ["data-price" => "50"]],
    ["opt", "T-Shirt", "Chibi Standee Girl/Tops/t-shirt.glb", "Chibi Standee Girl/Tops/t-shirt.png", "T-Shirt", ["data-price" => "50"]],
    ["opt", "CCC Uniform", "Chibi Standee Girl/Tops/ccc uniform.glb", "Chibi Standee Girl/Tops/ccc uniform.png", "CCC Uniform", ["data-price" => "50"]],
];

/* Chibi Boy bottom (Standee at Keychain) - ginagamit ng: chibiBottomSection, chibiBoyKeychainBottomSection */
$duChibiBoyBottom = [
    ["default", "Shorts", "Chibi Standee Boy/Defaults/bottom.png"],
    ["opt", "Pants 1", "Chibi Standee Boy/Bottoms/chibi pants.glb", "Chibi Standee Boy/Bottoms/chibi pants.png", "Pants 1", ["data-price" => "50"]],
    ["opt", "Pants 2", "Chibi Standee Boy/Bottoms/chibi pants1.glb", "Chibi Standee Boy/Bottoms/chibi pants1.png", "Pants 2", ["data-price" => "50"]],
    ["opt", "Pants 3", "Chibi Standee Boy/Bottoms/chibi pants2.glb", "Chibi Standee Boy/Bottoms/chibi pants2.png", "Pants 3", ["data-price" => "50"]],
    ["opt", "Pants 4", "Chibi Standee Boy/Bottoms/chibi pants3.glb", "Chibi Standee Boy/Bottoms/chibi pants3.png", "Pants 4", ["data-price" => "50"]],
    ["opt", "Pants 5", "Chibi Standee Boy/Bottoms/chibi pants4.glb", "Chibi Standee Boy/Bottoms/chibi pants4.png", "Pants 5", ["data-price" => "50"]],
    ["opt", "Pants 6", "Chibi Standee Boy/Bottoms/chibi pants5.glb", "Chibi Standee Boy/Bottoms/chibi pants5.png", "Pants 6", ["data-price" => "50"]],
    ["opt", "CCC Slacks", "Chibi Standee Boy/Bottoms/ccc slacks.glb", "Chibi Standee Boy/Bottoms/ccc slacks.png", "CCC Slacks", ["data-price" => "50"]],
];

/* Chibi Girl bottom (Standee at Keychain) - ginagamit ng: chibiGirlBottomSection, chibiGirlKeychainBottomSection */
$duChibiGirlBottom = [
    ["default", "Shorts", "Chibi Standee Girl/Defaults/bottom.png"],
    ["opt", "Pants 1", "Chibi Standee Girl/Bottoms/pants1.glb", "Chibi Standee Girl/Bottoms/pants1.png", "Pants 1", ["data-price" => "50"]],
    ["opt", "Pants 2", "Chibi Standee Girl/Bottoms/pants2.glb", "Chibi Standee Girl/Bottoms/pants2.png", "Pants 2", ["data-price" => "50"]],
    ["opt", "Pants 3", "Chibi Standee Girl/Bottoms/pants3.glb", "Chibi Standee Girl/Bottoms/pants3.png", "Pants 3", ["data-price" => "50"]],
    ["opt", "Pants 4", "Chibi Standee Girl/Bottoms/pants4.glb", "Chibi Standee Girl/Bottoms/pants4.png", "Pants 4", ["data-price" => "50"]],
    ["opt", "Pants 5", "Chibi Standee Girl/Bottoms/pants5.glb", "Chibi Standee Girl/Bottoms/pants5.png", "Pants 5", ["data-price" => "50"]],
    ["opt", "Pants 6", "Chibi Standee Girl/Bottoms/pants6.glb", "Chibi Standee Girl/Bottoms/pants6.png", "Pants 6", ["data-price" => "50"]],
    ["opt", "Pants 7", "Chibi Standee Girl/Bottoms/pants7.glb", "Chibi Standee Girl/Bottoms/pants7.png", "Pants 7", ["data-price" => "50"]],
    ["opt", "Shorts 1", "Chibi Standee Girl/Bottoms/shorts1.glb", "Chibi Standee Girl/Bottoms/shorts1.png", "Shorts 1", ["data-price" => "50"]],
    ["opt", "Skirt 1", "Chibi Standee Girl/Bottoms/skirt.glb", "Chibi Standee Girl/Bottoms/skirt.png", "Skirt 1", ["data-price" => "50"]],
    ["opt", "Skirt 2", "Chibi Standee Girl/Bottoms/skirt1.glb", "Chibi Standee Girl/Bottoms/skirt1.png", "Skirt 2", ["data-price" => "50"]],
    ["opt", "Skirt 3", "Chibi Standee Girl/Bottoms/sksirt2.glb", "Chibi Standee Girl/Bottoms/sksirt2.png", "Skirt 3", ["data-price" => "50"]],
    ["opt", "CCC Skirt", "Chibi Standee Girl/Bottoms/ccc skirt.glb", "Chibi Standee Girl/Bottoms/ccc skirt.png", "CCC Skirt", ["data-price" => "50"]],
    ["opt", "CCC Slacks", "Chibi Standee Girl/Bottoms/ccc slacks.glb", "Chibi Standee Girl/Bottoms/ccc slacks.png", "CCC Slacks", ["data-price" => "50"]],
];

/* Chibi Girl shoes (Standee at Keychain) - ginagamit ng: chibiShoesSection, chibiGirlKeychainShoesSection */
$duChibiGirlShoes = [
    ["default", "Shoes", "Chibi Standee Girl/Defaults/shoes.png"],
    ["opt", "Shoes 1", "Chibi Standee Girl/Shoes/shoes.glb", "Chibi Standee Girl/Shoes/shoes.png", "Shoes 1", ["data-price" => "50"]],
    ["opt", "Shoes 2", "Chibi Standee Girl/Shoes/shoes1.glb", "Chibi Standee Girl/Shoes/shoes1.png", "Shoes 2", ["data-price" => "50"]],
    ["opt", "Shoes 3", "Chibi Standee Girl/Shoes/shoes2.glb", "Chibi Standee Girl/Shoes/shoes2.png", "Shoes 3", ["data-price" => "50"]],
    ["opt", "Shoes 4", "Chibi Standee Girl/Shoes/shoes3.glb", "Chibi Standee Girl/Shoes/shoes3.png", "Shoes 4", ["data-price" => "50"]],
    ["opt", "Shoes 5", "Chibi Standee Girl/Shoes/shoes4.glb", "Chibi Standee Girl/Shoes/shoes4.png", "Shoes 5", ["data-price" => "50"]],
    ["opt", "Shoes 6", "Chibi Standee Girl/Shoes/shoes5.glb", "Chibi Standee Girl/Shoes/shoes5.png", "Shoes 6", ["data-price" => "50"]],
    ["opt", "Shoes 7", "Chibi Standee Girl/Shoes/shoes6.glb", "Chibi Standee Girl/Shoes/shoes6.png", "Shoes 7", ["data-price" => "50"]],
    ["opt", "Shoes 8", "Chibi Standee Girl/Shoes/shoes7.glb", "Chibi Standee Girl/Shoes/shoes7.png", "Shoes 8", ["data-price" => "50"]],
    ["opt", "Shoes 9", "Chibi Standee Girl/Shoes/shoes8.glb", "Chibi Standee Girl/Shoes/shoes8.png", "Shoes 9", ["data-price" => "50"]],
    ["opt", "Crocs", "Chibi Standee Girl/Shoes/crocs.glb", "Chibi Standee Girl/Shoes/crocs.png", "Crocs", ["data-price" => "50"]],
    ["opt", "Black Shoes", "Chibi Standee Girl/Shoes/black shoes.glb", "Chibi Standee Girl/Shoes/black shoes.png", "Black Shoes", ["data-price" => "50"]],
];

/* Chibi Boy shoes (Standee at Keychain) - ginagamit ng: chibiBoyShoesSection, chibiBoyKeychainShoesSection */
$duChibiBoyShoes = [
    ["default", "Barefoot", "Chibi Standee Boy/Defaults/shoes.png", ["data-model" => "placeholder:chibi-boy-shoes-barefoot"]],
    ["opt", "Shoes 1", "Chibi Standee Boy/Shoes/shoes.glb", "Chibi Standee Boy/Shoes/shoes.png", "Shoes 1", ["data-price" => "50"]],
    ["opt", "Shoes 2", "Chibi Standee Boy/Shoes/shoes1.glb", "Chibi Standee Boy/Shoes/shoes1.png", "Shoes 2", ["data-price" => "50"]],
    ["opt", "Shoes 3", "Chibi Standee Boy/Shoes/shoes2.glb", "Chibi Standee Boy/Shoes/shoes2.png", "Shoes 3", ["data-price" => "50"]],
    ["opt", "Shoes 4", "Chibi Standee Boy/Shoes/shoes3.glb", "Chibi Standee Boy/Shoes/shoes3.png", "Shoes 4", ["data-price" => "50"]],
    ["opt", "Shoes 5", "Chibi Standee Boy/Shoes/shoes4.glb", "Chibi Standee Boy/Shoes/shoes4.png", "Shoes 5", ["data-price" => "50"]],
    ["opt", "Shoes 6", "Chibi Standee Boy/Shoes/shoes5.glb", "Chibi Standee Boy/Shoes/shoes5.png", "Shoes 6", ["data-price" => "50"]],
    ["opt", "Shoes 7", "Chibi Standee Boy/Shoes/shoes9.glb", "Chibi Standee Boy/Shoes/shoes9.png", "Shoes 7", ["data-price" => "50"]],
    ["opt", "New Balance", "Chibi Standee Boy/Shoes/new balance.glb", "Chibi Standee Boy/Shoes/new balance.png", "New Balance", ["data-price" => "50"]],
    ["opt", "Vans Checkered", "Chibi Standee Boy/Shoes/vans checkered.glb", "Chibi Standee Boy/Shoes/vans checkered.png", "Vans Checkered", ["data-price" => "50"]],
    ["opt", "Crocs", "Chibi Standee Boy/Shoes/crocs.glb", "Chibi Standee Boy/Shoes/crocs.png", "Crocs", ["data-price" => "50"]],
    ["opt", "Panda Dunks", "Chibi Standee Boy/Shoes/panda dunks.glb", "Chibi Standee Boy/Shoes/panda dunks.png", "Panda Dunks", ["data-price" => "50"]],
];

$duChibi = [


    /* ---------- chibiTypeSection ---------- */
    [
        "id" => "chibiTypeSection",
        "class" => "option-section",
        "hidden" => false,
        "title" => ["Product Type*"],
        "grid" => "figure-model-grid",
        "figure" => "chibi",
        "slot" => "chibiType",
        "cards" => [
            ["type", "Full Body Standee", "Chibi Standee Boy/Body/Chibi Boy.png", "Full Body Standee", ["data-product-type" => "standee", "__alt" => "Chibi Full Body Standee", "data-skin-image" => "1"]],
            ["type", "Full Body Keychain", "Chibi Standee Boy/Body/chibi keychain.png", "Full Body Keychain", ["data-product-type" => "keychain", "__alt" => "Chibi Full Body Keychain", "data-skin-image" => "1"]],
        ],
    ],

    /* ---------- chibiModelSection ---------- */
    [
        "id" => "chibiModelSection",
        "class" => "option-section",
        "title" => ["Gender*"],
        "grid" => "figure-model-grid",
        "figure" => "chibi",
        "slot" => "model",
        "cards" => [
            ["model", "Chibi Boy", "Chibi Standee Boy/Chibi Boy.glb", "Chibi Standee Boy/Body/Chibi Boy.png", "Boy", ["data-product-type" => "standee", "data-skin-image" => "1"]],
            ["model", "Chibi Girl", "Chibi Standee Girl/chibi girl.glb", "Chibi Standee Girl/Body/chibi girl.png", "Girl", ["data-product-type" => "standee", "data-skin-image" => "1"]],
            ["model", "Chibi Boy Keychain", "Chibi Standee Boy/chibi keychain.glb", "Chibi Standee Boy/Body/chibi keychain.png", "Boy", ["data-product-type" => "keychain", "data-skin-image" => "1"]],
            ["model", "Chibi Girl Keychain", "Chibi Standee Girl/Chibi Girl Keychain.glb", "Chibi Standee Girl/Body/Chibi Girl Keychain.png", "Girl", ["data-product-type" => "keychain", "data-skin-image" => "1"]],
        ],
    ],

    /* ---------- chibiSkinSection ---------- */
    [
        "id" => "chibiSkinSection",
        "class" => "option-section skin-section",
        "title" => ["Skin Tone*"],
        "grid" => "skin-grid",
        "figure" => "chibi",
        "slot" => "skin",
        "cards" => $duSkinTones,
    ],

    /* ---------- chibiHairSection ---------- */
    [
        "id" => "chibiHairSection",
        "class" => "option-section",
        "title" => ["CHIBI HAIR"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "hair",
        "cards" => $duChibiBoyHair,
    ],

    /* ---------- chibiGirlHairSection ---------- */
    [
        "id" => "chibiGirlHairSection",
        "class" => "option-section",
        "title" => ["CHIBI GIRL HAIR"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "girlHair",
        "cards" => $duChibiGirlHair,
    ],

    /* ---------- chibiHairColorSection ---------- */
    [
        "id" => "chibiHairColorSection",
        "class" => "top-color-picker",
        "attrs" => ["data-figure" => "chibi", "data-slot" => "hairColor"],
        "grid" => "top-color-grid",
        "figure" => "chibi",
        "slot" => "hairColor",
        "cards" => $duColorsBrown,
    ],

    /* ---------- chibiGirlHairColorSection ---------- */
    [
        "id" => "chibiGirlHairColorSection",
        "class" => "top-color-picker",
        "attrs" => ["data-figure" => "chibi", "data-slot" => "girlHairColor"],
        "grid" => "top-color-grid",
        "figure" => "chibi",
        "slot" => "girlHairColor",
        "cards" => $duColorsBrown,
    ],

    /* ---------- chibiTopSection ---------- */
    [
        "id" => "chibiTopSection",
        "class" => "option-section",
        "title" => ["CHIBI TOP"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "top",
        "cards" => $duChibiBoyTop,
    ],

    /* ---------- chibiGirlTopSection ---------- */
    [
        "id" => "chibiGirlTopSection",
        "class" => "option-section",
        "title" => ["CHIBI GIRL TOP"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "girlTop",
        "cards" => $duChibiGirlTop,
    ],

    /* ---------- chibiBottomSection ---------- */
    [
        "id" => "chibiBottomSection",
        "class" => "option-section",
        "title" => ["CHIBI BOTTOM"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "bottom",
        "cards" => $duChibiBoyBottom,
    ],

    /* ---------- chibiGirlBottomSection ---------- */
    [
        "id" => "chibiGirlBottomSection",
        "class" => "option-section",
        "title" => ["CHIBI GIRL BOTTOM"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "girlBottom",
        "cards" => $duChibiGirlBottom,
    ],

    /* ---------- chibiShoesSection ---------- */
    [
        "id" => "chibiShoesSection",
        "class" => "option-section",
        "title" => ["CHIBI SHOES"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "shoes",
        "cards" => $duChibiGirlShoes,
    ],

    /* ---------- chibiBoyShoesSection ---------- */
    [
        "id" => "chibiBoyShoesSection",
        "class" => "option-section",
        "title" => ["CHIBI SHOES"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "shoes",
        "cards" => $duChibiBoyShoes,
    ],

    /* ---------- chibiBoyKeychainHairSection ---------- */
    [
        "id" => "chibiBoyKeychainHairSection",
        "class" => "option-section",
        "title" => ["CHIBI BOY KEYCHAIN HAIR"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "hair",
        "cards" => $duChibiBoyHair,
    ],

    /* ---------- chibiBoyKeychainTopSection ---------- */
    [
        "id" => "chibiBoyKeychainTopSection",
        "class" => "option-section",
        "title" => ["CHIBI BOY KEYCHAIN TOP"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "top",
        "cards" => $duChibiBoyTop,
    ],

    /* ---------- chibiBoyKeychainBottomSection ---------- */
    [
        "id" => "chibiBoyKeychainBottomSection",
        "class" => "option-section",
        "title" => ["CHIBI BOY KEYCHAIN BOTTOM"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "bottom",
        "cards" => $duChibiBoyBottom,
    ],

    /* ---------- chibiBoyKeychainShoesSection ---------- */
    [
        "id" => "chibiBoyKeychainShoesSection",
        "class" => "option-section",
        "title" => ["CHIBI BOY KEYCHAIN SHOES"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "shoes",
        "cards" => $duChibiBoyShoes,
    ],

    /* ---------- chibiGirlKeychainHairSection ---------- */
    [
        "id" => "chibiGirlKeychainHairSection",
        "class" => "option-section",
        "title" => ["CHIBI GIRL KEYCHAIN HAIR"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "girlHair",
        "cards" => $duChibiGirlHair,
    ],

    /* ---------- chibiGirlKeychainTopSection ---------- */
    [
        "id" => "chibiGirlKeychainTopSection",
        "class" => "option-section",
        "title" => ["CHIBI GIRL KEYCHAIN TOP"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "girlTop",
        "cards" => $duChibiGirlTop,
    ],

    /* ---------- chibiGirlKeychainBottomSection ---------- */
    [
        "id" => "chibiGirlKeychainBottomSection",
        "class" => "option-section",
        "title" => ["CHIBI GIRL KEYCHAIN BOTTOM"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "girlBottom",
        "cards" => $duChibiGirlBottom,
    ],

    /* ---------- chibiGirlKeychainShoesSection ---------- */
    [
        "id" => "chibiGirlKeychainShoesSection",
        "class" => "option-section",
        "title" => ["CHIBI GIRL KEYCHAIN SHOES"],
        "grid" => "option-grid",
        "figure" => "chibi",
        "slot" => "shoes",
        "cards" => $duChibiGirlShoes,
    ],

    /* ---------- chibiTopColorSection ---------- */
    [
        "id" => "chibiTopColorSection",
        "class" => "top-color-picker custom-color-only-section",
        "attrs" => ["data-figure" => "chibi", "data-slot" => "topColor"],
    ],

    /* ---------- chibiGirlTopColorSection ---------- */
    [
        "id" => "chibiGirlTopColorSection",
        "class" => "top-color-picker custom-color-only-section",
        "attrs" => ["data-figure" => "chibi", "data-slot" => "girlTopColor"],
    ],

    /* ---------- chibiBottomColorSection ---------- */
    [
        "id" => "chibiBottomColorSection",
        "class" => "top-color-picker custom-color-only-section",
        "attrs" => ["data-figure" => "chibi", "data-slot" => "bottomColor"],
    ],

    /* ---------- chibiGirlBottomColorSection ---------- */
    [
        "id" => "chibiGirlBottomColorSection",
        "class" => "top-color-picker custom-color-only-section",
        "attrs" => ["data-figure" => "chibi", "data-slot" => "girlBottomColor"],
    ],

    /* ---------- chibiShoesColorSection ---------- */
    [
        "id" => "chibiShoesColorSection",
        "class" => "top-color-picker custom-color-only-section",
        "attrs" => ["data-figure" => "chibi", "data-slot" => "shoesColor"],
    ],
];
/* ====================== END CHIBI PANEL ====================== */


/* ====================== [4] HIRONO PANEL ====================== */

$duHirono = [


    /* ---------- hironoTypeSection ---------- */
    [
        "id" => "hironoTypeSection",
        "class" => "option-section",
        "title" => ["Product Type*"],
        "grid" => "figure-model-grid",
        "figure" => "hirono",
        "slot" => "hironoType",
        "cards" => [
            ["model", "Full Body Standee", "Hirono Standee Boy/Hirono Standee-boy.glb", "Hirono Standee Boy/Body/Hirono Standee-boy.png", "Full Body Standee", ["data-mode" => "standee", "data-skin-image" => "1"]],
            ["model", "Full Body Keychain", "Hirono Standee Boy/Hirono Keychain.glb", "Hirono Standee Boy/Body/Hirono Keychain.png", "Full Body Keychain", ["data-mode" => "keychain", "data-skin-image" => "1"]],
        ],
    ],

    /* ---------- hironoModelSection ---------- */
    [
        "id" => "hironoModelSection",
        "class" => "option-section",
        "title" => ["Gender*"],
        "grid" => "figure-model-grid",
        "figure" => "hirono",
        "slot" => "model",
        "cards" => [
            ["model", "Hirono Boy", "Hirono Standee Boy/Hirono Standee-boy.glb", "Hirono Standee Boy/Body/Hirono Standee-boy.png", "Boy", ["data-mode" => "standee", "data-gender" => "boy", "data-skin-image" => "1"]],
            ["model", "Hirono Boy", "Hirono Standee Boy/Hirono Keychain.glb", "Hirono Standee Boy/Body/Hirono Keychain.png", "Boy", ["data-mode" => "keychain", "data-gender" => "boy", "data-skin-image" => "1"]],
            ["model", "Hirono Girl", "Hirono Standee Girl/Hirono Girl default.glb", "Hirono Standee Girl/Body/Hirono Girl default.png", "Girl", ["data-mode" => "standee", "data-gender" => "girl", "data-skin-image" => "1"]],
            ["model", "Hirono Girl", "Hirono Standee Girl/Hirono Girl Keychain.glb", "Hirono Standee Girl/Body/Hirono Girl Keychain.png", "Girl", ["data-mode" => "keychain", "data-gender" => "girl", "data-skin-image" => "1"]],
        ],
    ],

    /* ---------- hironoSkinSection ---------- */
    [
        "id" => "hironoSkinSection",
        "class" => "option-section skin-section",
        "title" => ["Skin Tone*"],
        "grid" => "skin-grid",
        "figure" => "hirono",
        "slot" => "skin",
        "cards" => $duSkinTones,
    ],

    /* ---------- hironoHairSection ---------- */
    [
        "id" => "hironoHairSection",
        "class" => "option-section",
        "title" => ["HIRONO HAIR"],
        "grid" => "option-grid",
        "figure" => "hirono",
        "slot" => "hair",
        "cards" => [
            ["default", "Default Hair", "Hirono Standee Boy/Defaults/hair.png", ["data-skin-image" => "1"]],
            ["opt", "Hirono Hair", "Hirono Standee Boy/Hairstyle/Hirono Hair01.glb", "Hirono Standee Boy/Hairstyle/Hirono Hair01.png", "Hirono Hair", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair.png", "Hirono Girl Hair", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair1", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair1.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair1.png", "Hirono Girl Hair1", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair2", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair2.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair2.png", "Hirono Girl Hair2", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair3", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair3.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair3.png", "Hirono Girl Hair3", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 1", "Hirono Standee Boy/Hairstyle/hair1.glb", "Hirono Standee Boy/Hairstyle/hair1.png", "Hirono Hair 1", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 2", "Hirono Standee Boy/Hairstyle/hair2.glb", "Hirono Standee Boy/Hairstyle/hair2.png", "Hirono Hair 2", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 3", "Hirono Standee Boy/Hairstyle/hair3.glb", "Hirono Standee Boy/Hairstyle/hair3.png", "Hirono Hair 3", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 4", "Hirono Standee Boy/Hairstyle/hair4.glb", "Hirono Standee Boy/Hairstyle/hair4.png", "Hirono Hair 4", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 5", "Hirono Standee Boy/Hairstyle/hair5.glb", "Hirono Standee Boy/Hairstyle/hair5.png", "Hirono Hair 5", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 6", "Hirono Standee Boy/Hairstyle/hair6.glb", "Hirono Standee Boy/Hairstyle/hair6.png", "Hirono Hair 6", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 7", "Hirono Standee Boy/Hairstyle/hair7.glb", "Hirono Standee Boy/Hairstyle/hair7.png", "Hirono Hair 7", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 8", "Hirono Standee Boy/Hairstyle/hair8.glb", "Hirono Standee Boy/Hairstyle/hair8.png", "Hirono Hair 8", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 9", "Hirono Standee Boy/Hairstyle/hair9.glb", "Hirono Standee Boy/Hairstyle/hair9.png", "Hirono Hair 9", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Hair 10", "Hirono Standee Boy/Hairstyle/hair10.glb", "Hirono Standee Boy/Hairstyle/hair10.png", "Hirono Hair 10", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair4", "Hirono Standee Girl/Hairstyle/hair4.glb", "Hirono Standee Girl/Hairstyle/hair4.png", "Hirono Girl Hair4", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair5", "Hirono Standee Girl/Hairstyle/hair5.glb", "Hirono Standee Girl/Hairstyle/hair5.png", "Hirono Girl Hair5", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair6", "Hirono Standee Girl/Hairstyle/hair6.glb", "Hirono Standee Girl/Hairstyle/hair6.png", "Hirono Girl Hair6", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair7", "Hirono Standee Girl/Hairstyle/hair7.glb", "Hirono Standee Girl/Hairstyle/hair7.png", "Hirono Girl Hair7", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair8", "Hirono Standee Girl/Hairstyle/hair8.glb", "Hirono Standee Girl/Hairstyle/hair8.png", "Hirono Girl Hair8", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair9", "Hirono Standee Girl/Hairstyle/hair9.glb", "Hirono Standee Girl/Hairstyle/hair9.png", "Hirono Girl Hair9", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
        ],
    ],

    /* ---------- hironoHairColorSection ---------- */
    [
        "id" => "hironoHairColorSection",
        "class" => "top-color-picker hirono-hair-color-picker",
        "grid" => "top-color-grid",
        "figure" => "hirono",
        "slot" => "hairColor",
        "cards" => $duColorsBrown,
    ],

    /* ---------- hironoOutfitSection ---------- */
    [
        "id" => "hironoOutfitSection",
        "class" => "option-section",
        "title" => ["HIRONO TOPS"],
        "grid" => "option-grid",
        "figure" => "hirono",
        "slot" => "outfit",
        "cards" => [
            ["default", "Sando", "Hirono Standee Boy/Defaults/top.png"],
            ["opt", "Hirono Polo", "Hirono Standee Boy/Tops/Hirono Polo.glb", "Hirono Standee Boy/Tops/Hirono Polo.png", "Hirono Polo", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Jersey", "Hirono Standee Boy/Tops/Hirono Jersey.glb", "Hirono Standee Boy/Tops/Hirono Jersey.png", "Hirono Jersey", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Coat", "Hirono Standee Boy/Tops/Hirono Coat.glb", "Hirono Standee Boy/Tops/Hirono Coat.png", "Hirono Coat", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Polo1", "Hirono Standee Boy/Tops/Hirono Polo1.glb", "Hirono Standee Boy/Tops/Hirono Polo1.png", "Hirono Polo1", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Girl Coat", "Hirono Standee Girl/Tops/Hirono Girl Coat.glb", "Hirono Standee Girl/Tops/Hirono Girl Coat.png", "Hirono Girl Coat", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Dress", "Hirono Standee Girl/Tops/Hirono Girl Dress.glb", "Hirono Standee Girl/Tops/Hirono Girl Dress.png", "Hirono Girl Dress", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl White Dress", "Hirono Standee Girl/Tops/dress.glb", "Hirono Standee Girl/Tops/dress.png", "Hirono Girl White Dress", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono CCC Uniform", "Hirono Standee Boy/Tops/ccc uniform.glb", "Hirono Standee Boy/Tops/ccc uniform.png", "Hirono CCC Uniform", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Hoodie", "Hirono Standee Boy/Tops/hoodie.glb", "Hirono Standee Boy/Tops/hoodie.png", "Hirono Hoodie", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Hoodie 1", "Hirono Standee Boy/Tops/hoodie1.glb", "Hirono Standee Boy/Tops/hoodie1.png", "Hirono Hoodie 1", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Hoodie 2", "Hirono Standee Boy/Tops/hoodie2.glb", "Hirono Standee Boy/Tops/hoodie2.png", "Hirono Hoodie 2", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Hoodie 3", "Hirono Standee Boy/Tops/hoodie3.glb", "Hirono Standee Boy/Tops/hoodie3.png", "Hirono Hoodie 3", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Sweater", "Hirono Standee Boy/Tops/sweater.glb", "Hirono Standee Boy/Tops/sweater.png", "Hirono Sweater", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Sweater 1", "Hirono Standee Boy/Tops/sweater1.glb", "Hirono Standee Boy/Tops/sweater1.png", "Hirono Sweater 1", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Sweater 2", "Hirono Standee Boy/Tops/sweater2.glb", "Hirono Standee Boy/Tops/sweater2.png", "Hirono Sweater 2", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono T-Shirt", "Hirono Standee Boy/Tops/t-shirt.glb", "Hirono Standee Boy/Tops/t-shirt.png", "Hirono T-Shirt", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Girl CCC Uniform", "Hirono Standee Girl/Tops/ccc uniform.glb", "Hirono Standee Girl/Tops/ccc uniform.png", "Hirono Girl CCC Uniform", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Denim Jacket", "Hirono Standee Girl/Tops/denim jacket.glb", "Hirono Standee Girl/Tops/denim jacket.png", "Hirono Girl Denim Jacket", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Fur Jacket", "Hirono Standee Girl/Tops/fur jacket.glb", "Hirono Standee Girl/Tops/fur jacket.png", "Hirono Girl Fur Jacket", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Hoodie", "Hirono Standee Girl/Tops/hoodie.glb", "Hirono Standee Girl/Tops/hoodie.png", "Hirono Girl Hoodie", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Sweater", "Hirono Standee Girl/Tops/sweater.glb", "Hirono Standee Girl/Tops/sweater.png", "Hirono Girl Sweater", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl T-Shirt", "Hirono Standee Girl/Tops/t-shirt.glb", "Hirono Standee Girl/Tops/t-shirt.png", "Hirono Girl T-Shirt", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl T-Shirt 1", "Hirono Standee Girl/Tops/t-shirt1.glb", "Hirono Standee Girl/Tops/t-shirt1.png", "Hirono Girl T-Shirt 1", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl White T-Shirt", "Hirono Standee Girl/Tops/white t-shirt.glb", "Hirono Standee Girl/Tops/white t-shirt.png", "Hirono Girl White T-Shirt", ["data-hirono-gender" => "girl", "data-price" => "80"]],
        ],
    ],

    /* ---------- hironoOutfitColorSection ---------- */
    [
        "id" => "hironoOutfitColorSection",
        "class" => "top-color-picker custom-color-only-section",
        "attrs" => ["data-figure" => "hirono", "data-slot" => "outfitColor"],
    ],

    /* ---------- hironoPantsSection ---------- */
    [
        "id" => "hironoPantsSection",
        "class" => "option-section",
        "title" => ["HIRONO BOTTOM"],
        "grid" => "option-grid",
        "figure" => "hirono",
        "slot" => "pants",
        "cards" => [
            ["default", "Shorts", "Hirono Standee Boy/Defaults/bottom.png"],
            ["opt", "Hirono Pants", "Hirono Standee Boy/Bottoms/Hirono Pants1.glb", "Hirono Standee Boy/Bottoms/Hirono Pants1.png", "Hirono Pants", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Pants 2", "Hirono Standee Boy/Bottoms/Hirono Pants.glb", "Hirono Standee Boy/Bottoms/Hirono Pants.png", "Hirono Pants 2", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Girl Pants", "Hirono Standee Girl/Bottoms/Hirono Girl Pants.glb", "Hirono Standee Girl/Bottoms/Hirono Girl Pants.png", "Hirono Girl Pants", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Basketball Shorts", "Hirono Standee Boy/Bottoms/bastketball shorts.glb", "Hirono Standee Boy/Bottoms/bastketball shorts.png", "Hirono Basketball Shorts", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono CCC Slacks", "Hirono Standee Boy/Bottoms/ccc slacks.glb", "Hirono Standee Boy/Bottoms/ccc slacks.png", "Hirono CCC Slacks", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Pants 3", "Hirono Standee Boy/Bottoms/pants2.glb", "Hirono Standee Boy/Bottoms/pants2.png", "Hirono Pants 3", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Pants 4", "Hirono Standee Boy/Bottoms/pants3.glb", "Hirono Standee Boy/Bottoms/pants3.png", "Hirono Pants 4", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Shorts", "Hirono Standee Boy/Bottoms/shorts.glb", "Hirono Standee Boy/Bottoms/shorts.png", "Hirono Shorts", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Shorts 1", "Hirono Standee Boy/Bottoms/shorts1.glb", "Hirono Standee Boy/Bottoms/shorts1.png", "Hirono Shorts 1", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Shorts 2", "Hirono Standee Boy/Bottoms/shorts2.glb", "Hirono Standee Boy/Bottoms/shorts2.png", "Hirono Shorts 2", ["data-hirono-gender" => "boy", "data-price" => "90"]],
            ["opt", "Hirono Girl CCC Skirt", "Hirono Standee Girl/Bottoms/ccc skirt.glb", "Hirono Standee Girl/Bottoms/ccc skirt.png", "Hirono Girl CCC Skirt", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl CCC Slacks", "Hirono Standee Girl/Bottoms/ccc slacks.glb", "Hirono Standee Girl/Bottoms/ccc slacks.png", "Hirono Girl CCC Slacks", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl Pants 1", "Hirono Standee Girl/Bottoms/pants1.glb", "Hirono Standee Girl/Bottoms/pants1.png", "Hirono Girl Pants 1", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl Pants 2", "Hirono Standee Girl/Bottoms/pants2.glb", "Hirono Standee Girl/Bottoms/pants2.png", "Hirono Girl Pants 2", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl Pants 3", "Hirono Standee Girl/Bottoms/pants3.glb", "Hirono Standee Girl/Bottoms/pants3.png", "Hirono Girl Pants 3", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl Shorts", "Hirono Standee Girl/Bottoms/shorts.glb", "Hirono Standee Girl/Bottoms/shorts.png", "Hirono Girl Shorts", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl Shorts 1", "Hirono Standee Girl/Bottoms/shorts1.glb", "Hirono Standee Girl/Bottoms/shorts1.png", "Hirono Girl Shorts 1", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl Shorts 2", "Hirono Standee Girl/Bottoms/shorts2.glb", "Hirono Standee Girl/Bottoms/shorts2.png", "Hirono Girl Shorts 2", ["data-hirono-gender" => "girl", "data-price" => "90"]],
            ["opt", "Hirono Girl Skirt", "Hirono Standee Girl/Bottoms/skirt.glb", "Hirono Standee Girl/Bottoms/skirt.png", "Hirono Girl Skirt", ["data-hirono-gender" => "girl", "data-price" => "90"]],
        ],
    ],

    /* ---------- hironoPantsColorSection ---------- */
    [
        "id" => "hironoPantsColorSection",
        "class" => "top-color-picker hirono-pants-color-picker",
        "grid" => "top-color-grid",
        "figure" => "hirono",
        "slot" => "pantsColor",
        "cards" => $duColorsBrown,
    ],

    /* ---------- hironoShoesSection ---------- */
    [
        "id" => "hironoShoesSection",
        "class" => "option-section",
        "title" => ["HIRONO SHOES"],
        "grid" => "option-grid",
        "figure" => "hirono",
        "slot" => "shoes",
        "cards" => [
            ["default", "Shoes", "Hirono Standee Boy/Defaults/shoes.png"],
            ["opt", "Hirono Shoes", "Hirono Standee Boy/Shoes/Hirono Shoes.glb", "Hirono Standee Boy/Shoes/Hirono Shoes.png", "Hirono Shoes", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Girl Shoes", "Hirono Standee Girl/Shoes/Hirono Girl Shoes.glb", "Hirono Standee Girl/Shoes/Hirono Girl Shoes.png", "Hirono Girl Shoes", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono New Balance", "Hirono Standee Boy/Shoes/New Balance.glb", "Hirono Standee Boy/Shoes/New Balance.png", "Hirono New Balance", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Crocs", "Hirono Standee Boy/Shoes/crocs.glb", "Hirono Standee Boy/Shoes/crocs.png", "Hirono Crocs", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Shoes 1", "Hirono Standee Boy/Shoes/shoes1.glb", "Hirono Standee Boy/Shoes/shoes1.png", "Hirono Shoes 1", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Vans Checkered", "Hirono Standee Boy/Shoes/vans checkered.glb", "Hirono Standee Boy/Shoes/vans checkered.png", "Hirono Vans Checkered", ["data-hirono-gender" => "boy", "data-price" => "80"]],
            ["opt", "Hirono Girl Black Shoes", "Hirono Standee Girl/Shoes/black shoes.glb", "Hirono Standee Girl/Shoes/black shoes.png", "Hirono Girl Black Shoes", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Crocs", "Hirono Standee Girl/Shoes/crocs.glb", "Hirono Standee Girl/Shoes/crocs.png", "Hirono Girl Crocs", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Shoes 1", "Hirono Standee Girl/Shoes/shoes1.glb", "Hirono Standee Girl/Shoes/shoes1.png", "Hirono Girl Shoes 1", ["data-hirono-gender" => "girl", "data-price" => "80"]],
            ["opt", "Hirono Girl Slippers", "Hirono Standee Girl/Shoes/slippers.glb", "Hirono Standee Girl/Shoes/slippers.png", "Hirono Girl Slippers", ["data-hirono-gender" => "girl", "data-price" => "80"]],
        ],
    ],

    /* ---------- hironoShoesColorSection ---------- */
    [
        "id" => "hironoShoesColorSection",
        "class" => "top-color-picker shoes-color-picker",
        "grid" => "top-color-grid",
        "figure" => "hirono",
        "slot" => "shoesColor",
        "cards" => $duColorsRed,
    ],

    /* ---------- hironoKeychainHairSection ---------- */
    [
        "id" => "hironoKeychainHairSection",
        "class" => "option-section",
        "title" => ["HIRONO KEYCHAIN HAIR"],
        "grid" => "option-grid",
        "figure" => "hirono",
        "slot" => "keychainHair",
        "cards" => [
            ["default", "Default Hair", "Hirono Standee Boy/Defaults/hair.png", ["data-skin-image" => "1"]],
            ["opt", "Hirono Hair", "Hirono Standee Boy/Hairstyle/Hirono Hair01.glb", "Hirono Standee Boy/Hairstyle/Hirono Hair01.png", "Hirono Hair", ["data-hirono-gender" => "boy", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair.png", "Hirono Girl Hair", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair1", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair1.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair1.png", "Hirono Girl Hair1", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair2", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair2.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair2.png", "Hirono Girl Hair2", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
            ["opt", "Hirono Girl Hair3", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair3.glb", "Hirono Standee Girl/Hairstyle/Hirono Girl Hair3.png", "Hirono Girl Hair3", ["data-hirono-gender" => "girl", "data-skin-image" => "1"]],
        ],
    ],

    /* ---------- hironoKeychainHairColorSection ---------- */
    [
        "id" => "hironoKeychainHairColorSection",
        "class" => "top-color-picker hirono-hair-color-picker",
        "title" => ["Keychain Hair Color", "Choose a color"],
        "grid" => "top-color-grid",
        "figure" => "hirono",
        "slot" => "keychainHairColor",
        "cards" => $duColorsBrown,
    ],

    /* ---------- hironoKeychainHatSection ---------- */
    [
        "id" => "hironoKeychainHatSection",
        "class" => "option-section",
        "title" => ["HIRONO HAT"],
        "grid" => "option-grid",
        "figure" => "hirono",
        "slot" => "keychainHat",
        "cards" => [
            ["opt", "KeyChain Cap", "Hirono Head Keychain/Hirono KeyChain Cap.glb", "Hirono Head Keychain/Hirono KeyChain Cap.png", "KeyChain Cap", ["data-price" => "40"]],
            ["opt", "Spider-Man Hat", "Hirono Head Keychain/Hirono SpidermanHat.glb", "Hirono Head Keychain/hirono spiderman.png", "Spider-Man Hat", ["data-price" => "60"]],
            ["opt", "Spider-Venom Hat", "Hirono Head Keychain/Hirono VenomxSpidermanHat.glb", "Hirono Head Keychain/hirono spiderxvenom.png", "Spider-Venom Hat", ["data-price" => "60"]],
        ],
    ],
];
/* ====================== END HIRONO PANEL ====================== */


/* ====================== [5] LISTAHAN PARA SA PRESYO ======================
   Ginagamit ng owner/content-management.php?tab=prices.
   Lahat ng hair / top / bottom / shoes / hat na may 3D model, isang beses bawat item,
   naka-grupo ayon sa Figure Style > Boy / Girl > part:
   ["Funko Pop"]["Boy"]["Hairstyle"][key] = ["name" => ..., "image" => ..., "default" => presyo sa itaas]
   Bagong item (bagong row na "opt" sa listahan sa itaas) = kusang lalabas din dito. */
function du_part_items(): array
{
    global $duFunko, $duChibi, $duHirono;

    $partLabels = [
        "hair" => "Hairstyle", "girlHair" => "Hairstyle", "keychainHair" => "Keychain Hairstyle",
        "top" => "Top", "girlTop" => "Top", "outfit" => "Top",
        "bottom" => "Bottom", "girlBottom" => "Bottom", "pants" => "Bottom",
        "shoes" => "Shoes", "keychainHat" => "Keychain Hat",
    ];

    $items = [];

    foreach (["Funko Pop" => $duFunko, "Chibi" => $duChibi, "Hirono" => $duHirono] as $style => $panel) {
        foreach ($panel as $sec) {
            foreach ($sec["cards"] ?? [] as $row) {
                if ($row[0] !== "opt") {
                    continue;
                }

                $model  = du_path($row[2], DU_GLB);
                $extra  = is_array(end($row)) ? end($row) : [];
                // Boy / Girl: galing sa data-hirono-gender (Hirono) o sa folder ng 3D model ("... Girl/")
                $gender = ($extra["data-hirono-gender"] ?? (stripos($model, "girl") !== false ? "girl" : "boy")) === "girl" ? "Girl" : "Boy";
                $part   = $partLabels[$sec["slot"]] ?? ucfirst($sec["slot"]);
                $key    = figurify_part_price_key($sec["figure"], $sec["slot"], $model);

                $items[$style][$gender][$part][$key] ??= [
                    "name"    => $row[4],
                    "image"   => du_path($row[3], DU_IMG),
                    "default" => (string) ($extra["data-price"] ?? "0"),
                ];
            }
        }
    }

    return $items;
}
/* ====================== END LISTAHAN PARA SA PRESYO ====================== */
