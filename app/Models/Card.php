<?php
// app/Models/Card.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;
    protected $fillable = [
        'card_number',
        'qr_code',
        'family_name',
        'phone',
        'station_id',
        'balance',
        'status',
        'notes',
    ];
    protected $casts = [
        'balance' => 'integer',
    ];
// Relationships
    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function topups()
    {
        return $this->hasMany(Topup::class);
    }
// Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    public function scopeByQrCode($query, $qrCode)
    {
        return $query->where('qr_code', $qrCode);
    }
    public function scopeByCardNumber($query, $cardNumber)
    {
        return $query->where('card_number', $cardNumber);
    }
// Accessors
    public function getFormattedBalanceAttribute()
    {
        return number_format($this->balance);
    }
}
