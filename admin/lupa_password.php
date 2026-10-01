<?php
require_once '../config.php';
require_once '../database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    
    if (empty($username) || empty($nama_lengkap)) {
        $error = 'Username dan Nama Lengkap (Sesuai Profil) wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND nama_lengkap = ? AND role IN ('admin', 'dosen', 'pengawas')");
        $stmt->execute([$username, $nama_lengkap]);
        $user = $stmt->fetch();
        
        if ($user) {
            $defaultPassword = password_hash('12345*', PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$defaultPassword, $user['id']]);
            
            header('Location: login.php?reset=success');
            exit;
        } else {
            // Cek apakah peserta mencoba reset di halaman ini
            $cekPeserta = $pdo->prepare("SELECT role FROM users WHERE username = ?");
            $cekPeserta->execute([$username]);
            $cekUser = $cekPeserta->fetch();
            if ($cekUser && $cekUser['role'] === 'peserta') {
                $error = 'Akun peserta tidak dapat mereset password di sini. Hubungi admin untuk reset password.';
            } else {
                $error = 'Data tidak ditemukan. Pastikan Username dan Nama Lengkap benar.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Panel Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>Panel Administrator</h1>
            <p class="subtitle">Reset Password Akun</p>
        </div>
    </header>
    
    <div class="login-container">
        <div class="card login-card">
            <h2>Lupa Password</h2>
            <p>Masukkan data Anda untuk mereset password ke default (12345*)</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Masukkan username" required>
                </div>
                
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap (Sesuai Profil)</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </form>
            
            <div class="login-footer">
                <p><a href="login.php">Kembali ke halaman login</a></p>
            </div>
        </div>
    </div>
</body>
</html>