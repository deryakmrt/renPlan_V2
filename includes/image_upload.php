<?php
/**
 * includes/image_upload.php
 * Tek işlev: ürün görselini güvenli şekilde kaydetmek
 * Kullanım: $rel = product_image_store($productId, $_FILES, 'image', $oldRelPath);
 * Geri dönüş: 'uploads/products/YY/MM/p_ID.ext' (relative) veya null.
 */
if (!function_exists('product_image_store')){
  function product_image_store($productId, $files, $field='image', $oldRel=null){
    if (!isset($files[$field]) || !is_array($files[$field])) return null;
    $f = $files[$field];
    if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_uploaded_file($f['tmp_name'])) return null;

    $orig = (string)$f['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = array('jpg','jpeg','png','gif','webp');
    if (!in_array($ext, $allowed, true)) $ext = 'jpg';

    $subdir = 'uploads/products/'.date('Y/m');
    $absdir = __DIR__ . '/../' . $subdir;
    if (!is_dir($absdir)) @mkdir($absdir, 0775, true);

    $fname = 'p_'.$productId.'.'.$ext;
    $abs = $absdir . '/' . $fname;
    $rel = $subdir . '/' . $fname;

    if (!@move_uploaded_file($f['tmp_name'], $abs)) return null;
    @chmod($abs, 0644);

    // Eski dosyayı sil (farklı ise)
    if ($oldRel){
      $oldAbs = __DIR__ . '/../' . ltrim($oldRel, '/');
      if ($oldAbs !== $abs && is_file($oldAbs)) { @unlink($oldAbs); }
    }

    return $rel;
  }
}
