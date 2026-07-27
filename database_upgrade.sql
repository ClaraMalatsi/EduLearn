USE edulearn;

CREATE TABLE IF NOT EXISTS quiz_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A','B','C','D') NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE(attempt_id, question_id),
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS learner_lecture_progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lecture_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, lecture_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (lecture_id) REFERENCES lectures(lecture_id) ON DELETE CASCADE
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS qualification VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(150) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL;
