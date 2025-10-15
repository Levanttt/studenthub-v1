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