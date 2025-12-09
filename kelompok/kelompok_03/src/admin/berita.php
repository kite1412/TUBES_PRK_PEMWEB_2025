<?php
require_once __DIR__ . '/../config/db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error = null;
$berita_list = [];
try {
    $berita_list = db_fetch_all('SELECT * FROM berita ORDER BY created_at DESC');
    $total = count($berita_list);
} catch (Exception $ex) {
    $error = $ex->getMessage();
    $total = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita - Tics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: {
                        primary: '#0466c8',
                        canvas: '#F4F7FE',
                        dark: '#2B3674',
                        muted: '#A3AED0',
                    },
                    boxShadow: {
                        'soft': '0px 18px 40px rgba(112, 144, 176, 0.12)',
                        'card': '0px 2px 15px rgba(112, 144, 176, 0.08)',
                    }
                }
            }
        }
    </script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
        .modal-content { transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .show-modal { opacity: 1; visibility: visible; }
        .show-modal .modal-content { transform: scale(1); }
        .hide-modal { opacity: 0; visibility: hidden; }
        .hide-modal .modal-content { transform: scale(0.95); }
    </style>
</head>
<body class="bg-canvas text-dark h-screen flex overflow-hidden font-sans antialiased">

    <aside class="w-72 bg-white h-full flex flex-col py-8 px-5 z-20 shadow-xl shadow-blue-900/5">
        <div class="flex items-center gap-3 px-4 mb-10">
            <div class="w-10 h-10 bg-primary text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-primary/40">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div class="text-2xl font-bold text-dark tracking-tight">Tics<span class="text-primary">Org</span></div>
        </div>

        <nav class="flex-1 space-y-2">
            <a href="anggota.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium"><i class="fa-solid fa-users w-5 text-center"></i><span>Anggota</span></a>
            <a href="departemen.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium"><i class="fa-solid fa-sitemap w-5 text-center"></i><span>Departemen</span></a>
            <a href="divisi.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium"><i class="fa-solid fa-network-wired w-5 text-center"></i><span>Divisi</span></a>
            <a href="kegiatan.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium"><i class="fa-regular fa-calendar-check w-5 text-center"></i><span>Kegiatan</span></a>
            <a href="berita.php" class="flex items-center gap-4 px-4 py-4 bg-primary text-white rounded-2xl shadow-lg shadow-primary/30 font-bold hover:scale-105 transition-transform"><i class="fa-regular fa-newspaper w-5 text-center"></i><span>Berita</span></a>
            <a href="pengumuman.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium"><i class="fa-solid fa-bullhorn w-5 text-center"></i><span>Pengumuman</span></a>
        </nav>

        <div class="mt-auto flex items-center gap-3 p-3 rounded-2xl border border-gray-100 bg-gray-50/50">
            <img src="https://ui-avatars.com/api/?name=Admin&background=0466c8&color=fff" class="w-10 h-10 rounded-full border border-white shadow-sm">
            <div class="overflow-hidden">
                <p class="text-sm font-bold text-dark truncate">Admin Utama</p>
                <p class="text-xs text-muted truncate">Super User</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col relative overflow-hidden">
        <header class="h-24 flex items-center justify-between px-10 pt-4 flex-shrink-0">
            <div>
                <p class="text-muted text-sm font-medium">Dashboard Admin</p>
                <h1 class="text-3xl font-bold text-dark mt-1">Portal Berita</h1>
            </div>
            
            <div class="bg-white p-2 pl-6 pr-2 rounded-full shadow-card flex items-center gap-4 w-[400px]">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                <input type="text" placeholder="Cari berita..." class="bg-transparent flex-1 outline-none text-sm text-dark placeholder:text-muted/70">
                <div class="h-8 w-[1px] bg-gray-100"></div>
                <button class="w-10 h-10 rounded-full flex items-center justify-center text-muted hover:text-primary hover:bg-blue-50 transition relative">
                    <span class="absolute top-2 right-3 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                    <i class="fa-regular fa-bell text-xl"></i>
                </button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-10 pb-10 no-scrollbar">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 mb-8">
                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-primary flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Total Artikel</p>
                        <h2 class="text-2xl font-bold text-dark" id="totalBerita"><?php echo e($total); ?></h2>
                        <p class="text-xs text-muted mt-1">Dipublikasikan</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-primary rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-newspaper"></i></div>
                </div>
                
                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-green-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Berita Bulan Ini</p>
                        <h2 class="text-2xl font-bold text-dark">5</h2>
                        <p class="text-xs text-green-500 font-bold mt-1">Artikel Baru</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-calendar-plus"></i></div>
                </div>

                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-orange-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Update Terakhir</p>
                        <h2 class="text-lg font-bold text-dark">2 Jam Lalu</h2>
                        <p class="text-xs text-muted mt-1">Ada aktivitas edit</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-clock-rotate-left"></i></div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6">
                <div class="flex gap-3">
                    <button class="px-5 py-2.5 rounded-full bg-white shadow-card text-primary font-bold text-sm border border-transparent hover:border-primary/20 transition">Semua</button>
                    <button class="px-5 py-2.5 rounded-full text-muted font-medium text-sm hover:bg-white hover:text-primary transition">Terbaru</button>
                    <button class="px-5 py-2.5 rounded-full text-muted font-medium text-sm hover:bg-white hover:text-primary transition">Arsip Lama</button>
                </div>

                <button onclick="toggleModal(true)" class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
                    <i class="fa-solid fa-plus"></i>
                    Tulis Berita
                </button>
            </div>

            <div class="w-full space-y-4" id="beritaList">
                <?php if ($error): ?>
                    <div class="p-4 bg-red-50 text-red-700 rounded">Error: <?php echo e($error); ?></div>
                <?php endif; ?>

                <?php foreach ($berita_list as $row): ?>
                    <?php
                        $id = $row['id'];
                        $judul = $row['judul'] ?? '';
                        $isi = $row['isi'] ?? '';
                        $thumb = $row['thumbnail'] ?? '';
                        $created = isset($row['created_at']) && $row['created_at'] ? date('d M Y', strtotime($row['created_at'])) : '';
                        $snippet = mb_substr(strip_tags($isi), 0, 160) . (mb_strlen(strip_tags($isi)) > 160 ? '...' : '');
                        $img = $thumb ? e($thumb) : 'https://source.unsplash.com/random/400x300/?news';
                    ?>
                    <div class="group bg-white rounded-[20px] p-4 flex gap-6 shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20 animate-fade-in" id="berita-<?php echo e($id); ?>">
                        <div class="w-48 h-32 bg-gray-200 rounded-xl overflow-hidden flex-shrink-0 relative">
                            <img src="<?php echo $img; ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        </div>
                        
                        <div class="flex-1 flex flex-col justify-center min-w-0">
                            <div class="flex items-center gap-3 text-xs text-muted mb-2">
                                <span class="flex items-center gap-1"><i class="fa-regular fa-calendar"></i> <?php echo e($created); ?></span>
                                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                <span class="flex items-center gap-1"><i class="fa-solid fa-user-pen"></i> Admin</span>
                            </div>
                            <h3 class="font-bold text-dark text-lg mb-2 group-hover:text-primary transition line-clamp-1"><?php echo e($judul); ?></h3>
                            <p class="text-muted text-sm line-clamp-2 leading-relaxed"><?php echo e($snippet); ?></p>
                        </div>

                        <div class="flex flex-col justify-center gap-2 border-l border-gray-100 pl-4">
                            <button onclick="editItem('<?php echo e(addslashes($judul)); ?>')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-primary hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-pen"></i></button>
                            <button onclick="deleteItem('berita-<?php echo e($id); ?>')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-red-500 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </main>

    <div id="modalBerita" class="fixed inset-0 z-50 flex items-center justify-center bg-dark/60 backdrop-blur-sm hide-modal modal">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl p-8 modal-content relative overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-dark">Tulis Berita Baru</h2>
                <button onclick="toggleModal(false)" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-muted hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="formBerita" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Judul Berita</label>
                    <input type="text" id="inputJudul" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Judul yang menarik...">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Thumbnail (Gambar Sampul)</label>
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 flex flex-col items-center justify-center text-muted hover:bg-gray-50 transition cursor-pointer relative">
                        <input type="file" id="inputThumbnail" class="absolute inset-0 opacity-0 cursor-pointer">
                        <i class="fa-solid fa-image text-3xl mb-2"></i>
                        <span class="text-xs">Klik untuk upload gambar (Max 2MB)</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Isi Berita</label>
                    <textarea id="inputIsi" rows="6" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Tulis konten berita di sini..."></textarea>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="toggleModal(false)" class="flex-1 bg-gray-100 hover:bg-gray-200 text-dark font-bold py-4 rounded-xl transition">Batal</button>
                    <button type="button" onclick="simpanBerita()" class="flex-1 bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">Publikasikan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            const modal = document.getElementById('modalBerita');
            if (show) {
                modal.classList.remove('hide-modal');
                modal.classList.add('show-modal');
            } else {
                modal.classList.remove('show-modal');
                modal.classList.add('hide-modal');
            }
        }

        function deleteItem(id) {
            if(confirm("Hapus berita ini dari database?")) {
                const item = document.getElementById(id);
                if (!item) return;
                item.style.opacity = '0';
                item.style.transform = 'scale(0.9)';
                setTimeout(() => item.remove(), 300);
            }
        }

        function editItem(judul) {
            alert("Edit berita: " + judul + "\n(Mengisi form dengan data lama dari DB)");
        }

        function simpanBerita() {
            const judul = document.getElementById('inputJudul').value;
            const isi = document.getElementById('inputIsi').value;
            // File input handle secara backend nanti
            
            if(!judul || !isi) { alert("Judul dan Isi tidak boleh kosong!"); return; }

            const today = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            const newId = 'berita-' + Date.now();
            
            // Generate Random Image untuk simulasi thumbnail
            const randomImg = "https://source.unsplash.com/random/400x300/?office";

            const newItemHTML = `
            <div class="group bg-white rounded-[20px] p-4 flex gap-6 shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20 animate-fade-in" id="${newId}">
                <div class="w-48 h-32 bg-gray-200 rounded-xl overflow-hidden flex-shrink-0 relative">
                    <img src="${randomImg}" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                </div>
                <div class="flex-1 flex flex-col justify-center min-w-0">
                    <div class="flex items-center gap-3 text-xs text-muted mb-2">
                        <span class="flex items-center gap-1"><i class="fa-regular fa-calendar"></i> ${today}</span>
                        <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                        <span class="flex items-center gap-1"><i class="fa-solid fa-user-pen"></i> Admin</span>
                    </div>
                    <h3 class="font-bold text-dark text-lg mb-2 group-hover:text-primary transition line-clamp-1">${judul}</h3>
                    <p class="text-muted text-sm line-clamp-2 leading-relaxed">${isi}</p>
                </div>
                <div class="flex flex-col justify-center gap-2 border-l border-gray-100 pl-4">
                     <button onclick="editItem('${judul}')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-primary hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-pen"></i></button>
                    <button onclick="deleteItem('${newId}')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-red-500 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>`;

            document.getElementById('beritaList').insertAdjacentHTML('afterbegin', newItemHTML);
            
            // Update Stat
            const totalEl = document.getElementById('totalBerita');
            totalEl.innerText = parseInt(totalEl.innerText) + 1;

            document.getElementById('formBerita').reset();
            toggleModal(false);
        }
    </script>
</body>
</html>
