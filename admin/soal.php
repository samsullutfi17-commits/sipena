<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$ujianId = (int)($_GET['ujian_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $targetUjianId      = (int)($_POST['id_ujian'] ?? 0);
        $jenisSoal          = $_POST['jenis_soal'] ?? 'pg';
        $pertanyaan         = trim($_POST['pertanyaan'] ?? '');
        $pembahasan         = trim($_POST['pembahasan'] ?? '');
        $poin               = (int)($_POST['poin'] ?? 4);
        $tingkatKesulitan   = $_POST['tingkat_kesulitan'] ?? 'sedang';
        $levelKognitif      = $_POST['level_kognitif'] ?? 'C1';
        $cpmk               = trim($_POST['cpmk'] ?? '');
        $indikator          = trim($_POST['indikator'] ?? '');

        if (!empty($pertanyaan) && $targetUjianId > 0) {
            $urutanStmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 FROM soal WHERE id_ujian = ?");
            $urutanStmt->execute([$targetUjianId]);
            $urutan = $urutanStmt->fetchColumn();
            
            $dataTambahan = null;
            
            if ($jenisSoal === 'tf') {
                $dataTambahan = json_encode(['jawaban_benar' => $_POST['jawaban_tf'] === 'true']);
            } elseif ($jenisSoal === 'short') {
                $dataTambahan = json_encode(['keywords' => $_POST['keywords'] ?? '']);
            } elseif ($jenisSoal === 'esai') {
                $dataTambahan = json_encode(['keywords' => $_POST['keywords'] ?? '']);
            } elseif ($jenisSoal === 'ordering') {
                $items = array_filter(array_map('trim', explode("\n", $_POST['ordering_items'] ?? '')));
                $dataTambahan = json_encode(['correct_order' => array_values($items)]);
            } elseif ($jenisSoal === 'matching') {
                $pairs = [];
                $keys = array_filter(array_map('trim', explode("\n", $_POST['matching_keys'] ?? '')));
                $values = array_filter(array_map('trim', explode("\n", $_POST['matching_values'] ?? '')));
                foreach ($keys as $i => $key) {
                    if (isset($values[$i])) {
                        $pairs[$key] = $values[$i];
                    }
                }
                $dataTambahan = json_encode(['pairs' => $pairs]);
            }
            
            $stmt = $pdo->prepare("INSERT INTO soal (id_ujian, jenis_soal, pertanyaan, pembahasan, poin, urutan, data_tambahan, tingkat_kesulitan, level_kognitif, cpmk, indikator) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([$targetUjianId, $jenisSoal, $pertanyaan, $pembahasan, $poin, $urutan, $dataTambahan, $tingkatKesulitan, $levelKognitif, $cpmk ?: null, $indikator ?: null]);
            $soalId = $stmt->fetch()['id'];
            
            if (in_array($jenisSoal, ['pg', 'multiple'])) {
                $opsiTexts = $_POST['opsi_teks'] ?? [];
                $opsiBenar = $_POST['opsi_benar'] ?? [];
                
                foreach ($opsiTexts as $idx => $teks) {
                    if (!empty(trim($teks))) {
                        $benar = in_array($idx, $opsiBenar) ? 't' : 'f';
                        $pdo->prepare("INSERT INTO opsi_jawaban (id_soal, teks_opsi, benar, urutan) VALUES (?, ?, ?, ?)")
                            ->execute([$soalId, trim($teks), $benar, $idx + 1]);
                    }
                }
            }
            
            $message = 'Soal berhasil ditambahkan!';
            $ujianId = $targetUjianId;
        } else {
            $error = 'Pertanyaan dan ujian wajib diisi!';
        }
    } elseif ($action === 'delete') {
        $soalId = (int)($_POST['soal_id'] ?? 0);
        $pdo->prepare("DELETE FROM soal WHERE id = ?")->execute([$soalId]);
        $message = 'Soal berhasil dihapus!';
    } elseif ($action === 'update') {
        $soalId           = (int)($_POST['soal_id'] ?? 0);
        $jenisSoal        = $_POST['jenis_soal'] ?? 'pg';
        $pertanyaan       = trim($_POST['pertanyaan'] ?? '');
        $pembahasan       = trim($_POST['pembahasan'] ?? '');
        $poin             = (int)($_POST['poin'] ?? 4);
        $tingkatKesulitan = $_POST['tingkat_kesulitan'] ?? 'sedang';
        $levelKognitif    = $_POST['level_kognitif'] ?? 'C1';
        $cpmk             = trim($_POST['cpmk'] ?? '');
        $indikator        = trim($_POST['indikator'] ?? '');
        
        if (!empty($pertanyaan) && $soalId > 0) {
            $dataTambahan = null;
            if ($jenisSoal === 'tf') {
                $dataTambahan = json_encode(['jawaban_benar' => $_POST['jawaban_tf'] === 'true']);
            } elseif ($jenisSoal === 'short' || $jenisSoal === 'esai') {
                $dataTambahan = json_encode(['keywords' => $_POST['keywords'] ?? '']);
            } elseif ($jenisSoal === 'ordering') {
                $items = array_filter(array_map('trim', explode("\n", $_POST['ordering_items'] ?? '')));
                $dataTambahan = json_encode(['correct_order' => array_values($items)]);
            } elseif ($jenisSoal === 'matching') {
                $pairs = [];
                $keys = array_filter(array_map('trim', explode("\n", $_POST['matching_keys'] ?? '')));
                $values = array_filter(array_map('trim', explode("\n", $_POST['matching_values'] ?? '')));
                foreach ($keys as $i => $key) {
                    if (isset($values[$i])) {
                        $pairs[$key] = $values[$i];
                    }
                }
                $dataTambahan = json_encode(['pairs' => $pairs]);
            }
            
            $stmt = $pdo->prepare("UPDATE soal SET jenis_soal = ?, pertanyaan = ?, pembahasan = ?, poin = ?, data_tambahan = ?, tingkat_kesulitan = ?, level_kognitif = ?, cpmk = ?, indikator = ? WHERE id = ?");
            $stmt->execute([$jenisSoal, $pertanyaan, $pembahasan, $poin, $dataTambahan, $tingkatKesulitan, $levelKognitif, $cpmk ?: null, $indikator ?: null, $soalId]);
            
            if (in_array($jenisSoal, ['pg', 'multiple'])) {
                $pdo->prepare("DELETE FROM opsi_jawaban WHERE id_soal = ?")->execute([$soalId]);
                $opsiTexts = $_POST['opsi_teks'] ?? [];
                $opsiBenar = $_POST['opsi_benar'] ?? [];
                foreach ($opsiTexts as $idx => $teks) {
                    if (!empty(trim($teks))) {
                        $benar = in_array($idx, $opsiBenar) ? 't' : 'f';
                        $pdo->prepare("INSERT INTO opsi_jawaban (id_soal, teks_opsi, benar, urutan) VALUES (?, ?, ?, ?)")
                            ->execute([$soalId, trim($teks), $benar, $idx + 1]);
                    }
                }
            }
            $message = 'Soal berhasil diperbarui!';
        }
    }
}

$ujianList = $pdo->query("SELECT u.*, mk.nama_mk FROM ujian u JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id ORDER BY u.created_at DESC")->fetchAll();

$soalList = [];
$currentUjian = null;
if ($ujianId > 0) {
    $soalList = getSoalByUjian($pdo, $ujianId);
    $stmt = $pdo->prepare("SELECT u.*, mk.nama_mk FROM ujian u JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id WHERE u.id = ?");
    $stmt->execute([$ujianId]);
    $currentUjian = $stmt->fetch();
}

$jenisSoalLabels = [
    'pg' => 'Pilihan Ganda',
    'tf' => 'Benar/Salah',
    'short' => 'Jawaban Singkat',
    'multiple' => 'Pilihan Ganda Kompleks',
    'matching' => 'Menjodohkan',
    'ordering' => 'Penyusunan Urutan',
    'esai' => 'Esai',
    'studi_kasus' => 'Studi Kasus'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Soal - Panel Admin</title>
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
                <a href="soal.php" class="nav-item active"><i class="fas fa-question-circle"></i> Bank Soal</a>
                <a href="kisi_kisi.php" class="nav-item"><i class="fas fa-table"></i> Kisi-Kisi</a>
                <a href="hasil.php" class="nav-item"><i class="fas fa-chart-bar"></i> Laporan</a>
                <a href="arsip.php" class="nav-item"><i class="fas fa-history"></i> Riwayat</a>
                <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                <a href="fakultas.php" class="nav-item"><i class="fas fa-building"></i> Organisasi</a>
                <a href="prodi.php" class="nav-item"><i class="fas fa-sitemap"></i> Program</a>
                <a href="kelas.php" class="nav-item"><i class="fas fa-users-rectangle"></i> Kelas / Grup</a>
                <a href="matkul.php" class="nav-item"><i class="fas fa-book"></i> Materi</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Pengguna</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
                <?php endif; ?>
            </nav>
        </aside>
        
        <main class="admin-main">
            <nav class="breadcrumb">
                <a href="dashboard.php">Beranda</a> / 
                <span>Bank Soal</span>
            </nav>
            
            <div class="page-header">
                <h2><i class="fas fa-question-circle"></i> Bank Soal</h2>
                <?php if ($ujianId > 0): ?>
                    <div class="btn-group-header">
                        <a href="export_docx_api.php?ujian_id=<?php echo $ujianId; ?>" class="btn btn-icon" style="background-color: #2b5797; color: white;"><i class="fas fa-file-word"></i> Export DOCX</a>
                        <button class="btn btn-accent btn-icon" onclick="showAiModal()"><i class="fas fa-robot"></i> Generate AI</button>
                        <button class="btn btn-primary btn-icon" onclick="showModal()"><i class="fas fa-plus"></i> Tambah Soal</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="filter-bar">
                <select onchange="location.href='soal.php?ujian_id='+this.value" class="form-select" style="width: auto;">
                    <option value="">-- Pilih Ujian --</option>
                    <?php foreach ($ujianList as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $ujianId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['judul_ujian'] . ' (' . $u['nama_mk'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($currentUjian): ?>
                <div class="card" style="margin-bottom: 20px;">
                    <h3><?php echo htmlspecialchars($currentUjian['judul_ujian']); ?></h3>
                    <p>Materi: <?php echo htmlspecialchars($currentUjian['nama_mk']); ?> | Total Soal: <?php echo count($soalList); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($ujianId > 0): ?>
                <div class="card">
                    <?php if (empty($soalList)): ?>
                        <div class="empty-state">
                            <h3>Belum Ada Soal</h3>
                            <p>Klik tombol "Tambah Soal" untuk menambahkan soal baru</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($soalList as $idx => $soal): ?>
                            <div id="soal-card-<?php echo $soal['id']; ?>" style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div style="flex: 1;">
                                        <span class="badge badge-warning" style="margin-bottom: 10px;">
                                            <?php echo $jenisSoalLabels[$soal['jenis_soal']] ?? $soal['jenis_soal']; ?> (<?php echo $soal['poin']; ?> poin)
                                        </span>
                                        <p class="soal-nomor-label"><strong>Soal <?php echo $idx + 1; ?>:</strong> <?php echo nl2br(htmlspecialchars($soal['pertanyaan'])); ?></p>
                                        
                                        <?php if (isset($soal['opsi']) && !empty($soal['opsi'])): ?>
                                            <ul style="margin-top: 10px; padding-left: 20px;">
                                                <?php foreach ($soal['opsi'] as $opsi): ?>
                                                    <li style="<?php echo $opsi['benar'] ? 'color: green; font-weight: bold;' : ''; ?>">
                                                        <?php echo htmlspecialchars($opsi['teks_opsi']); ?>
                                                        <?php echo $opsi['benar'] ? ' (Benar)' : ''; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($soal['pembahasan'])): ?>
                                            <p style="margin-top: 10px; color: var(--text-muted); font-size: 0.9rem;">
                                                <strong>Pembahasan:</strong> <?php echo htmlspecialchars($soal['pembahasan']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="button" class="btn btn-sm" onclick="editSoalById(<?php echo (int)$soal['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                        <button type="button" class="btn btn-sm btn-delete" onclick="deleteSoal(<?php echo $soal['id']; ?>, this)"><i class="fas fa-trash"></i> Hapus</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <h3>Pilih Ujian</h3>
                        <p>Pilih ujian dari dropdown di atas untuk melihat dan mengelola soal</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <div class="modal-overlay" id="modal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Tambah Soal Baru</h3>
                <button type="button" class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <form method="POST" id="soalForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="soal_id" id="soalIdField" value="">
                <input type="hidden" name="id_ujian" value="<?php echo $ujianId; ?>">
                
                <div class="modal-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label><i class="fas fa-list"></i> Jenis Soal</label>
                            <select name="jenis_soal" id="jenisSoal" class="form-select" onchange="toggleJenisFields()">
                                <option value="pg">Pilihan Ganda</option>
                                <option value="tf">Benar/Salah</option>
                                <option value="short">Jawaban Singkat</option>
                                <option value="multiple">Pilihan Ganda Kompleks</option>
                                <option value="matching">Menjodohkan</option>
                                <option value="ordering">Penyusunan Urutan</option>
                                <option value="esai">Esai</option>
                                <option value="studi_kasus">Studi Kasus</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Poin</label>
                            <input type="number" name="poin" id="poinField" value="4" min="1" max="100">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> Tingkat Kesulitan</label>
                            <select name="tingkat_kesulitan" id="tingkatKesulitanField" class="form-select">
                                <option value="mudah">Mudah</option>
                                <option value="sedang" selected>Sedang</option>
                                <option value="sulit">Sulit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-brain"></i> Level Kognitif (Bloom) <span style="color:var(--text-muted);font-size:0.82rem;">(bisa pilih lebih dari satu)</span></label>
                            <div class="multi-check-dropdown" id="soalKognitifDropdown">
                                <button type="button" class="multi-check-btn" onclick="toggleSoalKognitifDropdown(event)">
                                    <span id="soalKognitifLabel">C1 – Mengingat</span>
                                    <i class="fas fa-chevron-down" id="soalKognitifChevron"></i>
                                </button>
                                <div class="multi-check-panel" id="soalKognitifPanel">
                                    <?php foreach (['C1'=>'C1 – Mengingat','C2'=>'C2 – Memahami','C3'=>'C3 – Menerapkan','C4'=>'C4 – Menganalisis','C5'=>'C5 – Mengevaluasi','C6'=>'C6 – Mencipta'] as $val=>$lbl): ?>
                                    <label class="multi-check-item">
                                        <input type="checkbox" class="soal-kognitif-check" value="<?php echo $val; ?>"
                                            <?php echo $val === 'C1' ? 'checked' : ''; ?>
                                            onchange="updateSoalKognitifLabel()">
                                        <span><?php echo $lbl; ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" name="level_kognitif" id="levelKognitifField" value="C1">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-bullseye"></i> Tujuan Pembelajaran / CPMK <span style="color:var(--text-muted);font-weight:400;">(opsional)</span></label>
                        <input type="text" name="cpmk" id="cpmkField" placeholder="Masukkan tujuan pembelajaran yang dicapai soal ini">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-check-square"></i> Indikator <span style="color:var(--text-muted);font-weight:400;">(opsional)</span></label>
                        <input type="text" name="indikator" id="indikatorField" placeholder="Indikator spesifik yang diukur oleh soal ini">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-question"></i> Pertanyaan</label>
                        <textarea name="pertanyaan" id="pertanyaanField" rows="5" required placeholder="Tulis pertanyaan di sini..." style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 1rem;"></textarea>
                    </div>
                    
                    <div id="pgFields">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-check-circle"></i> Opsi Jawaban
                                <small id="opsiHint" style="color:var(--text-muted);font-weight:400;">(centang jawaban yang benar)</small>
                            </label>
                            <div id="opsiContainer" style="display: flex; flex-direction: column; gap: 12px;"></div>
                            <button type="button" class="ordering-add-btn" style="margin-top: 10px;" onclick="addOpsiRow('', false)">
                                <i class="fas fa-plus"></i> Tambah Opsi
                            </button>
                        </div>
                    </div>
                    
                    <div id="tfFields" style="display: none;">
                        <div class="form-group">
                            <label><i class="fas fa-toggle-on"></i> Jawaban Benar</label>
                            <select name="jawaban_tf" class="form-select">
                                <option value="true">BENAR</option>
                                <option value="false">SALAH</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="keywordFields" style="display: none;">
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Keywords (pisahkan dengan koma)</label>
                            <input type="text" name="keywords" placeholder="keyword1, keyword2, keyword3">
                        </div>
                    </div>
                    
                    <div id="matchingFields" style="display: none;">
                        <div class="matching-panel">
                            <div class="matching-panel-header">
                                <span><i class="fas fa-link"></i> Pasangan Jawaban (Matching)</span>
                                <button type="button" class="btn btn-sm btn-outline" onclick="addMatchingRow()">
                                    <i class="fas fa-plus"></i> Tambah Baris
                                </button>
                            </div>
                            <div class="matching-col-headers">
                                <span class="matching-col-a">KOLOM A</span>
                                <span class="matching-col-arrow"></span>
                                <span class="matching-col-b">KOLOM B</span>
                                <span class="matching-col-del"></span>
                            </div>
                            <div id="matchingRows">
                                <!-- rows injected by JS -->
                            </div>
                        </div>
                        <!-- hidden fields synced before submit -->
                        <textarea name="matching_keys" id="matchingKeysHidden" style="display:none;"></textarea>
                        <textarea name="matching_values" id="matchingValuesHidden" style="display:none;"></textarea>
                    </div>
                    
                    <div id="orderingFields" style="display: none;">
                        <div class="ordering-panel">
                            <div class="ordering-panel-label"><i class="fas fa-sort-numeric-down"></i> Urutan yang Benar (Atas ke Bawah)</div>
                            <div id="orderingRows">
                                <!-- rows injected by JS -->
                            </div>
                            <button type="button" class="ordering-add-btn" onclick="addOrderingRow('')">
                                <i class="fas fa-plus"></i> Tambah Langkah
                            </button>
                        </div>
                        <textarea name="ordering_items" id="orderingItemsHidden" style="display:none;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> Pembahasan (opsional)</label>
                        <textarea name="pembahasan" id="pembahasanField" rows="5" placeholder="Penjelasan jawaban yang benar..." style="width:100%; min-height:120px; padding:12px; border:1px solid var(--border-color); border-radius:8px; font-family:inherit; font-size:1rem;"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="hideModal()"><i class="fas fa-times"></i> Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="modal-overlay" id="aiModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-robot"></i> Generate Soal dengan AI</h3>
                <button type="button" class="modal-close" onclick="hideAiModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="ai-info-box">
                    <i class="fas fa-info-circle"></i>
                    <span>Fitur ini menggunakan AI untuk membuat soal secara otomatis.</span>
                </div>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label><i class="fas fa-lightbulb"></i> Topik / Materi</label>
                        <input type="text" id="aiTopic" placeholder="Contoh: Kode Etik Profesi IT, Prinsip Etika Bisnis...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-list"></i> Jenis Soal <span style="color:var(--text-muted);font-size:0.82rem;">(bisa pilih lebih dari satu)</span></label>
                        <div class="multi-check-dropdown" id="jenisDropdown">
                            <button type="button" class="multi-check-btn" onclick="toggleJenisDropdown(event)">
                                <span id="jenisLabel">Pilihan Ganda</span>
                                <i class="fas fa-chevron-down" id="jenisChevron"></i>
                            </button>
                            <div class="multi-check-panel" id="jenisPanel">
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="pg" checked onchange="updateJenisLabel()"><span>Pilihan Ganda</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="tf" onchange="updateJenisLabel()"><span>Benar/Salah</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="short" onchange="updateJenisLabel()"><span>Jawaban Singkat</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="multiple" onchange="updateJenisLabel()"><span>Pilihan Ganda Kompleks</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="matching" onchange="updateJenisLabel()"><span>Menjodohkan</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="ordering" onchange="updateJenisLabel()"><span>Penyusunan Urutan</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="esai" onchange="updateJenisLabel()"><span>Esai</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="jenis-check" value="studi_kasus" onchange="updateJenisLabel()"><span>Studi Kasus</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label><i class="fas fa-layer-group"></i> Tingkat Kesulitan <span style="color:var(--text-muted);font-size:0.82rem;">(bisa pilih lebih dari satu)</span></label>
                        <div class="multi-check-dropdown" id="kesulitanDropdown">
                            <button type="button" class="multi-check-btn" onclick="toggleKesulitanDropdown(event)">
                                <span id="kesulitanLabel">Sedang</span>
                                <i class="fas fa-chevron-down" id="kesulitanChevron"></i>
                            </button>
                            <div class="multi-check-panel" id="kesulitanPanel">
                                <label class="multi-check-item"><input type="checkbox" class="kesulitan-check" value="mudah" onchange="updateKesulitanLabel()"><span>Mudah</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="kesulitan-check" value="sedang" checked onchange="updateKesulitanLabel()"><span>Sedang</span></label>
                                <label class="multi-check-item"><input type="checkbox" class="kesulitan-check" value="sulit" onchange="updateKesulitanLabel()"><span>Sulit</span></label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-brain"></i> Level Kognitif (Bloom) <span style="color:var(--text-muted);font-size:0.82rem;">(bisa pilih lebih dari satu)</span></label>
                        <div class="multi-check-dropdown" id="kognitifDropdown">
                            <button type="button" class="multi-check-btn" onclick="toggleKognitifDropdown(event)">
                                <span id="kognitifLabel">C1 – Mengingat</span>
                                <i class="fas fa-chevron-down" id="kognitifChevron"></i>
                            </button>
                            <div class="multi-check-panel" id="kognitifPanel">
                                <?php
                                $bloomLevels = [
                                    'C1' => 'C1 – Mengingat',
                                    'C2' => 'C2 – Memahami',
                                    'C3' => 'C3 – Menerapkan',
                                    'C4' => 'C4 – Menganalisis',
                                    'C5' => 'C5 – Mengevaluasi',
                                    'C6' => 'C6 – Mencipta',
                                ];
                                foreach ($bloomLevels as $val => $label): ?>
                                <label class="multi-check-item">
                                    <input type="checkbox" class="kognitif-check" value="<?php echo $val; ?>"
                                        <?php echo $val === 'C1' ? 'checked' : ''; ?>
                                        onchange="updateKognitifLabel()">
                                    <span><?php echo $label; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label><i class="fas fa-bullseye"></i> Tujuan Pembelajaran / CPMK <span style="color:var(--text-muted); font-size:0.85rem;">(opsional)</span></label>
                        <input type="text" id="aiCpmk" placeholder="Masukkan tujuan pembelajaran...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Poin per Soal</label>
                        <input type="number" id="aiPoin" value="4" min="1" max="100">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label><i class="fas fa-sort-numeric-down"></i> Jumlah Soal</label>
                        <input type="number" id="aiCount" value="5" min="1">
                    </div>
                </div>
                
                <div id="aiResult" style="display: none;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                        <h4 style="margin:0;"><i class="fas fa-check-circle" style="color:var(--primary-color);"></i> Soal yang Dihasilkan</h4>
                        <button type="button" class="btn btn-sm btn-outline" id="btnRegenerate" onclick="regenerateAI()" style="display:none;">
                            <i class="fas fa-redo"></i> Generate Ulang
                        </button>
                    </div>
                    <div id="aiWarning" style="display:none; background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:10px 14px; margin-bottom:10px; font-size:0.9rem; color:#856404;">
                        <i class="fas fa-exclamation-triangle"></i> <span id="aiWarningText"></span>
                    </div>
                    <div id="aiSoalList"></div>
                </div>
                
                <div id="aiLoading" style="display: none; text-align: center; padding: 30px;">
                    <div class="spinner"></div>
                    <p>Sedang membuat soal dengan AI...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="hideAiModal()"><i class="fas fa-times"></i> Tutup</button>
                <button type="button" class="btn btn-primary" onclick="generateWithAI()" id="btnGenerate">
                    <i class="fas fa-magic"></i> Generate Soal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveAiSoal()" id="btnSaveAi" style="display: none;">
                    <i class="fas fa-save"></i> Simpan Semua Soal
                </button>
            </div>
            <!-- Regenerate button is inside aiResult header (btnRegenerate) -->
        </div>
    </div>
    
    <script>
        /* All soal data keyed by id — safe JSON in <script> block */
        var allSoalData = <?php
            $soalMap = [];
            foreach ($soalList as $s) { $soalMap[(int)$s['id']] = $s; }
            echo json_encode($soalMap, JSON_HEX_TAG | JSON_HEX_AMP);
        ?>;

        function editSoalById(id) {
            var soal = allSoalData[id];
            if (!soal) { alert('Data soal tidak ditemukan.'); return; }
            editSoal(soal);
        }

        function showModal(isEdit = false) {
            document.getElementById('modal').classList.add('active');
            if (!isEdit) {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Soal Baru';
                document.getElementById('formAction').value = 'create';
                document.getElementById('soalIdField').value = '';
                document.getElementById('soalForm').reset();
                setSoalKognitifChecks('C1');
                document.getElementById('matchingRows').innerHTML = '';
                matchingRowCount = 0;
                document.getElementById('orderingRows').innerHTML = '';
                orderingRowCount = 0;
                resetOpsiRows(5);
                toggleJenisFields();
            }
        }
        function hideModal() {
            document.getElementById('modal').classList.remove('active');
        }

        function parseDT(dt) {
            if (!dt) return {};
            if (typeof dt === 'object') return dt;
            try { return JSON.parse(dt); } catch(e) { return {}; }
        }

        function editSoal(soal) {
            showModal(true);
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Soal';
            document.getElementById('formAction').value = 'update';
            document.getElementById('soalIdField').value = soal.id;
            
            document.getElementById('jenisSoal').value = soal.jenis_soal;
            document.getElementById('pertanyaanField').value = soal.pertanyaan;
            document.getElementById('poinField').value = soal.poin;
            document.getElementById('pembahasanField').value = soal.pembahasan || '';
            document.getElementById('tingkatKesulitanField').value = soal.tingkat_kesulitan || 'sedang';
            setSoalKognitifChecks(soal.level_kognitif || 'C1');
            document.getElementById('cpmkField').value = soal.cpmk || '';
            document.getElementById('indikatorField').value = soal.indikator || '';
            
            toggleJenisFields();
            
            if (soal.jenis_soal === 'pg' || soal.jenis_soal === 'multiple') {
                document.getElementById('opsiContainer').innerHTML = '';
                opsiRowCount = 0;
                if (soal.opsi && soal.opsi.length > 0) {
                    soal.opsi.forEach(function(opsi) {
                        addOpsiRow(opsi.teks_opsi, opsi.benar == 1 || opsi.benar === 't' || opsi.benar === true);
                    });
                } else {
                    resetOpsiRows(5);
                }
            } else if (soal.jenis_soal === 'tf') {
                const data = parseDT(soal.data_tambahan);
                document.querySelector('select[name="jawaban_tf"]').value = data.jawaban_benar ? 'true' : 'false';
            } else if (soal.jenis_soal === 'short' || soal.jenis_soal === 'esai' || soal.jenis_soal === 'studi_kasus') {
                if (soal.data_tambahan) {
                    const data = parseDT(soal.data_tambahan);
                    document.querySelector('input[name="keywords"]').value = data.keywords || '';
                }
            } else if (soal.jenis_soal === 'matching') {
                const data = parseDT(soal.data_tambahan);
                const pairs = data.pairs || {};
                initMatchingRows(Object.keys(pairs), Object.values(pairs));
            } else if (soal.jenis_soal === 'ordering') {
                const data = parseDT(soal.data_tambahan);
                initOrderingRows(data.correct_order || []);
            }
        }

        function toggleJenisFields() {
            const jenis = document.getElementById('jenisSoal').value;
            document.getElementById('pgFields').style.display = (jenis === 'pg' || jenis === 'multiple') ? 'block' : 'none';
            document.getElementById('tfFields').style.display = jenis === 'tf' ? 'block' : 'none';
            document.getElementById('keywordFields').style.display = (jenis === 'short' || jenis === 'esai' || jenis === 'studi_kasus') ? 'block' : 'none';
            document.getElementById('matchingFields').style.display = jenis === 'matching' ? 'block' : 'none';
            document.getElementById('orderingFields').style.display = jenis === 'ordering' ? 'block' : 'none';

            if (jenis === 'pg' || jenis === 'multiple') {
                if (document.getElementById('opsiContainer').children.length === 0) {
                    resetOpsiRows(5);
                } else {
                    renumberOpsiRows(); // re-apply radio vs multi-check behavior
                }
                var hint = document.getElementById('opsiHint');
                if (hint) hint.textContent = jenis === 'pg'
                    ? '(centang satu jawaban yang benar)'
                    : '(centang semua jawaban yang benar)';
            }
            if (jenis === 'matching' && document.getElementById('matchingRows').children.length === 0) {
                initMatchingRows([], []);
            }
            if (jenis === 'ordering' && document.getElementById('orderingRows').children.length === 0) {
                initOrderingRows([]);
            }
        }

        /* ===== Opsi Jawaban dynamic rows (pg / multiple) ===== */
        var opsiRowCount = 0;

        function addOpsiRow(text, isBenar) {
            text    = text    !== undefined ? text    : '';
            isBenar = isBenar !== undefined ? isBenar : false;
            var container = document.getElementById('opsiContainer');
            var idx    = container.children.length;
            var letter = String.fromCharCode(65 + idx);
            var id     = ++opsiRowCount;
            var isSingle = document.getElementById('jenisSoal').value === 'pg';

            var div = document.createElement('div');
            div.className = 'opsi-row';
            div.dataset.rowId = id;
            div.style.cssText = 'display:flex;align-items:center;gap:15px;width:100%;background:#f8f9fa;padding:10px;border-radius:10px;border:1px solid var(--border-color);';

            var changeAttr = isSingle ? ' onchange="enforceSingleOpsi(this)"' : '';
            div.innerHTML =
                '<span class="opsi-label" style="min-width:32px;height:32px;background:var(--primary-color);color:white;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:bold;flex-shrink:0;">' + letter + '</span>' +
                '<input type="text" name="opsi_teks[]" placeholder="Ketik opsi jawaban ' + letter + '..." class="opsi-input" style="flex:1;padding:10px;border:1px solid transparent;background:transparent;font-size:0.95rem;outline:none;">' +
                '<div style="display:flex;align-items:center;gap:8px;padding-left:10px;border-left:1px solid #ddd;">' +
                    '<input type="checkbox" name="opsi_benar[]" value="' + idx + '" class="opsi-checkbox"' + changeAttr + ' style="width:22px;height:22px;cursor:pointer;accent-color:var(--primary-color);">' +
                '</div>' +
                '<button type="button" onclick="removeOpsiRow(this)" title="Hapus opsi ini" style="background:none;border:none;cursor:pointer;color:#dc3545;font-size:1.1rem;padding:4px 6px;flex-shrink:0;"><i class="fas fa-times-circle"></i></button>';

            container.appendChild(div);
            div.querySelector('.opsi-input').value = text;
            if (isBenar) div.querySelector('.opsi-checkbox').checked = true;
        }

        function removeOpsiRow(btn) {
            var container = document.getElementById('opsiContainer');
            if (container.children.length <= 2) {
                alert('Minimal 2 opsi jawaban diperlukan.');
                return;
            }
            btn.closest('.opsi-row').remove();
            renumberOpsiRows();
        }

        function renumberOpsiRows() {
            var rows     = document.querySelectorAll('#opsiContainer .opsi-row');
            var isSingle = document.getElementById('jenisSoal').value === 'pg';
            rows.forEach(function(row, idx) {
                var letter = String.fromCharCode(65 + idx);
                row.querySelector('.opsi-label').textContent = letter;
                var inp = row.querySelector('.opsi-input');
                inp.placeholder = 'Ketik opsi jawaban ' + letter + '...';
                var chk = row.querySelector('.opsi-checkbox');
                chk.value = idx;
                if (isSingle) {
                    chk.setAttribute('onchange', 'enforceSingleOpsi(this)');
                } else {
                    chk.removeAttribute('onchange');
                }
            });
        }

        function resetOpsiRows(count) {
            document.getElementById('opsiContainer').innerHTML = '';
            opsiRowCount = 0;
            for (var i = 0; i < (count || 5); i++) addOpsiRow('', false);
        }

        function enforceSingleOpsi(el) {
            if (el.checked) {
                document.querySelectorAll('#opsiContainer .opsi-checkbox').forEach(function(chk) {
                    if (chk !== el) chk.checked = false;
                });
            }
        }

        /* ===== Matching rows helpers ===== */
        var matchingRowCount = 0;

        function initMatchingRows(keys, vals) {
            var container = document.getElementById('matchingRows');
            container.innerHTML = '';
            matchingRowCount = 0;
            if (keys.length === 0) {
                addMatchingRow('', '');
            } else {
                for (var i = 0; i < keys.length; i++) {
                    addMatchingRow(keys[i], vals[i] || '');
                }
            }
        }

        function addMatchingRow(keyVal, valVal) {
            keyVal = keyVal || '';
            valVal = valVal || '';
            matchingRowCount++;
            var id = matchingRowCount;
            var container = document.getElementById('matchingRows');
            var row = document.createElement('div');
            row.className = 'matching-row';
            row.id = 'mrow-' + id;
            row.innerHTML =
                '<input type="text" class="matching-key-input" placeholder="Item ' + id + '" value="' + escapeHtml(keyVal) + '">' +
                '<div class="matching-row-arrow"><i class="fas fa-chevron-right"></i></div>' +
                '<input type="text" class="matching-val-input" placeholder="Jawaban..." value="' + escapeHtml(valVal) + '">' +
                '<div class="matching-row-del">' +
                  '<button type="button" class="btn-delete-row" onclick="removeMatchingRow(' + id + ')" title="Hapus baris">' +
                    '<i class="fas fa-trash"></i>' +
                  '</button>' +
                '</div>';
            container.appendChild(row);
        }

        function removeMatchingRow(id) {
            var row = document.getElementById('mrow-' + id);
            if (row) {
                var container = document.getElementById('matchingRows');
                if (container.children.length <= 1) {
                    alert('Minimal harus ada satu pasangan jawaban.');
                    return;
                }
                row.remove();
            }
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function syncMatchingData() {
            var keyInputs = document.querySelectorAll('#matchingRows .matching-key-input');
            var valInputs = document.querySelectorAll('#matchingRows .matching-val-input');
            var keys = [], vals = [];
            for (var i = 0; i < keyInputs.length; i++) {
                var k = keyInputs[i].value.trim();
                var v = valInputs[i].value.trim();
                if (k || v) {
                    keys.push(k);
                    vals.push(v);
                }
            }
            document.getElementById('matchingKeysHidden').value   = keys.join('\n');
            document.getElementById('matchingValuesHidden').value = vals.join('\n');
        }

        /* ===== Ordering rows helpers ===== */
        var orderingRowCount = 0;

        function initOrderingRows(items) {
            var container = document.getElementById('orderingRows');
            container.innerHTML = '';
            orderingRowCount = 0;
            if (!items || items.length === 0) {
                addOrderingRow('');
                addOrderingRow('');
                addOrderingRow('');
            } else {
                for (var i = 0; i < items.length; i++) {
                    addOrderingRow(items[i]);
                }
            }
        }

        function addOrderingRow(val) {
            val = val || '';
            orderingRowCount++;
            var num  = document.getElementById('orderingRows').children.length + 1;
            var id   = orderingRowCount;
            var container = document.getElementById('orderingRows');
            var row = document.createElement('div');
            row.className = 'ordering-row';
            row.id = 'orow-' + id;
            row.innerHTML =
                '<div class="ordering-row-num" id="onum-' + id + '">' + num + '</div>' +
                '<input type="text" class="ordering-item-input" placeholder="Urutan ke-' + num + '" value="' + escapeHtml(val) + '">' +
                '<button type="button" class="btn-delete-row" onclick="removeOrderingRow(' + id + ')" title="Hapus langkah"><i class="fas fa-trash"></i></button>';
            container.appendChild(row);
        }

        function removeOrderingRow(id) {
            var row = document.getElementById('orow-' + id);
            if (row) {
                var container = document.getElementById('orderingRows');
                if (container.children.length <= 1) {
                    alert('Minimal harus ada satu langkah.');
                    return;
                }
                row.remove();
                renumberOrderingRows();
            }
        }

        function renumberOrderingRows() {
            var rows = document.querySelectorAll('#orderingRows .ordering-row');
            rows.forEach(function(row, idx) {
                var numEl = row.querySelector('.ordering-row-num');
                if (numEl) numEl.textContent = idx + 1;
                var input = row.querySelector('input');
                if (input && !input.value) input.placeholder = 'Urutan ke-' + (idx + 1);
            });
        }

        function syncOrderingData() {
            var inputs = document.querySelectorAll('#orderingRows .ordering-item-input');
            var items = Array.from(inputs).map(function(el){ return el.value.trim(); }).filter(function(v){ return v !== ''; });
            document.getElementById('orderingItemsHidden').value = items.join('\n');
        }

        document.getElementById('soalForm').addEventListener('submit', function() {
            var jenis = document.getElementById('jenisSoal').value;
            if (jenis === 'matching') {
                syncMatchingData();
            }
            if (jenis === 'ordering') {
                syncOrderingData();
            }
            document.getElementById('levelKognitifField').value = getSelectedSoalKognitif();
        });
        
        var generatedSoal = [];
        
        function showAiModal() {
            document.getElementById('aiModal').classList.add('active');
            document.getElementById('aiResult').style.display  = 'none';
            document.getElementById('aiWarning').style.display = 'none';
            document.getElementById('btnSaveAi').style.display    = 'none';
            document.getElementById('btnRegenerate').style.display = 'none';
            document.getElementById('btnGenerate').style.display  = 'inline-flex';
        }

        function regenerateAI() {
            document.getElementById('aiResult').style.display  = 'none';
            document.getElementById('aiWarning').style.display = 'none';
            document.getElementById('btnSaveAi').style.display    = 'none';
            document.getElementById('btnRegenerate').style.display = 'none';
            document.getElementById('btnGenerate').style.display  = 'inline-flex';
            generateWithAI();
        }
        
        function hideAiModal() {
            document.getElementById('aiModal').classList.remove('active');
        }
        
        function getSelectedJenis() {
            var checked = document.querySelectorAll('.jenis-check:checked');
            var vals = Array.from(checked).map(function(el){ return el.value; });
            return vals.length > 0 ? vals.join(',') : 'pg';
        }

        function updateJenisLabel() {
            var checked = document.querySelectorAll('.jenis-check:checked');
            var labels = Array.from(checked).map(function(el){
                return el.closest('label').querySelector('span').textContent;
            });
            document.getElementById('jenisLabel').textContent =
                labels.length === 0 ? 'Pilih jenis...' : labels.join(', ');
        }

        function toggleJenisDropdown(e) {
            e.stopPropagation();
            var panel  = document.getElementById('jenisPanel');
            var btn    = e.currentTarget;
            var isOpen = panel.classList.contains('open');
            if (isOpen) {
                panel.classList.remove('open');
                btn.classList.remove('open');
            } else {
                panel.classList.add('open');
                btn.classList.add('open');
            }
        }

        function getSelectedKesulitan() {
            var checked = document.querySelectorAll('.kesulitan-check:checked');
            var vals = Array.from(checked).map(function(el){ return el.value; });
            return vals.length > 0 ? vals.join(',') : 'sedang';
        }

        function updateKesulitanLabel() {
            var checked = document.querySelectorAll('.kesulitan-check:checked');
            var labels = Array.from(checked).map(function(el){
                return el.closest('label').querySelector('span').textContent;
            });
            document.getElementById('kesulitanLabel').textContent =
                labels.length === 0 ? 'Pilih tingkat...' : labels.join(', ');
        }

        function toggleKesulitanDropdown(e) {
            e.stopPropagation();
            var panel  = document.getElementById('kesulitanPanel');
            var btn    = e.currentTarget;
            var isOpen = panel.classList.contains('open');
            if (isOpen) {
                panel.classList.remove('open');
                btn.classList.remove('open');
            } else {
                panel.classList.add('open');
                btn.classList.add('open');
            }
        }

        function getSelectedKognitif() {
            var checked = document.querySelectorAll('.kognitif-check:checked');
            var vals = Array.from(checked).map(function(el){ return el.value; });
            return vals.length > 0 ? vals.join(',') : 'C1';
        }

        function updateKognitifLabel() {
            var checked = document.querySelectorAll('.kognitif-check:checked');
            var labels  = Array.from(checked).map(function(el){
                return el.value;
            });
            document.getElementById('kognitifLabel').textContent =
                labels.length === 0 ? 'Pilih level...' : labels.join(', ');
        }

        function toggleKognitifDropdown(e) {
            e.stopPropagation();
            var panel  = document.getElementById('kognitifPanel');
            var btn    = e.currentTarget;
            var isOpen = panel.classList.contains('open');
            if (isOpen) {
                panel.classList.remove('open');
                btn.classList.remove('open');
            } else {
                panel.classList.add('open');
                btn.classList.add('open');
            }
        }

        function getSelectedSoalKognitif() {
            var checked = document.querySelectorAll('.soal-kognitif-check:checked');
            var vals = Array.from(checked).map(function(el){ return el.value; });
            return vals.length > 0 ? vals.join(',') : 'C1';
        }

        function updateSoalKognitifLabel() {
            var checked = document.querySelectorAll('.soal-kognitif-check:checked');
            var labels  = Array.from(checked).map(function(el){ return el.value; });
            document.getElementById('soalKognitifLabel').textContent =
                labels.length === 0 ? 'Pilih level...' : labels.join(', ');
            document.getElementById('levelKognitifField').value = getSelectedSoalKognitif();
        }

        function toggleSoalKognitifDropdown(e) {
            e.stopPropagation();
            var panel  = document.getElementById('soalKognitifPanel');
            var btn    = e.currentTarget;
            var isOpen = panel.classList.contains('open');
            if (isOpen) {
                panel.classList.remove('open');
                btn.classList.remove('open');
            } else {
                panel.classList.add('open');
                btn.classList.add('open');
            }
        }

        function setSoalKognitifChecks(value) {
            var levels = value ? value.split(',').map(function(s){ return s.trim(); }) : ['C1'];
            document.querySelectorAll('.soal-kognitif-check').forEach(function(cb) {
                cb.checked = levels.indexOf(cb.value) !== -1;
            });
            updateSoalKognitifLabel();
        }

        document.addEventListener('click', function(e) {
            var allDropdowns = [
                {id: 'jenisDropdown',       panelId: 'jenisPanel'},
                {id: 'kesulitanDropdown',   panelId: 'kesulitanPanel'},
                {id: 'kognitifDropdown',    panelId: 'kognitifPanel'},
                {id: 'soalKognitifDropdown', panelId: 'soalKognitifPanel'}
            ];
            allDropdowns.forEach(function(d) {
                var dropdown = document.getElementById(d.id);
                if (dropdown && !dropdown.contains(e.target)) {
                    document.getElementById(d.panelId).classList.remove('open');
                    var btn = dropdown.querySelector('.multi-check-btn');
                    if (btn) btn.classList.remove('open');
                }
            });
        });

        function generateWithAI() {
            var topic     = document.getElementById('aiTopic').value.trim();
            var jenis     = getSelectedJenis();
            var count     = document.getElementById('aiCount').value;
            var kesulitan = getSelectedKesulitan();
            var kognitif  = getSelectedKognitif();
            var cpmk      = document.getElementById('aiCpmk').value.trim();
            var poin      = document.getElementById('aiPoin').value;

            if (!topic) {
                alert('Masukkan topik/materi terlebih dahulu');
                return;
            }

            document.getElementById('aiLoading').style.display = 'block';
            document.getElementById('aiResult').style.display = 'none';
            document.getElementById('btnGenerate').disabled = true;

            var formData = new FormData();
            formData.append('topic', topic);
            formData.append('jenis', jenis);
            formData.append('count', count);
            formData.append('kesulitan', kesulitan);
            formData.append('kognitif', kognitif);
            formData.append('cpmk', cpmk);
            formData.append('poin', poin);
            
            fetch('ai_generate_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('aiLoading').style.display = 'none';
                document.getElementById('btnGenerate').disabled = false;
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                generatedSoal = Array.isArray(data) ? data : [data];
                displayGeneratedSoal(generatedSoal);
            })
            .catch(error => {
                document.getElementById('aiLoading').style.display = 'none';
                document.getElementById('btnGenerate').disabled = false;
                alert('Terjadi kesalahan: ' + error.message);
            });
        }
        
        function displayGeneratedSoal(soalList) {
            var container = document.getElementById('aiSoalList');
            container.innerHTML = '';

            var emptyCount = 0;
            var requestedCount = parseInt(document.getElementById('aiCount').value) || soalList.length;

            soalList.forEach(function(soal, index) {
                var div = document.createElement('div');
                var isEmpty = !soal.pertanyaan || soal.pertanyaan.trim() === '';
                if (isEmpty) emptyCount++;
                div.className = 'ai-soal-item' + (isEmpty ? ' ai-soal-empty' : '');

                var html = '<div class="ai-soal-header"><span class="badge badge-warning">' + (soal.jenis_soal || 'pg').toUpperCase() + '</span> Soal ' + (index + 1) + '</div>';

                if (isEmpty) {
                    html += '<p class="ai-soal-text" style="color:var(--text-muted);font-style:italic;">(Pertanyaan tidak dihasilkan — gunakan Generate Ulang)</p>';
                } else {
                    html += '<p class="ai-soal-text">' + soal.pertanyaan + '</p>';
                }

                var jenisSoalPreview = (soal.jenis_soal || 'pg');
                if (jenisSoalPreview === 'matching' && soal.pairs && Object.keys(soal.pairs).length > 0) {
                    html += '<table class="ai-matching-table"><thead><tr><th>Kolom A</th><th></th><th>Kolom B</th></tr></thead><tbody>';
                    Object.keys(soal.pairs).forEach(function(k) {
                        html += '<tr><td>' + k + '</td><td style="text-align:center;color:var(--text-muted)">→</td><td>' + soal.pairs[k] + '</td></tr>';
                    });
                    html += '</tbody></table>';
                } else if (jenisSoalPreview === 'ordering' && soal.correct_order && soal.correct_order.length > 0) {
                    html += '<ol class="ai-ordering-list">';
                    soal.correct_order.forEach(function(item) {
                        html += '<li>' + item + '</li>';
                    });
                    html += '</ol>';
                } else if (soal.opsi && soal.opsi.length > 0) {
                    html += '<ul class="ai-opsi-list">';
                    soal.opsi.forEach(function(opsi) {
                        var isCorrect = opsi.benar === true || opsi.benar === 'true';
                        html += '<li class="' + (isCorrect ? 'correct' : '') + '">' + (opsi.teks || opsi.teks_opsi || '') + (isCorrect ? ' <i class="fas fa-check"></i>' : '') + '</li>';
                    });
                    html += '</ul>';
                }

                if (soal.pembahasan) {
                    html += '<p class="ai-pembahasan"><strong>Pembahasan:</strong> ' + soal.pembahasan + '</p>';
                }
                
                div.innerHTML = html;
                container.appendChild(div);
            });
            
            var validCount = soalList.length - emptyCount;
            var warningEl  = document.getElementById('aiWarning');
            var warningTxt = document.getElementById('aiWarningText');

            if (emptyCount > 0 && validCount === 0) {
                warningTxt.textContent = 'Semua soal kosong (' + emptyCount + ' soal tidak dihasilkan). Silakan gunakan Generate Ulang.';
                warningEl.style.display = 'block';
            } else if (emptyCount > 0) {
                warningTxt.textContent = emptyCount + ' dari ' + soalList.length + ' soal kosong. Soal kosong tidak akan disimpan. Gunakan Generate Ulang jika diperlukan.';
                warningEl.style.display = 'block';
            } else if (soalList.length < requestedCount) {
                warningTxt.textContent = 'AI hanya menghasilkan ' + soalList.length + ' soal dari ' + requestedCount + ' yang diminta. Gunakan Generate Ulang untuk mencoba lagi.';
                warningEl.style.display = 'block';
            } else {
                warningEl.style.display = 'none';
            }

            document.getElementById('aiResult').style.display   = 'block';
            document.getElementById('btnRegenerate').style.display = 'inline-flex';
            document.getElementById('btnGenerate').style.display   = 'none';

            if (validCount > 0) {
                document.getElementById('btnSaveAi').style.display = 'inline-flex';
            } else {
                document.getElementById('btnSaveAi').style.display = 'none';
            }
        }
        
        function saveAiSoal() {
            var validSoal = generatedSoal.filter(function(s) {
                return s.pertanyaan && s.pertanyaan.trim() !== '';
            });
            if (validSoal.length === 0) {
                alert('Tidak ada soal yang valid untuk disimpan. Gunakan Generate Ulang.');
                return;
            }
            
            var ujianId = <?php echo $ujianId; ?>;
            var saved = 0;
            var total = validSoal.length;
            
            document.getElementById('btnSaveAi').disabled = true;
            document.getElementById('btnSaveAi').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            
            var aiKesulitan = getSelectedKesulitan();
            var aiKognitif  = getSelectedKognitif();
            var aiCpmk      = document.getElementById('aiCpmk').value.trim();
            var aiPoin      = document.getElementById('aiPoin').value;

            validSoal.forEach(function(soal) {
                var formData = new FormData();
                formData.append('action', 'create');
                formData.append('id_ujian', ujianId);
                formData.append('jenis_soal', soal.jenis_soal || 'pg');
                formData.append('poin', soal.poin || aiPoin || 4);
                formData.append('pertanyaan', soal.pertanyaan || '');
                formData.append('pembahasan', soal.pembahasan || '');
                formData.append('tingkat_kesulitan', soal.tingkat_kesulitan || aiKesulitan || 'sedang');
                formData.append('level_kognitif', soal.level_kognitif || aiKognitif || 'C1');
                formData.append('cpmk', soal.cpmk || aiCpmk || '');
                
                var jenisSoal = soal.jenis_soal || 'pg';
                if (jenisSoal === 'matching') {
                    var pairs = soal.pairs || {};
                    var keys  = Object.keys(pairs);
                    var vals  = Object.values(pairs);
                    formData.append('matching_keys',   keys.join('\n'));
                    formData.append('matching_values', vals.join('\n'));
                } else if (jenisSoal === 'ordering') {
                    var order = soal.correct_order || [];
                    formData.append('ordering_items', order.join('\n'));
                } else if (soal.opsi && soal.opsi.length > 0) {
                    soal.opsi.forEach(function(opsi, idx) {
                        formData.append('opsi_teks[]', opsi.teks || opsi.teks_opsi || '');
                        if (opsi.benar === true || opsi.benar === 'true') {
                            formData.append('opsi_benar[]', idx);
                        }
                    });
                }
                
                fetch('soal.php?ujian_id=' + ujianId, {
                    method: 'POST',
                    body: formData
                })
                .then(function() {
                    saved++;
                    if (saved >= total) {
                        location.reload();
                    }
                });
            });
        }
        function deleteSoal(soalId, btnEl) {
            if (!confirm('Yakin hapus soal ini?')) return;

            var card = document.getElementById('soal-card-' + soalId);
            btnEl.disabled = true;
            btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            var formData = new FormData();
            formData.append('action', 'delete');
            formData.append('soal_id', soalId);

            fetch('soal.php?ujian=<?php echo $ujianId; ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(res) {
                if (!res.ok) throw new Error('Server error ' + res.status);
                return res.text();
            })
            .then(function() {
                if (card) {
                    card.style.transition = 'opacity 0.25s';
                    card.style.opacity   = '0';
                    setTimeout(function() {
                        card.remove();
                        renumberSoalCards();
                        updateTotalSoalCount();
                    }, 260);
                }
            })
            .catch(function(err) {
                btnEl.disabled = false;
                btnEl.innerHTML = '<i class="fas fa-trash"></i> Hapus';
                alert('Gagal menghapus soal: ' + err.message);
            });
        }

        function renumberSoalCards() {
            var cards = document.querySelectorAll('[id^="soal-card-"]');
            cards.forEach(function(card, idx) {
                var label = card.querySelector('.soal-nomor-label strong');
                if (label) label.textContent = 'Soal ' + (idx + 1) + ':';
            });
        }

        function updateTotalSoalCount() {
            var cards  = document.querySelectorAll('[id^="soal-card-"]');
            var info   = document.querySelector('.card-header p');
            if (info) {
                var match = info.textContent.match(/^(.+?\|\s*Total Soal:\s*)\d+/);
                if (match) info.textContent = match[1] + cards.length;
            }
        }
    </script>
</body>
</html>
