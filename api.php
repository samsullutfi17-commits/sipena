<?php
require_once 'config.php';
require_once 'database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_kelas':
        $prodiId = (int)($_GET['prodi_id'] ?? 0);
        if ($prodiId > 0) {
            $kelas = getKelasByProdi($pdo, $prodiId);
            echo json_encode(['success' => true, 'data' => $kelas]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID Prodi tidak valid']);
        }
        break;
        
    case 'get_ujian':
        $kelasId = (int)($_GET['kelas_id'] ?? 0);
        if ($kelasId > 0) {
            // Kita ambil ujian yang aktif untuk kelas ini
            // DAN mata kuliahnya juga harus tersedia untuk kelas ini (jika ada pembatasan)
            $stmt = $pdo->prepare("
                SELECT u.*, mk.nama_mk, mk.kode_mk,
                       (SELECT SUM(poin) FROM soal WHERE id_ujian = u.id) as total_nilai
                FROM ujian u 
                JOIN ujian_kelas uk ON u.id = uk.id_ujian 
                JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
                WHERE uk.id_kelas = ? AND u.aktif = TRUE
                AND (
                    NOT EXISTS (SELECT 1 FROM mata_kuliah_kelas WHERE id_mata_kuliah = mk.id)
                    OR EXISTS (SELECT 1 FROM mata_kuliah_kelas WHERE id_mata_kuliah = mk.id AND id_kelas = ?)
                )
                ORDER BY u.created_at DESC
            ");
            $stmt->execute([$kelasId, $kelasId]);
            $ujian = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $ujian]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID Kelas tidak valid']);
        }
        break;
        
    case 'get_matkul_kelas':
        $matkulId = (int)($_GET['matkul_id'] ?? 0);
        if ($matkulId > 0) {
            $stmt = $pdo->prepare("SELECT id_kelas FROM mata_kuliah_kelas WHERE id_mata_kuliah = ?");
            $stmt->execute([$matkulId]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'data' => $ids]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID Matkul tidak valid']);
        }
        break;
        
    case 'save_answer':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $soalId = (int)($_POST['soal_id'] ?? 0);
        $jawaban = $_POST['jawaban'] ?? '';
        
        if (!isset($_SESSION['sesi_id'])) {
            echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
            break;
        }
        
        if (is_array($jawaban)) {
            $_SESSION['answers'][$soalId] = $jawaban;
        } else {
            $_SESSION['answers'][$soalId] = $jawaban;
        }
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
