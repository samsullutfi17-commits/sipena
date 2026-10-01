<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['admin_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $kode = trim($_POST['kode_mk'] ?? '');
        $nama = trim($_POST['nama_mk'] ?? '');
        $sks = (int)($_POST['sks'] ?? 3);
        $kelasIds = $_POST['kelas_ids'] ?? [];
        
        if (!empty($kode) && !empty($nama)) {
            $existing = $pdo->prepare("SELECT id FROM mata_kuliah WHERE kode_mk = ?");
            $existing->execute([$kode]);
            if ($existing->fetch()) {
                $error = 'Kode mata kuliah sudah digunakan!';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO mata_kuliah (kode_mk, nama_mk, sks) VALUES (?, ?, ?)");
                    $stmt->execute([$kode, $nama, $sks]);
                    $matkulId = $pdo->lastInsertId();
                    
                    // Kita bisa simpan relasi matkul-kelas di tabel baru atau handle lewat ujian.
                    // Sesuai permintaan user, kita tambahkan tabel relasi mata_kuliah_kelas
                    foreach ($kelasIds as $kelasId) {
                        $stmtRel = $pdo->prepare("INSERT INTO mata_kuliah_kelas (id_mata_kuliah, id_kelas) VALUES (?, ?)");
                        $stmtRel->execute([$matkulId, $kelasId]);
                    }
                    
                    $pdo->commit();
                    $message = 'Mata Kuliah berhasil ditambahkan!';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Gagal menyimpan: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Kode dan nama mata kuliah wajib diisi!';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_mk'] ?? '');
        $nama = trim($_POST['nama_mk'] ?? '');
        $sks = (int)($_POST['sks'] ?? 3);
        $kelasIds = $_POST['kelas_ids'] ?? [];
        
        if ($id > 0 && !empty($kode) && !empty($nama)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE mata_kuliah SET kode_mk = ?, nama_mk = ?, sks = ? WHERE id = ?");
                $stmt->execute([$kode, $nama, $sks, $id]);
                
                // Update relasi
                $pdo->prepare("DELETE FROM mata_kuliah_kelas WHERE id_mata_kuliah = ?")->execute([$id]);
                foreach ($kelasIds as $kelasId) {
                    $stmtRel = $pdo->prepare("INSERT INTO mata_kuliah_kelas (id_mata_kuliah, id_kelas) VALUES (?, ?)");
                    $stmtRel->execute([$id, $kelasId]);
                }
                
                $pdo->commit();
                $message = 'Mata Kuliah berhasil diupdate!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal update: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM mata_kuliah WHERE id = ?")->execute([$id]);
        $message = 'Mata Kuliah berhasil dihapus!';
    }
}

$search = trim($_GET['search'] ?? '');
$searchQuery = '';
$params = [];

