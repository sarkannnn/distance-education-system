-- Distant Təhsil Sistemi Verilənlər Bazası

-- Verilənlər bazasını yarat
CREATE DATABASE IF NOT EXISTS distant
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE distant;

-- İstifadəçilər cədvəli
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM('student', 'instructor', 'admin') DEFAULT 'student',
    is_active BOOLEAN DEFAULT TRUE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    lesson_reminders BOOLEAN DEFAULT TRUE,
    assignment_deadlines BOOLEAN DEFAULT TRUE,
    grade_updates BOOLEAN DEFAULT TRUE,
    language VARCHAR(10) DEFAULT 'az',
    timezone VARCHAR(50) DEFAULT 'Asia/Baku',
    session_timeout INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Müəllimlər cədvəli
CREATE TABLE IF NOT EXISTS instructors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    department VARCHAR(255),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Kateqoriyalar cədvəli
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kurslar cədvəli
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructor_id INT NOT NULL,
    category_id INT,
    total_lessons INT DEFAULT 0,
    status ENUM('active', 'inactive', 'completed', 'draft') DEFAULT 'active',
    initial_students INT DEFAULT 0,
    thumbnail VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Dərslər cədvəli
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT,
    lesson_order INT NOT NULL,
    duration_minutes INT DEFAULT 0,
    video_url VARCHAR(500),
    has_video BOOLEAN DEFAULT FALSE,
    has_pdf BOOLEAN DEFAULT FALSE,
    has_slides BOOLEAN DEFAULT FALSE,
    pdf_url VARCHAR(500),
    slides_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Kurs qeydiyyatı cədvəli
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_lessons INT DEFAULT 0,
    progress_percent INT DEFAULT 0,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (user_id, course_id)
);

-- Dərs tamamlama cədvəli
CREATE TABLE IF NOT EXISTS lesson_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    watch_duration_seconds INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_completion (user_id, lesson_id)
);

-- Canlı dərslər cədvəli
CREATE TABLE IF NOT EXISTS live_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructor_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL,
    max_participants INT DEFAULT 100,
    status ENUM('scheduled', 'live', 'starting-soon', 'ending-soon', 'ended') DEFAULT 'scheduled',
    zoom_link VARCHAR(500),
    webrtc_link VARCHAR(500),
    teams_link VARCHAR(500),
    zoom_available BOOLEAN DEFAULT TRUE,
    webrtc_available BOOLEAN DEFAULT TRUE,
    teams_available BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE
);

-- Canlı dərs iştirakçıları
CREATE TABLE IF NOT EXISTS live_class_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    live_class_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    FOREIGN KEY (live_class_id) REFERENCES live_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (live_class_id, user_id)
);

-- Tapşırıqlar cədvəli
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('assignment', 'quiz') NOT NULL,
    total_points INT DEFAULT 100,
    due_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Tapşırıq təqdimatları
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    user_id INT NOT NULL,
    submission_text TEXT,
    file_url VARCHAR(500),
    score INT,
    feedback TEXT,
    status ENUM('pending', 'submitted', 'graded', 'overdue') DEFAULT 'pending',
    submitted_at TIMESTAMP NULL,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (assignment_id, user_id)
);

-- Arxiv dərsləri (keçmiş dərslər)
CREATE TABLE IF NOT EXISTS archived_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT,
    live_class_id INT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructor_id INT NOT NULL,
    archived_date DATE NOT NULL,
    duration VARCHAR(20),
    video_url VARCHAR(500),
    pdf_url VARCHAR(500),
    slides_url VARCHAR(500),
    has_video BOOLEAN DEFAULT TRUE,
    has_pdf BOOLEAN DEFAULT FALSE,
    has_slides BOOLEAN DEFAULT FALSE,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE
);

