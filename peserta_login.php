<?php
require_once 'config.php';
require_once 'database.php';

// If already logged in as peserta, redirect to index
if (isset($_SESSION['peserta_id'])) {
    header('Location: index.php');
    exit;
}

// If already in exam, redirect to ujian
if (isset($_SESSION['sesi_id']) && isset($_SESSION['exam_started'])) {
    header('Location: ujian.php');
    exit;
}

// Tampilkan halaman "Test Selesai" jika peserta baru menyelesaikan ujian
if (isset($_GET['status']) && $_GET['status'] === 'selesai') {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian Selesai - SIPENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .selesai-card { text-align: center; padding: 50px 30px; }
        .selesai-icon { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #16a34a, #15803d); display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .selesai-icon i { font-size: 2.5rem; color: white; }
        .selesai-title { font-size: 2rem; font-weight: 700; color: #15803d; margin-bottom: 10px; }
        .selesai-subtitle { color: var(--text-muted); font-size: 1.05rem; margin-bottom: 32px; line-height: 1.6; }
        .status-tag { display: inline-block; background: #dcfce7; color: #15803d; border: 1.5px solid #86efac; border-radius: 999px; padding: 6px 22px; font-weight: 600; font-size: 1rem; letter-spacing: 0.5px; margin-bottom: 32px; }
        .divider { border: none; border-top: 1px solid var(--border-color); margin: 28px 0; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>SIPENA – Sistem Penilaian Akademik</h1>
            <p class="subtitle">Berbasis UTBK - Multi Program Studi</p>
        </div>
    </header>
    <div class="login-container" style="max-width: 480px;">
        <div class="card login-card selesai-card">
            <div class="selesai-icon">
                <i class="fas fa-check"></i>
            </div>
            <div class="status-tag"><i class="fas fa-flag-checkered"></i> TEST SELESAI</div>
            <div class="selesai-title">Ujian Telah Selesai</div>
            <div class="selesai-subtitle">
                Jawaban Anda telah berhasil dikumpulkan.<br>
                Terima kasih telah mengikuti ujian.<br>
                Hasil dapat dilihat setelah dosen merilisnya.
            </div>
            <hr class="divider">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;">
                Ingin login kembali dengan akun lain?
            </p>
            <a href="peserta_login.php" class="btn btn-primary" style="width: 100%; display: block;">
                <i class="fas fa-sign-in-alt"></i> Login Kembali
            </a>
            <div style="margin-top: 16px; text-align: center;">
                <a href="admin/login.php" style="color: var(--text-muted); font-size: 0.9rem;">Login sebagai Admin/Dosen/Pengawas</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'peserta'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['peserta_id']       = $user['id'];
            $_SESSION['peserta_username'] = $user['username'];
            $_SESSION['peserta_nama']     = $user['nama_lengkap'];
            $_SESSION['peserta_nim']      = $user['nim'] ?? $user['username'];

            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

            // Auto-expire timed-out sessions before checking
            cleanupExpiredSessions($pdo);

            // --- Cek apakah ada sesi ujian yang sedang berlangsung ---
            $activeSesi = getActiveSeziByUserId($pdo, $user['id']);
            if ($activeSesi) {
                // Hitung sisa waktu berdasarkan waktu_mulai dari DB
                $startEpoch  = strtotime($activeSesi['waktu_mulai']);
                $durasiDetik = (int)$activeSesi['durasi_menit'] * 60;
                $elapsed     = time() - $startEpoch;
                $remaining   = $durasiDetik - $elapsed;

                if ($remaining > 0) {
                    // Masih ada waktu — pulihkan sesi ujian sepenuhnya
                    $_SESSION['sesi_id']          = $activeSesi['id'];
                    $_SESSION['ujian_id']         = $activeSesi['id_ujian'];
                    $_SESSION['mahasiswa_id']     = $activeSesi['id_mahasiswa'];
                    $_SESSION['exam_started']     = true;
                    $_SESSION['exam_start_time']  = $startEpoch;
                    $_SESSION['exam_duration']    = $durasiDetik;
                    $_SESSION['nama_peserta']     = $activeSesi['nama_lengkap'];
                    $_SESSION['nim']              = $activeSesi['nim'];
                    $_SESSION['prodi']            = $activeSesi['nama_prodi'];
                    $_SESSION['kelas']            = $activeSesi['nama_kelas'] ?? '-';
                    $_SESSION['semester']         = $activeSesi['semester'] ?? '-';
                    $_SESSION['mata_kuliah']      = $activeSesi['nama_mk'];
                    $_SESSION['judul_ujian']      = $activeSesi['judul_ujian'];
                    $_SESSION['shuffled_options'] = json_decode($activeSesi['shuffled_options'] ?? '[]', true);
                    $_SESSION['answers']          = json_decode($activeSesi['jawaban_draft'] ?? '[]', true) ?: [];
                    $_SESSION['resume_soal']      = (int)($activeSesi['soal_terakhir'] ?? 1);

                    header('Location: ujian.php?no=' . $_SESSION['resume_soal']);
                    exit;
                } else {
                    // Waktu habis — tandai sesi sebagai timeout
                    $pdo->prepare("UPDATE sesi_ujian SET status = 'timeout', waktu_selesai = NOW() WHERE id = ?")
                        ->execute([$activeSesi['id']]);
                }
            }

            // Tidak ada sesi aktif — bersihkan sisa session lama lalu ke halaman pilih ujian
            unset(
                $_SESSION['sesi_id'], $_SESSION['ujian_id'], $_SESSION['mahasiswa_id'],
                $_SESSION['exam_started'], $_SESSION['exam_start_time'], $_SESSION['exam_duration'],
                $_SESSION['nama_peserta'], $_SESSION['nim'], $_SESSION['prodi'],
                $_SESSION['kelas'], $_SESSION['semester'], $_SESSION['mata_kuliah'],
                $_SESSION['judul_ujian'], $_SESSION['shuffled_options'], $_SESSION['answers'],
                $_SESSION['resume_soal']
            );

            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah, atau akun bukan Peserta!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Peserta - SIPENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>SIPENA – Sistem Penilaian Akademik</h1>
            <p class="subtitle">Berbasis UTBK - Multi Program Studi</p>
        </div>
    </header>

    <div class="login-container" style="max-width: 450px;">
        <div class="card login-card">
            <div style="margin-bottom: 20px;">
                <div style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <i class="fas fa-user-graduate" style="font-size: 1.8rem; color: white;"></i>
                </div>
                <h2>Login Peserta</h2>
                <p>Masukkan username dan password untuk mengakses ujian</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="Masukkan username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password"
                               placeholder="Masukkan password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eye-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 10px;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div style="margin-top: 16px; text-align: center; padding: 12px 16px; background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; font-size: 0.85rem; color: #795548;">
                <i class="fas fa-info-circle" style="color: #f59e0b;"></i>
                <strong>Lupa password?</strong> Hubungi Pengawas/Dosen untuk melakukan reset password.
            </div>

            <div style="margin-top: 16px; text-align: center; padding-top: 15px; border-top: 1px solid var(--border-color);">
                <a href="admin/login.php" style="color: var(--text-muted); font-size: 0.9rem;">Login sebagai Admin/Dosen/Pengawas</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
