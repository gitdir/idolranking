<?php

namespace App\Support;

final class Countries
{
    public static function localizedName(?string $countryCode, string $locale): ?string
    {
        static $countries = null;
        $countries ??= self::all();

        if ($countryCode === null || !array_key_exists(strtoupper($countryCode), $countries)) {
            return null;
        }

        $code = strtoupper($countryCode);
        $localized = class_exists(\Locale::class)
            ? \Locale::getDisplayRegion('und_'.$code, $locale)
            : '';

        return $localized !== '' && $localized !== 'und_'.$code
            ? $localized
            : ((str_starts_with(str_replace('-', '_', $locale), 'ko'))
                ? (self::nativeNames()[$code] ?? $countries[$code])
                : $countries[$code]);
    }

    public static function all(): array
    {
        $countries = [
            'AD' => 'Andorra', 'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AG' => 'Antigua and Barbuda',
            'AI' => 'Anguilla', 'AL' => 'Albania', 'AM' => 'Armenia', 'AO' => 'Angola', 'AQ' => 'Antarctica',
            'AR' => 'Argentina', 'AS' => 'American Samoa', 'AT' => 'Austria', 'AU' => 'Australia', 'AW' => 'Aruba',
            'AX' => 'Åland Islands', 'AZ' => 'Azerbaijan', 'BA' => 'Bosnia and Herzegovina', 'BB' => 'Barbados',
            'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BH' => 'Bahrain',
            'BI' => 'Burundi', 'BJ' => 'Benin', 'BL' => 'Saint Barthélemy', 'BM' => 'Bermuda', 'BN' => 'Brunei',
            'BO' => 'Bolivia', 'BQ' => 'Bonaire, Sint Eustatius and Saba', 'BR' => 'Brazil', 'BS' => 'Bahamas',
            'BT' => 'Bhutan', 'BV' => 'Bouvet Island', 'BW' => 'Botswana', 'BY' => 'Belarus', 'BZ' => 'Belize',
            'CA' => 'Canada', 'CC' => 'Cocos (Keeling) Islands', 'CD' => 'Democratic Republic of the Congo',
            'CF' => 'Central African Republic', 'CG' => 'Republic of the Congo', 'CH' => 'Switzerland',
            'CI' => "Côte d'Ivoire", 'CK' => 'Cook Islands', 'CL' => 'Chile', 'CM' => 'Cameroon', 'CN' => 'China',
            'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CU' => 'Cuba', 'CV' => 'Cabo Verde', 'CW' => 'Curaçao',
            'CX' => 'Christmas Island', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DE' => 'Germany', 'DJ' => 'Djibouti',
            'DK' => 'Denmark', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'DZ' => 'Algeria', 'EC' => 'Ecuador',
            'EE' => 'Estonia', 'EG' => 'Egypt', 'EH' => 'Western Sahara', 'ER' => 'Eritrea', 'ES' => 'Spain',
            'ET' => 'Ethiopia', 'FI' => 'Finland', 'FJ' => 'Fiji', 'FK' => 'Falkland Islands',
            'FM' => 'Micronesia', 'FO' => 'Faroe Islands', 'FR' => 'France', 'GA' => 'Gabon', 'GB' => 'United Kingdom',
            'GD' => 'Grenada', 'GE' => 'Georgia', 'GF' => 'French Guiana', 'GG' => 'Guernsey', 'GH' => 'Ghana',
            'GI' => 'Gibraltar', 'GL' => 'Greenland', 'GM' => 'Gambia', 'GN' => 'Guinea', 'GP' => 'Guadeloupe',
            'GQ' => 'Equatorial Guinea', 'GR' => 'Greece', 'GS' => 'South Georgia and the South Sandwich Islands',
            'GT' => 'Guatemala', 'GU' => 'Guam', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HK' => 'Hong Kong',
            'HM' => 'Heard Island and McDonald Islands', 'HN' => 'Honduras', 'HR' => 'Croatia', 'HT' => 'Haiti',
            'HU' => 'Hungary', 'ID' => 'Indonesia', 'IE' => 'Ireland', 'IL' => 'Israel', 'IM' => 'Isle of Man',
            'IN' => 'India', 'IO' => 'British Indian Ocean Territory', 'IQ' => 'Iraq', 'IR' => 'Iran',
            'IS' => 'Iceland', 'IT' => 'Italy', 'JE' => 'Jersey', 'JM' => 'Jamaica', 'JO' => 'Jordan',
            'JP' => 'Japan', 'KE' => 'Kenya', 'KG' => 'Kyrgyzstan', 'KH' => 'Cambodia', 'KI' => 'Kiribati',
            'KM' => 'Comoros', 'KN' => 'Saint Kitts and Nevis', 'KP' => 'North Korea', 'KR' => 'South Korea',
            'KW' => 'Kuwait', 'KY' => 'Cayman Islands', 'KZ' => 'Kazakhstan', 'LA' => 'Laos', 'LB' => 'Lebanon',
            'LC' => 'Saint Lucia', 'LI' => 'Liechtenstein', 'LK' => 'Sri Lanka', 'LR' => 'Liberia',
            'LS' => 'Lesotho', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'LY' => 'Libya',
            'MA' => 'Morocco', 'MC' => 'Monaco', 'MD' => 'Moldova', 'ME' => 'Montenegro', 'MF' => 'Saint Martin',
            'MG' => 'Madagascar', 'MH' => 'Marshall Islands', 'MK' => 'North Macedonia', 'ML' => 'Mali',
            'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MO' => 'Macao', 'MP' => 'Northern Mariana Islands',
            'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MS' => 'Montserrat', 'MT' => 'Malta', 'MU' => 'Mauritius',
            'MV' => 'Maldives', 'MW' => 'Malawi', 'MX' => 'Mexico', 'MY' => 'Malaysia', 'MZ' => 'Mozambique',
            'NA' => 'Namibia', 'NC' => 'New Caledonia', 'NE' => 'Niger', 'NF' => 'Norfolk Island',
            'NG' => 'Nigeria', 'NI' => 'Nicaragua', 'NL' => 'Netherlands', 'NO' => 'Norway', 'NP' => 'Nepal',
            'NR' => 'Nauru', 'NU' => 'Niue', 'NZ' => 'New Zealand', 'OM' => 'Oman', 'PA' => 'Panama',
            'PE' => 'Peru', 'PF' => 'French Polynesia', 'PG' => 'Papua New Guinea', 'PH' => 'Philippines',
            'PK' => 'Pakistan', 'PL' => 'Poland', 'PM' => 'Saint Pierre and Miquelon', 'PN' => 'Pitcairn',
            'PR' => 'Puerto Rico', 'PS' => 'Palestine', 'PT' => 'Portugal', 'PW' => 'Palau', 'PY' => 'Paraguay',
            'QA' => 'Qatar', 'RE' => 'Réunion', 'RO' => 'Romania', 'RS' => 'Serbia', 'RU' => 'Russia',
            'RW' => 'Rwanda', 'SA' => 'Saudi Arabia', 'SB' => 'Solomon Islands', 'SC' => 'Seychelles',
            'SD' => 'Sudan', 'SE' => 'Sweden', 'SG' => 'Singapore', 'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
            'SI' => 'Slovenia', 'SJ' => 'Svalbard and Jan Mayen', 'SK' => 'Slovakia', 'SL' => 'Sierra Leone',
            'SM' => 'San Marino', 'SN' => 'Senegal', 'SO' => 'Somalia', 'SR' => 'Suriname',
            'SS' => 'South Sudan', 'ST' => 'São Tomé and Príncipe', 'SV' => 'El Salvador', 'SX' => 'Sint Maarten',
            'SY' => 'Syria', 'SZ' => 'Eswatini', 'TC' => 'Turks and Caicos Islands', 'TD' => 'Chad',
            'TF' => 'French Southern Territories', 'TG' => 'Togo', 'TH' => 'Thailand', 'TJ' => 'Tajikistan',
            'TK' => 'Tokelau', 'TL' => 'Timor-Leste', 'TM' => 'Turkmenistan', 'TN' => 'Tunisia', 'TO' => 'Tonga',
            'TR' => 'Türkiye', 'TT' => 'Trinidad and Tobago', 'TV' => 'Tuvalu', 'TW' => 'Taiwan',
            'TZ' => 'Tanzania', 'UA' => 'Ukraine', 'UG' => 'Uganda', 'UM' => 'United States Minor Outlying Islands',
            'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VA' => 'Vatican City',
            'VC' => 'Saint Vincent and the Grenadines', 'VE' => 'Venezuela', 'VG' => 'British Virgin Islands',
            'VI' => 'U.S. Virgin Islands', 'VN' => 'Vietnam', 'VU' => 'Vanuatu', 'WF' => 'Wallis and Futuna',
            'WS' => 'Samoa', 'YE' => 'Yemen', 'YT' => 'Mayotte', 'ZA' => 'South Africa', 'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];

        uasort($countries, static function (string $left, string $right): int {
            $leftSort = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $left) ?: $left;
            $rightSort = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $right) ?: $right;

            return strnatcasecmp($leftSort, $rightSort);
        });

