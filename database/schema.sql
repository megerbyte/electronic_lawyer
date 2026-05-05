-- Electronic Lawyer - Legal Document Generation System
-- MySQL Schema

CREATE DATABASE IF NOT EXISTS electronic_lawyer
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE electronic_lawyer;

-- Stores metadata about each form
CREATE TABLE IF NOT EXISTS forms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  form_code VARCHAR(20) NOT NULL,
  form_title VARCHAR(255) NOT NULL,
  form_category VARCHAR(10) NOT NULL,
  form_filename VARCHAR(255) NOT NULL,
  form_type ENUM('petition','answer','motion','response','order','notice','other') NOT NULL DEFAULT 'other',
  party_role ENUM('plaintiff','defendant','either','court') DEFAULT 'either',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stores each section within a form
CREATE TABLE IF NOT EXISTS form_sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  form_id INT NOT NULL,
  section_letter VARCHAR(5),
  section_title VARCHAR(255),
  section_order INT NOT NULL,
  is_optional BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stores template content blocks within sections
CREATE TABLE IF NOT EXISTS form_content_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  block_order INT NOT NULL,
  paragraph_number INT,
  content_template TEXT NOT NULL,
  is_optional BOOLEAN DEFAULT FALSE,
  choice_group VARCHAR(50),
  FOREIGN KEY (section_id) REFERENCES form_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stores all unique variables/placeholders across forms
CREATE TABLE IF NOT EXISTS form_variables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  variable_name VARCHAR(100) NOT NULL UNIQUE,
  display_label VARCHAR(255) NOT NULL,
  input_type ENUM('text','textarea','date','select','radio','checkbox') DEFAULT 'text',
  is_shared BOOLEAN DEFAULT TRUE,
  hint TEXT,
  validation_regex VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maps which variables appear in which content blocks
CREATE TABLE IF NOT EXISTS block_variables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  block_id INT NOT NULL,
  variable_id INT NOT NULL,
  placeholder_text VARCHAR(255) NOT NULL,
  FOREIGN KEY (block_id) REFERENCES form_content_blocks(id) ON DELETE CASCADE,
  FOREIGN KEY (variable_id) REFERENCES form_variables(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Decision tree nodes for the intake questionnaire
CREATE TABLE IF NOT EXISTS decision_tree_nodes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT,
  question TEXT NOT NULL,
  node_type ENUM('question','result') NOT NULL DEFAULT 'question',
  result_form_id INT,
  result_form_ids TEXT,
  display_order INT DEFAULT 0,
  FOREIGN KEY (parent_id) REFERENCES decision_tree_nodes(id) ON DELETE SET NULL,
  FOREIGN KEY (result_form_id) REFERENCES forms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Answer options for decision tree nodes
CREATE TABLE IF NOT EXISTS decision_tree_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  node_id INT NOT NULL,
  option_text VARCHAR(255) NOT NULL,
  next_node_id INT,
  display_order INT DEFAULT 0,
  FOREIGN KEY (node_id) REFERENCES decision_tree_nodes(id) ON DELETE CASCADE,
  FOREIGN KEY (next_node_id) REFERENCES decision_tree_nodes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User case sessions
CREATE TABLE IF NOT EXISTS user_cases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_token VARCHAR(64) NOT NULL UNIQUE,
  form_id INT,
  status ENUM('intake','variables','complete') DEFAULT 'intake',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User answers to variable questions
CREATE TABLE IF NOT EXISTS user_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_id INT NOT NULL,
  variable_id INT NOT NULL,
  answer_value TEXT,
  FOREIGN KEY (case_id) REFERENCES user_cases(id) ON DELETE CASCADE,
  FOREIGN KEY (variable_id) REFERENCES form_variables(id) ON DELETE CASCADE,
  UNIQUE KEY uq_case_variable (case_id, variable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional section selections by user
CREATE TABLE IF NOT EXISTS user_section_selections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_id INT NOT NULL,
  section_id INT NOT NULL,
  is_included BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (case_id) REFERENCES user_cases(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES form_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
