ALTER TABLE `quizzes`
  ADD COLUMN `title` varchar(255) DEFAULT NULL COMMENT 'Quiz title' AFTER `actor_member_idx`;

UPDATE `quizzes` AS quiz
JOIN `quiz_questions` AS question
  ON question.`quiz_idx` = quiz.`idx`
  AND question.`quiz_version` = quiz.`quiz_version`
  AND question.`question_order` = 1
SET quiz.`title` = LEFT(question.`prompt`, 255)
WHERE quiz.`title` IS NULL OR quiz.`title` = '';

ALTER TABLE `quizzes`
  MODIFY COLUMN `title` varchar(255) NOT NULL COMMENT 'Quiz title';
