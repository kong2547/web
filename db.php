<?php
$host = "localhost";
$db = "web";
$user = "root";
$pass = "";

/*CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(6) UNSIGNED DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `action` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(6) UNSIGNED NOT NULL,

  `email` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('pending','active','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง rooms
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    floor INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    room_name VARCHAR(50) NOT NULL
);
ALTER TABLE rooms ADD COLUMN image VARCHAR(255) NULL AFTER room_name;

-- ตาราง switches (รวมสวิตช์ของห้อง)
CREATE TABLE switches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    switch_name VARCHAR(50),
    board VARCHAR(20),
    gpio_pin INT,
    status ENUM('on','off') DEFAULT 'off',
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- ตาราง schedule (แบบคุณเสนอ)
CREATE TABLE schedule (
    id int AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR (20),
    mode VARCHAR(10),
    weekdays varchar(50) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    start_time TIME,
    end_time TIME,
    enabled TINYINT(1) DEFAULT 1
);

CREATE TABLE switch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    switch_id INT NOT NULL,
    room_id INT NOT NULL,
    status ENUM('on','off') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (switch_id) REFERENCES switches(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);
CREATE TABLE schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_id VARCHAR(50),
  mode VARCHAR(20),      -- daily | weekly | once
  action VARCHAR(10),    -- on | off (สำคัญ)
  weekdays VARCHAR(50) DEFAULT NULL,
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  start_time TIME,
  end_time TIME,
  enabled TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    floor INT NOT NULL UNIQUE,   -- แต่ละชั้นมีแค่ 1 row
    filename VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);








 */

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>