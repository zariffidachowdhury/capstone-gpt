-- Capstone GPT CSE 448/449 Topic Refresh
-- Run after sql/001_schema.sql
-- Example: mysql -u root capstone_gpt < sql/005_topics_update.sql

USE capstone_gpt;

UPDATE topics SET topic_name = 'GitLab & Code Management' WHERE topic_name = 'GitLab';
UPDATE topics SET topic_name = 'Agile Practices & Scrum' WHERE topic_name = 'Agile Processes';
UPDATE topics SET topic_name = 'Working Agreement' WHERE topic_name = 'Professionalism';
UPDATE topics SET topic_name = 'Sprint Planning & Backlog' WHERE topic_name = 'Sprint Planning';
UPDATE topics SET topic_name = 'General Course Help' WHERE topic_name = 'General';

INSERT INTO topics (topic_name)
SELECT 'Project Ideas & Selection'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'Project Ideas & Selection');

INSERT INTO topics (topic_name)
SELECT 'Working Agreement'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'Working Agreement');

INSERT INTO topics (topic_name)
SELECT 'Sprint Planning & Backlog'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'Sprint Planning & Backlog');

INSERT INTO topics (topic_name)
SELECT 'Technical Standards'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'Technical Standards');

INSERT INTO topics (topic_name)
SELECT 'Agile Practices & Scrum'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'Agile Practices & Scrum');

INSERT INTO topics (topic_name)
SELECT 'GitLab & Code Management'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'GitLab & Code Management');

INSERT INTO topics (topic_name)
SELECT 'Expo & Video Prep'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'Expo & Video Prep');

INSERT INTO topics (topic_name)
SELECT 'Retrospectives & Reflection'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'Retrospectives & Reflection');

INSERT INTO topics (topic_name)
SELECT 'ABET Outcomes'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'ABET Outcomes');

INSERT INTO topics (topic_name)
SELECT 'General Course Help'
WHERE NOT EXISTS (SELECT 1 FROM topics WHERE topic_name = 'General Course Help');
