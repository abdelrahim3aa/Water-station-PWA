<?php
// app/Models/Organization.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{

    use HasFactory;
    protected $fillable = [
        'name',
        'phone',
        'address',
        'status',
    ];
// Relationships
    public function stations()
    {
        return $this->hasMany(Station::class);
    }
// Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
