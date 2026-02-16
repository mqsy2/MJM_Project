<?php
// ============================================
// Curtain Call - Sensor Data API
// POST: Arduino sends sensor readings
// GET:  Dashboard retrieves latest sensor data
// ============================================
require_once __DIR__ . '/../config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Arduino sends sensor data
    $data = getRequestBody();

    if (!isset($data['temperature']) || !isset($data['humidity']) || !isset($data['light_level'])) {
        jsonResponse(['error' => 'Missing required fields: temperature, humidity, light_level'], 400);
    }

    $temp = floatval($data['temperature']);
    $humidity = floatval($data['humidity']);
    $light = intval($data['light_level']);

    $stmt = $conn->prepare("INSERT INTO sensor_data (temperature, humidity, light_level) VALUES (?, ?, ?)");
    $stmt->bind_param("ddi", $temp, $humidity, $light);

    if ($stmt->execute()) {
        // Check auto-mode: if enabled, evaluate thresholds
        $autoMode = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_mode'")->fetch_assoc();

        $autoCommand = null;
        if ($autoMode && $autoMode['setting_value'] === '1') {
            $autoCommand = checkAutoThresholds($conn, $light, $temp);
        }

        jsonResponse([
            'success' => true,
            'id' => $stmt->insert_id,
            'auto_command' => $autoCommand
        ]);
    } else {
        jsonResponse(['error' => 'Failed to save sensor data'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Dashboard gets latest sensor data
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1;
    $limit = min($limit, 100); // max 100 records

    $result = $conn->query("SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT $limit");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    // If only 1 record requested, return the object directly
    if ($limit === 1 && count($data) > 0) {
        jsonResponse($data[0]);
    } else {
        jsonResponse($data);
    }
}

$conn->close();

/**
 * Check auto-mode thresholds and insert a command if needed
 */
function checkAutoThresholds($conn, $lightLevel, $temperature)
{
    // Get threshold settings
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('light_threshold_high', 'light_threshold_low', 'temp_threshold_high', 'curtain_status')");
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $lightHigh = intval($settings['light_threshold_high'] ?? 800);
    $lightLow = intval($settings['light_threshold_low'] ?? 200);
    $tempHigh = floatval($settings['temp_threshold_high'] ?? 35);
    $currentStatus = $settings['curtain_status'] ?? 'UNKNOWN';

    $action = null;
    $reason = null;

    // Close if too bright or too hot
    if (($lightLevel > $lightHigh || $temperature > $tempHigh) && $currentStatus !== 'CLOSED') {
        $action = 'CLOSE';
        $reason = "Auto-close: Light={$lightLevel} (threshold={$lightHigh}), Temp={$temperature}°C (threshold={$tempHigh}°C)";
    }
    // Open if light is low enough
    elseif ($lightLevel < $lightLow && $currentStatus !== 'OPEN') {
        $action = 'OPEN';
        $reason = "Auto-open: Light=$lightLevel (threshold=$lightLow)";
    }

    if ($action) {
        $stmt = $conn->prepare("INSERT INTO command_queue (action, speed, source, reason) VALUES (?, 255, 'AUTO', ?)");
        $stmt->bind_param("ss", $action, $reason);
        $stmt->execute();

        return ['action' => $action, 'speed' => 255, 'reason' => $reason];
    }

    return null;
}
?>