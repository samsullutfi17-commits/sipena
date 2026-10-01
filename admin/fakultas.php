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
        $kode = trim($_POST['kode_fakultas'] ?? '');
        $nama = trim($_POST['nama_fakultas'] ?? '');
        
        if (!empty($kode) && !empty($nama)) {
            $existing = $pdo->prepare("SELECT id FROM fakultas WHERE kode_fakultas = ?");
            $existing->execute([$kode]);
            if ($existing->fetch()) {
                $error = 'Kode fakultas sudah digunakan!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO fakultas (kode_fakultas, nama_fakultas) VALUES (?, ?)");
                $stmt->execute([$kode, $nama]);
                $message = 'Fakultas berhasil ditambahkan!';
            }
        } else {
            $error = 'Kode dan nama fakultas wajib diisi!';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_fakultas'] ?? '');
        $nama = trim($_POST['nama_fakultas'] ?? '');
        
        if ($id > 0 && !empty($kode) && !empty($nama)) {
            $stmt = $pdo->prepare("UPDATE fakultas SET kode_fakultas = ?, nama_fakultas = ? WHERE id = ?");
            $stmt->execute([$kode, $nama, $id]);
            $message = 'Fakultas berhasil diupdate!';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM fakultas WHERE id = ?")->execute([$id]);
        $message = 'Fakultas berhasil dihapus!';
    }
}

$search = trim($_GET['search'] ?? '');
$searchQuery = '';
$params = [];

if (!empty($search)) {
    $searchQuery = "WHERE f.nama_fakultas ILIKE ? OR f.kode_fakultas ILIKE ?";
    $params = ["%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT f.*, 
           (SELECT COUNT(*) FROM program_studi WHERE id_fakultas = f.id) as jumlah_prodi
    FROM fakultas f 
    $searchQuery
    ORDER BY f.nama_fakultas
");
$stmt->execute($params);
$fakultasList = $stmt->fetchAll();

$totalFakultas = $pdo->query("SELECT COUNT(*) FROM fakultas")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organisasi - Panel Admin</title>
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
                <a href="fakultas.php" class="nav-item active"><i class="fas fa-building"></i> Organisasi</a>
                <a href="prodi.php" class="nav-item"><i class="fas fa-sitemap"></i> Program</a>
                <a href="kelas.php" class="nav-item"><i class="fas fa-users-rectangle"></i> Kelas / Grup</a>
                <a href="matkul.php" class="nav-item"><i class="fas fa-book"></i> Materi</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <nav class="breadcrumb">
                <a href="dashboard.php">Beranda</a> / 
                <span>Organisasi</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-building"></i> Manajemen Organisasi</h2>
                <button class="btn btn-primary btn-icon" onclick="showModal()">
                    <i class="fas fa-plus"></i> Tambah Organisasi
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
                        <input type="text" id="liveSearch" placeholder="Ketik untuk mencari organisasi..." style="padding-left: 38px;">
                    </div>
                    <span id="searchCount" style="color: #666; font-size: 0.9rem;"></span>
                </div>
                <span class="total-info">Total: <strong><?php echo $totalFakultas; ?></strong> Organisasi</span>
            </div>
            
            <div class="card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Unit</th>
                                <th>Jumlah Program</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fakultasList)): ?>
                                <tr><td colspan="4" style="text-align: center;">Belum ada fakultas</td></tr>
                            <?php else: ?>
                                <?php foreach ($fakultasList as $f): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($f['kode_fakultas']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($f['nama_fakultas']); ?></td>
                                        <td>
                                            <?php if ($f['jumlah_prodi'] > 0): ?>
                                                <span class="badge-count active"><?php echo $f['jumlah_prodi']; ?></span>
                                            <?php else: ?>
                                                <span class="badge-count empty">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-edit btn-icon" onclick="editItem(<?php echo $f['id']; ?>, '<?php echo htmlspecialchars($f['kode_fakultas'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($f['nama_fakultas'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-delete btn-icon" onclick="confirmDelete(<?php echo $f['id']; ?>, '<?php echo htmlspecialchars($f['nama_fakultas'], ENT_QUOTES); ?>', <?php echo $f['jumlah_prodi']; ?>)">
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
            <h3 id="modalTitle"><i class="fas fa-building"></i> Tambah Organisasi</h3>
            <form method="POST" id="formFakultas">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="form-group">
                    <label>Kode</label>
                    <input type="text" name="kode_fakultas" id="kode_fakultas" required placeholder="Contoh: ORG-01">
                </div>
                
                <div class="form-group">
                    <label>Nama Unit / Departemen</label>
                    <input type="text" name="nama_fakultas" id="nama_fakultas" required placeholder="Contoh: Departemen Teknik Informatika">
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
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus fakultas ini?</p>
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
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-building"></i> Tambah Organisasi';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';
            document.getElementById('kode_fakultas').value = '';
            document.getElementById('nama_fakultas').value = '';
            document.getElementById('modal').classList.add('active');
        }
        function hideModal() {
            document.getElementById('modal').classList.remove('active');
        }
        function editItem(id, kode, nama) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Organisasi';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = id;
            document.getElementById('kode_fakultas').value = kode;
            document.getElementById('nama_fakultas').value = nama;
            document.getElementById('modal').classList.add('active');
        }
        function confirmDelete(id, nama, jumlahProdi) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteMessage').textContent = 'Apakah Anda yakin ingin menghapus "' + nama + '"?';
            if (jumlahProdi > 0) {
                document.getElementById('deleteWarning').textContent = 'Peringatan: Organisasi ini memiliki ' + jumlahProdi + ' program yang akan kehilangan relasi.';
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
