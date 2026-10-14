<?php

namespace App\Support\Seo;

/**
 * The public pages that search engines may index, with their default SEO.
 * Admin → Settings → SEO overrides any of these per page; anything left
 * blank there falls back to the values here.
 */
final class SeoRegistry
{
    /**
     * @return array<string, array{label: string, view: ?string, title: string, description: string, keywords: string, priority: float, changefreq: string, noindex?: bool}>
     */
    public static function pages(): array
    {
        return [
            'home' => [
                'label' => 'Home',
                'view' => 'marketing.home',
                'title' => 'Water billing software for vendors',
                'description' => 'HS Water Billing turns meter readings into invoices and payments. Built for small water companies, borehole operators and estate managers in Kenya.',
                'keywords' => 'water billing software, water billing system Kenya, borehole billing, meter reading app, water vendor software',
                'priority' => 1.0,
                'changefreq' => 'weekly',
            ],
            'marketing.services' => [
                'label' => 'Services & features',
                'view' => 'marketing.services',
                'title' => 'Services & features',
                'description' => 'Meter readings, photo capture, tiered tariffs, invoicing, payments, a client portal and full financial reports. See everything HS Water Billing does for a water vendor.',
                'keywords' => 'meter reading, water tariffs, water invoicing, client portal, water utility reports',
                'priority' => 0.9,
                'changefreq' => 'monthly',
            ],
            'marketing.pricing' => [
                'label' => 'Pricing',
                'view' => 'marketing.pricing',
                'title' => 'Pricing',
                'description' => 'Simple pricing in Kenya shillings, by the number of meters you manage. Starter, Growth and Enterprise plans with no setup fee.',
                'keywords' => 'water billing pricing, water billing software cost Kenya, KES plans',
                'priority' => 0.9,
                'changefreq' => 'monthly',
            ],
            'marketing.about' => [
                'label' => 'About',
                'view' => 'marketing.about',
                'title' => 'About',
                'description' => 'Human Solutions builds billing software for the small water companies, borehole operators and estate managers who keep neighbourhoods supplied.',
                'keywords' => 'Human Solutions, HumSols, software company Nairobi',
                'priority' => 0.7,
                'changefreq' => 'monthly',
            ],
            'marketing.contact' => [
                'label' => 'Contact',
                'view' => 'marketing.contact',
                'title' => 'Contact',
                'description' => 'Talk to the Human Solutions team. Book a demo, ask about pricing, or get help moving your clients and meters across.',
                'keywords' => 'contact Human Solutions, book a demo, water billing support',
                'priority' => 0.7,
                'changefreq' => 'yearly',
            ],
            'access.request' => [
                'label' => 'Request access',
                'view' => null,
                'title' => 'Request access',
                'description' => 'Request a HS Water Billing workspace for your water business. Approved requests get a sign-up link by email.',
                'keywords' => 'water billing free trial, sign up water billing',
                'priority' => 0.6,
                'changefreq' => 'yearly',
            ],
            'marketing.terms' => [
                'label' => 'Terms of service',
                'view' => 'marketing.terms',
                'title' => 'Terms of service',
                'description' => 'The terms that apply when you use HS Water Billing to bill your water customers.',
                'keywords' => '',
                'priority' => 0.3,
                'changefreq' => 'yearly',
            ],
            'marketing.privacy' => [
                'label' => 'Privacy policy',
                'view' => 'marketing.privacy',
                'title' => 'Privacy policy',
                'description' => 'What personal information Human Solutions collects, why, and the choices you have.',
                'keywords' => '',
                'priority' => 0.3,
                'changefreq' => 'yearly',
            ],
            'login' => [
                'label' => 'Sign in',
                'view' => null,
                'title' => 'Log in',
                'description' => 'Sign in to HS Water Billing.',
                'keywords' => '',
                'priority' => 0.2,
                'changefreq' => 'yearly',
                'noindex' => true,
            ],
        ];
    }

    public static function has(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::pages());
    }

    /** @return array{label: string, view: ?string, title: string, description: string, keywords: string, priority: float, changefreq: string, noindex?: bool}|null */
    public static function page(?string $key): ?array
    {
        return self::pages()[$key] ?? null;
    }
}
