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

$settingsFile = __DIR__ . '/pengaturan.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?? [];
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $settings['nama_institusi']   = trim($_POST['nama_institusi'] ?? '');
        $settings['alamat']           = trim($_POST['alamat'] ?? '');
        $settings['kontak']           = trim($_POST['kontak'] ?? '');
        $settings['tahun_akademik']   = trim($_POST['tahun_akademik'] ?? '');
        $settings['judul_header']     = trim($_POST['judul_header'] ?? '');
        $settings['pengampu_default'] = trim($_POST['pengampu_default'] ?? '');
        $settings['petunjuk_umum']   = trim($_POST['petunjuk_umum'] ?? '');
        $settings['pengumpulan']     = trim($_POST['pengumpulan'] ?? '');

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $logoPath = $uploadDir . 'logo_institusi.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                    $settings['logo_path'] = $logoPath;
                }
            } else {
                $message = 'Format logo tidak didukung. Gunakan PNG atau JPG.';
                $messageType = 'error';
            }
        }

        if (!$message) {
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = 'Pengaturan berhasil disimpan.';
        }

    } elseif ($action === 'hapus_logo') {
        if (!empty($settings['logo_path']) && file_exists($settings['logo_path'])) {
            unlink($settings['logo_path']);
        }
        unset($settings['logo_path']);
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = 'Logo berhasil dihapus.';
    }
}

$logoExists = !empty($settings['logo_path']) && file_exists($settings['logo_path']);
$logoExt    = $logoExists ? pathinfo($settings['logo_path'], PATHINFO_EXTENSION) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Panel Admin</title>
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
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item active"><i class="fas fa-cog"></i> Pengaturan</a>
            </nav>
        </aside>

        <main class="admin-main">
            <nav class="breadcrumb">
                <a href="dashboard.php">Beranda</a> /
                <span>Pengaturan</span>
            </nav>

            <div class="page-header">
                <h2><i class="fas fa-cog"></i> Pengaturan Institusi</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
                    <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 820px;">
                <h3 style="margin-bottom: 6px;"><i class="fas fa-file-word" style="color:#2563eb;"></i> Kop Surat Export DOCX</h3>
                <p style="color:#666; font-size:0.88rem; margin-bottom:20px;">Informasi ini akan ditampilkan sebagai kop surat di bagian atas dokumen soal saat di-export ke DOCX.</p>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">

                    <div class="form-group">
                        <label>Nama Institusi</label>
                        <input type="text" name="nama_institusi"
                               value="<?php echo htmlspecialchars($settings['nama_institusi'] ?? ''); ?>"
                               placeholder="Contoh: Universitas Nusantara">
                    </div>

                    <div class="form-group">
                        <label>Alamat</label>
                        <input type="text" name="alamat"
                               value="<?php echo htmlspecialchars($settings['alamat'] ?? ''); ?>"
                               placeholder="Contoh: Jl. Merdeka No. 1, Kota, Provinsi 12345">
                    </div>

                    <div class="form-group">
                        <label>Kontak (Telp / Website / Email)</label>
                        <input type="text" name="kontak"
                               value="<?php echo htmlspecialchars($settings['kontak'] ?? ''); ?>"
                               placeholder="Contoh: Telp. (021) 123456, Website: contoh.ac.id, email: info@contoh.ac.id">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Judul Ujian (Header)</label>
                            <input type="text" name="judul_header"
                                   value="<?php echo htmlspecialchars($settings['judul_header'] ?? ''); ?>"
                                   placeholder="Contoh: UJIAN AKHIR SEMESTER GANJIL">
                        </div>
                        <div class="form-group">
                            <label>Tahun Akademik</label>
                            <input type="text" name="tahun_akademik"
                                   value="<?php echo htmlspecialchars($settings['tahun_akademik'] ?? ''); ?>"
                                   placeholder="Contoh: 2024/2025">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Pengampu / Dosen (Default)</label>
                        <input type="text" name="pengampu_default"
                               value="<?php echo htmlspecialchars($settings['pengampu_default'] ?? ''); ?>"
                               placeholder="Nama dosen yang tampil di identitas soal">
                        <small style="color:#888; display:block; margin-top:4px;">Kosongkan untuk menggunakan nama akun yang sedang login saat export.</small>
                    </div>

                    <div class="form-group">
                        <label>Petunjuk Umum <small style="font-weight:400; color:#888;">(satu poin per baris → ditampilkan sebagai a, b, c… di dokumen)</small></label>
                        <textarea name="petunjuk_umum" rows="4"
                                  placeholder="Bacalah setiap soal dengan seksama.&#10;Untuk soal pilihan ganda, pilihlah jawaban yang paling tepat.&#10;Untuk soal essay, jawablah dengan jelas dan lengkap."
                                  style="width:100%; padding:9px 12px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; resize:vertical; font-family:inherit;"><?php echo htmlspecialchars($settings['petunjuk_umum'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Pengumpulan <small style="font-weight:400; color:#888;">(satu poin per baris → ditampilkan sebagai a, b, c… di dokumen)</small></label>
                        <textarea name="pengumpulan" rows="3"
                                  placeholder="Tulis [Nama; NIM; Mata Kuliah] pada lembar jawaban Anda.&#10;Kumpulkan lembar jawaban sesuai dengan waktu yang ditentukan.&#10;Pastikan semua bagian soal telah dikerjakan."
                                  style="width:100%; padding:9px 12px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; resize:vertical; font-family:inherit;"><?php echo htmlspecialchars($settings['pengumpulan'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Logo Institusi</label>
                        <?php if ($logoExists): ?>
                            <div style="margin-bottom:12px; display:flex; align-items:center; gap:15px;">
                                <img src="uploads/logo_institusi.<?php echo $logoExt; ?>"
                                     alt="Logo Institusi"
                                     style="max-height:80px; max-width:200px; border:1px solid #ddd; padding:6px; border-radius:6px; background:#fff;">
                                <div>
                                    <p style="font-size:0.85rem; color:#555; margin-bottom:6px;">Logo terpasang</p>
                                    <button type="button" class="btn btn-delete btn-sm btn-icon"
                                            onclick="if(confirm('Hapus logo institusi?')) document.getElementById('formHapusLogo').submit();">
                                        <i class="fas fa-trash"></i> Hapus Logo
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg"
                               style="padding:6px; display:block; margin-top:4px;">
                        <small style="color:#888;">Format: PNG atau JPG. Kosongkan jika tidak ingin mengubah logo.</small>
                    </div>

                    <div style="margin-top:10px;">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>

                <?php if ($logoExists): ?>
                <form id="formHapusLogo" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="hapus_logo">
                </form>
                <?php endif; ?>
            </div>

        </main>
    </div>
</body>
</html>
