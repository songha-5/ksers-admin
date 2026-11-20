-- KSERS 2027 Scientific Program / Glance tables
-- Run in Railway MySQL → Database → Data → Query

CREATE TABLE IF NOT EXISTS program_session (
  seq int NOT NULL AUTO_INCREMENT,
  year smallint NOT NULL,
  session_id varchar(40) NOT NULL,
  category varchar(80) NOT NULL DEFAULT '',
  session_code varchar(40) NOT NULL DEFAULT '',
  session_title varchar(255) NOT NULL,
  session_date date NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  room varchar(80) NOT NULL DEFAULT '',
  bg_color varchar(20) NOT NULL DEFAULT '',
  row_span tinyint NOT NULL DEFAULT 1,
  col_span tinyint NOT NULL DEFAULT 1,
  has_link tinyint NOT NULL DEFAULT 1,
  sort_no int NOT NULL DEFAULT 0,
  is_public tinyint NOT NULL DEFAULT 1,
  reg_date datetime DEFAULT NULL,
  PRIMARY KEY (seq),
  UNIQUE KEY uq_program_session_year_id (year, session_id),
  KEY idx_program_session_year (year, session_date, sort_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS program_chair (
  seq int NOT NULL AUTO_INCREMENT,
  session_seq int NOT NULL,
  name varchar(120) NOT NULL,
  affiliation varchar(255) NOT NULL DEFAULT '',
  sort_no int NOT NULL DEFAULT 0,
  PRIMARY KEY (seq),
  KEY idx_program_chair_session (session_seq, sort_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS program_talk (
  seq int NOT NULL AUTO_INCREMENT,
  session_seq int NOT NULL,
  talk_code varchar(40) NOT NULL DEFAULT '',
  talk_title varchar(500) NOT NULL,
  speaker_name varchar(120) NOT NULL,
  speaker_affiliation varchar(255) NOT NULL DEFAULT '',
  speaker_country varchar(80) NOT NULL DEFAULT '',
  photo_url varchar(500) NOT NULL DEFAULT '',
  cv_url varchar(500) NOT NULL DEFAULT '',
  abstract_url varchar(500) NOT NULL DEFAULT '',
  note varchar(255) NOT NULL DEFAULT '',
  sort_no int NOT NULL DEFAULT 0,
  is_public tinyint NOT NULL DEFAULT 1,
  reg_date datetime DEFAULT NULL,
  PRIMARY KEY (seq),
  KEY idx_program_talk_session (session_seq, sort_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
