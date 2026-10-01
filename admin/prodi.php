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
        $kode = trim($_POST['kode_prodi'] ?? '');
        $nama = trim($_POST['nama_prodi'] ?? '');
        $idFakultas = (int)($_POST['id_fakultas'] ?? 0);
        
        if (!empty($kode) && !empty($nama)) {
            $existing = $pdo->prepare("SELECT id FROM program_studi WHERE kode_prodi = ?");
            $existing->execute([$kode]);
            if ($existing->fetch()) {
                $error = 'Kode program studi sudah digunakan!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO program_studi (kode_prodi, nama_prodi, id_fakultas) VALUES (?, ?, ?)");
                $stmt->execute([$kode, $nama, $idFakultas ?: null]);
                $message = 'Program Studi berhasil ditambahkan!';
            }
        } else {
            $error = 'Kode dan nama program studi wajib diisi!';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_prodi'] ?? '');
        $nama = trim($_POST['nama_prodi'] ?? '');
        $idFakultas = (int)($_POST['id_fakultas'] ?? 0);
        
        if ($id > 0 && !empty($kode) && !empty($nama)) {
            $stmt = $pdo->prepare("UPDATE program_studi SET kode_prodi = ?, nama_prodi = ?, id_fakultas = ? WHERE id = ?");
            $stmt->execute([$kode, $nama, $idFakultas ?: null, $id]);
            $message = 'Program Studi berhasil diupdate!';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM program_studi WHERE id = ?")->execute([$id]);
        $message = 'Program Studi berhasil dihapus!';
    }
}

$search = trim($_GET['search'] ?? '');
$filterFakultas = (int)($_GET['fakultas'] ?? 0);
$searchQuery = 'WHERE 1=1';
$params = [];

if (!empty($search)) {
    $searchQuery .= " AND (ps.nama_prodi ILIKE ? OR ps.kode_prodi ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterFakultas > 0) {
    $searchQuery .= " AND ps.id_fakultas = ?";
    $params[] = $filterFakultas;
}

$fakultasList = $pdo->query("SELECT * FROM fakultas ORDER BY nama_fakultas")->fetchAll();

