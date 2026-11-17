<?php

/* =====================================================================
   dressup_helper.php  -  PINAGSAMANG HELPER PARA SA DRESS UP 3D FIGURE
   =====================================================================
   Dating tatlong file, isa na lang ngayon:
     - glb_paths.php    (nasa SECTION 2)
     - image_paths.php  (nasa SECTION 1)
     - dressup_helper.php (nasa SECTION 3)
   Puwede nang i-delete ang glb_paths.php at image_paths.php.

   PAANO SILA GUMAGANA
   ---------------------------------------------------------------------
   SECTION 1 - LUMANG LARAWAN => BAGONG LARAWAN
     figurify_image_path("chibi.jpg") => "figures/chibi/chibi-style.jpg"
     Para hindi masira ang mga lumang pangalan ng larawan na naka-save pa sa database.
     Tinatawag ng content_helper.php.

   SECTION 2 - LUMANG GLB PATH => TAMANG FOLDER
     figurify_relocate_glb_path("...GLB_files/Chibi Boy/x.glb")
        => "...GLB_files/Chibi Standee Boy/x.glb"
     Para hindi masira ang mga lumang Dress Up draft.
     Tinatawag ng Commission/submit_order.php bago i-save ang order.

   SECTION 3 - DRESS UP DESIGN + 3D VIEWER
     figurify_dressup_for_figure($fig)        = may Dress Up ba ang figure? (null = wala)
     figurify_render_dressup_design($fig, v)  = listahan ng napili (gender, hair, top...)
     figurify_render_dressup_viewer($fig)     = 3D viewer (Shared/dressup-viewer.js)
     Tinatawag ng My Orders at ng mga order page ng staff/owner.
     May sariling DB connection dahil may mga page na nagsasara na ng $conn bago mag-render.

   SECTION 4 - LABEL AT LARAWAN NG FIGURE (order pages)
     orderMethodLabel(), figureHasSize(), hironoBaseBoxPrice(), hironoBoxDesignLabel(),
     hironoBoxDesignImage(), hironoBlindItemImage(), hironoBlindTypeImage(),
     figureStyleImage(), productTypeImage()
     Ginagamit ng My Orders at ng mga order page ng staff/owner.

   PAANO GAMITIN
     require_once __DIR__ . "/../helpers/dressup_helper.php";   (isang require lang, safe kahit ulit-ulitin)

   PAALALA: puro function/constant lang ang nasa file na ito - walang inilalabas
   at walang kumokonekta sa database hangga't hindi tinatawag ang function,
   kaya ligtas i-require kahit saang page.
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : para sa Dress Up (pagbibihis ng 3D figure) at pag-ayos ng lumang path ng file.
   PARA SAAN   : ipinapakita sa My Orders at sa staff/owner kung ano ang pinili ng customer
                 (buhok, damit, sapatos) at ang 3D viewer nito.
   PANO NAGANA : 1) Ang napiling design ay naka-save bilang JSON sa order.
                 2) Binabasa ito ng figurify_dressup_for_figure().
                 3) Ginagawa ng figurify_render_dressup_design() ang listahan at ng
                    figurify_render_dressup_viewer() ang 3D na makikita.
                 Ang SECTION 1 at 2 ay taga-ayos lang ng lumang pangalan ng file para hindi masira ang lumang order.
   --------------------------------------------------------------------- */


/* =====================================================================
   SECTION 1 - IMAGE PATHS (dating image_paths.php)
   Inayos na ang Image folder (brand, payment, boxes, figures/chibi,
   figures/hirono, figures/funko-pop). Kung may naka-save pa sa database na
   lumang pangalan (hal. "Chibi Head.jpg"), dito kinukuha ang bago.
   ===================================================================== */

