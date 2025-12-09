<?php
// Simple date helper to format dates in Bahasa Indonesia
function format_date_id($datetime, $withTime = true) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $day = date('j', $ts);
    $month = $months[(int)date('n', $ts)];
    $year = date('Y', $ts);
    $result = $day . ' ' . $month . ' ' . $year;
    if ($withTime) {
        $result .= ' ' . date('H:i', $ts);
    }
    return $result;
}

function month_short_id($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $short = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return $short[(int)date('n', $ts) - 1];
}

?>
