<?php
// app/Models/Station.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'organization_id',
        'location',
        'latitude',
        'longitude',
        'status',
    ];
    protected $casts = [

        'latitude'  => 'decimal:8',
        'longitude' => 'decimal:8',
    ];
    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
    public function workers()
    {
        return $this->hasMany(Worker::class);
    }
    public function cards()
    {
        return $this->hasMany(Card::class);
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
}
