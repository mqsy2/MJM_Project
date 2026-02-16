-- ============================================
-- Curtain Call: The Automatic Curtain Opener
-- Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS curtain_call;
USE curtain_call;

-- -------------------------------------------
-- Table: sensor_data
-- Stores readings from DHT11 and photoresistor
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    temperature FLOAT NOT NULL COMMENT 'Temperature in Celsius from DHT11',
    humidity FLOAT NOT NULL COMMENT 'Humidity percentage from DHT11',
    light_level INT NOT NULL COMMENT 'Light level 0-1023 from photoresistor',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------
-- Table: command_queue
-- Pending commands for Arduino to execute
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS command_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action ENUM('OPEN', 'CLOSE', 'STOP') NOT NULL,
    speed INT NOT NULL DEFAULT 255 COMMENT 'Motor speed 0-255',
    source ENUM('MANUAL', 'AI', 'AUTO') NOT NULL DEFAULT 'MANUAL',
    status ENUM('PENDING', 'EXECUTED', 'FAILED') NOT NULL DEFAULT 'PENDING',
    reason TEXT NULL COMMENT 'AI reasoning or user note',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- -------------------------------------------
-- Table: device_logs
-- History of all actions taken
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS device_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action ENUM('OPEN', 'CLOSE', 'STOP') NOT NULL,
    speed INT NOT NULL DEFAULT 255,
    source ENUM('MANUAL', 'AI', 'AUTO') NOT NULL,
    reason TEXT NULL,
    sensor_temperature FLOAT NULL,
    sensor_humidity FLOAT NULL,
    sensor_light INT NULL,
    user_input TEXT NULL COMMENT 'Original user text command if AI-triggered',
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------
-- Table: settings
-- User preferences and system configuration
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('auto_mode', '0', 'Enable automatic curtain control based on sensors'),
('light_threshold_high', '800', 'Close curtains when light level exceeds this'),
('light_threshold_low', '200', 'Open curtains when light level drops below this'),
('temp_threshold_high', '35', 'Close curtains when temperature exceeds this (Celsius)'),
('polling_interval', '5', 'Arduino polling interval in seconds'),
('curtain_status', 'UNKNOWN', 'Current curtain position (OPEN/CLOSED/MOVING/UNKNOWN)'),
('travel_time', '5', 'Curtain travel time in seconds (how long motor runs per open/close)');
