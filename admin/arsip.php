<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$kelasId = (int)($_GET['kelas_id'] ?? 0);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_arsip') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM riwayat_sesi_ujian WHERE id = ?")->execute([$id]);
        $message = "Arsip berhasil dihapus.";
    } elseif ($action === 'clear_arsip') {
        $pdo->query("DELETE FROM riwayat_sesi_ujian");
        $message = "Seluruh arsip telah dibersihkan.";
    }
}

$query = "
    SELECT r.*, m.nim, m.nama_lengkap, u.judul_ujian, mk.nama_mk, ps.nama_prodi, k.nama_kelas
    FROM riwayat_sesi_ujian r
    JOIN mahasiswa m ON r.id_mahasiswa = m.id
    JOIN ujian u ON r.id_ujian = u.id
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
    LEFT JOIN kelas k ON m.id_kelas = k.id
    LEFT JOIN program_studi ps ON k.id_program_studi = ps.id
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
$arsipList = $stmt->fetchAll();

$kelasList = $pdo->query("SELECT k.*, ps.nama_prodi FROM kelas k JOIN program_studi ps ON k.id_program_studi = ps.id ORDER BY ps.nama_prodi, k.nama_kelas")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat - Panel Admin</title>
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
                <a href="hasil.php" class="nav-item"><i class="fas fa-chart-bar"></i> Laporan</a>
                <a href="arsip.php" class="nav-item active"><i class="fas fa-history"></i> Riwayat</a>
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
                <span>Riwayat</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-history"></i> Riwayat Reset Penilaian</h2>
                <div class="btn-group-header">
                    <form method="POST" onsubmit="return confirm('Yakin ingin membersihkan SEMUA arsip? Tindakan ini tidak dapat dibatalkan!');" style="margin: 0;">
                        <input type="hidden" name="action" value="clear_arsip">
                        <button type="submit" class="btn btn-delete btn-icon"><i class="fas fa-broom"></i> Bersihkan Arsip</button>
                    </form>
                    <a href="export_arsip_pdf.php?kelas_id=<?php echo $kelasId; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-icon" style="background-color: #e74c3c; color: white;"><i class="fas fa-file-pdf"></i> Export PDF</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div class="filter-bar card" style="margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
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
                        <label style="font-size: 0.8rem;">Cari Data</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Cari Nama, NPM, atau Ujian..." style="flex: 1; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
                            <?php if ($search || $kelasId): ?>
                                <a href="arsip.php" class="btn">Reset</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <?php if (empty($arsipList)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px;"></i>
                        <h3>Belum ada data arsip</h3>
                        <p>Data hasil ujian yang direset akan muncul di sini</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Waktu Reset</th>
                                <th>NPM / Nama</th>
                                <th>Penilaian / Materi</th>
                                <th>Nilai Sblmnya</th>
                                <th>Status</th>
                                <th>Alasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($arsipList as $a): ?>
                                <tr>
                                    <td style="font-size: 0.85rem;">
                                        <?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($a['nim']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($a['nama_lengkap']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($a['judul_ujian']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($a['nama_mk']); ?></small>
                                    </td>
                                    <td style="text-align: center;"><strong><?php echo $a['nilai_total']; ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo $a['status_kelulusan'] === 'Lulus' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo htmlspecialchars($a['status_kelulusan']); ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($a['alasan_reset']); ?></small></td>
                                    <td>
                                        <div class="action-buttons" style="display: flex; gap: 5px;">
                                            <a href="detail_hasil.php?sesi_id=<?php echo $a['id_sesi']; ?>&source=arsip" class="btn btn-sm btn-view" title="Lihat Detail"><i class="fas fa-eye"></i> Detail</a>
                                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus data arsip ini?');" style="margin: 0;">
                                                <input type="hidden" name="action" value="delete_arsip">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-delete" title="Hapus"><i class="fas fa-trash"></i></button>
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
        </main>
    </div>
</body>
</html>