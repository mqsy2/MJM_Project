// ============================================
// Curtain Call: The Automatic Curtain Opener
// Arduino Uno R4 WiFi Firmware
// ============================================
// Hardware:
//   - Arduino Uno R4 WiFi
//   - DHT11 sensor (temperature & humidity)
//   - Photoresistor (light level)
//   - L298N motor driver (dual H-bridge, red board)
//   - 12V DC motor
//   - 12V DC adapter + female connector
// ============================================

#include <WiFiS3.h>
#include <ArduinoHttpClient.h>
#include <ArduinoJson.h>
#include <Adafruit_Sensor.h>
#include <DHT.h>

// ==========================================
// ⚡ PIN CONFIGURATION — Update if needed!
// ==========================================

// DHT11 Sensor
#define DHT_PIN        2       // Digital pin for DHT11 data
#define DHT_TYPE       DHT11

// Photoresistor (Light Sensor)
#define LIGHT_PIN      A0      // Analog pin for photoresistor

// L298N Motor Driver
// Your L298N (red board) has: IN1, IN2, ENA (for Motor A)
// Connect ENA to a PWM pin for speed control
#define MOTOR_ENA      6       // PWM pin → L298N ENA (speed control)
#define MOTOR_IN1      5       // Digital pin → L298N IN1 (direction)
#define MOTOR_IN2      3       // Digital pin → L298N IN2 (direction)

// ==========================================
// 🌐 WIFI CONFIGURATION — Fill in yours!
// ==========================================
const char* WIFI_SSID     = "benboy";
const char* WIFI_PASSWORD = "Masuraki709#";

// ==========================================
// 🖥️ SERVER CONFIGURATION
// ==========================================
const char* SERVER_HOST = "192.168.1.2";  // Your PC's local IP (run ipconfig to find it)
const int   SERVER_PORT = 80;
const char* API_SENSOR  = "/curtain_call/api/sensor_data.php";
const char* API_COMMAND = "/curtain_call/api/command.php";

// ==========================================
// ⏱️ TIMING
// ==========================================
unsigned long lastSensorSend  = 0;
unsigned long lastCommandPoll = 0;
const unsigned long SENSOR_INTERVAL  = 5000;   // Send sensor data every 5 seconds
const unsigned long COMMAND_INTERVAL = 3000;   // Poll for commands every 3 seconds

// ==========================================
// 📏 CURTAIN TRAVEL DISTANCE (TIME-BASED)
// ==========================================
// Since the RS-775 has no encoder, we use TIME to control distance.
unsigned long OPEN_TIME_MS  = 1500;  // 1.5 seconds for FULL open (0→100%)
unsigned long CLOSE_TIME_MS = 1000;  // 1.0 second for FULL close (100→0%)
const int MOTOR_SPEED = 70;          // Fixed motor speed (0-255)

// Position tracking (0 = fully closed, 100 = fully open)
int currentPosition = 0;             // Assumes curtain starts closed
bool motorRunning = false;
unsigned long motorStartTime = 0;
unsigned long motorDuration = 0;
int targetPosition = 0;
String motorDirection = "";

// ==========================================
// OBJECTS
// ==========================================
DHT dht(DHT_PIN, DHT_TYPE);
WiFiClient wifiClient;

// Current motor state
String currentAction = "STOP";

void setup() {
    Serial.begin(9600);
    delay(1000);
    
    Serial.println("============================================");
    Serial.println("  Curtain Call — Arduino Firmware v1.0");
    Serial.println("============================================");

    // Initialize pins
    pinMode(MOTOR_ENA, OUTPUT);
    pinMode(MOTOR_IN1, OUTPUT);
    pinMode(MOTOR_IN2, OUTPUT);
    pinMode(LIGHT_PIN, INPUT);

    // Start with motor OFF
    stopMotor();

    // Initialize DHT sensor
    dht.begin();
    Serial.println("[OK] DHT11 sensor initialized");

    // Connect to WiFi
    connectWiFi();
}

void loop() {
    // Ensure WiFi is connected
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[WARN] WiFi disconnected. Reconnecting...");
        connectWiFi();
    }

    unsigned long now = millis();

    // ---- Auto-stop motor after travel duration ----
    if (motorRunning && (now - motorStartTime >= motorDuration)) {
        Serial.println("[MOTOR] Travel complete! Auto-stopping.");
        currentPosition = targetPosition;
        Serial.print("[STATE] Curtain position: ");
        Serial.print(currentPosition);
        Serial.println("%");
        stopMotor();
    }

    // Send sensor data periodically
    if (now - lastSensorSend >= SENSOR_INTERVAL) {
        lastSensorSend = now;
        sendSensorData();
    }

    // Poll for commands periodically (only if motor is not running)
    if (!motorRunning && (now - lastCommandPoll >= COMMAND_INTERVAL)) {
        lastCommandPoll = now;
        pollCommand();
    }
}

// ==========================================
// 🌐 WiFi Connection
// ==========================================
void connectWiFi() {
    Serial.print("[WiFi] Connecting to: ");
    Serial.println(WIFI_SSID);

    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println();
        Serial.print("[WiFi] Connected! IP: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println();
        Serial.println("[WiFi] Connection FAILED. Will retry...");
    }
}

