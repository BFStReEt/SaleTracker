<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_time',
        'end_time',
        'business_name',
        'user_name',
        'customer_name',
        'item',
        'quantity',
        'price',
        'sales_result',
    ];
}