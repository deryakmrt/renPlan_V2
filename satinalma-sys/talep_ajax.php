<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = $pdo ?? $DB ?? $db ?? null;

if (!$pdo && defined('DB_HOST')) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'search_products':
            $term = isset($_GET['term']) ? trim($_GET['term']) : '';

            if (strlen($term) < 2) {
                echo json_encode([]);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
            SELECT DISTINCT urun 
            FROM satinalma_order_items 
            WHERE urun LIKE ? 
            GROUP BY urun 
            ORDER BY COUNT(*) DESC, urun ASC 
            LIMIT 10
        ");
                $stmt->execute(['%' . $term . '%']);
                $products = $stmt->fetchAll(PDO::FETCH_COLUMN);

                echo json_encode($products);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;

        case 'get_product_suppliers':
            $productName = isset($_GET['product_name']) ? trim($_GET['product_name']) : '';

            if (!$productName) {
                echo json_encode(['suppliers' => [], 'historical_count' => 0]);
                exit;
            }

            // Geçmişi olan tedarikçiler
            $stmt = $pdo->prepare("
        SELECT DISTINCT 
            s.id,
            s.name,
            s.contact_person,
            s.phone,
            s.email,
            AVG(sq.price) as avg_price,
            MAX(sq.quote_date) as last_quote_date,
            COUNT(sq.id) as quote_count,
            1 as has_history
        FROM satinalma_order_items soi
        JOIN satinalma_quotes sq ON soi.id = sq.order_item_id
        JOIN suppliers s ON sq.supplier_id = s.id
        WHERE soi.urun = ? AND s.durum = 1
        GROUP BY s.id
        ORDER BY AVG(sq.price) ASC
    ");
            $stmt->execute([$productName]);
            $historical_suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $historical_ids = array_column($historical_suppliers, 'id');

            // Diğer aktif tedarikçiler
            if (!empty($historical_ids)) {
                $placeholders = implode(',', array_fill(0, count($historical_ids), '?'));
                $stmt = $pdo->prepare("
            SELECT id, name, contact_person, phone, email, 
                   NULL as avg_price, NULL as last_quote_date, 
                   0 as quote_count, 0 as has_history
            FROM suppliers 
            WHERE durum = 1 AND id NOT IN ($placeholders)
            ORDER BY name ASC
        ");
                $stmt->execute($historical_ids);
            } else {
                $stmt = $pdo->query("
            SELECT id, name, contact_person, phone, email,
                   NULL as avg_price, NULL as last_quote_date, 
                   0 as quote_count, 0 as has_history
            FROM suppliers 
            WHERE durum = 1
            ORDER BY name ASC
        ");
            }
            $other_suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $all_suppliers = array_merge($historical_suppliers, $other_suppliers);

            echo json_encode([
                'success' => true,
                'suppliers' => $all_suppliers,
                'historical_count' => count($historical_suppliers)
            ]);
            exit;

        case 'get_suppliers':
            $item_id = (int)($_GET['item_id'] ?? 0);
            $product_name = trim($_GET['product_name'] ?? '');

            // Tüm aktif tedarikçileri getir
            $stmt = $pdo->query("SELECT id, name, contact_person, phone, email, address FROM suppliers WHERE durum = 1 ORDER BY name");
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $quotes = [];
            $selected_quote = null;

            if ($item_id > 0) {
                // Bu ürün için tüm teklifleri getir - order_item_id ile
                $stmt = $pdo->prepare("
            SELECT sq.*, s.name as supplier_name, s.contact_person, s.phone, s.email
            FROM satinalma_quotes sq 
            JOIN suppliers s ON sq.supplier_id = s.id 
            WHERE sq.order_item_id = ? 
            ORDER BY sq.selected DESC, sq.price ASC
        ");
                $stmt->execute([$item_id]);
                $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Debug için
                error_log("Item ID: $item_id - Found quotes: " . count($quotes));

                // Seçili teklifi bul
                foreach ($quotes as $q) {
                    if ($q['selected'] == 1) {
                        $selected_quote = $q;
                        break;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'suppliers' => $suppliers,
                'quotes' => $quotes,
                'selected_quote' => $selected_quote
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'toggle_approval':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $approved = (int)($_POST['approved'] ?? 0);

            if ($item_id <= 0) {
                throw new Exception('Geçersiz ürün ID');
            }

            $stmt = $pdo->prepare("UPDATE satinalma_order_items SET son_onay = ? WHERE id = ?");
            $stmt->execute([$approved, $item_id]);

            echo json_encode([
                'success' => true,
                'approved' => $approved == 1,
                'message' => $approved ? 'Onaylandı' : 'Onay kaldırıldı'
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'save_quote':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $currency = $_POST['currency'] ?? 'TRY';
            $quote_date = $_POST['quote_date'] ?? date('Y-m-d');
            $delivery_days = (int)($_POST['delivery_days'] ?? 0);
            $payment_term = $_POST['payment_term'] ?? '';
            $shipping_type = $_POST['shipping_type'] ?? ''; // YENİ
            $notes = $_POST['notes'] ?? '';
            $product_name = trim($_POST['product_name'] ?? '');

            if ($supplier_id <= 0 || $price <= 0) {
                throw new Exception('Geçersiz parametreler');
            }
            if ($delivery_days <= 0) {
                throw new Exception('Teslimat süresi zorunludur');
            }
            if (empty($shipping_type)) {
                throw new Exception('Gönderim türü zorunludur');
            }

            $talep_id = 0;

            // Eğer item_id yoksa veya 0 ise, yeni bir order_item oluştur
            if ($item_id <= 0 && !empty($product_name)) {
                // Önce bu üründen var mı kontrol et
                $stmt = $pdo->prepare("SELECT id FROM satinalma_order_items WHERE urun = ? LIMIT 1");
                $stmt->execute([$product_name]);
                $existing_item = $stmt->fetch();

                if ($existing_item) {
                    $item_id = (int)$existing_item['id'];
                } else {
                    // Yeni item oluştur (geçici talep_id = 0)
                    $stmt = $pdo->prepare("
                INSERT INTO satinalma_order_items (talep_id, urun, miktar, birim, durum) 
                VALUES (0, ?, 0, '', 'Beklemede')
            ");
                    $stmt->execute([$product_name]);
                    $item_id = $pdo->lastInsertId();
                }
            }

            if ($item_id > 0) {
                $stmt = $pdo->prepare("SELECT talep_id FROM satinalma_order_items WHERE id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch();
                $talep_id = $item ? (int)$item['talep_id'] : 0;
            }

            // Aynı tedarikçi için mevcut teklifi kontrol et
            $stmt = $pdo->prepare("SELECT id FROM satinalma_quotes WHERE order_item_id = ? AND supplier_id = ?");
            $stmt->execute([$item_id, $supplier_id]);
            $existing_quote = $stmt->fetch();

            if ($existing_quote) {
                $stmt = $pdo->prepare("
            UPDATE satinalma_quotes 
            SET price = ?, currency = ?, quote_date = ?, delivery_days = ?, payment_term = ?, shipping_type = ?, note = ?, updated_at = NOW()
            WHERE id = ?
        ");
                $stmt->execute([$price, $currency, $quote_date, $delivery_days, $payment_term, $shipping_type, $notes, $existing_quote['id']]);
                $quote_id = $existing_quote['id'];
            } else {
                $stmt = $pdo->prepare("
            INSERT INTO satinalma_quotes (order_item_id, talep_id, supplier_id, price, currency, quote_date, delivery_days, payment_term, shipping_type, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
                $stmt->execute([$item_id, $talep_id, $supplier_id, $price, $currency, $quote_date, $delivery_days, $payment_term, $shipping_type, $notes]);
                $quote_id = $pdo->lastInsertId();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Teklif kaydedildi',
                'quote_id' => $quote_id,
                'item_id' => $item_id
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'select_quote':
            $quote_id = (int)($_POST['quote_id'] ?? 0);
            $item_id = (int)($_POST['item_id'] ?? 0);

            if ($quote_id <= 0 || $item_id <= 0) {
                throw new Exception('Geçersiz parametreler');
            }

            // talep_id'yi bul
            $stmt = $pdo->prepare("SELECT talep_id FROM satinalma_order_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            $talep_id = $item ? (int)$item['talep_id'] : 0;

            if ($talep_id <= 0) {
                throw new Exception('Talep ID bulunamadı');
            }

            // Önce tüm seçimleri kaldır
            $stmt = $pdo->prepare("UPDATE satinalma_quotes SET selected = 0 WHERE order_item_id = ?");
            $stmt->execute([$item_id]);

            // Seçilen teklifi işaretle
            $stmt = $pdo->prepare("UPDATE satinalma_quotes SET selected = 1 WHERE id = ?");
            $stmt->execute([$quote_id]);

            // Seçilen teklif bilgilerini getir
            $stmt = $pdo->prepare("
                SELECT sq.*, s.name as supplier_name 
                FROM satinalma_quotes sq 
                JOIN suppliers s ON sq.supplier_id = s.id 
                WHERE sq.id = ?
            ");
            $stmt->execute([$quote_id]);
            $selected_quote = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$selected_quote) {
                throw new Exception('Teklif bulunamadı');
            }

            // satinalma_order_items tablosunu güncelle
            $stmt = $pdo->prepare("
                UPDATE satinalma_order_items 
                SET selected_supplier_id = ?, 
                    selected_quote_id = ?,
                    birim_fiyat = ?,
                    durum = CASE 
                        WHEN durum = 'Beklemede' THEN 'Teklif Alındı'
                        ELSE durum 
                    END
                WHERE id = ?
            ");
            $stmt->execute([
                $selected_quote['supplier_id'],
                $quote_id,
                $selected_quote['price'],
                $item_id
            ]);

            // product_suppliers tablosunu güncelle (geçmiş veriler için)
            $stmt = $pdo->prepare("
                INSERT INTO product_suppliers (product_name, supplier_id, is_preferred, last_price, last_quote_date, total_orders) 
                VALUES (?, ?, 1, ?, NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                    is_preferred = 1,
                    last_price = VALUES(last_price),
                    last_quote_date = VALUES(last_quote_date),
                    total_orders = total_orders + 1
            ");

            // Ürün adını bul
            $stmt_product = $pdo->prepare("SELECT urun FROM satinalma_order_items WHERE id = ?");
            $stmt_product->execute([$item_id]);
            $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

            if ($product && !empty($product['urun'])) {
                $stmt->execute([
                    $product['urun'],
                    $selected_quote['supplier_id'],
                    $selected_quote['price']
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Teklif seçildi ve kaydedildi',
                'quote' => $selected_quote
            ], JSON_UNESCAPED_UNICODE);
            break;
        case 'get_talep_details':
            $talep_id = isset($_GET['talep_id']) ? (int)$_GET['talep_id'] : 0;

            if ($talep_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Geçersiz talep ID']);
                exit;
            }

            try {
                // SORGUTU GÜNCELLEDİK:
                // En ucuz fiyatı veren firmanın adını (best_price_supplier) alt sorgu ile çekiyoruz.
                $stmt = $pdo->prepare("
                    SELECT 
                        soi.*,
                        COUNT(DISTINCT sq.id) as quote_count,
                        MIN(sq.price) as best_price,
                        MIN(sq.currency) as best_price_currency,
                        
                        /* --- YENİ EKLENEN KISIM BAŞLANGIÇ --- */
                        (SELECT s_sub.name 
                         FROM satinalma_quotes sq_sub 
                         JOIN suppliers s_sub ON sq_sub.supplier_id = s_sub.id 
                         WHERE sq_sub.order_item_id = soi.id 
                         ORDER BY sq_sub.price ASC LIMIT 1) as best_price_supplier,
                        /* --- YENİ EKLENEN KISIM BİTİŞ --- */

                        s.name as selected_supplier,
                        sq_sel.price as selected_price,
                        sq_sel.id as selected_quote_id,
                        sq_sel.currency as selected_currency,
                        sq_sel.payment_term as selected_payment_term,
                        sq_sel.delivery_days as selected_delivery_days,
                        sq_sel.shipping_type as selected_shipping_type,
                        sq_sel.note as selected_note,
                        sq_sel.quote_date as selected_quote_date,
                        GROUP_CONCAT(DISTINCT s2.name SEPARATOR ', ') as quoted_suppliers
                    FROM satinalma_order_items soi
                    LEFT JOIN satinalma_quotes sq ON soi.id = sq.order_item_id
                    LEFT JOIN satinalma_quotes sq_sel ON soi.id = sq_sel.order_item_id AND sq_sel.selected = 1
                    LEFT JOIN suppliers s ON sq_sel.supplier_id = s.id
                    LEFT JOIN satinalma_quotes sq2 ON soi.id = sq2.order_item_id
                    LEFT JOIN suppliers s2 ON sq2.supplier_id = s2.id
                    WHERE soi.talep_id = ? 
                    GROUP BY soi.id
                    ORDER BY soi.id ASC
                ");
                $stmt->execute([$talep_id]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'items' => $items]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'add_supplier':
            $supplier_name = trim($_POST['supplier_name'] ?? '');

            if (empty($supplier_name)) {
                throw new Exception('Tedarikçi adı gereklidir');
            }

            $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ?");
            $stmt->execute([$supplier_name]);
            $existing = $stmt->fetch();

            if ($existing) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Bu tedarikçi zaten mevcut',
                    'supplier_id' => $existing['id'],
                    'supplier_name' => $supplier_name
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO suppliers (name, contact_person, phone, email, address, durum) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $supplier_name,
                trim($_POST['contact_person'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['address'] ?? '')
            ]);

            $new_id = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Tedarikçi eklendi',
                'supplier_id' => $new_id,
                'supplier_name' => $supplier_name
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception('Geçersiz işlem');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
