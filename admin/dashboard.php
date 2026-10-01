<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Auto-expire sessions whose time limit has passed
cleanupExpiredSessions($pdo);

$totalUjian = $pdo->query("SELECT COUNT(*) FROM ujian")->fetchColumn();
$totalSoal = $pdo->query("SELECT COUNT(*) FROM soal")->fetchColumn();
$totalPeserta = $pdo->query("SELECT COUNT(*) FROM sesi_ujian WHERE status = 'selesai'")->fetchColumn();
$totalMahasiswa = $pdo->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();

$recentExams = $pdo->query("
    SELECT u.*, mk.nama_mk, COUNT(su.id) as peserta_count
    FROM ujian u
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
    LEFT JOIN sesi_ujian su ON u.id = su.id_ujian AND su.status = 'selesai'
    GROUP BY u.id, mk.nama_mk
    ORDER BY u.created_at DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Panel Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin-sidebar.js" defer></script>
</head>
<body>
    <header class="header">
        <div class="container header-flex">
            <div>
                <h1>SIPENA – Sistem Penilaian Akademik</h1>
                <p class="subtitle">Selamat datang, <?php echo htmlspecialchars($_SESSION['admin_nama']); ?></p>
            </div>
            <a href="logout.php" class="btn btn-accent"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    
    <div class="admin-container">
        <aside class="admin-sidebar">
            <nav class="admin-nav">
                <a href="dashboard.php" class="nav-item active"><i class="fas fa-home"></i> Beranda</a>
                <a href="ujian.php" class="nav-item"><i class="fas fa-file-alt"></i> Penilaian</a>
                <a href="soal.php" class="nav-item"><i class="fas fa-question-circle"></i> Bank Soal</a>
                <a href="kisi_kisi.php" class="nav-item"><i class="fas fa-table"></i> Kisi-Kisi</a>
                <a href="hasil.php" class="nav-item"><i class="fas fa-chart-bar"></i> Laporan</a>
                <a href="arsip.php" class="nav-item"><i class="fas fa-history"></i> Riwayat</a>
                <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                <a href="fakultas.php" class="nav-item"><i class="fas fa-building"></i> Organisasi</a>
                <a href="prodi.php" class="nav-item"><i class="fas fa-sitemap"></i> Program</a>
                <a href="kelas.php" class="nav-item"><i class="fas fa-users-rectangle"></i> Kelas / Grup</a>
                <a href="matkul.php" class="nav-item"><i class="fas fa-book"></i> Materi</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
                <?php endif; ?>
            </nav>
        </aside>
        
        <main class="admin-main">
            <nav class="breadcrumb">
                <span>Beranda</span>
            </nav>
            
            <h2><i class="fas fa-home"></i> Beranda</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalUjian; ?></div>
                    <div class="stat-label">Total Penilaian</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalSoal; ?></div>
                    <div class="stat-label">Total Soal</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalPeserta; ?></div>
                    <div class="stat-label">Penilaian Selesai</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalMahasiswa; ?></div>
                    <div class="stat-label">Total Peserta</div>
                </div>
            </div>
            
            <div class="card" style="margin-top: 30px;">
                <h3>Penilaian Terbaru</h3>
                <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Materi / Program</th>
                            <th>Jenis</th>
                            <th>Durasi</th>
                            <th>Peserta</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentExams)): ?>
                            <tr><td colspan="6" style="text-align: center;">Belum ada ujian</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentExams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['judul_ujian']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['nama_mk']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['jenis_ujian']); ?></td>
                                    <td><?php echo $exam['durasi_menit']; ?> menit</td>
                                    <td><?php echo $exam['peserta_count']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $exam['aktif'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $exam['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
