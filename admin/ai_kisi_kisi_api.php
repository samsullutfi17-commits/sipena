<?php
require_once '../config.php';
require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Generate Blueprint ────────────────────────────────────────────────────────
if ($action === 'generate_blueprint') {
    $topic        = trim($_POST['topic']        ?? '');
    $mata_kuliah  = trim($_POST['mata_kuliah']  ?? $topic);
    $total        = max(1, min(50, (int)($_POST['total'] ?? 10)));
    $mudah        = max(0, (int)($_POST['mudah']  ?? 3));
    $sedang       = max(0, (int)($_POST['sedang'] ?? 5));
    $sulit        = max(0, (int)($_POST['sulit']  ?? 2));
    $jenis        = trim($_POST['jenis']        ?? 'pg');
    $kognitif     = trim($_POST['kognitif']     ?? 'C1,C2,C3');
    $poin         = max(1, min(100, (int)($_POST['poin'] ?? 4)));

    if (empty($topic)) {
        echo json_encode(['error' => 'Topik tidak boleh kosong']);
        exit;
    }

    $command = sprintf(
        'cd %s && python3 ai_kisi_kisi.py %s %d %d %d %d %s %s %d %s 2>&1',
        escapeshellarg(dirname(__DIR__)),
        escapeshellarg($topic),
        $total, $mudah, $sedang, $sulit,
        escapeshellarg($jenis),
        escapeshellarg($kognitif),
        $poin,
        escapeshellarg($mata_kuliah)
    );

    $output = shell_exec($command);

    if ($output === null) {
        echo json_encode(['error' => 'Gagal menjalankan AI generator']);
        exit;
    }

    $result = json_decode($output, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Respons AI tidak valid: ' . substr($output, 0, 300)]);
        exit;
    }

    // $result should be an array of blueprint rows (from stdout)
    if (isset($result['error'])) {
        echo json_encode($result);
        exit;
    }

    echo json_encode(['kisi_kisi' => $result]);
    exit;
}

// ── Save Soal Batch ───────────────────────────────────────────────────────────
if ($action === 'save_soal_batch') {
    $id_ujian   = (int)($_POST['id_ujian'] ?? 0);
    $soal_json  = $_POST['soal_list'] ?? '[]';

    if ($id_ujian <= 0) {
        echo json_encode(['error' => 'Ujian tidak valid']);
        exit;
    }

    $soalList = json_decode($soal_json, true);
    if (!is_array($soalList) || empty($soalList)) {
        echo json_encode(['error' => 'Daftar soal kosong']);
        exit;
    }

    // Get current max urutan
    $urutanStmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) FROM soal WHERE id_ujian = ?");
    $urutanStmt->execute([$id_ujian]);
    $urutanBase = (int)$urutanStmt->fetchColumn();

    $saved = 0;
    $errors = [];

    foreach ($soalList as $i => $soal) {
        $jenisSoal        = $soal['jenis_soal']       ?? 'pg';
        $pertanyaan       = trim($soal['pertanyaan']   ?? '');
        $pembahasan       = trim($soal['pembahasan']   ?? '');
        $poin             = max(1, (int)($soal['poin'] ?? 4));
        $tingkatKesulitan = $soal['tingkat_kesulitan'] ?? 'sedang';
        $levelKognitif    = $soal['level_kognitif']    ?? 'C1';
        $cpmk             = trim($soal['cpmk']         ?? '');
        $indikator        = trim($soal['indikator']    ?? '');
        $urutan           = $urutanBase + $i + 1;

        if (empty($pertanyaan)) {
            $errors[] = "Baris " . ($i + 1) . ": pertanyaan kosong";
            continue;
        }

        // Build data_tambahan
        $dataTambahan = null;
        if ($jenisSoal === 'tf') {
            $jawabanTf = $soal['jawaban_tf'] ?? true;
            $dataTambahan = json_encode(['jawaban_benar' => (bool)$jawabanTf]);
        } elseif ($jenisSoal === 'short') {
            $keywords = $soal['keywords'] ?? '';
            $dataTambahan = json_encode(['keywords' => $keywords]);
        } elseif ($jenisSoal === 'matching') {
            $pairs = $soal['pairs'] ?? [];
            $dataTambahan = json_encode(['pairs' => $pairs]);
        } elseif ($jenisSoal === 'ordering') {
            $correctOrder = $soal['correct_order'] ?? [];
            $dataTambahan = json_encode(['correct_order' => $correctOrder]);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan,
                                  data_tambahan, tingkat_kesulitan, level_kognitif, cpmk, indikator)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
            ");
            $stmt->execute([
                $id_ujian, $jenisSoal, $pertanyaan, $pembahasan, $poin, $urutan,
                $dataTambahan, $tingkatKesulitan, $levelKognitif,
                $cpmk ?: null, $indikator ?: null
            ]);
            $soalId = $stmt->fetch()['id'];

            // Save opsi for pg/multiple
            if (in_array($jenisSoal, ['pg', 'multiple'])) {
                $opsiList = $soal['opsi'] ?? [];
                foreach ($opsiList as $opsiIdx => $opsi) {
                    $tekOpsi = trim($opsi['teks'] ?? $opsi['teks_opsi'] ?? '');
                    $benar   = !empty($opsi['benar']) ? 1 : 0;
                    if ($tekOpsi) {
                        $stmtOpsi = $pdo->prepare("
                            INSERT INTO opsi_jawaban (id_soal, teks_opsi, benar, urutan)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmtOpsi->execute([$soalId, $tekOpsi, $benar, $opsiIdx + 1]);
                    }
                }
            }

            $saved++;
        } catch (Exception $e) {
            $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
        }
    }

    echo json_encode([
        'saved'  => $saved,
        'errors' => $errors,
        'total'  => count($soalList),
    ]);
    exit;
}

