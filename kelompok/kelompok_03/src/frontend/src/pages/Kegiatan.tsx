import type { Kegiatan as KegiatanType } from '../types'; // Import tipe data

const Kegiatan = () => {
  // Mock Data sesuai struktur ERD
  const events: KegiatanType[] = [
    { 
      id: 1, 
      judul: 'Webinar Teknologi 2025', 
      target: 'semua',
      tanggal: '2025-12-20 09:00:00', 
      konten: 'Pembahasan mengenai AI di masa depan.',
      created_at: '2025-12-01'
    },
    { 
      id: 2, 
      judul: 'Bakti Sosial Raya', 
      target: 'semua',
      tanggal: '2026-01-15 08:00:00', 
      konten: 'Kegiatan sosial di desa binaan.',
      created_at: '2025-12-05'
    },
  ];

  // Helper untuk format tanggal
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return {
      day: date.getDate(),
      month: date.toLocaleString('id-ID', { month: 'short' }).toUpperCase(),
      full: date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
    };
  };

  return (
    <div className="container px-6 py-12 mx-auto">
      <h1 className="mb-8 text-4xl font-bold text-gray-900">Agenda Kegiatan</h1>
      
      <div className="flex flex-col gap-6">
        {events.map((event) => {
          const dateObj = formatDate(event.tanggal);
          return (
            <div key={event.id} className="flex flex-col p-6 transition bg-white border rounded-lg shadow-sm md:flex-row hover:shadow-md border-gray-100">
              <div className="flex-shrink-0 mb-4 md:mb-0 md:mr-6">
                <div className="flex flex-col items-center justify-center w-20 h-20 text-blue-600 bg-blue-100 rounded-lg">
                  <span className="text-xl font-bold">{dateObj.day}</span>
                  <span className="text-xs uppercase">{dateObj.month}</span>
                </div>
              </div>
              <div>
                {/* Menggunakan variable 'judul' sesuai ERD */}
                <h3 className="text-xl font-bold text-gray-800">{event.judul}</h3>
                <p className="mb-2 text-sm text-gray-500">{dateObj.full}</p>
                {/* Menggunakan variable 'konten' sesuai ERD */}
                <p className="text-gray-600">{event.konten}</p>
                <div className="mt-3">
                  <span className="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">
                    Target: {event.target}
                  </span>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default Kegiatan;