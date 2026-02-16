<?php
// ============================================
// Curtain Call - Command API
// POST: Dashboard sends target position or commands
// GET:  Arduino polls for pending commands
// ============================================
require_once __DIR__ . '/../config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getRequestBody();

    if (!isset($data['action'])) {
        jsonResponse(['error' => 'Missing required field: action (OPEN/CLOSE/STOP)'], 400);
    }

    $action = strtoupper($data['action']);
    if (!in_array($action, ['OPEN', 'CLOSE', 'STOP'])) {
        jsonResponse(['error' => 'Invalid action. Must be OPEN, CLOSE, or STOP'], 400);
    }

    $targetPosition = isset($data['target_position']) ? intval($data['target_position']) : -1;
    $speed = 70; // Fixed speed
    $source = $data['source'] ?? 'MANUAL';
    $reason = $data['reason'] ?? "Move to {$targetPosition}%";

    // Cancel any old pending commands — only the latest matters
    $conn->query("UPDATE command_queue SET status = 'EXECUTED', executed_at = NOW() WHERE status = 'PENDING'");

    $stmt = $conn->prepare("INSERT INTO command_queue (action, speed, source, reason) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $action, $speed, $source, $reason);

    if ($stmt->execute()) {
        $movingStatus = ($action === 'STOP') ? 'STOPPED' : 'MOVING';
        $conn->query("UPDATE settings SET setting_value = '$movingStatus' WHERE setting_key = 'curtain_status'");

        jsonResponse([
            'success' => true,
            'command_id' => $stmt->insert_id,
            'action' => $action,
            'target_position' => $targetPosition
        ]);
    } else {
        jsonResponse(['error' => 'Failed to create command'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Arduino polls for the next pending command
    $result = $conn->query("SELECT * FROM command_queue WHERE status = 'PENDING' ORDER BY created_at DESC LIMIT 1");

    if ($result->num_rows > 0) {
        $command = $result->fetch_assoc();

        // Mark as executed
        $stmt = $conn->prepare("UPDATE command_queue SET status = 'EXECUTED', executed_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $command['id']);
        $stmt->execute();

        // Extract target_position from reason
        $targetPosition = -1;
        if (preg_match('/(\d+)%/', $command['reason'], $matches)) {
            $targetPosition = intval($matches[1]);
        }

        // Update curtain status
        $newStatus = $command['action'];
        if ($newStatus === 'CLOSE')
            $newStatus = 'CLOSED';
        elseif ($newStatus === 'STOP')
            $newStatus = 'STOPPED';
        $conn->query("UPDATE settings SET setting_value = '$newStatus' WHERE setting_key = 'curtain_status'");

        // Log the action
        $stmt2 = $conn->prepare("INSERT INTO device_logs (action, speed, source, reason) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("siss", $command['action'], $command['speed'], $command['source'], $command['reason']);
        $stmt2->execute();

        jsonResponse([
            'command_id' => $command['id'],
            'action' => $command['action'],
            'speed' => intval($command['speed']),
            'target_position' => $targetPosition
        ]);
    } else {
        jsonResponse(['action' => 'NONE']);
    }
}

$conn->close();
?>