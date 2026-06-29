<?php

$sms = "Confirmed. You have received TZS 45,000 from JOHN MWAKAJE 0789123456 on 29/6/26 at 2:30 PM. ABC123XYZ09";

if (preg_match("/(\d{1,2}\/\d{1,2}\/\d{2,4})\s+at\s+(\d{1,2}:\d{2}\s*[AP]M)/i", $sms, $m)) {
    echo "Matched date: {$m[1]} time: {$m[2]}\n";

    $segs = explode("/", $m[1]);

    if (strlen($segs[2]) === 2) {
        $segs[2] = "20" . $segs[2];
    }

    $norm = implode("/", $segs);

    echo "Normalized: $norm\n";

    $ts = strtotime("$norm {$m[2]}");

    echo "Timestamp: " . var_export($ts, true) . "\n";
    echo "Formatted: " . date("Y-m-d H:i:s", $ts) . "\n";
} else {
    echo "NO MATCH\n";
}