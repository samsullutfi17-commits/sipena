<?php
require_once 'config.php';
require_once 'database.php';

initDatabase($pdo);
seedMasterData($pdo);
seedUjianEtikaProfesi($pdo);
seedAdminUser($pdo);

// Jika sedang ujian, langsung ke halaman ujian
if (isset($_SESSION['sesi_id']) && isset($_SESSION['exam_started'])) {
    header('Location: ujian.php');
    exit;
}

// Wajib login sebagai peserta terlebih dahulu
if (!isset($_SESSION['peserta_id'])) {
    header('Location: peserta_login.php');
    exit;
}

// Data peserta dari sesi login
$pesertaNama = $_SESSION['peserta_nama'];
$pesertaNim  = $_SESSION['peserta_nim'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $nim      = trim($_POST['nim'] ?? '');
    $prodiId  = (int)($_POST['prodi_id'] ?? 0);
    $kelasId  = (int)($_POST['kelas_id'] ?? 0);
    $ujianId  = (int)($_POST['ujian_id'] ?? 0);

    if (empty($nama) || empty($nim) || $prodiId <= 0 || $kelasId <= 0 || $ujianId <= 0) {
        $error = 'Semua field wajib diisi!';
    } else {
        $mahasiswaId = getOrCreateMahasiswa($pdo, $nim, $nama, $kelasId, $_SESSION['peserta_id']);

        // Auto-expire sessions whose time has passed before checking
        cleanupExpiredSessions($pdo);

        $existingSesi = checkExistingSesi($pdo, $ujianId, $mahasiswaId); // selesai OR timeout
        $ongoingSesi  = getActiveSesi($pdo, $ujianId, $mahasiswaId);     // berlangsung
        if ($existingSesi) {
            $error = 'Anda sudah melakukan ujian sebelumnya dan tidak dapat mengikuti ujian ini kembali.';
        } elseif ($ongoingSesi) {
            $error = 'Anda sudah memiliki sesi ujian ini yang sedang berlangsung. Silakan tunggu atau hubungi pengawas.';
        } else {
            $soalList       = getSoalByUjian($pdo, $ujianId);
            $shuffledOptions = shuffleOptionsForSesi($soalList, 0);

            $stmt = $pdo->prepare("INSERT INTO sesi_ujian (id_ujian, id_mahasiswa, shuffled_options) VALUES (?, ?, ?) RETURNING id");
            $stmt->execute([$ujianId, $mahasiswaId, json_encode($shuffledOptions)]);
            $sesiId = $stmt->fetch()['id'];

            $stmt = $pdo->prepare("SELECT u.*, mk.nama_mk FROM ujian u JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id WHERE u.id = ?");
            $stmt->execute([$ujianId]);
            $ujianData = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT ps.nama_prodi, k.nama_kelas, k.angkatan, k.semester FROM kelas k JOIN program_studi ps ON k.id_program_studi = ps.id WHERE k.id = ?");
            $stmt->execute([$kelasId]);
            $kelasData = $stmt->fetch();

            $_SESSION['sesi_id']         = $sesiId;
            $_SESSION['ujian_id']        = $ujianId;
            $_SESSION['mahasiswa_id']    = $mahasiswaId;
            $_SESSION['exam_started']    = true;
            $_SESSION['exam_start_time'] = time();
            $_SESSION['exam_duration']   = $ujianData['durasi_menit'] * 60;
            $_SESSION['nama_peserta']    = $nama;
            $_SESSION['nim']             = $nim;
            $_SESSION['prodi']           = $kelasData['nama_prodi'];
            $_SESSION['kelas']           = $kelasData['nama_kelas'] ?? '-';
            $_SESSION['semester']        = $kelasData['semester'] ?? '-';
            $_SESSION['mata_kuliah']     = $ujianData['nama_mk'];
            $_SESSION['judul_ujian']     = $ujianData['judul_ujian'];
            $_SESSION['shuffled_options'] = $shuffledOptions;
            $_SESSION['answers']         = [];

            header('Location: ujian.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian Online - SIPENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .field-locked {
            background-color: #f0faf6 !important;
            border-color: var(--primary) !important;
            color: var(--text-dark) !important;
            font-weight: 500;
            cursor: default;
        }
        .field-locked-label {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .field-locked-label i {
            color: var(--primary);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container header-flex">
            <div>
                <h1>SIPENA – Sistem Penilaian Akademik</h1>
                <p class="subtitle">Selamat datang, <strong><?php echo htmlspecialchars($pesertaNama); ?></strong></p>
            </div>
            <a href="peserta_logout.php" class="btn btn-accent" style="white-space: nowrap;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="login-container" style="max-width: 550px;">
        <div class="card login-card">
            <h2>Selamat Datang</h2>
            <p>Silahkan lengkapi data diri Anda untuk memulai ujian</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="login-form">
                <!-- Nama Lengkap - auto-isi dari sesi -->
                <div class="form-group">
                    <label for="nama" class="field-locked-label">
                        Nama Lengkap <i class="fas fa-lock" title="Terisi otomatis dari akun Anda"></i>
                    </label>
                    <input type="text" id="nama" name="nama"
                           value="<?php echo htmlspecialchars($pesertaNama); ?>"
                           class="field-locked" readonly required>
                </div>

                <!-- NPM / NIDN - auto-isi dari sesi -->
                <div class="form-group">
                    <label for="nim" class="field-locked-label">
                        NPM / NIDN <i class="fas fa-lock" title="Terisi otomatis dari akun Anda"></i>
                    </label>
                    <input type="text" id="nim" name="nim"
                           value="<?php echo htmlspecialchars($pesertaNim); ?>"
                           class="field-locked" readonly required>
                </div>

                <div class="form-group">
                    <label for="prodi_id">Program</label>
                    <select id="prodi_id" name="prodi_id" required class="form-select">
                        <option value="">-- Pilih Program --</option>
                        <?php
                        $activeProdi = $pdo->query("
                            SELECT DISTINCT ps.*
                            FROM program_studi ps
                            JOIN kelas k ON k.id_program_studi = ps.id
                            JOIN ujian_kelas uk ON uk.id_kelas = k.id
                            JOIN ujian u ON u.id = uk.id_ujian
                            WHERE u.aktif = true
                            ORDER BY ps.nama_prodi
                        ")->fetchAll();
                        foreach ($activeProdi as $prodi): ?>
                            <option value="<?php echo $prodi['id']; ?>"><?php echo htmlspecialchars($prodi['nama_prodi'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="kelas_id">Grup</label>
                    <select id="kelas_id" name="kelas_id" required class="form-select" disabled>
                        <option value="">-- Pilih Grup --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ujian_id">Penilaian yang Tersedia</label>
                    <select id="ujian_id" name="ujian_id" required class="form-select" disabled>
                        <option value="">-- Pilih Ujian --</option>
                    </select>
                </div>

                <div id="ujian-info" style="display: none;" class="info-box">
                    <h4>Informasi Ujian</h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span>Materi</span>
                            <span id="info-matkul">-</span>
                        </div>
                        <div class="info-item">
                            <span>Jenis Ujian</span>
                            <span id="info-jenis">-</span>
                        </div>
                        <div class="info-item">
                            <span>Durasi</span>
                            <span id="info-durasi">-</span>
                        </div>
                        <div class="info-item">
                            <span>Total Nilai</span>
                            <span id="info-nilai">-</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="btn-mulai" disabled>Mulai Ujian</button>
            </form>

            <div style="margin-top: 20px; text-align: center; padding-top: 15px; border-top: 1px solid var(--border-color);">
                <a href="admin/login.php" style="color: var(--text-muted); font-size: 0.9rem;">Login sebagai Dosen/Admin</a>
            </div>
        </div>
    </div>

    <script>
        const prodiSelect = document.getElementById('prodi_id');
        const kelasSelect = document.getElementById('kelas_id');
        const ujianSelect = document.getElementById('ujian_id');
        const ujianInfo   = document.getElementById('ujian-info');
        const btnMulai    = document.getElementById('btn-mulai');

        let ujianData = [];

        prodiSelect.addEventListener('change', function () {
            const prodiId = this.value;
            kelasSelect.innerHTML = '<option value="">-- Pilih Grup --</option>';
            kelasSelect.disabled  = true;
            ujianSelect.innerHTML = '<option value="">-- Pilih Ujian --</option>';
            ujianSelect.disabled  = true;
            ujianInfo.style.display = 'none';
            btnMulai.disabled = true;

            if (prodiId) {
                fetch('api.php?action=get_kelas&prodi_id=' + prodiId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            data.data.forEach(k => {
                                const opt = document.createElement('option');
                                opt.value = k.id;
                                opt.textContent = k.nama_kelas + ' (' + k.angkatan + ')';
                                kelasSelect.appendChild(opt);
                            });
                            kelasSelect.disabled = false;
                        }
                    });
            }
        });

        kelasSelect.addEventListener('change', function () {
            const kelasId = this.value;
            ujianSelect.innerHTML = '<option value="">-- Pilih Ujian --</option>';
            ujianSelect.disabled  = true;
            ujianInfo.style.display = 'none';
            btnMulai.disabled = true;
            ujianData = [];

            if (kelasId) {
                fetch('api.php?action=get_ujian&kelas_id=' + kelasId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            ujianData = data.data;
                            data.data.forEach(u => {
                                const opt = document.createElement('option');
                                opt.value = u.id;
                                opt.textContent = u.judul_ujian + ' (' + u.jenis_ujian + ')';
                                ujianSelect.appendChild(opt);
                            });
                            ujianSelect.disabled = false;
                        } else {
                            ujianSelect.innerHTML = '<option value="">-- Tidak ada ujian tersedia --</option>';
                        }
                    });
            }
        });

        ujianSelect.addEventListener('change', function () {
            const ujianId = this.value;
            btnMulai.disabled = true;

            if (ujianId) {
                const ujian = ujianData.find(u => u.id == ujianId);
                if (ujian) {
                    document.getElementById('info-matkul').textContent  = ujian.nama_mk;
                    document.getElementById('info-jenis').textContent   = ujian.jenis_ujian;
                    document.getElementById('info-durasi').textContent  = ujian.durasi_menit + ' Menit';
                    document.getElementById('info-nilai').textContent   = ujian.total_nilai + ' Poin';
                    ujianInfo.style.display = 'block';
                    btnMulai.disabled = false;
                }
            } else {
                ujianInfo.style.display = 'none';
            }
        });
    </script>
</body>
</html>