if (!function_exists("figurify_image_legacy_map")) {

    /* ANO ITO: listahan ng lumang pangalan ng larawan => bagong path sa loob ng Image/ */
    function figurify_image_legacy_map(): array
    {
        return [
        "blindboxset.jpg" => "boxes/blind-box-set.jpg",
        "funko pop image/funko-couple-box.jpg" => "boxes/funko-couple-box.jpg",
        "funko-couple-box.jpg" => "boxes/funko-couple-box.jpg",
        "funko pop image/funko-solo-box.jpg" => "boxes/funko-solo-box.jpg",
        "funko-solo-box.jpg" => "boxes/funko-solo-box.jpg",
        "checkered.jpg" => "boxes/hirono-box-checkered.jpg",
        "peek.jpg" => "boxes/hirono-box-peek.jpg",
        "photocard.jpg" => "boxes/photocard.jpg",
        "pouch.jpg" => "boxes/pouch.jpg",
        "tear.jpg" => "boxes/tear-blind-paper.jpg",
        "figurify-background.png" => "brand/background-pattern.png",
        "967b3754-be4c-4feb-9e02-0c81ce1d6466.jpg" => "brand/banner-figures.jpg",
        "Clay and Stuff- BG.jpg" => "brand/banner-happy-hands.jpg",
        "logoclay-sidebar.png" => "brand/logo-sidebar.png",
        "logoclay.png" => "brand/logo.png",
        "Profile Icon.png" => "brand/profile-icon.png",
        "ch-fbk.jpg" => "figures/chibi/chibi-full-body-keychain.jpg",
        "ch-fbs.jpg" => "figures/chibi/chibi-full-body-standee.jpg",
        "ch-hbk.jpg" => "figures/chibi/chibi-half-body-keychain.jpg",
        "Chibi Head.jpg" => "figures/chibi/chibi-head-keychain-pair.jpg",
        "Chibi Head- Spiderman.jpg" => "figures/chibi/chibi-head-keychain-spiderman.jpg",
        "chibi/chibi hear only.jpg" => "figures/chibi/chibi-head-only-keychain-set.jpg",
        "ch-ho.jpg" => "figures/chibi/chibi-head-only-keychain.jpg",
        "chibi/chibi keychain all.jpg" => "figures/chibi/chibi-keychain-set.jpg",
        "chibi/chibi keychain with tag many.jpg" => "figures/chibi/chibi-keychain-with-name-tag.jpg",
        "chibi/chibi standee 2 many.jpg" => "figures/chibi/chibi-standee-2in-set.jpg",
        "chibi/chibi standee 2 with box.jpg" => "figures/chibi/chibi-standee-2in-with-box.jpg",
        "chibi/chibi standee 5.jpg" => "figures/chibi/chibi-standee-5in.jpg",
        "chibi.jpg" => "figures/chibi/chibi-style.jpg",
        "funko pop image/483098545_661553982937126_5536479445907858919_n.jpg" => "figures/funko-pop/funko-pop-01.jpg",
        "funko pop image/484345765_661550749604116_1972038684008627811_n.jpg" => "figures/funko-pop/funko-pop-02.jpg",
        "funko pop image/518354280_755566130202577_1201449548295838440_n.jpg" => "figures/funko-pop/funko-pop-03.jpg",
        "funko pop image/521599946_755566120202578_1922478042498125163_n.jpg" => "figures/funko-pop/funko-pop-04.jpg",
        "funko pop image/528576188_769798828779307_3828401976156501767_n.jpg" => "figures/funko-pop/funko-pop-05.jpg",
        "funko pop image/541108487_789824886776701_2447255486903800360_n.jpg" => "figures/funko-pop/funko-pop-06.jpg",
        "funko pop image/541806757_789874973438359_5591743442036346559_n.jpg" => "figures/funko-pop/funko-pop-07.jpg",
        "funko pop image/542628601_789875326771657_6952732024735596112_n.jpg" => "figures/funko-pop/funko-pop-08.jpg",
        "funko pop image/569138187_832315795860943_7151710862753224541_n.jpg" => "figures/funko-pop/funko-pop-09.jpg",
        "funko pop image/571274642_832315842527605_7219486203757563394_n.jpg" => "figures/funko-pop/funko-pop-10.jpg",
        "funko pop image/668838672_962881096137745_7903923905028564459_n.jpg" => "figures/funko-pop/funko-pop-11.jpg",
        "funko pop image/668867986_962881086137746_3167677033471984745_n.jpg" => "figures/funko-pop/funko-pop-12.jpg",
        "funko couple.jpg" => "figures/funko-pop/funko-pop-12.jpg",
        "funko pop image/668895882_962880246137830_4472113105271450612_n.jpg" => "figures/funko-pop/funko-pop-13.jpg",
        "funko solo.jpg" => "figures/funko-pop/funko-pop-13.jpg",
        "funko pop image/669047834_962874522805069_2004985564578206095_n.jpg" => "figures/funko-pop/funko-pop-14.jpg",
        "funko pop image/669047834_962874522805069_2004985564578206095_n (1).jpg" => "figures/funko-pop/funko-pop-14.jpg",
        "funko pop image/669458815_962879506137904_1095953134623004891_n.jpg" => "figures/funko-pop/funko-pop-15.jpg",
        "funko pop image/669559884_962876512804870_3412557050871217194_n.jpg" => "figures/funko-pop/funko-pop-16.jpg",
        "funko pop image/669721728_962876689471519_4678475376546463393_n.jpg" => "figures/funko-pop/funko-pop-17.jpg",
        "funko pop image/670968266_963762189382969_9053453680769840741_n.jpg" => "figures/funko-pop/funko-pop-18.jpg",
        "funko pop image/758943867_1054332150325972_6648886957808836398_n.jpg" => "figures/funko-pop/funko-pop-19.jpg",
        "funko pop image/760232693_1054332123659308_2278034785721401844_n.jpg" => "figures/funko-pop/funko-pop-20.jpg",
        "funko pop image/762763703_1054332126992641_7932121532812030769_n.jpg" => "figures/funko-pop/funko-pop-21.jpg",
        "funko pop image/762916824_1054332146992639_2680887771270653216_n.jpg" => "figures/funko-pop/funko-pop-22.jpg",
        "funko-fbs.jpg" => "figures/funko-pop/funko-pop-full-body-standee.jpg",
        "Customized Pet.jpg" => "figures/funko-pop/funko-pop-pet.jpg",
        "funko.jpg" => "figures/funko-pop/funko-pop-style.jpg",
        "hirono/hirono with box & card & tear1 3.5.jpg" => "figures/hirono/hirono-blind-box-set-01.jpg",
        "3.5” Customize Hirono With Blind Box Set.jpg" => "figures/hirono/hirono-blind-box-set-02.jpg",
        "3_5_U201d_Customize_Hirono_With_Blind_Box_Set_20260920_153654_43e69d.jpg" => "figures/hirono/hirono-blind-box-set-02.jpg",
        "3_5_U201d_Customize_Hirono_With_Blind_Box_Set_20260920_180923_4bea86.jpg" => "figures/hirono/hirono-blind-box-set-02.jpg",
        "3_5_U201d_Customize_Hirono_With_Blind_Box_Set_20260920_180929_00a5e3.jpg" => "figures/hirono/hirono-blind-box-set-02.jpg",
        "hirono/hirono couple1 3.5.jpg" => "figures/hirono/hirono-couple-standee-1.jpg",
        "Hirono-Couple.jpg" => "figures/hirono/hirono-couple-standee-2.jpg",
        "hirono/hirono couple2 3.5.jpg" => "figures/hirono/hirono-couple-standee-2.jpg",
        "hirono-fbk.jpg" => "figures/hirono/hirono-full-body-keychain.jpg",
        "hirono/hirono keychain3  2.jpg" => "figures/hirono/hirono-full-body-keychain.jpg",
        "hirono-fbs.jpg" => "figures/hirono/hirono-full-body-standee.jpg",
        "hirono-hbk.jpg" => "figures/hirono/hirono-half-body-keychain.jpg",
        "hirono/hirono head only.jpg" => "figures/hirono/hirono-head-only-keychain-pair.jpg",
        "hirono-ho.jpg" => "figures/hirono/hirono-head-only-keychain.jpg",
        "3.5” Customize Hirono Inspired Standee.jpg" => "figures/hirono/hirono-inspired-standee.jpg",
        "3_5_U201d_Customize_Hirono_Inspired_Standee_20260920_153651_d74fe1.jpg" => "figures/hirono/hirono-inspired-standee.jpg",
        "hirono/hirono keychain 2.jpg" => "figures/hirono/hirono-keychain-2in-01.jpg",
        "hirono/hirono keychain2 2.jpg" => "figures/hirono/hirono-keychain-2in-02.jpg",
        "hirono/hirono keychain3 2.jpg" => "figures/hirono/hirono-keychain-2in-03.jpg",
        "hirono/hirono 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-01.jpg",
        "hirono/hirono2 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-02.jpg",
        "hirono/hirono3 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-03.jpg",
        "hirono/hirono4 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-04.jpg",
        "3.5” Customize Hirono Standee.jpg" => "figures/hirono/hirono-standee-3.5in-04.jpg",
        "3_5_U201d_Customize_Hirono_Standee_20260920_153657_5e74bd.jpg" => "figures/hirono/hirono-standee-3.5in-04.jpg",
        "3_5_U201d_Customize_Hirono_Standee_20260920_153657_5e74bd_20260920_180931_4aa240.jpg" => "figures/hirono/hirono-standee-3.5in-04.jpg",
        "3_5_U201d_Customize_Hirono_Standee_20260920_180916_1148ba.jpg" => "figures/hirono/hirono-standee-3.5in-04.jpg",
        "3_5_U201d_Customize_Hirono_Standee_20260920_180919_9792aa.jpg" => "figures/hirono/hirono-standee-3.5in-04.jpg",
        "hirono/hirono5 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-05.jpg",
        "hirono/hirono6 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-06.jpg",
        "hirono/hirono7 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-07.jpg",
        "hirono/hirono8 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-08.jpg",
        "hirono/hirono9 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-09.jpg",
        "hirono/hirono10 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-10.jpg",
        "hirono/hirono11 3.5.jpg" => "figures/hirono/hirono-standee-3.5in-11.jpg",
        "Hirono.jpg" => "figures/hirono/hirono-style.jpg",
        "hirono.jpg" => "figures/hirono/hirono-style.jpg",
        "hirono/hirono with box & card 3.5.jpg" => "figures/hirono/hirono-with-photocard-01.jpg",
        "3.5” Customize Solo Hirono With Photocard.jpg" => "figures/hirono/hirono-with-photocard-01.jpg",
        "3_5_U201d_Customize_Solo_Hirono_With_Photocard_20260920_180925_0f812a.jpg" => "figures/hirono/hirono-with-photocard-01.jpg",
        "hirono/hirono with box & card2 3.5.jpg" => "figures/hirono/hirono-with-photocard-02.jpg",
        "3.5” Customize Solo Hirono With Photocard(1).jpg" => "figures/hirono/hirono-with-photocard-02.jpg",
        "hirono/hirono with box & card3 3.5.jpg" => "figures/hirono/hirono-with-photocard-03.jpg",
        "3.5” Customize Solo Hirono With Photocard2.jpg" => "figures/hirono/hirono-with-photocard-03.jpg",
        "hirono/hirono with box & card4 3.5.jpg" => "figures/hirono/hirono-with-photocard-04.jpg",
        "3.5” Customize Solo Hirono With Photocard3.jpg" => "figures/hirono/hirono-with-photocard-04.jpg",
        "hirono/hirono with box & card5 3.5.jpg" => "figures/hirono/hirono-with-photocard-05.jpg",
        "hirono/hirono with box & card6 3.5.jpg" => "figures/hirono/hirono-with-photocard-06.jpg",
        "3.5” Customize Solo Hirono With Photocard4.jpg" => "figures/hirono/hirono-with-photocard-06.jpg",
        "carousel1.jpg" => "home/carousel-1.jpg",
        "carousel2.jpg" => "home/carousel-2.jpg",
        "carousel3.jpg" => "home/carousel-3.jpg",
        "qrbpi.jpg" => "payment/qr-bpi.jpg",
        "qrgcash.jpg" => "payment/qr-gcash.jpg",
        ];
    }

    /* GINAGAMIT NG: content_helper.php (figurify_resolve_image_src)
       Kapag nasa mapa ang pangalan, ibabalik ang bago; kapag wala, ibabalik ang parehong pangalan
       (kaya hindi masisira ang mga bagong larawan). */
    function figurify_image_path(string $value): string
    {
        $map = figurify_image_legacy_map();

        return $map[$value] ?? $value;
    }
}


