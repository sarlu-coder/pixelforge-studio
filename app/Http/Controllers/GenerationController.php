<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\PixelforgeService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GenerationController extends Controller
{
    private const STATUS_RANK = [
        'queued'     => 1,
        'processing' => 2,
        'succeeded'  => 3,
        'failed'     => 3,
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $generations = Generation::where('user_id', $user->id)
            ->latest()
            ->take(50)
            ->get();

        // Total settled user spend (failed jobs cost $0.00000000)
        $totalUnits = Generation::where('user_id', $user->id)
            ->where('status', '!=', 'failed')
            ->get(['actual_cost', 'estimated_cost'])
            ->sum(fn (Generation $g) => PixelforgeService::parseToUnits(
                $g->actual_cost ?? $g->estimated_cost
            ));

        return Inertia::render('Studio', [
            'generations' => $generations,
            'totalSpend' => PixelforgeService::formatUnits($totalUnits),
            'pricingConfig' => [
                'markupPercent' => PixelforgeService::MARKUP_PERCENT,
                'baseRates' => [
                    'low'    => '0.01100000',
                    'medium' => '0.04200000',
                    'high'   => '0.16700000',
                ],
                'sizeMultipliers' => [
                    '1024x1024' => '1.00000000',
                    '1536x1024' => '1.50000000',
                    '1024x1536' => '1.50000000',
                ],
                'tokenRate' => '0.00000500',
                'referenceImageRate' => '0.00250000',
            ],
        ]);
    }

    public function store(Request $request, PixelforgeService $pixelforge)
    {
        $rawRefs = array_values(array_filter(
            $request->input('reference_urls', []),
            fn ($url) => is_string($url) && trim($url) !== ''
        ));
        $request->merge(['reference_urls' => $rawRefs]);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:2000'],
            'size' => ['required', 'in:1024x1024,1536x1024,1024x1536'],
            'quality' => ['required', 'in:low,medium,high'],
            'reference_urls' => ['nullable', 'array', 'max:4'],
            'reference_urls.*' => ['required', 'url', 'max:2048'],
        ]);

        $refUrls = $validated['reference_urls'] ?? [];
        $estimatedUserCost = $pixelforge->estimateUserCost(
            $validated['prompt'],
            $validated['size'],
            $validated['quality'],
            $refUrls
        );

        $idempotencyKey = (string) Str::uuid();

        $generation = Generation::create([
            'user_id' => $request->user()->id,
            'idempotency_key' => $idempotencyKey,
            'prompt' => $validated['prompt'],
            'size' => $validated['size'],
            'quality' => $validated['quality'],
            'reference_urls' => $refUrls,
            'status' => 'queued',
            'estimated_cost' => $estimatedUserCost,
        ]);

        try {
            $payload = [
                'prompt' => $validated['prompt'],
                'size' => $validated['size'],
                'quality' => $validated['quality'],
            ];

            if (!empty($refUrls)) {
                $payload['reference_images'] = $refUrls;
            }

            $apiResponse = $pixelforge->createGeneration($payload, $idempotencyKey);

            // Apply state safely in case webhook arrived before HTTP response returned!
            DB::transaction(function () use ($generation, $apiResponse) {
                $locked = Generation::where('id', $generation->id)->lockForUpdate()->first();
                $locked->pixelforge_id = $apiResponse['id'] ?? $locked->pixelforge_id;
                $locked->save();

                $this->applyProviderState($locked, $apiResponse);
            });
        } catch (\Throwable $e) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'provider_cost' => '0.00000000',
                'actual_cost' => '0.00000000',
                'completed_at' => now(),
            ]);

            return back()->withErrors(['prompt' => 'Pixelforge API error: ' . $e->getMessage()]);
        }

        return back();
    }

    public function sync(Request $request, Generation $generation, PixelforgeService $pixelforge)
    {
        abort_unless($generation->user_id === $request->user()->id, 403);

        if ($generation->isTerminal() || !$generation->pixelforge_id) {
            return response()->json($generation);
        }

        $data = $pixelforge->retrieveGeneration($generation->pixelforge_id);

        DB::transaction(function () use ($generation, $data) {
            $locked = Generation::where('id', $generation->id)->lockForUpdate()->first();
            $this->applyProviderState($locked, $data);
        });

        return response()->json($generation->fresh());
    }

    /**
     * Receives Pixelforge webhooks, verifies HMAC signature, deduplicates evt_ IDs,
     * and handles out-of-order delivery safely.
     */
    public function webhook(Request $request)
    {
        if (!$this->verifyWebhookSignature($request)) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        $payload = $request->all();
        $eventId = $payload['id'] ?? $request->header('X-Pixelforge-Event-Id');
        $eventType = $payload['type'] ?? null;
        $genData = $payload['data']['object'] ?? $payload['data'] ?? $payload;
        $pixelforgeId = $genData['id'] ?? null;

        try {
            DB::transaction(function () use ($eventId, $eventType, $pixelforgeId, $genData) {
                // 1. Record event ID once — duplicate deliveries hit UNIQUE constraint and abort cleanly
                if ($eventId) {
                    if (WebhookEvent::where('event_id', $eventId)->exists()) {
                        return;
                    }

                    WebhookEvent::create([
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'pixelforge_id' => $pixelforgeId,
                        'processed_at' => now(),
                    ]);
                }

                // 2. Lock generation row and apply monotonic state transition
                if ($pixelforgeId) {
                    $generation = Generation::where('pixelforge_id', $pixelforgeId)
                        ->lockForUpdate()
                        ->first();

                    if ($generation) {
                        $this->applyProviderState($generation, $genData);
                    }
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent duplicate delivery of the same evt_ ID — safely ignore
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Monotonic state transition: prevents older delayed webhooks from overwriting newer states.
     */
    private function applyProviderState(Generation $generation, array $data): void
    {
        $incomingStatus = $data['status'] ?? $generation->status;
        $currentRank = self::STATUS_RANK[$generation->status] ?? 0;
        $incomingRank = self::STATUS_RANK[$incomingStatus] ?? 0;

        // Ignore stale / out-of-order events (e.g. 'processing' arriving after 'succeeded' or 'failed')
        if ($incomingRank < $currentRank) {
            return;
        }

        // If already terminal, never allow a different terminal state to overwrite it
        if ($generation->isTerminal() && $generation->status !== $incomingStatus) {
            return;
        }

        $imageUrl = $data['output']['url']
            ?? $data['images'][0]['url']
            ?? $data['image_url']
            ?? $generation->image_url;

        $rawProviderCost = $data['cost']
            ?? $data['usage']['total_cost']
            ?? null;

        $providerCost = $generation->provider_cost;
        $actualUserCost = $generation->actual_cost;

        if ($incomingStatus === 'failed') {
            // Failed generations cost the user nothing
            $providerCost = '0.00000000';
            $actualUserCost = '0.00000000';
        } elseif ($rawProviderCost !== null) {
            // Apply 25% markup to Pixelforge's exact cost
            $providerCost = (string) $rawProviderCost;
            $actualUserCost = PixelforgeService::applyMarkupToDecimal($providerCost);
        }

        $generation->update([
            'status' => $incomingStatus,
            'image_url' => $imageUrl,
            'provider_cost' => $providerCost,
            'actual_cost' => $actualUserCost,
            'error_message' => $data['error']['message'] ?? $generation->error_message,
            'provider_updated_at' => isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : now(),
            'completed_at' => in_array($incomingStatus, ['succeeded', 'failed'], true)
                ? ($generation->completed_at ?? now())
                : null,
        ]);
    }

    /**
     * Verifies HMAC-SHA256 webhook signature against PIXELFORGE_WEBHOOK_SECRET.
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        \Illuminate\Support\Facades\Log::warning('Pixelforge webhook signature mismatch', [
            'headers' => $request->headers->all(),
            'has_secret' => !empty(config('services.pixelforge.webhook_secret')),
        ]);
        
        $secret = trim((string) config('services.pixelforge.webhook_secret'));
        if ($secret === '') {
            return true;
        }

        $header = $request->header('X-Pixelforge-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('Webhook-Signature');

        if (!$header) {
            \Illuminate\Support\Facades\Log::warning('Webhook missing signature header', [
                'headers' => array_keys($request->headers->all()),
            ]);
            return false;
        }

        $rawBody = $request->getContent();
        $expectedRawHmac = hash_hmac('sha256', $rawBody, $secret);

        // Handle "t=<timestamp>,v1=<hex>" (Standard / Stripe-style webhook signature)
        if (str_contains($header, 'v1=')) {
            parse_str(str_replace(',', '&', $header), $parts);
            $timestamp = trim((string) ($parts['t'] ?? ''));
            $sig = trim((string) ($parts['v1'] ?? ''));

            $expectedWithTs = $timestamp !== ''
                ? hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret)
                : $expectedRawHmac;

            return hash_equals($expectedWithTs, $sig) || hash_equals($expectedRawHmac, $sig);
        }

        // Handle "sha256=<hex>" or raw "<hex>"
        $sig = trim(str_starts_with($header, 'sha256=') ? substr($header, 7) : $header);

        // Also check separate timestamp header if Pixelforge sends X-Pixelforge-Timestamp
        $timestampHeader = $request->header('X-Pixelforge-Timestamp') ?? $request->header('X-Webhook-Timestamp');
        if ($timestampHeader) {
            $expectedWithTsHeader = hash_hmac('sha256', "{$timestampHeader}.{$rawBody}", $secret);
            if (hash_equals($expectedWithTsHeader, $sig)) {
                return true;
            }
        }

        return hash_equals($expectedRawHmac, $sig);
    }
}