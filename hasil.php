<?php
require_once 'config.php';
require_once 'database.php';

if (!isset($_SESSION['sesi_id']) || !isset($_SESSION['exam_completed'])) {
    header('Location: index.php');
    exit;
}

$sesiId = $_SESSION['sesi_id'];
$ujianId = $_SESSION['ujian_id'];
$scores = $_SESSION['exam_result'] ?? [];
$answers = $_SESSION['answers'] ?? [];

$stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id = ?");
$stmt->execute([$sesiId]);
$sesi = $stmt->fetch();

$stmt = $pdo->prepare("SELECT u.*, mk.nama_mk FROM ujian u JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id WHERE u.id = ?");
$stmt->execute([$ujianId]);
$ujian = $stmt->fetch();

$soalList = getSoalByUjian($pdo, $ujianId);

$jenisSoalLabels = [
    'pg' => 'Pilihan Ganda',
    'tf' => 'Benar/Salah',
    'short' => 'Jawaban Singkat',
    'multiple' => 'Pilihan Ganda Kompleks',
    'matching' => 'Menjodohkan',
    'ordering' => 'Penyusunan Urutan',
    'esai' => 'Esai',
    'studi_kasus' => 'Studi Kasus'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Ujian - <?php echo htmlspecialchars($ujian['judul_ujian']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>Hasil Ujian</h1>
            <p class="subtitle"><?php echo htmlspecialchars($ujian['judul_ujian']); ?></p>
        </div>
    </header>
    
    <div class="container result-container">
        <div class="result-header">
            <h2>Ujian Selesai!</h2>
            <p>Berikut adalah hasil ujian Anda</p>
        </div>
        
        <div class="score-card">
            <div class="total-score"><?php echo $scores['total'] ?? 0; ?></div>
            <div class="score-label">Nilai Total dari <?php echo $ujian['total_nilai']; ?></div>
            <?php 
            $nilaiLulus = $ujian['nilai_lulus'] ?? 60;
            $totalScore = $scores['total'] ?? 0;
            $isLulus = $totalScore >= $nilaiLulus;
            ?>
            <div class="status-badge <?php echo $isLulus ? 'status-pass' : 'status-fail'; ?>" style="margin-top: 10px; font-weight: bold; font-size: 1.2rem; padding: 5px 15px; border-radius: 20px; display: inline-block;">
                STATUS: <?php echo $isLulus ? 'LULUS' : 'TIDAK LULUS'; ?>
            </div>
        </div>
        
        <div class="info-box">
            <h4>Informasi Peserta & Ujian</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span>Nama</span>
                    <span><?php echo htmlspecialchars($_SESSION['nama_peserta']); ?></span>
                </div>
                <div class="info-item">
                    <span>NPM</span>
                    <span><?php echo htmlspecialchars($_SESSION['nim']); ?></span>
                </div>
                <div class="info-item">
                    <span>Program Studi</span>
                    <span><?php echo htmlspecialchars($_SESSION['prodi']); ?></span>
                </div>
                <div class="info-item">
                    <span>Semester</span>
                    <span><?php echo htmlspecialchars($_SESSION['semester'] ?? '-'); ?></span>
                </div>
                <div class="info-item">
                    <span>Mata Kuliah</span>
                    <span><?php echo htmlspecialchars($ujian['nama_mk']); ?></span>
                </div>
                <div class="info-item">
                    <span>Jenis Ujian</span>
                    <span><?php echo htmlspecialchars($ujian['jenis_ujian']); ?></span>
                </div>
                <div class="info-item">
                    <span>Waktu Mulai</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($sesi['waktu_mulai'])); ?></span>
                </div>
                <div class="info-item">
                    <span>Waktu Selesai</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($sesi['waktu_selesai'])); ?></span>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="peserta_logout.php?selesai=1" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> Selesai &amp; Keluar
            </a>
        </div>
    </div>
</body>
</html>
