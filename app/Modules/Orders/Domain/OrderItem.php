<?php

namespace App\Modules\Orders\Domain;

class OrderItem
{
    public int $id;
    public int $orderId;
    public ?int $productId;
    public string $name;
    public ?string $unit;
    public float $qty;
    public float $price;
    public ?string $urunOzeti;
    public ?string $kullanimAlani;
    
    // Ürün (Product) tablosundan JOIN ile gelecek
    public ?string $sku = null; 

    public function getTotalPrice(): float
    {
        return $this->qty * $this->price;
    }
}