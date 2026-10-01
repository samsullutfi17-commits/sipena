<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$sesiId = (int)($_GET['sesi_id'] ?? 0);
if ($sesiId <= 0) die("ID Sesi tidak valid");

// Ambil data sesi
$stmt = $pdo->prepare("
    SELECT su.*, m.nim, m.nama_lengkap, u.judul_ujian, mk.nama_mk, ps.nama_prodi, k.nama_kelas, k.angkatan
    FROM sesi_ujian su
    JOIN mahasiswa m ON su.id_mahasiswa = m.id
    JOIN ujian u ON su.id_ujian = u.id
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
    LEFT JOIN kelas k ON m.id_kelas = k.id
    LEFT JOIN program_studi ps ON k.id_program_studi = ps.id
    WHERE su.id = ?
");
$stmt->execute([$sesiId]);
$sesi = $stmt->fetch();

if (!$sesi) {
    // Cek di riwayat jika tidak ada di sesi aktif
    $stmt = $pdo->prepare("
        SELECT r.*, m.nim, m.nama_lengkap, u.judul_ujian, mk.nama_mk, ps.nama_prodi, k.nama_kelas
        FROM riwayat_sesi_ujian r
        JOIN mahasiswa m ON r.id_mahasiswa = m.id
        JOIN ujian u ON r.id_ujian = u.id
        JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
        LEFT JOIN kelas k ON m.id_kelas = k.id
        LEFT JOIN program_studi ps ON k.id_program_studi = ps.id
        WHERE r.id_sesi = ?
    ");
    $stmt->execute([$sesiId]);
    $sesi = $stmt->fetch();
    if (!$sesi) die("Data tidak ditemukan");
    $isArsip = true;
} else {
    $isArsip = false;
}

// Ambil jawaban detail
$stmt = $pdo->prepare("
    SELECT jp.*, s.pertanyaan, s.jenis_soal, s.pembahasan, s.poin as poin_maks
    FROM jawaban_peserta jp
    JOIN soal s ON jp.id_soal = s.id
    WHERE jp.id_sesi = ?
    ORDER BY s.urutan
");
$stmt->execute([$sesiId]);
$jawaban = $stmt->fetchAll();

$source = $_GET['source'] ?? 'hasil';
$backUrl = ($source === 'arsip') ? 'arsip.php' : 'hasil.php?ujian_id=' . $sesi['id_ujian'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Jawaban - Panel Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin-sidebar.js" defer></script>
</head>
<body>
    <header class="header">
        <div class="container header-flex">
            <h1>Detail Jawaban Peserta</h1>
            <a href="<?php echo $backUrl; ?>" class="btn btn-accent"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
    </header>
    
    <div class="admin-container">
        <main class="admin-main" style="width: 100%; max-width: 1000px; margin: 0 auto;">
            <div class="card" style="margin-bottom: 30px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <p><strong>NPM:</strong> <?php echo htmlspecialchars($sesi['nim']); ?></p>
                        <p><strong>Nama:</strong> <?php echo htmlspecialchars($sesi['nama_lengkap']); ?></p>
                        <p><strong>Kelas:</strong> <?php echo htmlspecialchars($sesi['nama_kelas'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p><strong>Ujian:</strong> <?php echo htmlspecialchars($sesi['judul_ujian']); ?></p>
                        <p><strong>Mata Kuliah:</strong> <?php echo htmlspecialchars($sesi['nama_mk']); ?></p>
                        <p><strong>Nilai Total:</strong> <span style="font-size: 1.5rem; font-weight: bold; color: var(--primary);"><?php echo $sesi['nilai_total']; ?></span></p>
                    </div>
                </div>
                <?php if ($isArsip): ?>
                    <div class="badge badge-warning" style="margin-top: 10px;">DATA ARSIP (TELAH DIRESET)</div>
                <?php endif; ?>
            </div>

            <div class="answer-review">
                <?php foreach ($jawaban as $idx => $j): ?>
                    <div class="review-item <?php echo $j['nilai'] > 0 ? 'correct' : 'incorrect'; ?>">
                        <div class="review-header">
                            <span class="review-number">Soal <?php echo $idx + 1; ?></span>
                            <span class="review-status <?php echo $j['nilai'] > 0 ? 'correct' : 'incorrect'; ?>">
                                Skor: <?php echo $j['nilai']; ?> / <?php echo $j['poin_maks']; ?>
                            </span>
                        </div>
                        <div class="review-question"><?php echo nl2br(htmlspecialchars($j['pertanyaan'])); ?></div>
                        <div class="answer-box user">
                            <strong>Jawaban Peserta:</strong>
                            <?php echo nl2br(htmlspecialchars($j['jawaban'])); ?>
                        </div>
                        <?php if (!empty($j['pembahasan'])): ?>
                            <div class="review-explanation">
                                <strong>Pembahasan:</strong>
                                <?php echo htmlspecialchars($j['pembahasan']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>