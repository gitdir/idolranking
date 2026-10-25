CREATE TABLE `quiz_likes` (
  `quiz_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned DEFAULT NULL
    COMMENT 'Authenticated actor; differs when an Admin acts as a dummy member',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`quiz_idx`, `member_idx`),
  KEY `idx_quiz_likes_member_idx_quiz_idx` (`member_idx`, `quiz_idx`),
  CONSTRAINT `fk_quiz_likes_quiz_idx`
    FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`),
  CONSTRAINT `fk_quiz_likes_member_idx`
    FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_likes_actor_member_idx`
    FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Member Likes on quizzes';
