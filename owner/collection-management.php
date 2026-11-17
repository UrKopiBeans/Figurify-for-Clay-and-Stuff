<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/content_helper.php";

// Collection Management - pag-eedit ng Chibi/Hirono/Funko Pop/Other Figures na produkto sa Collection page (iisang listahan sa page)

$grouped = figurify_collection_get_grouped($conn);

$chibiItems   = figurify_collection_filter_category($grouped["normal"], "chibi");
$hironoItems  = figurify_collection_filter_category($grouped["normal"], "hirono");
$funkoItems   = figurify_collection_filter_category($grouped["normal"], "funko");
/* hiwalay na Box & Add-ons section */
$customItems  = figurify_collection_filter_category($grouped["special"], "custom");
$specialItems = array_values(array_filter($grouped["special"], fn($item) => $item["category"] !== "custom"));

$saved = isset($_GET["saved"]);


// card ng isang produkto (Chibi/Hirono/Funko Pop/Box & Add-ons).
// Ginagaya rin ito ng <template> sa ibaba para sa "+ Add".
function figurify_cm_collection_item(string $groupKey, string $label, array $item, string $fallbackImg): void
{
    $itemId = (int) $item["item_id"];
    $imageValue = $item["image"] ?? "";
    $name = "items[{$groupKey}][{$itemId}]";
    ?>
    <div class="content-subgroup">
        <div class="content-subgroup-title"><?php echo e($label); ?> #<?php echo $itemId; ?></div>

        <label class="content-field content-field-full">
            Image
            <div class="content-media-field">
                <div
                    class="content-media-preview content-media-upload cm-ratio-square"
                    onclick="this.querySelector('input[type=file]').click()"
                >
                    <img src="<?php echo e(figurify_resolve_image_src($imageValue, $fallbackImg)); ?>" alt="<?php echo e($label); ?> #<?php echo $itemId; ?> preview">
                    <div class="content-media-upload-overlay">
                        <span>Click to change</span>
                    </div>
                    <input
                        type="file"
                        accept="image/*"
                        hidden
                        onchange="figurifyUploadMedia(this, 'image')"
                    >
                </div>
                <input type="hidden" name="<?php echo e($name); ?>[image]" value="<?php echo e($imageValue); ?>">
                <p class="content-media-status"></p>
            </div>
        </label>

        <input type="hidden" name="<?php echo e($name); ?>[item_id]" value="<?php echo $itemId; ?>">
        <input type="hidden" name="<?php echo e($name); ?>[remove]" value="0">

        <label class="content-field">
            Product Type
            <input type="text" name="<?php echo e($name); ?>[type]" value="<?php echo e($item["type"]); ?>" placeholder="e.g. Half-body, Standee">
        </label>

        <label class="content-field">
            Size
            <input type="text" name="<?php echo e($name); ?>[size]" value="<?php echo e($item["size"]); ?>" placeholder='e.g. 2&quot; or Custom'>
        </label>

        <label class="content-field">
            Price
            <input type="text" name="<?php echo e($name); ?>[price]" value="<?php echo e($item["price"]); ?>" placeholder="e.g. ₱450">
        </label>

        <div class="cm-item-footer">
            <label class="content-field cm-checkbox-field">
                <input type="checkbox" name="<?php echo e($name); ?>[bestseller]" value="1" <?php echo !empty($item["is_bestseller"]) ? "checked" : ""; ?>>
                Best Seller
            </label>

            <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
        </div>
    </div>
    <?php
}