-- Cədvəl cədvəli (bu günün dərsləri)
CREATE TABLE IF NOT EXISTS schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    lesson_id INT,
    live_class_id INT,
    title VARCHAR(255) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    schedule_date DATE NOT NULL,
    type ENUM('live', 'recorded', 'assignment') NOT NULL,
    status ENUM('upcoming', 'in-progress', 'completed') DEFAULT 'upcoming',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Statistika cədvəli
CREATE TABLE IF NOT EXISTS user_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_lessons INT DEFAULT 0,
    average_score DECIMAL(5,2) DEFAULT 0,
    completed_assignments INT DEFAULT 0,
    total_assignments INT DEFAULT 0,
    current_streak INT DEFAULT 0,
    highest_score DECIMAL(5,2) DEFAULT 0,
    lowest_score DECIMAL(5,2) DEFAULT 0,
    total_quizzes INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- Həftəlik performans
CREATE TABLE IF NOT EXISTS weekly_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_number INT NOT NULL,
    year INT NOT NULL,
    score DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_weekly (user_id, year, week_number)
);

-- Həftəlik fəaliyyət
CREATE TABLE IF NOT EXISTS weekly_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day_of_week ENUM('Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə', 'Bazar') NOT NULL,
    hours DECIMAL(4,2) DEFAULT 0,
    week_start DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_activity (user_id, day_of_week, week_start)
);

-- Bildirişlər cədvəli
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Aktiv sessiyalar
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    device VARCHAR(255),
    location VARCHAR(255),
    ip_address VARCHAR(45),
    is_current BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- NÜMUNƏ VERİLƏNLƏR (Test məqsədi ilə)
-- ============================================================

-- Kateqoriyalar
INSERT INTO categories (name, description) VALUES
('Texnologiya', 'Texnologiya və proqramlaşdırma kursları'),
('Riyaziyyat', 'Riyaziyyat və analiz kursları'),
('Dillər', 'Xarici dil kursları');

-- Müəllimlər
INSERT INTO instructors (title, name, email, department) VALUES
('Prof.', 'Samir Əliyev', 'samir.aliyev@university.az', 'Kompüter Elmləri'),
('Prof.', 'Elmar Qocayev', 'elmar.gocayev@university.az', 'Riyaziyyat'),
('Dos.', 'Gülkərimova', 'gulkerimova@university.az', 'Xarici Dillər'),
('Dos.', 'Yıldız Karimova', 'yildiz.karimova@university.az', 'Xarici Dillər'),
('Prof.', 'Rəşad Məmmədov', 'rashad.mammadov@university.az', 'Kompüter Elmləri'),
('Dos.', 'Nigar Həsənova', 'nigar.hasanova@university.az', 'Kompüter Elmləri');

-- Test istifadəçisi (şifrə: password123)
INSERT INTO users (student_id, first_name, last_name, email, password, phone) VALUES
('STU2024001', 'Farida', 'Əhmədova', 'farida.ahmadova@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+994 50 123 45 67');

