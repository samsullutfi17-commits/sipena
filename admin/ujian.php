<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Auto-expire sessions whose time limit has passed
cleanupExpiredSessions($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $judul = trim($_POST['judul_ujian'] ?? '');
        $matkulId = (int)($_POST['id_mata_kuliah'] ?? 0);
        $jenis = $_POST['jenis_ujian'] ?? 'UTS';
        $durasi = (int)($_POST['durasi_menit'] ?? 90);
        $totalNilai = (int)($_POST['total_nilai'] ?? 100);
        $nilaiLulus = (int)($_POST['nilai_lulus'] ?? 60);
        $kelasIds = $_POST['kelas_ids'] ?? [];
        
        if (!empty($judul) && $matkulId > 0) {
            $stmt = $pdo->prepare("INSERT INTO ujian (judul_ujian, id_mata_kuliah, jenis_ujian, durasi_menit, total_nilai, nilai_lulus) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([$judul, $matkulId, $jenis, $durasi, $totalNilai, $nilaiLulus]);
            $ujianId = $stmt->fetch()['id'];
            
            foreach ($kelasIds as $kelasId) {
                $pdo->prepare("INSERT INTO ujian_kelas (id_ujian, id_kelas) VALUES (?, ?)")->execute([$ujianId, $kelasId]);
            }
            
            $message = 'Ujian berhasil ditambahkan!';
        } else {
            $error = 'Judul ujian dan mata kuliah wajib diisi!';
        }
    } elseif ($action === 'update') {
        $ujianId = (int)($_POST['ujian_id'] ?? 0);
        $judul = trim($_POST['judul_ujian'] ?? '');
        $matkulId = (int)($_POST['id_mata_kuliah'] ?? 0);
        $jenis = $_POST['jenis_ujian'] ?? 'UTS';
        $durasi = (int)($_POST['durasi_menit'] ?? 90);
        $totalNilai = (int)($_POST['total_nilai'] ?? 100);
        $nilaiLulus = (int)($_POST['nilai_lulus'] ?? 60);
        $kelasIds = $_POST['kelas_ids'] ?? [];
        
        if (!empty($judul) && $matkulId > 0 && $ujianId > 0) {
            $stmt = $pdo->prepare("UPDATE ujian SET judul_ujian = ?, id_mata_kuliah = ?, jenis_ujian = ?, durasi_menit = ?, total_nilai = ?, nilai_lulus = ? WHERE id = ?");
            $stmt->execute([$judul, $matkulId, $jenis, $durasi, $totalNilai, $nilaiLulus, $ujianId]);
            
            $pdo->prepare("DELETE FROM ujian_kelas WHERE id_ujian = ?")->execute([$ujianId]);
            foreach ($kelasIds as $kelasId) {
                $pdo->prepare("INSERT INTO ujian_kelas (id_ujian, id_kelas) VALUES (?, ?)")->execute([$ujianId, $kelasId]);
            }
            
            $message = 'Ujian berhasil diperbarui!';
        }
    } elseif ($action === 'toggle') {
        $ujianId = (int)($_POST['ujian_id'] ?? 0);
        $pdo->prepare("UPDATE ujian SET aktif = NOT aktif WHERE id = ?")->execute([$ujianId]);
        $message = 'Status ujian berhasil diubah!';
    } elseif ($action === 'delete') {
        $ujianId = (int)($_POST['ujian_id'] ?? 0);
        $pdo->prepare("DELETE FROM ujian WHERE id = ?")->execute([$ujianId]);
        $message = 'Ujian berhasil dihapus!';
    }
}

