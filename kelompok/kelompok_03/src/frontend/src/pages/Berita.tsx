import { Link } from 'react-router-dom';
import type { Berita as BeritaType } from '../types';

const Berita = () => {
  // Mock Data sesuai ERD
  const newsList: BeritaType[] = Array.from({ length: 6 }).map((_, i) => ({
    id: i + 1,
    judul: `Artikel Berita Organisasi ${i + 1}`,
    created_at: '2025-12-08 10:00:00',
    isi: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ini adalah contoh isi berita yang tersimpan di kolom text database.',
    thumbnail: '' // Kosongkan dulu jika belum ada gambar
  }));

  return (
    <div className="container px-6 py-12 mx-auto">
      <div className="mb-10 text-center">
        <h1 className="text-4xl font-bold text-gray-900">Kabar Terbaru</h1>
        <p className="mt-2 text-gray-600">Update informasi seputar aktivitas kami.</p>
      </div>

      <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
        {newsList.map((news) => (
          <div key={news.id} className="flex flex-col overflow-hidden bg-white border border-gray-100 rounded-lg shadow-sm hover:shadow-lg transition">
            {/* Thumbnail Logic */}
            <div className="h-48 bg-gray-200">
              {news.thumbnail ? (
                <img src={news.thumbnail} alt={news.judul} className="object-cover w-full h-full" />
              ) : (
                <div className="flex items-center justify-center w-full h-full text-gray-400">No Image</div>
              )}
            </div>
            
            <div className="flex flex-col flex-grow p-6">
              <span className="mb-2 text-xs font-bold tracking-wide text-blue-500 uppercase">Berita</span>
              <h3 className="mb-2 text-xl font-bold text-gray-900">
                <Link to={`/berita/${news.id}`} className="hover:text-blue-600">
                  {news.judul}
                </Link>
              </h3>
              <p className="flex-grow mb-4 text-gray-600 line-clamp-3">{news.isi}</p>
              <div className="flex items-center justify-between pt-4 mt-auto border-t">
                {/* Format tanggal sederhana */}
                <span className="text-sm text-gray-400">
                  {new Date(news.created_at).toLocaleDateString('id-ID')}
                </span>
                <Link to={`/berita/${news.id}`} className="font-semibold text-blue-600 hover:underline">
                  Baca &rarr;
                </Link>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Berita;