// Same idea pero para sa Other Figures - free-form ang Style/Kategorya
function figurify_cm_special_item(array $item, string $fallbackImg): void
{
    $itemId = (int) $item["item_id"];
    $imageValue = $item["image"] ?? "";
    $category = $item["category"] ?? "special";
    $name = "items[special][{$itemId}]";

    $categoryOptions = [
        "funko"   => "Funko Pop",
        "special" => "Other",
    ];
    ?>
    <div class="content-subgroup">
        <div class="content-subgroup-title">Other <?php echo $itemId; ?></div>

        <label class="content-field content-field-full">
            Image
            <div class="content-media-field">
                <div
                    class="content-media-preview content-media-upload cm-ratio-square"
                    onclick="this.querySelector('input[type=file]').click()"
                >
                    <img src="<?php echo e(figurify_resolve_image_src($imageValue, $fallbackImg)); ?>" alt="Other item <?php echo $itemId; ?> preview">
                    <div class="content-media-upload-overlay">
                        <span>Click to change</span>
                    </div>
                    <input
                        type="file"
                        accept="image/*"
                        hidden
                        onchange="figurifyUploadMedia(this, 'image')"
                    >
                </div>
                <input type="hidden" name="<?php echo e($name); ?>[image]" value="<?php echo e($imageValue); ?>">
                <p class="content-media-status"></p>
            </div>
        </label>

        <input type="hidden" name="<?php echo e($name); ?>[item_id]" value="<?php echo $itemId; ?>">
        <input type="hidden" name="<?php echo e($name); ?>[remove]" value="0">

        <label class="content-field">
            Style (header)
            <input type="text" name="<?php echo e($name); ?>[style]" value="<?php echo e($item["style"]); ?>" placeholder="e.g. Pop Culture">
        </label>

        <label class="content-field">
            Category (for filter)
            <select name="<?php echo e($name); ?>[category]">
                <?php foreach ($categoryOptions as $value => $text): ?>
                    <option value="<?php echo e($value); ?>" <?php echo $category === $value ? "selected" : ""; ?>><?php echo e($text); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="content-field">
            Product Type
            <input type="text" name="<?php echo e($name); ?>[type]" value="<?php echo e($item["type"]); ?>" placeholder="e.g. Custom Figure">
        </label>

        <label class="content-field">
            Size
            <input type="text" name="<?php echo e($name); ?>[size]" value="<?php echo e($item["size"]); ?>" placeholder="e.g. Custom">
        </label>

        <label class="content-field">
            Price
            <input type="text" name="<?php echo e($name); ?>[price]" value="<?php echo e($item["price"]); ?>" placeholder="e.g. Starts at ₱500">
        </label>

        <div class="cm-item-footer">
            <label class="content-field cm-checkbox-field">
                <input type="checkbox" name="<?php echo e($name); ?>[bestseller]" value="1" <?php echo !empty($item["is_bestseller"]) ? "checked" : ""; ?>>
                Best Seller
            </label>

            <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
        </div>
    </div>
    <?php
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Figurify | Content Management</title>

<!-- ===================== STYLE NG PAGE NA ITO: collection-management.css ===================== -->
<link rel="stylesheet" href="collection-management.css">


</head>


<body class="sf-fixed">

<div class="app">

<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2 class="portal-title">
            Content Management
        </h2>

        

    </div>


        <div class="top-actions">

<!-- ==========================================================
     [TOPBAR-DATETIME]  (dating Shared/topbar-datetime.php - nasa page na ito na)
     ANO ITO: petsa at oras sa taas ng page, sa kaliwa ng notification bell.
     PAANO GUMAGANA: ang JS sa Shared/sidebar.php ([3] TOPBAR DATE / TIME) ang nagpapalit ng .cs-tb-date at .cs-tb-time
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


    <div class="cm-tabs">
        <a href="content-management.php" class="cm-tab">Home Page</a>
        <a href="collection-management.php" class="cm-tab active">Collection</a>
        <a href="content-management.php?tab=legal" class="cm-tab">Legal Pages</a>
    </div>


<?php if ($saved): ?>

    <?php
        echo figurify_saved_toast("Changes saved. You will see this on the Collection page now."); // "Changes saved" pop up (helpers/security_helper.php)
    ?>

<?php endif; ?>


<div class="sf-body">

<?php
/* Filter ng Collection: isang category lang ang nakikita sa bawat oras */
echo cs_section_filter("collection-management", [
    ["section-chibi",   "Chibi",          count($chibiItems)],
    ["section-hirono",  "Hirono",         count($hironoItems)],
    ["section-funko",   "Funko Pop",      count($funkoItems)],
    ["section-custom",  "Box & Add-ons",  count($customItems)],
    ["section-special", "Other Figures",  count($specialItems)],
]);
?>

<form
    method="POST"
    action="save_collection.php"
    class="content-form sf-form"
>

    <!-- CHIBI -->

    <section class="card content-section sf-scroll" id="section-chibi">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Chibi</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('chibi')">+ Add Chibi</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-chibi">

            <?php foreach ($chibiItems as $item): ?>
                <?php figurify_cm_collection_item("chibi", "Chibi", $item, "../Image/figures/chibi/chibi-head-keychain-pair.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- HIRONO -->

    <section class="card content-section sf-scroll" id="section-hirono">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Hirono</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('hirono')">+ Add Hirono</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-hirono">

            <?php foreach ($hironoItems as $item): ?>
                <?php figurify_cm_collection_item("hirono", "Hirono", $item, "../Image/figures/hirono/hirono-style.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- FUNKO POP -->

    <section class="card content-section sf-scroll" id="section-funko">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Funko Pop</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('funko')">+ Add Funko Pop</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-funko">

            <?php foreach ($funkoItems as $item): ?>
                <?php figurify_cm_collection_item("funko", "Funko Pop", $item, "../Image/figures/funko-pop/funko-pop-style.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- CUSTOM BOX -->

    <section class="card content-section sf-scroll" id="section-custom">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Box &amp; Add-ons</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('custom')">+ Add Box &amp; Add-ons</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-custom">

            <?php foreach ($customItems as $item): ?>
                <?php figurify_cm_collection_item("custom", "Box & Add-ons", $item, "../Image/boxes/hirono-box-checkered.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- SPECIAL FIGURES -->

    <section class="card content-section sf-scroll" id="section-special">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Other Figures</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('special')">+ Add Other Figure</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-special">

            <?php foreach ($specialItems as $item): ?>
                <?php figurify_cm_special_item($item, "../Image/figures/hirono/hirono-style.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <div class="content-form-actions">
        <button
            type="submit"
            class="content-save-btn"
        >
            Save Changes
        </button>
    </div>

</form>

</div><!-- /.sf-body -->


<!-- Templates para sa "+ Mag-add" - JS ang gagawa ng bagong subgroup base dito -->

<template id="tpl-chibi">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Chibi</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/chibi/chibi-head-keychain-pair.jpg" alt="New Chibi item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[chibi][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[chibi][__IDX__][item_id]" value="">
    <input type="hidden" name="items[chibi][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[chibi][__IDX__][type]" value="" placeholder="e.g. Half-body, Standee">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[chibi][__IDX__][size]" value="" placeholder='e.g. 2&quot; or Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[chibi][__IDX__][price]" value="" placeholder="e.g. ₱450">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[chibi][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-hirono">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Hirono</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/hirono/hirono-style.jpg" alt="New Hirono item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[hirono][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[hirono][__IDX__][item_id]" value="">
    <input type="hidden" name="items[hirono][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[hirono][__IDX__][type]" value="" placeholder="e.g. Standee, Couple Set">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[hirono][__IDX__][size]" value="" placeholder='e.g. 3.5&quot; or Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[hirono][__IDX__][price]" value="" placeholder="e.g. ₱750">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[hirono][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-funko">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Funko Pop</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/funko-pop/funko-pop-style.jpg" alt="New Funko Pop item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[funko][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[funko][__IDX__][item_id]" value="">
    <input type="hidden" name="items[funko][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[funko][__IDX__][type]" value="" placeholder="e.g. Standee">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[funko][__IDX__][size]" value="" placeholder='e.g. 4&quot; or Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[funko][__IDX__][price]" value="" placeholder="e.g. ₱1,000">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[funko][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-custom">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Custom Box</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/boxes/hirono-box-checkered.jpg" alt="New Custom Box item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[custom][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[custom][__IDX__][item_id]" value="">
    <input type="hidden" name="items[custom][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[custom][__IDX__][type]" value="" placeholder="e.g. Solo Box, 3D Box">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[custom][__IDX__][size]" value="" placeholder='e.g. Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[custom][__IDX__][price]" value="" placeholder="e.g. ₱500">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[custom][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-special">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Other Figure</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/hirono/hirono-style.jpg" alt="New Other Figure item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[special][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[special][__IDX__][item_id]" value="">
    <input type="hidden" name="items[special][__IDX__][remove]" value="0">

    <label class="content-field">
      Style (header)
      <input type="text" name="items[special][__IDX__][style]" value="" placeholder="e.g. Pop Culture">
    </label>

    <label class="content-field">
      Category (for filter)
      <select name="items[special][__IDX__][category]">
        <option value="funko">Funko Pop</option>
        <option value="special" selected>Other</option>
      </select>
    </label>

    <label class="content-field">
      Product Type
      <input type="text" name="items[special][__IDX__][type]" value="" placeholder="e.g. Custom Figure">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[special][__IDX__][size]" value="" placeholder="e.g. Custom">
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[special][__IDX__][price]" value="" placeholder="e.g. Starts at ₱500">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[special][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>


</main>

</div>

<script>
/* ==========================================================================
   [MEDIA UPLOAD (larawan)]
   ========================================================================== */
// Media upload (larawan) - kapareho ng content-management.php: auto-upload sa upload_content_media.php,
// then update hidden input + live preview.
function figurifyUploadMedia(fileInput, kind) {

    const file = fileInput.files && fileInput.files[0];

    if (!file) {
        return;
    }

    const field = fileInput.closest(".content-media-field");
    const hiddenInput = field.querySelector('input[type="hidden"]');
    const status = field.querySelector(".content-media-status");
    const previewImg = field.querySelector("img");

    if (status) {
        status.textContent = "Uploading...";
        status.classList.remove("error");
    }

    const formData = new FormData();
    formData.append("file", file);
    formData.append("kind", kind);
    formData.append("category", "collection");

    // ipadala ang lumang value para mabura ng server ang lumang file
    if (hiddenInput && hiddenInput.value) {
        formData.append("old_value", hiddenInput.value);
    }

    formData.append("csrf_token", window.csCsrfToken || "");

    fetch("upload_content_media.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {

            if (!data.ok) {
                if (status) {
                    status.textContent = data.error || "Failed to upload file.";
                    status.classList.add("error");
                }
                return;
            }

            if (hiddenInput) {
                hiddenInput.value = data.value;
            }

            if (previewImg) {
                previewImg.src = data.src;
            }

            if (status) {
                status.textContent = "Uploaded. Click \"Save Changes\" to apply.";
            }

        })
        .catch(() => {
            if (status) {
                status.textContent = "Upload error. Please try again.";
                status.classList.add("error");
            }
        });

    fileInput.value = "";
}

// Add: clone ang <template> ng group, palitan ang __IDX__ placeholder ng temp ID, idagdag sa grid.
// Remove: kung existing item, itago + set hidden remove flag ("1"); kung bagong item pa, tanggalin sa DOM agad.
let figurifyCmCounter = 0;

function figurifyAddCollectionItem(groupKey) {
    const tpl = document.getElementById("tpl-" + groupKey);
    const grid = document.getElementById("grid-" + groupKey);

    if (!tpl || !grid) {
        return;
    }

    figurifyCmCounter++;
    const idx = "new" + figurifyCmCounter;

    const html = tpl.innerHTML.split("__IDX__").join(idx);

    const wrapper = document.createElement("div");
    wrapper.innerHTML = html.trim();

    const node = wrapper.firstElementChild;

    if (node) {
        grid.appendChild(node);

        // ipakita agad ang bagong item sa loob ng scroll box
        node.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
}

function figurifyRemoveCollectionItem(button) {
    const subgroup = button.closest(".content-subgroup");

    if (!subgroup) {
        return;
    }

    const idInput = subgroup.querySelector('input[name$="[item_id]"]');

    if (idInput && idInput.value) {
        const removeFlag = subgroup.querySelector('input[name$="[remove]"]');
        if (removeFlag) {
            removeFlag.value = "1";
        }
        subgroup.hidden = true;
    } else {
        subgroup.remove();
    }
}
/* ==========================================================================
   END [MEDIA UPLOAD (larawan)]
   ========================================================================== */


/* Auto-grow ng lahat ng textbox sa page na ito: lumalaki habang nagta-type,
   kaya walang scrollbar sa loob ng textbox at tuloy-tuloy ang scroll ng page. */
(function () {
    function grow(t) {
        if (!t.offsetParent) {
            return; // nakatago pa (ibang tab) - aayusin pag lumabas na
        }
        // dagdagan ang rows hanggang kasya ang laman (walang inline style)
        t.rows = 1;
        while (t.scrollHeight > t.clientHeight && t.rows < 60) {
            t.rows += 1;
        }
    }

    function growAll() {
        document.querySelectorAll("textarea.cs-autogrow").forEach(grow);
    }

    document.querySelectorAll("textarea").forEach(function (t) {
        t.classList.add("cs-autogrow");
        t.addEventListener("input", function () { grow(t); });
    });

    growAll();
    window.addEventListener("load", growAll);

    var timer;
    window.addEventListener("resize", function () {
        clearTimeout(timer);
        timer = setTimeout(growAll, 100);
    });

    // kapag lumipat ng tab (Home Page / Collection) o bumukas ang nakatagong section
    document.addEventListener("click", function () { setTimeout(growAll, 0); });
})();
</script>

</body>

</html>