$ujianList = $pdo->query("
    SELECT u.*, mk.nama_mk, mk.kode_mk,
           (SELECT COUNT(*) FROM soal WHERE id_ujian = u.id) as jumlah_soal,
           (SELECT COUNT(*) FROM sesi_ujian WHERE id_ujian = u.id AND status = 'selesai') as peserta_count
    FROM ujian u
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
    ORDER BY u.created_at DESC
")->fetchAll();

$matkulList = $pdo->query("SELECT * FROM mata_kuliah ORDER BY nama_mk")->fetchAll();
$kelasList = $pdo->query("
    SELECT k.*, ps.nama_prodi 
    FROM kelas k 
    JOIN program_studi ps ON k.id_program_studi = ps.id 
    ORDER BY ps.nama_prodi, k.angkatan DESC, k.nama_kelas
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian - Panel Admin</title>
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
                <a href="ujian.php" class="nav-item active"><i class="fas fa-file-alt"></i> Penilaian</a>
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
                <a href="dashboard.php">Beranda</a> / 
                <span>Penilaian</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-file-alt"></i> Penilaian</h2>
                <button class="btn btn-primary btn-icon" onclick="showModal()"><i class="fas fa-plus"></i> Tambah Penilaian</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Materi / Program</th>
                            <th>Jenis</th>
                            <th>Durasi</th>
                            <th>Jumlah Soal</th>
                            <th>Peserta</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ujianList)): ?>
                            <tr><td colspan="9" style="text-align: center;">Belum ada ujian</td></tr>
                        <?php else: ?>
                            <?php foreach ($ujianList as $ujian): 
                                $stmt = $pdo->prepare("SELECT id_kelas FROM ujian_kelas WHERE id_ujian = ?");
                                $stmt->execute([$ujian['id']]);
                                $ujian['kelas_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ujian['judul_ujian']); ?></td>
                                    <td><?php echo htmlspecialchars($ujian['kode_mk'] . ' - ' . $ujian['nama_mk']); ?></td>
                                    <td><?php echo htmlspecialchars($ujian['jenis_ujian']); ?></td>
                                    <td>
                                        <div style="font-size: 0.9rem;">
                                            <div><i class="fas fa-clock"></i> <?php echo $ujian['durasi_menit']; ?> menit</div>
                                            <div><i class="fas fa-check-double"></i> Lulus: <?php echo $ujian['nilai_lulus'] ?? 60; ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo $ujian['jumlah_soal']; ?></td>
                                    <td><?php echo $ujian['peserta_count']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $ujian['aktif'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $ujian['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="soal.php?ujian_id=<?php echo $ujian['id']; ?>" class="btn btn-sm btn-view" title="Bank Soal"><i class="fas fa-question-circle"></i></a>
                                            <button type="button" class="btn btn-sm btn-edit" onclick='editUjian(<?php echo json_encode($ujian); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="ujian_id" value="<?php echo $ujian['id']; ?>">
                                                <button type="submit" class="btn btn-sm" style="background: var(--accent); color: white;" title="<?php echo $ujian['aktif'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                                    <i class="fas <?php echo $ujian['aktif'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus ujian ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="ujian_id" value="<?php echo $ujian['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-delete" title="Hapus"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
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
    
    <div class="modal-overlay" id="modal">
        <div class="modal">
            <h3 id="modalTitle">Tambah Penilaian Baru</h3>
            <form method="POST" id="ujianForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="ujian_id" id="ujianIdField" value="">
                
                <div class="form-group">
                    <label>Judul Penilaian</label>
                    <input type="text" name="judul_ujian" id="judulField" required placeholder="Contoh: UTS Etika Profesi">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Materi</label>
                        <select name="id_mata_kuliah" id="matkulField" required class="form-select">
                            <option value="">-- Pilih Materi --</option>
                            <?php foreach ($matkulList as $mk): ?>
                                <option value="<?php echo $mk['id']; ?>"><?php echo htmlspecialchars($mk['kode_mk'] . ' - ' . $mk['nama_mk']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Jenis</label>
                        <select name="jenis_ujian" id="jenisField" class="form-select">
                            <option value="UTS">UTS</option>
                            <option value="UAS">UAS</option>
                            <option value="Kuis">Kuis</option>
                            <option value="Remedial">Remedial</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Durasi (menit)</label>
                        <input type="number" name="durasi_menit" id="durasiField" value="90" min="10" max="300">
                    </div>
                    
                    <div class="form-group">
                        <label>Batas Kelulusan</label>
                        <input type="number" name="nilai_lulus" id="lulusField" value="60" min="0" max="100">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Total Nilai Maksimal</label>
                    <input type="number" name="total_nilai" id="totalField" value="100" min="10" max="1000">
                </div>
                
                <div class="form-group">
                    <label>Grup yang Mengikuti</label>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid var(--border-color); padding: 10px; border-radius: 8px;" id="kelasCheckboxes">
                        <?php foreach ($kelasList as $kelas): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="kelas_ids[]" value="<?php echo $kelas['id']; ?>" class="kelas-checkbox">
                                <?php echo htmlspecialchars($kelas['nama_prodi'] . ' - ' . $kelas['nama_kelas'] . ' (' . $kelas['angkatan'] . ')'); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="hideModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showModal(isEdit = false) {
            document.getElementById('modal').classList.add('active');
            if (!isEdit) {
                document.getElementById('modalTitle').innerText = 'Tambah Ujian Baru';
                document.getElementById('formAction').value = 'create';
                document.getElementById('ujianIdField').value = '';
                document.getElementById('ujianForm').reset();
                // Uncheck all class checkboxes
                document.querySelectorAll('.kelas-checkbox').forEach(cb => cb.checked = false);
            }
        }
        function hideModal() {
            document.getElementById('modal').classList.remove('active');
        }

        function editUjian(ujian) {
            showModal(true);
            document.getElementById('modalTitle').innerText = 'Edit Penilaian';
            document.getElementById('formAction').value = 'update';
            document.getElementById('ujianIdField').value = ujian.id;
            
            document.getElementById('judulField').value = ujian.judul_ujian;
            document.getElementById('matkulField').value = ujian.id_mata_kuliah;
            document.getElementById('jenisField').value = ujian.jenis_ujian;
            document.getElementById('durasiField').value = ujian.durasi_menit;
            document.getElementById('lulusField').value = ujian.nilai_lulus || 60;
            document.getElementById('totalField').value = ujian.total_nilai;
            
            // Set checkboxes for classes
            const kelasIds = ujian.kelas_ids || [];
            document.querySelectorAll('.kelas-checkbox').forEach(cb => {
                cb.checked = kelasIds.includes(parseInt(cb.value)) || kelasIds.includes(cb.value.toString());
            });
        }
    </script>
</body>
</html>
