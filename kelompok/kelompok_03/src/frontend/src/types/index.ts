// File: src/types/index.ts

// --- HAPUS IMPORT DI SINI, BIARKAN KOSONG ---

// Sesuai tabel 'admin'
export interface Admin {
  id: number;
  username: string;
  created_at: string;
}

// Sesuai tabel 'anggota'
export interface Anggota {
  id: number;
  nama: string;
  npm: string;
  username: string;
  foto?: string;
  created_at: string;
}

// Sesuai tabel 'departemen'
export interface Departemen {
  id: number;
  nama: string;
  deskripsi: string;
  created_at: string;
}

// Sesuai tabel 'divisi'
export interface Divisi {
  id: number;
  departemen_id: number;
  nama: string;
  deskripsi: string;
  created_at: string;
}

// Sesuai tabel 'kegiatan'
export interface Kegiatan {
  id: number;
  judul: string;
  konten: string;
  target: 'semua' | 'departemen' | 'divisi';
  departemen_id?: number;
  divisi_id?: number;
  tanggal: string;
  created_at: string;
}

// Sesuai tabel 'berita'
export interface Berita {
  id: number;
  judul: string;
  isi: string;
  thumbnail?: string;
  created_at: string;
}