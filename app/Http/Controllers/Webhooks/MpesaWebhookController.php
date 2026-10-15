<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Mpesa\MpesaReconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use function Illuminate\Support\defer;

/**
 * Safaricom Daraja callbacks. Each vendor has its own secret URL token, so
 * the URL alone says whose Paybill the money went to. Safaricom is answered
 * at once; reconciliation (invoices, tokens, SMS) runs after the response.
 */
class MpesaWebhookController extends Controller
{
    /** C2B validation: accept every payment — unknown accounts go to suspense rather than bouncing the customer. */
    public function validation(string $token): JsonResponse
    {
        $this->integration($token);

        return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
    }

    public function confirmation(Request $request, string $token, MpesaReconciler $reconciler): JsonResponse
    {
        $integration = $this->integration($token);
        $payload = $request->json()->all();

        defer(fn () => $reconciler->recordC2b($integration, $payload));

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    public function stk(Request $request, string $token, MpesaReconciler $reconciler): JsonResponse
    {
        $integration = $this->integration($token);
        $payload = $request->json()->all();

        defer(fn () => $reconciler->recordStkCallback($integration, $payload));

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    protected function integration(string $token): Integration
    {
        $integration = Integration::withoutGlobalScope('vendor')
            ->where('type', Integration::MPESA)
            ->where('webhook_token', $token)
            ->where('is_enabled', true)
            ->firstOrFail();

        // Scope every model touched while handling this callback to the vendor.
        app()->instance('current_vendor_id', $integration->vendor_id);
        $integration->forceFill(['last_used_at' => now()])->save();

        return $integration;
    }
}