        return $countries;
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function records(): array
    {
        $nativeNames = self::nativeNames();
        $records = [];

        foreach (self::all() as $code => $english) {
            $native = $nativeNames[$code] ?? $english;
            $records[$code] = [
                'code' => $code,
                'english' => $english,
                'native' => $native,
                'display' => strcasecmp($native, $english) === 0 ? $english : $native.' ('.$english.')',
            ];
        }

        return $records;
    }

    public static function displayName(?string $code): ?string
    {
        return self::records()[strtoupper((string) $code)]['display'] ?? null;
    }

    public static function name(?string $code): ?string
    {
        return self::all()[strtoupper((string) $code)] ?? null;
    }

    private static function nativeNames(): array
    {
        return [
            'AD' => 'Andorra', 'AE' => 'الإمارات العربية المتحدة', 'AF' => 'افغانستان',
            'AL' => 'Shqipëria', 'AM' => 'Հայաստան', 'AO' => 'Angola', 'AR' => 'Argentina',
            'AT' => 'Österreich', 'AU' => 'Australia', 'AZ' => 'Azərbaycan', 'BA' => 'Bosna i Hercegovina',
            'BD' => 'বাংলাদেশ', 'BE' => 'België / Belgique / Belgien', 'BF' => 'Burkina Faso',
            'BG' => 'България', 'BH' => 'البحرين', 'BI' => 'Uburundi', 'BJ' => 'Bénin',
            'BN' => 'Brunei Darussalam', 'BO' => 'Bolivia', 'BR' => 'Brasil', 'BT' => 'འབྲུག',
            'BW' => 'Botswana', 'BY' => 'Беларусь', 'BZ' => 'Belize', 'CA' => 'Canada',
            'CD' => 'République démocratique du Congo', 'CF' => 'République centrafricaine',
            'CG' => 'République du Congo', 'CH' => 'Schweiz / Suisse / Svizzera / Svizra',
            'CI' => "Côte d'Ivoire", 'CL' => 'Chile', 'CM' => 'Cameroun', 'CN' => '中国',
            'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CU' => 'Cuba', 'CV' => 'Cabo Verde',
            'CY' => 'Κύπρος / Kıbrıs', 'CZ' => 'Česko', 'DE' => 'Deutschland', 'DJ' => 'Djibouti',
            'DK' => 'Danmark', 'DO' => 'República Dominicana', 'DZ' => 'الجزائر', 'EC' => 'Ecuador',
            'EE' => 'Eesti', 'EG' => 'مصر', 'ER' => 'ኤርትራ', 'ES' => 'España', 'ET' => 'ኢትዮጵያ',
            'FI' => 'Suomi', 'FJ' => 'Viti', 'FR' => 'France', 'GA' => 'Gabon',
            'GB' => 'United Kingdom', 'GE' => 'საქართველო', 'GH' => 'Ghana', 'GM' => 'The Gambia',
            'GN' => 'Guinée', 'GQ' => 'Guinea Ecuatorial', 'GR' => 'Ελλάδα', 'GT' => 'Guatemala',
            'GW' => 'Guiné-Bissau', 'GY' => 'Guyana', 'HK' => '香港', 'HN' => 'Honduras',
            'HR' => 'Hrvatska', 'HT' => 'Ayiti', 'HU' => 'Magyarország', 'ID' => 'Indonesia',
            'IE' => 'Éire', 'IL' => 'ישראל', 'IN' => 'भारत', 'IQ' => 'العراق', 'IR' => 'ایران',
            'IS' => 'Ísland', 'IT' => 'Italia', 'JM' => 'Jamaica', 'JO' => 'الأردن', 'JP' => '日本',
            'KE' => 'Kenya', 'KG' => 'Кыргызстан', 'KH' => 'កម្ពុជា', 'KI' => 'Kiribati',
            'KM' => 'Komori', 'KN' => 'Saint Kitts and Nevis', 'KP' => '조선민주주의인민공화국',
            'KR' => '대한민국', 'KW' => 'الكويت', 'KZ' => 'Қазақстан', 'LA' => 'ລາວ',
            'LB' => 'لبنان', 'LK' => 'ශ්‍රී ලංකාව / இலங்கை', 'LR' => 'Liberia', 'LS' => 'Lesotho',
            'LT' => 'Lietuva', 'LU' => 'Lëtzebuerg', 'LV' => 'Latvija', 'LY' => 'ليبيا',
            'MA' => 'المغرب', 'MC' => 'Monaco', 'MD' => 'Moldova', 'ME' => 'Crna Gora',
            'MG' => 'Madagasikara', 'MH' => 'Aelōñ in M̧ajeļ', 'MK' => 'Северна Македонија',
            'ML' => 'Mali', 'MM' => 'မြန်မာ', 'MN' => 'Монгол Улс', 'MO' => '澳門',
            'MR' => 'موريتانيا', 'MT' => 'Malta', 'MU' => 'Maurice', 'MV' => 'ދިވެހިރާއްޖެ',
            'MW' => 'Malawi', 'MX' => 'México', 'MY' => 'Malaysia', 'MZ' => 'Moçambique',
            'NA' => 'Namibia', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NI' => 'Nicaragua',
            'NL' => 'Nederland', 'NO' => 'Norge', 'NP' => 'नेपाल', 'NR' => 'Naoero',
            'NZ' => 'New Zealand / Aotearoa', 'OM' => 'عُمان', 'PA' => 'Panamá', 'PE' => 'Perú',
            'PG' => 'Papua Niugini', 'PH' => 'Pilipinas', 'PK' => 'پاکستان', 'PL' => 'Polska',
            'PR' => 'Puerto Rico', 'PS' => 'فلسطين', 'PT' => 'Portugal', 'PW' => 'Belau',
            'PY' => 'Paraguay', 'QA' => 'قطر', 'RO' => 'România', 'RS' => 'Србија',
            'RU' => 'Россия', 'RW' => 'Rwanda', 'SA' => 'المملكة العربية السعودية',
            'SB' => 'Solomon Islands', 'SC' => 'Sesel', 'SD' => 'السودان', 'SE' => 'Sverige',
            'SG' => 'Singapore', 'SI' => 'Slovenija', 'SK' => 'Slovensko', 'SL' => 'Sierra Leone',
            'SM' => 'San Marino', 'SN' => 'Sénégal', 'SO' => 'Soomaaliya', 'SR' => 'Suriname',
            'SS' => 'South Sudan', 'ST' => 'São Tomé e Príncipe', 'SV' => 'El Salvador',
            'SY' => 'سوريا', 'SZ' => 'eSwatini', 'TD' => 'Tchad', 'TG' => 'Togo',
            'TH' => 'ประเทศไทย', 'TJ' => 'Тоҷикистон', 'TL' => 'Timor-Leste', 'TM' => 'Türkmenistan',
            'TN' => 'تونس', 'TO' => 'Tonga', 'TR' => 'Türkiye', 'TT' => 'Trinidad and Tobago',
            'TV' => 'Tuvalu', 'TW' => '臺灣', 'TZ' => 'Tanzania', 'UA' => 'Україна',
            'UG' => 'Uganda', 'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Oʻzbekiston',
            'VA' => 'Città del Vaticano', 'VE' => 'Venezuela', 'VN' => 'Việt Nam',
            'VU' => 'Vanuatu', 'WS' => 'Sāmoa', 'YE' => 'اليمن', 'ZA' => 'South Africa',
            'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
        ];
    }
}
