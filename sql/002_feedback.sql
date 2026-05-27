-- Capstone GPT Feedback Schema
-- Run after sql/001_schema.sql
-- Example: mysql -u root capstone_gpt < sql/002_feedback.sql

USE capstone_gpt;

CREATE TABLE IF NOT EXISTS feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    cas_uid VARCHAR(100) DEFAULT 'test_student',
    rating ENUM('up', 'down') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_feedback_log_user (log_id, cas_uid),
    CONSTRAINT fk_feedback_log
        FOREIGN KEY (log_id) REFERENCES chat_logs(log_id)
        ON DELETE CASCADE
);
