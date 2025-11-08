<?php
// app/Http/Controllers/Api/CardController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\JWTGuard;

/**
 * @method void middleware(array|string $middleware, array $options = [])
 */
class CardController extends Controller
{
    public function __construct()
    {
        // JWT-auth middleware
        $this->middleware('auth:api');
    }

    /**
     * Get card by QR code.
     *
     * @param Request $request
     * @param string $qrCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByQrCode(Request $request, string $qrCode)
    {
        /** @var JWTGuard $auth */
        $auth = auth('api');
        $worker = $auth->user();

        // Rate limiting
        $key = 'card-lookup:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        // Fetch card from cache or database
        $card = Cache::remember("card:qr:{$qrCode}", 300, function () use ($qrCode) {
            return Card::with(['station', 'station.organization'])
                ->byQrCode($qrCode)
                ->active()
                ->first();
        });

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found or inactive',
            ], 404);
        }

        // Check if card belongs to worker's station
        if ($card->station_id !== $worker->station_id) {
            return response()->json([
                'success' => false,
                'message' => 'Card does not belong to your station',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'card' => [
                    'id' => $card->id,
                    'card_number' => $card->card_number,
                    'qr_code' => $card->qr_code,
                    'family_name' => $card->family_name,
                    'phone' => $card->phone,
                    'balance' => $card->balance,
                    'status' => $card->status,
                    'station_name' => $card->station->name,
                    'last_transaction' => optional($card->transactions()->latest()->first())->created_at?->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Search card by number.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchByNumber(Request $request)
    {
        $request->validate([
            'card_number' => 'required|string',
        ]);

        /** @var JWTGuard $auth */
        $auth = auth('api');
        $worker = $auth->user();

        $card = Card::with(['station'])
            ->where('card_number', $request->card_number)
            ->where('station_id', $worker->station_id)
            ->active()
            ->first();

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'card' => [
                    'id' => $card->id,
                    'card_number' => $card->card_number,
                    'qr_code' => $card->qr_code,
                    'family_name' => $card->family_name,
                    'phone' => $card->phone,
                    'balance' => $card->balance,
                    'status' => $card->status,
                ],
            ],
        ]);
    }

    /**
     * Get card transaction history (last 20).
     *
     * @param Request $request
     * @param int $cardId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactionHistory(Request $request, int $cardId)
    {
        /** @var JWTGuard $auth */
        $auth = auth('api');
        $worker = $auth->user();

        $card = Card::where('id', $cardId)
            ->where('station_id', $worker->station_id)
            ->first();

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found',
            ], 404);
        }

        $transactions = $card->transactions()
            ->with('worker:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'amount' => $t->amount,
                'previous_balance' => $t->previous_balance,
                'new_balance' => $t->new_balance,
                'transaction_type' => $t->transaction_type,
                'worker_name' => $t->worker->name,
                'date' => $t->created_at->format('Y-m-d H:i:s'),
                'notes' => $t->notes,
            ]);

        return response()->json([
            'success' => true,
            'data' => ['transactions' => $transactions],
        ]);
    }
}