// ==========================================
// 🌡️ Read Sensors & Send to Server
// ==========================================
void sendSensorData() {
    // Read DHT11
    float temperature = dht.readTemperature();
    float humidity = dht.readHumidity();

    // Read photoresistor
    int lightLevel = analogRead(LIGHT_PIN);

    // Check for read errors
    if (isnan(temperature) || isnan(humidity)) {
        Serial.println("[SENSOR] DHT11 read error! Using defaults.");
        temperature = 0;
        humidity = 0;
    }

    Serial.print("[SENSOR] Temp: ");
    Serial.print(temperature);
    Serial.print("°C | Humidity: ");
    Serial.print(humidity);
    Serial.print("% | Light: ");
    Serial.println(lightLevel);

    // Build JSON payload
    StaticJsonDocument<256> doc;
    doc["temperature"] = temperature;
    doc["humidity"] = humidity;
    doc["light_level"] = lightLevel;

    String jsonPayload;
    serializeJson(doc, jsonPayload);

    // Send HTTP POST
    HttpClient http(wifiClient, SERVER_HOST, SERVER_PORT);
    http.beginRequest();
    http.post(API_SENSOR);
    http.sendHeader("Content-Type", "application/json");
    http.sendHeader("Content-Length", jsonPayload.length());
    http.beginBody();
    http.print(jsonPayload);
    http.endRequest();

    int statusCode = http.responseStatusCode();
    String response = http.responseBody();

    if (statusCode == 200) {
        Serial.println("[SENSOR] Data sent successfully");

        // Check if auto-mode returned a command
        StaticJsonDocument<512> resDoc;
        DeserializationError err = deserializeJson(resDoc, response);
        if (!err && !resDoc["auto_command"].isNull()) {
            String autoAction = resDoc["auto_command"]["action"].as<String>();
            int autoSpeed = resDoc["auto_command"]["speed"].as<int>();
            Serial.print("[AUTO] Auto-command received: ");
            Serial.print(autoAction);
            Serial.print(" @ speed ");
            Serial.println(autoSpeed);
            if (autoAction == "OPEN") moveToPosition(100);
            else if (autoAction == "CLOSE") moveToPosition(0);
        }
    } else {
        Serial.print("[SENSOR] Send failed. HTTP: ");
        Serial.println(statusCode);
    }

    http.stop();
}

// ==========================================
// 📡 Poll Server for Commands
// ==========================================
void pollCommand() {
    HttpClient http(wifiClient, SERVER_HOST, SERVER_PORT);
    http.get(API_COMMAND);

    int statusCode = http.responseStatusCode();
    String response = http.responseBody();

    if (statusCode == 200) {
        StaticJsonDocument<256> doc;
        DeserializationError err = deserializeJson(doc, response);

        if (!err) {
            String action = doc["action"].as<String>();

            if (action != "NONE") {
                int tPos = -1;
                if (doc.containsKey("target_position")) {
                    tPos = doc["target_position"].as<int>();
                }
                Serial.print("[CMD] Received: ");
                Serial.print(action);
                Serial.print(" target_position=");
                Serial.println(tPos);

                if (action == "STOP") {
                    stopMotor();
                } else {
                    moveToPosition(tPos);
                }
            }
        }
    } else {
        Serial.print("[CMD] Poll failed. HTTP: ");
        Serial.println(statusCode);
    }

    http.stop();
}

// ==========================================
// 🔧 Motor Control via L298N
// ==========================================
// Move curtain to a target position (0-100%)
void moveToPosition(int tPos) {
    if (tPos < 0 || tPos > 100) return;
    if (tPos == currentPosition) {
        Serial.println("[MOTOR] Already at target position, skipping.");
        return;
    }
    
    targetPosition = tPos;
    int delta = targetPosition - currentPosition;  // positive = open, negative = close
    
    if (delta > 0) {
        // Need to OPEN (move forward)
        motorDuration = (unsigned long)(OPEN_TIME_MS * delta / 100);
        motorDirection = "OPEN";
        Serial.print("[MOTOR] Opening from ");
        Serial.print(currentPosition);
        Serial.print("% to ");
        Serial.print(targetPosition);
        Serial.print("% for ");
        Serial.print(motorDuration);
        Serial.println("ms");
        
        motorRunning = true;
        motorStartTime = millis();
        digitalWrite(MOTOR_IN1, HIGH);
        digitalWrite(MOTOR_IN2, LOW);
        analogWrite(MOTOR_ENA, MOTOR_SPEED);
    } else {
        // Need to CLOSE (move reverse)
        motorDuration = (unsigned long)(CLOSE_TIME_MS * (-delta) / 100);
        motorDirection = "CLOSE";
        Serial.print("[MOTOR] Closing from ");
        Serial.print(currentPosition);
        Serial.print("% to ");
        Serial.print(targetPosition);
        Serial.print("% for ");
        Serial.print(motorDuration);
        Serial.println("ms");
        
        motorRunning = true;
        motorStartTime = millis();
        digitalWrite(MOTOR_IN1, LOW);
        digitalWrite(MOTOR_IN2, HIGH);
        analogWrite(MOTOR_ENA, MOTOR_SPEED);
    }
}

void stopMotor() {
    Serial.println("[MOTOR] Stopped");
    motorRunning = false;
    motorDirection = "";
    
    digitalWrite(MOTOR_IN1, LOW);
    digitalWrite(MOTOR_IN2, LOW);
    analogWrite(MOTOR_ENA, 0);
}
