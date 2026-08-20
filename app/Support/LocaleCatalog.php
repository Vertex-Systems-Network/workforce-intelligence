<?php

namespace App\Support;

/** Provides locale catalog behavior within the WorkIntel application. */ final class LocaleCatalog
{
    public const CORE = ['en','tr','ru','ur','ar'];
    public const SUPPORTED = ['en','tr','ru','ur','ar','de','fr','es','it','pt'];
    public const RTL = ['ur','ar'];

    public const LABELS = [
        'en' => 'English', 'tr' => 'Türkçe', 'ru' => 'Русский', 'ur' => 'اردو', 'ar' => 'العربية',
        'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano', 'pt' => 'Português',
    ];

    public const INTL = [
        'en'=>'en-US','tr'=>'tr-TR','ru'=>'ru-RU','ur'=>'ur-PK','ar'=>'ar','de'=>'de-DE',
        'fr'=>'fr-FR','es'=>'es-ES','it'=>'it-IT','pt'=>'pt-PT',
    ];

    /** Handles the normalize operation for the current WorkIntel workflow. */ public static function normalize(?string $locale, string $fallback='en'): string
    {
        $locale = strtolower(str_replace('_','-',trim((string)$locale)));
        $short = explode('-', $locale)[0] ?: $fallback;
        return in_array($short,self::SUPPORTED,true) ? $short : $fallback;
    }

    /** Handles the direction operation for the current WorkIntel workflow. */ public static function direction(?string $locale): string
    {
        return in_array(self::normalize($locale), self::RTL, true) ? 'rtl' : 'ltr';
    }

    /** Handles the intl operation for the current WorkIntel workflow. */ public static function intl(?string $locale): string
    {
        $code = self::normalize($locale);
        return self::INTL[$code] ?? 'en-US';
    }

    /** Handles the options operation for the current WorkIntel workflow. */ public static function options(): array
    {
        return array_map(fn(string $code) => [
            'code'=>$code,
            'label'=>self::LABELS[$code],
            'direction'=>self::direction($code),
            'intl'=>self::intl($code),
            'core'=>in_array($code,self::CORE,true),
        ], self::SUPPORTED);
    }
}
