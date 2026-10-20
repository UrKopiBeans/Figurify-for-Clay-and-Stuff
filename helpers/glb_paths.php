<?php

/* GLB folders (dressup-assets/GLB_files/<Folder>/<file>.glb) - bawat figure,
   product type at gender may sariling folder. Ginagamit para ituro ang mga
   lumang path (walang folder, o "Chibi Boy/") sa bagong folder, hal. sa mga
   lumang Dress Up draft na naka-save pa sa browser ng customer.
   Kapareho ng GLB_FOLDER_BY_FILE sa Commission/dress-up.js at Shared/dressup-viewer.js. */

const FIGURIFY_GLB_FOLDER_BY_FILE = [
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
];

/* lumang pangalan ng folder -> bagong pangalan */
const FIGURIFY_GLB_FOLDER_ALIASES = [
    "Chibi Boy/"      => "Chibi Standee Boy/",
    "Funko Pop Boy/"  => "Funko Pop Standee Boy/",
    "Funko Pop Girl/" => "Funko Pop Standee Girl/",
];

function figurify_relocate_glb_path(string $path): string
{
    $marker = "GLB_files/";
    $at     = strpos($path, $marker);

    if ($at === false) {
        return $path;
    }

    $head = substr($path, 0, $at + strlen($marker));
    $rest = substr($path, $at + strlen($marker));

    foreach (FIGURIFY_GLB_FOLDER_ALIASES as $oldFolder => $newFolder) {
        if (strpos($rest, $oldFolder) === 0) {
            return $head . $newFolder . substr($rest, strlen($oldFolder));
        }
    }

    if (strpos($rest, "/") === false && isset(FIGURIFY_GLB_FOLDER_BY_FILE[$rest])) {
        return $head . FIGURIFY_GLB_FOLDER_BY_FILE[$rest] . "/" . $rest;
    }

    return $path;
}
