<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use Illuminate\Http\RedirectResponse;

/** /p/{code} → the pay page it stands for. */
class ShortLinkController extends Controller
{
    public function __invoke(string $code): RedirectResponse
    {
        $link = ShortLink::where('code', $code)->firstOrFail();

        $link->increment('clicks', 1, ['last_clicked_at' => now()]);

        return redirect()->away($link->destination());
    }
}