// ── Save Blueprint Only (kisi-kisi tanpa soal) ───────────────────────────────
if ($action === 'save_blueprint') {
    $id_ujian      = (int)($_POST['id_ujian'] ?? 0);
    $blueprint_json = $_POST['blueprint'] ?? '[]';
    $blueprint     = json_decode($blueprint_json, true);

    if ($id_ujian <= 0) { echo json_encode(['error' => 'Ujian tidak valid']); exit; }
    if (!is_array($blueprint) || empty($blueprint)) { echo json_encode(['error' => 'Blueprint kosong']); exit; }

    $urutanStmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) FROM soal WHERE id_ujian = ?");
    $urutanStmt->execute([$id_ujian]);
    $urutanBase = (int)$urutanStmt->fetchColumn();

    $savedIds = [];
    $errors   = [];

    foreach ($blueprint as $i => $row) {
        $jenis     = $row['jenis_soal']        ?? 'pg';
        $cpmk      = trim($row['cpmk']         ?? '');
        $indikator = trim($row['indikator']    ?? '');
        $poin      = max(1, (int)($row['poin'] ?? 4));
        $level     = $row['level_kognitif']    ?? 'C1';
        $kesulitan = $row['tingkat_kesulitan'] ?? 'sedang';
        $urutan    = $urutanBase + $i + 1;

        // Placeholder pertanyaan — diawali [Kisi-kisi] agar bisa dideteksi
        if ($cpmk)           $pertanyaan = '[Kisi-kisi] ' . $cpmk;
        elseif ($indikator)  $pertanyaan = '[Kisi-kisi] ' . $indikator;
        else                 $pertanyaan = '[Kisi-kisi] Soal ' . ($i + 1);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, poin, urutan,
                                  tingkat_kesulitan, level_kognitif, cpmk, indikator)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
            ");
            $stmt->execute([$id_ujian, $jenis, $pertanyaan, $poin, $urutan,
                            $kesulitan, $level, $cpmk ?: null, $indikator ?: null]);
            $savedIds[] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            $errors[] = 'Baris ' . ($i + 1) . ': ' . $e->getMessage();
        }
    }

    echo json_encode(['saved' => count($savedIds), 'ids' => $savedIds, 'errors' => $errors]);
    exit;
}

