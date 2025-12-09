CREATE DATABASE IF NOT EXISTS web_organisasi;

USE web_organisasi;

-- Drop tables in correct FK order
DROP TABLE IF EXISTS anggota_jabatan;
DROP TABLE IF EXISTS pengumuman;
DROP TABLE IF EXISTS divisi;
DROP TABLE IF EXISTS departemen;
DROP TABLE IF EXISTS kegiatan;
DROP TABLE IF EXISTS berita;
DROP TABLE IF EXISTS anggota;
DROP TABLE IF EXISTS jabatan;
DROP TABLE IF EXISTS admin;

-- admin
CREATE TABLE admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255),
  password VARCHAR(255),
  created_at DATETIME,
  updated_at DATETIME
);

-- anggota
CREATE TABLE anggota (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(255),
  npm VARCHAR(255),
  username VARCHAR(255),
  password VARCHAR(255),
  foto VARCHAR(255),
  created_at DATETIME,
  updated_at DATETIME
);

-- departemen
CREATE TABLE departemen (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(255),
  deskripsi TEXT,
  created_at DATETIME,
  updated_at DATETIME
);

-- divisi
CREATE TABLE divisi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  departemen_id INT,
  nama VARCHAR(255),
  deskripsi TEXT,
  created_at DATETIME,
  updated_at DATETIME,
  FOREIGN KEY (departemen_id) REFERENCES departemen(id)
);

-- jabatan
CREATE TABLE jabatan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(255)
);

-- anggota_jabatan
CREATE TABLE anggota_jabatan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anggota_id INT,
  jabatan_id INT,
  departemen_id INT NULL,
  divisi_id INT NULL,
  FOREIGN KEY (anggota_id) REFERENCES anggota(id),
  FOREIGN KEY (jabatan_id) REFERENCES jabatan(id),
  FOREIGN KEY (departemen_id) REFERENCES departemen(id),
  FOREIGN KEY (divisi_id) REFERENCES divisi(id)
);

-- pengumuman
CREATE TABLE pengumuman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(255),
  konten TEXT,
  target ENUM('semua', 'departemen', 'divisi'),
  departemen_id INT NULL,
  divisi_id INT NULL,
  tanggal DATETIME,
  created_at DATETIME,
  updated_at DATETIME,
  FOREIGN KEY (departemen_id) REFERENCES departemen(id),
  FOREIGN KEY (divisi_id) REFERENCES divisi(id)
);

-- kegiatan
CREATE TABLE kegiatan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(255),
  deskripsi TEXT,
  tanggal DATETIME,
  created_at DATETIME,
  updated_at DATETIME
);

-- berita
CREATE TABLE berita (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(255),
  isi TEXT,
  thumbnail VARCHAR(255),
  created_at DATETIME,
  updated_at DATETIME
);

-- default jabatan
INSERT INTO jabatan (nama) VALUES
('Ketua Organisasi'),
('Wakil Ketua Organisasi'),
('Bendahara Organisasi'),
('Sekretaris Organisasi'),
('Ketua Departemen'),
('Sekretaris Departemen'),
('Ketua Divisi'),
('Sekretaris Divisi');

INSERT INTO admin (username, password, created_at, updated_at) 
VALUES ('admin', 'admin123', NOW(), NOW());