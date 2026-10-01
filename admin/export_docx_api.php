<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized");
}

$ujianId = (int)($_GET['ujian_id'] ?? 0);

if ($ujianId <= 0) {
    die("Ujian ID required");
}

// Fetch ujian with extended info: program studi list and SKS
$stmt = $pdo->prepare("
    SELECT u.*, mk.nama_mk, mk.sks,
        STRING_AGG(DISTINCT ps.nama_prodi, ' / ') AS nama_prodi_list
    FROM ujian u
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
    LEFT JOIN ujian_kelas uk ON uk.id_ujian = u.id
    LEFT JOIN kelas k ON k.id = uk.id_kelas
    LEFT JOIN program_studi ps ON ps.id = k.id_program_studi
    WHERE u.id = ?
    GROUP BY u.id, mk.nama_mk, mk.sks
");
$stmt->execute([$ujianId]);
$ujian = $stmt->fetch();

if (!$ujian) {
    die("Ujian not found");
}

$soalList = getSoalByUjian($pdo, $ujianId);

$jenisSoalLabels = [
    'pg'          => 'Pilihan Ganda',
    'tf'          => 'Benar/Salah',
    'short'       => 'Jawaban Singkat',
    'multiple'    => 'Pilihan Ganda Kompleks',
    'matching'    => 'Menjodohkan',
    'ordering'    => 'Penyusunan Urutan',
    'esai'        => 'Esai',
    'studi_kasus' => 'Studi Kasus',
];

foreach ($soalList as &$soal) {
    $soal['jenis_label'] = $jenisSoalLabels[$soal['jenis_soal']] ?? $soal['jenis_soal'];
    if (!empty($soal['data_tambahan']) && is_string($soal['data_tambahan'])) {
        $soal['data_tambahan_parsed'] = json_decode($soal['data_tambahan'], true);
    } elseif (!empty($soal['data_tambahan']) && is_array($soal['data_tambahan'])) {
        $soal['data_tambahan_parsed'] = $soal['data_tambahan'];
    }
}
unset($soal);

// Load institution settings
$settingsFile = __DIR__ . '/pengaturan.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?? [];
}

// Build exam identity for kop surat
$durasi   = $ujian['durasi'] ?? 90;
$sksVal   = $ujian['sks'] ?? '-';
$examInfo = [
    'hari_tanggal' => date('l, d F Y'),
    'waktu'        => $durasi . ' Menit',
    'pengampu'     => $settings['pengampu_default'] ?: ($_SESSION['admin_nama'] ?? '-'),
    'nama_prodi'   => $ujian['nama_prodi_list'] ?? '-',
    'nama_mk'      => $ujian['nama_mk'],
    'sks'          => ($sksVal !== '-' ? $sksVal . ' SKS' : '-'),
];

$exportData = [
    'judul_ujian' => $ujian['judul_ujian'],
    'nama_mk'     => $ujian['nama_mk'],
    'settings'    => $settings,
    'exam_info'   => $examInfo,
    'soal'        => $soalList,
];

$jsonData   = json_encode($exportData, JSON_UNESCAPED_UNICODE);
$tempFile   = tempnam(sys_get_temp_dir(), 'docx_');
$outputFile = $tempFile . '.docx';

$command = "python3 export_docx.py " . escapeshellarg($jsonData) . " " . escapeshellarg($outputFile);
exec($command, $output, $returnVar);

if ($returnVar === 0 && file_exists($outputFile)) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="Bank_Soal_' . str_replace(' ', '_', $ujian['judul_ujian']) . '.docx"');
    readfile($outputFile);
    unlink($tempFile);
    unlink($outputFile);
    exit;
} else {
    echo "Export gagal. Error: " . implode("\n", $output);
    if (file_exists($tempFile)) unlink($tempFile);
}
