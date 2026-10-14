<?php

/*
|--------------------------------------------------------------------------
| Borehole Project Management
|--------------------------------------------------------------------------
| Lifecycle order, allowed transitions, default milestones and expense
| categories. Change these to fit a different kind of project — no code
| changes needed.
*/

return [

    /* Lifecycle stages in order (key => label). "completed" is reached only through closure. */
    'stages' => [
        'draft' => 'Draft',
        'planning' => 'Planning',
        'site_preparation' => 'Site Preparation',
        'drilling' => 'Drilling',
        'casing_installation' => 'Casing & Installation',
        'pump_installation' => 'Pump Installation',
        'testing' => 'Testing',
        'commissioning' => 'Commissioning',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ],

    /*
     * Allowed moves from each stage. "on_hold" may return to any working stage.
     * Moving to "completed" happens only via Close project (checklist enforced).
     */
    'transitions' => [
        'draft' => ['planning', 'cancelled'],
        'planning' => ['site_preparation', 'on_hold', 'cancelled'],
        'site_preparation' => ['drilling', 'planning', 'on_hold', 'cancelled'],
        'drilling' => ['casing_installation', 'on_hold', 'cancelled'],
        'casing_installation' => ['pump_installation', 'drilling', 'on_hold', 'cancelled'],
        'pump_installation' => ['testing', 'on_hold', 'cancelled'],
        'testing' => ['commissioning', 'pump_installation', 'on_hold', 'cancelled'],
        'commissioning' => ['testing', 'on_hold'],
        'on_hold' => ['planning', 'site_preparation', 'drilling', 'casing_installation', 'pump_installation', 'testing', 'commissioning', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ],

    /* Default milestone sequence for new projects: [name, weight]. */
    'milestones' => [
        ['Approval & mobilization', 5],
        ['Site survey & preparation', 5],
        ['Equipment mobilization', 5],
        ['Drilling commenced', 10],
        ['Target depth reached', 15],
        ['Casing completed', 10],
        ['Gravel packing & development', 8],
        ['Pump installation', 10],
        ['Pumping & yield tests', 8],
        ['Water quality testing', 6],
        ['Electrical & plumbing', 8],
        ['Commissioning', 5],
        ['Final handover & closure', 5],
    ],

    'expense_categories' => [
        'drilling' => 'Drilling services',
        'labour' => 'Labour & wages',
        'materials' => 'Materials & supplies',
        'fuel_transport' => 'Fuel & transport',
        'equipment_hire' => 'Equipment hire',
        'equipment_repairs' => 'Equipment repairs',
        'accommodation' => 'Accommodation & meals',
        'site_prep' => 'Site prep & excavation',
        'permits' => 'Permits & fees',
        'water_testing' => 'Water quality testing',
        'pump_electrical' => 'Pump installation & electrical',
        'security' => 'Security & logistics',
        'subcontractors' => 'Subcontractors',
        'admin' => 'Communication & admin',
        'contingency' => 'Contingency',
        'other' => 'Other',
    ],

    /* Budget utilisation alerts (percent of the current approved budget). */
    'budget_alerts' => [75, 90, 100],

    /* A single expense above this amount is flagged for extra scrutiny. */
    'expense_approval_limit' => env('PROJECT_EXPENSE_APPROVAL_LIMIT', 100000),
];
