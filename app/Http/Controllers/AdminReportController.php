<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\User;
use App\Services\PixelforgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminReportController extends Controller
{
    public function index(Request $request, PixelforgeService $pixelforge)
    {
        abort_unless($request->user()?->is_admin, 403);

        $generations = Generation::with('user:id,name,email,credit_balance')
            ->whereIn('status', ['succeeded', 'failed'])
            ->orderByDesc('created_at')
            ->get();

        $byDay = [];
        $byUser = [];

        $totalCreditSpendUnits = 0;
        $totalProviderCostUnits = 0;

        foreach ($generations as $gen) {
            $day = $gen->created_at->format('Y-m-d');
            $userId = $gen->user_id;

            // Failed generations cost $0.00 for both user and provider
            $creditSpendUnits = $gen->status === 'failed'
                ? 0
                : PixelforgeService::parseToUnits((string) ($gen->actual_cost ?? '0'));

            $providerCostUnits = $gen->status === 'failed'
                ? 0
                : PixelforgeService::parseToUnits((string) ($gen->provider_cost ?? '0'));

            $profitUnits = $creditSpendUnits - $providerCostUnits;

            $totalCreditSpendUnits += $creditSpendUnits;
            $totalProviderCostUnits += $providerCostUnits;

            if (!isset($byDay[$day])) {
                $byDay[$day] = [
                    'date' => $day,
                    'credit_spend_units' => 0,
                    'provider_cost_units' => 0,
                    'profit_units' => 0,
                    'generations_count' => 0,
                ];
            }
            $byDay[$day]['credit_spend_units'] += $creditSpendUnits;
            $byDay[$day]['provider_cost_units'] += $providerCostUnits;
            $byDay[$day]['profit_units'] += $profitUnits;
            $byDay[$day]['generations_count']++;

            if (!isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'user_id' => $userId,
                    'name' => $gen->user?->name ?? 'Unknown',
                    'email' => $gen->user?->email ?? '',
                    'credit_balance' => $gen->user?->credit_balance ?? '0.00000000',
                    'credit_spend_units' => 0,
                    'provider_cost_units' => 0,
                    'profit_units' => 0,
                    'generations_count' => 0,
                ];
            }
            $byUser[$userId]['credit_spend_units'] += $creditSpendUnits;
            $byUser[$userId]['provider_cost_units'] += $providerCostUnits;
            $byUser[$userId]['profit_units'] += $profitUnits;
            $byUser[$userId]['generations_count']++;
        }

        $formatRow = fn (array $row) => [
            ...$row,
            'credit_spend' => PixelforgeService::formatUnits($row['credit_spend_units']),
            'provider_cost' => PixelforgeService::formatUnits($row['provider_cost_units']),
            'profit' => PixelforgeService::formatUnits($row['profit_units']),
        ];

        $providerUsage = null;
        $providerUsageError = null;

        try {
            $providerUsage = $pixelforge->getUsage();
        } catch (\Throwable $e) {
            $providerUsageError = $e->getMessage();
        }

        return Inertia::render('Admin/Report', [
            'summary' => [
                'credit_spend' => PixelforgeService::formatUnits($totalCreditSpendUnits),
                'provider_cost' => PixelforgeService::formatUnits($totalProviderCostUnits),
                'profit' => PixelforgeService::formatUnits($totalCreditSpendUnits - $totalProviderCostUnits),
            ],
            'dailyRows' => array_values(array_map($formatRow, $byDay)),
            'userRows' => array_values(array_map($formatRow, $byUser)),
            'providerUsage' => $providerUsage,
            'providerUsageError' => $providerUsageError,
        ]);
    }
}