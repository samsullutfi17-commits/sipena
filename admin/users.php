<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';


// Handle XLSX Import via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_data') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['rows']) || !is_array($_POST['rows'])) {
        echo json_encode(['success' => false, 'message' => 'No data provided']);
        exit;
    }
    
    $importedCount = 0;
    $skippedCount = 0;
    $errors = [];
    
    foreach ($_POST['rows'] as $row) {
        $username = trim($row[0] ?? '');
        $password = trim($row[1] ?? '');
        $namaLengkap = trim($row[2] ?? '');
        $role = trim($row[3] ?? 'dosen');
        $nim = trim($row[4] ?? '');

        if (empty($username) || empty($password) || empty($namaLengkap)) {
            $skippedCount++;
            continue;
        }
        
        $existing = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $existing->execute([$username]);
        
        if ($existing->fetch()) {
            $skippedCount++;
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, nim) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$username, $hashedPassword, $namaLengkap, $role, $nim ?: null])) {
                $importedCount++;
            } else {
                $errors[] = "Error adding user: $username";
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'imported' => $importedCount,
        'skipped' => $skippedCount,
        'errors' => $errors
    ]);
    exit;
}

// Handle regular POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $role        = $_POST['role'] ?? 'dosen';
        $nim         = trim($_POST['nim'] ?? '');

        if (!empty($username) && !empty($password) && !empty($namaLengkap)) {
            $existing = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $existing->execute([$username]);

            if ($existing->fetch()) {
                $error = 'Username sudah digunakan!';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, nim) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $namaLengkap, $role, $nim ?: null]);
                $message = 'User berhasil ditambahkan!';
            }
        } else {
            $error = 'Semua field wajib diisi!';
        }
    } elseif ($action === 'update') {
        $userId      = (int)($_POST['user_id'] ?? 0);
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $role        = $_POST['role'] ?? 'dosen';
        $nim         = trim($_POST['nim'] ?? '');

        if (!empty($namaLengkap)) {
            $newPassword = $_POST['password'] ?? '';
            if (!empty($newPassword)) {
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, role = ?, nim = ?, password = ? WHERE id = ?");
                $stmt->execute([$namaLengkap, $role, $nim ?: null, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, role = ?, nim = ? WHERE id = ?");
                $stmt->execute([$namaLengkap, $role, $nim ?: null, $userId]);
            }
            $message = 'User berhasil diupdate!';
        } else {
            $error = 'Nama lengkap tidak boleh kosong!';
        }
    } elseif ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId !== $_SESSION['admin_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            $message = 'User berhasil dihapus!';
        } else {
            $error = 'Tidak dapat menghapus akun sendiri!';
        }
    } elseif ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = password_hash('12345*', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newPassword, $userId]);
        $message = 'Password berhasil direset ke: 12345*';
    }
}

