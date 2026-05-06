<?php
/**
 * Ürün Model Sınıfı (Domain Layer)
 * Bu sınıf, veritabanındaki 'products' tablosunun PHP tarafındaki karşılığıdır.
 */
class ProductModel {
    // Özelliklerin (Property) tiplerini belirliyoruz. 
    // Başındaki '?' işareti bu değerin veritabanında 'NULL' olabileceğini gösterir.
    public ?int $id;
    public ?string $sku;
    public string $name;
    public ?string $unit;
    public float $price;
    public ?string $urun_ozeti;
    public ?string $kullanim_alani;
    public ?string $description;
    public ?string $image;
    public ?int $category_id;
    public ?int $brand_id;
    public ?int $parent_id;
    public ?string $sku_config;
    public ?string $created_at;
    public ?string $updated_at;

    // Veritabanından veya formdan gelen diziyi nesneye (object) dönüştüren yapıcı metod
    public function __construct(array $data = []) {
        // Gelen verileri kendi tiplerine göre zorlayarak (casting) atıyoruz
        $this->id             = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;
        $this->sku            = $data['sku'] ?? '';
        $this->name           = $data['name'] ?? '';
        $this->unit           = $data['unit'] ?? 'Adet';
        $this->price          = isset($data['price']) ? (float)$data['price'] : 0.0000;
        $this->urun_ozeti     = $data['urun_ozeti'] ?? '';
        $this->kullanim_alani = $data['kullanim_alani'] ?? '';
        $this->description    = $data['description'] ?? '';
        $this->image          = $data['image'] ?? '';
        
        // Yabancı anahtarlar (Foreign Keys) boş ise NULL olmalıdır (Veritabanı ilişkileri için önemlidir)
        $this->category_id    = isset($data['category_id']) && $data['category_id'] !== '' ? (int)$data['category_id'] : null;
        $this->brand_id       = isset($data['brand_id']) && $data['brand_id'] !== '' ? (int)$data['brand_id'] : null;
        $this->parent_id      = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null;
        
        $this->sku_config     = $data['sku_config'] ?? '';
        $this->created_at     = $data['created_at'] ?? null;
        $this->updated_at     = $data['updated_at'] ?? null;
    }
}