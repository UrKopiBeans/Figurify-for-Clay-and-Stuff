<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

/* DATABASE CONNECTION - iisang config (Admin/database.php) */
require_once __DIR__ . "/../Admin/database.php";

/* DEFAULT CUSTOMER INFORMATION */
$customer_name = "";
$customer_email = "";
$profile_picture = "";

/* GET LOGGED-IN CUSTOMER */
if (isset($_SESSION["user_id"])) {

    $user_id = $_SESSION["user_id"];

    $stmt = $conn->prepare("
        SELECT full_name, email, profile_picture
        FROM users
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $customer_name = $user["full_name"];
        $customer_email = $user["email"];
        $profile_picture = $user["profile_picture"];
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Clay and Stuff — Dress Up</title>

  <!-- shared base styles (navbar, hero, buttons) -->
  <link rel="stylesheet" href="commission.css?v=87" />

  <!-- lahat ng Dress Up styles -->
  <link rel="stylesheet" href="dress-up.css?v=45" />

</head>
<body data-page="editor">

<!-- navbar -->
<?php include "../Shared/navbar.php"; ?>



<!-- main -->

<main class="page">

<!-- DRESS UP - sariling page na (dress-up.php / dress-up.css / dress-up.js) -->

<div id="dressUpModule">

    <div class="form-shell">

        <a href="commission.php" class="dress-up-back" id="dressUpBackLink" hidden>Back</a>

        <div class="dressup-workspace">

            <div class="dressup-left">


            <div class="preview-header">

                <h2>Your Figure</h2>

                <button type="button" id="resetBtn">Reset Design</button>

            </div>


            <!-- 3D figure preview -->

            <div class="figure-area">

                <canvas
                    id="figureViewer"
                    class="figure-viewer"
                ></canvas>

            </div>


            <!-- Reset at Add Another Order: magkatabi sa ilalim ng figure -->
            <div class="figure-actions">
                <button
                    type="button"
                    id="dressUpSaveFigureBtn"
                    class="figure-add-btn"
                    hidden
                >
                    Save Changes
                </button>
            </div>



            </div>


            <div class="dressup-right">

            <ol class="wizard-pills" id="wizardPills" aria-label="Dress up steps">
                <li data-wizard="1"><span>Figure Details</span></li>
                <li data-wizard="2"><span>Parts</span></li>
                <li data-wizard="3"><span>Box</span></li>
            </ol>

            <p class="dressup-view-note" id="dressUpViewNote" hidden></p>

            <!-- scroll area: pare-pareho ang laki ng Figure Details container; kapag dumami
                 ang laman (hal. maraming Tops), dito na lang mag-i-scroll, nasa baba pa rin ang Back/Next -->
            <div class="dressup-right-scroll" id="dressUpRightScroll">


            <!-- figure details: from Figure Style up to the last option (e.g. shoes), then Box/Add-ons na kung meron -->

            <div
                class="option-section figure-details-heading"
                id="figureDetailsContainer"
            >



                <!-- figure style -->

                <div
                    class="option-section figure-style-section"
                    id="figureStyleSection"
                >

                    <span class="figure-style-kicker">FIGURE STYLE</span>

                    <div class="figure-style-grid">

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="funko"
                        >
                            <img src="../Image/figures/covers/funko-pop-style-cover.jpg" alt="Funko Pop">
                            <span>Funko Pop</span>
                        </button>

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="hirono"
                        >
                            <img src="../Image/figures/covers/hirono-style-cover.jpg" alt="Hirono">
                            <span>Hirono</span>
                        </button>

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="chibi"
                        >
                            <img src="../Image/figures/covers/chibi-style-cover.jpg" alt="Chibi">
                            <span>Chibi</span>
                        </button>

                    </div>

                    <div
                        class="figure-style-navigation"
                        id="figureStyleNavigation"
                    >
                        <button
                            type="button"
                            class="continue-btn step-navigation-button"
                            id="nextFigureStyle"
                            disabled
                        >
                            Next
                        </button>
                    </div>

                </div>


                <!-- funko pop panel -->

                <div
                    class="category-panel"
                    id="funkoPanel"
                    hidden
                >

                    <div
                    class="option-section"
                    id="funkoModelSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>GENDER</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="funko"
                            data-slot="model"
                            data-name="Funko Pop Default"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/funko boy.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/Funko pop-default.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/Funko pop-default.png"
                                    alt="Funko Pop Default"
                                >
                            </div>
                            <span class="figure-model-name">Male</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="funko"
                            data-slot="model"
                            data-name="Funko Pop-Girl"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko pop-girl.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/Funko pop-girl.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/Funko pop-girl.png"
                                    alt="Funko Pop Girl"
                                >
                            </div>
                            <span class="figure-model-name">Female</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section skin-section"
                    id="funkoSkinSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Skin Tone</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="skin-grid">

                        <button
                            type="button"
                            class="skin-card"
                            data-figure="funko"
                            data-slot="skin"
                            data-name="Light"
                            data-color="#F7C8A0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-light"></span>
                        </button>


                        <button
                            type="button"
                            class="skin-card"
                            data-figure="funko"
                            data-slot="skin"
                            data-name="Dark"
                            data-color="#D19477"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-dark"></span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section funko-step"
                    id="funkoGirlHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Default Hair"
                            data-model="placeholder:funko-girlHair-default-hair"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/funkoGirl-hair.png" alt="Default Hair">
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Hair 01"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko GirlHair01.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/Funko GirlHair01.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/Funko GirlHair01.png"
                                    alt="Hair 01"
                                >
                            </div>
                            <span>Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Hair 02"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko GirlHair02.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/Funko GirlHair02.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/Funko GirlHair02.png"
                                    alt="Hair 02"
                                >
                            </div>
                            <span>Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Hair 03"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko GirlHair03.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/Funko GirlHair03.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/Funko GirlHair03.png"
                                    alt="Hair 03"
                                >
                            </div>
                            <span>Hair 03</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section top-section"
                    id="funkoGirlTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP TOP</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Tank Top"
                            data-model="placeholder:funko-girlTop-tank-top"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/funkoGirl-top.png" alt="Tank Top">
                            </div>
                            <span>Tank Top</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Girl Shirt 01"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko Girl T-Shirt.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/GirlShirt01.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/GirlShirt01.png"
                                    alt="Girl Shirt 01"
                                >
                            </div>
                            <span>Girl Shirt 01</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Girl Shirt 02"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko Girl T-Shirt1.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/Girl Shirt02.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/Girl Shirt02.png"
                                    alt="Girl Shirt 02"
                                >
                            </div>
                            <span>Girl Shirt 02</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Girl Shirt 03"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko Girl T-Shirt2.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/GirlShirt03.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/GirlShirt03.png"
                                    alt="Girl Shirt 03"
                                >
                            </div>
                            <span>Girl Shirt 03</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Jacket"
                            data-model="placeholder:funko-girlTop-jacket"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🧥</span>
                            </div>
                            <span>Jacket</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Long Sleeve"
                            data-model="placeholder:funko-girlTop-long-sleeve"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👔</span>
                            </div>
                            <span>Long Sleeve</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker funko-step"
                    id="funkoGirlTopColorSection"
                    data-figure="funko"
                    data-slot="girlTopColor"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="Red"
                            data-color="#D94B4B"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-red"></span>
                            <span class="top-color-name">Red</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="funkoGirlBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP BOTTOM</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="girlBottom"
                            data-name="Shorts"
                            data-model="placeholder:funko-girlBottom-shorts"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/funkoGirl-bottom.png" alt="Shorts">
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlBottom"
                            data-name="Girl Bottom"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko Girl Bottom.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/Funko Girl Bottom.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/Funko Girl Bottom.png"
                                    alt="Girl Bottom"
                                >
                            </div>
                            <span>Girl Bottom</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlBottom"
                            data-name="Girl Bottom 1"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Girl/Funko Girl Bottom1.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Girl/Funko Girl Bottom1.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Girl/Funko Girl Bottom1.png"
                                    alt="Girl Bottom 1"
                                >
                            </div>
                            <span>Girl Bottom 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker pants-color-picker funko-step"
                    id="funkoGirlBottomColorSection"
                    hidden
                >
                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section funko-step"
                    id="funkoHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Default Hair"
                            data-model="placeholder:funko-hair-default-hair"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/funkoBoy-hair.png" alt="Default Hair">
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 1"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair.png"
                                    alt="Hair 1"
                                >
                            </div>
                            <span>Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 2"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair1.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair1.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair1.png"
                                    alt="Hair 2"
                                >
                            </div>
                            <span>Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 3"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair2.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair2.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair2.png"
                                    alt="Hair 3"
                                >
                            </div>
                            <span>Hair 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 4"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair3.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair3.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair3.png"
                                    alt="Hair 4"
                                >
                            </div>
                            <span>Hair 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 5"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair5.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair5.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair5.png"
                                    alt="Hair 5"
                                >
                            </div>
                            <span>Hair 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 6"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair6.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair6.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair6.png"
                                    alt="Hair 6"
                                >
                            </div>
                            <span>Hair 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 7"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair7.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair7.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair7.png"
                                    alt="Hair 7"
                                >
                            </div>
                            <span>Hair 7</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 8"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair8.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair8.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair8.png"
                                    alt="Hair 8"
                                >
                            </div>
                            <span>Hair 8</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 9"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/hair/hair9.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/hair/hair9.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/hair/hair9.png"
                                    alt="Hair 9"
                                >
                            </div>
                            <span>Hair 9</span>
                            <small class="option-price" data-base-price></small>
                        </button>



                    </div>

                </div>


                <div
                    class="top-color-picker hair-color-picker funko-step"
                    id="funkoHairColorSection"
                    hidden
                >



                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Brown"
                            data-color="#5C3A2E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Blonde"
                            data-color="#D8BE7A"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">Blonde</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section top-section"
                    id="funkoTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP TOPS</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Sando"
                            data-model="placeholder:funko-top-sando"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/funkoBoy-top.png" alt="Sando">
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="CCC Uniform"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/ccc uniform.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/ccc uniform.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/ccc uniform.png"
                                    alt="CCC Uniform"
                                >
                            </div>
                            <span>CCC Uniform</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Hoodie"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/hoodie.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/hoodie.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/hoodie.png"
                                    alt="Hoodie"
                                >
                            </div>
                            <span>Hoodie</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Plain Polo"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/polo plain.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/polo plain.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/polo plain.png"
                                    alt="Plain Polo"
                                >
                            </div>
                            <span>Plain Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Sweater"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/sweater.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/sweater.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/sweater.png"
                                    alt="Sweater"
                                >
                            </div>
                            <span>Sweater</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Hockey Jersey"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/hockey jersey.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/hockey jersey.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/hockey jersey.png"
                                    alt="Hockey Jersey"
                                >
                            </div>
                            <span>Hockey Jersey</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Leather Jacket"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/leather jacket.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/leather jacket.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/leather jacket.png"
                                    alt="Leather Jacket"
                                >
                            </div>
                            <span>Leather Jacket</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="New York T-Shirt"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/new york t-shirt.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/new york t-shirt.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/new york t-shirt.png"
                                    alt="New York T-Shirt"
                                >
                            </div>
                            <span>New York T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Teacher Polo"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/teacher polo.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo.png"
                                    alt="Teacher Polo"
                                >
                            </div>
                            <span>Teacher Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Teacher Polo 2"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/teacher polo1.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo1.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo1.png"
                                    alt="Teacher Polo 2"
                                >
                            </div>
                            <span>Teacher Polo 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Teacher Polo 3"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/teacher polo2.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo2.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo2.png"
                                    alt="Teacher Polo 3"
                                >
                            </div>
                            <span>Teacher Polo 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Teacher Polo 4"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/top/teacher polo3.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo3.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/top/teacher polo3.png"
                                    alt="Teacher Polo 4"
                                >
                            </div>
                            <span>Teacher Polo 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>



                    </div>

                </div>


                <div
                    class="top-color-picker shirt-color-picker funko-step"
                    id="funkoTopColorSection"
                    hidden
                >



                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="Red"
                            data-color="#D94B4B"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-red"></span>
                            <span class="top-color-name">Red</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="funkoBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP BOTTOMS</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Shorts"
                            data-model="placeholder:funko-bottom-shorts"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/funkoBoy-bottom.png" alt="Shorts">
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="CCC Slacks"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/bottom/ccc slacks.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/bottom/ccc slacks.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/bottom/ccc slacks.png"
                                    alt="CCC Slacks"
                                >
                            </div>
                            <span>CCC Slacks</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Basketball Shorts"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/bottom/basketball shorts.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/bottom/basketball shorts.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/bottom/basketball shorts.png"
                                    alt="Basketball Shorts"
                                >
                            </div>
                            <span>Basketball Shorts</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Pants 1"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/bottom/pants.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/bottom/pants.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/bottom/pants.png"
                                    alt="Pants 1"
                                >
                            </div>
                            <span>Pants 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Pants 2"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/bottom/pants1.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/bottom/pants1.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/bottom/pants1.png"
                                    alt="Pants 2"
                                >
                            </div>
                            <span>Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Pants 3"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/bottom/pants2.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/bottom/pants2.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/bottom/pants2.png"
                                    alt="Pants 3"
                                >
                            </div>
                            <span>Pants 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Pants 4"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/bottom/pants3.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/bottom/pants3.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/bottom/pants3.png"
                                    alt="Pants 4"
                                >
                            </div>
                            <span>Pants 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Pants 5"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/bottom/pants4.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/bottom/pants4.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/bottom/pants4.png"
                                    alt="Pants 5"
                                >
                            </div>
                            <span>Pants 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>



                    </div>

                </div>


                <div
                    class="top-color-picker pants-color-picker funko-step"
                    id="funkoBottomPantsColorSection"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="funkoShoesSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP SHOES</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes"
                            data-model="placeholder:funko-shoes-shoes"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/funkoBoy-shoes.png" alt="Shoes">
                            </div>
                            <span>Shoes</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 1"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes6.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes6.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes6.png"
                                    alt="Shoes 1"
                                >
                            </div>
                            <span>Shoes 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 2"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes7.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes7.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes7.png"
                                    alt="Shoes 2"
                                >
                            </div>
                            <span>Shoes 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 3"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes8.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes8.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes8.png"
                                    alt="Shoes 3"
                                >
                            </div>
                            <span>Shoes 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 4"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes9.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes9.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes9.png"
                                    alt="Shoes 4"
                                >
                            </div>
                            <span>Shoes 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 5"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes10.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes10.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes10.png"
                                    alt="Shoes 5"
                                >
                            </div>
                            <span>Shoes 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 6"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes11.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes11.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes11.png"
                                    alt="Shoes 6"
                                >
                            </div>
                            <span>Shoes 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 7"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes12.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes12.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes12.png"
                                    alt="Shoes 7"
                                >
                            </div>
                            <span>Shoes 7</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 8"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes13.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes13.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes13.png"
                                    alt="Shoes 8"
                                >
                            </div>
                            <span>Shoes 8</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 9"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes14.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes14.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes14.png"
                                    alt="Shoes 9"
                                >
                            </div>
                            <span>Shoes 9</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes 10"
                            data-model="dressup-assets/GLB_files/Funko Pop Standee Boy/shoes/shoes15.glb"
                            data-image="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes15.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Pop Standee Boy/shoes/shoes15.png"
                                    alt="Shoes 10"
                                >
                            </div>
                            <span>Shoes 10</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                    </div>

                </div>


                <div
                    class="top-color-picker custom-color-only-section"
                    id="funkoShoesColorSection"
                    data-figure="funko"
                    data-slot="shoesColor"
                    hidden
                >

                </div>

            </div>


            <!-- chibi panel -->

            <div
                class="category-panel"
                id="chibiPanel"
                hidden
            >

                <div
                    class="option-section"
                    id="chibiTypeSection"
                >

                    <div class="section-title">
                        <h2>PRODUCT TYPE</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card chibi-type-card"
                            data-figure="chibi"
                            data-slot="chibiType"
                            data-product-type="standee"
                            data-name="Full Body Standee"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="../Image/figures/covers/chibi-full-body-standee-cover.jpg" alt="Chibi Full Body Standee">
                            </div>
                            <span>Full Body Standee</span>
                        </button>


                        <button
                            type="button"
                            class="option-card chibi-type-card"
                            data-figure="chibi"
                            data-slot="chibiType"
                            data-product-type="keychain"
                            data-name="Full Body Keychain"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="../Image/figures/covers/chibi-full-body-keychain-cover.jpg" alt="Chibi Full Body Keychain">
                            </div>
                            <span>Full Body Keychain</span>
                        </button>

                    </div>

                </div>

                <div
                    class="option-section"
                    id="chibiModelSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>GENDER</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="standee"
                            data-name="Chibi Boy"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/Chibi Boy.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/Chibi Boy.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/Chibi Boy.png"
                                    alt="Chibi Boy"
                                >
                            </div>
                            <span class="figure-model-name">Male</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="standee"
                            data-name="Chibi Girl"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/chibi girl-light.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/Chibi Girl.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/Chibi Girl.png"
                                    alt="Chibi Girl"
                                >
                            </div>
                            <span class="figure-model-name">Female</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="keychain"
                            data-name="Chibi Boy Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Keychain Boy/chibi keychain-light.glb"
                            data-image="dressup-assets/images/Chibi Keychain Boy/Chibi Boy Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="dressup-assets/images/Chibi Keychain Boy/Chibi Boy Keychain.png" alt="Chibi Boy Keychain">
                            </div>
                            <span class="figure-model-name">Male</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="keychain"
                            data-name="Chibi Girl Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Keychain Girl/girl-keychain-light.glb"
                            data-image="dressup-assets/images/Chibi Keychain Girl/Chibi Girl Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="dressup-assets/images/Chibi Keychain Girl/Chibi Girl Keychain.png" alt="Chibi Girl Keychain">
                            </div>
                            <span class="figure-model-name">Female</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section skin-section"
                    id="chibiSkinSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Skin Tone</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="skin-grid">

                        <button
                            type="button"
                            class="skin-card"
                            data-figure="chibi"
                            data-slot="skin"
                            data-name="Light"
                            data-color="#F2CCB7"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-light"></span>
                        </button>


                        <button
                            type="button"
                            class="skin-card"
                            data-figure="chibi"
                            data-slot="skin"
                            data-name="Dark"
                            data-color="#D19477"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-dark"></span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Bald (No Hair)"
                            data-model="placeholder:chibi-boy-hair-bald"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiBoy-hair.png" alt="Bald (No Hair)">
                            </div>
                            <span>Bald (No Hair)</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair.png"
                                    alt="Hair 1"
                                >
                            </div>
                            <span>Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair1.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair1.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair1.png"
                                    alt="Hair 2"
                                >
                            </div>
                            <span>Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 3"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair2.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair2.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair2.png"
                                    alt="Hair 3"
                                >
                            </div>
                            <span>Hair 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 4"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair3.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair3.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair3.png"
                                    alt="Hair 4"
                                >
                            </div>
                            <span>Hair 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 5"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair4.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair4.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair4.png"
                                    alt="Hair 5"
                                >
                            </div>
                            <span>Hair 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 6"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair5.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair5.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair5.png"
                                    alt="Hair 6"
                                >
                            </div>
                            <span>Hair 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 7"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair6.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair6.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair6.png"
                                    alt="Hair 7"
                                >
                            </div>
                            <span>Hair 7</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 8"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair7.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair7.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair7.png"
                                    alt="Hair 8"
                                >
                            </div>
                            <span>Hair 8</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 9"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair8.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair8.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair8.png"
                                    alt="Hair 9"
                                >
                            </div>
                            <span>Hair 9</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 10"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair9.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair9.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair9.png"
                                    alt="Hair 10"
                                >
                            </div>
                            <span>Hair 10</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 11"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/hair/hair10.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/hair/hair10.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/hair/hair10.png"
                                    alt="Hair 11"
                                >
                            </div>
                            <span>Hair 11</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">
                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Default Hair"
                            data-model="placeholder:chibi-girlHair-default-hair"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiGirl-hair.png" alt="Default Hair">
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair.png"
                                    alt="Girl Hair 1"
                                >
                            </div>
                            <span>Girl Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair1.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair1.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair1.png"
                                    alt="Girl Hair 2"
                                >
                            </div>
                            <span>Girl Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 3"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair2.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair2.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair2.png"
                                    alt="Girl Hair 3"
                                >
                            </div>
                            <span>Girl Hair 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 4"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair3.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair3.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair3.png"
                                    alt="Girl Hair 4"
                                >
                            </div>
                            <span>Girl Hair 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 5"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair4.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair4.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair4.png"
                                    alt="Girl Hair 5"
                                >
                            </div>
                            <span>Girl Hair 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 6"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair5.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair5.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair5.png"
                                    alt="Girl Hair 6"
                                >
                            </div>
                            <span>Girl Hair 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 7"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair6.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair6.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair6.png"
                                    alt="Girl Hair 7"
                                >
                            </div>
                            <span>Girl Hair 7</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 8"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair7.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair7.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair7.png"
                                    alt="Girl Hair 8"
                                >
                            </div>
                            <span>Girl Hair 8</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 9"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair8.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair8.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair8.png"
                                    alt="Girl Hair 9"
                                >
                            </div>
                            <span>Girl Hair 9</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 10"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/hair/hair9.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/hair/hair9.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/hair/hair9.png"
                                    alt="Girl Hair 10"
                                >
                            </div>
                            <span>Girl Hair 10</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker"
                    id="chibiHairColorSection"
                    data-figure="chibi"
                    data-slot="hairColor"
                    hidden
                >


                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker"
                    id="chibiGirlHairColorSection"
                    data-figure="chibi"
                    data-slot="girlHairColor"
                    hidden
                >
                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Sando"
                            data-model="placeholder:chibi-top-sando"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiBoy-top.png" alt="Sando">
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Coat"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/chibi coat.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/chibi coat.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/chibi coat.png"
                                    alt="Coat"
                                >
                            </div>
                            <span>Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Coat 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/chibi coat1.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/chibi coat1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/chibi coat1.png"
                                    alt="Coat 2"
                                >
                            </div>
                            <span>Coat 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Plain Polo"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/plain polo.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/plain polo.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/plain polo.png"
                                    alt="Plain Polo"
                                >
                            </div>
                            <span>Plain Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="RL Polo"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/rl polo.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/rl polo.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/rl polo.png"
                                    alt="RL Polo"
                                >
                            </div>
                            <span>RL Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Pocket T-Shirt"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/chibi pocket tee.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/chibi pocket tee.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/chibi pocket tee.png"
                                    alt="Pocket T-Shirt"
                                >
                            </div>
                            <span>Pocket T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="T-Shirt"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/t-shirt.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/t-shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/t-shirt.png"
                                    alt="T-Shirt"
                                >
                            </div>
                            <span>T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="NY T-Shirt"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/ny t-shirt.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/ny t-shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/ny t-shirt.png"
                                    alt="NY T-Shirt"
                                >
                            </div>
                            <span>NY T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Hoodie"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/chibi hoodie.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/chibi hoodie.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/chibi hoodie.png"
                                    alt="Hoodie"
                                >
                            </div>
                            <span>Hoodie</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="CCC Uniform"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/tops/ccc uniform.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/tops/ccc uniform.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/tops/ccc uniform.png"
                                    alt="CCC Uniform"
                                >
                            </div>
                            <span>CCC Uniform</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">
                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Tank Top"
                            data-model="placeholder:chibi-girlTop-tank-top"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiGirl-top.png" alt="Tank Top">
                            </div>
                            <span>Tank Top</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Hoodie"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/hoodie.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/hoodie.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/hoodie.png"
                                    alt="Hoodie"
                                >
                            </div>
                            <span>Hoodie</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Denim Jacket"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/denim jacket.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/denim jacket.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/denim jacket.png"
                                    alt="Denim Jacket"
                                >
                            </div>
                            <span>Denim Jacket</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Sweater"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/sweater1.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/sweater1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/sweater1.png"
                                    alt="Sweater"
                                >
                            </div>
                            <span>Sweater</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Coat"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/coat.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/coat.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/coat.png"
                                    alt="Coat"
                                >
                            </div>
                            <span>Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Dress"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/dress.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/dress.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/dress.png"
                                    alt="Dress"
                                >
                            </div>
                            <span>Dress</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Leather Jacket"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/leather jacket.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/leather jacket.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/leather jacket.png"
                                    alt="Leather Jacket"
                                >
                            </div>
                            <span>Leather Jacket</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Fur Jacket"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/fur jacket.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/fur jacket.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/fur jacket.png"
                                    alt="Fur Jacket"
                                >
                            </div>
                            <span>Fur Jacket</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="T-Shirt"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/t-shirt.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/t-shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/t-shirt.png"
                                    alt="T-Shirt"
                                >
                            </div>
                            <span>T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="CCC Uniform"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/tops/ccc uniform.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/tops/ccc uniform.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/tops/ccc uniform.png"
                                    alt="CCC Uniform"
                                >
                            </div>
                            <span>CCC Uniform</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-bottom-shorts"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiBoy-bottom.png" alt="Shorts">
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Pants 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/pants/chibi pants.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/pants/chibi pants.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/pants/chibi pants.png"
                                    alt="Pants 1"
                                >
                            </div>
                            <span>Pants 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Pants 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/pants/chibi pants1.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/pants/chibi pants1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/pants/chibi pants1.png"
                                    alt="Pants 2"
                                >
                            </div>
                            <span>Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Pants 3"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/pants/chibi pants2.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/pants/chibi pants2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/pants/chibi pants2.png"
                                    alt="Pants 3"
                                >
                            </div>
                            <span>Pants 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Pants 4"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/pants/chibi pants3.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/pants/chibi pants3.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/pants/chibi pants3.png"
                                    alt="Pants 4"
                                >
                            </div>
                            <span>Pants 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Pants 5"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/pants/chibi pants4.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/pants/chibi pants4.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/pants/chibi pants4.png"
                                    alt="Pants 5"
                                >
                            </div>
                            <span>Pants 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Pants 6"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/pants/chibi pants5.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/pants/chibi pants5.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/pants/chibi pants5.png"
                                    alt="Pants 6"
                                >
                            </div>
                            <span>Pants 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="CCC Slacks"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/pants/ccc slacks.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/pants/ccc slacks.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/pants/ccc slacks.png"
                                    alt="CCC Slacks"
                                >
                            </div>
                            <span>CCC Slacks</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">
                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-girlBottom-shorts"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiGirl-bottom.png" alt="Shorts">
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Pants 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/pants1.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/pants1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/pants1.png"
                                    alt="Pants 1"
                                >
                            </div>
                            <span>Pants 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Pants 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/pants2.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/pants2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/pants2.png"
                                    alt="Pants 2"
                                >
                            </div>
                            <span>Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Pants 3"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/pants3.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/pants3.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/pants3.png"
                                    alt="Pants 3"
                                >
                            </div>
                            <span>Pants 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Pants 4"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/pants4.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/pants4.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/pants4.png"
                                    alt="Pants 4"
                                >
                            </div>
                            <span>Pants 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Pants 5"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/pants5.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/pants5.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/pants5.png"
                                    alt="Pants 5"
                                >
                            </div>
                            <span>Pants 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Pants 6"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/pants6.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/pants6.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/pants6.png"
                                    alt="Pants 6"
                                >
                            </div>
                            <span>Pants 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Pants 7"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/pants7.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/pants7.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/pants7.png"
                                    alt="Pants 7"
                                >
                            </div>
                            <span>Pants 7</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Shorts 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/shorts1.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/shorts1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/shorts1.png"
                                    alt="Shorts 1"
                                >
                            </div>
                            <span>Shorts 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Skirt 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/skirt.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/skirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/skirt.png"
                                    alt="Skirt 1"
                                >
                            </div>
                            <span>Skirt 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Skirt 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/skirt1.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/skirt1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/skirt1.png"
                                    alt="Skirt 2"
                                >
                            </div>
                            <span>Skirt 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Skirt 3"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/sksirt2.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/sksirt2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/sksirt2.png"
                                    alt="Skirt 3"
                                >
                            </div>
                            <span>Skirt 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="CCC Skirt"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/ccc skirt.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/ccc skirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/ccc skirt.png"
                                    alt="CCC Skirt"
                                >
                            </div>
                            <span>CCC Skirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="CCC Slacks"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/pants/ccc slacks.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/pants/ccc slacks.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/pants/ccc slacks.png"
                                    alt="CCC Slacks"
                                >
                            </div>
                            <span>CCC Slacks</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiShoesSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI SHOES</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">
                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes"
                            data-model="placeholder:chibi-shoes-shoes"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiBoy-shoes.png" alt="Shoes">
                            </div>
                            <span>Shoes</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes.png"
                                    alt="Shoes 1"
                                >
                            </div>
                            <span>Shoes 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes1.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes1.png"
                                    alt="Shoes 2"
                                >
                            </div>
                            <span>Shoes 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 3"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes2.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes2.png"
                                    alt="Shoes 3"
                                >
                            </div>
                            <span>Shoes 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 4"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes3.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes3.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes3.png"
                                    alt="Shoes 4"
                                >
                            </div>
                            <span>Shoes 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 5"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes4.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes4.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes4.png"
                                    alt="Shoes 5"
                                >
                            </div>
                            <span>Shoes 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 6"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes5.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes5.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes5.png"
                                    alt="Shoes 6"
                                >
                            </div>
                            <span>Shoes 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 7"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes6.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes6.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes6.png"
                                    alt="Shoes 7"
                                >
                            </div>
                            <span>Shoes 7</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 8"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes7.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes7.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes7.png"
                                    alt="Shoes 8"
                                >
                            </div>
                            <span>Shoes 8</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 9"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/shoes8.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/shoes8.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/shoes8.png"
                                    alt="Shoes 9"
                                >
                            </div>
                            <span>Shoes 9</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Crocs"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/crocs.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/crocs.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/crocs.png"
                                    alt="Crocs"
                                >
                            </div>
                            <span>Crocs</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Black Shoes"
                            data-model="dressup-assets/GLB_files/Chibi Standee Girl/shoes/black shoes.glb"
                            data-image="dressup-assets/images/Chibi Standee Girl/shoes/black shoes.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Girl/shoes/black shoes.png"
                                    alt="Black Shoes"
                                >
                            </div>
                            <span>Black Shoes</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <!-- Chibi Boy (standee) shoes - kasama sa bagong Chibi Boy set,
                     hiwalay sa shared chibiShoesSection (Chibi Girl / keychain) -->

                <div
                    class="option-section"
                    id="chibiBoyShoesSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI SHOES</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Barefoot"
                            data-model="placeholder:chibi-boy-shoes-barefoot"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiBoy-shoes.png" alt="Barefoot">
                            </div>
                            <span>Barefoot</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 1"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/shoes.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/shoes.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/shoes.png"
                                    alt="Shoes 1"
                                >
                            </div>
                            <span>Shoes 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 2"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/shoes1.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/shoes1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/shoes1.png"
                                    alt="Shoes 2"
                                >
                            </div>
                            <span>Shoes 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 3"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/shoes2.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/shoes2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/shoes2.png"
                                    alt="Shoes 3"
                                >
                            </div>
                            <span>Shoes 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 4"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/shoes3.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/shoes3.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/shoes3.png"
                                    alt="Shoes 4"
                                >
                            </div>
                            <span>Shoes 4</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 5"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/shoes4.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/shoes4.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/shoes4.png"
                                    alt="Shoes 5"
                                >
                            </div>
                            <span>Shoes 5</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 6"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/shoes5.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/shoes5.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/shoes5.png"
                                    alt="Shoes 6"
                                >
                            </div>
                            <span>Shoes 6</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes 7"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/shoes9.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/shoes9.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/shoes9.png"
                                    alt="Shoes 7"
                                >
                            </div>
                            <span>Shoes 7</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="New Balance"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/new balance.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/new balance.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/new balance.png"
                                    alt="New Balance"
                                >
                            </div>
                            <span>New Balance</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Vans Checkered"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/vans checkered.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/vans checkered.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/vans checkered.png"
                                    alt="Vans Checkered"
                                >
                            </div>
                            <span>Vans Checkered</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Crocs"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/crocs.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/crocs.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/crocs.png"
                                    alt="Crocs"
                                >
                            </div>
                            <span>Crocs</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Panda Dunks"
                            data-model="dressup-assets/GLB_files/Chibi Standee Boy/shoes/panda dunks.glb"
                            data-image="dressup-assets/images/Chibi Standee Boy/shoes/panda dunks.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Standee Boy/shoes/panda dunks.png"
                                    alt="Panda Dunks"
                                >
                            </div>
                            <span>Panda Dunks</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                    </div>

                </div>


                <!-- Chibi keychain tops / bottoms (galing sa DressUp - sariling models ng keychain) -->

                <div
                    class="option-section"
                    id="chibiBoyKeychainTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI BOY KEYCHAIN TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Sando"
                            data-model="placeholder:chibi-top-sando"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiBoy-top.png" alt="Sando">
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Chibi T-Shirt Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Keychain Boy/Chibi T-Shirt Keychain.glb"
                            data-image="dressup-assets/images/Chibi Keychain Boy/Chibi T-Shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Keychain Boy/Chibi T-Shirt.png"
                                    alt="Chibi T-Shirt"
                                >
                            </div>
                            <span>Chibi T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Chibi Polo Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Keychain Boy/Chibi Polo Keychain.glb"
                            data-image="dressup-assets/images/Chibi Keychain Boy/Chibi Polo.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Keychain Boy/Chibi Polo.png"
                                    alt="Chibi Polo"
                                >
                            </div>
                            <span>Chibi Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiBoyKeychainBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI BOY KEYCHAIN BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-bottom-shorts"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiBoy-bottom.png" alt="Shorts">
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Shorts Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Keychain Boy/Chibi Shorts Keychain.glb"
                            data-image="dressup-assets/images/Chibi Keychain Boy/Chibi Shorts.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Keychain Boy/Chibi Shorts.png"
                                    alt="Chibi Shorts"
                                >
                            </div>
                            <span>Chibi Shorts</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Pants Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Keychain Boy/Chibi Pants Keychain.glb"
                            data-image="dressup-assets/images/Chibi Keychain Boy/Chibi Pants.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Keychain Boy/Chibi Pants.png"
                                    alt="Chibi Pants"
                                >
                            </div>
                            <span>Chibi Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Pants Keychain1"
                            data-model="dressup-assets/GLB_files/Chibi Keychain Boy/Chibi Pants Keychain1.glb"
                            data-image="dressup-assets/images/Chibi Keychain Boy/Chibi Girl Pants2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Keychain Boy/Chibi Girl Pants2.png"
                                    alt="Chibi Pants 2"
                                >
                            </div>
                            <span>Chibi Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlKeychainTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL KEYCHAIN TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">
                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Tank Top"
                            data-model="placeholder:chibi-girlTop-tank-top"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiGirl-top.png" alt="Tank Top">
                            </div>
                            <span>Tank Top</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlKeychainBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL KEYCHAIN BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">
                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-girlBottom-shorts"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/chibiGirl-bottom.png" alt="Shorts">
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>

                <!-- custom color ng Chibi top / bottom / shoes - naka-mount sa title ng design section -->
                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiTopColorSection"
                    data-figure="chibi"
                    data-slot="topColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiGirlTopColorSection"
                    data-figure="chibi"
                    data-slot="girlTopColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiBottomColorSection"
                    data-figure="chibi"
                    data-slot="bottomColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiGirlBottomColorSection"
                    data-figure="chibi"
                    data-slot="girlBottomColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiShoesColorSection"
                    data-figure="chibi"
                    data-slot="shoesColor"
                    hidden
                ></div>

            </div>


            <!-- hirono panel -->

            <div
                class="category-panel"
                id="hironoPanel"
                hidden
            >

                <div
                    class="option-section"
                    id="hironoTypeSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>PRODUCT TYPE</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="hironoType"
                            data-mode="standee"
                            data-name="Full Body Standee"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono default.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Standee.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Standee.png"
                                    alt="Full Body Standee"
                                >
                            </div>
                            <span class="figure-model-name">Full Body Standee</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="hironoType"
                            data-mode="keychain"
                            data-name="Full Body Keychain"
                            data-model="dressup-assets/GLB_files/Hirono Keychain Boy/Hirono Keychain.glb"
                            data-image="dressup-assets/images/Hirono Keychain Boy/Hirono Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Keychain Boy/Hirono Keychain.png"
                                    alt="Full Body Keychain"
                                >
                            </div>
                            <span class="figure-model-name">Full Body Keychain</span>
                        </button>


                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoModelSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>GENDER</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="standee"
                            data-gender="boy"
                            data-name="Hirono Boy"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono default.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Standee.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Standee.png"
                                    alt="Hirono Boy"
                                >
                            </div>
                            <span class="figure-model-name">Male</span>
                        </button>

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="keychain"
                            data-gender="boy"
                            data-name="Hirono Boy"
                            data-model="dressup-assets/GLB_files/Hirono Keychain Boy/Hirono Keychain.glb"
                            data-image="dressup-assets/images/Hirono Keychain Boy/Hirono Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Keychain Boy/Hirono Keychain.png"
                                    alt="Hirono Boy"
                                >
                            </div>
                            <span class="figure-model-name">Male</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="standee"
                            data-gender="girl"
                            data-name="Hirono Girl"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl default.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Standee.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Standee.png"
                                    alt="Hirono Girl"
                                >
                            </div>
                            <span class="figure-model-name">Female</span>
                        </button>

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="keychain"
                            data-gender="girl"
                            data-name="Hirono Girl"
                            data-model="dressup-assets/GLB_files/Hirono Keychain Girl/Hirono Girl Keychain.glb"
                            data-image="dressup-assets/images/Hirono Keychain Girl/Hirono Girl Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Keychain Girl/Hirono Girl Keychain.png"
                                    alt="Hirono Girl"
                                >
                            </div>
                            <span class="figure-model-name">Female</span>
                        </button>


                    </div>

                </div>


                <div
                    class="option-section skin-section"
                    id="hironoSkinSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Skin Tone</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="skin-grid">

                        <button
                            type="button"
                            class="skin-card"
                            data-figure="hirono"
                            data-slot="skin"
                            data-name="Light"
                            data-color="#F2CCB7"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-light"></span>
                        </button>


                        <button
                            type="button"
                            class="skin-card"
                            data-figure="hirono"
                            data-slot="skin"
                            data-name="Dark"
                            data-color="#D19477"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-dark"></span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="hair"
                            data-name="Default Hair"
                            data-model="placeholder:hirono-hair-default-hair"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/hironoBoy-hair.png" alt="Default Hair">
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="hair"
                            data-name="Hirono Hair"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Hair01.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono hair.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono hair.png"
                                    alt="Hirono Hair"
                                >
                            </div>
                            <span>Hirono Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair.png"
                                    alt="Hirono Girl Hair"
                                >
                            </div>
                            <span>Hirono Girl Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair1"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair1.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair1.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair1.png"
                                    alt="Hirono Girl Hair1"
                                >
                            </div>
                            <span>Hirono Girl Hair1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair2"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair2.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair2.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair2.png"
                                    alt="Hirono Girl Hair2"
                                >
                            </div>
                            <span>Hirono Girl Hair2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair3"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair3.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair3.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair3.png"
                                    alt="Hirono Girl Hair3"
                                >
                            </div>
                            <span>Hirono Girl Hair3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker hirono-hair-color-picker"
                    id="hironoHairColorSection"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoOutfitSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO TOPS</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-name="Sando"
                            data-model="placeholder:hirono-outfit-sando"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/hironoBoy-top.png" alt="Sando">
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="outfit"
                            data-name="Hirono Polo"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Polo.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Polo.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Polo.png"
                                    alt="Hirono Polo"
                                >
                            </div>
                            <span>Hirono Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="outfit"
                            data-name="Hirono Jersey"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Jersey.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Jersey.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Jersey.png"
                                    alt="Hirono Jersey"
                                >
                            </div>
                            <span>Hirono Jersey</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="boy"
                            data-name="Hirono Coat"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Coat.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Coat.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Coat.png"
                                    alt="Hirono Coat"
                                >
                            </div>
                            <span>Hirono Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="boy"
                            data-name="Hirono Polo1"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Polo1.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Polo1.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Polo1.png"
                                    alt="Hirono Polo1"
                                >
                            </div>
                            <span>Hirono Polo1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Coat"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Coat.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Coat.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Coat.png"
                                    alt="Hirono Girl Coat"
                                >
                            </div>
                            <span>Hirono Girl Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Dress"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Dress.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Dress.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Dress.png"
                                    alt="Hirono Girl Dress"
                                >
                            </div>
                            <span>Hirono Girl Dress</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-name="Jacket"
                            data-model="placeholder:hirono-outfit-jacket"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🧥</span>
                            </div>
                            <span>Jacket</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-name="Long Sleeve"
                            data-model="placeholder:hirono-outfit-long-sleeve"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👔</span>
                            </div>
                            <span>Long Sleeve</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker custom-color-only-section"
                    id="hironoOutfitColorSection"
                    data-figure="hirono"
                    data-slot="outfitColor"
                    hidden
                >

                </div>


                <div
                    class="option-section"
                    id="hironoPantsSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO BOTTOM</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="pants"
                            data-name="Shorts"
                            data-model="placeholder:hirono-pants-shorts"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/hironoBoy-bottom.png" alt="Shorts">
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="pants"
                            data-name="Hirono Pants"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Pants1.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Pants1.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Pants1.png"
                                    alt="Hirono Pants"
                                >
                            </div>
                            <span>Hirono Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="pants"
                            data-hirono-gender="boy"
                            data-name="Hirono Pants 2"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Pants.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono Pants.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono Pants.png"
                                    alt="Hirono Pants 2"
                                >
                            </div>
                            <span>Hirono Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="pants"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Pants"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Pants.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Pants.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Pants.png"
                                    alt="Hirono Girl Pants"
                                >
                            </div>
                            <span>Hirono Girl Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker hirono-pants-color-picker"
                    id="hironoPantsColorSection"
                    hidden
                >


                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoShoesSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO SHOES</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="shoes"
                            data-name="Shoes"
                            data-model="placeholder:hirono-shoes-shoes"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/hironoBoy-shoes.png" alt="Shoes">
                            </div>
                            <span>Shoes</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="shoes"
                            data-name="Hirono Shoes"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Shoes.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/shoes.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/shoes.png"
                                    alt="Hirono Shoes"
                                >
                            </div>
                            <span>Hirono Shoes</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="shoes"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Shoes"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Shoes.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/shoes.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/shoes.png"
                                    alt="Hirono Girl Shoes"
                                >
                            </div>
                            <span>Hirono Girl Shoes</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker shoes-color-picker"
                    id="hironoShoesColorSection"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="Red"
                            data-color="#D94B4B"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-red"></span>
                            <span class="top-color-name">Red</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoKeychainHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO KEYCHAIN HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-name="Default Hair"
                            data-model="placeholder:hirono-keychainHair-default-hair"
                            data-price="0"
                            data-billable="false"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <img src="dressup-assets/images/Defaults/hironoBoy-hair.png" alt="Default Hair">
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="keychainHair"
                            data-name="Hirono Hair"
                            data-model="dressup-assets/GLB_files/Hirono Standee Boy/Hirono Hair01.glb"
                            data-image="dressup-assets/images/Hirono Standee Boy/Hirono hair.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Boy/Hirono hair.png"
                                    alt="Hirono Hair"
                                >
                            </div>
                            <span>Hirono Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair.png"
                                    alt="Hirono Girl Hair"
                                >
                            </div>
                            <span>Hirono Girl Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair1"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair1.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair1.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair1.png"
                                    alt="Hirono Girl Hair1"
                                >
                            </div>
                            <span>Hirono Girl Hair1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair2"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair2.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair2.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair2.png"
                                    alt="Hirono Girl Hair2"
                                >
                            </div>
                            <span>Hirono Girl Hair2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair3"
                            data-model="dressup-assets/GLB_files/Hirono Standee Girl/Hirono Girl Hair3.glb"
                            data-image="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair3.png"
                            data-price="0"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee Girl/Hirono Girl Hair3.png"
                                    alt="Hirono Girl Hair3"
                                >
                            </div>
                            <span>Hirono Girl Hair3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker hirono-hair-color-picker"
                    id="hironoKeychainHairColorSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Keychain Hair Color</h2>
                        <span>Choose a color</span>
                    </div>


                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoKeychainHatSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO HAT</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHat"
                            data-name="KeyChain Cap"
                            data-model="dressup-assets/GLB_files/Hirono Head Keychain/Hirono KeyChain Cap.glb"
                            data-image="dressup-assets/images/Hirono Head Keychain/Hirono KeyChain Cap.png"
                            data-price="40"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Head Keychain/Hirono KeyChain Cap.png"
                                    alt="KeyChain Cap"
                                >
                            </div>
                            <span>KeyChain Cap</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHat"
                            data-name="Spider-Man Hat"
                            data-model="dressup-assets/GLB_files/Hirono Head Keychain/Hirono SpidermanHat.glb"
                            data-image="dressup-assets/images/Hirono Head Keychain/hirono spiderman.png"
                            data-price="60"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Head Keychain/hirono spiderman.png"
                                    alt="Spider-Man Hat"
                                >
                            </div>
                            <span>Spider-Man Hat</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHat"
                            data-name="Spider-Venom Hat"
                            data-model="dressup-assets/GLB_files/Hirono Head Keychain/Hirono VenomxSpidermanHat.glb"
                            data-image="dressup-assets/images/Hirono Head Keychain/hirono spiderxvenom.png"
                            data-price="60"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Head Keychain/hirono spiderxvenom.png"
                                    alt="Spider-Venom Hat"
                                >
                            </div>
                            <span>Spider-Venom Hat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>

            </div>


            <!-- SIZE - shared step after Gender/Skin, para sa kahit anong Figure Style -->

            <div
                class="option-section size-section"
                id="figureSizeSection"
                hidden
            >

                <div class="section-title">
                    <h2>Size</h2>
                    <span>Choose one</span>
                </div>

                <div class="size-choice-grid" id="sizeChoiceGrid"></div>

            </div>

            </div>


            <section
                class="product-details-panel"
                id="productDetailsPanel"
                hidden
            >

                <!-- one container lang, becomes "Box / Add-ons" kung Funko/Hirono, "Figure Details" pa rin kung Chibi -->

                <div class="section-title">
                    <div>
                        <span class="details-kicker" id="productDetailsKicker">FIGURE DETAILS</span>
                        <h2 id="productDetailsTitle">Choose Your Figure Details</h2>
                        <p id="productDetailsDescription">Choose the box for your selected product.</p>
                    </div>
                </div>

                <!-- scroll box: dito lang nag-i-scroll, hindi kasama ang title -->
                <div class="product-details-scroll" id="productDetailsScroll">

                <label class="product-detail-group" id="figureNameField" hidden>
                    <span class="detail-label">Figure Name <small>(optional)</small></span>
                    <input class="product-detail-input" id="figureNameInput" type="text" maxlength="40" placeholder="Example: Omar123">
                    <small class="detail-hint">₱50 if you add a name</small>
                </label>

                <div class="product-detail-group" id="boxChoiceGroup">
                    <span class="detail-label">Box</span>
                    <p class="detail-hint" id="boxChoiceHint">
                        Funko Pop figures come with a custom box. Choose Solo or Couple Box, and fill in the box details below.
                    </p>
                    <div class="box-choice-grid" id="boxChoiceGrid"></div>
                    <p class="detail-hint" id="boxLockedHint" hidden>
                        Please select a box type above to unlock the box details form.
                    </p>
                </div>

                <div class="box-detail-fields" id="boxDetailFields" hidden>
                    <label>
                        <span class="detail-label">Name to print on box <small>(optional)</small></span>
                        <input class="product-detail-input" id="boxNameInput" data-detail-field="boxName" type="text" maxlength="40" placeholder="Example: Omar">
                    </label>
                    <label>
                        <span class="detail-label">Box Number <small>(optional)</small></span>
                        <input class="product-detail-input" id="boxNumberInput" data-detail-field="boxNumber" type="text" maxlength="20" placeholder="Example: 001">
                    </label>
                    <label>
                        <span class="detail-label">Box Color <small>(optional)</small></span>
                        <input class="product-detail-input" id="boxColorInput" data-detail-field="boxColor" type="text" maxlength="30" placeholder="Example: Pastel Pink">
                    </label>
                </div>

                <div class="hirono-detail-fields" id="hironoDetailFields" hidden>
                    <div class="product-detail-group">
                        <span class="detail-label">Blind Box Type</span>
                        <div class="box-choice-grid" id="hironoBlindBoxGrid"></div>
                    </div>
                    <div class="product-detail-group">
                        <span class="detail-label">Optional Extras</span>
                        <div class="box-choice-grid" id="hironoAddonGrid"></div>
                    </div>
                    <div class="product-detail-group">
                        <span class="detail-label">Box Design</span>
                        <div class="box-choice-grid" id="hironoBoxDesignGrid"></div>
                    </div>
                    <div class="box-detail-fields" id="hironoBoxFields"></div>
                </div>

                </div>
                <!-- /.product-details-scroll -->

            </section>


            </div>
            <!-- /.dressup-right-scroll -->

            <!-- no summary panel here, redirects to finalsummary.php na (see dressup.js) -->


            <div
                class="design-step-navigation"
                id="designStepNavigation"
                hidden
            >
                <div class="design-step-total">
                    <span>TOTAL</span>
                    <strong id="dressUpTotalPrice">&#8369;0</strong>
                </div>
                <div class="design-step-jump">
                    <span>DESIGN SECTION</span>
                    <select id="designStepPicker" aria-label="Jump to design section" tabindex="-1" aria-hidden="true"></select>
                </div>
                <button
                    type="button"
                    class="step-navigation-button"
                    id="previousDesignStep"
                >
                    Back
                </button>
                <button
                    type="button"
                    class="continue-btn step-navigation-button"
                    id="nextDesignStep"
                >
                    Next
                </button>
                <a
                    href="finalsummary.php"
                    class="continue-btn"
                    id="continueBtn"
                    hidden
                >
                    Next
                </a>
            </div>

            </div>

        </div>

    </div>

</div>

</main>


<!-- importmap: kailangan dahil direktang nag-i-import ng three.js ang dress-up.js -->

<script type="importmap">
{
    "imports": {
        "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
        "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
    }
}
</script>

<!-- lahat ng Dress Up logic (3D, presyo, order submit) -->
<script type="module" src="dress-up.js?v=97"></script>


</body>
</html>
