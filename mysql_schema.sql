-- ============================================================
-- EBAUB CSE Gallery — MySQL schema (for XAMPP / phpMyAdmin)
-- 1. Open http://localhost/phpmyadmin
-- 2. Create database:  cse_gallery
-- 3. Select it -> Import -> choose this file -> Go
-- 4. In includes/config.php switch to the MySQL block
-- ============================================================

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'Other',
  event_date DATE NOT NULL,
  reg_deadline DATE NULL,
  description TEXT,
  custom_roles TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type ENUM('image','video') NOT NULL,
  sort_order INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin -> username: admin , password: ebaub123
INSERT INTO admins (username, password) VALUES
('admin', '$2y$12$kniwVqQ5v.IZPlyB.T1Ja.engMsNrOaxkqd8i2wPEC1sZDhjbuJJ2');

-- The code is fully portable: it works on both SQLite (demo) and MySQL (XAMPP)
-- without changes — timestamps are generated in PHP.

CREATE TABLE registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'student',
  name VARCHAR(150) NOT NULL,
  semester VARCHAR(30) NULL,
  batch VARCHAR(30) NULL,
  reg_no VARCHAR(50) NULL,
  designation VARCHAR(100) NULL,
  mobile VARCHAR(30) NOT NULL,
  reference VARCHAR(150) NULL,
  event_role VARCHAR(100) NULL,
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  image VARCHAR(255) NULL,
  sort_order INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
