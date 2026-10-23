<?php
/**
 * Lightweight Jalali calendar helpers.
 * Pure PHP implementation: no Composer package, Intl extension, JavaScript library or network access.
 * Datetimes stay Gregorian in storage and machine-readable metadata; only visible date strings are converted.
 */
if (!function_exists('mhJalaliDigits')) {
    function mhJalaliDigits(string $value): string {
        return strtr($value, [
            '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴',
            '5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',
        ]);
    }
}

if (!function_exists('mhGregorianToJalali')) {
    /** @return array{0:int,1:int,2:int} Jalali year, month, day */
    function mhGregorianToJalali(int $gy, int $gm, int $gd): array {
        $gMonthDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $gMonthDays[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }
}

if (!function_exists('jalaliFormatDate')) {
    /**
     * Format a Gregorian datetime as a Jalali date.
     * Supported tokens include Y/y, m/n, d/j, H/G, h/g, i/s, F/M and l/D.
     * Unknown letters are retained as literals for backwards compatibility with simple date formats.
     */
    function jalaliFormatDate($value, string $format = 'Y/m/d', bool $persianDigits = true): string {
        if ($value === null || trim((string)$value) === '') return '-';
        $timestamp = is_int($value) ? $value : strtotime((string)$value);
        if ($timestamp === false) return '-';

        $gy = (int)date('Y', $timestamp);
        $gm = (int)date('n', $timestamp);
        $gd = (int)date('j', $timestamp);
        [$jy, $jm, $jd] = mhGregorianToJalali($gy, $gm, $gd);

        $months = [
            1=>'فروردین', 2=>'اردیبهشت', 3=>'خرداد', 4=>'تیر',
            5=>'مرداد', 6=>'شهریور', 7=>'مهر', 8=>'آبان',
            9=>'آذر', 10=>'دی', 11=>'بهمن', 12=>'اسفند',
        ];
        $weekdays = [
            0=>'یکشنبه', 1=>'دوشنبه', 2=>'سه‌شنبه', 3=>'چهارشنبه',
            4=>'پنجشنبه', 5=>'جمعه', 6=>'شنبه',
        ];
        $tokens = [
            'Y'=>(string)$jy, 'y'=>substr((string)$jy, -2),
            'm'=>sprintf('%02d', $jm), 'n'=>(string)$jm,
            'd'=>sprintf('%02d', $jd), 'j'=>(string)$jd,
            'H'=>date('H', $timestamp), 'G'=>(string)(int)date('G', $timestamp),
            'h'=>date('h', $timestamp), 'g'=>(string)(int)date('g', $timestamp),
            'i'=>date('i', $timestamp), 's'=>date('s', $timestamp),
            'a'=>date('a', $timestamp), 'A'=>date('A', $timestamp),
            'F'=>$months[$jm], 'M'=>mb_substr($months[$jm], 0, 3, 'UTF-8'),
            'l'=>$weekdays[(int)date('w', $timestamp)],
            'D'=>mb_substr($weekdays[(int)date('w', $timestamp)], 0, 3, 'UTF-8'),
        ];

        $formatted = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $formatted .= $format[++$i];
                continue;
            }
            $formatted .= $tokens[$char] ?? $char;
        }

        return $persianDigits ? mhJalaliDigits($formatted) : $formatted;
    }
}
