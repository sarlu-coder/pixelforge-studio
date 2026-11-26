<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Services\PixelforgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    public function topUp(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.10', 'max:1000'],
        ]);

        $topUpUnits = PixelforgeService::parseToUnits((string) $validated['amount']);
        $topUpDecimal = PixelforgeService::formatUnits($topUpUnits);

        DB::transaction(function () use ($request, $topUpUnits, $topUpDecimal) {
            $user = $request->user()->lockForUpdate()->find($request->user()->id);

            $currentUnits = PixelforgeService::parseToUnits((string) $user->credit_balance);
            $newUnits = $currentUnits + $topUpUnits;
            $newBalanceDecimal = PixelforgeService::formatUnits($newUnits);

            $user->update(['credit_balance' => $newBalanceDecimal]);

            CreditTransaction::create([
                'user_id' => $user->id,
                'generation_id' => null,
                'type' => 'topup',
                'amount' => $topUpDecimal,
                'balance_after' => $newBalanceDecimal,
                'description' => 'Simulated credit purchase (+$' . $topUpDecimal . ')',
            ]);
        });

        return back();
    }
}