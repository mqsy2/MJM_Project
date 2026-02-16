# Curtain Call 🎭
**Smart Automated Curtain System with AI Control**

Curtain Call is an IoT project that allows you to control your curtains automatically based on sensor data (light, temperature) or through natural language AI commands.

## Features ✨
- **AI Control:** "Open the curtains", "It's too dark", "Set to 50%".
- **Smart Sensors:** Monitors light, temperature, and humidity.
- **Manual Control:** Real-time slider for precise positioning.
- **Automated Mode:** Reacts to environmental changes automatically.
- **Activity Log:** Tracks all actions (AI, Manual, Auto).

---

## 🛠️ Hardware Requirements
- **Microcontroller:** Arduino Uno R4 WiFi
- **Motor:** 12V DC Motor
- **Motor Driver:** L298N (or XY160D)
- **Sensors:**
  - Photoresistor (LDR)
  - DHT11 (Temperature & Humidity)
- **Power Supply:** 12V DC Adapter

## 💻 Software Requirements
- **XAMPP** (Apache & MySQL)
- **Arduino IDE**
- **Git**
- **Groq API Key** (Free tier available at [console.groq.com](https://console.groq.com))

---

## 🚀 Installation Guide

### 1. Backend Setup (XAMPP)
1.  Install **XAMPP** and start **Apache** and **MySQL**.
2.  Navigate to your XAMPP installation directory (usually `C:\xampp\htdocs\`).
3.  Clone this repository into a folder named `curtain_call`:
    ```bash
    git clone https://github.com/yourusername/MJM_Project.git curtain_call
    ```
    *(Note: Ensure the folder name is exactly `curtain_call` so the API paths match).*

### 2. Database Setup
1.  Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2.  Create a new database named `curtain_call`.
3.  Import the SQL file located at:
    `curtain_call/database/database.sql`

### 3. API Key Configuration
1.  Get a free API Key from [Groq Console](https://console.groq.com/keys).
2.  Open `backend/config.php`.
3.  Paste your API key:
    ```php
    define('GROQ_API_KEY', 'your_gsk_key_here');
    ```

### 4. Hardware Setup (Arduino)
1.  Open `arduino/curtain_call/curtain_call.ino` in Arduino IDE.
2.  Install required libraries:
    - `Arduino_UNOWiFi4` (or `WiFiS3`)
    - `ArduinoJson`
    - `DHT sensor library`
3.  Update the **WiFi Credentials** and **Server IP** in the code:
    ```cpp
    const char* ssid = "YOUR_WIFI_NAME";
    const char* password = "YOUR_WIFI_PASSWORD";
    const char* server = "192.168.1.X"; // Your PC's Local IP Address
    ```
4.  Upload the code to your Arduino Uno R4 WiFi.

---

## 🎮 How to Run
1.  Ensure XAMPP (Apache & MySQL) is running.
2.  Power on your Arduino device.
3.  Open the dashboard in your browser:
    [http://localhost/curtain_call/frontend/index.html](http://localhost/curtain_call/frontend/index.html)

### Voice/AI Commands to Try:
- *"Open curtains to 50%"*
- *"Close the curtains"*
- *"It's too bright in here"*
- *"I want some privacy"*
