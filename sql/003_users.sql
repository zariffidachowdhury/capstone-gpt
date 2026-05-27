-- Capstone GPT Users Schema
-- Run after sql/002_feedback.sql
-- Example: mysql -u root capstone_gpt < sql/003_users.sql

USE capstone_gpt;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    major VARCHAR(100),
    project_idea TEXT,
    teammates JSON,
    course_section ENUM('CSE448', 'CSE449') DEFAULT 'CSE449',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
