CREATE TABLE `member_info` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `member_type` tinyint NOT NULL,
  `email` varchar(45) NOT NULL,
  `password` varchar(100) NOT NULL,
  `name_ko` varchar(45) NOT NULL,
  `first_name_eng` varchar(45) NOT NULL,
  `last_name_eng` varchar(45) NOT NULL,
  `affiliation_ko` varchar(45) NOT NULL,
  `affiliation_eng` varchar(45) NOT NULL,
  `mobile` varchar(45) NOT NULL,
  `telephone` varchar(45) NOT NULL DEFAULT '',
  `salutation` varchar(20) NOT NULL DEFAULT '',
  `affiliation_type` varchar(40) NOT NULL DEFAULT '',
  `department` varchar(45) NOT NULL DEFAULT '',
  `agree_email` tinyint NOT NULL DEFAULT 0,
  `user_class` char(2) NOT NULL DEFAULT 'M0',
  `country` char(2) NOT NULL DEFAULT '',
  `year` smallint NOT NULL DEFAULT 2027,
  `reg_date` datetime DEFAULT NULL,
  `is_admin` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`seq`),
  UNIQUE KEY `uq_member_email_year` (`email`,`year`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `participant_info` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `member_seq` int NOT NULL,
  `ksers_member` varchar(20) NOT NULL DEFAULT '',
  `membership_no` varchar(45) NOT NULL DEFAULT '',
  `membership_type` varchar(20) NOT NULL DEFAULT '',
  `category` varchar(20) NOT NULL DEFAULT '',
  `senior_lifetime` tinyint NOT NULL DEFAULT 0,
  `license_no` varchar(45) NOT NULL DEFAULT '',
  `certificate_file` varchar(255) NOT NULL DEFAULT '',
  `certificate_stored` varchar(120) NOT NULL DEFAULT '',
  `dietary` varchar(20) NOT NULL DEFAULT '',
  `pay_confirm` char(1) NOT NULL DEFAULT 'N',
  `cme_apply` char(1) NOT NULL DEFAULT '',
  `year` smallint NOT NULL,
  `reg_date` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`seq`),
  UNIQUE KEY `uq_member_year` (`member_seq`,`year`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `abstract_info` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `member_seq` int NOT NULL,
  `year` smallint NOT NULL,
  `title` varchar(200) NOT NULL,
  `presentation_type` varchar(40) NOT NULL,
  `topic` varchar(100) NOT NULL,
  `authors` varchar(1000) NOT NULL,
  `author_country` int NOT NULL DEFAULT 0,
  `affiliation` varchar(200) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `stored_name` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'received',
  `final_type` varchar(40) NOT NULL DEFAULT 'pending',
  `agree_coauthor` tinyint NOT NULL DEFAULT 1,
  `agree_privacy` tinyint NOT NULL DEFAULT 1,
  `agree_publication` tinyint NOT NULL DEFAULT 1,
  `reg_date` datetime DEFAULT NULL,
  `update_date` datetime DEFAULT NULL,
  PRIMARY KEY (`seq`),
  KEY `idx_member_year` (`member_seq`,`year`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `code` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `majr` tinyint NOT NULL,
  `minr` smallint NOT NULL,
  `name_ko` varchar(120) NOT NULL,
  `name_eng` varchar(120) DEFAULT NULL,
  `iso` char(2) NOT NULL DEFAULT '',
  `dial` varchar(8) NOT NULL DEFAULT '',
  `state` tinyint(1) DEFAULT '1',
  `reg_date` datetime DEFAULT NULL,
  PRIMARY KEY (`seq`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- code rows 4

CREATE TABLE IF NOT EXISTS `event_setting` (
  `year` smallint NOT NULL,
  `title_ko` varchar(120) NOT NULL,
  `title_en` varchar(120) NOT NULL,
  `notice_ko` varchar(1000) NOT NULL DEFAULT '',
  `notice_en` varchar(1000) NOT NULL DEFAULT '',
  `bank_name` varchar(80) NOT NULL DEFAULT '',
  `bank_account` varchar(80) NOT NULL DEFAULT '',
  `bank_holder` varchar(80) NOT NULL DEFAULT '',
  `mail_from` varchar(120) NOT NULL DEFAULT '',
  `is_open` tinyint NOT NULL DEFAULT 0,
  `pay_date_from` date DEFAULT NULL,
  `pay_date_to` date DEFAULT NULL,
  `pay_unlimited` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `fee_item` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `year` smallint NOT NULL,
  `kind` varchar(20) NOT NULL,
  `name_ko` varchar(80) NOT NULL,
  `name_en` varchar(80) NOT NULL,
  `detail_ko` varchar(200) NOT NULL DEFAULT '',
  `detail_en` varchar(200) NOT NULL DEFAULT '',
  `amount` int NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `sort_no` int NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `affiliation_type` varchar(40) NOT NULL DEFAULT '',
  `currency` char(3) NOT NULL DEFAULT 'KRW',
  `fee_code` varchar(40) NOT NULL DEFAULT '',
  `period_code` varchar(20) NOT NULL DEFAULT '',
  `domestic_only` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq`),
  KEY `idx_fee_year` (`year`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `abstract_topic` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `year` smallint NOT NULL,
  `name_ko` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `sort_no` int NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`seq`),
  KEY `idx_topic_year` (`year`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `abstract_author` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `abstract_seq` int NOT NULL,
  `sort_no` int NOT NULL DEFAULT 0,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `affiliation` varchar(200) NOT NULL,
  `department` varchar(120) NOT NULL DEFAULT '',
  `country` int NOT NULL DEFAULT 0,
  `email` varchar(120) NOT NULL DEFAULT '',
  `presenting` tinyint NOT NULL DEFAULT 0,
  `corresponding` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq`),
  KEY `idx_abstract_author` (`abstract_seq`, `sort_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `payment_order` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `member_seq` int NOT NULL,
  `year` smallint NOT NULL,
  `method` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `amount` int NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'KRW',
  `tid` varchar(80) NOT NULL DEFAULT '',
  `reg_date` datetime DEFAULT NULL,
  `paid_date` datetime DEFAULT NULL,
  `refund_date` datetime DEFAULT NULL,
  `refund_account_label` varchar(80) NOT NULL DEFAULT '',
  `refund_mail_sent` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq`),
  KEY `idx_pay_member` (`member_seq`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `payment_line` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `order_seq` int NOT NULL,
  `fee_seq` int NOT NULL,
  `name_ko` varchar(80) NOT NULL,
  `name_en` varchar(80) NOT NULL,
  `amount` int NOT NULL,
  PRIMARY KEY (`seq`),
  KEY `idx_line_order` (`order_seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `member_submission_file` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `member_seq` int NOT NULL,
  `year` smallint NOT NULL,
  `kind` varchar(40) NOT NULL,
  `abstract_seq` int DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL DEFAULT '',
  `file_size` bigint NOT NULL DEFAULT 0,
  `reg_date` datetime NOT NULL,
  `update_date` datetime DEFAULT NULL,
  PRIMARY KEY (`seq`),
  KEY `idx_submission_year_kind` (`year`,`kind`),
  KEY `idx_submission_abstract` (`abstract_seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `program_session` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `year` smallint NOT NULL,
  `session_id` varchar(40) NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT '',
  `session_code` varchar(40) NOT NULL DEFAULT '',
  `session_title` varchar(255) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(80) NOT NULL DEFAULT '',
  `bg_color` varchar(20) NOT NULL DEFAULT '',
  `row_span` tinyint NOT NULL DEFAULT 1,
  `col_span` tinyint NOT NULL DEFAULT 1,
  `has_link` tinyint NOT NULL DEFAULT 1,
  `sort_no` int NOT NULL DEFAULT 0,
  `is_public` tinyint NOT NULL DEFAULT 1,
  `reg_date` datetime DEFAULT NULL,
  PRIMARY KEY (`seq`),
  UNIQUE KEY `uq_program_session_year_id` (`year`, `session_id`),
  KEY `idx_program_session_year` (`year`, `session_date`, `sort_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `program_chair` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `session_seq` int NOT NULL,
  `name` varchar(120) NOT NULL,
  `affiliation` varchar(255) NOT NULL DEFAULT '',
  `sort_no` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq`),
  KEY `idx_program_chair_session` (`session_seq`, `sort_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `program_talk` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `session_seq` int NOT NULL,
  `talk_code` varchar(40) NOT NULL DEFAULT '',
  `talk_title` varchar(500) NOT NULL,
  `speaker_name` varchar(120) NOT NULL,
  `speaker_affiliation` varchar(255) NOT NULL DEFAULT '',
  `speaker_country` varchar(80) NOT NULL DEFAULT '',
  `photo_url` varchar(500) NOT NULL DEFAULT '',
  `cv_url` varchar(500) NOT NULL DEFAULT '',
  `abstract_url` varchar(500) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `sort_no` int NOT NULL DEFAULT 0,
  `is_public` tinyint NOT NULL DEFAULT 1,
  `reg_date` datetime DEFAULT NULL,
  PRIMARY KEY (`seq`),
  KEY `idx_program_talk_session` (`session_seq`, `sort_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `abstract_video_link` (
  `seq` int NOT NULL AUTO_INCREMENT,
  `token` char(64) NOT NULL,
  `file_seq` int NOT NULL,
  `abstract_seq` int NOT NULL,
  `year` smallint NOT NULL,
  `reg_date` datetime NOT NULL,
  PRIMARY KEY (`seq`),
  UNIQUE KEY `uq_video_link_token` (`token`),
  UNIQUE KEY `uq_video_link_file` (`file_seq`),
  KEY `idx_video_link_abstract` (`year`, `abstract_seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
