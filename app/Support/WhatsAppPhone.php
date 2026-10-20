<?php

namespace App\Support;

/**
 * Normalizes the split country-code / WhatsApp-number fields used by web and API forms.
 */
final class WhatsAppPhone
{
    /** @return array{country_code: string, number: string} */
    public static function normalize(mixed $countryCode, mixed $number): array
    {
        // Normalize scalar form values only. Structured JSON must reach the
        // required-field validation as invalid input, never trigger a 500 here.
        $rawCountryCode = is_string($countryCode) || is_int($countryCode) ? (string) $countryCode : '';
        $rawNumber = is_string($number) || is_int($number) ? trim((string) $number) : '';
        $countryCode = preg_replace('/\D/', '', $rawCountryCode) ?: '';
        $number = preg_replace('/\D/', '', $rawNumber) ?: '';

        // Legacy MiCatalogo forms used the Dominican area code as if it were
        // the international calling code. Keep accepting those values, but
        // persist the canonical NANP calling code and national number.
        $dominicanAreaCodes = [
            '1809' => '809',
            '1829' => '829',
            '1849' => '849',
        ];

        if (isset($dominicanAreaCodes[$countryCode])) {
            $areaCode = $dominicanAreaCodes[$countryCode];
            $countryCode = '1';

            if (strlen($number) === 7) {
                $number = $areaCode.$number;
            } elseif (strlen($number) === 11 && str_starts_with($number, '1'.$areaCode)) {
                $number = substr($number, 1);
            }
        } elseif ($countryCode === '1' && strlen($number) === 11 && str_starts_with($number, '1')) {
            // A browser's autocomplete may place the full +1 number into the
            // national-number field even though the country code is separate.
            $number = substr($number, 1);
        } elseif ($rawNumber !== '' && str_starts_with($rawNumber, '+') && $countryCode !== '' && str_starts_with($number, $countryCode)) {
            // Accept an E.164 number pasted into the number field when the
            // country code is also supplied separately.
            $number = substr($number, strlen($countryCode));
        }

        return ['country_code' => $countryCode, 'number' => $number];
    }
}
