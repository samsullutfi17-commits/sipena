<?php
require_once '../config.php';
require_once '../database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$ujianList = $pdo->query("
    SELECT u.id, u.judul_ujian, mk.nama_mk
    FROM ujian u
    JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id
    ORDER BY u.created_at DESC
")->fetchAll();

$selectedUjianId = (int)($_GET['ujian_id'] ?? 0);
$soalKisiKisi    = [];
$selectedUjian   = null;

if ($selectedUjianId > 0) {
    $stmtU = $pdo->prepare("SELECT u.*, mk.nama_mk FROM ujian u JOIN mata_kuliah mk ON u.id_mata_kuliah = mk.id WHERE u.id = ?");
    $stmtU->execute([$selectedUjianId]);
    $selectedUjian = $stmtU->fetch();

    $stmtS = $pdo->prepare("
        SELECT id, jenis_soal, pertanyaan, cpmk, indikator, level_kognitif, tingkat_kesulitan, poin, urutan
        FROM soal WHERE id_ujian = ? ORDER BY urutan
    ");
    $stmtS->execute([$selectedUjianId]);
    $soalKisiKisi = $stmtS->fetchAll();
}

$jenisLabel = [
    'pg'=>'Pilihan Ganda','multiple'=>'PG Kompleks','tf'=>'Benar/Salah',
    'short'=>'Jawaban Singkat','matching'=>'Menjodohkan','ordering'=>'Penyusunan Urutan',
    'esai'=>'Esai','studi_kasus'=>'Studi Kasus',
];
$kognitifLabel = [
    'C1'=>'C1 – Mengingat','C2'=>'C2 – Memahami','C3'=>'C3 – Menerapkan',
    'C4'=>'C4 – Menganalisis','C5'=>'C5 – Mengevaluasi','C6'=>'C6 – Mencipta',
];
$kesulitanLabel = ['mudah'=>'Mudah','sedang'=>'Sedang','sulit'=>'Sulit'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kisi-Kisi Soal - Panel Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin-sidebar.js" defer></script>
    <style>
        /* ── CSS variable aliases (style.css uses --primary/--accent/--text-dark) */
        :root {
            --primary-color: var(--primary);
            --accent-color:  var(--accent);
            --text-color:    var(--text-dark);
        }

        /* ── Kisi-Kisi Table ─────────────────── */
        .kisi-filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap}
        .kisi-filter-bar select{flex:1;min-width:220px;padding:10px 14px;border:1.5px solid var(--border-color);border-radius:8px;font-size:.95rem;font-family:inherit;background:#fff}
        .kisi-filter-bar select:focus{outline:none;border-color:var(--primary-color)}
        .kisi-table-wrap{overflow-x:auto;border-radius:10px;border:1px solid var(--border-color);background:#fff}
        .kisi-table{width:100%;border-collapse:collapse;font-size:.88rem}
        .kisi-table thead tr{background:var(--primary-color);color:#fff}
        .kisi-table th,.kisi-table td{padding:10px 12px;border:1px solid #dce4db;vertical-align:top;text-align:left}
        .kisi-table th{font-weight:600;white-space:nowrap;font-size:.85rem}
        .kisi-table tbody tr:nth-child(even){background:#f7faf8}
        .kisi-table tbody tr:hover{background:#edf7f1}
        .kisi-table td.num{text-align:center;width:44px}
        .kisi-table td.poin-col{text-align:center;width:60px;font-weight:600;color:var(--primary-color)}
        .badge-kesulitan{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.78rem;font-weight:600}
        .badge-mudah{background:#d1fae5;color:#065f46}
        .badge-sedang{background:#fef3c7;color:#92400e}
        .badge-sulit{background:#fee2e2;color:#991b1b}
        .badge-kognitif{display:inline-block;padding:2px 9px;border-radius:4px;font-size:.78rem;font-weight:600;background:#e0f2fe;color:#0c4a6e}
        .badge-jenis{display:inline-block;padding:2px 9px;border-radius:4px;font-size:.78rem;background:#e6f4ee;color:var(--primary-color)}
        .kisi-empty{text-align:center;padding:56px 20px;color:var(--text-muted)}
        .kisi-empty i{font-size:2.5rem;margin-bottom:12px;display:block}
        .kisi-summary{display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap}
        .kisi-summary-card{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 20px;min-width:110px;text-align:center}
        .kisi-summary-card .val{font-size:1.6rem;font-weight:700;color:var(--primary-color)}
        .kisi-summary-card .lbl{font-size:.78rem;color:var(--text-muted);margin-top:2px}

        /* ── Modal shared ─────────────────────── */
        .kk-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;overflow-y:auto;padding:24px 16px}
        .kk-modal-overlay.active{display:flex;align-items:flex-start;justify-content:center}
        .kk-modal{background:#fff;border-radius:14px;width:100%;max-width:860px;box-shadow:0 20px 60px rgba(0,0,0,.2);display:flex;flex-direction:column;max-height:90vh}
        .kk-modal-header{padding:20px 24px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
        .kk-modal-header h3{margin:0;font-size:1.1rem;display:flex;align-items:center;gap:10px}
        .kk-modal-body{padding:24px;overflow-y:auto;flex:1}
        .kk-modal-footer{padding:16px 24px;border-top:1px solid var(--border-color);display:flex;gap:10px;justify-content:flex-end;flex-shrink:0;flex-wrap:wrap}
        .btn-close-modal{background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);padding:4px;line-height:1}
        .btn-close-modal:hover{color:var(--text-color)}

        /* ── Form rows ─────────────────────────── */
        .form-row{display:grid;gap:16px;margin-bottom:16px}
        .form-row-2{grid-template-columns:1fr 1fr}
        .form-row-3{grid-template-columns:1fr 1fr 1fr}
        .form-group label{display:block;font-weight:600;font-size:.88rem;margin-bottom:6px;color:var(--text-color)}
        .form-group input[type=text],.form-group input[type=number],.form-group select,.form-group textarea{
            width:100%;padding:9px 12px;border:1.5px solid var(--border-color);border-radius:8px;
            font-size:.9rem;font-family:inherit;background:#fff;box-sizing:border-box}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary-color)}
        .check-group{display:flex;flex-wrap:wrap;gap:8px 14px;margin-top:4px}
        .check-group label{font-weight:400;display:flex;align-items:center;gap:5px;cursor:pointer;font-size:.88rem}
        .dist-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .dist-row label{min-width:60px;font-weight:600;font-size:.88rem}
        .dist-row input{width:70px;padding:7px 10px;border:1.5px solid var(--border-color);border-radius:8px;font-size:.9rem;font-family:inherit;text-align:center}
        .dist-row .dist-badge{padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600}
        #distTotal{font-size:.85rem;color:var(--text-muted);margin-top:6px}
        #distTotal.over{color:#c0392b}

        /* ── Blueprint preview table ──────────── */
        .bp-table-wrap{overflow-x:auto;border-radius:8px;border:1px solid var(--border-color);margin-top:16px}
        .bp-table{width:100%;border-collapse:collapse;font-size:.83rem}
        .bp-table thead tr{background:#1F5C3E;color:#fff}
        .bp-table th,.bp-table td{padding:8px 10px;border:1px solid #dce4db;vertical-align:middle}
        .bp-table th{font-size:.8rem;font-weight:600;white-space:nowrap}
        .bp-table tbody tr:nth-child(even){background:#f7faf8}
        .bp-table td input,.bp-table td select{width:100%;padding:4px 6px;border:1px solid var(--border-color);border-radius:4px;font-size:.8rem;font-family:inherit;background:#fff;min-width:80px}
        .bp-table td input:focus,.bp-table td select:focus{outline:none;border-color:var(--primary-color)}
        .btn-del-row{background:none;border:none;cursor:pointer;color:#c0392b;padding:2px 6px;border-radius:4px;font-size:.85rem}
        .btn-del-row:hover{background:#fee2e2}

        /* ── Soal generator list ─────────────── */
        .gen-soal-list{display:flex;flex-direction:column;gap:12px}
        .gen-soal-item{border:1.5px solid var(--border-color);border-radius:10px;overflow:hidden}
        .gen-soal-item-head{display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f7faf8;flex-wrap:wrap}
        .gen-soal-item-head .no{font-weight:700;color:var(--primary-color);min-width:28px}
        .gen-soal-item-head .meta{flex:1;font-size:.83rem;color:var(--text-muted)}
        .gen-soal-item-head .status{font-size:.8rem;font-weight:600;padding:2px 10px;border-radius:20px}
        .status-pending{background:#f3f4f6;color:#6b7280}
        .status-loading{background:#fef3c7;color:#92400e}
        .status-done{background:#d1fae5;color:#065f46}
        .status-error{background:#fee2e2;color:#991b1b}
        .gen-soal-item-body{padding:12px 14px;border-top:1px solid var(--border-color);display:none}
        .gen-soal-item-body.visible{display:block}
        .gen-soal-preview{font-size:.85rem;color:var(--text-color);line-height:1.6}
        .gen-soal-preview strong{color:var(--primary-color)}

        .loading-bar{height:4px;background:linear-gradient(90deg,var(--primary-color),var(--accent-color));border-radius:2px;animation:loadingAnim 1.2s infinite}
        @keyframes loadingAnim{0%{width:0%;margin-left:0}50%{width:70%;margin-left:15%}100%{width:0%;margin-left:100%}}

        .progress-info{text-align:center;padding:12px;font-size:.9rem;color:var(--text-muted)}
        .step-indicator{display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:.85rem}
        .step-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;flex-shrink:0}
        .step-dot.active{background:var(--primary-color);color:#fff}
        .step-dot.done{background:#d1fae5;color:#065f46}
        .step-dot.inactive{background:#f3f4f6;color:#9ca3af}
        .step-line{flex:1;height:2px;background:var(--border-color)}
        .step-line.done{background:var(--primary-color)}

        /* ── Kop Dokumen (hidden normally, shown on print) ── */
        .kop-dokumen{display:none}
        @media print{
            .admin-sidebar,.kisi-filter-bar,.btn-print,.kk-modal-overlay,
            .admin-topbar .btn,.admin-topbar{display:none!important}
            .admin-container{display:block!important}
            .admin-content{padding:0!important}
            body{background:#fff!important;margin:0;padding:0}
            .kisi-summary{display:none!important}
            .status-col,.aksi-col,.print-hide{display:none!important}

            /* Show kop dokumen when printing */
            .kop-dokumen{
                display:block!important;
                font-family:Arial,sans-serif;
                color:#000;
                margin-bottom:6px;
            }
            .kop-line-top{
                border-top:3px solid #000;
                margin-bottom:6px;
            }
            .kop-content{
                display:flex;
                align-items:center;
                gap:14px;
                padding:4px 0;
            }
            .kop-logo{
                width:82px;
                height:82px;
                object-fit:contain;
                flex-shrink:0;
            }
            .kop-text{
                flex:1;
                text-align:center;
            }
            .kop-name{
                font-size:20pt;
                font-weight:900;
                letter-spacing:.5px;
                line-height:1.1;
                text-transform:uppercase;
            }
            .kop-akreditasi{
                font-size:10pt;
                font-weight:700;
                margin-top:2px;
            }
            .kop-addr{
                font-size:8.5pt;
                font-weight:400;
                margin-top:1px;
                line-height:1.4;
            }
            .kop-lines{
                margin-top:6px;
                border-top:3px solid #000;
            }
            .kop-lines-thin{
                border-top:1px solid #000;
                margin-top:2px;
            }

            /* Table print tweaks */
            .kisi-table-wrap{border:none!important}
            .kisi-table th{-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .badge-mudah,.badge-sedang,.badge-sulit,.badge-kognitif,.badge-jenis{
                -webkit-print-color-adjust:exact;print-color-adjust:exact
            }
            .btn-aksi{display:none!important}
        }
        @media(max-width:640px){.form-row-2,.form-row-3{grid-template-columns:1fr}}

        /* ── Aksi buttons in table ───────────── */
        .btn-aksi{border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:.8rem;font-weight:600;transition:all .15s;white-space:nowrap}
        .btn-aksi-edit{background:#e0f2fe;color:#0369a1;}
        .btn-aksi-edit:hover{background:#0369a1;color:#fff}
        .btn-aksi-del{background:#fee2e2;color:#991b1b;margin-left:4px}
        .btn-aksi-del:hover{background:#991b1b;color:#fff}
        .kisi-table td.aksi-col{text-align:center;white-space:nowrap;width:90px}
        .kisi-table th.aksi-col{text-align:center}
    </style>
</head>
<body>
<header class="header">
    <div class="container header-flex">
        <div>
            <h1>SIPENA – Sistem Penilaian Akademik</h1>
            <p class="subtitle">Kisi-Kisi Soal</p>
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
            <a href="kisi_kisi.php" class="nav-item active"><i class="fas fa-table"></i> Kisi-Kisi</a>
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

    <main class="admin-content">
        <!-- ── Kop Dokumen (only visible when printing) ──────────────────────── -->
        <div class="kop-dokumen">
            <div class="kop-line-top"></div>
            <div class="kop-content">
                <img src="uploads/logo_institusi.png" class="kop-logo" alt="Logo Universitas Hamzanwadi">
                <div class="kop-text">
                    <div class="kop-name">UNIVERSITAS HAMZANWADI</div>
                    <div class="kop-akreditasi">AKREDITASI BAN-PT NOMOR : 1702/SK/BAN-PT/Ak.Ppj/PT/X/2022</div>
                    <div class="kop-addr">Sekretariat : Jalan TGKH. Muhammad Zainuddin Abdul Madjid No. 132 Pancor (83611) Selong-Lombok Timur-NTB</div>
                    <div class="kop-addr">Telp: (0376) 21394 22953 &nbsp; Fax. (0376) 22954 &nbsp; Email: universitas@hamzanwadi.ac.id</div>
                    <div class="kop-addr">Website : www.hamzanwadi.ac.id</div>
                </div>
            </div>
            <div class="kop-lines"></div>
            <div class="kop-lines-thin"></div>
        </div>

        <!-- ── Top bar ───────────────────────────────────────────────────────── -->
        <div class="admin-topbar" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="margin:0;font-size:1.3rem;"><i class="fas fa-table" style="color:var(--primary-color);margin-right:8px;"></i>Kisi-Kisi Soal</h2>
                <p style="margin:4px 0 0;color:var(--text-muted);font-size:.88rem;">Buat atau tampilkan kisi-kisi berdasarkan ujian</p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn btn-primary" onclick="openModal1()">
                    <i class="fas fa-magic"></i> Generate Kisi-Kisi AI
                </button>
                <?php if ($selectedUjianId > 0 && count($soalKisiKisi) > 0): ?>
                <button class="btn" style="background:var(--accent-color);color:#fff;" onclick="openModal2FromExisting()">
                    <i class="fas fa-bolt"></i> Generate Soal dari Kisi-Kisi
                </button>
                <button class="btn btn-primary btn-print" onclick="window.print()" style="background:#6b7280;">
                    <i class="fas fa-print"></i> Cetak
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Ujian filter ──────────────────────────────────────────────────── -->
        <div class="kisi-filter-bar">
            <form method="GET" action="kisi_kisi.php" style="display:flex;gap:10px;width:100%;flex-wrap:wrap;align-items:center;">
                <label style="font-weight:600;white-space:nowrap;color:var(--text-color);">
                    <i class="fas fa-file-alt" style="color:var(--primary-color);"></i> Pilih Ujian:
                </label>
                <select name="ujian_id" onchange="this.form.submit()">
                    <option value="">-- Pilih Ujian --</option>
                    <?php foreach ($ujianList as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $selectedUjianId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['judul_ujian']); ?> &ndash; <?php echo htmlspecialchars($u['nama_mk']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- ── Existing kisi-kisi table ─────────────────────────────────────── -->
        <?php if ($selectedUjianId > 0 && $selectedUjian): ?>
        <?php if (count($soalKisiKisi) > 0):
            $totalPoin = array_sum(array_column($soalKisiKisi, 'poin'));
            $cntMudah  = count(array_filter($soalKisiKisi, fn($s) => $s['tingkat_kesulitan'] === 'mudah'));
            $cntSedang = count(array_filter($soalKisiKisi, fn($s) => $s['tingkat_kesulitan'] === 'sedang'));
            $cntSulit  = count(array_filter($soalKisiKisi, fn($s) => $s['tingkat_kesulitan'] === 'sulit'));
        ?>
        <div class="kisi-summary">
            <div class="kisi-summary-card"><div class="val"><?php echo count($soalKisiKisi); ?></div><div class="lbl">Total Soal</div></div>
            <div class="kisi-summary-card"><div class="val"><?php echo $totalPoin; ?></div><div class="lbl">Total Poin</div></div>
            <div class="kisi-summary-card"><div class="val" style="color:#065f46;"><?php echo $cntMudah; ?></div><div class="lbl">Mudah</div></div>
            <div class="kisi-summary-card"><div class="val" style="color:#92400e;"><?php echo $cntSedang; ?></div><div class="lbl">Sedang</div></div>
            <div class="kisi-summary-card"><div class="val" style="color:#991b1b;"><?php echo $cntSulit; ?></div><div class="lbl">Sulit</div></div>
        </div>

        <div style="margin-bottom:12px;">
            <strong><?php echo htmlspecialchars($selectedUjian['judul_ujian']); ?></strong>
            <span style="color:var(--text-muted);"> &mdash; <?php echo htmlspecialchars($selectedUjian['nama_mk']); ?></span>
        </div>

        <div class="kisi-table-wrap">
            <table class="kisi-table">
                <thead><tr>
                    <th>No</th><th>CPMK / Tujuan Pembelajaran</th><th>Indikator</th>
                    <th>Level Kognitif</th><th>Tingkat Kesulitan</th><th>Jenis Soal</th><th>Poin</th>
                    <th class="status-col" style="text-align:center;">Status Soal</th>
                    <th class="aksi-col">Aksi</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($soalKisiKisi as $i => $soal):
                        $isPlaceholder = (strpos($soal['pertanyaan'] ?? '', '[Kisi-kisi]') === 0);
                    ?>
                    <tr data-id="<?php echo (int)$soal['id']; ?>"
                        data-cpmk="<?php echo htmlspecialchars($soal['cpmk'] ?? '', ENT_QUOTES); ?>"
                        data-indikator="<?php echo htmlspecialchars($soal['indikator'] ?? '', ENT_QUOTES); ?>"
                        data-level="<?php echo htmlspecialchars($soal['level_kognitif'] ?? 'C1', ENT_QUOTES); ?>"
                        data-kesulitan="<?php echo htmlspecialchars($soal['tingkat_kesulitan'] ?? 'sedang', ENT_QUOTES); ?>"
                        data-jenis="<?php echo htmlspecialchars($soal['jenis_soal'], ENT_QUOTES); ?>"
                        data-poin="<?php echo (int)$soal['poin']; ?>">
                        <td class="num"><?php echo $i + 1; ?></td>
                        <td><?php echo $soal['cpmk'] ? htmlspecialchars($soal['cpmk']) : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                        <td><?php echo $soal['indikator'] ? htmlspecialchars($soal['indikator']) : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                        <td>
                            <?php foreach (array_map('trim', explode(',', $soal['level_kognitif'] ?? '')) as $lv):
                                if (!$lv) continue; ?>
                            <span class="badge-kognitif"><?php echo htmlspecialchars($kognitifLabel[$lv] ?? $lv); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?php $ks = $soal['tingkat_kesulitan'] ?? 'sedang'; ?>
                            <span class="badge-kesulitan badge-<?php echo htmlspecialchars($ks); ?>"><?php echo htmlspecialchars($kesulitanLabel[$ks] ?? ucfirst($ks)); ?></span>
                        </td>
                        <td><span class="badge-jenis"><?php echo htmlspecialchars($jenisLabel[$soal['jenis_soal']] ?? $soal['jenis_soal']); ?></span></td>
                        <td class="poin-col"><?php echo (int)$soal['poin']; ?></td>
                        <td class="status-col" style="text-align:center;">
                            <?php if ($isPlaceholder): ?>
                                <span style="background:#fff3cd;color:#856404;border:1px solid #ffc107;padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:600;white-space:nowrap;">
                                    ⏳ Belum ada soal
                                </span>
                            <?php else: ?>
                                <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:600;white-space:nowrap;">
                                    ✅ Soal tersedia
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="aksi-col">
                            <button class="btn-aksi btn-aksi-edit" title="Edit baris ini"
                                onclick="openEditKisiKisi(<?php echo (int)$soal['id']; ?>)">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="btn-aksi btn-aksi-del" title="Hapus baris ini"
                                onclick="confirmDeleteKisiKisi(<?php echo (int)$soal['id']; ?>, <?php echo $i + 1; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f0f7f3;font-weight:700;">
                        <td colspan="6" style="text-align:right;padding-right:16px;">Total Poin</td>
                        <td class="poin-col"><?php echo $totalPoin; ?></td>
                        <td class="print-hide" colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="kisi-empty">
            <i class="fas fa-inbox"></i>
            <p>Belum ada soal untuk ujian ini. Klik <strong>Generate Kisi-Kisi AI</strong> untuk membuat kisi-kisi sekaligus soalnya secara otomatis.</p>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="kisi-empty">
            <i class="fas fa-table"></i>
            <p>Pilih ujian di atas, atau klik <strong>Generate Kisi-Kisi AI</strong> untuk membuat kisi-kisi baru.</p>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL 1 — Generate Kisi-Kisi AI
════════════════════════════════════════════════════════════════════════════ -->
<div class="kk-modal-overlay" id="modal1">
    <div class="kk-modal">
        <div class="kk-modal-header">
            <h3><i class="fas fa-magic" style="color:var(--primary-color);"></i> Generate Kisi-Kisi AI</h3>
            <button class="btn-close-modal" onclick="closeModal1()"><i class="fas fa-times"></i></button>
        </div>
        <div class="kk-modal-body">
            <!-- Step indicator -->
            <div class="step-indicator" id="stepIndicator1">
                <div class="step-dot active" id="step1dot">1</div>
                <span style="font-weight:600;font-size:.85rem;">Konfigurasi</span>
                <div class="step-line" id="step1line"></div>
                <div class="step-dot inactive" id="step2dot">2</div>
                <span style="color:var(--text-muted);font-size:.85rem;">Preview & Edit</span>
            </div>

            <!-- Step 1: Form -->
            <div id="formStep">
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label><i class="fas fa-book"></i> Topik / Materi</label>
                        <input type="text" id="bp_topic" placeholder="Contoh: Etika Profesi dalam Teknologi Informasi">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> Simpan ke Ujian</label>
                        <select id="bp_ujian">
                            <option value="">-- Pilih Ujian --</option>
                            <?php foreach ($ujianList as $u): ?>
                            <option value="<?php echo $u['id']; ?>" data-mk="<?php echo htmlspecialchars($u['nama_mk']); ?>" <?php echo $u['id'] == $selectedUjianId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['judul_ujian']); ?> – <?php echo htmlspecialchars($u['nama_mk']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label><i class="fas fa-layer-group"></i> Distribusi Tingkat Kesulitan <span style="font-weight:400;color:var(--text-muted);">(jumlah soal per level)</span></label>
                    <div class="dist-row">
                        <span class="dist-badge badge-mudah">Mudah</span>
                        <input type="number" id="bp_mudah" value="3" min="0" max="50" oninput="updateDistTotal()">
                        <span class="dist-badge badge-sedang">Sedang</span>
                        <input type="number" id="bp_sedang" value="5" min="0" max="50" oninput="updateDistTotal()">
                        <span class="dist-badge badge-sulit">Sulit</span>
                        <input type="number" id="bp_sulit" value="2" min="0" max="50" oninput="updateDistTotal()">
                    </div>
                    <div id="distTotal">Total: <strong id="distTotalNum">10</strong> soal</div>
                </div>

                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label><i class="fas fa-list-ul"></i> Jenis Soal</label>
                        <div class="check-group" id="jenisChecks">
                            <label><input type="checkbox" value="pg" checked> Pilihan Ganda</label>
                            <label><input type="checkbox" value="multiple"> PG Kompleks</label>
                            <label><input type="checkbox" value="tf"> Benar/Salah</label>
                            <label><input type="checkbox" value="short"> Jawaban Singkat</label>
                            <label><input type="checkbox" value="matching"> Menjodohkan</label>
                            <label><input type="checkbox" value="ordering"> Penyusunan Urutan</label>
                            <label><input type="checkbox" value="esai"> Esai</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-brain"></i> Level Kognitif Bloom</label>
                        <div class="check-group" id="kognitifChecks">
                            <label><input type="checkbox" value="C1" checked> C1 – Mengingat</label>
                            <label><input type="checkbox" value="C2" checked> C2 – Memahami</label>
                            <label><input type="checkbox" value="C3" checked> C3 – Menerapkan</label>
                            <label><input type="checkbox" value="C4"> C4 – Menganalisis</label>
                            <label><input type="checkbox" value="C5"> C5 – Mengevaluasi</label>
                            <label><input type="checkbox" value="C6"> C6 – Mencipta</label>
                        </div>
                    </div>
                </div>

                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Poin Default per Soal</label>
                        <input type="number" id="bp_poin" value="4" min="1" max="100">
                    </div>
                </div>

                <div id="bpError" style="display:none;background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:.88rem;margin-top:8px;"></div>
            </div>

            <!-- Step 2: Preview table -->
            <div id="previewStep" style="display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                    <p style="margin:0;font-size:.9rem;color:var(--text-muted);">Review dan edit kisi-kisi. Klik sel untuk mengubah.</p>
                    <button class="btn" style="font-size:.82rem;padding:6px 14px;" onclick="addBlueprintRow()">
                        <i class="fas fa-plus"></i> Tambah Baris
                    </button>
                </div>
                <div class="bp-table-wrap">
                    <table class="bp-table" id="bpPreviewTable">
                        <thead><tr>
                            <th>No</th>
                            <th>CPMK / Tujuan Pembelajaran</th>
                            <th>Indikator</th>
                            <th>Level</th>
                            <th>Kesulitan</th>
                            <th>Jenis</th>
                            <th>Poin</th>
                            <th></th>
                        </tr></thead>
                        <tbody id="bpTbody"></tbody>
                    </table>
                </div>
                <div id="bpSummaryLine" style="margin-top:10px;font-size:.85rem;color:var(--text-muted);text-align:right;"></div>
            </div>

            <!-- Loading -->
            <div id="bpLoading" style="display:none;text-align:center;padding:40px;">
                <div class="loading-bar" style="width:100%;margin-bottom:16px;"></div>
                <p style="color:var(--text-muted);">AI sedang membuat kisi-kisi… Mohon tunggu.</p>
            </div>
        </div>
        <div class="kk-modal-footer">
            <button class="btn" onclick="closeModal1()" style="background:#f3f4f6;color:var(--text-color);">Tutup</button>
            <button class="btn" id="btnBackStep" style="display:none;background:#f3f4f6;color:var(--text-color);" onclick="backToForm()">
                <i class="fas fa-arrow-left"></i> Kembali
            </button>
            <button class="btn btn-primary" id="btnGenerate" onclick="generateBlueprint()">
                <i class="fas fa-magic"></i> Generate Kisi-Kisi
            </button>
            <button class="btn" id="btnSaveBlueprint" style="display:none;background:#f59e0b;color:#fff;" onclick="saveBlueprint()">
                <i class="fas fa-save"></i> Simpan Kisi-Kisi
            </button>
            <button class="btn" id="btnUseBlueprint" style="display:none;background:var(--primary-color);color:#fff;" onclick="useBlueprint()">
                <i class="fas fa-bolt"></i> Generate Soal dari Kisi-Kisi Ini
            </button>
        </div>
        <!-- Save blueprint result msg -->
        <div id="bpSaveMsg" style="display:none;padding:10px 24px 16px;font-size:.87rem;"></div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL 2 — Generate Soal dari Kisi-Kisi
════════════════════════════════════════════════════════════════════════════ -->
<div class="kk-modal-overlay" id="modal2">
    <div class="kk-modal" style="max-width:900px;">
        <div class="kk-modal-header">
            <h3><i class="fas fa-bolt" style="color:var(--accent-color);"></i> Generate Soal dari Kisi-Kisi</h3>
            <button class="btn-close-modal" onclick="closeModal2()"><i class="fas fa-times"></i></button>
        </div>
        <div class="kk-modal-body">
            <div id="m2Ujian" style="margin-bottom:16px;padding:10px 14px;background:#f0f7f3;border-radius:8px;font-size:.88rem;"></div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                <button class="btn btn-primary" id="btnGenAll" onclick="generateAllSoal()">
                    <i class="fas fa-magic"></i> Generate Semua Soal
                </button>
                <span id="genProgress" style="font-size:.85rem;color:var(--text-muted);"></span>
            </div>
            <div id="genSoalList" class="gen-soal-list"></div>
            <div id="m2Error" style="display:none;background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:.88rem;margin-top:12px;"></div>
        </div>
        <div class="kk-modal-footer">
            <button class="btn" onclick="closeModal2()" style="background:#f3f4f6;color:var(--text-color);">Tutup</button>
            <button class="btn btn-primary" id="btnSaveSoal" style="display:none;" onclick="saveAllSoal()">
                <i class="fas fa-save"></i> Simpan Semua ke Bank Soal
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL 3 — Edit Kisi-Kisi Row
════════════════════════════════════════════════════════════════════════════ -->
<div class="kk-modal-overlay" id="modal3">
    <div class="kk-modal" style="max-width:560px;">
        <div class="kk-modal-header">
            <h3><i class="fas fa-pencil-alt" style="color:var(--primary-color);"></i> Edit Kisi-Kisi</h3>
            <button class="btn-close-modal" onclick="closeModal3()"><i class="fas fa-times"></i></button>
        </div>
        <div class="kk-modal-body">
            <input type="hidden" id="editSoalId">
            <div id="editError" style="display:none;background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:.88rem;margin-bottom:14px;"></div>
            <div class="form-row" style="margin-bottom:14px;">
                <div class="form-group">
                    <label><i class="fas fa-bullseye"></i> CPMK / Tujuan Pembelajaran</label>
                    <input type="text" id="editCpmk" placeholder="Capaian pembelajaran mata kuliah…">
                </div>
            </div>
            <div class="form-row" style="margin-bottom:14px;">
                <div class="form-group">
                    <label><i class="fas fa-list-check"></i> Indikator</label>
                    <input type="text" id="editIndikator" placeholder="Indikator pencapaian…">
                </div>
            </div>
            <div class="form-row form-row-2" style="margin-bottom:14px;">
                <div class="form-group">
                    <label><i class="fas fa-brain"></i> Level Kognitif</label>
                    <select id="editLevel">
                        <option value="C1">C1 – Mengingat</option>
                        <option value="C2">C2 – Memahami</option>
                        <option value="C3">C3 – Menerapkan</option>
                        <option value="C4">C4 – Menganalisis</option>
                        <option value="C5">C5 – Mengevaluasi</option>
                        <option value="C6">C6 – Mencipta</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-signal"></i> Tingkat Kesulitan</label>
                    <select id="editKesulitan">
                        <option value="mudah">Mudah</option>
                        <option value="sedang">Sedang</option>
                        <option value="sulit">Sulit</option>
                    </select>
                </div>
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label><i class="fas fa-list-ul"></i> Jenis Soal</label>
                    <select id="editJenis">
                        <option value="pg">Pilihan Ganda</option>
                        <option value="multiple">PG Kompleks</option>
                        <option value="tf">Benar/Salah</option>
                        <option value="short">Jawaban Singkat</option>
                        <option value="matching">Menjodohkan</option>
                        <option value="ordering">Penyusunan Urutan</option>
                        <option value="esai">Esai</option>
                        <option value="studi_kasus">Studi Kasus</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-star"></i> Poin</label>
                    <input type="number" id="editPoin" min="1" max="100" value="4">
                </div>
            </div>
        </div>
        <div class="kk-modal-footer">
            <button class="btn" onclick="closeModal3()" style="background:#f3f4f6;color:var(--text-color);">Batal</button>
            <button class="btn" id="btnSaveEdit" style="background:var(--primary-color);color:#fff;" onclick="saveEditKisiKisi()">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════════════════ -->
<script>
var blueprint   = [];   // array of kisi-kisi row objects
var generatedSoal = []; // parallel array of generated soal
var currentUjianId   = <?php echo $selectedUjianId ?: 0; ?>;
var currentUjianName = <?php echo json_encode($selectedUjian ? $selectedUjian['judul_ujian'] . ' – ' . $selectedUjian['nama_mk'] : ''); ?>;

// Existing soal as blueprint (for "Generate Soal dari Kisi-Kisi" on existing table)
var existingKisiKisi = <?php echo json_encode(array_map(function($s) {
    return [
        'soal_id'          => (int)$s['id'],
        'is_placeholder'   => (strpos($s['pertanyaan'] ?? '', '[Kisi-kisi]') === 0),
        'cpmk'             => $s['cpmk'] ?? '',
        'indikator'        => $s['indikator'] ?? '',
        'level_kognitif'   => $s['level_kognitif'] ?? 'C1',
        'tingkat_kesulitan'=> $s['tingkat_kesulitan'] ?? 'sedang',
        'jenis_soal'       => $s['jenis_soal'],
        'poin'             => (int)$s['poin'],
    ];
}, $soalKisiKisi), JSON_HEX_TAG | JSON_HEX_AMP); ?>;
var isUpdateMode = false;

// ── Dist total ──────────────────────────────────────────────────────────────
function updateDistTotal() {
    var m = parseInt(document.getElementById('bp_mudah').value) || 0;
    var s = parseInt(document.getElementById('bp_sedang').value) || 0;
    var h = parseInt(document.getElementById('bp_sulit').value) || 0;
    var t = m + s + h;
    document.getElementById('distTotalNum').textContent = t;
    var el = document.getElementById('distTotal');
    el.classList.toggle('over', t === 0);
}

// ── Modal 1 ─────────────────────────────────────────────────────────────────
function openModal1() {
    document.getElementById('modal1').classList.add('active');
    backToForm();
}
function closeModal1() {
    document.getElementById('modal1').classList.remove('active');
}

function getCheckedValues(groupId) {
    var cbs = document.querySelectorAll('#' + groupId + ' input[type=checkbox]:checked');
    return Array.from(cbs).map(function(c){ return c.value; });
}

async function generateBlueprint() {
    var topic = document.getElementById('bp_topic').value.trim();
    var ujianSel = document.getElementById('bp_ujian');
    var ujianId = ujianSel.value;
    var mudah  = parseInt(document.getElementById('bp_mudah').value) || 0;
    var sedang = parseInt(document.getElementById('bp_sedang').value) || 0;
    var sulit  = parseInt(document.getElementById('bp_sulit').value) || 0;
    var total  = mudah + sedang + sulit;
    var jenis  = getCheckedValues('jenisChecks');
    var kognitif = getCheckedValues('kognitifChecks');
    var poin   = parseInt(document.getElementById('bp_poin').value) || 4;

    var errEl = document.getElementById('bpError');
    errEl.style.display = 'none';

    if (!topic) { showBpError('Masukkan topik/materi terlebih dahulu.'); return; }
    if (!ujianId) { showBpError('Pilih ujian terlebih dahulu.'); return; }
    if (total === 0) { showBpError('Jumlah soal tidak boleh nol.'); return; }
    if (jenis.length === 0) { showBpError('Pilih minimal satu jenis soal.'); return; }
    if (kognitif.length === 0) { showBpError('Pilih minimal satu level kognitif.'); return; }

    currentUjianId = ujianId;
    currentUjianName = ujianSel.options[ujianSel.selectedIndex].text;

    // Show loading
    document.getElementById('formStep').style.display = 'none';
    document.getElementById('previewStep').style.display = 'none';
    document.getElementById('bpLoading').style.display = 'block';
    document.getElementById('btnGenerate').disabled = true;

    var matKuliah = ujianSel.options[ujianSel.selectedIndex].dataset.mk || topic;

    var fd = new FormData();
    fd.append('action',       'generate_blueprint');
    fd.append('topic',        topic);
    fd.append('mata_kuliah',  matKuliah);
    fd.append('total',        total);
    fd.append('mudah',        mudah);
    fd.append('sedang',       sedang);
    fd.append('sulit',        sulit);
    fd.append('jenis',        jenis.join(','));
    fd.append('kognitif',     kognitif.join(','));
    fd.append('poin',         poin);

    try {
        var res = await fetch('ai_kisi_kisi_api.php', { method: 'POST', body: fd });
        var data = await res.json();

        document.getElementById('bpLoading').style.display = 'none';
        document.getElementById('btnGenerate').disabled = false;

        if (data.error) {
            document.getElementById('formStep').style.display = 'block';
            showBpError(data.error);
            return;
        }

        blueprint = data.kisi_kisi || [];
        if (blueprint.length === 0) {
            document.getElementById('formStep').style.display = 'block';
            showBpError('AI tidak menghasilkan kisi-kisi. Coba lagi.');
            return;
        }

        renderBlueprintTable();
        showPreviewStep();

    } catch(e) {
        document.getElementById('bpLoading').style.display = 'none';
        document.getElementById('formStep').style.display = 'block';
        document.getElementById('btnGenerate').disabled = false;
        showBpError('Gagal terhubung ke server: ' + e.message);
    }
}

function showBpError(msg) {
    var el = document.getElementById('bpError');
    el.textContent = '⚠ ' + msg;
    el.style.display = 'block';
}

function showPreviewStep() {
    document.getElementById('formStep').style.display = 'none';
    document.getElementById('previewStep').style.display = 'block';
    document.getElementById('btnGenerate').style.display = 'none';
    document.getElementById('btnBackStep').style.display = 'inline-flex';
    document.getElementById('btnSaveBlueprint').style.display = 'inline-flex';
    document.getElementById('btnUseBlueprint').style.display = 'inline-flex';
    document.getElementById('bpSaveMsg').style.display = 'none';
    // Step indicator
    document.getElementById('step1dot').className = 'step-dot done';
    document.getElementById('step1dot').innerHTML = '<i class="fas fa-check" style="font-size:.7rem;"></i>';
    document.getElementById('step1line').className = 'step-line done';
    document.getElementById('step2dot').className = 'step-dot active';
}

function backToForm() {
    document.getElementById('formStep').style.display = 'block';
    document.getElementById('previewStep').style.display = 'none';
    document.getElementById('bpLoading').style.display = 'none';
    document.getElementById('btnGenerate').style.display = 'inline-flex';
    document.getElementById('btnGenerate').disabled = false;
    document.getElementById('btnBackStep').style.display = 'none';
    document.getElementById('btnSaveBlueprint').style.display = 'none';
    document.getElementById('btnUseBlueprint').style.display = 'none';
    document.getElementById('bpError').style.display = 'none';
    document.getElementById('bpSaveMsg').style.display = 'none';
    document.getElementById('step1dot').className = 'step-dot active';
    document.getElementById('step1dot').textContent = '1';
    document.getElementById('step1line').className = 'step-line';
    document.getElementById('step2dot').className = 'step-dot inactive';
}

async function saveBlueprint() {
    var ujianId = currentUjianId;
    if (!ujianId || blueprint.length === 0) {
        alert('Pilih ujian dan pastikan kisi-kisi sudah di-generate.');
        return;
    }

    var btn = document.getElementById('btnSaveBlueprint');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…';

    var fd = new FormData();
    fd.append('action',    'save_blueprint');
    fd.append('id_ujian',  ujianId);
    fd.append('blueprint', JSON.stringify(blueprint));

    try {
        var res  = await fetch('ai_kisi_kisi_api.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.error) throw new Error(data.error);

        var msgEl = document.getElementById('bpSaveMsg');
        msgEl.style.display = 'block';
        msgEl.style.cssText = 'display:block;padding:10px 24px 16px;font-size:.87rem;background:#f0fdf4;color:#16a34a;border-top:1px solid #bbf7d0;';
        msgEl.innerHTML = '<i class="fas fa-check-circle"></i> <strong>' + data.saved + ' baris kisi-kisi berhasil disimpan!</strong> '
            + 'Halaman akan dimuat ulang untuk menampilkan kisi-kisi…';

        // Disable both action buttons after save
        btn.innerHTML = '<i class="fas fa-check"></i> Tersimpan';
        btn.style.background = '#059669';
        document.getElementById('btnUseBlueprint').disabled = true;

        setTimeout(function() {
            window.location.href = 'kisi_kisi.php?ujian_id=' + ujianId;
        }, 1800);

    } catch (e) {
        var msgEl = document.getElementById('bpSaveMsg');
        msgEl.style.cssText = 'display:block;padding:10px 24px 16px;font-size:.87rem;background:#fff0f0;color:#dc2626;border-top:1px solid #fca5a5;';
        msgEl.innerHTML = '<i class="fas fa-circle-exclamation"></i> Gagal menyimpan: ' + escHtml(e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Simpan Kisi-Kisi';
    }
}

var JENIS_OPTS = {
    'pg':'Pilihan Ganda','multiple':'PG Kompleks','tf':'Benar/Salah',
    'short':'Jawaban Singkat','matching':'Menjodohkan','ordering':'Penyusunan Urutan',
    'esai':'Esai','studi_kasus':'Studi Kasus'
};
var KOGNITIF_OPTS = {
    'C1':'C1 – Mengingat','C2':'C2 – Memahami','C3':'C3 – Menerapkan',
    'C4':'C4 – Menganalisis','C5':'C5 – Mengevaluasi','C6':'C6 – Mencipta'
};

function buildSelectHTML(name, opts, selected) {
    var html = '<select name="' + name + '">';
    for (var k in opts) {
        html += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + opts[k] + '</option>';
    }
    return html + '</select>';
}

function renderBlueprintTable() {
    var tbody = document.getElementById('bpTbody');
    tbody.innerHTML = '';
    blueprint.forEach(function(row, i) {
        tbody.appendChild(buildBpRow(row, i));
    });
    updateBpSummary();
}

function buildBpRow(row, i) {
    var tr = document.createElement('tr');
    tr.dataset.idx = i;
    tr.innerHTML =
        '<td style="text-align:center;font-weight:700;color:var(--primary-color);">' + (i + 1) + '</td>' +
        '<td><input type="text" value="' + escHtml(row.cpmk || '') + '" oninput="bp_update(' + i + ',\'cpmk\',this.value)" style="width:100%;min-width:120px;"></td>' +
        '<td><input type="text" value="' + escHtml(row.indikator || '') + '" oninput="bp_update(' + i + ',\'indikator\',this.value)" style="width:100%;min-width:100px;"></td>' +
        '<td>' + buildSelectHTML('level', KOGNITIF_OPTS, row.level_kognitif || 'C1').replace('>', ' onchange="bp_update(' + i + ',\'level_kognitif\',this.value)">') + '</td>' +
        '<td><select onchange="bp_update(' + i + ',\'tingkat_kesulitan\',this.value)">' +
            '<option value="mudah"' + (row.tingkat_kesulitan==='mudah'?' selected':'') + '>Mudah</option>' +
            '<option value="sedang"' + (row.tingkat_kesulitan==='sedang'?' selected':'') + '>Sedang</option>' +
            '<option value="sulit"' + (row.tingkat_kesulitan==='sulit'?' selected':'') + '>Sulit</option>' +
        '</select></td>' +
        '<td>' + buildSelectHTML('jenis', JENIS_OPTS, row.jenis_soal || 'pg').replace('>', ' onchange="bp_update(' + i + ',\'jenis_soal\',this.value)">') + '</td>' +
        '<td><input type="number" value="' + (parseInt(row.poin) || 4) + '" min="1" max="100" oninput="bp_update(' + i + ',\'poin\',parseInt(this.value)||1)" style="width:60px;"></td>' +
        '<td><button class="btn-del-row" onclick="deleteBpRow(' + i + ')" title="Hapus baris"><i class="fas fa-trash"></i></button></td>';
    return tr;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function bp_update(idx, field, val) {
    if (blueprint[idx]) blueprint[idx][field] = val;
    updateBpSummary();
}

function deleteBpRow(idx) {
    blueprint.splice(idx, 1);
    renderBlueprintTable();
}

function addBlueprintRow() {
    blueprint.push({ cpmk:'', indikator:'', level_kognitif:'C1', tingkat_kesulitan:'sedang', jenis_soal:'pg', poin:4 });
    renderBlueprintTable();
}

function updateBpSummary() {
    var m=0, s=0, h=0, total=0;
    blueprint.forEach(function(r){
        total += parseInt(r.poin) || 0;
        if (r.tingkat_kesulitan==='mudah') m++;
        else if (r.tingkat_kesulitan==='sedang') s++;
        else if (r.tingkat_kesulitan==='sulit') h++;
    });
    document.getElementById('bpSummaryLine').textContent =
        blueprint.length + ' soal | Total poin: ' + total + ' | Mudah: ' + m + ' Sedang: ' + s + ' Sulit: ' + h;
}

function useBlueprint() {
    closeModal1();
    openModal2(blueprint, currentUjianId, currentUjianName, false);
}

// ── Modal 2 ─────────────────────────────────────────────────────────────────
function openModal2(rows, ujianId, ujianName, updateMode) {
    blueprint = rows;
    currentUjianId = ujianId;
    currentUjianName = ujianName;
    isUpdateMode = !!updateMode;
    generatedSoal = new Array(rows.length).fill(null);

    document.getElementById('m2Ujian').innerHTML =
        '<i class="fas fa-file-alt" style="color:var(--primary-color);margin-right:6px;"></i>' +
        '<strong>Ujian:</strong> ' + escHtml(ujianName || '—') +
        ' &nbsp;|&nbsp; <strong>' + rows.length + ' soal</strong> akan di-generate';
    document.getElementById('m2Error').style.display = 'none';
    document.getElementById('btnSaveSoal').style.display = 'none';
    document.getElementById('genProgress').textContent = '';
    document.getElementById('btnGenAll').disabled = false;

    renderGenSoalList();
    document.getElementById('modal2').classList.add('active');
}

function openModal2FromExisting() {
    // Cek apakah ada baris placeholder (belum ada soal) — gunakan update mode
    var hasPlaceholder = existingKisiKisi.some(function(r){ return r.is_placeholder; });
    openModal2(existingKisiKisi, currentUjianId, currentUjianName, hasPlaceholder);
}

function closeModal2() {
    document.getElementById('modal2').classList.remove('active');
}

function renderGenSoalList() {
    var container = document.getElementById('genSoalList');
    container.innerHTML = '';
    blueprint.forEach(function(row, i) {
        container.appendChild(buildGenItem(row, i));
    });
    checkAllDone();
}

function buildGenItem(row, i) {
    var div = document.createElement('div');
    div.className = 'gen-soal-item';
    div.id = 'genItem' + i;
    div.innerHTML =
        '<div class="gen-soal-item-head">' +
            '<span class="no">' + (i+1) + '</span>' +
            '<span class="meta">' +
                '<span class="badge-jenis">' + escHtml(JENIS_OPTS[row.jenis_soal] || row.jenis_soal) + '</span> ' +
                '<span class="badge-kesulitan badge-' + (row.tingkat_kesulitan||'sedang') + '">' + ucfirst(row.tingkat_kesulitan||'sedang') + '</span> ' +
                '<span class="badge-kognitif">' + escHtml(row.level_kognitif||'C1') + '</span>' +
                (row.cpmk ? ' &nbsp;<em style="color:var(--text-muted);">' + escHtml(row.cpmk.substring(0,60)) + (row.cpmk.length>60?'…':'') + '</em>' : '') +
            '</span>' +
            '<span class="status status-pending" id="status' + i + '">⏳ Belum</span>' +
            '<button class="btn" style="font-size:.78rem;padding:5px 12px;" id="btnGenRow' + i + '" onclick="generateSoalRow(' + i + ')">' +
                '<i class="fas fa-magic"></i> Generate' +
            '</button>' +
        '</div>' +
        '<div class="gen-soal-item-body" id="body' + i + '">' +
            '<div id="loadingRow' + i + '" style="display:none;"><div class="loading-bar"></div></div>' +
            '<div id="preview' + i + '" class="gen-soal-preview"></div>' +
        '</div>';
    return div;
}

function ucfirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

function setRowStatus(i, status, label) {
    var el = document.getElementById('status' + i);
    if (!el) return;
    el.className = 'status status-' + status;
    el.textContent = label;
}

async function generateSoalRow(i) {
    var row = blueprint[i];
    if (!row) return;

    setRowStatus(i, 'loading', '🔄 Generating…');
    document.getElementById('btnGenRow' + i).disabled = true;
    var loadEl = document.getElementById('loadingRow' + i);
    var bodyEl = document.getElementById('body' + i);
    var previewEl = document.getElementById('preview' + i);
    if (loadEl) loadEl.style.display = 'block';
    if (bodyEl) bodyEl.classList.add('visible');
    if (previewEl) previewEl.innerHTML = '';

    // Combine cpmk + indikator for richer AI context
    var cpmkCtx = [row.cpmk, row.indikator].filter(Boolean).join(' — ');

    var fd = new FormData();
    fd.append('topic',        document.getElementById('bp_topic')?.value.trim() || currentUjianName);
    fd.append('jenis',        row.jenis_soal || 'pg');
    fd.append('count',        1);
    fd.append('kesulitan',    row.tingkat_kesulitan || 'sedang');
    fd.append('kognitif',     row.level_kognitif || 'C1');
    fd.append('cpmk',         cpmkCtx);
    fd.append('poin',         row.poin || 4);

    try {
        var res  = await fetch('ai_generate_api.php', { method:'POST', body: fd });
        var data = await res.json();

        if (loadEl) loadEl.style.display = 'none';

        if (!Array.isArray(data) || data.length === 0) {
            throw new Error(data.error || 'Respons kosong dari AI');
        }
        if (data[0].error) throw new Error(data[0].error);

        var soal = data[0];
        // Inject blueprint metadata into the generated soal
        soal.cpmk             = row.cpmk || '';
        soal.indikator        = row.indikator || '';
        soal.level_kognitif   = row.level_kognitif || soal.level_kognitif;
        soal.tingkat_kesulitan= row.tingkat_kesulitan || soal.tingkat_kesulitan;
        soal.poin             = row.poin || soal.poin;
        soal.jenis_soal       = row.jenis_soal || soal.jenis_soal;
        // Bawa soal_id dari blueprint (untuk update mode)
        if (row.soal_id) soal.soal_id = row.soal_id;

        generatedSoal[i] = soal;
        setRowStatus(i, 'done', '✅ Selesai');
        if (previewEl) previewEl.innerHTML = renderSoalPreview(soal);

    } catch(e) {
        if (loadEl) loadEl.style.display = 'none';
        setRowStatus(i, 'error', '❌ Error');
        document.getElementById('btnGenRow' + i).disabled = false;
        if (previewEl) previewEl.innerHTML = '<span style="color:#c0392b;">' + escHtml(e.message) + '</span>';
    }

    checkAllDone();
}

async function generateAllSoal() {
    document.getElementById('btnGenAll').disabled = true;
    for (var i = 0; i < blueprint.length; i++) {
        document.getElementById('genProgress').textContent = 'Memproses soal ' + (i+1) + ' dari ' + blueprint.length + '…';
        await generateSoalRow(i);
    }
    document.getElementById('genProgress').textContent = 'Semua soal selesai di-generate!';
}

function checkAllDone() {
    var allDone = generatedSoal.length > 0 && generatedSoal.every(function(s){ return s !== null; });
    document.getElementById('btnSaveSoal').style.display = allDone ? 'inline-flex' : 'none';
}

function renderSoalPreview(soal) {
    var html = '<strong>Pertanyaan:</strong> ' + escHtml(soal.pertanyaan || '');
    var jenis = soal.jenis_soal;
    if ((jenis === 'pg' || jenis === 'multiple') && soal.opsi && soal.opsi.length) {
        html += '<br><strong>Opsi:</strong><ul style="margin:4px 0 0 16px;padding:0;">';
        soal.opsi.forEach(function(o, idx) {
            var letter = String.fromCharCode(65 + idx);
            html += '<li' + (o.benar ? ' style="color:#065f46;font-weight:600;"' : '') + '>';
            html += letter + '. ' + escHtml(o.teks || o.teks_opsi || '') + (o.benar ? ' ✓' : '') + '</li>';
        });
        html += '</ul>';
    } else if (jenis === 'tf') {
        var dt = soal.data_tambahan || {};
        if (typeof dt === 'string') { try { dt = JSON.parse(dt); } catch(e){} }
        var benar = dt.jawaban_benar;
        if (soal.opsi && soal.opsi.length) {
            soal.opsi.forEach(function(o){ if(o.benar) benar = (o.teks === 'BENAR'); });
        }
        html += '<br><strong>Jawaban:</strong> ' + (benar ? '✅ BENAR' : '❌ SALAH');
    } else if (jenis === 'short') {
        if (soal.opsi && soal.opsi.length) {
            html += '<br><strong>Keywords:</strong> ' + soal.opsi.map(function(o){ return escHtml(o.teks || o.teks_opsi || ''); }).join(', ');
        }
    } else if (jenis === 'matching' && soal.pairs) {
        html += '<br><strong>Pasangan:</strong><ul style="margin:4px 0 0 16px;padding:0;">';
        for (var k in soal.pairs) { html += '<li>' + escHtml(k) + ' → ' + escHtml(soal.pairs[k]) + '</li>'; }
        html += '</ul>';
    } else if (jenis === 'ordering' && soal.correct_order && soal.correct_order.length) {
        html += '<br><strong>Urutan:</strong><ol style="margin:4px 0 0 16px;padding:0;">';
        soal.correct_order.forEach(function(it){ html += '<li>' + escHtml(it) + '</li>'; });
        html += '</ol>';
    }
    if (soal.pembahasan) {
        html += '<br><strong>Pembahasan:</strong> <span style="color:var(--text-muted);">' + escHtml(soal.pembahasan.substring(0,120)) + (soal.pembahasan.length>120?'…':'') + '</span>';
    }
    return html;
}

function normalizeSoal(soal, withSoalId) {
    var s = {
        jenis_soal:        soal.jenis_soal || 'pg',
        pertanyaan:        soal.pertanyaan || '',
        pembahasan:        soal.pembahasan || '',
        poin:              soal.poin || 4,
        cpmk:              soal.cpmk || '',
        indikator:         soal.indikator || '',
        level_kognitif:    soal.level_kognitif || 'C1',
        tingkat_kesulitan: soal.tingkat_kesulitan || 'sedang',
        opsi:              [],
        pairs:             {},
        correct_order:     [],
        jawaban_tf:        null,
        keywords:          '',
    };
    if (withSoalId && soal.soal_id) s.soal_id = soal.soal_id;

    var jenis = s.jenis_soal;
    if (jenis === 'pg' || jenis === 'multiple') {
        s.opsi = (soal.opsi || []).map(function(o){
            return { teks: o.teks || o.teks_opsi || '', benar: !!(o.benar) };
        });
    } else if (jenis === 'tf') {
        var dt = soal.data_tambahan || {};
        if (typeof dt === 'string') { try { dt = JSON.parse(dt); } catch(e2){} }
        var benarVal = dt.jawaban_benar;
        if (soal.opsi && soal.opsi.length) {
            soal.opsi.forEach(function(o){ if (o.benar) benarVal = (o.teks === 'BENAR'); });
        }
        s.jawaban_tf = !!benarVal;
    } else if (jenis === 'short') {
        var kws = (soal.opsi || []).map(function(o){ return o.teks || o.teks_opsi || ''; }).filter(Boolean);
        s.keywords = kws.join(',');
    } else if (jenis === 'matching') {
        s.pairs = soal.pairs || {};
    } else if (jenis === 'ordering') {
        s.correct_order = soal.correct_order || [];
    }
    return s;
}

async function saveAllSoal() {
    var toSave = generatedSoal.filter(Boolean);
    if (!toSave.length || !currentUjianId) {
        document.getElementById('m2Error').textContent = 'Tidak ada soal atau ujian belum dipilih.';
        document.getElementById('m2Error').style.display = 'block';
        return;
    }

    var btn = document.getElementById('btnSaveSoal');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…';

    // Update mode: soal sudah ada di DB (dari simpan kisi-kisi), update isinya
    // Insert mode: soal baru, insert ke DB
    var normalized = toSave.map(function(soal){ return normalizeSoal(soal, isUpdateMode); });

    // Pilih action berdasarkan mode
    // Update mode hanya berlaku jika SEMUA soal punya soal_id; fallback ke insert jika tidak
    var useUpdate = isUpdateMode && normalized.every(function(s){ return s.soal_id; });
    var action    = useUpdate ? 'update_soal_batch' : 'save_soal_batch';

    var fd = new FormData();
    fd.append('action',    action);
    fd.append('id_ujian',  currentUjianId);
    fd.append('soal_list', JSON.stringify(normalized));

    try {
        var res  = await fetch('ai_kisi_kisi_api.php', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.error) throw new Error(data.error);

        var count   = data.saved ?? data.updated ?? 0;
        var errList = data.errors || [];

        btn.innerHTML = '<i class="fas fa-check"></i> Tersimpan!';
        btn.style.background = '#059669';

        if (errList.length) {
            document.getElementById('m2Error').style.cssText = 'display:block;background:#fff3cd;color:#856404;padding:10px 14px;border-radius:8px;font-size:.88rem;margin-top:12px;';
            document.getElementById('m2Error').textContent = errList.join('; ');
        } else {
            document.getElementById('m2Error').style.display = 'none';
        }

        setTimeout(function(){
            window.location.href = 'kisi_kisi.php?ujian_id=' + currentUjianId;
        }, 1500);

    } catch(e) {
        document.getElementById('m2Error').style.cssText = 'display:block;background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:.88rem;margin-top:12px;';
        document.getElementById('m2Error').textContent = 'Gagal menyimpan: ' + e.message;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Simpan Semua ke Bank Soal';
    }
}

// Init
updateDistTotal();

// ── Edit / Delete Kisi-Kisi ──────────────────────────────────────────────────
function openEditKisiKisi(soalId) {
    var row = document.querySelector('tr[data-id="' + soalId + '"]');
    if (!row) return;
    document.getElementById('editSoalId').value      = soalId;
    document.getElementById('editCpmk').value        = row.dataset.cpmk      || '';
    document.getElementById('editIndikator').value   = row.dataset.indikator  || '';
    document.getElementById('editLevel').value       = row.dataset.level      || 'C1';
    document.getElementById('editKesulitan').value   = row.dataset.kesulitan  || 'sedang';
    document.getElementById('editJenis').value       = row.dataset.jenis      || 'pg';
    document.getElementById('editPoin').value        = row.dataset.poin       || '4';
    document.getElementById('editError').style.display = 'none';
    var btn = document.getElementById('btnSaveEdit');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
    document.getElementById('modal3').classList.add('active');
}

function closeModal3() {
    document.getElementById('modal3').classList.remove('active');
}

async function saveEditKisiKisi() {
    var soalId    = document.getElementById('editSoalId').value;
    var cpmk      = document.getElementById('editCpmk').value.trim();
    var indikator = document.getElementById('editIndikator').value.trim();
    var level     = document.getElementById('editLevel').value;
    var kesulitan = document.getElementById('editKesulitan').value;
    var jenis     = document.getElementById('editJenis').value;
    var poin      = parseInt(document.getElementById('editPoin').value) || 4;

    var errEl = document.getElementById('editError');
    errEl.style.display = 'none';
    if (!cpmk && !indikator) {
        errEl.textContent = '⚠ Isi minimal CPMK atau Indikator.';
        errEl.style.display = 'block';
        return;
    }

    var btn = document.getElementById('btnSaveEdit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…';

    var fd = new FormData();
    fd.append('action',            'edit_kisi_kisi');
    fd.append('soal_id',           soalId);
    fd.append('cpmk',              cpmk);
    fd.append('indikator',         indikator);
    fd.append('level_kognitif',    level);
    fd.append('tingkat_kesulitan', kesulitan);
    fd.append('jenis_soal',        jenis);
    fd.append('poin',              poin);

    try {
        var res  = await fetch('ai_kisi_kisi_api.php', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.error) throw new Error(data.error);
        window.location.reload();
    } catch(e) {
        errEl.textContent = '⚠ Gagal menyimpan: ' + e.message;
        errEl.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
    }
}

async function confirmDeleteKisiKisi(soalId, rowNum) {
    if (!confirm('Hapus kisi-kisi baris ke-' + rowNum + '?\nData tidak bisa dikembalikan.')) return;
    var fd = new FormData();
    fd.append('action',  'delete_kisi_kisi');
    fd.append('soal_id', soalId);
    try {
        var res  = await fetch('ai_kisi_kisi_api.php', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.error) throw new Error(data.error);
        window.location.reload();
    } catch(e) {
        alert('Gagal menghapus: ' + e.message);
    }
}
</script>
</body>
</html>
