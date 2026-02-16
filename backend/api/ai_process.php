<?php
// ============================================
// Curtain Call - AI Process API
// POST: Receives user text + sensor data,
//       sends to Gemini, stores command
// ============================================
require_once __DIR__ . '/../config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getRequestBody();
    $userInput = $data['user_input'] ?? '';

    if (empty($userInput)) {
        jsonResponse(['error' => 'Missing required field: user_input'], 400);
    }

    // Get latest sensor data
    $sensorResult = $conn->query("SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT 1");
    $sensor = $sensorResult->fetch_assoc();

    $lightLevel = $sensor ? $sensor['light_level'] : 'N/A';
    $temperature = $sensor ? $sensor['temperature'] : 'N/A';
    $humidity = $sensor ? $sensor['humidity'] : 'N/A';

    // Get current curtain status
    $statusResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'curtain_status'");
    $curtainStatus = $statusResult->fetch_assoc()['setting_value'] ?? 'UNKNOWN';

    // Build the AI prompt
    $prompt = "You are the brain of an automated curtain system called \"Curtain Call\".

Current sensor data:
- Light Level: $lightLevel (0-1023 scale, higher = brighter)
- Temperature: $temperature°C
- Humidity: $humidity%
- Current Curtain Status: $curtainStatus

User command: \"$userInput\"

Based on the sensor data and user command, decide what the curtain should do.
Respond ONLY with a valid JSON object (no markdown, no code blocks):
{\"action\": \"OPEN\" or \"CLOSE\" or \"STOP\", \"speed\": 0-255, \"reason\": \"brief explanation\"}";

    // Call Gemini API
    $apiKey = GEMINI_API_KEY;

    if ($apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
        jsonResponse(['error' => 'Gemini API key not configured. Please update config.php'], 500);
    }

    $url = GEMINI_API_URL . '?key=' . $apiKey;

    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 256
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Needed for localhost

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        jsonResponse([
            'error' => 'Gemini API request failed',
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ], 500);
    }

    // Parse Gemini response
    $geminiData = json_decode($response, true);
    $aiText = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Clean up response (remove markdown code blocks if present)
    $aiText = preg_replace('/```json\s*/', '', $aiText);
    $aiText = preg_replace('/```\s*/', '', $aiText);
    $aiText = trim($aiText);

    $aiDecision = json_decode($aiText, true);

    if (!$aiDecision || !isset($aiDecision['action'])) {
        jsonResponse([
            'error' => 'Failed to parse AI response',
            'raw_response' => $aiText
        ], 500);
    }

    $action = strtoupper($aiDecision['action']);
    $speed = intval($aiDecision['speed'] ?? 255);
    $reason = $aiDecision['reason'] ?? 'AI decision';

    // Validate action
    if (!in_array($action, ['OPEN', 'CLOSE', 'STOP'])) {
        jsonResponse(['error' => 'AI returned invalid action: ' . $action], 500);
    }

    // Insert command into queue
    $stmt = $conn->prepare("INSERT INTO command_queue (action, speed, source, reason) VALUES (?, ?, 'AI', ?)");
    $stmt->bind_param("sis", $action, $speed, $reason);
    $stmt->execute();
    $commandId = $stmt->insert_id;

    // Log the AI action with sensor context
    $stmt2 = $conn->prepare("INSERT INTO device_logs (action, speed, source, reason, sensor_temperature, sensor_humidity, sensor_light, user_input) VALUES (?, ?, 'AI', ?, ?, ?, ?, ?)");
    $stmt2->bind_param("sissddis", $action, $speed, $reason, $temperature, $humidity, $lightLevel, $userInput);
    $stmt2->execute();

    jsonResponse([
        'success' => true,
        'command_id' => $commandId,
        'ai_decision' => [
            'action' => $action,
            'speed' => $speed,
            'reason' => $reason
        ],
        'sensor_context' => [
            'light' => $lightLevel,
            'temperature' => $temperature,
            'humidity' => $humidity
        ]
    ]);
}

$conn->close();
?>