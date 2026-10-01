<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) die("Unauthorized");

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT r.*, m.nim, m.nama_lengkap, u.judul_ujian, mk.nama_mk, k.nama_kelas
    FROM riwayat_sesi_ujian r
    JOIN mahasiswa m ON r.id_mahasiswa = m.id
    JOIN ujian u ON r.id_ujian = u.id
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
    LEFT JOIN kelas k ON m.id_kelas = k.id
    WHERE 1=1
";
$params = [];

if ($kelasId > 0) {
    $query .= " AND k.id = ?";
    $params[] = $kelasId;
}

if (!empty($search)) {
    $query .= " AND (m.nim ILIKE ? OR m.nama_lengkap ILIKE ? OR u.judul_ujian ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$arsip = $stmt->fetchAll();

$exportData = [
    'judul_ujian' => 'Arsip & History Ujian',
    'nama_mk' => 'Laporan Seluruh Reset Ujian',
    'stats' => [
        'total_peserta' => count($arsip),
        'rata_rata' => count($arsip) > 0 ? round(array_sum(array_column($arsip, 'nilai_total')) / count($arsip), 1) : 0,
        'lulus' => count(array_filter($arsip, fn($a) => $a['status_kelulusan'] === 'Lulus'))
    ],
    'hasil' => array_map(function($a) {
        return [
            'nim' => $a['nim'],
            'nama_lengkap' => $a['nama_lengkap'],
            'nama_kelas' => $a['nama_kelas'],
            'nilai_total' => $a['nilai_total']
        ];
    }, $arsip)
];

$jsonData = json_encode($exportData);
$tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
$outputFile = $tempFile . '.pdf';

$command = "python3 export_pdf.py " . escapeshellarg($jsonData) . " " . escapeshellarg($outputFile);
exec($command, $output, $returnVar);

if ($returnVar === 0 && file_exists($outputFile)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Arsip_Hasil_Ujian.pdf"');
    readfile($outputFile);
    unlink($tempFile);
    unlink($outputFile);
    exit;
} else {
    echo "Export failed.";
}