/* =====================================================================
   SECTION 2 - GLB PATHS (dating glb_paths.php)
   GLB folders: dressup-assets/GLB_files/<Figure>/<Hairstyle|Tops|Bottoms|Shoes>/<file>.glb
   Funko Pop Standee Boy/Girl, Hirono Standee Boy/Girl, Chibi Standee Boy/Girl
   (kasama na sa standee folder ang keychain na figure), Hirono Head Keychain.
   ===================================================================== */

/* pangalan ng .glb => folder niya (kapareho ng GLB_FOLDER_BY_FILE sa Commission/dress-up.js at Shared/dressup-viewer.js) */
if (!defined("FIGURIFY_GLB_FOLDER_BY_FILE")) {
define("FIGURIFY_GLB_FOLDER_BY_FILE", [
    "Funko pop-default.glb" => "Funko Pop Standee Boy",
    "funko boy.glb" => "Funko Pop Standee Boy",
    "Hair_01.glb" => "Funko Pop Standee Boy",
    "Hair02.glb" => "Funko Pop Standee Boy",
    "Hoodie.glb" => "Funko Pop Standee Boy",
    "T-Shirt.glb" => "Funko Pop Standee Boy",
    "Pants with Shoes.glb" => "Funko Pop Standee Boy",
    "Funko pop-girl.glb" => "Funko Pop Standee Girl",
    "Funko GirlHair01.glb" => "Funko Pop Standee Girl",
    "Funko GirlHair02.glb" => "Funko Pop Standee Girl",
    "Funko GirlHair03.glb" => "Funko Pop Standee Girl",
    "Funko Girl T-Shirt.glb" => "Funko Pop Standee Girl",
    "Funko Girl T-Shirt1.glb" => "Funko Pop Standee Girl",
    "Funko Girl T-Shirt2.glb" => "Funko Pop Standee Girl",
    "Funko Girl Bottom.glb" => "Funko Pop Standee Girl",
    "Funko Girl Bottom1.glb" => "Funko Pop Standee Girl",
    "Hirono default.glb" => "Hirono Standee Boy",
    "Hirono Hair01.glb" => "Hirono Standee Boy",
    "Hirono Polo.glb" => "Hirono Standee Boy",
    "Hirono Polo1.glb" => "Hirono Standee Boy",
    "Hirono Jersey.glb" => "Hirono Standee Boy",
    "Hirono Coat.glb" => "Hirono Standee Boy",
    "Hirono Pants.glb" => "Hirono Standee Boy",
    "Hirono Pants1.glb" => "Hirono Standee Boy",
    "Hirono Shoes.glb" => "Hirono Standee Boy",
    "Hirono Keychain.glb" => "Hirono Keychain Boy",
    "Hirono Girl default.glb" => "Hirono Standee Girl",
    "Hirono Girl Hair.glb" => "Hirono Standee Girl",
    "Hirono Girl Hair1.glb" => "Hirono Standee Girl",
    "Hirono Girl Hair2.glb" => "Hirono Standee Girl",
    "Hirono Girl Hair3.glb" => "Hirono Standee Girl",
    "Hirono Girl Coat.glb" => "Hirono Standee Girl",
    "Hirono Girl Dress.glb" => "Hirono Standee Girl",
    "Hirono Girl Pants.glb" => "Hirono Standee Girl",
    "Hirono Girl Shoes.glb" => "Hirono Standee Girl",
    "Hirono Girl Keychain.glb" => "Hirono Keychain Girl",
    "Hirono KeyChain Cap.glb" => "Hirono Head Keychain",
    "Hirono SpidermanHat.glb" => "Hirono Head Keychain",
    "Hirono VenomxSpidermanHat.glb" => "Hirono Head Keychain",
    "Chibi Boy Keychain.glb" => "Chibi Keychain Boy",
    "Chibi T-Shirt Keychain.glb" => "Chibi Keychain Boy",
    "Chibi Polo Keychain.glb" => "Chibi Keychain Boy",
    "Chibi Shorts Keychain.glb" => "Chibi Keychain Boy",
    "Chibi Pants Keychain.glb" => "Chibi Keychain Boy",
    "Chibi Pants Keychain1.glb" => "Chibi Keychain Boy",
    "Chibi Girl.glb" => "Chibi Standee Girl",
    "Chibi GirlHair1.glb" => "Chibi Standee Girl",
    "Chibi GirlHair2.glb" => "Chibi Standee Girl",
    "Chibi Girl Coat.glb" => "Chibi Standee Girl",
    "Chibi Girl Hoodie.glb" => "Chibi Standee Girl",
    "Chibi Girl Sweater.glb" => "Chibi Standee Girl",
    "Chibi Girl T-Shirt.glb" => "Chibi Standee Girl",
    "Chibi Girl Pants.glb" => "Chibi Standee Girl",
    "Chibi Girl Pants2.glb" => "Chibi Standee Girl",
    "Chibi Girl Keychain.glb" => "Chibi Keychain Girl",
]);
}

