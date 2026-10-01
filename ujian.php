<?php
require_once 'config.php';
require_once 'database.php';

if (!isset($_SESSION['sesi_id']) || !isset($_SESSION['exam_started'])) {
    header('Location: index.php');
    exit;
}

$sesiId = $_SESSION['sesi_id'];
$ujianId = $_SESSION['ujian_id'];
$startTime = $_SESSION['exam_start_time'];
$duration = $_SESSION['exam_duration'];
$elapsed = time() - $startTime;
$remaining = max(0, $duration - $elapsed);

if ($remaining <= 0) {
    header('Location: submit.php?auto=1');
    exit;
}

$soalList = getSoalByUjian($pdo, $ujianId);

$stmt = $pdo->prepare("SELECT shuffled_options FROM sesi_ujian WHERE id = ?");
$stmt->execute([$sesiId]);
$dbSesi = $stmt->fetch();
$shuffledOptions = [];
if ($dbSesi && !empty($dbSesi['shuffled_options'])) {
    $shuffledOptions = json_decode($dbSesi['shuffled_options'], true) ?? [];
    $_SESSION['shuffled_options'] = $shuffledOptions;
} else {
    $shuffledOptions = $_SESSION['shuffled_options'] ?? [];
}

$answers = $_SESSION['answers'] ?? [];

