<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) die("Unauthorized");

$ujianId = (int)($_GET['ujian_id'] ?? 0);
if ($ujianId <= 0) die("Ujian ID required");

$stmt = $pdo->prepare("SELECT u.*, mk.nama_mk FROM ujian u JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id WHERE u.id = ?");
$stmt->execute([$ujianId]);
$ujian = $stmt->fetch();
if (!$ujian) die("Ujian tidak ditemukan");

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$search  = trim($_GET['search'] ?? '');

$query  = "
    SELECT su.nilai_total, m.nim, m.nama_lengkap, k.nama_kelas
    FROM sesi_ujian su
    JOIN mahasiswa m ON su.id_mahasiswa = m.id
    LEFT JOIN kelas k ON m.id_kelas = k.id
    WHERE su.id_ujian = ? AND su.status = 'selesai'
";
$params = [$ujianId];

if ($kelasId > 0) {
    $query .= " AND k.id = ?";
    $params[] = $kelasId;
}
if (!empty($search)) {
    $query .= " AND (m.nim ILIKE ? OR m.nama_lengkap ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY su.nilai_total DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$hasil = $stmt->fetchAll();

$nilaiArr    = array_column($hasil, 'nilai_total');
$nilaiLulusMin = (int)($ujian['nilai_lulus'] ?? 60);
$stats = [
    'total_peserta' => count($hasil),
    'rata_rata'     => count($hasil) > 0 ? round(array_sum($nilaiArr) / count($hasil), 1) : 0,
    'lulus'         => count(array_filter($nilaiArr, fn($n) => $n >= $nilaiLulusMin)),
];

$exportData = [
    'judul_ujian' => $ujian['judul_ujian'],
    'nama_mk'     => $ujian['nama_mk'],
    'nilai_lulus' => $nilaiLulusMin,
    'stats'       => $stats,
    'hasil'       => $hasil,
];

// Tulis JSON ke temp file (hindari batas ARG_MAX untuk data besar)
$jsonFile  = tempnam(sys_get_temp_dir(), 'pdf_data_') . '.json';
$outputFile = tempnam(sys_get_temp_dir(), 'pdf_out_') . '.pdf';
file_put_contents($jsonFile, json_encode($exportData));

$scriptPath = __DIR__ . '/export_pdf.py';
$command    = 'python3 ' . escapeshellarg($scriptPath)
            . ' ' . escapeshellarg($jsonFile)
            . ' ' . escapeshellarg($outputFile);
exec($command . ' 2>&1', $output, $returnVar);

@unlink($jsonFile);

if ($returnVar === 0 && file_exists($outputFile)) {
    $filename = 'Rekap_Hasil_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $ujian['judul_ujian']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($outputFile));
    readfile($outputFile);
    unlink($outputFile);
    exit;
} else {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Export PDF gagal.\n";
    if (!empty($output)) echo implode("\n", $output);
}
