<?php
// app/Http/Controllers/Api/TransactionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Card;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Create a new transaction (debit)
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_id' => 'required|integer|exists:cards,id',
            'amount' => 'required|integer|min:1',
            'temp_id' => 'nullable|string|unique:transactions,temp_id',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data provided',
                'errors' => $validator->errors()
            ], 422);
        }

        $worker = auth('api')->user();

        // Get the card with a lock for update
        $card = Card::where('id', $request->card_id)
            ->where('station_id', $worker->station_id)
            ->active()
            ->lockForUpdate()
            ->first();

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found or inactive'
            ], 404);
        }

        // Check for sufficient balance
        if ($card->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
                'data' => [
                    'current_balance' => $card->balance,
                    'requested_amount' => $request->amount
                ]
            ], 400);
        }

        try {
            DB::beginTransaction();

            $previousBalance = $card->balance;
            $newBalance = $previousBalance - $request->amount;

            // Create transaction
            $transaction = Transaction::create([
                'temp_id' => $request->temp_id,
                'card_id' => $card->id,
                'station_id' => $worker->station_id,
                'worker_id' => $worker->id,
                'amount' => $request->amount,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'transaction_type' => 'debit',
                'notes' => $request->notes,
                'synced_at' => now()
            ]);

            // Update card balance
            $card->update(['balance' => $newBalance]);

            // Clear cache
            \Cache::forget("card:qr:{$card->qr_code}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'temp_id' => $transaction->temp_id,
                        'amount' => $transaction->amount,
                        'previous_balance' => $transaction->previous_balance,
                        'new_balance' => $transaction->new_balance,
                        'date' => $transaction->created_at->format('Y-m-d H:i:s')
                    ],
                    'card' => [
                        'balance' => $newBalance
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during the transaction',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Sync offline transactions
     */
    public function sync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transactions' => 'required|array|max:100',
            'transactions.*.temp_id' => 'required|string|distinct',
            'transactions.*.card_id' => 'required|integer|exists:cards,id',
            'transactions.*.amount' => 'required|integer|min:1',
            'transactions.*.notes' => 'nullable|string|max:500',
            'transactions.*.created_at' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data provided',
                'errors' => $validator->errors()
            ], 422);
        }

        $worker = auth('api')->user();
        $syncedTransactions = [];
        $failedTransactions = [];

        foreach ($request->transactions as $transactionData) {
            try {
                // Check if transaction already exists
                $existingTransaction = Transaction::where('temp_id', $transactionData['temp_id'])->first();

                if ($existingTransaction) {
                    $syncedTransactions[] = [
                        'temp_id' => $transactionData['temp_id'],
                        'status' => 'already_exists',
                        'transaction_id' => $existingTransaction->id
                    ];
                    continue;
                }

                DB::beginTransaction();

                $card = Card::where('id', $transactionData['card_id'])
                    ->where('station_id', $worker->station_id)
                    ->lockForUpdate()
                    ->first();

                if (!$card) {
                    $failedTransactions[] = [
                        'temp_id' => $transactionData['temp_id'],
                        'reason' => 'card_not_found'
                    ];
                    DB::rollBack();
                    continue;
                }

                if ($card->balance < $transactionData['amount']) {
                    $failedTransactions[] = [
                        'temp_id' => $transactionData['temp_id'],
                        'reason' => 'insufficient_balance',
                        'current_balance' => $card->balance
                    ];
                    DB::rollBack();
                    continue;
                }

                $previousBalance = $card->balance;
                $newBalance = $previousBalance - $transactionData['amount'];

                $transaction = Transaction::create([
                    'temp_id' => $transactionData['temp_id'],
                    'card_id' => $card->id,
                    'station_id' => $worker->station_id,
                    'worker_id' => $worker->id,
                    'amount' => $transactionData['amount'],
                    'previous_balance' => $previousBalance,
                    'new_balance' => $newBalance,
                    'transaction_type' => 'debit',
                    'notes' => $transactionData['notes'] ?? null,
                    'created_at' => $transactionData['created_at'],
                    'synced_at' => now()
                ]);

                $card->update(['balance' => $newBalance]);
                \Cache::forget("card:qr:{$card->qr_code}");

                $syncedTransactions[] = [
                    'temp_id' => $transactionData['temp_id'],
                    'status' => 'synced',
                    'transaction_id' => $transaction->id
                ];

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();

                $failedTransactions[] = [
                    'temp_id' => $transactionData['temp_id'],
                    'reason' => 'sync_error',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Transactions synced successfully',
            'data' => [
                'synced_count' => count($syncedTransactions),
                'failed_count' => count($failedTransactions),
                'synced_transactions' => $syncedTransactions,
                'failed_transactions' => $failedTransactions
            ]
        ]);
    }

    /**
     * Get recent transactions
     */
    public function getRecentTransactions(Request $request)
    {
        $worker = auth('api')->user();
        $limit = $request->get('limit', 20);

        $transactions = Transaction::with(['card:id,card_number,family_name'])
            ->where('station_id', $worker->station_id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'temp_id' => $transaction->temp_id,
                    'amount' => $transaction->amount,
                    'new_balance' => $transaction->new_balance,
                    'card_number' => $transaction->card->card_number,
                    'family_name' => $transaction->card->family_name,
                    'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'is_synced' => !is_null($transaction->synced_at)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions
            ]
        ]);
    }
}
