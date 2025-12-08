import type { Departemen, Divisi } from '../types';

const Struktur = () => {
  // Mock Data Departemen & Divisi (Sesuai Screenshot)
  const departemenData: (Departemen & { divisis: Divisi[] })[] = [
    { 
      id: 1, 
      nama: 'Departemen Kominfo', 
      deskripsi: 'Mengelola media sosial dan publikasi.',
      created_at: '',
      divisis: [
        { id: 1, departemen_id: 1, nama: 'Divisi Multimedia', deskripsi: '', created_at: '' },
        { id: 2, departemen_id: 1, nama: 'Divisi Public Relation', deskripsi: '', created_at: '' }
      ]
    },
    { 
      id: 2, 
      nama: 'Departemen PSDM', 
      deskripsi: 'Pengembangan sumber daya anggota.',
      created_at: '',
      divisis: [
        { id: 3, departemen_id: 2, nama: 'Divisi Kaderisasi', deskripsi: '', created_at: '' },
        { id: 4, departemen_id: 2, nama: 'Divisi Pelatihan', deskripsi: '', created_at: '' }
      ]
    },
    { 
      id: 3, 
      nama: 'Departemen Eksternal', 
      deskripsi: 'Hubungan dengan pihak luar kampus.',
      created_at: '',
      divisis: [
        { id: 5, departemen_id: 3, nama: 'Divisi Hubungan Mitra', deskripsi: '', created_at: '' },
        { id: 6, departemen_id: 3, nama: 'Divisi Pengabdian', deskripsi: '', created_at: '' }
      ]
    },
  ];

  return (
    <div className="container px-6 py-12 mx-auto">
      {/* 1. Judul Halaman */}
      <div className="mb-12 text-center">
        <h1 className="mb-4 text-4xl font-bold text-gray-900">Struktur Organisasi</h1>
        <p className="text-gray-600">Mengenal lebih dekat tim di balik layar.</p>
      </div>

      {/* 2. LEVEL 1: KETUA UMUM (Kotak Biru) */}
      <div className="flex flex-col items-center justify-center mb-8">
        <div className="flex flex-col items-center justify-center w-64 p-6 text-white transition transform shadow-lg bg-[#043873] rounded-xl hover:-translate-y-1">
          {/* Avatar Bulat (Opsional) */}
          <div className="w-16 h-16 mb-3 bg-blue-400 rounded-full opacity-50"></div>
          <h3 className="text-xl font-bold">Ketua Umum</h3>
          <p className="text-sm opacity-80">Nama Ketua</p>
        </div>
        {/* Garis Penghubung ke Bawah */}
        <div className="w-1 h-8 bg-gray-300"></div>
      </div>

      {/* 3. LEVEL 2: WAKIL, SEKRETARIS, BENDAHARA (Kotak Putih) */}
      <div className="flex flex-wrap justify-center gap-6 mb-16">
        {/* Wakil Ketua */}
        <div className="flex flex-col items-center w-56 p-6 transition bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md">
          <h3 className="font-bold text-gray-800">Wakil Ketua</h3>
          <p className="text-sm text-gray-500">Nama Wakil</p>
        </div>

        {/* Sekretaris */}
        <div className="flex flex-col items-center w-56 p-6 transition bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md">
          <h3 className="font-bold text-gray-800">Sekretaris</h3>
          <p className="text-sm text-gray-500">Nama Sekretaris</p>
        </div>

        {/* Bendahara */}
        <div className="flex flex-col items-center w-56 p-6 transition bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md">
          <h3 className="font-bold text-gray-800">Bendahara</h3>
          <p className="text-sm text-gray-500">Nama Bendahara</p>
        </div>
      </div>

      {/* 4. LEVEL 3: DEPARTEMEN & DIVISI (Grid Biru Muda) */}
      <div className="pt-8 border-t border-gray-200">
        <h2 className="mb-8 text-2xl font-bold text-gray-800">Departemen & Divisi</h2>
        
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {departemenData.map((dept) => (
            <div key={dept.id} className="p-6 transition bg-blue-50 rounded-xl hover:bg-blue-100">
              <h3 className="mb-2 text-xl font-bold text-[#043873]">{dept.nama}</h3>
              <p className="mb-4 text-sm text-gray-600">{dept.deskripsi}</p>
              
              {/* List Divisi dengan Garis Samping */}
              {dept.divisis.length > 0 && (
                <div className="pl-4 mt-4 border-l-4 border-blue-200">
                  <span className="block mb-2 text-xs font-bold tracking-wider text-gray-400 uppercase">
                    DIVISI:
                  </span>
                  <ul className="space-y-2">
                    {dept.divisis.map((div) => (
                      <li key={div.id} className="flex items-center text-sm font-semibold text-gray-700">
                        <span className="mr-2 text-blue-500">•</span> 
                        {div.nama}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default Struktur;