/* lumang folder => bagong folder (pinagsama ang keychain sa standee) */
if (!defined("FIGURIFY_GLB_FOLDER_ALIASES")) {
define("FIGURIFY_GLB_FOLDER_ALIASES", [
    "Chibi Boy/"             => "Chibi Standee Boy/",
    "Chibi Keychain Boy/"    => "Chibi Standee Boy/",
    "Chibi Keychain Girl/"   => "Chibi Standee Girl/",
    "Hirono Keychain Boy/"   => "Hirono Standee Boy/",
    "Hirono Keychain Girl/"  => "Hirono Standee Girl/",
    "Funko Pop Boy/"         => "Funko Pop Standee Boy/",
    "Funko Pop Girl/"        => "Funko Pop Standee Girl/",
]);
}

/* lumang pangalan ng subfolder => bago (Hairstyle / Tops / Bottoms / Shoes) */
if (!defined("FIGURIFY_GLB_SUBFOLDER_NAMES")) {
define("FIGURIFY_GLB_SUBFOLDER_NAMES", [
    "hair"    => "Hairstyle",
    "top"     => "Tops",
    "tops"    => "Tops",
    "topss"   => "Tops",
    "bottom"  => "Bottoms",
    "pants"   => "Bottoms",
    "shoes"   => "Shoes",
]);
}

