
CREATE DATABASE IF NOT EXISTS tugasku
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tugasku;

CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username    VARCHAR(30)  NOT NULL,
  password    VARCHAR(255) NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS matkul (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  nama        VARCHAR(40)  NOT NULL,
  warna       CHAR(7)      NOT NULL DEFAULT '#5BC0C0',
  PRIMARY KEY (id),
  UNIQUE KEY uq_matkul_user_nama (user_id, nama),
  CONSTRAINT fk_matkul_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  matkul_id   INT UNSIGNED NULL,                        
  judul       VARCHAR(60)  NOT NULL,
  deskripsi   TEXT         NULL,
  deadline    DATE         NOT NULL,
  prioritas   ENUM('Low','Medium','High')  NOT NULL DEFAULT 'Medium',
  status      ENUM('Pending','Selesai')    NOT NULL DEFAULT 'Pending',
  deleted_at  DATETIME     NULL,                        
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_user_deadline (user_id, deadline),
  CONSTRAINT fk_tasks_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_matkul FOREIGN KEY (matkul_id)
    REFERENCES matkul (id) ON DELETE RESTRICT              
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengaturan (
  user_id          INT UNSIGNED NOT NULL,
  pengingat_aktif  TINYINT(1)   NOT NULL DEFAULT 1,
  pengingat_hari   VARCHAR(60)  NOT NULL DEFAULT '0,1,3',
  PRIMARY KEY (user_id),
  CONSTRAINT fk_pengaturan_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
