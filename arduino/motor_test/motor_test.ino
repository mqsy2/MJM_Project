// ============================================
// Motor Test — RS-775 with L298N
// Runs at MAX speed in a loop: forward, stop, reverse, stop
// ============================================

#define MOTOR_ENA 6
#define MOTOR_IN1 5
#define MOTOR_IN2 4

void setup() {
    Serial.begin(9600);
    pinMode(MOTOR_ENA, OUTPUT);
    pinMode(MOTOR_IN1, OUTPUT);
    pinMode(MOTOR_IN2, OUTPUT);
    Serial.println("=== Motor Loop Test (MAX speed) ===");
}

void loop() {
    // Forward (OPEN)
    Serial.println(">> FORWARD...");
    digitalWrite(MOTOR_IN1, HIGH);
    digitalWrite(MOTOR_IN2, LOW);
    analogWrite(MOTOR_ENA, 255);
    delay(3000);

    // Stop
    Serial.println(">> STOP");
    digitalWrite(MOTOR_IN1, LOW);
    digitalWrite(MOTOR_IN2, LOW);
    analogWrite(MOTOR_ENA, 0);
    delay(2000);

    // Reverse (CLOSE)
    Serial.println(">> REVERSE...");
    digitalWrite(MOTOR_IN1, LOW);
    digitalWrite(MOTOR_IN2, HIGH);
    analogWrite(MOTOR_ENA, 255);
    delay(3000);

    // Stop
    Serial.println(">> STOP");
    digitalWrite(MOTOR_IN1, LOW);
    digitalWrite(MOTOR_IN2, LOW);
    analogWrite(MOTOR_ENA, 0);
    delay(2000);
}
