<?php

/*
|--------------------------------------------------------------------------
| Workspace modules
|--------------------------------------------------------------------------
| Water Billing is always on. Project Management can be switched off for the
| whole platform; only a platform admin can change it (Admin → Settings →
| Modules). When off, its pages answer 404, it disappears from the sidebar,
| module switcher and team roles, and its scheduled alerts do not run. Its
| data is kept, so switching it back on restores everything.
*/

return [
    'projects' => (bool) env('PROJECTS_MODULE_ENABLED', false),
];
