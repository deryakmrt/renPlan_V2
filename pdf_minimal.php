<?php
// pdf_minimal.php — PDF teslim testi (kütüphane gerektirmez)

// Header'lar daha önce gönderildiyse uyarı yaz ve çık
if (headers_sent($file, $line)) {
    header_remove();
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Headers were already sent in $file on line $line. Remove any BOM/whitespace before <?php.";
    exit;
}

// Olası tampon/kompresyon sorunlarını kapat
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 0);
while (ob_get_level()) { ob_end_clean(); }

// Doğru PDF header'ları
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="pdf_minimal_test.pdf"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Tek sayfalık minimal, geçerli bir PDF (base64)
$base64 = 'JVBERi0xLjQKJcTl8uXrp/Og0MTGCjEgMCBvYmoKPDwKL1R5cGUgL0NhdGFsb2cKL1BhZ2VzIDIgMCBSCj4+CmVuZG9iagoKMiAwIG9iago8PAovVHlwZSAvUGFnZXMKL0tpZHMgWzMgMCBSXQovQ291bnQgMQo+PgplbmRvYmoKCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA1OTUuMjcgODQxLjg5XQovUmVzb3VyY2VzIDw8Pj4KL0NvbnRlbnRzIDQgMCBSCj4+CmVuZG9iagoKNCAwIG9iago8PAovTGVuZ3RoIDAKPj4Kc3RyZWFtCgpzdHJlYW0KZW5kc3RyZWFtCmVuZG9iagoKeHJlZgowIDYKMD000MDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAwMTUgMDAwMDAgbiAKMDAwMDAwMDA3MyAwMDAwMCBuIAowMDAwMDAwMTQyIDAwMDAwIG4gCjAwMDAwMDAyMzUgMDAwMDAgbiAKMDAwMDAwMDI4NiAwMDAwMCBuIAp0cmFpbGVyCjw8Ci9TaXplIDYK L1Jvb3QgMSAwIFIKPj4Kc3RhcnR4cmVmCjM1MQolJUVPRgo=';

// PDF içeriğini yazdır
echo base64_decode($base64);
exit;
