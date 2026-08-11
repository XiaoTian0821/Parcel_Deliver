CREATE DATABASE IF NOT EXISTS parcel_deliver;

USE parcel_deliver;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'rider') NOT NULL DEFAULT 'rider',
    phone VARCHAR(30) NULL,
    avatar VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS riders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    employee_code VARCHAR(50) NOT NULL,
    vehicle_type VARCHAR(100) NULL,
    status ENUM('online', 'offline') NOT NULL DEFAULT 'offline',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    current_latitude DECIMAL(10,7) NULL,
    current_longitude DECIMAL(10,7) NULL,
    last_location_update DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_riders_user_id (user_id),
    UNIQUE KEY uq_riders_employee_code (employee_code),
    KEY idx_riders_status (status),
    CONSTRAINT fk_riders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parcels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_number VARCHAR(60) NOT NULL,
    recipient_name VARCHAR(150) NOT NULL,
    recipient_phone VARCHAR(30) NOT NULL,
    delivery_address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    pickup_address VARCHAR(255) NULL,
    delivery_instructions TEXT NULL,
    status ENUM('pending', 'out_for_delivery', 'delivered', 'failed_delivery') NOT NULL DEFAULT 'pending',
    assigned_rider_id INT UNSIGNED NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parcels_tracking_number (tracking_number),
    KEY idx_parcels_status (status),
    KEY idx_parcels_assigned_rider (assigned_rider_id),
    CONSTRAINT fk_parcels_rider FOREIGN KEY (assigned_rider_id) REFERENCES riders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parcel_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parcel_id INT UNSIGNED NOT NULL,
    rider_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'out_for_delivery', 'delivered', 'failed_delivery') NOT NULL,
    remarks TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_history_parcel_id (parcel_id),
    KEY idx_history_rider_id (rider_id),
    CONSTRAINT fk_history_parcel FOREIGN KEY (parcel_id) REFERENCES parcels (id) ON DELETE CASCADE,
    CONSTRAINT fk_history_rider FOREIGN KEY (rider_id) REFERENCES riders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rider_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rider_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy DECIMAL(10,2) NULL,
    speed DECIMAL(10,2) NULL,
    heading DECIMAL(10,2) NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_locations_rider_id (rider_id),
    KEY idx_locations_recorded_at (recorded_at),
    CONSTRAINT fk_locations_rider FOREIGN KEY (rider_id) REFERENCES riders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parcel_id INT UNSIGNED NOT NULL,
    rider_id INT UNSIGNED NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    remarks TEXT NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_photos_parcel_id (parcel_id),
    KEY idx_photos_rider_id (rider_id),
    CONSTRAINT fk_photos_parcel FOREIGN KEY (parcel_id) REFERENCES parcels (id) ON DELETE CASCADE,
    CONSTRAINT fk_photos_rider FOREIGN KEY (rider_id) REFERENCES riders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action_type VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_user_id (user_id),
    KEY idx_activity_created_at (created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
