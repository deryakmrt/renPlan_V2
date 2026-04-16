<?php

namespace App\Modules\Orders\Domain;

class Order
{
    public int $id;
    public ?string $orderCode;
    public ?int $customerId;
    public ?string $customerName; // JOIN ile gelecek
    public string $status;
    
    // Tarihler (Listeleme ekranında görünenler)
    public ?string $siparisTarihi;
    public ?string $terminTarihi;
    public ?string $baslangicTarihi;
    public ?string $bitisTarihi;
    public ?string $teslimTarihi;
    public ?string $faturaTarihi;
    
    // Diğer önemli alanlar
    public ?string $projeAdi;
    public ?string $revizyonNo;
    public ?string $faturaParaBirimi;
    public string $createdAt;

    /** @var OrderItem[] */
    private array $items = [];

    public function addItem(OrderItem $item): void
    {
        $this->items[] = $item;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}