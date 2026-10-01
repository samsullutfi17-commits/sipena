<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$topic     = $_POST['topic']     ?? '';
$jenis     = $_POST['jenis']     ?? 'pg';
$count     = (int)($_POST['count']    ?? 5);
$kesulitan = $_POST['kesulitan'] ?? 'sedang';
$kognitif  = $_POST['kognitif']  ?? 'C1';
$cpmk      = $_POST['cpmk']      ?? '';
$poin      = (int)($_POST['poin'] ?? 4);

if (empty($topic)) {
    echo json_encode(['error' => 'Topik tidak boleh kosong']);
    exit;
}

$count = max(1, min(25, $count));
$poin  = max(1, min(100, $poin));

$escapedTopic     = escapeshellarg($topic);
$escapedJenis     = escapeshellarg($jenis);
$escapedKesulitan = escapeshellarg($kesulitan);
$escapedKognitif  = escapeshellarg($kognitif);
$escapedCpmk      = escapeshellarg($cpmk);

$command = "cd " . dirname(__DIR__) . " && python3 ai_generate.py $escapedTopic $escapedJenis $count $escapedKesulitan $escapedKognitif $escapedCpmk $poin 2>&1";
$output = shell_exec($command);

if ($output === null) {
    echo json_encode(['error' => 'Gagal menjalankan AI generator']);
    exit;
}

$result = json_decode($output, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid JSON response: ' . $output]);
    exit;
}

echo json_encode($result);
