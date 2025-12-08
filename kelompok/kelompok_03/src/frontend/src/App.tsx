import { BrowserRouter as Router, Routes, Route, Link } from 'react-router-dom';
import Landing from './pages/Landing';
import Struktur from './pages/Struktur';
import Kegiatan from './pages/Kegiatan';
import Berita from './pages/Berita';
import BeritaDetail from './pages/BeritaDetail';

function App() {
  return (
    <Router>
      <div className="min-h-screen font-sans text-gray-800 bg-white">
        {/* Navbar Sederhana (Sesuai style Whitepace) */}
        <nav className="flex items-center justify-between px-6 py-4 bg-[#FFE492] shadow-sm">
          <div className="text-xl font-bold text-gray-900">ORGANISASI</div>
          <div className="space-x-6 font-medium">
            <Link to="/" className="hover:text-blue-600">Beranda</Link>
            <Link to="/struktur" className="hover:text-blue-600">Struktur</Link>
            <Link to="/kegiatan" className="hover:text-blue-600">Kegiatan</Link>
            <Link to="/berita" className="hover:text-blue-600">Berita</Link>
          </div>
          <button className="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            Login
          </button>
        </nav>

        {/* Content */}
        <Routes>
          <Route path="/" element={<Landing />} />
          <Route path="/struktur" element={<Struktur />} />
          <Route path="/kegiatan" element={<Kegiatan />} />
          <Route path="/berita" element={<Berita />} />
          <Route path="/berita/:id" element={<BeritaDetail />} />
        </Routes>
        
        {/* Footer Sederhana */}
        <footer className="py-8 mt-12 text-center text-white bg-[#043873]">
          <p>© 2025 Kelompok 03 - Pemrograman Web</p>
        </footer>
      </div>
    </Router>
  );
}

export default App;