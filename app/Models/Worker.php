<?php
// app/Models/Worker.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Worker extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'username',
        'password',
        'phone',
        'station_id',
        'role',
        'status',
    ];
    protected $hidden = [
        'password',
    ];
    protected $casts = [
        'last_login' => 'datetime',
    ];
// JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [
            'station_id' => $this->station_id,
            'role'       => $this->role,
        ];
    }
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
// Mutators
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }
}
