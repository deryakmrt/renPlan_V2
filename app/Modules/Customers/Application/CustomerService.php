<?php

namespace App\Modules\Customers\Application;

use App\Modules\Customers\Infrastructure\CustomerRepository;

class CustomerService
{
    public function __construct(private CustomerRepository $repo) {}

    public function save(array $post): int
    {
        $data = [
            'id'               => (int)($post['id'] ?? 0),
            'name'             => trim($post['name'] ?? ''),
            'email'            => trim($post['email'] ?? ''),
            'phone'            => trim($post['phone'] ?? ''),
            'billing_address'  => trim($post['billing_address'] ?? ''),
            'shipping_address' => trim($post['shipping_address'] ?? ''),
            'ilce'             => trim($post['ilce'] ?? ''),
            'il'               => trim($post['il'] ?? ''),
            'ulke'             => trim($post['ulke'] ?? ''),
            'vergi_dairesi'    => trim($post['vergi_dairesi'] ?? ''),
            'vergi_no'         => trim($post['vergi_no'] ?? ''),
            'website'          => $this->normalizeUrl(trim($post['website'] ?? '')),
        ];

        if ($data['name'] === '') {
            throw new \InvalidArgumentException('Müşteri adı zorunludur.');
        }

        return $this->repo->save($data);
    }

    public function exportCsv(): void
    {
        $cols = $this->repo->getAllColumns();
        $rows = $this->repo->getForExport();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=customers_' . date('Y-m-d_H-i-s') . '.csv');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        $fh = fopen('php://output', 'w');
        fputcsv($fh, $cols);
        while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($fh, array_map(fn($c) => $row[$c] ?? '', $cols));
        }
        fclose($fh);
    }

    public function importCsv(string $tmpPath): array
    {
        [$delimiter, $rows] = $this->detectCsv($tmpPath);

        if (count($rows) < 2) {
            throw new \RuntimeException('CSV boş veya hatalı.');
        }

        $allowed    = array_map('strtolower', $this->repo->getAllColumns());
        $headers    = array_map(fn($h) => strtolower($this->normalizeHeader($h)), $rows[0]);
        $colMap     = [];
        foreach ($headers as $i => $h) {
            $pos = array_search($h, $allowed, true);
            if ($pos !== false) $colMap[$i] = $this->repo->getAllColumns()[$pos];
        }

        if (empty($colMap)) {
            throw new \RuntimeException('Başlıklar customers tablosu ile eşleşmiyor.');
        }

        $inserted = $updated = $skipped = 0;

        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            if (empty(array_filter(array_map('trim', $row)))) { $skipped++; continue; }

            $data = [];
            foreach ($colMap as $idx => $col) {
                $data[$col] = isset($row[$idx]) ? trim((string)$row[$idx]) : null;
            }

            $result = $this->repo->upsert($data);
            $result === 'inserted' ? $inserted++ : $updated++;
        }

        return compact('inserted', 'updated', 'skipped');
    }

    private function normalizeUrl(string $url): string
    {
        if ($url === '') return '';
        if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : '';
    }

    private function normalizeHeader(string $h): string
    {
        $map = [' '=>'_','İ'=>'I','ı'=>'i','Ş'=>'S','ş'=>'s','Ğ'=>'G','ğ'=>'g',
                'Ü'=>'U','ü'=>'u','Ö'=>'O','ö'=>'o','Ç'=>'C','ç'=>'c'];
        return strtolower(strtr(trim($h), $map));
    }

    private function detectCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $first  = fgets($handle);
        fclose($handle);

        $best = ','; $bestCount = 0;
        foreach ([',', ';', "\t", '|'] as $d) {
            $c = substr_count($first, $d);
            if ($c > $bestCount) { $bestCount = $c; $best = $d; }
        }

        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($h, 0, $best)) !== false) $rows[] = $data;
            fclose($h);
        }
        return [$best, $rows];
    }
}
