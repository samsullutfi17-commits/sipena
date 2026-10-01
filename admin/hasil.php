<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Auto-expire sessions whose time limit has passed
cleanupExpiredSessions($pdo);

$ujianId = (int)($_GET['ujian_id'] ?? 0);
$kelasId = (int)($_GET['kelas_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$message = '';

// ... (post actions stay the same) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reset_single') {
        $sesiId = (int)($_POST['sesi_id'] ?? 0);
        
        // Archive before delete
        $pdo->prepare("
            INSERT INTO riwayat_sesi_ujian (id_sesi, id_ujian, id_mahasiswa, nilai_total, waktu_selesai, status_kelulusan, alasan_reset, direset_oleh)
            SELECT su.id, su.id_ujian, su.id_mahasiswa, su.nilai_total, su.waktu_selesai, 
                   CASE WHEN su.nilai_total >= COALESCE(u.nilai_lulus, 60) THEN 'Lulus' ELSE 'Tidak Lulus' END,
                   'Reset Manual Individual', ?
            FROM sesi_ujian su
            JOIN ujian u ON su.id_ujian = u.id
            WHERE su.id = ?
        ")->execute([$_SESSION['admin_id'] ?? 0, $sesiId]);

        $pdo->prepare("DELETE FROM jawaban_peserta WHERE id_sesi = ?")->execute([$sesiId]);
        $pdo->prepare("DELETE FROM sesi_ujian WHERE id = ?")->execute([$sesiId]);
        $message = "Ujian peserta berhasil direset dan diarsipkan.";
    } elseif ($action === 'reset_massal') {
        $uId = (int)($_POST['ujian_id'] ?? 0);
        
        // Archive mass reset
        $pdo->prepare("
            INSERT INTO riwayat_sesi_ujian (id_sesi, id_ujian, id_mahasiswa, nilai_total, waktu_selesai, status_kelulusan, alasan_reset, direset_oleh)
            SELECT su.id, su.id_ujian, su.id_mahasiswa, su.nilai_total, su.waktu_selesai, 
                   CASE WHEN su.nilai_total >= COALESCE(u.nilai_lulus, 60) THEN 'Lulus' ELSE 'Tidak Lulus' END,
                   'Reset Massal', ?
            FROM sesi_ujian su
            JOIN ujian u ON su.id_ujian = u.id
            WHERE su.id_ujian = ? AND su.status = 'selesai'
        ")->execute([$_SESSION['admin_id'] ?? 0, $uId]);

        $pdo->prepare("DELETE FROM jawaban_peserta WHERE id_sesi IN (SELECT id FROM sesi_ujian WHERE id_ujian = ?)")->execute([$uId]);
        $pdo->prepare("DELETE FROM sesi_ujian WHERE id_ujian = ?")->execute([$uId]);
        $message = "Semua hasil ujian berhasil direset dan diarsipkan.";
    } elseif ($action === 'delete_single') {
        $sesiId = (int)($_POST['sesi_id'] ?? 0);
        $pdo->prepare("DELETE FROM jawaban_peserta WHERE id_sesi = ?")->execute([$sesiId]);
        $pdo->prepare("DELETE FROM sesi_ujian WHERE id = ?")->execute([$sesiId]);
        $message = "Data hasil ujian berhasil dihapus permanen.";
    }
}

$ujianList = $pdo->query("
    SELECT u.*, mk.nama_mk 
    FROM ujian u 
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id 
    ORDER BY u.created_at DESC
")->fetchAll();

$kelasList = $pdo->query("
    SELECT k.*, ps.nama_prodi 
    FROM kelas k 
    JOIN program_studi ps ON k.id_program_studi = ps.id 
    ORDER BY ps.nama_prodi, k.nama_kelas
")->fetchAll();

$hasilList = [];
$stats = null;
$currentUjian = null;

if ($ujianId > 0) {
    $stmt = $pdo->prepare("SELECT u.*, mk.nama_mk FROM ujian u JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id WHERE u.id = ?");
    $stmt->execute([$ujianId]);
    $currentUjian = $stmt->fetch();
    
    $query = "
        SELECT su.*, m.nim, m.nama_lengkap, k.nama_kelas, k.angkatan, ps.nama_prodi, k.id as id_kelas
        FROM sesi_ujian su
        JOIN mahasiswa m ON su.id_mahasiswa = m.id
        LEFT JOIN kelas k ON m.id_kelas = k.id
        LEFT JOIN program_studi ps ON k.id_program_studi = ps.id
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
    $hasilList = $stmt->fetchAll();
    
    if (!empty($hasilList)) {
        $nilaiArr = array_column($hasilList, 'nilai_total');
        $nilaiLulusMin = $currentUjian['nilai_lulus'] ?? 60;
        $stats = [
            'total_peserta' => count($hasilList),
            'rata_rata' => round(array_sum($nilaiArr) / count($nilaiArr), 1),
            'nilai_tertinggi' => max($nilaiArr),
            'nilai_terendah' => min($nilaiArr),
            'lulus' => count(array_filter($nilaiArr, fn($n) => $n >= $nilaiLulusMin))
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Panel Admin</title>
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
                <p class="subtitle"><?php echo htmlspecialchars($_SESSION['admin_nama']); ?></p>
            </div>
            <a href="logout.php" class="btn btn-accent"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    
    <div class="admin-container">
        <aside class="admin-sidebar">
            <nav class="admin-nav">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Beranda</a>
                <a href="ujian.php" class="nav-item"><i class="fas fa-file-alt"></i> Penilaian</a>
                <a href="soal.php" class="nav-item"><i class="fas fa-question-circle"></i> Bank Soal</a>
                <a href="kisi_kisi.php" class="nav-item"><i class="fas fa-table"></i> Kisi-Kisi</a>
                <a href="hasil.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Laporan</a>
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
                <a href="dashboard.php">Beranda</a> / 
                <span>Laporan</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-chart-bar"></i> Laporan Hasil Penilaian</h2>
                <?php if ($ujianId > 0 && !empty($hasilList)): ?>
                    <div class="btn-group-header">
                        <form method="POST" onsubmit="return confirm('Yakin ingin mereset SEMUA hasil ujian ini? Semua data nilai akan dihapus permanent!');" style="margin: 0;">
                            <input type="hidden" name="action" value="reset_massal">
                            <input type="hidden" name="ujian_id" value="<?php echo $ujianId; ?>">
                            <button type="submit" class="btn btn-delete btn-icon"><i class="fas fa-trash-alt"></i> Reset Massal</button>
                        </form>
                        <a href="export_pdf_api.php?ujian_id=<?php echo $ujianId; ?>&kelas_id=<?php echo $kelasId; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-icon" style="background-color: #e74c3c; color: white;"><i class="fas fa-file-pdf"></i> Export PDF</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div class="filter-bar card" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <label style="font-size: 0.8rem;">Pilih Penilaian</label>
                        <select name="ujian_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Semua Penilaian --</option>
                            <?php foreach ($ujianList as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $ujianId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['judul_ujian'] . ' (' . $u['nama_mk'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <label style="font-size: 0.8rem;">Filter Grup</label>
                        <select name="kelas_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Semua Grup --</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?php echo $k['id']; ?>" <?php echo $k['id'] == $kelasId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($k['nama_prodi'] . ' - ' . $k['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0; flex: 2; min-width: 250px;">
                        <label style="font-size: 0.8rem;">Cari Nama / NIM</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Masukkan Nama atau NIM..." style="padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; flex: 1;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if ($currentUjian && $stats): ?>
                <div class="card" style="margin-bottom: 20px;">
                    <h3><?php echo htmlspecialchars($currentUjian['judul_ujian']); ?></h3>
                    <p>Materi: <?php echo htmlspecialchars($currentUjian['nama_mk']); ?></p>
                </div>
                
                <div class="hasil-summary">
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $stats['total_peserta']; ?></div>
                        <div class="summary-label">Total Peserta</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $stats['rata_rata']; ?></div>
                        <div class="summary-label">Rata-rata</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $stats['nilai_tertinggi']; ?></div>
                        <div class="summary-label">Tertinggi</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $stats['nilai_terendah']; ?></div>
                        <div class="summary-label">Terendah</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?php echo $stats['lulus']; ?></div>
                        <div class="summary-label">Lulus (&ge;<?php echo $currentUjian['nilai_lulus'] ?? 60; ?>)</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($ujianId > 0): ?>
                <div class="card">
                    <?php if (empty($hasilList)): ?>
                        <div class="empty-state">
                            <h3>Belum Ada Peserta</h3>
                            <p>Belum ada peserta yang menyelesaikan penilaian ini</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Peringkat</th>
                                    <th>NPM</th>
                                    <th>Nama</th>
                                    <th>Program</th>
                                    <th>Grup</th>
                                    <th>Nilai</th>
                                    <th>Waktu Selesai</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hasilList as $idx => $h): ?>
                                    <tr>
                                        <td><?php echo $idx + 1; ?></td>
                                        <td><?php echo htmlspecialchars($h['nim']); ?></td>
                                        <td><?php echo htmlspecialchars($h['nama_lengkap']); ?></td>
                                        <td><?php echo htmlspecialchars($h['nama_prodi'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(($h['nama_kelas'] ?? '-') . ' (' . ($h['angkatan'] ?? '-') . ')'); ?></td>
                                        <td><strong><?php echo $h['nilai_total']; ?></strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($h['waktu_selesai'])); ?></td>
                                        <td>
                                            <?php 
                                            $nilaiLulusUjian = (int)($currentUjian['nilai_lulus'] ?? 60);
                                            $totalNilaiPeserta = (int)$h['nilai_total'];
                                            $isLulusPeserta = $totalNilaiPeserta >= $nilaiLulusUjian;
                                            ?>
                                            <span class="badge <?php echo $isLulusPeserta ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $isLulusPeserta ? 'Lulus' : 'Tidak Lulus'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons" style="display: flex; gap: 5px;">
                                                <a href="detail_hasil.php?sesi_id=<?php echo $h['id']; ?>" class="btn btn-sm btn-view" title="Lihat Detail"><i class="fas fa-eye"></i> Detail</a>
                                                <form method="POST" onsubmit="return confirm('Yakin ingin mereset ujian peserta ini?');" style="margin: 0;">
                                                    <input type="hidden" name="action" value="reset_single">
                                                    <input type="hidden" name="sesi_id" value="<?php echo $h['id']; ?>">
                                                    <button type="submit" class="btn btn-sm" title="Reset Ujian" style="background-color: var(--accent); color: white;"><i class="fas fa-undo"></i> Reset</button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Yakin ingin MENGHAPUS PERMANEN hasil ujian peserta ini?');" style="margin: 0;">
                                                    <input type="hidden" name="action" value="delete_single">
                                                    <input type="hidden" name="sesi_id" value="<?php echo $h['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-delete" title="Hapus Permanen"><i class="fas fa-trash"></i> Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <h3>Pilih Penilaian</h3>
                        <p>Pilih penilaian dari dropdown di atas untuk melihat laporan hasil</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
