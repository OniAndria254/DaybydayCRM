<?php

namespace App\Models;

use App\Repositories\Money\Money;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $appends = ['divided_price'];
    protected $hidden=['id'];

    protected $fillable = [
        'name',
        'external_id',
        'description',
        'number',
        'default_type',
        'archived',
        'integration_type',
        'integration_id',
        'price',
        'delete_at',
        'created_at',
        'update_at',
    ];

    
    public function getRouteKeyName()
    {
        return 'external_id';
    }

    public function getMoneyPriceAttribute()
    {
        $money = new Money($this->price);
        return $money;
    }

    public function getDividedPriceAttribute()
    {
        return $this->price / 100;
    }
    
}