/* file na inilipat sa subfolder o pinalitan ng pangalan */
if (!defined("FIGURIFY_GLB_MOVED_FILES")) {
define("FIGURIFY_GLB_MOVED_FILES", [
    "Funko Pop Standee Girl/Funko pop-girl.glb"       => "Funko Pop Standee Girl/funko girl.glb",
    "Funko Pop Standee Girl/Funko_pop-girl.glb"       => "Funko Pop Standee Girl/funko girl.glb",
    "Funko Pop Standee Girl/Funko GirlHair01.glb"     => "Funko Pop Standee Girl/Hairstyle/Funko GirlHair01.glb",
    "Funko Pop Standee Girl/Funko GirlHair02.glb"     => "Funko Pop Standee Girl/Hairstyle/Funko GirlHair02.glb",
    "Funko Pop Standee Girl/Funko GirlHair03.glb"     => "Funko Pop Standee Girl/Hairstyle/Funko GirlHair03.glb",
    "Funko Pop Standee Girl/Funko Girl T-Shirt.glb"   => "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt.glb",
    "Funko Pop Standee Girl/Funko Girl T-Shirt1.glb"  => "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt1.glb",
    "Funko Pop Standee Girl/Funko Girl T-Shirt2.glb"  => "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt2.glb",
    "Funko Pop Standee Girl/Funko Girl Bottom.glb"    => "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom.glb",
    "Funko Pop Standee Girl/Funko Girl Bottom1.glb"   => "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom1.glb",
    "Hirono Standee Boy/Hirono default.glb"           => "Hirono Standee Boy/Hirono Standee-boy.glb",
    "Hirono Standee Boy/Hirono Hair01.glb"            => "Hirono Standee Boy/Hairstyle/Hirono Hair01.glb",
    "Hirono Standee Boy/Hirono Coat.glb"              => "Hirono Standee Boy/Tops/Hirono Coat.glb",
    "Hirono Standee Boy/Hirono Jersey.glb"            => "Hirono Standee Boy/Tops/Hirono Jersey.glb",
    "Hirono Standee Boy/Hirono Polo.glb"              => "Hirono Standee Boy/Tops/Hirono Polo.glb",
    "Hirono Standee Boy/Hirono Polo1.glb"             => "Hirono Standee Boy/Tops/Hirono Polo1.glb",
    "Hirono Standee Boy/Hirono Pants.glb"             => "Hirono Standee Boy/Bottoms/Hirono Pants.glb",
    "Hirono Standee Boy/Hirono Pants1.glb"            => "Hirono Standee Boy/Bottoms/Hirono Pants1.glb",
    "Hirono Standee Boy/Hirono Shoes.glb"             => "Hirono Standee Boy/Shoes/Hirono Shoes.glb",
    "Hirono Standee Girl/Girl Keychain.glb"    => "Hirono Standee Girl/Hirono Girl Keychain.glb",
    "Hirono Standee Girl/Hirono Girl Hair.glb"        => "Hirono Standee Girl/Hairstyle/Hirono Girl Hair.glb",
    "Hirono Standee Girl/Hirono Girl Hair1.glb"       => "Hirono Standee Girl/Hairstyle/Hirono Girl Hair1.glb",
    "Hirono Standee Girl/Hirono Girl Hair2.glb"       => "Hirono Standee Girl/Hairstyle/Hirono Girl Hair2.glb",
    "Hirono Standee Girl/Hirono Girl Hair3.glb"       => "Hirono Standee Girl/Hairstyle/Hirono Girl Hair3.glb",
    "Hirono Standee Girl/Hirono Girl Coat.glb"        => "Hirono Standee Girl/Tops/Hirono Girl Coat.glb",
    "Hirono Standee Girl/Hirono Girl Dress.glb"       => "Hirono Standee Girl/Tops/Hirono Girl Dress.glb",
    "Hirono Standee Girl/Hirono Girl Pants.glb"       => "Hirono Standee Girl/Bottoms/Hirono Girl Pants.glb",
    "Hirono Standee Girl/Hirono Girl Shoes.glb"       => "Hirono Standee Girl/Shoes/Hirono Girl Shoes.glb",
    "Chibi Standee Girl/chibi_girl.glb"               => "Chibi Standee Girl/chibi girl.glb",
]);
}

if (!function_exists("figurify_relocate_glb_path")) {

    /* GINAGAMIT NG: Commission/submit_order.php (figurify_dressup_relocate_paths)
       Inaayos ang lumang GLB path para tama ang ma-save (kapareho ng glbPath() sa dress-up.js):
         1) lumang file na walang folder  => "<folder>/<file>.glb"
         2) lumang folder ("Hirono Keychain Boy/") => bagong folder ("Hirono Standee Boy/")
         3) lumang subfolder ("hair/", "topss/", "pants/") => "Hairstyle/", "Tops/", "Bottoms/"
         4) file na inilipat o pinalitan ng pangalan
       Kung walang tugma, hindi ginagalaw ang path. */
    function figurify_relocate_glb_path(string $path): string
    {
        /* lumang Chibi keychain na may light/dark => iisang katawan na lang */
        $path = preg_replace('/(chibi(?:%20| )keychain)-(?:light|dark)\.glb$/i', '$1.glb', $path);
        $path = preg_replace('/girl-keychain-(?:light|dark)\.glb$/i', 'Chibi Girl Keychain.glb', $path);

        $marker = "GLB_files/";
        $at     = strpos($path, $marker);

        if ($at === false) {
            return $path;
        }

        $head = substr($path, 0, $at + strlen($marker));
        $rest = rawurldecode(substr($path, $at + strlen($marker)));

        /* 1) lumang file na walang folder */
        if (strpos($rest, "/") === false && isset(FIGURIFY_GLB_FOLDER_BY_FILE[$rest])) {
            $rest = FIGURIFY_GLB_FOLDER_BY_FILE[$rest] . "/" . $rest;
        }

        /* 2) lumang folder */
        foreach (FIGURIFY_GLB_FOLDER_ALIASES as $oldFolder => $newFolder) {
            if (strpos($rest, $oldFolder) === 0) {
                $rest = $newFolder . substr($rest, strlen($oldFolder));
                break;
            }
        }

        /* 3) lumang subfolder */
        $parts = explode("/", $rest);
        if (count($parts) === 3 && isset(FIGURIFY_GLB_SUBFOLDER_NAMES[strtolower($parts[1])])) {
            $parts[1] = FIGURIFY_GLB_SUBFOLDER_NAMES[strtolower($parts[1])];
            $rest     = implode("/", $parts);
        }

        /* 4) file na inilipat / pinalitan ng pangalan */
        return $head . (FIGURIFY_GLB_MOVED_FILES[$rest] ?? $rest);
    }

}
/* ===== END GLB PATHS ===== */


/* =====================================================================
   SECTION 3 - DRESS UP DESIGN + 3D VIEWER (dating dressup_helper.php)
   ===================================================================== */

