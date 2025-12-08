import { useParams, Link } from 'react-router-dom';

const BeritaDetail = () => {
  const { id } = useParams();

  return (
    <div className="container max-w-3xl px-6 py-12 mx-auto">
      <Link to="/berita" className="inline-flex items-center mb-6 text-sm text-gray-500 hover:text-blue-600">
        &larr; Kembali ke Berita
      </Link>
      
      <article>
        <div className="w-full h-64 mb-8 bg-gray-300 rounded-lg lg:h-96">
          {/* Placeholder Gambar Header */}
        </div>
        
        <h1 className="mb-4 text-3xl font-bold leading-tight text-gray-900 lg:text-5xl">
          Judul Lengkap Berita Organisasi (ID: {id})
        </h1>
        
        <div className="flex items-center mb-8 space-x-4 text-sm text-gray-500">
          <span>Oleh Admin</span>
          <span>•</span>
          <span>08 Desember 2025</span>
        </div>

        <div className="prose prose-lg text-gray-700 max-w-none">
          <p className="mb-4">
            Ini adalah paragraf pembuka dari berita. Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
            Nullam non urna eros. Vivamus nec côngue elit.
          </p>
          <p className="mb-4">
            Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. 
            Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
          </p>
          <h3 className="mt-8 mb-4 text-2xl font-bold text-gray-900">Sub Judul Penting</h3>
          <p className="mb-4">
            Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, 
            totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.
          </p>
        </div>
      </article>
    </div>
  );
};

export default BeritaDetail;