$currentSoal = isset($_GET['no']) ? (int)$_GET['no'] : 1;
$currentSoal = max(1, min($currentSoal, count($soalList)));
$currentIndex = $currentSoal - 1;
$current = $soalList[$currentIndex];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_answer'])) {
    $soalId  = (int)$_POST['soal_id'];
    $jawaban = $_POST['jawaban'] ?? '';
    $noSoal  = (int)($_POST['no_soal'] ?? 1);

    if (is_array($jawaban)) {
        $_SESSION['answers'][$soalId] = $jawaban;
    } else {
        $_SESSION['answers'][$soalId] = $jawaban;
    }
    $answers = $_SESSION['answers'];

    // Persist answers to DB so user can resume after logout/disconnect
    $stmt = $pdo->prepare("UPDATE sesi_ujian SET jawaban_draft = ?, soal_terakhir = ? WHERE id = ?");
    $stmt->execute([json_encode($answers), $noSoal, $sesiId]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

function getShuffledOpsi($soal, $shuffledOptions) {
    if (!isset($shuffledOptions[$soal['id']]['order']) || !isset($soal['opsi'])) {
        return $soal['opsi'] ?? [];
    }
    
    $order = $shuffledOptions[$soal['id']]['order'];
    $opsiById = [];
    foreach ($soal['opsi'] as $opsi) {
        $opsiById[$opsi['id']] = $opsi;
    }
    
    $shuffled = [];
    foreach ($order as $id) {
        if (isset($opsiById[$id])) {
            $shuffled[] = $opsiById[$id];
        }
    }
    
    return $shuffled;
}

function getShuffledMatchingValues($soal, $shuffledOptions) {
    if (isset($shuffledOptions[$soal['id']]['shuffled_values'])) {
        return $shuffledOptions[$soal['id']]['shuffled_values'];
    }
    if (isset($soal['data_tambahan']['pairs'])) {
        return array_values($soal['data_tambahan']['pairs']);
    }
    return [];
}

function getShuffledOrderingItems($soal, $shuffledOptions) {
    if (isset($shuffledOptions[$soal['id']]['display_order'])) {
        return $shuffledOptions[$soal['id']]['display_order'];
    }
    if (isset($soal['data_tambahan']['correct_order'])) {
        return $soal['data_tambahan']['correct_order'];
    }
    return [];
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
    <title><?php echo htmlspecialchars($_SESSION['judul_ujian']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .checkbox-option { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .checkbox-option input[type="checkbox"] { width: 20px; height: 20px; }
        .tf-options { display: flex; gap: 20px; margin-top: 15px; }
        .tf-option { flex: 1; }
        .tf-label { display: flex; align-items: center; justify-content: center; padding: 20px; border: 2px solid var(--border-color); border-radius: 10px; cursor: pointer; transition: all 0.2s; font-weight: 600; }
        .tf-label:hover { border-color: var(--primary); }
        .tf-label input { display: none; }
        .tf-label:has(input:checked) { background: var(--primary); color: white; border-color: var(--primary); }
        .matching-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .matching-left, .matching-right { display: flex; flex-direction: column; gap: 10px; }
        .matching-item { padding: 15px; background: var(--bg-light); border-radius: 8px; border: 2px solid var(--border-color); }
        .matching-item select { width: 100%; margin-top: 10px; padding: 8px; border-radius: 5px; border: 1px solid var(--border-color); }
        .ordering-list { list-style: none; }
        .ordering-item { padding: 15px 20px; background: var(--bg-light); border: 2px solid var(--border-color); border-radius: 8px; margin-bottom: 10px; cursor: grab; display: flex; align-items: center; gap: 15px; }
        .ordering-item:active { cursor: grabbing; }
        .ordering-handle { color: var(--text-muted); font-size: 1.2rem; }
        .ordering-item.dragging { opacity: 0.5; border-color: var(--primary); }
        .short-input { width: 100%; padding: 15px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 1rem; }
        .short-input:focus { outline: none; border-color: var(--primary); }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <h1><?php echo htmlspecialchars($_SESSION['judul_ujian']); ?></h1>
            <p class="subtitle"><?php echo htmlspecialchars($_SESSION['nama_peserta'] ?? ''); ?> - <?php echo htmlspecialchars($_SESSION['nim'] ?? ''); ?> | <?php echo htmlspecialchars($_SESSION['prodi'] ?? ''); ?> - <?php echo htmlspecialchars($_SESSION['kelas'] ?? ''); ?></p>
        </div>
    </header>
    
    <div class="container">
        <div class="exam-layout">
            <aside class="sidebar">
                <div class="timer-box" id="timer-box">
                    <h3>Sisa Waktu</h3>
                    <div class="time" id="timer">--:--:--</div>
                </div>
                
                <div class="nav-title">Navigasi Soal</div>
                <div class="nav-grid">
                    <?php foreach ($soalList as $idx => $s): ?>
                        <?php 
                        $no = $idx + 1;
                        $isActive = $no === $currentSoal;
                        $isAnswered = isset($answers[$s['id']]) && !empty($answers[$s['id']]);
                        if (is_array($answers[$s['id']] ?? null)) {
                            $isAnswered = count($answers[$s['id']]) > 0;
                        }
                        $classes = 'nav-btn';
                        if ($isActive) $classes .= ' active';
                        if ($isAnswered) $classes .= ' answered';
                        ?>
                        <button class="<?php echo $classes; ?>" 
                                onclick="navigateTo(<?php echo $no; ?>)" 
                                data-no="<?php echo $no; ?>"
                                data-soal-id="<?php echo $s['id']; ?>">
                            <?php echo $no; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-dot current"></div>
                        <span>Aktif</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot answered"></div>
                        <span>Terjawab</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot unanswered"></div>
                        <span>Belum</span>
                    </div>
                </div>
                
                <button class="btn btn-accent btn-block" onclick="confirmSubmit()">
                    Selesai Ujian
                </button>
            </aside>
            
            <main class="main-content">
                <div class="question-header">
                    <span class="question-number">Soal <?php echo $currentSoal; ?> dari <?php echo count($soalList); ?></span>
                    <span class="question-type"><?php echo $jenisSoalLabels[$current['jenis_soal']] ?? $current['jenis_soal']; ?></span>
                </div>
                
                <div class="question-text">
                    <?php echo nl2br(htmlspecialchars($current['pertanyaan'])); ?>
                </div>
                
                <?php if ($current['jenis_soal'] === 'pg'): ?>
                    <?php 
                    $opsiList = getShuffledOpsi($current, $shuffledOptions);
                    $selectedAnswer = $answers[$current['id']] ?? '';
                    $labels = ['A', 'B', 'C', 'D', 'E'];
                    ?>
                    <ul class="options-list">
                        <?php foreach ($opsiList as $idx => $opsi): ?>
                            <li class="option-item">
                                <label class="option-label">
                                    <input type="radio" 
                                           name="jawaban" 
                                           value="<?php echo $opsi['id']; ?>"
                                           <?php echo (string)$selectedAnswer === (string)$opsi['id'] ? 'checked' : ''; ?>
                                           onchange="saveAnswer(<?php echo $current['id']; ?>, '<?php echo $opsi['id']; ?>')">
                                    <span class="option-letter"><?php echo $labels[$idx] ?? ($idx + 1); ?></span>
                                    <span class="option-text"><?php echo htmlspecialchars($opsi['teks_opsi']); ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                
                <?php elseif ($current['jenis_soal'] === 'tf'): ?>
                    <?php $selectedAnswer = $answers[$current['id']] ?? ''; ?>
                    <div class="tf-options">
                        <div class="tf-option">
                            <label class="tf-label">
                                <input type="radio" name="jawaban" value="true" 
                                       <?php echo $selectedAnswer === 'true' ? 'checked' : ''; ?>
                                       onchange="saveAnswer(<?php echo $current['id']; ?>, 'true')">
                                BENAR
                            </label>
                        </div>
                        <div class="tf-option">
                            <label class="tf-label">
                                <input type="radio" name="jawaban" value="false"
                                       <?php echo $selectedAnswer === 'false' ? 'checked' : ''; ?>
                                       onchange="saveAnswer(<?php echo $current['id']; ?>, 'false')">
                                SALAH
                            </label>
                        </div>
                    </div>
                
                <?php elseif ($current['jenis_soal'] === 'short'): ?>
                    <?php $shortAnswer = $answers[$current['id']] ?? ''; ?>
                    <input type="text" class="short-input" id="short-answer" 
                           placeholder="Ketik jawaban singkat Anda di sini..."
                           value="<?php echo htmlspecialchars($shortAnswer); ?>"
                           onblur="saveShortAnswer(<?php echo $current['id']; ?>)">
                
                <?php elseif ($current['jenis_soal'] === 'multiple'): ?>
                    <?php 
                    $opsiList = getShuffledOpsi($current, $shuffledOptions);
                    $selectedAnswers = $answers[$current['id']] ?? [];
                    if (!is_array($selectedAnswers)) {
                        $selectedAnswers = explode(',', $selectedAnswers);
                    }
                    $selectedAnswers = array_map('strval', $selectedAnswers);
                    $labels = ['A', 'B', 'C', 'D', 'E'];
                    ?>
                    <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">Pilih semua jawaban yang benar</p>
                    <ul class="options-list">
                        <?php foreach ($opsiList as $idx => $opsi): ?>
                            <li class="option-item">
                                <label class="option-label">
                                    <input type="checkbox" 
                                           name="jawaban[]" 
                                           value="<?php echo $opsi['id']; ?>"
                                           <?php echo in_array((string)$opsi['id'], $selectedAnswers) ? 'checked' : ''; ?>
                                           onchange="saveMultipleAnswer(<?php echo $current['id']; ?>)"
                                           style="display: block; width: 20px; height: 20px; margin-right: 10px;">
                                    <span class="option-letter"><?php echo $labels[$idx] ?? ($idx + 1); ?></span>
                                    <span class="option-text"><?php echo htmlspecialchars($opsi['teks_opsi']); ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                
                <?php elseif ($current['jenis_soal'] === 'matching'): ?>
                    <?php 
                    $pairs = $current['data_tambahan']['pairs'] ?? [];
                    $keys = array_keys($pairs);
                    $values = getShuffledMatchingValues($current, $shuffledOptions);
                    $userMatches = $answers[$current['id']] ?? [];
                    if (is_string($userMatches)) {
                        $userMatches = json_decode($userMatches, true) ?? [];
                    }
                    ?>
                    <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">Pilih pasangan yang tepat untuk setiap istilah</p>
                    <div class="matching-container">
                        <div class="matching-left">
                            <?php foreach ($keys as $key): ?>
                                <div class="matching-item">
                                    <strong><?php echo htmlspecialchars($key); ?></strong>
                                    <select name="match_<?php echo htmlspecialchars($key); ?>" 
                                            onchange="saveMatchingAnswer(<?php echo $current['id']; ?>)"
                                            data-key="<?php echo htmlspecialchars($key); ?>">
                                        <option value="">-- Pilih Pasangan --</option>
                                        <?php foreach ($values as $val): ?>
                                            <option value="<?php echo htmlspecialchars($val); ?>"
                                                    <?php echo (isset($userMatches[$key]) && $userMatches[$key] === $val) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($val); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                
                <?php elseif ($current['jenis_soal'] === 'ordering'): ?>
                    <?php 
                    $items = getShuffledOrderingItems($current, $shuffledOptions);
                    $userOrder = $answers[$current['id']] ?? [];
                    if (is_string($userOrder) && !empty($userOrder)) {
                        $userOrder = json_decode($userOrder, true) ?? [];
                    }
                    if (!empty($userOrder)) {
                        $items = $userOrder;
                    }
                    ?>
                    <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">Seret dan susun item ke dalam urutan yang benar</p>
                    <ul class="ordering-list" id="ordering-list" data-soal-id="<?php echo $current['id']; ?>">
                        <?php foreach ($items as $idx => $item): ?>
                            <li class="ordering-item" draggable="true" data-value="<?php echo htmlspecialchars($item); ?>">
                                <span class="ordering-handle">&#9776;</span>
                                <span class="ordering-text"><?php echo htmlspecialchars($item); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                
                <?php elseif ($current['jenis_soal'] === 'esai'): ?>
                    <?php $essayAnswer = $answers[$current['id']] ?? ''; ?>
                    <textarea class="essay-textarea" 
                              id="essay-answer"
                              placeholder="Tulis jawaban uraian Anda di sini..."
                              onblur="saveEssayAnswer(<?php echo $current['id']; ?>)"><?php echo htmlspecialchars($essayAnswer); ?></textarea>
                <?php elseif ($current['jenis_soal'] === 'studi_kasus'): ?>
                    <?php $essayAnswer = $answers[$current['id']] ?? ''; ?>
                    <div style="background:#f0f9ff;border-left:4px solid #0ea5e9;padding:10px 14px;margin-bottom:14px;border-radius:0 6px 6px 0;font-size:0.9rem;color:#0369a1;">
                        <i class="fas fa-briefcase"></i> <strong>Studi Kasus</strong> — Baca skenario di atas dengan seksama, lalu tulis analisis/jawaban Anda.
                    </div>
                    <textarea class="essay-textarea"
                              id="essay-answer"
                              placeholder="Tulis analisis atau jawaban studi kasus Anda di sini..."
                              onblur="saveEssayAnswer(<?php echo $current['id']; ?>)"><?php echo htmlspecialchars($essayAnswer); ?></textarea>
                <?php endif; ?>
                
                <div class="nav-buttons">
                    <?php if ($currentSoal > 1): ?>
                        <button class="btn btn-primary" onclick="navigateTo(<?php echo $currentSoal - 1; ?>)">
                            &larr; Sebelumnya
                        </button>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    
                    <?php if ($currentSoal < count($soalList)): ?>
                        <button class="btn btn-primary" onclick="navigateTo(<?php echo $currentSoal + 1; ?>)">
                            Selanjutnya &rarr;
                        </button>
                    <?php else: ?>
                        <button class="btn btn-accent" onclick="confirmSubmit()">
                            Selesai Ujian
                        </button>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        let remainingTime = <?php echo $remaining; ?>;
        const timerBox = document.getElementById('timer-box');
        const timerDisplay = document.getElementById('timer');
        
        function updateTimer() {
            if (remainingTime <= 0) {
                window.location.href = 'submit.php?auto=1';
                return;
            }
            
            const hours = Math.floor(remainingTime / 3600);
            const minutes = Math.floor((remainingTime % 3600) / 60);
            const seconds = remainingTime % 60;
            
            timerDisplay.textContent = 
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
            
            if (remainingTime <= 300) {
                timerBox.classList.add('warning');
            }
            
            remainingTime--;
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
        
        const currentNoSoal = <?php echo $currentSoal; ?>;

        function saveAnswer(soalId, jawaban) {
            fetch('ujian.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `save_answer=1&soal_id=${soalId}&jawaban=${encodeURIComponent(jawaban)}&no_soal=${currentNoSoal}`
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      const btn = document.querySelector(`[data-soal-id="${soalId}"]`);
                      if (btn && jawaban) {
                          btn.classList.add('answered');
                      }
                  }
              });
        }
        
        function saveShortAnswer(soalId) {
            const input = document.getElementById('short-answer');
            saveAnswer(soalId, input.value.trim());
        }
        
        function saveEssayAnswer(soalId) {
            const textarea = document.getElementById('essay-answer');
            saveAnswer(soalId, textarea.value.trim());
        }
        
        function saveMultipleAnswer(soalId) {
            const checkboxes = document.querySelectorAll('input[name="jawaban[]"]:checked');
            const values = Array.from(checkboxes).map(cb => cb.value);
            
            const formData = new FormData();
            formData.append('save_answer', '1');
            formData.append('soal_id', soalId);
            formData.append('no_soal', currentNoSoal);
            values.forEach(v => formData.append('jawaban[]', v));
            
            fetch('ujian.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      const btn = document.querySelector(`[data-soal-id="${soalId}"]`);
                      if (btn && values.length > 0) {
                          btn.classList.add('answered');
                      } else if (btn) {
                          btn.classList.remove('answered');
                      }
                  }
              });
        }
        
        function saveMatchingAnswer(soalId) {
            const selects = document.querySelectorAll('.matching-item select');
            const matches = {};
            selects.forEach(select => {
                const key = select.dataset.key;
                if (select.value) {
                    matches[key] = select.value;
                }
            });
            
            fetch('ujian.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `save_answer=1&soal_id=${soalId}&jawaban=${encodeURIComponent(JSON.stringify(matches))}&no_soal=${currentNoSoal}`
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      const btn = document.querySelector(`[data-soal-id="${soalId}"]`);
                      if (btn && Object.keys(matches).length > 0) {
                          btn.classList.add('answered');
                      }
                  }
              });
        }
        
        const orderingList = document.getElementById('ordering-list');
        if (orderingList) {
            let draggedItem = null;
            
            orderingList.querySelectorAll('.ordering-item').forEach(item => {
                item.addEventListener('dragstart', function(e) {
                    draggedItem = this;
                    this.classList.add('dragging');
                });
                
                item.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                    saveOrderingAnswer();
                });
                
                item.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    const afterElement = getDragAfterElement(orderingList, e.clientY);
                    if (afterElement == null) {
                        orderingList.appendChild(draggedItem);
                    } else {
                        orderingList.insertBefore(draggedItem, afterElement);
                    }
                });
            });
            
            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.ordering-item:not(.dragging)')];
                return draggableElements.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) {
                        return { offset: offset, element: child };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }
            
            function saveOrderingAnswer() {
                const soalId = orderingList.dataset.soalId;
                const items = orderingList.querySelectorAll('.ordering-item');
                const order = Array.from(items).map(item => item.dataset.value);
                
                fetch('ujian.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `save_answer=1&soal_id=${soalId}&jawaban=${encodeURIComponent(JSON.stringify(order))}&no_soal=${currentNoSoal}`
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          const btn = document.querySelector(`[data-soal-id="${soalId}"]`);
                          if (btn) {
                              btn.classList.add('answered');
                          }
                      }
                  });
            }
        }
        
        function navigateTo(no) {
            window.location.href = 'ujian.php?no=' + no;
        }
        
        function confirmSubmit() {
            if (confirm('Apakah Anda yakin ingin menyelesaikan ujian? Jawaban tidak dapat diubah setelah submit.')) {
                window.location.href = 'submit.php';
            }
        }
    </script>
</body>
</html>
