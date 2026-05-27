-- Capstone GPT Database Schema
-- Run: mysql -u root < sql/001_schema.sql

CREATE DATABASE IF NOT EXISTS capstone_gpt;
USE capstone_gpt;

CREATE TABLE IF NOT EXISTS topics (
    topic_id INT AUTO_INCREMENT PRIMARY KEY,
    topic_name VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS chat_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    cas_uid VARCHAR(100) DEFAULT 'test_student',
    topic_id INT,
    user_query TEXT NOT NULL,
    ai_response TEXT,
    dify_conversation_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(topic_id)
);

-- Seed initial topics
INSERT INTO topics (topic_name) VALUES
    ('GitLab'),
    ('Agile Processes'),
    ('Professionalism'),
    ('Sprint Planning'),
    ('ABET Outcomes'),
    ('General');
