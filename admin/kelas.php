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
        $nama = trim($_POST['nama_kelas'] ?? '');
        $angkatan = (int)($_POST['angkatan'] ?? date('Y'));
        $prodiId = (int)($_POST['id_program_studi'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 1);
        
        if (!empty($nama) && $prodiId > 0) {
            $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, angkatan, id_program_studi, semester) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $angkatan, $prodiId, $semester]);
            $message = 'Kelas berhasil ditambahkan!';
        } else {
            $error = 'Nama kelas dan program studi wajib diisi!';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_kelas'] ?? '');
        $angkatan = (int)($_POST['angkatan'] ?? date('Y'));
        $prodiId = (int)($_POST['id_program_studi'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 1);
        
        if ($id > 0 && !empty($nama) && $prodiId > 0) {
            $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas = ?, angkatan = ?, id_program_studi = ?, semester = ? WHERE id = ?");
            $stmt->execute([$nama, $angkatan, $prodiId, $semester, $id]);
            $message = 'Kelas berhasil diupdate!';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM kelas WHERE id = ?")->execute([$id]);
        $message = 'Kelas berhasil dihapus!';
    }
}

$search = trim($_GET['search'] ?? '');
$searchQuery = '';
$params = [];

if (!empty($search)) {
    $searchQuery = "WHERE k.nama_kelas ILIKE ? OR ps.nama_prodi ILIKE ?";
    $params = ["%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT k.*, ps.nama_prodi 
    FROM kelas k 
    JOIN program_studi ps ON k.id_program_studi = ps.id 
    $searchQuery
    ORDER BY ps.nama_prodi, k.angkatan DESC, k.nama_kelas
");
$stmt->execute($params);
$kelasList = $stmt->fetchAll();

$prodiList = $pdo->query("SELECT * FROM program_studi ORDER BY nama_prodi")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelas / Grup - Panel Admin</title>
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
                <a href="kelas.php" class="nav-item active"><i class="fas fa-users-rectangle"></i> Kelas / Grup</a>
                <a href="matkul.php" class="nav-item"><i class="fas fa-book"></i> Materi</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
                <?php endif; ?>
            </nav>
        </aside>
        
        <main class="admin-main">
            <nav class="breadcrumb">
                <a href="dashboard.php">Beranda</a> / 
                <span>Kelas / Grup</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-users-rectangle"></i> Kelas / Grup</h2>
                <button class="btn btn-primary btn-icon" onclick="showModal()">
                    <i class="fas fa-plus"></i> Tambah Grup
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="search-bar">
                <div style="position: relative; flex: 1; max-width: 400px;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                    <input type="text" id="liveSearch" placeholder="Cari nama grup atau program..." style="padding-left: 38px;">
                </div>
            </div>
            
            <div class="card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Nama Grup</th>
                                <th>Periode</th>
                                <th>Tahun</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kelasList)): ?>
                                <tr><td colspan="5" style="text-align: center;">Belum ada data kelas</td></tr>
                            <?php else: ?>
                                <?php foreach ($kelasList as $kelas): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kelas['nama_prodi']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong></td>
                                        <td><?php echo $kelas['semester'] ?? '-'; ?></td>
                                        <td><?php echo $kelas['angkatan']; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-edit btn-icon" onclick="editItem(<?php echo $kelas['id']; ?>, '<?php echo htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES); ?>', <?php echo $kelas['angkatan']; ?>, <?php echo $kelas['id_program_studi']; ?>, <?php echo $kelas['semester'] ?? 1; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-delete btn-icon" onclick="confirmDelete(<?php echo $kelas['id']; ?>, '<?php echo htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES); ?>')">
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
            <h3 id="modalTitle"><i class="fas fa-users-rectangle"></i> Tambah Grup</h3>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="form-group">
                    <label>Program</label>
                    <select name="id_program_studi" id="id_program_studi" required class="form-select">
                        <option value="">-- Pilih Program --</option>
                        <?php foreach ($prodiList as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama_prodi']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Grup</label>
                    <input type="text" name="nama_kelas" id="nama_kelas" required placeholder="Contoh: Grup A">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Periode</label>
                        <input type="number" name="semester" id="semester" value="1" min="1" max="14">
                    </div>
                    <div class="form-group">
                        <label>Tahun</label>
                        <input type="number" name="angkatan" id="angkatan" value="<?php echo date('Y'); ?>" placeholder="Opsional">
                    </div>
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
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus kelas ini?</p>
            <form method="POST">
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
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-users-rectangle"></i> Tambah Grup';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';
            document.getElementById('nama_kelas').value = '';
            document.getElementById('angkatan').value = '<?php echo date('Y'); ?>';
            document.getElementById('id_program_studi').value = '';
            document.getElementById('semester').value = '1';
            document.getElementById('modal').classList.add('active');
        }
        function hideModal() {
            document.getElementById('modal').classList.remove('active');
        }
        function editItem(id, nama, angkatan, prodiId, semester) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Grup';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = id;
            document.getElementById('nama_kelas').value = nama;
            document.getElementById('angkatan').value = angkatan;
            document.getElementById('id_program_studi').value = prodiId;
            document.getElementById('semester').value = semester;
            document.getElementById('modal').classList.add('active');
        }
        function confirmDelete(id, nama) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteMessage').textContent = 'Apakah Anda yakin ingin menghapus kelas "' + nama + '"?';
            document.getElementById('deleteModal').classList.add('active');
        }
        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        document.getElementById('liveSearch').addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            var rows = document.querySelectorAll('.admin-table tbody tr');
            rows.forEach(function(row) {
                if (row.querySelector('td[colspan]')) return;
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>