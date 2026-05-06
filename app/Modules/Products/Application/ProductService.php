<?php

namespace App\Modules\Products\Application;

use App\Modules\Products\Infrastructure\ProductRepository;

class ProductService
{
    public function __construct(private ProductRepository $repo) {}

    // ─── Ürün kaydet / güncelle ──────────────────────────────────────────────
    public function save(array $post, array $files = []): int
    {
        $id   = (int)($post['id'] ?? 0);
        $name = trim($post['name'] ?? '');
        $sku  = trim($post['sku'] ?? '') ?: null;
        $unit = trim($post['unit'] ?? 'Adet');

        if ($name === '') throw new \InvalidArgumentException('Ürün adı zorunludur.');
        if ($unit === '') throw new \InvalidArgumentException('Birim zorunludur.');

        // SKU benzersizlik
        if ($sku && !$this->repo->checkSkuUnique($sku, $id)) {
            throw new \InvalidArgumentException('Bu SKU zaten kullanılıyor: ' . $sku);
        }

        // Fiyat — virgülü noktaya çevir
        $price = (float)str_replace(',', '.', str_replace('.', '', $post['price'] ?? '0'));

        $data = [
            'id'             => $id,
            'name'           => $name,
            'sku'            => $sku,
            'unit'           => $unit,
            'price'          => $price,
            'urun_ozeti'     => trim($post['urun_ozeti'] ?? ''),
            'kullanim_alani' => trim($post['kullanim_alani'] ?? ''),
            'category_id'    => ($post['category_id'] ?? '') !== '' ? (int)$post['category_id'] : null,
            'brand_id'       => ($post['brand_id'] ?? '')    !== '' ? (int)$post['brand_id']    : null,
            'parent_id'      => ($post['parent_id'] ?? '')   !== '' ? (int)$post['parent_id']   : null,
            'sku_config'     => $post['sku_config'] ?? null,
        ];

        $savedId = $this->repo->save($data);

        // Görsel işle
        $this->handleImage($savedId, $post, $files);

        // Varyasyonları kaydet
        $this->saveVariants($savedId, $post, $price, $unit, $data['category_id'], $data['brand_id']);

        return $savedId;
    }

    // ─── Görsel yönetimi ─────────────────────────────────────────────────────
    public function handleImage(int $id, array $post, array $files): void
    {
        // Görsel sil
        if (!empty($post['delete_image'])) {
            $old = $this->repo->getImage($id);
            if ($old) $this->deleteImageFile($old);
            $this->repo->updateImage($id, null);
            return;
        }

        // Yeni görsel yükle
        if (!empty($files['image']['name']) && ($files['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $old = $this->repo->getImage($id);
            $rel = $this->storeImage($id, $files['image'], $old);
            if ($rel) $this->repo->updateImage($id, $rel);
        }
    }

    // ─── Silme ───────────────────────────────────────────────────────────────
    public function delete(int $id): void
    {
        $old = $this->repo->getImage($id);
        if ($old) $this->deleteImageFile($old);
        $this->repo->delete($id);
    }

    // ─── Varyasyon gruplama ──────────────────────────────────────────────────
    public function groupVariants(int $parentId, array $childIds): void
    {
        $this->repo->groupVariants($parentId, $childIds);
    }

    // ─── İç: Varyasyonları kaydet ────────────────────────────────────────────
    private function saveVariants(int $parentId, array $post, float $defaultPrice, string $unit, ?int $catId, ?int $brandId): void
    {
        // Sil
        if (!empty($post['delete_v_ids']) && is_array($post['delete_v_ids'])) {
            $this->repo->deleteVariants($post['delete_v_ids'], $parentId);
        }

        // Ayır (unlink)
        if (!empty($post['unlink_v_ids']) && is_array($post['unlink_v_ids'])) {
            $this->repo->unlinkVariants($post['unlink_v_ids'], $parentId);
        }

        // Mevcut varyasyonları güncelle
        if (!empty($post['v_name']) && is_array($post['v_name'])) {
            foreach ($post['v_name'] as $vid => $vName) {
                $vid = (int)$vid;
                if (empty($vName)) continue;
                $vPrice = (float)str_replace(',', '.', str_replace('.', '', $post['v_price'][$vid] ?? ''));
                $this->repo->save([
                    'id'             => $vid,
                    'name'           => trim($vName),
                    'sku'            => trim($post['v_sku'][$vid] ?? '') ?: null,
                    'unit'           => $unit,
                    'price'          => $vPrice ?: $defaultPrice,
                    'urun_ozeti'     => $post['v_ozet'][$vid] ?? '',
                    'kullanim_alani' => $post['v_alan'][$vid] ?? '',
                    'category_id'    => $catId,
                    'brand_id'       => $brandId,
                    'parent_id'      => $parentId,
                    'sku_config'     => null,
                ]);
            }
        }

        // Yeni varyasyon ekle
        if (!empty($post['new_v_name']) && is_array($post['new_v_name'])) {
            foreach ($post['new_v_name'] as $idx => $nName) {
                if (empty(trim($nName))) continue;
                $nPrice = (float)str_replace(',', '.', str_replace('.', '', $post['new_v_price'][$idx] ?? ''));
                $this->repo->save([
                    'id'             => 0,
                    'name'           => trim($nName),
                    'sku'            => trim($post['new_v_sku'][$idx] ?? '') ?: null,
                    'unit'           => $unit,
                    'price'          => $nPrice ?: $defaultPrice,
                    'urun_ozeti'     => $post['new_v_ozet'][$idx] ?? '',
                    'kullanim_alani' => $post['new_v_alan'][$idx] ?? '',
                    'category_id'    => $catId,
                    'brand_id'       => $brandId,
                    'parent_id'      => $parentId,
                    'sku_config'     => null,
                ]);
            }
        }
    }

    // ─── Görsel dosya yönetimi ───────────────────────────────────────────────
    private function storeImage(int $id, array $file, ?string $oldRel): ?string
    {
        $year  = date('Y');
        $month = date('m');
        $dir   = __DIR__ . "/../../../../uploads/products/$year/$month/";

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) return null;

        // Eski görseli sil
        if ($oldRel) $this->deleteImageFile($oldRel);

        $fname = "p_{$id}_" . time() . ".$ext";
        $dest  = $dir . $fname;

        if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

        return "uploads/products/$year/$month/$fname";
    }

    private function deleteImageFile(string $rel): void
    {
        $abs = __DIR__ . '/../../../../' . ltrim($rel, '/');
        if (file_exists($abs)) @unlink($abs);
    }
}