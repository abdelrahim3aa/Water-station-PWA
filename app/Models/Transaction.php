<?php
// app/Models/Transaction.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'temp_id',
        'card_id',
        'station_id',
        'worker_id',
        'amount',
        'previous_balance',
        'new_balance',
        'transaction_type',
        'status',
        'notes',
        'synced_at',
    ];
    protected $casts = [
        'amount'           => 'integer',
        'previous_balance' => 'integer',
        'new_balance'      => 'integer',
        'synced_at'        => 'datetime',
    ];
    // Boot method
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($transaction) {
            if (! $transaction->temp_id) {
                $transaction->temp_id = (string) Str::uuid();
            }
        });
    }
    // Relationships
    public function card()
    {
        return $this->belongsTo(Card::class);
    }
    public function station()
    {
        return $this->belongsTo(Station::class);
    }
    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    public function scopeUnsynced($query)
    {
        return $query->whereNull('synced_at');
    }
}