-- Test müəllimi (şifrə: password)
INSERT INTO users (student_id, first_name, last_name, email, password, phone, role) VALUES
('INS2024001', 'Samir', 'Əliyev', 'samir.aliyev@university.az', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+994 50 123 45 67', 'instructor');

-- Müəllimi istifadəçi ilə bağla
UPDATE instructors SET user_id = (SELECT id FROM users WHERE email = 'samir.aliyev@university.az') 
WHERE email = 'samir.aliyev@university.az';

-- Kurslar
INSERT INTO courses (title, description, instructor_id, category_id, total_lessons, status) VALUES
('Süni İntellektə Giriş', 'Süni intellekt və maşın öyrənməsinin əsasları', 1, 1, 24, 'active'),
('Riyazi Analiz', 'Riyazi analiz və hesablama', 2, 2, 30, 'active'),
('İngilis Dili - B1/Başlanğıc', 'Orta səviyyə ingilis dili kursu', 3, 3, 40, 'active'),
('İngilis Dili – B2', 'Yüksək orta səviyyə ingilis dili', 4, 3, 45, 'active'),
('Verilənlər Bazası İdarəetməsi', 'SQL və verilənlər bazası idarəetməsi', 5, 1, 20, 'completed'),
('Alqoritmlər və Verilənlər Strukturları', 'Alqoritmlər və verilənlər strukturlarının əsasları', 6, 1, 35, 'inactive');

-- Kurs qeydiyyatı
INSERT INTO enrollments (user_id, course_id, enrolled_date, completed_lessons, progress_percent, status) VALUES
(1, 1, '2024-09-15', 12, 52, 'active'),
(1, 2, '2024-09-10', 6, 20, 'active'),
(1, 3, '2024-08-20', 27, 68, 'active'),
(1, 4, '2024-10-01', 7, 15, 'active'),
(1, 5, '2024-07-05', 20, 100, 'completed'),
(1, 6, '2024-12-18', 0, 0, 'inactive');

-- Canlı dərslər
INSERT INTO live_classes (course_id, title, instructor_id, start_time, end_time, duration_minutes, max_participants, status, zoom_link, webrtc_link, teams_link, zoom_available, webrtc_available, teams_available) VALUES
(2, 'İnteqrallar və tətbiqləri', 2, '2024-12-19 10:00:00', '2024-12-19 11:30:00', 90, 100, 'live', 'https://zoom.us/j/123456789', '#webrtc', NULL, TRUE, TRUE, FALSE),
(1, 'Neural Network Architecture', 1, '2024-12-19 14:00:00', '2024-12-19 15:30:00', 90, 80, 'starting-soon', 'https://zoom.us/j/987654321', '#webrtc', '#teams', TRUE, TRUE, TRUE),
(4, 'Advanced Grammar Workshop', 4, '2024-12-19 11:00:00', '2024-12-19 12:30:00', 90, 50, 'ending-soon', 'https://zoom.us/j/456789123', NULL, '#teams', TRUE, FALSE, TRUE);

-- Tapşırıqlar
INSERT INTO assignments (course_id, title, description, type, total_points, due_date) VALUES
(2, 'İntegrallar üzrə praktiki tapşırıq', 'Müəyyən və qeyri-müəyyən inteqrallar üzrə 10 məsələnin həlli', 'assignment', 100, '2024-12-25 23:59:00'),
(3, 'İngilis dili - Başlanğıc səviyyə Quiz', 'Grammar və vocabulary üzrə 25 sual', 'quiz', 50, '2024-12-22 18:00:00'),
(1, 'Machine Learning Layihəsi', 'Neural network modelinin yaradılması və təlimi', 'assignment', 200, '2024-12-18 23:59:00'),
(4, 'Qrammatika Final Quiz', 'Son qrammatika testi', 'quiz', 100, '2024-12-15 15:00:00'),
(6, 'Verilənlər strukturları analizi', 'Alqoritm analizləri', 'assignment', 150, '2024-12-10 23:59:00'),
(2, 'Riyazi Analiz - Midterm Quiz', 'Yarı final quiz', 'quiz', 100, '2024-12-05 12:00:00');

-- Tapşırıq təqdimatları
INSERT INTO assignment_submissions (assignment_id, user_id, status, score, submitted_at, graded_at) VALUES
(1, 1, 'pending', NULL, NULL, NULL),
(2, 1, 'pending', NULL, NULL, NULL),
(3, 1, 'overdue', NULL, NULL, NULL),
(4, 1, 'graded', 88, '2024-12-15 14:30:00', '2024-12-16 10:00:00'),
(5, 1, 'submitted', NULL, '2024-12-10 22:00:00', NULL),
(6, 1, 'graded', 92, '2024-12-05 11:45:00', '2024-12-06 09:00:00');

-- Arxiv dərsləri
INSERT INTO archived_lessons (course_id, title, description, instructor_id, archived_date, duration, has_video, has_pdf, has_slides, views) VALUES
(1, 'Neural Network Architecture və Deep Learning', 'Neural şəbəkələrin strukturu və dərin öyrənmə prinsipləri', 1, '2024-12-15', '1:45:30', TRUE, TRUE, TRUE, 124),
(2, 'İnteqrallar və tətbiqləri - Praktiki nümunələr', 'Müəyyən və qeyri-müəyyən inteqralların həlli', 2, '2024-12-14', '1:30:15', TRUE, TRUE, FALSE, 98),
(4, 'Advanced Grammar Workshop - Past Perfect', 'Keçmiş zamanların istifadəsi və praktika', 4, '2024-12-13', '1:15:00', TRUE, TRUE, TRUE, 156),
(1, 'Machine Learning Algorithms', 'Supervised və unsupervised öyrənmə alqoritmləri', 1, '2024-12-10', '2:00:00', TRUE, TRUE, TRUE, 203),
(6, 'Verilənlər Strukturları - Ağaclar və Qraflar', 'Tree və graph strukturlarının ətraflı təhlili', 6, '2024-12-08', '1:40:20', TRUE, FALSE, TRUE, 87),
(3, 'Vocabulary Building Strategies', 'Lüğət zənginləşdirmə üsulları və praktikalar', 3, '2024-12-05', '1:20:00', TRUE, TRUE, FALSE, 142),
(2, 'Törəmələr və İntegrallar arasında əlaqə', 'Diferensial və inteqral hesablamanın əlaqəsi', 2, '2024-12-01', '1:35:45', TRUE, TRUE, TRUE, 115),
(1, 'Data Preprocessing və Feature Engineering', 'Verilənlərin hazırlanması və xüsusiyyət mühəndisliyi', 1, '2024-11-28', '1:50:00', TRUE, TRUE, TRUE, 178);

-- Statistika
INSERT INTO user_statistics (user_id, total_lessons, average_score, completed_assignments, total_assignments, current_streak, highest_score, lowest_score, total_quizzes) VALUES
(1, 107, 87.5, 24, 28, 12, 98, 65, 18);


-- Həftəlik performans
INSERT INTO weekly_performance (user_id, week_number, year, score) VALUES
(1, 1, 2024, 75),
(1, 2, 2024, 82),
(1, 3, 2024, 78),
(1, 4, 2024, 88),
(1, 5, 2024, 85),
(1, 6, 2024, 92),
(1, 7, 2024, 89),
(1, 8, 2024, 94);

-- Həftəlik fəaliyyət
INSERT INTO weekly_activity (user_id, day_of_week, hours, week_start) VALUES
(1, 'Bazar ertəsi', 3.5, '2024-12-16'),
(1, 'Çərşənbə axşamı', 4.2, '2024-12-16'),
(1, 'Çərşənbə', 2.8, '2024-12-16'),
(1, 'Cümə axşamı', 5.1, '2024-12-16'),
(1, 'Cümə', 3.9, '2024-12-16'),
(1, 'Şənbə', 2.5, '2024-12-16'),
(1, 'Bazar', 1.2, '2024-12-16');

-- Sessiyalar
INSERT INTO user_sessions (user_id, session_token, device, location, ip_address, is_current) VALUES
(1, 'abc123def456', 'Chrome - Windows 10', 'Bakı, Azərbaycan', '192.168.1.1', TRUE),
(1, 'ghi789jkl012', 'Safari - iPhone 14', 'Bakı, Azərbaycan', '192.168.1.2', FALSE),
(1, 'mno345pqr678', 'Firefox - MacBook Pro', 'Bakı, Azərbaycan', '192.168.1.3', FALSE);

-- Bildirişlər
INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
(1, 'Yeni dərs əlavə edildi', 'Süni İntellekt kursuna yeni dərs əlavə edildi', 'info', FALSE),
(1, 'Tapşırıq son tarixi', 'İntegrallar tapşırığının son tarixi yaxınlaşır', 'warning', FALSE),
(1, 'Quiz nəticəsi', 'Qrammatika quizindən 88% bal aldınız', 'success', TRUE);
