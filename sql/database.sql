-- Create database
CREATE DATABASE IF NOT EXISTS studenthub;
USE studenthub;

-- Table: users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'stakeholder') NOT NULL,
    name VARCHAR(100),
    photo VARCHAR(255),
    university VARCHAR(100),
    major VARCHAR(100),
    bio TEXT,
    linkedin VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: projects  
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    video_url VARCHAR(255),
    github_url VARCHAR(255),
    figma_url VARCHAR(255),
    demo_url VARCHAR(255),
    skills TEXT,
    collaborators TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE project_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    skill_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_skill (project_id, skill_id)
);

INSERT INTO skills (name) VALUES 
('PHP'), ('JavaScript'), ('Python'), ('Java'), ('HTML'), ('CSS'),
('React'), ('Vue.js'), 'Laravel'), ('MySQL'), ('PostgreSQL'),
('UI Design'), ('UX Design'), ('Figma'), ('Adobe XD'),
('Node.js'), 'Express.js'), ('Git'), ('REST API'), ('Mobile Development');

-- Table: skills dengan classification
CREATE TABLE skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    skill_type ENUM('technical', 'soft', 'tool') NOT NULL DEFAULT 'technical',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: project_skills (many-to-many relationship)
CREATE TABLE project_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    skill_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_skill (project_id, skill_id)
);

INSERT INTO skills (name, skill_type) VALUES 
-- Technical Skills
('PHP', 'technical'), ('JavaScript', 'technical'), ('Python', 'technical'), ('Java', 'technical'), 
('HTML', 'technical'), ('CSS', 'technical'), ('React', 'technical'), ('Vue.js', 'technical'), 
('Laravel', 'technical'), ('MySQL', 'technical'), ('PostgreSQL', 'technical'), ('Node.js', 'technical'),
('Express.js', 'technical'), ('REST API', 'technical'), ('Mobile Development', 'technical'),
('TypeScript', 'technical'), ('React Native', 'technical'), ('Flutter', 'technical'), ('Angular', 'technical'),
('Django', 'technical'), ('Spring Boot', 'technical'), ('Data Analysis', 'technical'), ('Machine Learning', 'technical'),
('Cybersecurity', 'technical'), ('DevOps', 'technical'), ('Cloud Computing', 'technical'), ('UI/UX Design', 'technical'),

-- Soft Skills
('Leadership', 'soft'), ('Communication', 'soft'), ('Problem Solving', 'soft'), ('Teamwork', 'soft'),
('Project Management', 'soft'), ('Critical Thinking', 'soft'), ('Creativity', 'soft'), ('Time Management', 'soft'),
('Adaptability', 'soft'), ('Public Speaking', 'soft'), ('Negotiation', 'soft'), ('Emotional Intelligence', 'soft'),

-- Tools
('Figma', 'tool'), ('Adobe XD', 'tool'), ('Git', 'tool'), ('Visual Studio Code', 'tool'), ('IntelliJ IDEA', 'tool'),
('Android Studio', 'tool'), ('Postman', 'tool'), ('Docker', 'tool'), ('Kubernetes', 'tool'), ('AWS', 'tool'),
('Google Cloud', 'tool'), ('Microsoft Azure', 'tool'), ('Jira', 'tool'), ('Slack', 'tool'), ('Trello', 'tool');

ALTER TABLE projects 
ADD COLUMN category ENUM('web', 'mobile', 'data-science', 'design', 'iot', 'game', 'other') NOT NULL DEFAULT 'web',
ADD COLUMN status ENUM('completed', 'in-progress', 'prototype') NOT NULL DEFAULT 'completed',
ADD COLUMN start_date DATE NULL,
ADD COLUMN end_date DATE NULL;
ALTER TABLE projects 
ADD COLUMN project_year YEAR NULL,
ADD COLUMN project_duration VARCHAR(50) NULL;

ALTER TABLE projects 
MODIFY COLUMN project_type ENUM('academic', 'personal', 'freelance', 'competition', 'internship') NOT NULL DEFAULT 'personal';

CREATE TABLE project_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER email;