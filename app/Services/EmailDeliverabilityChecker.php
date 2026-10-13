<?php

namespace App\Services;

use App\Enums\EmailCheckResult;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Best-effort "is this a real email address?" check, run automatically when an
 * access request arrives.
 *
 *  1. RFC syntax.
 *  2. Not a known throw-away provider (config/access.php).
 *  3. The domain publishes MX records (or at least an A record), i.e. it can
 *     receive mail at all.
 *
 * This cannot prove the specific mailbox exists — that would need an SMTP
 * probe, which most providers block or answer falsely. The emailed signup link
 * is what finally proves the person controls the inbox.
 */
class EmailDeliverabilityChecker
{
    /** @return array{0: EmailCheckResult, 1: string} */
    public function check(string $email): array
    {
        $email = Str::lower(trim($email));

        if (Validator::make(['email' => $email], ['email' => 'required|email:rfc,strict'])->fails()) {
            return [EmailCheckResult::Invalid, 'Not a valid email address.'];
        }

        $domain = Str::after($email, '@');

        if ($this->isDisposable($domain)) {
            return [EmailCheckResult::Disposable, "{$domain} is a throw-away email provider."];
        }

        if ($this->hasMx($domain)) {
            return [EmailCheckResult::Deliverable, "{$domain} accepts mail (MX record found)."];
        }

        if ($this->hasA($domain)) {
            // Technically deliverable via the A record, but real mail domains
            // almost always publish MX — treat as suspicious and let a human look.
            return [EmailCheckResult::NoMailServer, "{$domain} has no MX record (only an A record)."];
        }

        return [EmailCheckResult::NoMailServer, "{$domain} does not exist or cannot receive mail."];
    }

    protected function isDisposable(string $domain): bool
    {
        $list = array_map('strtolower', config('access.disposable_domains', []));

        foreach ($list as $blocked) {
            if ($domain === $blocked || Str::endsWith($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    protected function hasMx(string $domain): bool
    {
        return function_exists('checkdnsrr') && @checkdnsrr($domain.'.', 'MX');
    }

    protected function hasA(string $domain): bool
    {
        return function_exists('checkdnsrr')
            && (@checkdnsrr($domain.'.', 'A') || @checkdnsrr($domain.'.', 'AAAA'));
    }
}
