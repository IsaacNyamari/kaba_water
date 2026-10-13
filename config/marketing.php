<?php

/*
|--------------------------------------------------------------------------
| Public marketing site content
|--------------------------------------------------------------------------
| Everything in this file is PLACEHOLDER content: sample figures, prices,
| contact details and a sample testimonial. Replace each value with the real
| one before the site goes live. The views in resources/views/marketing read
| from here, so nothing else needs to change.
*/

return [

    'tagline' => 'Water billing for vendors who would rather be in the field than in a spreadsheet.',

    /* Contact details shown on /contact and in the footer. */
    'contact' => [
        'phone' => '+254 700 000 000',
        'email' => env('MAIL_SALES_ADDRESS', 'sales@example.com'),
        'address' => ['Kaba Water', 'Westlands', 'Nairobi, Kenya'],
        'hours' => [
            'Monday – Friday' => '8:00 am – 5:30 pm',
            'Saturday' => '9:00 am – 1:00 pm',
            'Sunday & public holidays' => 'Closed',
        ],
    ],

    'social' => [
        'x' => '#',
        'facebook' => '#',
        'linkedin' => '#',
    ],

    /* Trust strip on the home page. */
    'trust' => [
        'vendor_count' => '120+',
        'logos' => ['Mwangaza Water', 'Tana Springs', 'Kilele Estates', 'Baraka Boreholes'],
    ],

    /* Navy stats band on the home page. */
    'stats' => [
        ['value' => '120+', 'label' => 'Water vendors'],
        ['value' => '48,000', 'label' => 'Meters managed'],
        ['value' => '35,000', 'label' => 'Invoices every month'],
        ['value' => '99.9%', 'label' => 'Uptime'],
    ],

    'testimonial' => [
        'quote' => 'We used to spend the first week of every month typing readings into a spreadsheet and chasing arithmetic mistakes. Now the readings come in from the field, the invoices are out the same day, and I can see who has paid before I finish my tea.',
        'name' => 'Grace Wanjiru',
        'role' => 'Operations manager, community water project',
        'initials' => 'GW',
    ],

    /* Team grid on /about — roles only until real profiles are supplied. */
    'team' => [
        ['role' => 'Product & founder', 'focus' => 'Spends a day a month reading meters with a customer.', 'icon' => 'light-bulb'],
        ['role' => 'Engineering', 'focus' => 'Keeps billing accurate to the cent and the app fast on a weak signal.', 'icon' => 'code-bracket'],
        ['role' => 'Customer success', 'focus' => 'Sets up your tariffs, imports your clients and trains your team.', 'icon' => 'lifebuoy'],
        ['role' => 'Finance & compliance', 'focus' => 'Makes sure the reports are ones your accountant will accept.', 'icon' => 'scale'],
    ],

    /*
    | Plans. Prices are in KES. `annual` is the price for a full year
    | (two months free); null means "talk to us".
    */
    'plans' => [
        [
            'slug' => 'starter',
            'name' => 'Starter',
            'blurb' => 'For a single borehole or a small estate.',
            'monthly' => 2500,
            'annual' => 25000,
            'limit' => 'Up to 150 meters',
            'featured' => false,
            'cta' => 'Start free',
            'features' => [
                'Up to 150 meters and 2 staff users',
                'Meter readings with photo capture',
                'Invoices, tariffs and PDF bills',
                'Payment recording and receipts',
                'Client portal',
            ],
        ],
        [
            'slug' => 'growth',
            'name' => 'Growth',
            'blurb' => 'For growing vendors with a team in the field.',
            'monthly' => 6500,
            'annual' => 65000,
            'limit' => 'Up to 1,000 meters',
            'featured' => true,
            'cta' => 'Start free',
            'features' => [
                'Up to 1,000 meters and 10 staff users',
                'Everything in Starter',
                'Expenses, suppliers and work orders',
                'Income statement, balance sheet and cash flow',
                'Double-entry ledger and accountant exports',
            ],
        ],
        [
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'blurb' => 'For utilities and multi-site operators.',
            'monthly' => null,
            'annual' => null,
            'limit' => 'Unlimited meters',
            'featured' => false,
            'cta' => 'Talk to sales',
            'features' => [
                'Unlimited meters and staff users',
                'Everything in Growth',
                'Guided data migration',
                'Priority support with a named contact',
                'Custom onboarding and training',
            ],
        ],
    ],

    /* Feature comparison on /pricing: label => [starter, growth, enterprise]. */
    'comparison' => [
        'Billing' => [
            'Meters' => ['150', '1,000', 'Unlimited'],
            'Staff users' => ['2', '10', 'Unlimited'],
            'Tiered tariffs' => [true, true, true],
            'PDF invoices' => [true, true, true],
            'Photo reading capture' => [true, true, true],
        ],
        'Getting paid' => [
            'Payment recording (M-Pesa, cash, bank)' => [true, true, true],
            'Client portal' => [true, true, true],
            'Overdue tracking' => [true, true, true],
            'Bad-debt write-offs' => [false, true, true],
        ],
        'Finance' => [
            'Expenses and suppliers' => [false, true, true],
            'Assets and work orders' => [false, true, true],
            'Income statement and balance sheet' => [false, true, true],
            'Double-entry ledger and fiscal periods' => [false, true, true],
            'Excel exports for your accountant' => [false, true, true],
        ],
        'Support' => [
            'Email support' => [true, true, true],
            'Guided data migration' => [false, false, true],
            'Named account contact' => [false, false, true],
        ],
    ],

    'faqs' => [
        [
            'q' => 'How do I get started?',
            'a' => 'Request access with your business email. Once your workspace is approved you receive a sign-up link, and you can add your first clients and meters straight away.',
        ],
        [
            'q' => 'Can I bring my existing clients and balances?',
            'a' => 'Yes. You can add clients and meters yourself, and the opening balances wizard records what customers already owe you on the day you start. On the Enterprise plan we do the migration with you.',
        ],
        [
            'q' => 'Does Kaba Water work with M-Pesa?',
            'a' => 'You record M-Pesa payments against an invoice with the transaction code, alongside cash and bank payments. The invoice balance and the client statement update immediately.',
        ],
        [
            'q' => 'What happens if I go over my meter limit?',
            'a' => 'Nothing breaks. We will let you know you are close to the limit and help you move to the next plan when it makes sense.',
        ],
        [
            'q' => 'Can my accountant use it?',
            'a' => 'Yes. Invite them with the accountant role. They see the ledger, the financial statements and the exports, without access to day-to-day operations.',
        ],
        [
            'q' => 'Can I cancel?',
            'a' => 'Any time. Monthly plans stop at the end of the month you have paid for, and you can export your data before you leave.',
        ],
    ],
];
