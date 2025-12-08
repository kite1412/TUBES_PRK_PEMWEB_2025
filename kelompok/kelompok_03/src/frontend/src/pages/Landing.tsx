import { Link } from 'react-router-dom';

const Landing = () => {
  return (
    <div>
      {/* 1. Hero Section */}
      <section className="flex flex-col items-center justify-center px-6 py-20 text-center bg-[#043873] text-white">
        <h1 className="mb-4 text-5xl font-bold leading-tight">Membangun Masa Depan <br/> Bersama Organisasi Kami</h1>
        <p className="max-w-2xl mb-8 text-lg text-gray-300">
          Wadah kolaborasi dan inovasi mahasiswa untuk menciptakan dampak positif bagi lingkungan sekitar.
        </p>
        <button className="px-8 py-4 font-bold text-white transition bg-blue-500 rounded-lg hover:bg-blue-600">
          Gabung Sekarang &rarr;
        </button>
      </section>

      {/* 2. Promo Organisasi */}
      <section className="px-6 py-16 bg-white">
        <div className="container mx-auto">
          <div className="grid gap-8 md:grid-cols-2">
            <div className="flex items-center justify-center bg-gray-100 rounded-lg h-60">
              <span className="text-gray-400">[Image Promo / Dokumentasi]</span>
            </div>
            <div className="flex flex-col justify-center">
              <h2 className="mb-4 text-3xl font-bold text-gray-900">Kenapa Harus Bergabung?</h2>
              <p className="text-gray-600">
                Kami menyediakan platform untuk mengembangkan soft skill, networking yang luas, 
                dan pengalaman manajemen proyek yang nyata.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* 3. Visi Misi */}
      <section className="px-6 py-16 bg-[#FFE492]">
        <div className="container mx-auto text-center">
          <h2 className="mb-10 text-3xl font-bold text-gray-900">Visi & Misi</h2>
          <div className="grid gap-8 md:grid-cols-2">
            <div className="p-8 bg-white rounded-lg shadow-md">
              <h3 className="mb-4 text-xl font-bold text-blue-600">Visi</h3>
              <p>Menjadi organisasi kemahasiswaan terdepan yang adaptif dan inovatif di tahun 2025.</p>
            </div>
            <div className="p-8 bg-white rounded-lg shadow-md">
              <h3 className="mb-4 text-xl font-bold text-blue-600">Misi</h3>
              <ul className="text-left list-disc list-inside">
                <li>Mengembangkan potensi anggota.</li>
                <li>Menjalin kerjasama eksternal.</li>
                <li>Melaksanakan pengabdian masyarakat.</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* 4. Struktur Inti (BPH) */}
      <section className="px-6 py-16 bg-white">
        <div className="container mx-auto text-center">
          <h2 className="mb-10 text-3xl font-bold text-gray-900">Pengurus Inti</h2>
          <div className="grid gap-6 md:grid-cols-4">
            {['Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara'].map((role, idx) => (
              <div key={idx} className="p-6 border border-gray-200 rounded-lg hover:shadow-lg">
                <div className="w-24 h-24 mx-auto mb-4 bg-gray-200 rounded-full"></div>
                <h3 className="text-lg font-bold">Nama {role}</h3>
                <p className="text-sm text-blue-500">{role}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* 5. Berita Terbaru (Preview) */}
      <section className="px-6 py-16 bg-gray-50">
        <div className="container mx-auto">
          <div className="flex items-center justify-between mb-8">
            <h2 className="text-3xl font-bold text-gray-900">Berita Terbaru</h2>
            <Link to="/berita" className="text-blue-600 hover:underline">Lihat Semua</Link>
          </div>
          <div className="grid gap-6 md:grid-cols-3">
            {[1, 2, 3].map((item) => (
              <div key={item} className="overflow-hidden bg-white rounded-lg shadow hover:shadow-md">
                <div className="h-40 bg-gray-300"></div>
                <div className="p-6">
                  <h3 className="mb-2 text-xl font-bold">Judul Berita Organisasi {item}</h3>
                  <p className="mb-4 text-sm text-gray-600">Cuplikan singkat berita...</p>
                  <Link to={`/berita/${item}`} className="font-semibold text-blue-500">Baca Selengkapnya</Link>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
};

export default Landing;