// Get filter and search parameters
$roleFilter = $_GET['role'] ?? '';
$kelasFilter = $_GET['kelas'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT u.* FROM users u WHERE 1=1";
$params = [];

if (!empty($roleFilter)) {
    $query .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($kelasFilter)) {
    $query .= " AND u.id_kelas = ?";
    $params[] = (int)$kelasFilter;
}

if (!empty($searchQuery)) {
    $query .= " AND (u.username ILIKE ? OR u.nama_lengkap ILIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}

// Get all roles for filter
$allRoles = ['peserta', 'pengawas', 'dosen', 'admin'];

// Get all classes for filter
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

// Handle AJAX: ubah status sesi peserta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ubah_status_peserta') {
    header('Content-Type: application/json');
    $sesiId    = (int)($_POST['sesi_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    if (!$sesiId || !in_array($newStatus, ['berlangsung', 'selesai', 'timeout'])) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
        exit;
    }

    try {
        if ($newStatus === 'selesai') {
            $stmt = $pdo->prepare("UPDATE sesi_ujian SET status = 'selesai', waktu_selesai = NOW() WHERE id = ?");
        } elseif ($newStatus === 'timeout') {
            // "Login" = paksa keluar dari ujian → timeout
            $stmt = $pdo->prepare("UPDATE sesi_ujian SET status = 'timeout' WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE sesi_ujian SET status = 'berlangsung' WHERE id = ?");
        }
        $stmt->execute([$sesiId]);
        echo json_encode(['success' => true, 'message' => 'Status berhasil diubah']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengubah status: ' . $e->getMessage()]);
    }
    exit;
}

// Status Peserta: fetch all peserta users with their latest sesi_ujian status
$statusPesertaList = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.nama_lengkap,
        u.nim,
        u.last_login_at,
        su.id             AS sesi_id,
        su.status         AS sesi_status,
        su.waktu_mulai    AS sesi_mulai,
        su.waktu_selesai  AS sesi_selesai,
        su.nilai_total,
        uj.judul_ujian
    FROM users u
    LEFT JOIN LATERAL (
        SELECT s.id, s.status, s.waktu_mulai, s.waktu_selesai, s.nilai_total, s.id_ujian
        FROM sesi_ujian s
        JOIN mahasiswa m ON m.id = s.id_mahasiswa
        WHERE m.nim = u.nim OR m.nim = u.username
        ORDER BY s.waktu_mulai DESC
        LIMIT 1
    ) su ON TRUE
    LEFT JOIN ujian uj ON uj.id = su.id_ujian
    WHERE u.role = 'peserta'
    ORDER BY
        CASE
            WHEN su.status = 'berlangsung' THEN 1
            WHEN u.last_login_at > NOW() - INTERVAL '4 hours' AND su.status IS DISTINCT FROM 'berlangsung' THEN 2
            WHEN su.status = 'selesai' THEN 3
            ELSE 4
        END,
        u.last_login_at DESC NULLS LAST
")->fetchAll();

$activeTab = $_GET['tab'] ?? 'users';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Panel Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn-clear-filter {
            padding: 10px 20px;
            background: var(--border-color);
            color: var(--text-dark);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-clear-filter:hover {
            background: var(--text-muted);
            color: white;
        }
        .page-header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
            color: var(--primary);
        }
        .loading-spinner.active {
            display: block;
        }
        /* Tab navigation */
        .tab-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
        }
        .tab-nav a {
            padding: 10px 22px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px 8px 0 0;
            border: 2px solid transparent;
            border-bottom: none;
            margin-bottom: -2px;
            transition: color 0.2s, background 0.2s;
        }
        .tab-nav a:hover { color: var(--primary); background: #f0faf6; }
        .tab-nav a.active {
            color: var(--primary);
            background: #fff;
            border-color: var(--border-color);
            border-bottom-color: #fff;
        }
        /* Status badges */
        .status-login   { background: #fff8e1; color: #b45309; border: 1px solid #fde68a; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; white-space: nowrap; }
        .status-berlangsung { background: #fff0f0; color: #dc2626; border: 1px solid #fca5a5; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; white-space: nowrap; }
        .status-selesai { background: #f0fdf4; color: #16a34a; border: 1px solid #86efac; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; white-space: nowrap; }
        .status-offline { background: #f3f4f6; color: #9ca3af; border: 1px solid #e5e7eb; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; white-space: nowrap; }
        .status-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .dot-login       { background: #f59e0b; }
        .dot-berlangsung { background: #ef4444; animation: pulse-dot 1.2s infinite; }
        .dot-selesai     { background: #22c55e; }
        .dot-offline     { background: #d1d5db; }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .refresh-badge {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-left: 8px;
        }
    </style>
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
                <a href="prodi.php" class="nav-item"><i class="fas fa-sitemap"></i> Program</a>
                <a href="kelas.php" class="nav-item"><i class="fas fa-users-rectangle"></i> Kelas / Grup</a>
                <a href="matkul.php" class="nav-item"><i class="fas fa-book"></i> Materi</a>
                <a href="users.php" class="nav-item active"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <nav class="breadcrumb">
                <a href="dashboard.php">Beranda</a> / 
                <span>Pengguna</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-users"></i> Pengguna</h2>
                <div class="page-header-actions">
                    <button class="btn btn-primary btn-icon" onclick="showModal()"><i class="fas fa-plus"></i> Tambah Pengguna</button>
                    <button class="btn btn-accent btn-icon" onclick="showImportModal()"><i class="fas fa-file-import"></i> Import XLSX</button>
                </div>
            </div>

            <!-- Tab Navigation -->
            <nav class="tab-nav">
                <a href="users.php?tab=users" class="<?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Daftar User
                </a>
                <a href="users.php?tab=status" class="<?php echo $activeTab === 'status' ? 'active' : ''; ?>">
                    <i class="fas fa-circle-dot"></i> Status Peserta
                    <?php
                        $activeCount = count(array_filter($statusPesertaList, fn($p) => $p['sesi_status'] === 'berlangsung'));
                        if ($activeCount > 0):
                    ?>
                        <span style="background:#ef4444;color:#fff;border-radius:12px;padding:1px 8px;font-size:0.75rem;margin-left:6px;"><?php echo $activeCount; ?></span>
                    <?php endif; ?>
                </a>
            </nav>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($activeTab === 'status'): ?>
            <!-- ===== STATUS PESERTA TAB ===== -->
            <div class="card" style="margin-bottom: 16px; padding: 14px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:center;">
                    <span><span class="status-dot dot-berlangsung"></span> <strong><?php echo count(array_filter($statusPesertaList, fn($p) => $p['sesi_status'] === 'berlangsung')); ?></strong> Sedang Ujian</span>
                    <span><span class="status-dot dot-login"></span> <strong><?php echo count(array_filter($statusPesertaList, fn($p) => $p['sesi_status'] !== 'berlangsung' && $p['last_login_at'] && strtotime($p['last_login_at']) > time() - 4*3600)); ?></strong> Login</span>
                    <span><span class="status-dot dot-selesai"></span> <strong><?php echo count(array_filter($statusPesertaList, fn($p) => $p['sesi_status'] === 'selesai')); ?></strong> Selesai</span>
                    <span><span class="status-dot dot-offline"></span> <strong><?php echo count(array_filter($statusPesertaList, fn($p) => !$p['last_login_at'] || (strtotime($p['last_login_at']) <= time() - 4*3600 && $p['sesi_status'] !== 'berlangsung' && $p['sesi_status'] !== 'selesai'))); ?></strong> Offline</span>
                    <span style="border-left:2px solid var(--border-color); padding-left:18px; color:var(--text-muted);">
                        <i class="fas fa-users" style="margin-right:5px;"></i><strong style="color:var(--text-dark);"><?php echo count($statusPesertaList); ?></strong> Total Terdaftar
                    </span>
                </div>
                <a href="users.php?tab=status" class="btn btn-sm" style="background:var(--border-color);color:var(--text-dark);font-size:0.82rem;">
                    <i class="fas fa-rotate-right"></i> Refresh
                </a>
            </div>

            <div class="card">
                <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>NIM / NPM</th>
                            <th>Status Test</th>
                            <th>Ujian</th>
                            <th>Waktu</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($statusPesertaList)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted);">Belum ada peserta terdaftar</td></tr>
                        <?php else: ?>
                            <?php foreach ($statusPesertaList as $p):
                                $isBerlangsung = $p['sesi_status'] === 'berlangsung';
                                $isSelesai     = $p['sesi_status'] === 'selesai';
                                $isLogin       = !$isBerlangsung && $p['last_login_at'] && strtotime($p['last_login_at']) > time() - 4*3600;
                                $isOffline     = !$isBerlangsung && !$isSelesai && !$isLogin;
                                $hasSesi       = !empty($p['sesi_id']);
                                $currentStatus = $isBerlangsung ? 'berlangsung' : ($isSelesai ? 'selesai' : ($isLogin ? 'login' : 'offline'));
                            ?>
                            <tr id="row-user-<?php echo $p['id']; ?>">
                                <td><?php echo htmlspecialchars($p['username']); ?></td>
                                <td><?php echo htmlspecialchars($p['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($p['nim'] ?? '-'); ?></td>
                                <td class="status-cell-<?php echo $p['id']; ?>">
                                    <?php if ($isBerlangsung): ?>
                                        <span class="status-berlangsung">
                                            <span class="status-dot dot-berlangsung"></span>Test Sedang Dikerjakan
                                        </span>
                                    <?php elseif ($isLogin): ?>
                                        <span class="status-login">
                                            <span class="status-dot dot-login"></span>Login
                                        </span>
                                    <?php elseif ($isSelesai): ?>
                                        <span class="status-selesai">
                                            <span class="status-dot dot-selesai"></span>Test Selesai
                                        </span>
                                    <?php else: ?>
                                        <span class="status-offline">
                                            <span class="status-dot dot-offline"></span>Offline
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.88rem;">
                                    <?php echo $p['judul_ujian'] ? htmlspecialchars($p['judul_ujian']) : '<span style="color:var(--text-muted)">—</span>'; ?>
                                </td>
                                <td style="font-size:0.82rem; color:var(--text-muted);">
                                    <?php if ($isBerlangsung && $p['sesi_mulai']): ?>
                                        Mulai: <?php echo date('H:i', strtotime($p['sesi_mulai'])); ?>
                                    <?php elseif ($isSelesai && $p['sesi_selesai']): ?>
                                        Selesai: <?php echo date('H:i d/m', strtotime($p['sesi_selesai'])); ?>
                                    <?php elseif ($p['last_login_at']): ?>
                                        Login: <?php echo date('H:i d/m', strtotime($p['last_login_at'])); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($hasSesi): ?>
                                        <button
                                            class="btn btn-sm"
                                            style="background:#f0faf6;color:var(--primary);border:1px solid var(--primary);font-size:0.8rem;padding:5px 12px;white-space:nowrap;"
                                            onclick="openUbahStatusModal(<?php echo $p['id']; ?>, <?php echo $p['sesi_id']; ?>, '<?php echo htmlspecialchars($p['nama_lengkap'], ENT_QUOTES); ?>', '<?php echo $currentStatus; ?>')"
                                        >
                                            <i class="fas fa-pen-to-square"></i> Ubah Status
                                        </button>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <script>
                // Auto-refresh halaman setiap 30 detik saat di tab status
                let autoRefreshTimer = setTimeout(() => location.reload(), 30000);
                let countdown = 30;
                const countEl = document.createElement('small');
                countEl.style.cssText = 'color:var(--text-muted);font-size:0.78rem;margin-left:6px;';
                document.querySelector('.tab-nav a.active')?.appendChild(countEl);
                setInterval(() => { countdown--; if(countEl) countEl.textContent = `(refresh ${countdown}s)`; }, 1000);
            </script>

            <?php else: ?>
            <!-- ===== DAFTAR USER TAB ===== -->
            <!-- Filter Section -->
            <div class="card" style="margin-bottom: 20px;">
                <form class="filter-section" id="filterForm">
                    <div class="filter-group" style="min-width: 200px;">
                        <label for="search">Cari (Username / Nama)</label>
                        <input type="text" id="search" name="search" placeholder="Ketikkan username atau nama..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div class="filter-group" style="min-width: 150px;">
                        <label for="role">Filter Role</label>
                        <select id="role" name="role">
                            <option value="">-- Semua Role --</option>
                            <?php foreach ($allRoles as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo $roleFilter === $r ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($r); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="min-width: 150px;">
                        <label for="kelas">Filter Kelas</label>
                        <select id="kelas" name="kelas">
                            <option value="">-- Semua Kelas --</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?php echo $k['id']; ?>" <?php echo $kelasFilter === (string)$k['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($k['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($searchQuery) || !empty($roleFilter) || !empty($kelasFilter)): ?>
                        <a href="users.php" class="btn-clear-filter">Bersihkan Filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="loading-spinner" id="loadingSpinner">
                <i class="fas fa-spinner fa-spin"></i> Memuat data...
            </div>
            
            <div class="card" id="tableContainer">
                <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php if (count($usersList) > 0): ?>
                            <?php foreach ($usersList as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            if ($user['role'] === 'admin') echo 'badge-success';
                                            elseif ($user['role'] === 'dosen') echo 'badge-warning';
                                            else echo 'badge-info';
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-edit" onclick="return confirm('Reset password user ini?');">Reset PW</button>
                                            </form>
                                            <?php if ($user['id'] !== $_SESSION['admin_id']): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus user ini?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-delete">Hapus</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted);">
                                    Tidak ada user yang ditemukan
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Modal Tambah User -->
    <div class="modal-overlay" id="modal">
        <div class="modal">
            <h3>Tambah User Baru</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Username untuk login">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Password">
                </div>
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" required placeholder="Nama lengkap user">
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="create-role" class="form-select" onchange="toggleNimField('create')">
                        <option value="peserta">Peserta</option>
                        <option value="pengawas">Pengawas</option>
                        <option value="dosen">Dosen</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group" id="create-nim-group">
                    <label>NPM / NIM <span style="color:var(--text-muted); font-weight:400;">(untuk Peserta)</span></label>
                    <input type="text" name="nim" placeholder="Masukkan NPM atau NIM peserta">
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="hideModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Import XLSX -->
    <div class="modal-overlay" id="importModal">
        <div class="modal">
            <h3>Import User dari XLSX</h3>
            <form id="importForm">
                <div class="form-group">
                    <label>File XLSX</label>
                    <input type="file" id="xlsxFile" accept=".xlsx,.xls" required placeholder="Pilih file XLSX">
                    <small style="color: var(--text-muted); display: block; margin-top: 8px;">
                        Format: username, password, nama_lengkap, role (peserta/pengawas/dosen/admin)<br>
                        <a href="#" onclick="downloadTemplate(); return false;" style="color: var(--primary);">Download template XLSX</a>
                    </small>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="hideImportModal()">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="importXLSX()">Import</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Edit User -->
    <?php if ($editUser): ?>
    <div class="modal-overlay" id="editModal" style="display: flex;">
        <div class="modal">
            <h3>Edit User</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($editUser['username']); ?>" disabled placeholder="Username tidak dapat diubah">
                </div>
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($editUser['nama_lengkap']); ?>" required placeholder="Nama lengkap user">
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit-role" class="form-select" onchange="toggleNimField('edit')">
                        <option value="peserta" <?php echo $editUser['role'] === 'peserta' ? 'selected' : ''; ?>>Peserta</option>
                        <option value="pengawas" <?php echo $editUser['role'] === 'pengawas' ? 'selected' : ''; ?>>Pengawas</option>
                        <option value="dosen" <?php echo $editUser['role'] === 'dosen' ? 'selected' : ''; ?>>Dosen</option>
                        <option value="admin" <?php echo $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="form-group" id="edit-nim-group" style="<?php echo $editUser['role'] !== 'peserta' ? 'display:none;' : ''; ?>">
                    <label>NPM / NIM <span style="color:var(--text-muted); font-weight:400;">(untuk Peserta)</span></label>
                    <input type="text" name="nim" value="<?php echo htmlspecialchars($editUser['nim'] ?? ''); ?>" placeholder="Masukkan NPM atau NIM peserta">
                </div>

                <div class="form-group">
                    <label>Password Baru <span style="color:var(--text-muted); font-weight:400;">(kosongkan jika tidak ingin mengubah)</span></label>
                    <div style="position: relative;">
                        <input type="password" id="edit-password" name="password" placeholder="Masukkan password baru" style="width:100%; padding-right: 45px;">
                        <button type="button" onclick="toggleEditPassword()"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:1rem; padding:0; line-height:1;">
                            <i class="fas fa-eye" id="edit-eye-icon"></i>
                        </button>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <a href="users.php" class="btn" style="text-decoration: none; padding: 10px 20px;">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Modal Ubah Status Peserta -->
    <div class="modal-overlay" id="ubahStatusModal">
        <div class="modal" style="max-width:420px;">
            <h3 style="margin-bottom:6px;"><i class="fas fa-pen-to-square" style="color:var(--primary);"></i> Ubah Status Test</h3>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:20px;" id="ubahStatusNama"></p>

            <div class="form-group">
                <label style="font-weight:600;margin-bottom:8px;display:block;">Status Test</label>
                <div style="display:flex;flex-direction:column;gap:10px;" id="statusOptions">
                    <label class="status-radio-card" id="card-timeout">
                        <input type="radio" name="status_pilihan" value="timeout">
                        <span class="status-login" style="pointer-events:none;"><span class="status-dot dot-login"></span>Login</span>
                        <small style="color:var(--text-muted);display:block;margin-top:4px;margin-left:18px;">Keluarkan peserta dari sesi ujian (paksa keluar)</small>
                    </label>
                    <label class="status-radio-card" id="card-berlangsung">
                        <input type="radio" name="status_pilihan" value="berlangsung">
                        <span class="status-berlangsung" style="pointer-events:none;"><span class="status-dot dot-berlangsung"></span>Test Sedang Dikerjakan</span>
                        <small style="color:var(--text-muted);display:block;margin-top:4px;margin-left:18px;">Tandai sesi sebagai aktif / sedang berlangsung</small>
                    </label>
                    <label class="status-radio-card" id="card-selesai">
                        <input type="radio" name="status_pilihan" value="selesai">
                        <span class="status-selesai" style="pointer-events:none;"><span class="status-dot dot-selesai"></span>Test Selesai</span>
                        <small style="color:var(--text-muted);display:block;margin-top:4px;margin-left:18px;">Akhiri sesi ujian peserta secara paksa</small>
                    </label>
                </div>
            </div>

            <div id="ubahStatusMsg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:0.88rem;"></div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:22px;">
                <button type="button" class="btn" onclick="closeUbahStatusModal()">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSimpanStatus" onclick="simpanUbahStatus()">
                    <i class="fas fa-check"></i> Simpan
                </button>
            </div>
        </div>
    </div>

    <style>
        .status-radio-card {
            display: block;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }
        .status-radio-card:hover { border-color: var(--primary); background: #f0faf6; }
        .status-radio-card input[type=radio] { margin-right: 8px; accent-color: var(--primary); }
        .status-radio-card.selected { border-color: var(--primary); background: #e8f8f0; }
    </style>

    <script>
        let _ubahSesiId = null;
        let _ubahUserId = null;

        function openUbahStatusModal(userId, sesiId, nama, currentStatus) {
            _ubahSesiId = sesiId;
            _ubahUserId = userId;
            document.getElementById('ubahStatusNama').textContent = 'Peserta: ' + nama;
            document.getElementById('ubahStatusMsg').style.display = 'none';

            // Pre-select current status
            const radios = document.querySelectorAll('input[name="status_pilihan"]');
            radios.forEach(r => {
                r.checked = (r.value === currentStatus || (currentStatus === 'login' && r.value === 'timeout'));
            });
            updateCardHighlight();

            document.getElementById('ubahStatusModal').classList.add('active');
        }

        function closeUbahStatusModal() {
            document.getElementById('ubahStatusModal').classList.remove('active');
        }

        function updateCardHighlight() {
            ['timeout','berlangsung','selesai'].forEach(v => {
                const card = document.getElementById('card-' + v);
                const radio = card ? card.querySelector('input') : null;
                if (card) card.classList.toggle('selected', radio && radio.checked);
            });
        }

        document.querySelectorAll('input[name="status_pilihan"]').forEach(r => {
            r.addEventListener('change', updateCardHighlight);
        });

        function simpanUbahStatus() {
            const selected = document.querySelector('input[name="status_pilihan"]:checked');
            if (!selected) { alert('Pilih status terlebih dahulu'); return; }

            const btn = document.getElementById('btnSimpanStatus');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

            // Pause auto-refresh
            clearTimeout(autoRefreshTimer);

            const fd = new FormData();
            fd.append('action', 'ubah_status_peserta');
            fd.append('sesi_id', _ubahSesiId);
            fd.append('new_status', selected.value);

            fetch('users.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    const msgEl = document.getElementById('ubahStatusMsg');
                    if (res.success) {
                        msgEl.style.display = 'block';
                        msgEl.style.cssText = 'display:block;padding:10px 14px;border-radius:8px;font-size:0.88rem;background:#f0fdf4;color:#16a34a;border:1px solid #86efac;margin-top:12px;';
                        msgEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + res.message;

                        // Update badge in table immediately
                        updateStatusBadge(_ubahUserId, selected.value);

                        setTimeout(() => {
                            closeUbahStatusModal();
                            location.reload();
                        }, 1200);
                    } else {
                        msgEl.style.cssText = 'display:block;padding:10px 14px;border-radius:8px;font-size:0.88rem;background:#fff0f0;color:#dc2626;border:1px solid #fca5a5;margin-top:12px;';
                        msgEl.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + res.message;
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check"></i> Simpan';
                    }
                })
                .catch(() => {
                    const msgEl = document.getElementById('ubahStatusMsg');
                    msgEl.style.cssText = 'display:block;padding:10px 14px;border-radius:8px;font-size:0.88rem;background:#fff0f0;color:#dc2626;border:1px solid #fca5a5;margin-top:12px;';
                    msgEl.innerHTML = '<i class="fas fa-circle-exclamation"></i> Gagal menghubungi server';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Simpan';
                });
        }

        function updateStatusBadge(userId, newStatus) {
            const cell = document.querySelector('.status-cell-' + userId);
            if (!cell) return;
            const labels = {
                'timeout':     '<span class="status-login"><span class="status-dot dot-login"></span>Login</span>',
                'berlangsung': '<span class="status-berlangsung"><span class="status-dot dot-berlangsung"></span>Test Sedang Dikerjakan</span>',
                'selesai':     '<span class="status-selesai"><span class="status-dot dot-selesai"></span>Test Selesai</span>',
            };
            if (labels[newStatus]) cell.innerHTML = labels[newStatus];
        }
    </script>

    <script>
        // Modal functions
        function showModal() {
            document.getElementById('modal').classList.add('active');
        }
        function hideModal() {
            document.getElementById('modal').classList.remove('active');
        }
        function showImportModal() {
            document.getElementById('importModal').classList.add('active');
        }
        function hideImportModal() {
            document.getElementById('importModal').classList.remove('active');
        }

        // Generate & download proper XLSX template using SheetJS
        function downloadTemplate() {
            const data = [
                ['username', 'password', 'nama_lengkap', 'role', 'nim (opsional)'],
                ['user_sample1', '12345*', 'Nama Lengkap Sample 1', 'dosen', ''],
                ['user_sample2', '12345*', 'Nama Lengkap Sample 2', 'peserta', '240305001'],
                ['# Catatan: role diisi dengan: peserta / pengawas / dosen / admin | nim = NIM/NIDN (opsional)', '', '', '', '']
            ];

            const ws = XLSX.utils.aoa_to_sheet(data);

            // Set column widths
            ws['!cols'] = [
                { wch: 20 },
                { wch: 12 },
                { wch: 30 },
                { wch: 12 },
                { wch: 18 }
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Template User');

            const today = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, 'template_user_' + today + '.xlsx');
        }
        
        // Show/hide password on edit form
        function toggleEditPassword() {
            const input = document.getElementById('edit-password');
            const icon  = document.getElementById('edit-eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Show/hide NIM field based on selected role
        function toggleNimField(prefix) {
            const role  = document.getElementById(prefix + '-role').value;
            const group = document.getElementById(prefix + '-nim-group');
            group.style.display = (role === 'peserta') ? '' : 'none';
        }
        // Init on page load for edit modal (only if modal elements exist)
        if (document.getElementById('create-role')) toggleNimField('create');

        // Real-time filter (only active on Daftar User tab)
        const searchInput = document.getElementById('search');
        const roleSelect = document.getElementById('role');
        const kelasSelect = document.getElementById('kelas');
        let filterTimeout;
        
        function applyFilters() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                const search = searchInput ? searchInput.value : '';
                const role = roleSelect ? roleSelect.value : '';
                const kelas = kelasSelect ? kelasSelect.value : '';
                
                let url = 'users.php?';
                if (search) url += 'search=' + encodeURIComponent(search) + '&';
                if (role) url += 'role=' + encodeURIComponent(role) + '&';
                if (kelas) url += 'kelas=' + encodeURIComponent(kelas);
                
                window.location.href = url;
            }, 500);
        }
        
        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (roleSelect) roleSelect.addEventListener('change', applyFilters);
        if (kelasSelect) kelasSelect.addEventListener('change', applyFilters);
        
        // XLSX Import handler
        function importXLSX() {
            const fileInput = document.getElementById('xlsxFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Pilih file terlebih dahulu');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(event) {
                const data = new Uint8Array(event.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                
                if (rows.length < 2) {
                    alert('File tidak valid atau kosong');
                    return;
                }
                
                // Remove header
                rows.shift();
                
                // Filter out empty and comment rows
                const validRows = rows.filter(row => {
                    if (!row || row.length === 0) return false;
                    if (row[0] && row[0].toString().startsWith('#')) return false;
                    return true;
                });
                
                if (validRows.length === 0) {
                    alert('Tidak ada data valid untuk diimport');
                    return;
                }
                
                // Send to server
                const formData = new FormData();
                formData.append('action', 'import_data');
                
                validRows.forEach((row, index) => {
                    formData.append(`rows[${index}][0]`, row[0] || '');
                    formData.append(`rows[${index}][1]`, row[1] || '');
                    formData.append(`rows[${index}][2]`, row[2] || '');
                    formData.append(`rows[${index}][3]`, row[3] || '');
                    formData.append(`rows[${index}][4]`, row[4] || '');
                });
                
                const loader = document.getElementById('loadingSpinner');
                loader.classList.add('active');
                
                fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    loader.classList.remove('active');
                    if (result.success) {
                        alert(`Import selesai:\n${result.imported} user berhasil ditambahkan\n${result.skipped} user skip (sudah ada)`);
                        hideImportModal();
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    loader.classList.remove('active');
                    alert('Error: ' + error.message);
                });
            };
            reader.readAsArrayBuffer(file);
        }
    </script>
</body>
</html>
