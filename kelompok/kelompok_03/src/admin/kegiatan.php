<?php
require_once __DIR__ . '/../config/db.php';

try {
    $kegiatans = db_fetch_all('SELECT * FROM kegiatan ORDER BY tanggal DESC');
    $totalKegiatan = count($kegiatans);

    $now = new DateTime();
    $upcoming = 0;
    $done = 0;
    foreach ($kegiatans as $k) {
        $t = new DateTime($k['tanggal']);
        if ($t >= $now) $upcoming++; else $done++;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $kegiatans = [];
    $totalKegiatan = 0;
    $upcoming = 0;
    $done = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kegiatan - Tics</title>
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
            <a href="kegiatan.php" class="flex items-center gap-4 px-4 py-4 bg-primary text-white rounded-2xl shadow-lg shadow-primary/30 font-bold hover:scale-105 transition-transform"><i class="fa-regular fa-calendar-check w-5 text-center"></i><span>Kegiatan</span></a>
            <a href="berita.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium"><i class="fa-regular fa-newspaper w-5 text-center"></i><span>Berita</span></a>
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
                <h1 class="text-3xl font-bold text-dark mt-1">Daftar Kegiatan</h1>
            </div>
            
            <div class="bg-white p-2 pl-6 pr-2 rounded-full shadow-card flex items-center gap-4 w-[400px]">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                <input type="text" id="searchInput" placeholder="Cari kegiatan..." class="bg-transparent flex-1 outline-none text-sm text-dark placeholder:text-muted/70">
                <button class="w-10 h-10 rounded-full flex items-center justify-center text-muted hover:text-primary bg-gray-50"><i class="fa-regular fa-bell"></i></button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-10 pb-10 no-scrollbar">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 mb-8">
                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-primary flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Total Kegiatan</p>
                        <h2 class="text-2xl font-bold text-dark" id="totalKegiatan"><?= htmlspecialchars($totalKegiatan) ?></h2>
                        <p class="text-xs text-muted mt-1">Tahun ini</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-primary rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-list-check"></i></div>
                </div>
                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-green-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Akan Datang</p>
                        <h2 class="text-2xl font-bold text-dark"><?= htmlspecialchars($upcoming) ?></h2>
                        <p class="text-xs text-green-500 font-bold mt-1">Segera dilaksanakan</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-regular fa-clock"></i></div>
                </div>
                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-gray-400 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Selesai</p>
                        <h2 class="text-2xl font-bold text-dark"><?= htmlspecialchars($done) ?></h2>
                        <p class="text-xs text-muted mt-1">Terlaksana sukses</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 text-gray-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-check-double"></i></div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6">
                <div class="flex gap-3">
                    <div class="relative group">
                        <button class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition flex items-center gap-2">
                            <span>Bulan Ini</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </button>
                    </div>
                    <div class="relative group">
                        <button class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition flex items-center gap-2">
                            <span>Status: Semua</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </button>
                    </div>
                </div>

                <button onclick="toggleModal(true)" class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
                    <i class="fa-solid fa-plus"></i>
                    Buat Kegiatan Baru
                </button>
            </div>

            <div class="w-full space-y-4" id="kegiatanList">
                <?php if (!empty($error)): ?>
                    <div class="text-red-600 p-4 bg-white rounded-lg shadow-card"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php foreach ($kegiatans as $k):
                    $t = new DateTime($k['tanggal']);
                    $day = $t->format('j');
                    $mon = $t->format('M');
                    $time = $t->format('H:i');
                ?>
                <div class="group bg-white rounded-[20px] p-5 flex items-center gap-6 shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20" id="kegiatan-<?= htmlspecialchars($k['id']) ?>">
                    <div class="bg-blue-50 text-primary w-20 h-20 rounded-2xl flex flex-col items-center justify-center border border-blue-100 flex-shrink-0">
                        <span class="text-2xl font-bold"><?= htmlspecialchars($day) ?></span>
                        <span class="text-xs font-semibold uppercase"><?= htmlspecialchars($mon) ?></span>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-3 mb-1">
                            <h3 class="font-bold text-dark text-lg truncate"><?= htmlspecialchars($k['judul']) ?></h3>
                            <?php $statusLabel = ($t >= new DateTime()) ? '<span class="bg-green-100 text-green-600 px-3 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide">Akan Datang</span>' : '<span class="bg-gray-200 text-gray-600 px-3 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide">Selesai</span>'; ?>
                            <?= $statusLabel ?>
                        </div>
                        <p class="text-muted text-sm line-clamp-1"><?= htmlspecialchars($k['deskripsi']) ?></p>
                        <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-muted font-medium">
                            <span class="flex items-center gap-1"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($time) ?> WIB</span>
                            <span class="flex items-center gap-1"><i class="fa-solid fa-calendar-days"></i> <?= htmlspecialchars($t->format('Y-m-d')) ?></span>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button onclick="editItem('<?= addslashes(htmlspecialchars($k['judul'])) ?>')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-primary hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="deleteItem('kegiatan-<?= htmlspecialchars($k['id']) ?>')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-red-500 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </main>

    <div id="modalKegiatan" class="fixed inset-0 z-50 flex items-center justify-center bg-dark/60 backdrop-blur-sm hide-modal modal">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl p-8 modal-content relative overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-dark">Tambah Kegiatan</h2>
                <button onclick="toggleModal(false)" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-muted hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="formKegiatan" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Judul Kegiatan</label>
                    <input type="text" id="inputJudul" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Contoh: Seminar Nasional">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-dark uppercase mb-1">Tanggal</label>
                        <input type="date" id="inputTanggal" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-dark uppercase mb-1">Jam Mulai</label>
                        <input type="time" id="inputJam" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Penyelenggara (Div/Dept)</label>
                    <select id="inputDept" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary bg-white cursor-pointer">
                        <option value="Dept. Kominfo">Dept. Kominfo</option>
                        <option value="Dept. PSDM">Dept. PSDM</option>
                        <option value="Divisi Kaderisasi">Divisi Kaderisasi</option>
                        <option value="BPH Inti">BPH Inti</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Lokasi</label>
                    <input type="text" id="inputLokasi" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Contoh: Aula Kampus / Zoom">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Deskripsi Singkat</label>
                    <textarea id="inputDeskripsi" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Jelaskan tujuan kegiatan..."></textarea>
                </div>

                <div class="pt-2">
                    <button type="button" onclick="simpanKegiatan()" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">
                        Simpan & Publikasikan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Logic
        function toggleModal(show) {
            const modal = document.getElementById('modalKegiatan');
            if (show) {
                modal.classList.remove('hide-modal');
                modal.classList.add('show-modal');
            } else {
                modal.classList.remove('show-modal');
                modal.classList.add('hide-modal');
            }
        }

        // Delete Logic
        function deleteItem(id) {
            if(confirm("Yakin ingin menghapus kegiatan ini?")) {
                const item = document.getElementById(id);
                if (!item) return;
                item.style.opacity = '0';
                item.style.transform = 'scale(0.9)';
                setTimeout(() => item.remove(), 300);
            }
        }

        // Edit Logic (Placeholder)
        function editItem(judul) {
            alert("Fitur Edit untuk '" + judul + "' akan membuka form dengan data terisi.");
        }

        // Add Logic (client-side only)
        function simpanKegiatan() {
            const judul = document.getElementById('inputJudul').value;
            const tanggalRaw = document.getElementById('inputTanggal').value; // YYYY-MM-DD
            const jam = document.getElementById('inputJam').value;
            const dept = document.getElementById('inputDept').value;
            const lokasi = document.getElementById('inputLokasi').value;
            const deskripsi = document.getElementById('inputDeskripsi').value;

            if(!judul || !tanggalRaw) { alert("Judul dan Tanggal wajib diisi!"); return; }

            const dateObj = new Date(tanggalRaw);
            const tgl = dateObj.getDate();
            const bln = dateObj.toLocaleString('default', { month: 'short' });

            const newId = 'kegiatan-' + Date.now();

            const newItemHTML = `
            <div class="group bg-white rounded-[20px] p-5 flex items-center gap-6 shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20 animate-fade-in" id="${newId}">
                <div class="bg-blue-50 text-primary w-20 h-20 rounded-2xl flex flex-col items-center justify-center border border-blue-100 flex-shrink-0">
                    <span class="text-2xl font-bold">${tgl}</span>
                    <span class="text-xs font-semibold uppercase">${bln}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-3 mb-1">
                        <h3 class="font-bold text-dark text-lg truncate">${judul}</h3>
                        <span class="bg-blue-100 text-blue-600 px-3 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide">Baru</span>
                        <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md text-[10px] font-semibold border border-gray-200 flex items-center gap-1">
                            <i class="fa-solid fa-bullhorn"></i> ${dept}
                        </span>
                    </div>
                    <p class="text-muted text-sm line-clamp-1">${deskripsi}</p>
                    <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-muted font-medium">
                        <span class="flex items-center gap-1"><i class="fa-regular fa-clock"></i> ${jam} WIB</span>
                        <span class="flex items-center gap-1"><i class="fa-solid fa-location-dot"></i> ${lokasi}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="editItem('${judul}')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-primary hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-pen"></i></button>
                    <button onclick="deleteItem('${newId}')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-red-500 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
            `;

            const list = document.getElementById('kegiatanList');
            list.insertAdjacentHTML('afterbegin', newItemHTML);

            const totalEl = document.getElementById('totalKegiatan');
            totalEl.innerText = parseInt(totalEl.innerText) + 1;

            document.getElementById('formKegiatan').reset();
            toggleModal(false);
        }
    </script>
</body>
</html>
