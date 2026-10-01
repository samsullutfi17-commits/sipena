<?php
require_once 'config.php';

$fromSelesai = isset($_GET['selesai']) && $_GET['selesai'] === '1';

// Clear peserta session keys
unset(
    $_SESSION['peserta_id'],
    $_SESSION['peserta_username'],
    $_SESSION['peserta_nama'],
    $_SESSION['peserta_nim'],
    $_SESSION['sesi_id'],
    $_SESSION['ujian_id'],
    $_SESSION['mahasiswa_id'],
    $_SESSION['exam_started'],
    $_SESSION['exam_start_time'],
    $_SESSION['exam_duration'],
    $_SESSION['nama_peserta'],
    $_SESSION['nim'],
    $_SESSION['prodi'],
    $_SESSION['kelas'],
    $_SESSION['semester'],
    $_SESSION['mata_kuliah'],
    $_SESSION['judul_ujian'],
    $_SESSION['shuffled_options'],
    $_SESSION['answers'],
    $_SESSION['resume_soal'],
    $_SESSION['exam_result'],
    $_SESSION['exam_completed']
);

if ($fromSelesai) {
    header('Location: peserta_login.php?status=selesai');
} else {
    header('Location: peserta_login.php');
}
exit;