if (!empty($search)) {
    $searchQuery = "WHERE mk.nama_mk ILIKE ? OR mk.kode_mk ILIKE ?";
    $params = ["%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT mk.*, 
           (SELECT COUNT(*) FROM ujian WHERE id_mata_kuliah = mk.id) as jumlah_ujian,
           (SELECT STRING_AGG(k.nama_kelas, ', ') 
            FROM mata_kuliah_kelas mkk 
            JOIN kelas k ON mkk.id_kelas = k.id 
            WHERE mkk.id_mata_kuliah = mk.id) as kelas_list
    FROM mata_kuliah mk
    $searchQuery
    ORDER BY mk.nama_mk
");
$stmt->execute($params);
$matkulList = $stmt->fetchAll();

$kelasList = $pdo->query("SELECT k.*, ps.nama_prodi FROM kelas k JOIN program_studi ps ON k.id_program_studi = ps.id ORDER BY ps.nama_prodi, k.nama_kelas")->fetchAll();

$totalMatkul = $pdo->query("SELECT COUNT(*) FROM mata_kuliah")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi - Panel Admin</title>
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
                <a href="arsip.php" class="nav-item"><i class="fas fa-history"></i> Riwayat</a>
                <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                <a href="fakultas.php" class="nav-item"><i class="fas fa-building"></i> Organisasi</a>
                <a href="prodi.php" class="nav-item"><i class="fas fa-sitemap"></i> Program</a>
                <a href="kelas.php" class="nav-item"><i class="fas fa-users-rectangle"></i> Kelas / Grup</a>
                <a href="matkul.php" class="nav-item active"><i class="fas fa-book"></i> Materi</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
                <?php endif; ?>
            </nav>
        </aside>
        
        <main class="admin-main">
            <nav class="breadcrumb">
                <a href="dashboard.php">Beranda</a> / 
                <span>Materi</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-book"></i> Manajemen Materi</h2>
                <button class="btn btn-primary btn-icon" onclick="showModal()">
                    <i class="fas fa-plus"></i> Tambah Materi
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="search-bar">
                <div style="display: flex; gap: 10px; flex: 1; align-items: center;">
                    <div style="position: relative; flex: 1; max-width: 400px;">
                        <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                        <input type="text" id="liveSearch" placeholder="Ketik untuk mencari materi..." style="padding-left: 38px;">
                    </div>
                    <span id="searchCount" style="color: #666; font-size: 0.9rem;"></span>
                </div>
                <span class="total-info">Total: <strong><?php echo $totalMatkul; ?></strong> Materi</span>
            </div>
            
            <div class="card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Materi</th>
                                <th>Durasi / Bobot</th>
                                <th>Grup</th>
                                <th>Jumlah Penilaian</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($matkulList)): ?>
                                <tr><td colspan="5" style="text-align: center;">Belum ada mata kuliah</td></tr>
                            <?php else: ?>
                                <?php foreach ($matkulList as $mk): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($mk['kode_mk']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mk['nama_mk']); ?></td>
                                        <td><?php echo $mk['sks']; ?></td>
                                        <td>
                                            <?php if ($mk['kelas_list']): ?>
                                                <small><?php echo htmlspecialchars($mk['kelas_list']); ?></small>
                                            <?php else: ?>
                                                <span class="badge badge-success">Semua Kelas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($mk['jumlah_ujian'] > 0): ?>
                                                <span class="badge-count active"><?php echo $mk['jumlah_ujian']; ?></span>
                                            <?php else: ?>
                                                <span class="badge-count empty">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-edit btn-icon" onclick="editItem(<?php echo $mk['id']; ?>, '<?php echo htmlspecialchars($mk['kode_mk'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($mk['nama_mk'], ENT_QUOTES); ?>', <?php echo $mk['sks']; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-delete btn-icon" onclick="confirmDelete(<?php echo $mk['id']; ?>, '<?php echo htmlspecialchars($mk['nama_mk'], ENT_QUOTES); ?>', <?php echo $mk['jumlah_ujian']; ?>)">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
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
            <h3 id="modalTitle"><i class="fas fa-book"></i> Tambah Materi</h3>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="form-group">
                    <label>Kode</label>
                    <input type="text" name="kode_mk" id="kode_mk" required placeholder="Contoh: MAT-001">
                </div>
                
                <div class="form-group">
                    <label>Nama Materi</label>
                    <input type="text" name="nama_mk" id="nama_mk" required placeholder="Contoh: Etika Profesi">
                </div>
                
                <div class="form-group">
                    <label>Durasi / Bobot</label>
                    <input type="number" name="sks" id="sks" value="3" min="1" max="6">
                </div>
                
                <div class="form-group">
                    <label>Grup yang Tersedia</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                        <?php foreach ($kelasList as $k): ?>
                            <div style="margin-bottom: 5px;">
                                <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                                    <input type="checkbox" name="kelas_ids[]" value="<?php echo $k['id']; ?>" class="kelas-checkbox">
                                    <span><?php echo htmlspecialchars($k['nama_prodi']); ?> - <?php echo htmlspecialchars($k['nama_kelas']); ?> (<?php echo $k['angkatan']; ?>)</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size: 0.8rem; color: #666; margin-top: 5px;">Pilih grup yang dapat mengakses materi ini. Kosongkan jika berlaku untuk semua grup.</p>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="hideModal()"><i class="fas fa-times"></i> Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="modal-overlay" id="deleteModal">
        <div class="modal delete-modal">
            <div class="warning-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>Konfirmasi Hapus</h3>
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus mata kuliah ini?</p>
            <p id="deleteWarning" style="color: #dc3545; font-size: 0.9rem;"></p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId" value="">
                <div class="btn-group">
                    <button type="button" class="btn" onclick="hideDeleteModal()"><i class="fas fa-times"></i> Batal</button>
                    <button type="submit" class="btn btn-delete"><i class="fas fa-trash"></i> Hapus</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-book"></i> Tambah Materi';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';
            document.getElementById('kode_mk').value = '';
            document.getElementById('nama_mk').value = '';
            document.getElementById('sks').value = '3';
            
            // Reset checkboxes
            document.querySelectorAll('.kelas-checkbox').forEach(cb => cb.checked = false);
            
            document.getElementById('modal').classList.add('active');
        }
        function hideModal() {
            document.getElementById('modal').classList.remove('active');
        }
        function editItem(id, kode, nama, sks) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Materi';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = id;
            document.getElementById('kode_mk').value = kode;
            document.getElementById('nama_mk').value = nama;
            document.getElementById('sks').value = sks;
            
            // Reset checkboxes
            document.querySelectorAll('.kelas-checkbox').forEach(cb => cb.checked = false);
            
            // Fetch selected classes
            fetch('../api.php?action=get_matkul_kelas&matkul_id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(kelasId => {
                            const cb = document.querySelector('.kelas-checkbox[value="' + kelasId + '"]');
                            if (cb) cb.checked = true;
                        });
                    }
                });
            
            document.getElementById('modal').classList.add('active');
        }
        function confirmDelete(id, nama, jumlahUjian) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteMessage').textContent = 'Apakah Anda yakin ingin menghapus "' + nama + '"?';
            if (jumlahUjian > 0) {
                document.getElementById('deleteWarning').textContent = 'Peringatan: Materi ini memiliki ' + jumlahUjian + ' penilaian yang akan ikut terhapus.';
            } else {
                document.getElementById('deleteWarning').textContent = '';
            }
            document.getElementById('deleteModal').classList.add('active');
        }
        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        document.getElementById('liveSearch').addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            var rows = document.querySelectorAll('.admin-table tbody tr');
            var visibleCount = 0;
            
            rows.forEach(function(row) {
                if (row.querySelector('td[colspan]')) return;
                
                var kode = row.cells[0].textContent.toLowerCase();
                var nama = row.cells[1].textContent.toLowerCase();
                
                if (kode.includes(searchTerm) || nama.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            var countSpan = document.getElementById('searchCount');
            if (searchTerm) {
                countSpan.textContent = 'Ditemukan: ' + visibleCount + ' data';
            } else {
                countSpan.textContent = '';
            }
        });
    </script>
</body>
</html>
