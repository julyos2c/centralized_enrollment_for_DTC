<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['enrollment_id', 'processed_by', 'amount', 'receipt_no', 'paid_at'];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}