-- Create database
CREATE DATABASE IF NOT EXISTS studenthub;
USE studenthub;

-- Table: users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nim VARCHAR(20),
    email VARCHAR(255) UNIQUE NOT NULL,
    profile_picture VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    role ENUM('student','mitra_industri','admin') DEFAULT 'student',
    name VARCHAR(100),
    photo VARCHAR(255),
    university VARCHAR(100),
    major VARCHAR(100),
    bio TEXT,
    phone VARCHAR(20),
    specializations TEXT,
    cv_file_path VARCHAR(255),
    linkedin VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    company_name VARCHAR(255),
    position VARCHAR(100),
    company_website VARCHAR(255),
    semester INT,
    phone_number VARCHAR(20),
    eligibility_status VARCHAR(20) DEFAULT 'pending'
    verification_status ENUM('verified','unverified','pending') DEFAULT 'pending'
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
    category VARCHAR(100),
    status ENUM('completed','in-progress','prototype') DEFAULT 'completed',
    start_date DATE,
    end_date DATE,
    project_year YEAR,
    project_duration VARCHAR(50),
    project_type ENUM('academic','personal','freelance','competition','internship') DEFAULT 'personal',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    certificate_path VARCHAR(500),
    certificate_credential_id VARCHAR(255),
    certificate_credential_url TEXT,
    certificate_issue_date DATE,
    certificate_expiry_date DATE,
    certificate_description TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table: certificates
CREATE TABLE certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    project_id INT,
    title VARCHAR(255) NOT NULL,
    organization VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE,
    file_path VARCHAR(500),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    credential_id VARCHAR(255),
    credential_url VARCHAR(500),
    certificate_url VARCHAR(500),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- Table: project_categories
CREATE TABLE project_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    value VARCHAR(50),
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: project_images
CREATE TABLE project_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Table: profile_views
CREATE TABLE profile_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    viewer_id INT,
    viewed_user_id INT,
    viewer_type ENUM('stakeholder','student','admin','other') NOT NULL,
    viewer_info VARCHAR(45),
    additional_info TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (viewed_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table: project_likes
CREATE TABLE project_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    mitra_industri_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (mitra_industri_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table: skills
CREATE TABLE skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    skill_type ENUM('technical','soft','tool') DEFAULT 'technical',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: project_skills
CREATE TABLE project_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    skill_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_skill (project_id, skill_id)
);

-- Table: skill_logs
CREATE TABLE skill_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    skill_id INT,
    skill_name VARCHAR(100) NOT NULL,
    action ENUM('added','edited','deleted') NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(100),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table: specializations
CREATE TABLE specializations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert initial skills data
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

-- Insert initial specializations data
INSERT INTO specializations (id, name)
VALUES
(1, 'Frontend Development'),
(2, 'Backend Development'),
(3, 'Full Stack Development'),
(4, 'Mobile Development (Android/iOS)'),
(5, 'QA Tester'),
(6, 'UI/UX Design'),
(7, 'Data Analysis'),
(8, 'Data Science'),
(9, 'Machine Learning / AI'),
(10, 'Cybersecurity'),
(11, 'DevOps / Cloud Computing'),
(12, 'Game Programmer'),
(13, 'Game Designer'),
(14, 'Digital Marketing'),
(15, 'SEO / SEM Specialist'),
(16, 'Social Media Management'),
(17, 'Content Strategy'),
(18, 'E-commerce Specialist'),
(19, 'Financial Analyst'),
(20, 'Investment Banking'),
(21, 'Risk Management'),
(22, 'Business Development'),
(23, 'Project Management'),
(24, 'Human Resources (HR)'),
(25, 'Supply Chain Management'),
(26, 'Public Relations'),
(27, 'Corporate Communication'),
(28, 'Content Writing'),
(29, 'Graphic Design'),
(30, 'Illustrator'),
(31, '2D Artist'),
(32, 'Photography / Videography'),
(33, 'Investment Analyst'),
(34, 'Professional Banker'),
(35, 'Broker'),
(36, 'Risk Analyst'),
(37, 'Treasurer'),
(38, 'Auditor'),
(39, 'Marketing Analyst'),
(40, 'Growth Manager'),
(41, 'Human Resource Development'),
(42, 'Accountant'),
(43, 'Account Officer'),
(44, 'Power Systems Engineering'),
(45, 'Electronics'),
(46, 'Telecommunications'),
(47, 'Control Systems / Automation'),
(48, 'Quality Management'),
(49, 'Work System Design Ergonomics'),
(50, 'Optimization Operations Research'),
(51, 'Manufacturing Production'),
(52, 'Industrial Data Analysis'),
(53, 'Public Relations (PR)'),
(54, 'Marketing Communication / IMC'),
(55, 'Journalism'),
(56, 'Broadcasting'),
(57, 'Advertising'),
(58, 'Media Studies / Media Analysis'),
(59, 'Digital Communication Strategy'),
(60, 'Branding Identity Design'),
(61, 'Animation (2D/3D)'),
(63, 'Motion Graphics'),
(65, 'Typography'),
(66, 'Packaging Design'),
(67, 'Art Direction'),
(85, 'Clinical Psychology'),
(86, 'Counseling Psychology'),
(87, 'Industrial/Organizational Psychology'),
(88, 'Educational Psychology'),
(89, 'Developmental Psychology'),
(90, 'Social Psychology'),
(91, 'Forensic Psychology'),
(92, 'Cognitive Psychology'),
(93, 'Elementary Curriculum Development'),
(94, 'Educational Technology'),
(95, 'Special Needs Education'),
(96, 'Literacy Education'),
(97, 'Math Education'),
(98, 'Science Education'),
(99, 'Classroom Management'),
(100, 'Criminal Law'),
(101, 'Civil Law / Private Law'),
(102, 'Business Law / Corporate Law'),
(103, 'International Law'),
(104, 'Constitutional Law'),
(105, 'Administrative Law'),
(106, 'Environmental Law'),
(107, 'Human Rights Law'),
(108, 'Intellectual Property (IP) Law'),
(111, 'Business Analysis');

-- Create indexes 
CREATE INDEX idx_projects_student_id ON projects(student_id);
CREATE INDEX idx_certificates_student_id ON certificates(student_id);
CREATE INDEX idx_certificates_project_id ON certificates(project_id);
CREATE INDEX idx_project_images_project_id ON project_images(project_id);
CREATE INDEX idx_profile_views_viewed_user_id ON profile_views(viewed_user_id);
CREATE INDEX idx_project_likes_project_id ON project_likes(project_id);
CREATE INDEX idx_project_skills_project_id ON project_skills(project_id);
CREATE INDEX idx_project_skills_skill_id ON project_skills(skill_id);