if (!function_exists("figurify_dressup_connection")) {

    /* ANO ITO: sariling DB connection ng helper (isang beses lang bubuksan, kahit ilang beses tawagin).
       BAKIT: may mga page na nagsasara na ng $conn bago mag-render ng figure.
       Kapag nag-fail, ibabalik ay false (hindi mag-eerror ang page). */
    function figurify_dressup_connection()
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


    /* decoded Dress Up design ng figure, o null kung hindi Dress Up */

    /* ANO ITO: kinukuha ang Dress Up design ng isang figure.
       GINAGAMIT NG: lahat ng order page para malaman kung Dress Up ang figure (null = hindi).
       FLOW: 1) kung may "dressup_data" na sa $fig, yun ang gamit
             2) kung wala, kukunin sa table order_figures gamit ang figure_id
             3) i-decode ang JSON; valid lang kung may ["model"]["model"]
             4) naka-cache kada figure_id para isang query lang */
    function figurify_dressup_for_figure(array $fig): ?array
    {
        static $cache = [];

        $figureId = (int) ($fig["figure_id"] ?? 0);

        if (array_key_exists("dressup_data", $fig)) {
            $raw = $fig["dressup_data"];
        } else {

            if ($figureId <= 0) {
                return null;
            }

            if (array_key_exists($figureId, $cache)) {
                return $cache[$figureId];
            }

            $raw = null;
            $connection = figurify_dressup_connection();

            if ($connection) {
                try {
                    $stmt = $connection->prepare(
                        "SELECT dressup_data FROM order_figures WHERE figure_id = ? LIMIT 1"
                    );

                    if ($stmt) {
                        $stmt->bind_param("i", $figureId);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        $raw = $row["dressup_data"] ?? null;
                    }
                } catch (Throwable $error) {
                    /* wala pang dressup_data column - Image Submission lang ang meron */
                    $raw = null;
                }
            }
        }

        $decoded = null;

        if (is_string($raw) && $raw !== "") {
            $json = json_decode($raw, true);

            if (is_array($json) && !empty($json["model"]["model"])) {
                $decoded = $json;
            }
        }

        if ($figureId > 0) {
            $cache[$figureId] = $decoded;
        }

        return $decoded;
    }


    /* ANO ITO: URL folder ng project (hal. "/clayandstuff/") kahit anong folder ang kinalalagyan nito sa htdocs.
       Pang-buo ng path papunta sa Shared/dressup-viewer.js at Commission/ (GLB files). */
    function figurify_dressup_site_base(): string
    {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $documentRoot = rtrim(str_replace("\\", "/", (string) realpath($_SERVER["DOCUMENT_ROOT"] ?? "")), "/");
        $projectRoot  = rtrim(str_replace("\\", "/", (string) realpath(__DIR__ . "/..")), "/");

        $base = ($documentRoot !== "" && strpos($projectRoot, $documentRoot) === 0)
            ? substr($projectRoot, strlen($documentRoot)) . "/"
            : "/";

        return $base;
    }


    /* ANO ITO: maikling htmlspecialchars para ligtas ilabas ang text sa HTML */
    function figurify_dressup_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }


    /* ANO ITO: kulay na pinili + color code, para hindi manghula ang staff/owner.
       Hal. "Custom (#7A3B1F)", "Black (#1E1E1E)". Walang kulay = "".
       GINAGAMIT NG: listahan ng Dress Up design (staff/owner/My Orders) at PDF ng order. */
    function figurify_dressup_color_text(string $name, string $code): string
    {
        $code = strtoupper(trim($code));

        if ($code === "") {
            return $name;
        }

        return $name !== "" ? $name . " (" . $code . ")" : $code;
    }


    /* ANO ITO: presyo ng isang napiling item ("₱150.00"); walang presyo = walang ilalabas */
    function figurify_dressup_price_label(?array $item): string
    {
        if (!$item) {
            return "N/A";
        }

        $price = (float) ($item["price"] ?? 0);

        if ($price > 0) {
            return "₱" . number_format($price, 2);
        }

        return "";
    }


    /* CSS + 3D scripts - isang beses lang ilalabas kada page */

    /* ANO ITO: inilalabas ang CSS + three.js importmap + dressup-viewer.js.
       "once" = isang beses lang kada page kahit ilang figure ang nasa page. */
    function figurify_dressup_assets_once(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;

        $base = figurify_dressup_site_base();
        $viewerJs = $base . "Shared/dressup-viewer.js";
        ?>
<style>
/* Dress Up figure layout: kaliwa = figure details + design, kanan = 3D (parehong taas), baba = custom box */
.dressup-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-template-areas:"details view" "box box";gap:18px;align-items:stretch}
.dressup-grid > :first-child{grid-area:details;min-width:0}
.dressup-grid > :nth-child(2){grid-area:box;min-width:0}
.dressup-grid > .dressup-3d-column{grid-area:view;min-width:0;display:flex;flex-direction:column}
.dressup-3d-column .dressup-viewer-block{flex:1;display:flex;flex-direction:column;margin-top:0;gap:14px}
.dressup-3d-column .dressup-viewer-block .dressup-3d-stage{flex:1;height:auto;min-height:280px}
.dressup-3d-column .dressup-design-title{font-size:13px;line-height:1.2}
/* Figure Style / Product Type / Size: walang larawan, maliit, tatlong magkakatabi */
.dressup-grid > :first-child .detail-item-list,
.dressup-grid > :first-child .qv-detail-item-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.dressup-grid > :first-child .detail-item-image,
.dressup-grid > :first-child .qv-detail-item-image{display:none}
.dressup-grid > :first-child .detail-item-card,
.dressup-grid > :first-child .qv-detail-item-card{flex-direction:column;align-items:flex-start;justify-content:flex-start;gap:3px;padding:8px 10px;flex-wrap:nowrap;min-width:0}
.dressup-grid > :first-child .detail-item-body,
.dressup-grid > :first-child .qv-detail-item-body{min-width:0;width:100%}
.dressup-grid > :first-child .detail-item-label,
.dressup-grid > :first-child .qv-detail-item-label{font-size:9px}
.dressup-grid > :first-child .detail-item-value,
.dressup-grid > :first-child .qv-detail-item-value{font-size:12px;line-height:1.25}
.dressup-grid > :first-child .detail-item-price,
.dressup-grid > :first-child .qv-detail-item-price{font-size:11px;text-align:left}
.dressup-grid > :first-child .detail-item-price.muted,
.dressup-grid > :first-child .qv-detail-item-price-muted{display:none}
@media (max-width:900px){.dressup-grid{grid-template-columns:minmax(0,1fr);grid-template-areas:"details" "view" "box"}}
/* makitid na lalagyan (hal. side panel ng Owner/Staff): isa-isang column na lang */
*:has(> .dressup-grid){container-type:inline-size}
@container (max-width:480px){.dressup-grid{grid-template-columns:minmax(0,1fr);grid-template-areas:"details" "view" "box"}}
.dressup-design{display:flex;flex-direction:column;gap:14px;margin-top:14px}
.dressup-design-title{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800;color:#8c3764;letter-spacing:.3px}
.dressup-3d-stage{position:relative;width:100%;height:320px;border-radius:16px;overflow:hidden;border:1px solid #f0d5e5;background:radial-gradient(circle at 50% 35%,#ffffff 0%,#fff3fa 60%,#fbe6f1 100%)}
.dressup-3d-stage canvas{position:absolute;inset:0;display:block;width:100%;height:100%;cursor:pointer;touch-action:pan-y}
.dressup-3d-status{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#a2688a;font-size:12px;font-weight:700;text-align:center;padding:16px;pointer-events:none}
.dressup-3d-status .dressup-3d-spinner{width:26px;height:26px;border-radius:50%;border:3px solid #f5cfe2;border-top-color:#df65a6;animation:dressupSpin .8s linear infinite}
.dressup-3d-stage.is-ready .dressup-3d-status{display:none}
.dressup-3d-stage.is-error .dressup-3d-spinner{display:none}
.dressup-3d-hint{position:absolute;left:0;right:0;text-align:center;font-size:10.5px;color:#b08aa0;pointer-events:none;bottom:34px}
.dressup-3d-stage .figure-360-control{position:absolute;left:50%;bottom:6px;transform:translateX(-50%);z-index:5;display:inline-flex;align-items:center;gap:2px;padding:2px;border:1px solid #f0c3dc;border-radius:999px;background:rgba(255,255,255,.88);box-shadow:0 2px 6px rgba(218,107,165,.14);user-select:none}
.dressup-3d-stage .figure-360-label{min-width:30px;text-align:center;color:#c9548f;font-size:10px;font-weight:700}
.dressup-3d-stage .figure-360-arrow{width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;padding:0;border:none;border-radius:50%;background:#fbe3f0;color:#c9548f;cursor:pointer;transition:background .2s,color .2s,transform .15s}
.dressup-3d-stage .figure-360-arrow:hover{background:linear-gradient(180deg,#e78aba,#d86aa7);color:#fff}
.dressup-3d-stage .figure-360-arrow:active{transform:scale(.9)}
@keyframes dressupSpin{to{transform:rotate(360deg)}}
.dressup-picks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.dressup-pick{display:flex;flex-direction:column;align-items:flex-start;gap:2px;padding:8px 10px;border-radius:12px;background:#fff;border:1px solid #f1dbe7;min-width:0;box-shadow:0 3px 6px rgba(0,0,0,.18)}
.dressup-pick-body{display:flex;flex-direction:column;gap:1px;min-width:0;width:100%}
.dressup-pick.has-image{flex-direction:row;align-items:center;gap:8px}
.dressup-pick-img{flex:0 0 auto;width:44px;height:44px;object-fit:contain;border-radius:8px;border:1px solid #f1dbe7;background:#fff5f9}
.dressup-pick-label{font-size:9px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:#b07a98}
.dressup-pick-value{font-size:12px;font-weight:700;color:#4a2c3c;line-height:1.25;word-break:break-word}
.dressup-pick-color{display:flex;align-items:center;gap:5px;margin-top:2px;font-size:10.5px;font-weight:600;color:#7d5a6d}
.dressup-swatch{display:inline-block;width:11px;height:11px;border-radius:50%;border:1px solid rgba(0,0,0,.15);vertical-align:middle}
.dressup-pick-price{font-size:10.5px;font-weight:800;color:#d05c98;white-space:nowrap}
.dressup-pick-price.is-included{color:#8aa596;font-weight:700}
@media (max-width:640px){.dressup-3d-stage{height:300px}}
</style>
<script type="importmap">
{
    "imports": {
        "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
        "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
    }
}
</script>
<script type="module" src="<?php echo figurify_dressup_e($viewerJs); ?>"></script>
        <?php
    }


    /* ANO ITO: data na kailangan ng 3D viewer (body + accessories + kulay), ginagawang JSON
       para ilagay sa data-design ng .dressup-3d-stage. Binabasa ito ng Shared/dressup-viewer.js. */

    function figurify_dressup_viewer_json(array $design): string
    {
        $viewerData = [
            "category" => $design["category"] ?? "",
            "model"    => $design["model"]["model"] ?? "",
            "skin"     => $design["skin"]["color"] ?? "",
            "skinName" => $design["skin"]["name"] ?? "",
            "parts"    => [],
        ];

        foreach (($design["slots"] ?? []) as $slotRow) {
            $viewerData["parts"][] = [
                "slot"     => $slotRow["slot"] ?? "",
                "model"    => $slotRow["item"]["model"] ?? "",
                "color"    => $slotRow["colorValue"] ?? "",
                "isBottom" => !empty($slotRow["isBottom"]),
            ];
        }

        return (string) json_encode($viewerData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }


    /* 3D figure - nasa kanan ng Figure Details (tingnan ang .dressup-grid) */

    /* ANO ITO: ang 3D figure box (kanan ng Figure Details). Walang ilalabas kung hindi Dress Up.
       GINAGAMIT NG: my-orders.php at lahat ng owner/staff order page. */
    function figurify_render_dressup_viewer(array $fig): void
    {
        $design = figurify_dressup_for_figure($fig);

        if ($design === null) {
            return;
        }

        figurify_dressup_assets_once();

        $base = figurify_dressup_site_base();
        ?>
        <div class="dressup-design dressup-viewer-block">

            <div class="dressup-design-title">
                3D Figure
            </div>

            <div
                class="dressup-3d-stage"
                data-dressup-viewer
                data-asset-base="<?php echo figurify_dressup_e($base . "Commission/"); ?>"
                data-design="<?php echo figurify_dressup_e(figurify_dressup_viewer_json($design)); ?>"
            >
                <canvas aria-label="3D preview of the ordered figure"></canvas>
                <div class="dressup-3d-status">
                    <span class="dressup-3d-spinner"></span>
                    <span class="dressup-3d-status-text">Loading 3D figure…</span>
                </div>
            </div>

        </div>
        <?php
    }


    /* listahan ng napiling design (Gender, Skin, Hair, Top, Bottom, Shoes) -
       nasa ilalim ng Figure Style / Product Type / Size sa kaliwa */

    /* ANO ITO: ang listahan ng napili (Gender, Skin Tone, Hair, Top, Bottom, Shoes + kulay + presyo).
       $variant: "customer" (My Orders) o "staff" (owner/staff) - pareho ang itsura, pang-label lang. */
    function figurify_render_dressup_design(array $fig, string $variant = "customer"): void
    {
        $design = figurify_dressup_for_figure($fig);

        if ($design === null) {
            return;
        }

        figurify_dressup_assets_once();

        $skin = $design["skin"] ?? null;
        ?>
        <div class="dressup-design" data-variant="<?php echo figurify_dressup_e($variant); ?>">

            <div class="dressup-design-title">Dress Up Design</div>

            <div class="dressup-picks">

                <!-- Gender at Skin Tone: maliit (dressup-pick-basic), magkatabi sa My Orders -->
                <div class="dressup-pick dressup-pick-basic">
                    <div class="dressup-pick-body">
                        <span class="dressup-pick-label">Gender</span>
                        <span class="dressup-pick-value"><?php
                            /* Boy / Girl - kasama ang mga lumang order (Male / Female / "... Default") */
                            $genderRaw = (string) ($design["genderLabel"] ?? ($design["model"]["name"] ?? ""));
                            $genderShown = preg_match('/girl|female/i', $genderRaw) ? "Girl" : (preg_match('/boy|male|default/i', $genderRaw) ? "Boy" : $genderRaw);
                            echo figurify_dressup_e($genderShown);
                        ?></span>
                    </div>
                </div>

                <?php if ($skin): ?>
                    <div class="dressup-pick dressup-pick-basic">
                        <div class="dressup-pick-body">
                            <span class="dressup-pick-label">Skin Tone</span>
                            <span class="dressup-pick-value">
                                <?php if (!empty($skin["color"])): ?>
                                    <svg class="dressup-swatch" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="<?php echo figurify_dressup_e($skin["color"]); ?>"></circle></svg>
                                <?php endif; ?>
                                <?php echo figurify_dressup_e(figurify_dressup_color_text((string) ($skin["name"] ?? ""), (string) ($skin["color"] ?? ""))); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php $base = figurify_dressup_site_base(); ?>
                <?php foreach (($design["slots"] ?? []) as $slotRow): ?>
                    <?php
                    $item       = $slotRow["item"] ?? [];
                    $priceLabel = figurify_dressup_price_label($item);
                    $slotImage  = (string) ($slotRow["image"] ?? "");
                    ?>
                    <!-- larawan (kung meron) | Hair / Top... + pangalan ng napili | kulay | presyo -->
                    <div class="dressup-pick<?php echo $slotImage !== "" ? " has-image" : ""; ?>">
                        <?php if ($slotImage !== ""): ?>
                            <img class="dressup-pick-img" src="<?php echo figurify_dressup_e($base . "Commission/" . $slotImage); ?>" alt="<?php echo figurify_dressup_e($item["name"] ?? ""); ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="dressup-pick-body">
                            <span class="dressup-pick-label"><?php echo figurify_dressup_e($slotRow["label"] ?? ""); ?></span>
                            <span class="dressup-pick-value">
                                <?php echo figurify_dressup_e($item["name"] ?? ""); ?>
                                <?php if (!empty($slotRow["colorName"]) || !empty($slotRow["colorValue"])): ?>
                                    <!-- kulay + color code, kasunod mismo ng pangalan -->
                                    <span class="dressup-pick-color">
                                        <?php if (!empty($slotRow["colorValue"])): ?>
                                            <svg class="dressup-swatch" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="<?php echo figurify_dressup_e($slotRow["colorValue"]); ?>"></circle></svg>
                                        <?php endif; ?>
                                        <?php echo figurify_dressup_e(figurify_dressup_color_text((string) ($slotRow["colorName"] ?? ""), (string) ($slotRow["colorValue"] ?? ""))); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($priceLabel !== ""): ?>
                            <div class="dressup-pick-price"><?php echo figurify_dressup_e($priceLabel); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            </div>

        </div>
        <?php
    }

}


/* =====================================================================
   SECTION 4 - LABEL AT LARAWAN NG FIGURE (order pages)
   Iisang kopya para sa owner/staff order pages at my-order/my-orders.php.
   Dati ay nakakopya ito sa bawat page.
   ===================================================================== */

function orderMethodLabel($method)
{
    $labels = [
        "reference"    => "Image Submission",
        "create_style" => "Dress Up",
    ];

    return $labels[$method] ?? "Image Submission";
}

function figureHasSize($fig)
{
    return !empty($fig["size_label"]);
}

function hironoBaseBoxPrice($fig)
{
    return (float) $fig["box_addon_price"] - (float) $fig["hirono_blind_items_total"];
}

function hironoBoxDesignLabel($design)
{
    $labels = [
        "checkered"   => "Checkered",
        "hirono_peek" => "Hirono Peek",
    ];

    return $labels[$design] ?? $design;
}

function hironoBoxDesignImage($design)
{
    $map = [
        "checkered"   => "boxes/hirono-box-checkered.jpg",
        "hirono_peek" => "boxes/hirono-box-peek.jpg",
    ];

    return $map[$design] ?? null;
}

function hironoBlindItemImage($itemKey)
{
    return "figures/hirono/hirono-style.jpg";
}

function hironoBlindTypeImage($blindType)
{
    return ($blindType === "set") ? "boxes/blind-box-set.jpg" : "figures/hirono/hirono-style.jpg";
}

function figureStyleImage($style)
{
    $map = [
        "Chibi"     => "figures/chibi/chibi-style.jpg",
        "Funko Pop" => "figures/funko-pop/funko-pop-style.jpg",
        "Hirono"    => "figures/hirono/hirono-style.jpg",
    ];

    return $map[$style] ?? null;
}

function productTypeImage($style, $product)
{
    $map = [
        "Chibi" => [
            "Full Body Standee"  => "figures/chibi/chibi-full-body-standee.jpg",
            "Full Body Keychain" => "figures/chibi/chibi-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/chibi/chibi-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/chibi/chibi-head-only-keychain.jpg",
        ],
        "Funko Pop" => [
            "Full Body Standee"  => "figures/funko-pop/funko-pop-full-body-standee.jpg",
        ],
        "Hirono" => [
            "Full Body Standee"  => "figures/hirono/hirono-full-body-standee.jpg",
            "Full Body Keychain" => "figures/hirono/hirono-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/hirono/hirono-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/hirono/hirono-head-only-keychain.jpg",
        ],
    ];

    return $map[$style][$product] ?? null;
}
/* ====================== END SECTION 4 ====================== */
