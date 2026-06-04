CREATE TABLE IF NOT EXISTS chatbot_feedback (
    FeedbackID INT AUTO_INCREMENT PRIMARY KEY,
    ChatID VARCHAR(180) NULL,
    UserMessage MEDIUMTEXT NULL,
    BotReply MEDIUMTEXT NULL,
    Rating VARCHAR(20) NOT NULL,
    Comment MEDIUMTEXT NULL,
    Intent VARCHAR(100) NULL,
    Source VARCHAR(50) NOT NULL DEFAULT 'chatbot_ui',
    UserAgent VARCHAR(500) NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chatbot_feedback_chatid (ChatID),
    INDEX idx_chatbot_feedback_rating (Rating),
    INDEX idx_chatbot_feedback_created (CreatedAt)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
