<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Fetch all divisi with their parent departemen name
    $divisis = db_fetch_all('SELECT d.*, dep.nama AS departemen_nama FROM divisi d LEFT JOIN departemen dep ON d.departemen_id = dep.id ORDER BY d.nama ASC');

    // Precompute stats
    $totalDivisi = count($divisis);
    $totalStaff = 0;
    $divisiStats = [];

    foreach ($divisis as $div) {
        $divId = $div['id'];

        $staffRow = db_fetch('SELECT COUNT(*) AS c FROM anggota_jabatan WHERE divisi_id = :id', ['id' => $divId]);
        $staffCount = $staffRow ? (int)$staffRow['c'] : 0;
        $totalStaff += $staffCount;

        // Try to find a Ketua (leader) for the divisi
        $leader = db_fetch(
            'SELECT a.* FROM anggota a JOIN anggota_jabatan aj ON aj.anggota_id = a.id JOIN jabatan j ON j.id = aj.jabatan_id WHERE aj.divisi_id = :id AND LOWER(j.nama) LIKE :ketua LIMIT 1',
            ['id' => $divId, 'ketua' => '%ketua%']
        );

        if (!$leader) {
            // Fallback: any anggota in this divisi
            $leader = db_fetch('SELECT a.* FROM anggota a JOIN anggota_jabatan aj ON aj.anggota_id = a.id WHERE aj.divisi_id = :id LIMIT 1', ['id' => $divId]);
        }

        $divisiStats[$divId] = [
            'staffCount' => $staffCount,
            'leader' => $leader,
        ];
    }

} catch (Exception $e) {
    $error = $e->getMessage();
    $divisis = [];
    $divisiStats = [];
    $totalDivisi = 0;
    $totalStaff = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divisi - Tics</title>
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
        table { border-collapse: separate; border-spacing: 0 16px; }
        tr td:first-child { border-top-left-radius: 20px; border-bottom-left-radius: 20px; }
        tr td:last-child { border-top-right-radius: 20px; border-bottom-right-radius: 20px; }
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
            <a href="anggota.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-users w-5 text-center"></i><span>Anggota</span>
            </a>
            <a href="departemen.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-sitemap w-5 text-center"></i><span>Departemen</span>
            </a>
            <a href="divisi.php" class="flex items-center gap-4 px-4 py-4 bg-primary text-white rounded-2xl shadow-lg shadow-primary/30 font-bold hover:scale-105 transition-transform">
                <i class="fa-solid fa-network-wired w-5 text-center"></i><span>Divisi</span>
            </a>
            <a href="kegiatan.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-regular fa-calendar-check w-5 text-center"></i><span>Kegiatan</span>
            </a>
            <a href="berita.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-regular fa-newspaper w-5 text-center"></i><span>Berita</span>
            </a>
            <a href="pengumuman.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-bullhorn w-5 text-center"></i><span>Pengumuman</span>
            </a>
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
                <h1 class="text-3xl font-bold text-dark mt-1">Manajemen Divisi</h1>
            </div>
            
            <div class="bg-white p-2 pl-6 pr-2 rounded-full shadow-card flex items-center gap-4 w-[400px]">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                <input type="text" placeholder="Cari divisi..." class="bg-transparent flex-1 outline-none text-sm text-dark placeholder:text-muted/70">
                <button class="w-10 h-10 rounded-full flex items-center justify-center text-muted hover:text-primary bg-gray-50"><i class="fa-regular fa-bell"></i></button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-10 pb-10 no-scrollbar">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 mb-8">
                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-primary">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Total Divisi</p>
                        <h2 class="text-2xl font-bold text-dark" id="totalDivisi"><?= htmlspecialchars($totalDivisi) ?></h2>
                        <p class="text-xs text-muted mt-1">Aktif beroperasi</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-primary rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-network-wired"></i></div>
                </div>
                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-green-500">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Total Staf</p>
                        <h2 class="text-2xl font-bold text-dark"><?= htmlspecialchars($totalStaff) ?></h2>
                        <p class="text-xs text-green-500 font-bold mt-1">Terassign</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-users-viewfinder"></i></div>
                </div>
                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-purple-500">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Divisi Terbesar</p>
                        <h2 class="text-lg font-bold text-dark truncate w-32">--</h2>
                        <p class="text-xs text-muted mt-1">-- Anggota</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-ranking-star"></i></div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6">
                <div class="relative group">
                    <button class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition flex items-center gap-2">
                        <span>Filter Departemen</span>
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <div class="absolute top-full left-0 mt-2 w-48 bg-white rounded-xl shadow-xl hidden group-hover:block z-10 border border-gray-100 overflow-hidden">
                        <!-- Could populate dynamically if needed -->
                        <a href="#" class="block px-4 py-2 text-sm text-dark hover:bg-blue-50 hover:text-primary">Semua Departemen</a>
                    </div>
                </div>

                <button onclick="toggleModal(true)" class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Divisi
                </button>
            </div>

            <div class="w-full">
                <div class="grid grid-cols-12 gap-4 px-6 mb-2 text-xs font-bold text-muted uppercase tracking-wider">
                    <div class="col-span-4">Nama Divisi</div>
                    <div class="col-span-3">Induk Departemen</div>
                    <div class="col-span-2">Jumlah Anggota</div>
                    <div class="col-span-2">Ketua Divisi</div>
                    <div class="col-span-1 text-right">Aksi</div>
                </div>

                <div class="space-y-4" id="divisiContainer">
                    <?php if (!empty($error)): ?>
                        <div class="text-red-600 p-4 bg-white rounded-lg shadow-card"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if (empty($divisis)): ?>
                        <div class="bg-white rounded-[20px] p-6 shadow-card">Belum ada divisi terdaftar.</div>
                    <?php else: ?>
                        <?php foreach ($divisis as $div):
                            $stats = $divisiStats[$div['id']] ?? ['staffCount' => 0, 'leader' => null];
                            $leader = $stats['leader'];
                        ?>
                            <div class="group bg-white rounded-[20px] p-4 grid grid-cols-12 gap-4 items-center shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20">
                                <div class="col-span-4 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-photo-film"></i></div>
                                    <div>
                                        <h3 class="font-bold text-dark text-sm group-hover:text-primary transition"><?= htmlspecialchars($div['nama']) ?></h3>
                                        <p class="text-[10px] text-muted"><?= htmlspecialchars($div['deskripsi'] ?? '') ?></p>
                                    </div>
                                </div>
                                <div class="col-span-3">
                                    <span class="bg-blue-50 text-blue-600 border border-blue-100 px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-2 w-fit"><i class="fa-solid fa-bullhorn text-[10px]"></i> <?= htmlspecialchars($div['departemen_nama'] ?? '—') ?></span>
                                </div>
                                <div class="col-span-2 text-sm font-bold text-dark pl-2"><?= htmlspecialchars($stats['staffCount']) ?> Orang</div>
                                <div class="col-span-2 flex items-center gap-2">
                                    <?php if ($leader): ?>
                                        <?php $avatar = $leader['foto'] ? $leader['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($leader['nama']) . '&background=random'; ?>
                                        <img src="<?= htmlspecialchars($avatar) ?>" class="w-8 h-8 rounded-full border border-white shadow-sm">
                                        <span class="text-xs font-bold text-dark"><?= htmlspecialchars($leader['nama']) ?></span>
                                    <?php else: ?>
                                        <img src="https://ui-avatars.com/api/?name=—&background=random" class="w-8 h-8 rounded-full border border-white shadow-sm">
                                        <span class="text-xs font-bold text-dark">Belum Ada</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-span-1 text-right"><button class="text-muted hover:text-primary px-2"><i class="fa-solid fa-pen-to-square text-lg"></i></button></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </main>

    <div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center bg-dark/60 backdrop-blur-sm hide-modal modal">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl p-8 modal-content relative">
            
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-dark">Tambah Divisi Baru</h2>
                <button onclick="toggleModal(false)" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-muted hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="addDivisiForm" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Nama Divisi</label>
                    <input type="text" id="divisiName" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition" placeholder="Contoh: Multimedia">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Tagline / Deskripsi Singkat</label>
                    <input type="text" id="divisiTag" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition" placeholder="Contoh: Creative Team">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Induk Departemen</label>
                    <div class="relative">
                        <select id="deptSelect" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition appearance-none bg-white cursor-pointer">
                            <option value="">-- Pilih Departemen --</option>
                            <?php
                                // Simple department list for the select
                                $depts = db_fetch_all('SELECT id, nama FROM departemen ORDER BY nama ASC');
                                foreach ($depts as $dp) {
                                    echo '<option value="' . htmlspecialchars($dp['id']) . '">' . htmlspecialchars($dp['nama']) . '</option>';
                                }
                            ?>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-xs text-muted"></i>
                    </div>
                    <p class="text-[10px] text-muted mt-1 ml-1">*Divisi ini akan berada di bawah naungan departemen yang dipilih.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Ketua Divisi</label>
                    <div class="relative">
                        <select id="leaderSelect" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition appearance-none bg-white cursor-pointer">
                            <option value="">-- Pilih Anggota --</option>
                            <?php
                                $allAnggota = db_fetch_all('SELECT id, nama FROM anggota ORDER BY nama ASC');
                                foreach ($allAnggota as $ag) {
                                    echo '<option value="' . htmlspecialchars($ag['id']) . '">' . htmlspecialchars($ag['nama']) . '</option>';
                                }
                            ?>
                        </select>
                        <i class="fa-solid fa-user-check absolute right-4 top-4 text-xs text-muted"></i>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="button" onclick="handleAddDivisi()" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">
                        Simpan Divisi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            const modal = document.getElementById('addModal');
            if (show) {
                modal.classList.remove('hide-modal');
                modal.classList.add('show-modal');
            } else {
                modal.classList.remove('show-modal');
                modal.classList.add('hide-modal');
            }
        }

        function handleAddDivisi() {
            const name = document.getElementById('divisiName').value;
            const tag = document.getElementById('divisiTag').value || 'General Team';
            const dept = document.getElementById('deptSelect').value;
            const leader = document.getElementById('leaderSelect').value;

            if(!name) { alert("Nama divisi wajib diisi!"); return; }

            // This client-side add is only visual. For persistence, call a backend API.
            const newRow = `
            <div class="group bg-white rounded-[20px] p-4 grid grid-cols-12 gap-4 items-center shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20 animate-fade-in">
                <div class="col-span-4 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-cube"></i></div>
                    <div>
                        <h3 class="font-bold text-dark text-sm group-hover:text-primary transition">${name}</h3>
                        <p class="text-[10px] text-muted">${tag}</p>
                    </div>
                </div>
                <div class="col-span-3">
                    <span class="bg-blue-50 text-blue-600 border border-blue-100 px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-2 w-fit"><i class="fa-solid fa-bullhorn text-[10px]"></i> ${dept}</span>
                </div>
                <div class="col-span-2 text-sm font-bold text-dark pl-2">0 Orang</div>
                <div class="col-span-2 flex items-center gap-2">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(document.getElementById('leaderSelect').selectedOptions[0].text)}&background=random" class="w-8 h-8 rounded-full border border-white shadow-sm">
                    <span class="text-xs font-bold text-dark">${document.getElementById('leaderSelect').selectedOptions[0].text}</span>
                </div>
                <div class="col-span-1 text-right"><button class="text-muted hover:text-primary px-2"><i class="fa-solid fa-pen-to-square text-lg"></i></button></div>
            </div>
            `;

            const container = document.getElementById('divisiContainer');
            container.insertAdjacentHTML('afterbegin', newRow);

            document.getElementById('addDivisiForm').reset();
            toggleModal(false);
        }
    </script>
</body>
</html>