$stmt = $pdo->prepare("
    SELECT ps.*, f.nama_fakultas,
           (SELECT COUNT(*) FROM kelas WHERE id_program_studi = ps.id) as jumlah_kelas
    FROM program_studi ps
    LEFT JOIN fakultas f ON ps.id_fakultas = f.id
    $searchQuery
    ORDER BY f.nama_fakultas, ps.nama_prodi
");
$stmt->execute($params);
$prodiList = $stmt->fetchAll();

$totalProdi = $pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program - Panel Admin</title>
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
                <a href="fakultas.php" class="nav-item"><i class="fas fa-building"></i> Organisasi</a>
                <a href="prodi.php" class="nav-item active"><i class="fas fa-sitemap"></i> Program</a>
                <a href="kelas.php" class="nav-item"><i class="fas fa-users-rectangle"></i> Kelas / Grup</a>
                <a href="matkul.php" class="nav-item"><i class="fas fa-book"></i> Materi</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <nav class="breadcrumb">
                <a href="dashboard.php">Beranda</a> / 
                <span>Program</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-sitemap"></i> Manajemen Program</h2>
                <button class="btn btn-primary btn-icon" onclick="showModal()">
                    <i class="fas fa-plus"></i> Tambah Program
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="search-bar">
                <div style="display: flex; gap: 10px; flex: 1; align-items: center; flex-wrap: wrap;">
                    <div style="position: relative; flex: 1; max-width: 350px;">
                        <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                        <input type="text" id="liveSearch" placeholder="Ketik untuk mencari program..." style="padding-left: 38px;">
                    </div>
                    <select id="filterFakultas" class="form-select" style="width: auto;">
                        <option value="">Semua Organisasi</option>
                        <?php foreach ($fakultasList as $f): ?>
                            <option value="<?php echo htmlspecialchars($f['nama_fakultas']); ?>">
                                <?php echo htmlspecialchars($f['nama_fakultas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="searchCount" style="color: #666; font-size: 0.9rem;"></span>
                </div>
                <span class="total-info">Total: <strong><?php echo $totalProdi; ?></strong> Program</span>
            </div>
            
            <div class="card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Program</th>
                                <th>Organisasi</th>
                                <th>Jumlah Grup</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($prodiList)): ?>
                                <tr><td colspan="5" style="text-align: center;">Belum ada program studi</td></tr>
                            <?php else: ?>
                                <?php foreach ($prodiList as $p): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($p['kode_prodi']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['nama_prodi']); ?></td>
                                        <td><?php echo htmlspecialchars($p['nama_fakultas'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($p['jumlah_kelas'] > 0): ?>
                                                <span class="badge-count active"><?php echo $p['jumlah_kelas']; ?></span>
                                            <?php else: ?>
                                                <span class="badge-count empty">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-edit btn-icon" onclick="editItem(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['kode_prodi'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['nama_prodi'], ENT_QUOTES); ?>', <?php echo $p['id_fakultas'] ?? 0; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-delete btn-icon" onclick="confirmDelete(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nama_prodi'], ENT_QUOTES); ?>', <?php echo $p['jumlah_kelas']; ?>)">
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
            <h3 id="modalTitle"><i class="fas fa-sitemap"></i> Tambah Program</h3>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="form-group">
                    <label>Organisasi</label>
                    <select name="id_fakultas" id="id_fakultas" class="form-select">
                        <option value="">-- Pilih Organisasi --</option>
                        <?php foreach ($fakultasList as $f): ?>
                            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nama_fakultas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Kode Program</label>
                    <input type="text" name="kode_prodi" id="kode_prodi" required placeholder="Contoh: PRG-01">
                </div>
                
                <div class="form-group">
                    <label>Nama Program</label>
                    <input type="text" name="nama_prodi" id="nama_prodi" required placeholder="Contoh: Teknik Informatika">
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
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus program studi ini?</p>
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
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-sitemap"></i> Tambah Program';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';
            document.getElementById('id_fakultas').value = '';
            document.getElementById('kode_prodi').value = '';
            document.getElementById('nama_prodi').value = '';
            document.getElementById('modal').classList.add('active');
        }
        function hideModal() {
            document.getElementById('modal').classList.remove('active');
        }
        function editItem(id, kode, nama, idFakultas) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Program';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = id;
            document.getElementById('id_fakultas').value = idFakultas || '';
            document.getElementById('kode_prodi').value = kode;
            document.getElementById('nama_prodi').value = nama;
            document.getElementById('modal').classList.add('active');
        }
        function confirmDelete(id, nama, jumlahKelas) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteMessage').textContent = 'Apakah Anda yakin ingin menghapus "' + nama + '"?';
            if (jumlahKelas > 0) {
                document.getElementById('deleteWarning').textContent = 'Peringatan: Program ini memiliki ' + jumlahKelas + ' grup yang akan ikut terhapus.';
            } else {
                document.getElementById('deleteWarning').textContent = '';
            }
            document.getElementById('deleteModal').classList.add('active');
        }
        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        function filterTable() {
            var searchTerm = document.getElementById('liveSearch').value.toLowerCase();
            var fakultasFilter = document.getElementById('filterFakultas').value.toLowerCase();
            var rows = document.querySelectorAll('.admin-table tbody tr');
            var visibleCount = 0;
            
            rows.forEach(function(row) {
                if (row.querySelector('td[colspan]')) return;
                
                var kode = row.cells[0].textContent.toLowerCase();
                var nama = row.cells[1].textContent.toLowerCase();
                var fakultas = row.cells[2].textContent.toLowerCase();
                
                var matchSearch = kode.includes(searchTerm) || nama.includes(searchTerm);
                var matchFakultas = !fakultasFilter || fakultas.includes(fakultasFilter);
                
                if (matchSearch && matchFakultas) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            var countSpan = document.getElementById('searchCount');
            if (searchTerm || fakultasFilter) {
                countSpan.textContent = 'Ditemukan: ' + visibleCount + ' data';
            } else {
                countSpan.textContent = '';
            }
        }
        
        document.getElementById('liveSearch').addEventListener('input', filterTable);
        document.getElementById('filterFakultas').addEventListener('change', filterTable);
    </script>
</body>
</html>
