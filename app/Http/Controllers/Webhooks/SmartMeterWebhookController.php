<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Metering\TelemetryIngest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Readings pushed by smart meters — from a LoRaWAN network server
 * (ChirpStack, The Things Stack) or a meter maker's head-end system.
 * The vendor's secret URL token identifies the workspace.
 */
class SmartMeterWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, TelemetryIngest $ingest): JsonResponse
    {
        $integration = Integration::withoutGlobalScope('vendor')
            ->where('type', Integration::SMART_METERS)
            ->where('webhook_token', $token)
            ->where('is_enabled', true)
            ->firstOrFail();

        app()->instance('current_vendor_id', $integration->vendor_id);

        // ChirpStack also posts join / status / ack events to the same URL; only uplinks carry readings.
        if ($request->query('event') && $request->query('event') !== 'up') {
            return response()->json(['accepted' => 0, 'ignored' => $request->query('event')]);
        }

        return response()->json($ingest->ingest($integration, $request->json()->all()));
    }
}
