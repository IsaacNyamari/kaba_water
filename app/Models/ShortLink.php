<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * A short, unguessable code (/p/{code}) that opens a pay page: an invoice's
 * M-Pesa pay page or a prepaid meter's buy-token page. One code per
 * invoice / meter, reused every time the link is sent.
 */
class ShortLink extends Model
{
    public const KINDS = ['invoice' => 'pay.invoice', 'token' => 'pay.token'];

    protected $fillable = ['vendor_id', 'code', 'kind', 'subject_id', 'clicks', 'last_clicked_at'];

    protected $casts = ['last_clicked_at' => 'datetime'];

    /** The short URL for an invoice or meter, created on first use. */
    public static function urlFor(string $kind, int $subjectId, int $vendorId): string
    {
        $link = static::firstOrCreate(
            ['kind' => $kind, 'subject_id' => $subjectId],
            ['vendor_id' => $vendorId, 'code' => static::newCode()],
        );

        return route('short-link', $link->code);
    }

    /** The full (signed) page the code stands for, built fresh on each visit. */
    public function destination(): string
    {
        return URL::signedRoute(self::KINDS[$this->kind], $this->subject_id);
    }

    /** 7 lowercase letters and digits (~27 billion combinations) without look-alikes — lowercase so it works in case-insensitive databases. */
    protected static function newCode(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';

        do {
            $code = '';
            for ($i = 0; $i < 7; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
