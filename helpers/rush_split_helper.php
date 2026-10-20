<?php

/* Rush fee kada figure (Head Only Keychain = 200, iba = 500).
   Kung hindi tugma sa naka-save na rush_fee (lumang order), isang Rush Fee line na lang.

     $split = figurify_rush_split($order, $figures);
     $split["per"][0]  -> rush fee ng Figure 1
     $split["legacy"]  -> buong rush fee kapag hindi mahati */

if (!function_exists("figurify_rush_split")) {

    function figurify_rush_fee_for_figure(array $figure): float
    {
        $type = strtolower(trim((string) ($figure["product_type"] ?? "")));

        if ($type === "") {
            return 0.0;
        }

        return (strpos($type, "head only") !== false) ? 200.0 : 500.0;
    }

    function figurify_rush_split(array $order, array $figures): array
    {
        $stored = (float) ($order["rush_fee"] ?? 0);
        $isRush = ($stored > 0) || (strtolower((string) ($order["order_type"] ?? "")) === "rush");

        $per = [];

        foreach (array_values($figures) as $index => $figure) {
            $per[$index] = $isRush ? figurify_rush_fee_for_figure($figure) : 0.0;
        }

        if (!$isRush) {
            return ["per" => $per, "legacy" => 0.0, "total" => 0.0];
        }

        $sum = array_sum($per);

        if ($stored > 0 && abs($sum - $stored) > 0.009) {
            return [
                "per"    => array_fill(0, count($per), 0.0),
                "legacy" => $stored,
                "total"  => $stored,
            ];
        }

        return ["per" => $per, "legacy" => 0.0, "total" => $stored > 0 ? $stored : $sum];
    }
}


/* * FIGURE NAME
 * Kapag walang inilagay na pangalan ang customer, "Chibi Figure 2" (placeholder)
 * ang naka-save sa database. Sa display, "None" ang ipapakita. */
if (!function_exists("figurify_figure_name_display")) {

    function figurify_figure_name_display(array $figure): string
    {
        $name = trim((string) ($figure["figure_name"] ?? ""));

        if ($name === "") {
            return "None";
        }

        $hasFee = array_key_exists("name_fee", $figure) ? ((float) $figure["name_fee"] > 0) : false;
        $style  = trim((string) ($figure["figure_style"] ?? ""));

        $isPlaceholder = $style !== ""
            ? (bool) preg_match('/^' . preg_quote($style, '/') . ' Figure \d+$/u', $name)
            : (bool) preg_match('/^[A-Za-z ]+ Figure \d+$/u', $name);

        return ($isPlaceholder && !$hasFee) ? "None" : $name;
    }
}
