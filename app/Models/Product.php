<?php

namespace App\Models;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prices',
        'vat',
        'discount',
        'quantity'
    ];

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)->withPivot('quantity', 'prices');
    }
}
