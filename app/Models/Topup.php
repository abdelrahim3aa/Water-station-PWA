<?php
     // app/Models/Topup.php
     namespace App\Models;

     use Illuminate\Database\Eloquent\Factories\HasFactory;
     use Illuminate\Database\Eloquent\Model;

     class Topup extends Model
     {
         use HasFactory;
         protected $fillable = [
             'card_id',
             'station_id',
             'worker_id',
             'amount',
             'method',
             'price',
             'notes',
         ];
         protected $casts = [
             'amount' => 'integer',
             'price'  => 'decimal:2',
         ];
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
         public function scopeByMethod($query, $method)
         {
             return $query->where('method', $method);
         }
 }
