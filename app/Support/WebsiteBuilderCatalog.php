<?php

namespace App\Support;

/** Defines the stable Website Studio page and section catalog shared by UI, validation and tests. */
final class WebsiteBuilderCatalog
{
    public const PAGE_TYPES = ['home','about','contact','services','portfolio','buy','sell','careers','blog','custom'];

    public const SECTION_TYPES = [
        'hero','rich_text','image','gallery','features','stats','services','team','portfolio','testimonials','pricing','faq','form','cta','columns','divider','spacer','custom',
    ];

    /** Returns a safe starter schema for a newly created page type. */
    public static function starter(string $pageType): array
    {
        $heading = match ($pageType) {
            'home' => 'Welcome to our company',
            'about' => 'About us',
            'contact' => 'Contact us',
            'services' => 'Our services',
            'portfolio' => 'Selected work',
            'buy' => 'Buy with confidence',
            'sell' => 'Sell with confidence',
            'careers' => 'Join our team',
            'blog' => 'Latest insights',
            default => 'New page',
        };

        return [
            'schema_version' => 1,
            'sections' => [[
                'id' => 'section_'.strtolower(bin2hex(random_bytes(4))),
                'type' => 'hero',
                'settings' => [
                    'eyebrow' => '',
                    'title' => $heading,
                    'body' => 'Add clear, useful content for your visitors.',
                    'alignment' => 'left',
                    'primary_label' => 'Get started',
                    'primary_url' => '#',
                    'secondary_label' => '',
                    'secondary_url' => '',
                    'media_id' => null,
                ],
            ]],
        ];
    }

    /** Returns the default site theme and visual tokens for a new workspace website. */
    public static function theme(): array
    {
        return [
            'font_heading' => 'Inter',
            'font_body' => 'Inter',
            'background' => '#ffffff',
            'surface' => '#f7f7f8',
            'text' => '#17171c',
            'muted' => '#666673',
            'primary' => '#4f46e5',
            'radius' => 14,
            'button_radius' => 10,
            'content_width' => 1180,
            'body_size' => 16,
            'heading_scale' => 1,
            'section_spacing' => 72,
            'shadow_strength' => 1,
        ];
    }

    /** Returns a safe initial header configuration including a navigation placeholder. */
    public static function header(): array
    {
        return ['layout' => 'logo_left', 'sticky' => true, 'show_navigation' => true, 'show_language_switcher' => true, 'cta_label' => 'Contact', 'cta_url' => '/contact'];
    }

    /** Returns a safe initial footer configuration. */
    public static function footer(): array
    {
        return ['show_logo' => true, 'show_navigation' => true, 'copyright' => '© {{year}} {{company.name}}. All rights reserved.', 'columns' => []];
    }
}
