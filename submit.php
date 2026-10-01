<?php
require_once 'config.php';
require_once 'database.php';

if (!isset($_SESSION['sesi_id'])) {
    header('Location: index.php');
    exit;
}

$sesiId = $_SESSION['sesi_id'];
$ujianId = $_SESSION['ujian_id'];

$stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id = ?");
$stmt->execute([$sesiId]);
$dbSesi = $stmt->fetch();

if (!$dbSesi || $dbSesi['status'] === 'selesai') {
    header('Location: index.php');
    exit;
}

$startTime = strtotime($dbSesi['waktu_mulai']);
$duration = $_SESSION['exam_duration'] ?? 5400;
$elapsed = time() - $startTime;

if ($elapsed > $duration + 60) {
    $stmt = $pdo->prepare("UPDATE sesi_ujian SET status = 'selesai', waktu_selesai = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$sesiId]);
    session_destroy();
    header('Location: index.php');
    exit;
}

$answers = $_SESSION['answers'] ?? [];
$shuffledOptions = [];
if (!empty($dbSesi['shuffled_options'])) {
    $shuffledOptions = json_decode($dbSesi['shuffled_options'], true) ?? [];
} else {
    $shuffledOptions = $_SESSION['shuffled_options'] ?? [];
}

$soalList = getSoalByUjian($pdo, $ujianId);
$scores = calculateScoreNew($pdo, $sesiId, $answers, $shuffledOptions, $soalList);

$stmt = $pdo->prepare("UPDATE sesi_ujian SET 
    waktu_selesai = CURRENT_TIMESTAMP,
    nilai_total = ?,
    status = 'selesai'
    WHERE id = ?");
$stmt->execute([$scores['total'], $sesiId]);

$_SESSION['exam_result'] = $scores;
$_SESSION['exam_completed'] = true;

unset($_SESSION['exam_started']);
unset($_SESSION['exam_start_time']);

header('Location: hasil.php');
exit;
