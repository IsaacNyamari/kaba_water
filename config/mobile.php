<?php

/*
|--------------------------------------------------------------------------
| HS Water Billing Android app
|--------------------------------------------------------------------------
| Served at /api/mobile/version so the app can tell users when a newer APK
| is available. Bump these after publishing a new build to /downloads.
*/

return [
    'latest_version' => env('MOBILE_APP_VERSION', '1.0.0'),
    'download_url' => env('MOBILE_APP_URL', rtrim((string) env('APP_URL'), '/').'/downloads/hs-water-billing.apk'),
];
