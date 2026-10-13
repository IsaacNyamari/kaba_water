<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-approve access requests
    |--------------------------------------------------------------------------
    | When true, a VENDOR access request whose email passes the automatic check
    | (well-formed, domain accepts mail, not a disposable provider) is approved
    | straight away and the signup link is emailed without waiting for an admin.
    | Requests that fail the check — and all CLIENT requests, which must be
    | linked to an existing customer account — always wait for a human.
    |
    | Off by default: with it on, anyone with a working inbox can open a trial
    | vendor workspace.
    */
    'auto_approve' => (bool) env('ACCESS_AUTO_APPROVE', false),

    /* How long an emailed signup link stays valid. */
    'invite_expires_days' => (int) env('ACCESS_INVITE_EXPIRES_DAYS', 7),

    /* Trial length for workspaces created from an approved vendor request. */
    'trial_days' => (int) env('ACCESS_TRIAL_DAYS', 30),

    /* Throw-away email providers that never pass the automatic check. */
    'disposable_domains' => [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com',
        '10minutemail.com', '10minutemail.net', 'temp-mail.org', 'tempmail.com',
        'tempmailo.com', 'yopmail.com', 'yopmail.net', 'trashmail.com', 'getnada.com',
        'nada.email', 'dispostable.com', 'maildrop.cc', 'mailnesia.com', 'mintemail.com',
        'throwawaymail.com', 'fakeinbox.com', 'emailondeck.com', 'mohmal.com',
        'burnermail.io', 'mail.tm', 'moakt.com', 'spamgourmet.com', 'discard.email',
    ],
];
