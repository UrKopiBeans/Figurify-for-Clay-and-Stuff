<?php

/* Upload check: hindi sapat ang extension lang - sinisigurong
   totoong larawan ang file (MIME galing sa laman mismo + getimagesize),
   para hindi makapag-upload ng script na pinangalanang .jpg. */

if (!function_exists("figurify_is_real_image")) {

    function figurify_is_real_image(string $tmpPath, int $maxBytes = 10485760): bool
    {
        if (!is_file($tmpPath) || filesize($tmpPath) <= 0 || filesize($tmpPath) > $maxBytes) {
            return false;
        }

        $mime = function_exists("mime_content_type") ? (string) mime_content_type($tmpPath) : "";

        if (!in_array($mime, ["image/jpeg", "image/png", "image/gif", "image/webp"], true)) {
            return false;
        }

        return @getimagesize($tmpPath) !== false;
    }
}
