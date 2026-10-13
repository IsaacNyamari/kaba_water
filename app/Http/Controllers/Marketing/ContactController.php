<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('marketing.contact');
    }

    /**
     * Store the enquiry, then notify the sales inbox. The message is saved
     * first so a mail outage never loses it.
     */
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $message = ContactMessage::create($request->safe()->only(['name', 'email', 'company', 'message']));

        rescue(fn () => Mail::to(config('mail.sales'))->send(new ContactMessageReceived($message)));

        return redirect()
            ->to(route('marketing.contact').'#contact-form')
            ->with('contact_sent', true);
    }
}
