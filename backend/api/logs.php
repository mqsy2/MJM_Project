<?php
// ============================================
// Curtain Call - Logs API
// GET: Returns device action history
// ============================================
require_once __DIR__ . '/../config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $limit = min($limit, 100);

    // Optional filter by source
    $sourceFilter = '';
    if (isset($_GET['source']) && in_array(strtoupper($_GET['source']), ['MANUAL', 'AI', 'AUTO'])) {
        $source = strtoupper($_GET['source']);
        $sourceFilter = "WHERE source = '$source'";
    }

    $result = $conn->query("SELECT * FROM device_logs $sourceFilter ORDER BY logged_at DESC LIMIT $limit");
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    jsonResponse([
        'count' => count($logs),
        'logs' => $logs
    ]);
}

$conn->close();
?>