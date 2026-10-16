<?php

namespace App\Support;

/**
 * The .env settings a platform admin can edit, grouped for the settings page.
 *
 * Field types: text, email, url, number, select, bool, secret.
 * Secret values are never sent to the browser; an empty input keeps them.
 * Groups marked "danger" can take the site offline if set wrongly.
 */
final class EnvSettings
{
    /**
     * @return array<string, array{title: string, icon: string, description: string, danger?: bool, fields: array<string, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'title' => 'General',
                'icon' => 'cog-6-tooth',
                'description' => 'Platform name, address and environment.',
                'fields' => [
                    'APP_NAME' => ['label' => 'Platform name', 'type' => 'text', 'rules' => ['required', 'string', 'max:60']],
                    'APP_URL' => ['label' => 'Platform URL', 'type' => 'url', 'help' => 'The public address, e.g. https://kabawater.co.ke. Used in emails and links.', 'rules' => ['required', 'url', 'max:255']],
                    'APP_ENV' => ['label' => 'Environment', 'type' => 'select', 'options' => ['production' => 'Production', 'staging' => 'Staging', 'local' => 'Local'], 'rules' => ['required', 'in:production,staging,local']],
                    'APP_DEBUG' => ['label' => 'Debug mode', 'type' => 'bool', 'help' => 'Shows detailed errors. Keep OFF in production — it can expose secrets.'],
                    'APP_LOCALE' => ['label' => 'Language', 'type' => 'text', 'help' => 'e.g. en', 'rules' => ['required', 'alpha_dash', 'max:10']],
                ],
            ],
            'modules' => [
                'title' => 'Modules',
                'icon' => 'squares-2x2',
                'description' => 'Switch optional modules on or off for every workspace. Water Billing is always on. Switching a module off hides it and keeps its data.',
                'fields' => [
                    'PROJECTS_MODULE_ENABLED' => ['label' => 'Project Management', 'type' => 'bool', 'help' => 'Borehole projects, project suppliers and equipment. When off, its pages, menu, team roles and daily alerts are disabled.'],
                ],
            ],
            'mail' => [
                'title' => 'Mail',
                'icon' => 'envelope',
                'description' => 'How the platform sends email (invitations, invoices, contact form).',
                'fields' => [
                    'MAIL_MAILER' => ['label' => 'Mailer', 'type' => 'select', 'options' => ['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'log' => 'Log only (no sending)', 'resend' => 'Resend', 'postmark' => 'Postmark', 'ses' => 'Amazon SES'], 'rules' => ['required', 'in:smtp,sendmail,log,resend,postmark,ses']],
                    'MAIL_HOST' => ['label' => 'SMTP host', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'MAIL_PORT' => ['label' => 'SMTP port', 'type' => 'number', 'rules' => ['nullable', 'integer', 'between:1,65535']],
                    'MAIL_SCHEME' => ['label' => 'Encryption', 'type' => 'select', 'options' => ['' => 'Automatic', 'smtp' => 'None / STARTTLS', 'smtps' => 'SSL/TLS (smtps)'], 'rules' => ['nullable', 'in:smtp,smtps,null']],
                    'MAIL_USERNAME' => ['label' => 'SMTP username', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'MAIL_PASSWORD' => ['label' => 'SMTP password', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:255']],
                    'MAIL_FROM_ADDRESS' => ['label' => 'From address', 'type' => 'email', 'rules' => ['required', 'email', 'max:190']],
                    'MAIL_FROM_NAME' => ['label' => 'From name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    'MAIL_SALES_ADDRESS' => ['label' => 'Sales inbox', 'type' => 'email', 'help' => 'Where contact-form messages are delivered.', 'rules' => ['nullable', 'email', 'max:190']],
                    'RESEND_API_KEY' => ['label' => 'Resend API key', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:255']],
                    'POSTMARK_API_KEY' => ['label' => 'Postmark API key', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:255']],
                ],
            ],
            'access' => [
                'title' => 'Access requests',
                'icon' => 'user-plus',
                'description' => 'How new vendors and clients get in.',
                'fields' => [
                    'ACCESS_AUTO_APPROVE' => ['label' => 'Auto-approve safe requests', 'type' => 'bool', 'help' => 'Approve requests automatically when the email check passes.'],
                    'ACCESS_INVITE_EXPIRES_DAYS' => ['label' => 'Invitation link valid for (days)', 'type' => 'number', 'rules' => ['required', 'integer', 'between:1,60']],
                    'ACCESS_TRIAL_DAYS' => ['label' => 'Trial length for new vendors (days)', 'type' => 'number', 'rules' => ['required', 'integer', 'between:0,365']],
                ],
            ],
            'services' => [
                'title' => 'Services',
                'icon' => 'sparkles',
                'description' => 'Third-party services used by the platform.',
                'fields' => [
                    'GEMINI_API_KEY' => ['label' => 'Platform Gemini API key', 'type' => 'secret', 'help' => 'Vendors add their own key in their Settings → Integrations.', 'rules' => ['nullable', 'string', 'max:255']],
                    'GEMINI_PLATFORM_FALLBACK' => ['label' => 'Let vendors without a key use the platform key', 'type' => 'bool', 'help' => 'Off keeps each vendor on their own quota. On means their usage counts against the platform key.'],
                    'GEMINI_MODELS' => ['label' => 'Gemini models', 'type' => 'text', 'help' => 'Comma-separated, tried in order.', 'rules' => ['nullable', 'string', 'max:255']],
                ],
            ],
            'queue' => [
                'title' => 'Queue & logging',
                'icon' => 'queue-list',
                'description' => 'Background jobs (queued emails) and log output.',
                'fields' => [
                    'QUEUE_CONNECTION' => ['label' => 'Queue connection', 'type' => 'select', 'options' => ['database' => 'Database', 'redis' => 'Redis', 'sync' => 'Sync (no worker; slower requests)'], 'rules' => ['required', 'in:database,redis,sync']],
                    'LOG_CHANNEL' => ['label' => 'Log channel', 'type' => 'select', 'options' => ['stack' => 'Stack', 'single' => 'Single file', 'daily' => 'Daily files', 'stderr' => 'Stderr'], 'rules' => ['required', 'in:stack,single,daily,stderr']],
                    'LOG_LEVEL' => ['label' => 'Log level', 'type' => 'select', 'options' => ['debug' => 'Debug', 'info' => 'Info', 'warning' => 'Warning', 'error' => 'Error'], 'rules' => ['required', 'in:debug,info,warning,error']],
                ],
            ],
            'infrastructure' => [
                'title' => 'Infrastructure',
                'icon' => 'server-stack',
                'description' => 'Database, sessions, cache and Redis. A wrong value here can take the whole platform offline — change only if you know the new values work.',
                'danger' => true,
                'fields' => [
                    'DB_CONNECTION' => ['label' => 'Database driver', 'type' => 'select', 'options' => ['mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'], 'rules' => ['required', 'in:mysql,mariadb,pgsql,sqlite']],
                    'DB_HOST' => ['label' => 'Database host', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'DB_PORT' => ['label' => 'Database port', 'type' => 'number', 'rules' => ['nullable', 'integer', 'between:1,65535']],
                    'DB_DATABASE' => ['label' => 'Database name', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'DB_USERNAME' => ['label' => 'Database user', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'DB_PASSWORD' => ['label' => 'Database password', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:255']],
                    'SESSION_DRIVER' => ['label' => 'Session driver', 'type' => 'select', 'options' => ['database' => 'Database', 'file' => 'File', 'redis' => 'Redis', 'cookie' => 'Cookie'], 'rules' => ['required', 'in:database,file,redis,cookie']],
                    'SESSION_LIFETIME' => ['label' => 'Session lifetime (minutes)', 'type' => 'number', 'rules' => ['required', 'integer', 'between:5,43200']],
                    'CACHE_STORE' => ['label' => 'Cache store', 'type' => 'select', 'options' => ['database' => 'Database', 'file' => 'File', 'redis' => 'Redis', 'array' => 'Array (none)'], 'rules' => ['required', 'in:database,file,redis,array']],
                    'REDIS_HOST' => ['label' => 'Redis host', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'REDIS_PORT' => ['label' => 'Redis port', 'type' => 'number', 'rules' => ['nullable', 'integer', 'between:1,65535']],
                    'REDIS_PASSWORD' => ['label' => 'Redis password', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:255']],
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> every field, keyed by ENV name */
    public static function fields(): array
    {
        return collect(self::groups())->flatMap(fn (array $group) => $group['fields'])->all();
    }
}
