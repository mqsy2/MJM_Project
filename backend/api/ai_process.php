<?php
// ============================================
// Curtain Call - AI Process API (Groq Edition)
// POST: Receives user text + sensor data,
//       sends to Groq (Llama 3), returns position command
// ============================================
require_once __DIR__ . '/../config.php';

// Function to call Groq API
function handleGroqRequest($user_input, $sensor_data)
{
    // Create prompt with context
    $system_prompt = "You are the brain of an automated curtain system called 'Curtain Call'.\n" .
        "Your goal is to decide the curtains' target position (0% to 100%) based on sensor data and user commands.\n" .
        "- 0% = Fully Closed\n" .
        "- 100% = Fully Open\n" .
        "- 50% = Half Open\n\n" .

        "Current Sensor Data:\n" .
        "- Light Level: " . $sensor_data['light_level'] . " (0=Dark, 1023=Bright)\n" .
        "- Temperature: " . $sensor_data['temperature'] . "°C\n" .
        "- Humidity: " . $sensor_data['humidity'] . "%\n\n" .

        "User Command: \"$user_input\"\n\n" .

        "Respond ONLY with a valid JSON object. Do not include markdown formatting (like ```json). " .
        "The JSON must have this exact structure:\n" .
        "{\"position\": 0-100, \"reason\": \"short explanation\"}\n\n" .

        "Examples:\n" .
        "- User: 'It's too dark' -> {\"position\": 100, \"reason\": \"Opening curtains to let in light.\"}\n" .
        "- User: 'Close them slightly' -> {\"position\": 20, \"reason\": \"Adjusting to user preference.\"}\n" .
        "- User: 'Set to half' -> {\"position\": 50, \"reason\": \"Setting to 50% as requested.\"}";

    // Prepare API Payload (OpenAI Compatible Format)
    $data = [
        "model" => GROQ_MODEL,
        "messages" => [
            [
                "role" => "system",
                "content" => $system_prompt
            ],
            [
                "role" => "user",
                "content" => $user_input
            ]
        ],
        "temperature" => 0.7,
        "max_completion_tokens" => 1024
    ];

    // Initialize cURL
    $ch = curl_init(GROQ_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Execute Request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        jsonResponse(['error' => 'Curl error: ' . curl_error($ch)], 500);
    }
    curl_close($ch);

    // Handle Errors
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_msg = $error_data['error']['message'] ?? 'Unknown API Error';

        // Pass through 429 for rate handling on frontend
        if ($http_code === 429) {
            jsonResponse(['error' => 'Groq API Rate Limit: ' . $error_msg], 429);
        }

        jsonResponse(['error' => "Groq API Error ($http_code): $error_msg"], $http_code);
    }

    // Decode Response
    $result = json_decode($response, true);
    $ai_text = $result['choices'][0]['message']['content'] ?? null;

    if (!$ai_text) {
        jsonResponse(['error' => 'Invalid response structure from Groq.'], 500);
    }

    // Clean up Markdown logic if the AI ignores "no markdown" rule
    $ai_text = str_replace(['```json', '```'], '', $ai_text);
    $ai_text = trim($ai_text);

    // Parse JSON from AI
    $ai_json = json_decode($ai_text, true);

    if (!$ai_json || !isset($ai_json['position'])) {
        // Fallback if JSON parsing fails
        jsonResponse(['error' => 'Failed to parse AI JSON response: ' . $ai_text], 500);
    }

    // Extract position and reason for easier use
    $position = $ai_json['position'];
    $reason = $ai_json['reason'] ?? 'No specific reason provided.'; // Add a fallback for reason

    // Save to Database
    $conn = getDBConnection();

    // Determine action based on position
    $action = $position >= 50 ? 'OPEN' : 'CLOSE';

    // Insert command into queue
    $speed = 70; // Fixed speed
    $cmdReason = "AI: $reason (Move to {$position}%)";

    // Mark previous commands as executed
    $conn->query("UPDATE command_queue SET status = 'EXECUTED', executed_at = NOW() WHERE status = 'PENDING'");

    // Insert new command with action AND position
    $stmt = $conn->prepare("INSERT INTO command_queue (action, position, status, reason) VALUES (?, ?, 'PENDING', ?)");
    $stmt->bind_param("sis", $action, $ai_json['position'], $cmdReason);

    if (!$stmt->execute()) {
        jsonResponse(['error' => 'Database Insert Failed: ' . $stmt->error], 500);
    }
    $stmt->close();

    // Log the action
    // Log the action
    $stmt = $conn->prepare("INSERT INTO device_logs (action, speed, source, reason, sensor_temperature, sensor_humidity, sensor_light, user_input) VALUES (?, ?, 'AI', ?, ?, ?, ?, ?)");
    $logAction = $action; // Use OPEN/CLOSE calculated earlier
    $speed = 70;

    // Extract sensor data safely
    $temp = $sensor_data['temperature'] ?? 0;
    $hum = $sensor_data['humidity'] ?? 0;
    $light = $sensor_data['light_level'] ?? 0;

    $stmt->bind_param("sisddis", $logAction, $speed, $cmdReason, $temp, $hum, $light, $user_input);
    $stmt->execute();
    $stmt->close();

    $conn->close();

    // Return success to frontend
    jsonResponse([
        'success' => true,
        'ai_response' => $ai_json
    ]);
}

// Main Execution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getRequestBody();
    $user_input = $input['user_input'] ?? '';

    if (empty($user_input)) {
        jsonResponse(['error' => 'No user input provided.'], 400);
    }

    // Fetch latest sensor data for context
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1");
    $sensor_data = $result->fetch_assoc();

    if (!$sensor_data) {
        $sensor_data = [
            'light_level' => 0,
            'temperature' => 0,
            'humidity' => 0
        ];
    }
    $conn->close();

    handleGroqRequest($user_input, $sensor_data);
} else {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}
?>