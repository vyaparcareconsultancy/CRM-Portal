<?php

declare(strict_types=1);

namespace App\Helpers;

class IndianNumberToWords
{
    private static array $ones = [
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private static array $tens = [
        2 => 'Twenty',
        3 => 'Thirty',
        4 => 'Forty',
        5 => 'Fifty',
        6 => 'Sixty',
        7 => 'Seventy',
        8 => 'Eighty',
        9 => 'Ninety',
    ];

    /**
     * Convert an amount in INR to Indian format words.
     * E.g. 125450.50 -> "One Lakh Twenty-Five Thousand Four Hundred Fifty Rupees and Fifty Paise Only"
     */
    public static function toIndianRupees(float|int|string $amount): string
    {
        $num = (float)$amount;
        if ($num < 0) {
            return 'Minus ' . self::toIndianRupees(abs($num));
        }

        // Split rupees and paise using integer rounding to avoid float drift
        $rupees = (int)floor($num);
        $paise = (int)round(($num - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return 'Zero Rupees Only';
        }

        $rupeesWords = '';
        if ($rupees > 0) {
            $rupeesWords = self::convertRupees($rupees) . ' Rupees';
        }

        $paiseWords = '';
        if ($paise > 0) {
            $paiseWords = self::convertBelowHundred($paise) . ' Paise';
        }

        if ($rupees > 0 && $paise > 0) {
            return trim($rupeesWords) . ' and ' . trim($paiseWords) . ' Only';
        }

        if ($rupees > 0) {
            return trim($rupeesWords) . ' Only';
        }

        return trim($paiseWords) . ' Only';
    }

    private static function convertRupees(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        $words = [];

        // Crores (>= 1,00,00,000)
        $crores = (int)floor($n / 10000000);
        $n %= 10000000;
        if ($crores > 0) {
            $words[] = self::convertRupees($crores) . ' Crore';
        }

        // Lakhs (>= 1,00,000)
        $lakhs = (int)floor($n / 100000);
        $n %= 100000;
        if ($lakhs > 0) {
            $words[] = self::convertBelowHundred($lakhs) . ' Lakh';
        }

        // Thousands (>= 1,000)
        $thousands = (int)floor($n / 1000);
        $n %= 1000;
        if ($thousands > 0) {
            $words[] = self::convertBelowHundred($thousands) . ' Thousand';
        }

        // Hundreds (>= 100)
        $hundreds = (int)floor($n / 100);
        $n %= 100;
        if ($hundreds > 0) {
            $words[] = self::$ones[$hundreds] . ' Hundred';
        }

        // Remainder (< 100)
        if ($n > 0) {
            $words[] = self::convertBelowHundred($n);
        }

        return implode(' ', $words);
    }

    private static function convertBelowHundred(int $n): string
    {
        if ($n <= 0) {
            return '';
        }

        if ($n < 20) {
            return self::$ones[$n];
        }

        $ten = (int)floor($n / 10);
        $unit = $n % 10;

        if ($unit > 0) {
            return self::$tens[$ten] . '-' . self::$ones[$unit];
        }

        return self::$tens[$ten];
    }
}
