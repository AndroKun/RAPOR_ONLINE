<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId) {
    http_response_code(404);
    exit('Rapor tidak ditemukan.');
}

$statement = $pdo->prepare("SELECT pdf_path FROM reports WHERE id = :id AND status = 'published' LIMIT 1");
$statement->execute(['id' => $reportId]);
$report = $statement->fetch();

if (!$report || !is_file($report['pdf_path'])) {
    http_response_code(404);
    exit('Rapor tidak tersedia.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="rapor-' . $reportId . '.pdf"');
readfile($report['pdf_path']);
