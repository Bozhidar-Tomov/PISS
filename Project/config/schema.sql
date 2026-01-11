-- Create database if not exists
CREATE DATABASE IF NOT EXISTS audience_reaction_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE audience_reaction_app;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(32) PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    role ENUM('active participant', 'passive viewer', 'admin') DEFAULT 'active participant',
    points INT DEFAULT 0,
    gender VARCHAR(20),
    password VARCHAR(255),
    categories VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Active users table
CREATE TABLE IF NOT EXISTS active_users (
    user_id VARCHAR(32) PRIMARY KEY,
    last_seen INT,
    user_agent VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Commands table
CREATE TABLE IF NOT EXISTS commands (
    id VARCHAR(32) PRIMARY KEY,
    command_type VARCHAR(50),
    command_data JSON,
    is_active BOOLEAN DEFAULT FALSE,
    timestamp INT
);

-- Mic results table
CREATE TABLE IF NOT EXISTS mic_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(32),
    command_id VARCHAR(32),
    intensity TINYINT,
    volume INT,
    reaction_accuracy INT,
    timestamp INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (command_id) REFERENCES commands(id) ON DELETE CASCADE,
    UNIQUE KEY user_command_unique (user_id, command_id)
);

-- Point transfers log
CREATE TABLE IF NOT EXISTS point_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id VARCHAR(32),
    to_user_id VARCHAR(32),
    amount INT,
    message TEXT,
    timestamp INT,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255)
);

-- Insert initial setting for simulated audience
INSERT INTO settings (setting_key, setting_value) VALUES ('sim_audience_on', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value; 