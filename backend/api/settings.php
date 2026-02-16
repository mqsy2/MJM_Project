<?php
// ============================================
// Curtain Call - Settings API
// GET:  Retrieve all settings
// POST: Update a specific setting
// ============================================
require_once __DIR__ . '/../config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return all settings as key-value pairs
    $result = $conn->query("SELECT setting_key, setting_value, description FROM settings ORDER BY id ASC");
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'description' => $row['description']
        ];
    }

    jsonResponse($settings);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getRequestBody();

    if (!isset($data['key']) || !isset($data['value'])) {
        jsonResponse(['error' => 'Missing required fields: key, value'], 400);
    }

    $key = $data['key'];
    $value = $data['value'];

    // Validate the key exists
    $check = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check->bind_param("s", $key);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        jsonResponse(['error' => 'Unknown setting key: ' . $key], 404);
    }

    // Update the setting
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);

    if ($stmt->execute()) {
        jsonResponse([
            'success' => true,
            'key' => $key,
            'value' => $value
        ]);
    } else {
        jsonResponse(['error' => 'Failed to update setting'], 500);
    }
}

$conn->close();
?>