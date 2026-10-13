<?php

namespace App\Actions\AccessRequests;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use App\Notifications\AccessRequestSubmitted;
use App\Services\EmailDeliverabilityChecker;
use Illuminate\Support\Facades\Notification;

/**
 * Store a new access request, run the automatic email check and either
 * auto-approve it (when enabled and safe) or notify the admins.
 */
class SubmitAccessRequest
{
    public function __construct(
        protected EmailDeliverabilityChecker $checker,
        protected ApproveAccessRequest $approve,
    ) {}

    /** @param array<string, mixed> $data validated form data */
    public function handle(array $data, ?string $ip = null): AccessRequest
    {
        $email = AccessRequest::normalizeEmail($data['email']);

        [$result, $detail] = $this->checker->check($email);

        $request = AccessRequest::create([
            'name' => trim($data['name']),
            'email' => $email,
            'phone' => $data['phone'] ?: null,
            'organization' => $data['organization'] ?: null,
            'requested_type' => $data['requested_type'],
            'provider_name' => $data['provider_name'] ?: null,
            'account_reference' => $data['account_reference'] ?: null,
            'message' => $data['message'] ?: null,
            'ip_address' => $ip,
            'email_check' => $result,
            'email_check_detail' => $detail,
            'status' => AccessRequestStatus::Pending,
        ]);

        if ($this->shouldAutoApprove($request)) {
            try {
                // Vendor request + real-looking email + feature on → new trial workspace.
                $this->approve->handle($request, role: 'vendor', vendorId: null, clientId: null, reviewer: null, auto: true);

                return $request->fresh();
            } catch (\Throwable $e) {
                // e.g. the workspace email is taken, or mail is down — fall back to a human.
                report($e);
            }
        }

        rescue(fn () => Notification::send(
            User::role('super_admin')->get(),
            new AccessRequestSubmitted($request),
        ));

        return $request;
    }

    protected function shouldAutoApprove(AccessRequest $request): bool
    {
        return config('access.auto_approve')
            && $request->requested_type === AccessRequest::TYPE_VENDOR
            && filled($request->organization)
            && $request->email_check?->passed();
    }
}