// ── Update Soal Batch (generate soal dari kisi-kisi tersimpan) ────────────────
if ($action === 'update_soal_batch') {
    $soal_json = $_POST['soal_list'] ?? '[]';
    $soalList  = json_decode($soal_json, true);

    if (!is_array($soalList) || empty($soalList)) {
        echo json_encode(['error' => 'Daftar soal kosong']); exit;
    }

    $updated = 0;
    $errors  = [];

    foreach ($soalList as $i => $soal) {
        $soalId     = (int)($soal['soal_id']    ?? 0);
        $pertanyaan = trim($soal['pertanyaan']   ?? '');
        $pembahasan = trim($soal['pembahasan']   ?? '');
        $poin       = max(1, (int)($soal['poin'] ?? 4));
        $jenis      = $soal['jenis_soal']        ?? 'pg';
        $cpmk       = trim($soal['cpmk']         ?? '');
        $indikator  = trim($soal['indikator']    ?? '');
        $level      = $soal['level_kognitif']    ?? 'C1';
        $kesulitan  = $soal['tingkat_kesulitan'] ?? 'sedang';

        if (!$soalId)     { $errors[] = 'Baris ' . ($i+1) . ': ID soal tidak valid'; continue; }
        if (!$pertanyaan) { $errors[] = 'Baris ' . ($i+1) . ': pertanyaan kosong';   continue; }

        $dataTambahan = null;
        if ($jenis === 'tf')       $dataTambahan = json_encode(['jawaban_benar' => (bool)($soal['jawaban_tf'] ?? true)]);
        elseif ($jenis === 'short')    $dataTambahan = json_encode(['keywords' => $soal['keywords'] ?? '']);
        elseif ($jenis === 'matching') $dataTambahan = json_encode(['pairs' => $soal['pairs'] ?? []]);
        elseif ($jenis === 'ordering') $dataTambahan = json_encode(['correct_order' => $soal['correct_order'] ?? []]);

        try {
            $pdo->prepare("
                UPDATE soal
                SET pertanyaan=?, pembahasan=?, poin=?, jenis_soal=?,
                    tingkat_kesulitan=?, level_kognitif=?, cpmk=?, indikator=?, data_tambahan=?
                WHERE id=?
            ")->execute([$pertanyaan, $pembahasan, $poin, $jenis,
                         $kesulitan, $level, $cpmk ?: null, $indikator ?: null,
                         $dataTambahan, $soalId]);

            // Opsi: hapus lama, insert baru
            if (in_array($jenis, ['pg', 'multiple'])) {
                $pdo->prepare("DELETE FROM opsi_jawaban WHERE id_soal = ?")->execute([$soalId]);
                foreach (($soal['opsi'] ?? []) as $idx => $opsi) {
                    $teks  = trim($opsi['teks'] ?? $opsi['teks_opsi'] ?? '');
                    $benar = !empty($opsi['benar']) ? 1 : 0;
                    if ($teks) {
                        $pdo->prepare("INSERT INTO opsi_jawaban (id_soal, teks_opsi, benar, urutan) VALUES (?,?,?,?)")
                            ->execute([$soalId, $teks, $benar, $idx + 1]);
                    }
                }
            }
            $updated++;
        } catch (Exception $e) {
            $errors[] = 'Soal ID ' . $soalId . ': ' . $e->getMessage();
        }
    }

    echo json_encode(['updated' => $updated, 'errors' => $errors]);
    exit;
}

// ── Edit Single Kisi-Kisi Row ─────────────────────────────────────────────────
if ($action === 'edit_kisi_kisi') {
    $soal_id   = (int)($_POST['soal_id']          ?? 0);
    $cpmk      = trim($_POST['cpmk']              ?? '');
    $indikator = trim($_POST['indikator']          ?? '');
    $level     = trim($_POST['level_kognitif']    ?? 'C1');
    $kesulitan = trim($_POST['tingkat_kesulitan'] ?? 'sedang');
    $jenis     = trim($_POST['jenis_soal']        ?? 'pg');
    $poin      = max(1, (int)($_POST['poin']      ?? 4));

    if ($soal_id <= 0) { echo json_encode(['error' => 'ID soal tidak valid']); exit; }
    if (!$cpmk && !$indikator) { echo json_encode(['error' => 'CPMK atau Indikator harus diisi']); exit; }

    // Fetch existing row to check placeholder status
    $check = $pdo->prepare("SELECT pertanyaan FROM soal WHERE id = ?");
    $check->execute([$soal_id]);
    $existing = $check->fetch();
    if (!$existing) { echo json_encode(['error' => 'Soal tidak ditemukan']); exit; }

    // Keep placeholder text in sync with new CPMK/indikator values
    $pertanyaan = $existing['pertanyaan'];
    if (strpos($pertanyaan, '[Kisi-kisi]') === 0) {
        if ($cpmk)           $pertanyaan = '[Kisi-kisi] ' . $cpmk;
        elseif ($indikator)  $pertanyaan = '[Kisi-kisi] ' . $indikator;
    }

    $pdo->prepare("
        UPDATE soal
           SET cpmk=?, indikator=?, level_kognitif=?, tingkat_kesulitan=?,
               jenis_soal=?, poin=?, pertanyaan=?
         WHERE id=?
    ")->execute([$cpmk ?: null, $indikator ?: null, $level, $kesulitan,
                 $jenis, $poin, $pertanyaan, $soal_id]);

    echo json_encode(['ok' => true]);
    exit;
}

// ── Delete Single Kisi-Kisi Row ───────────────────────────────────────────────
if ($action === 'delete_kisi_kisi') {
    $soal_id = (int)($_POST['soal_id'] ?? 0);
    if ($soal_id <= 0) { echo json_encode(['error' => 'ID soal tidak valid']); exit; }

    // Verify soal exists before deleting
    $check = $pdo->prepare("SELECT id FROM soal WHERE id = ?");
    $check->execute([$soal_id]);
    if (!$check->fetch()) { echo json_encode(['error' => 'Soal tidak ditemukan']); exit; }

    // Remove opsi_jawaban first (FK constraint), then the soal row
    $pdo->prepare("DELETE FROM opsi_jawaban WHERE id_soal = ?")->execute([$soal_id]);
    $pdo->prepare("DELETE FROM soal WHERE id = ?")->execute([$soal_id]);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Action tidak dikenal']);
