<?php

namespace App\Modules\Orders\Application;

use PDO;
use Exception;

/**
 * Sipariş işlemlerini (Kaydetme, Güncelleme) yöneten Uygulama Servisi.
 */
class OrderService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * POST'tan gelen verilerle siparişi veritabanına kaydeder veya günceller.
     * Transaction ve "Duplicate order_code" (Çakışma) durumunda otomatik 3 kez tekrar deneme mantığı içerir.
     */
    public function saveOrder(array $postData): int
    {
        // --- İŞ KURALI (BUSINESS RULE): YAYINLA BUTONU ---
        // Eğer kullanıcı "Siparişi Yayınla" butonuna bastıysa, formdan ne gelirse gelsin durumu 'tedarik' yap.
        if (!empty($postData['yayinla_butonu'])) {
            $postData['status'] = 'tedarik';
        }

        // 1. TEMEL VERİLER
        $id          = (int)($postData['id'] ?? 0);
        $order_code  = trim($postData['order_code'] ?? '');
        $customer_id = (int)($postData['customer_id'] ?? 0);
        $status      = $postData['status'] ?? 'pending';

        if (!$order_code && function_exists('next_order_code')) {
            $order_code = next_order_code();
        }

        // --- İŞ KURALI (BUSINESS RULE): MÜŞTERİ ZORUNLUDUR ---
        if ($customer_id <= 0) {
            throw new Exception("Müşteri seçimi zorunludur! Lütfen siparişi kaydetmeden önce bir müşteri seçiniz.");
        }

        // 2. PARA BİRİMLERİ VE FİNANSAL ALANLAR
        $odeme_para_birimi  = $postData['odeme_para_birimi']  ?? '';
        $fatura_para_birimi = $postData['fatura_para_birimi'] ?? '';
        $kalem_para_birimi  = $postData['kalem_para_birimi']  ?? 'TL';
        
        $allowed_currencies = ['TL', 'EUR', 'USD', 'TRY'];
        if (!in_array($fatura_para_birimi, $allowed_currencies, true)) $fatura_para_birimi = 'TL';
        if (!in_array($odeme_para_birimi,  $allowed_currencies, true)) $odeme_para_birimi  = 'TL';
        
        $currency = ($odeme_para_birimi === 'TL' ? 'TRY' : ($odeme_para_birimi ?: 'TRY'));

        $kdv_orani     = (int)($postData['kdv_orani'] ?? 20);
        // Kur sadece fatura_edildi durumunda kaydedilir, diğer durumlarda NULL bırak
        $kur_usd       = ($status === 'fatura_edildi' && !empty($postData['kur_usd']))  ? (float)$postData['kur_usd']  : null;
        $kur_eur       = ($status === 'fatura_edildi' && !empty($postData['kur_eur']))  ? (float)$postData['kur_eur']  : null;
        $fatura_toplam = !empty($postData['fatura_toplam']) ? (float)$postData['fatura_toplam'] : null;

        // 3. TARİHLER
        $termin_tarihi    = !empty($postData['termin_tarihi'])    ? $postData['termin_tarihi']    : null;
        $baslangic_tarihi = !empty($postData['baslangic_tarihi']) ? $postData['baslangic_tarihi'] : null;
        $bitis_tarihi     = !empty($postData['bitis_tarihi'])     ? $postData['bitis_tarihi']     : null;
        $teslim_tarihi    = !empty($postData['teslim_tarihi'])    ? $postData['teslim_tarihi']    : null;
        $siparis_tarihi   = !empty($postData['siparis_tarihi'])   ? $postData['siparis_tarihi']   : null;
        $fatura_tarihi    = !empty($postData['fatura_tarihi'])    ? $postData['fatura_tarihi']    : null;

        // 4. METİN ALANLARI
        $notes          = trim($postData['notes'] ?? '');
        $proje_adi      = trim($postData['proje_adi'] ?? '');
        $siparis_veren  = trim($postData['siparis_veren'] ?? '');
        $siparisi_alan  = trim($postData['siparisi_alan'] ?? '');
        $siparisi_giren = trim($postData['siparisi_giren'] ?? '');
        $revizyon_no    = trim($postData['revizyon_no'] ?? '');
        $nakliye_turu   = trim($postData['nakliye_turu'] ?? '');
        $odeme_kosulu   = trim($postData['odeme_kosulu'] ?? '');

        // --- YENİ MİMARİ: ÇAKIŞMA ÖNLEYİCİ (RETRY) MANTIK ---
        $attempt = 0;
        $max_attempts = ($id > 0) ? 1 : 3; // Güncellemelerde tek deneme, yenilerde 3 deneme
        $order_id = $id;

        while ($attempt < $max_attempts) {
            try {
                // Yeni kayıtlarda çakışma olursa (attempt > 0) yeni kod al
                if ($id == 0 && $attempt > 0 && function_exists('next_order_code')) {
                    $order_code = next_order_code();
                }

                $this->db->beginTransaction();

                if ($id > 0) {
                    // --- GÜNCELLEME (UPDATE) ---
                    $sql = "UPDATE orders SET 
                            order_code=?, customer_id=?, status=?, currency=?, 
                            termin_tarihi=?, baslangic_tarihi=?, bitis_tarihi=?, teslim_tarihi=?, notes=?,
                            siparis_veren=?, siparisi_alan=?, siparisi_giren=?, siparis_tarihi=?, fatura_tarihi=?, 
                            fatura_para_birimi=?, kalem_para_birimi=?, proje_adi=?, revizyon_no=?, nakliye_turu=?, 
                            odeme_kosulu=?, odeme_para_birimi=?, kdv_orani=?, kur_usd=?, kur_eur=?, fatura_toplam=?
                            WHERE id=?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $order_code, $customer_id, $status, $currency, 
                        $termin_tarihi, $baslangic_tarihi, $bitis_tarihi, $teslim_tarihi, $notes,
                        $siparis_veren, $siparisi_alan, $siparisi_giren, $siparis_tarihi, $fatura_tarihi,
                        $fatura_para_birimi, $kalem_para_birimi, $proje_adi, $revizyon_no, $nakliye_turu,
                        $odeme_kosulu, $odeme_para_birimi, $kdv_orani, $kur_usd, $kur_eur, $fatura_toplam,
                        $id
                    ]);

                    $this->db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);
                    $order_id = $id;

                } else {
                    // --- YENİ KAYIT (INSERT) ---
                    $sql = "INSERT INTO orders (
                            order_code, customer_id, status, currency, 
                            termin_tarihi, baslangic_tarihi, bitis_tarihi, teslim_tarihi, notes,
                            siparis_veren, siparisi_alan, siparisi_giren, siparis_tarihi, fatura_tarihi, 
                            fatura_para_birimi, kalem_para_birimi, proje_adi, revizyon_no, nakliye_turu, 
                            odeme_kosulu, odeme_para_birimi, kdv_orani, kur_usd, kur_eur, fatura_toplam
                            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $order_code, $customer_id, $status, $currency, 
                        $termin_tarihi, $baslangic_tarihi, $bitis_tarihi, $teslim_tarihi, $notes,
                        $siparis_veren, $siparisi_alan, $siparisi_giren, $siparis_tarihi, $fatura_tarihi,
                        $fatura_para_birimi, $kalem_para_birimi, $proje_adi, $revizyon_no, $nakliye_turu,
                        $odeme_kosulu, $odeme_para_birimi, $kdv_orani, $kur_usd, $kur_eur, $fatura_toplam
                    ]);
                    $order_id = (int)$this->db->lastInsertId();
                }

                // Kalemleri ekle
                $this->saveOrderItems($order_id, $postData);

                // İşlem başarılıysa onayla ve döngüden çık
                $this->db->commit();
                return $order_id;

            } catch (\PDOException $e) {
                // Hata varsa geri al
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                // Hata 1062 / 23000 Duplicate Entry ise ve yeni kayıtsa 3 kere tekrar dene!
                if ($id == 0 && $e->getCode() == 23000 && strpos($e->getMessage(), 'order_code') !== false) {
                    $attempt++;
                    usleep(100000); // 0.1 saniye bekle
                    if ($attempt >= $max_attempts) {
                        throw new Exception("Sipariş kodu oluşturulamadı (Sürekli çakışma yaşandı). Lütfen tekrar deneyin.");
                    }
                    continue; // Hata yokmuş gibi döngünün başına dön ve yeni kodla tekrar dene
                }

                // Başka bir veritabanı hatasıysa direkt fırlat
                throw $e;
            } catch (Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        }

        return $order_id;
    }

    /**
     * Siparişe ait kalemleri (ürünleri) veritabanına yazar
     */
    private function saveOrderItems(int $order_id, array $postData): void
    {
        $p_ids  = $postData['product_id'] ?? [];
        $names  = $postData['name'] ?? [];
        $units  = $postData['unit'] ?? [];
        $qtys   = $postData['qty'] ?? [];
        $prices = $postData['price'] ?? ($postData['birim_fiyat'] ?? []);
        $ozet   = $postData['urun_ozeti'] ?? [];
        $kalan  = $postData['kullanim_alani'] ?? [];

        $keys = array_unique(array_merge(
            array_keys((array)$p_ids), array_keys((array)$names),
            array_keys((array)$units), array_keys((array)$qtys), array_keys((array)$prices)
        ));
        sort($keys);

        $ins = $this->db->prepare("INSERT INTO order_items (order_id, product_id, name, unit, qty, price, urun_ozeti, kullanim_alani) VALUES (?,?,?,?,?,?,?,?)");

        foreach ($keys as $i) {
            $n   = trim((string)($names[$i] ?? ''));
            $pid = (int)($p_ids[$i] ?? 0);
            
            if (empty($pid) && trim($n) === '') continue;
            if ($pid === 0) $pid = null;

            $u = trim((string)($units[$i] ?? 'adet'));
            
            // Güvenli sayı parse: sadece ilk virgülü noktaya çevir
            $q  = (float)preg_replace('/,(?=.*,)/', '', str_replace(',', '.', (string)($qtys[$i]  ?? 0)));
            $pr = (float)preg_replace('/,(?=.*,)/', '', str_replace(',', '.', (string)($prices[$i] ?? 0)));

            $oz = trim((string)($ozet[$i] ?? ''));
            $ka = trim((string)($kalan[$i] ?? ''));

            $ins->execute([$order_id, $pid, $n, $u, $q, $pr, $oz, $ka]);
        }
    }
}