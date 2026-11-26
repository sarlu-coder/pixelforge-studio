<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PixelforgeService
{
    private const SCALE = 100000000; // 10^8 for 8-decimal USD precision
    public const MARKUP_PERCENT = 25;

    private function client(): PendingRequest
    {
        $apiKey = trim((string) config('services.pixelforge.api_key'));

        if ($apiKey === '') {
            throw new \RuntimeException('PIXELFORGE_API_KEY is not configured in .env.');
        }
        return Http::baseUrl(config('services.pixelforge.base_url'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    /**
     * Estimates Pixelforge's base cost + 25% user markup as an exact 8-decimal USD string.
     */
    public function estimateUserCost(string $prompt, string $size, string $quality, array $referenceUrls = []): string
    {
        // Pixelforge base output rates in 10^-8 USD units ($0.011, $0.042, $0.167)
        $baseUnits = match ($quality) {
            'low'    => 1100000,
            'high'   => 16700000,
            default  => 4200000,
        };

        $sizeBasisPoints = match ($size) {
            '1536x1024', '1024x1536' => 150, // 1.5x
            default => 100,                  // 1.0x
        };

        $outputUnits = intdiv($baseUnits * $sizeBasisPoints, 100);

        // Estimated prompt tokens (~1 token per 4 chars @ $0.00000500 = 500 units/token)
        $estimatedTokens = max(1, (int) ceil(mb_strlen(trim($prompt)) / 4));
        $promptUnits = $estimatedTokens * 500;

        // Reference image input cost ($0.00250000 = 250,000 units per valid URL)
        $refCount = count(array_filter($referenceUrls));
        $refUnits = $refCount * 250000;

        $providerEstimateUnits = $outputUnits + $promptUnits + $refUnits;
        $userEstimateUnits = self::applyMarkupUnits($providerEstimateUnits);

        return self::formatUnits($userEstimateUnits);
    }

    /**
     * Applies the 25% markup to Pixelforge's exact provider cost string.
     */
    public static function applyMarkupToDecimal(string $providerDecimal): string
    {
        $providerUnits = self::parseToUnits($providerDecimal);
        $userUnits = self::applyMarkupUnits($providerUnits);

        return self::formatUnits($userUnits);
    }

    public static function applyMarkupUnits(int $units): int
    {
        // 125% = units * 125 / 100 (rounded half-up to nearest 10^-8 USD)
        return intdiv(($units * (100 + self::MARKUP_PERCENT)) + 50, 100);
    }

    public static function formatUnits(int $units): string
    {
        $negative = $units < 0;
        $abs = abs($units);
        $whole = intdiv($abs, self::SCALE);
        $fraction = str_pad((string) ($abs % self::SCALE), 8, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . "{$whole}.{$fraction}";
    }

    public static function parseToUnits(?string $decimal): int
    {
        if ($decimal === null || $decimal === '') {
            return 0;
        }

        $negative = str_starts_with(trim($decimal), '-');
        $clean = ltrim(trim($decimal), '+-');
        [$whole, $frac] = array_pad(explode('.', $clean, 2), 2, '0');
        $frac = str_pad(substr($frac, 0, 8), 8, '0', STR_PAD_RIGHT);

        $units = ((int) $whole * self::SCALE) + (int) $frac;

        return $negative ? -$units : $units;
    }

    public function createGeneration(array $payload, string $idempotencyKey): array
    {
        $response = $this->client()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/generations', $payload);

        $response->throw();

        return $response->json();
    }

    public function retrieveGeneration(string $pixelforgeId): array
    {
        $response = $this->client()->get("/generations/{$pixelforgeId}");
        $response->throw();

        return $response->json();
    }

    public function getUsage(array $query = []): array
    {
        $response = $this->client()->get('/usage', $query);
        $response->throw();

        return $response->json();
    }
}