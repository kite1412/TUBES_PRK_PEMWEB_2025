<?php
require_once __DIR__ . '/../config/db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error = null;
$anggota_list = [];
try {
    // fetch all anggota
    $anggota_list = db_fetch_all('SELECT * FROM anggota ORDER BY nama ASC');
    $total = count($anggota_list);
    // compute new anggota this month using date range (works for MySQL and SQLite text datetimes)
    $start = (new DateTime('first day of this month'))->format('Y-m-d 00:00:00');
    $end = (new DateTime('first day of next month'))->format('Y-m-d 00:00:00');
    $newRow = db_fetch('SELECT COUNT(*) AS c FROM anggota WHERE created_at >= :start AND created_at < :end', ['start' => $start, 'end' => $end]);
    $anggota_baru = $newRow ? (int)$newRow['c'] : 0;

    // total departemen count
    $depRow = db_fetch('SELECT COUNT(*) AS c FROM departemen');
    $total_departemen = $depRow ? (int)$depRow['c'] : 0;
} catch (Exception $ex) {
    $error = $ex->getMessage();
    $total = 0;
    $anggota_baru = 0;
    $total_departemen = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggota - Atics</title>
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
    </style>
</head>
<body class="bg-canvas text-dark h-screen flex overflow-hidden font-sans antialiased">

    <aside class="w-72 bg-white h-full flex flex-col py-8 px-5 z-20 shadow-xl shadow-blue-900/5">
        <div class="flex items-center gap-3 px-4 mb-10">
            <div class="w-10 h-10 bg-primary text-white rounded-xl flex items-center justify-center text-xl shadow-lg shadow-primary/40">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div class="text-2xl font-bold text-dark tracking-tight">Atics<span class="text-primary">.Inf</span></div>
        </div>

        <nav class="flex-1 space-y-2">
            <a href="anggota.php" class="flex items-center gap-4 px-4 py-4 bg-primary text-white rounded-2xl shadow-lg shadow-primary/30 font-bold hover:scale-105 transition-transform">
                <i class="fa-solid fa-users w-5 text-center"></i>
                <span>Anggota</span>
            </a>
            <a href="departemen.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-sitemap w-5 text-center"></i>
                <span>Departemen</span>
            </a>
            <a href="divisi.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-network-wired w-5 text-center"></i>
                <span>Divisi</span>
            </a>
            <a href="kegiatan.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-regular fa-calendar-check w-5 text-center"></i>
                <span>Kegiatan</span>
            </a>
            <a href="berita.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-regular fa-newspaper w-5 text-center"></i>
                <span>Berita</span>
            </a>
            <a href="pengumuman.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-bullhorn w-5 text-center"></i>
                <span>Pengumuman</span>
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
                <h1 class="text-3xl font-bold text-dark mt-1">Manajemen Anggota</h1>
            </div>

            <div class="bg-white p-2 pl-6 pr-2 rounded-full shadow-card flex items-center gap-4 w-[400px]">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                <input type="text" placeholder="Cari nama, NPM, atau jabatan..." class="bg-transparent flex-1 outline-none text-sm text-dark placeholder:text-muted/70">
                <div class="h-8 w-[1px] bg-gray-100"></div>
                <button class="w-10 h-10 rounded-full flex items-center justify-center text-muted hover:text-primary hover:bg-blue-50 transition relative">
                    <span class="absolute top-2 right-3 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                    <i class="fa-regular fa-bell text-xl"></i>
                </button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-10 pb-10 no-scrollbar">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 mb-8">
                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-primary">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Total Anggota</p>
                        <h2 class="text-2xl font-bold text-dark"><?php echo e($total); ?></h2>
                        <p class="text-xs text-muted mt-1">Terdaftar di sistem</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-primary rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-green-500">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Anggota Baru</p>
                        <h2 class="text-2xl font-bold text-dark"><?= e($anggota_baru) ?></h2>
                        <p class="text-xs text-green-500 font-bold mt-1">Bulan Ini</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-purple-500">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Total Departemen</p>
                        <h2 class="text-2xl font-bold text-dark"><?= e($total_departemen) ?></h2>
                        <p class="text-xs text-muted mt-1">Aktif beroperasi</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 text-purple-500 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6">
                <div class="flex gap-3">
                    <div class="relative group">
                        <button class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition flex items-center gap-2">
                            <span>Semua Departemen</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </button>
                    </div>

                    <button class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition">
                        Filter Jabatan
                    </button>
                </div>
                
                <button class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Anggota
                </button>
            </div>

            <div class="w-full">
                <div class="grid grid-cols-12 gap-4 px-6 mb-2 text-xs font-bold text-muted uppercase tracking-wider">
                    <div class="col-span-4">Profil Anggota</div>
                    <div class="col-span-2">NPM</div>
                    <div class="col-span-3">Departemen / Divisi</div>
                    <div class="col-span-2">Jabatan</div>
                    <div class="col-span-1 text-right">Aksi</div>
                </div>

                <div class="space-y-4">
                    <?php if ($error): ?>
                        <div class="p-4 bg-red-50 text-red-700 rounded">Error: <?php echo e($error); ?></div>
                    <?php endif; ?>

                    <?php foreach ($anggota_list as $anggota): ?>
                        <?php
                            $nama = $anggota['nama'] ?? '';
                            $username = $anggota['username'] ?? '';
                            $npm = $anggota['npm'] ?? '';
                            $foto = $anggota['foto'] ?? '';
                            $jabatan = $anggota['jabatan'] ?? ''; // optional column
                            $avatar = $foto ? e($foto) : 'https://ui-avatars.com/api/?name=' . urlencode($nama) . '&background=random';
                        ?>
                        <div class="group bg-white rounded-[20px] p-4 grid grid-cols-12 gap-4 items-center shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20">
                            <div class="col-span-4 flex items-center gap-4">
                                <img src="<?php echo $avatar; ?>" class="w-12 h-12 rounded-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=' + encodeURIComponent('<?php echo e($nama); ?>')">
                                <div>
                                    <h3 class="font-bold text-dark text-sm group-hover:text-primary transition"><?php echo e($nama); ?></h3>
                                    <p class="text-xs text-muted">@<?php echo e($username); ?></p>
                                </div>
                            </div>
                            <div class="col-span-2 font-bold text-dark text-sm"><?php echo e($npm); ?></div>
                            <div class="col-span-3 text-sm text-dark font-medium"><?php echo e($anggota['departemen'] ?? ''); ?> <span class="text-muted text-xs"><?php echo isset($anggota['divisi']) ? '• ' . e($anggota['divisi']) : ''; ?></span></div>
                            <div class="col-span-2">
                                <span class="bg-blue-50 text-primary px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1">
                                    <?php echo e($jabatan ?: 'Anggota'); ?>
                                </span>
                            </div>
                            <div class="col-span-1 text-right">
                                <button class="text-muted hover:text-primary p-2 text-lg"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-between items-center mt-8 text-sm px-2">
                <span class="text-muted font-medium">Menampilkan <?php echo e(min(10, $total)); ?> dari <?php echo e($total); ?> data</span>
                <div class="bg-white p-1 rounded-xl shadow-card flex items-center gap-1">
                    <button class="w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:bg-blue-50 hover:text-primary transition"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="w-8 h-8 rounded-lg flex items-center justify-center bg-primary text-white font-bold shadow-md shadow-primary/20">1</button>
                    <button class="w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:bg-blue-50 hover:text-primary transition">2</button>
                    <button class="w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:bg-blue-50 hover:text-primary transition">3</button>
                    <button class="w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:bg-blue-50 hover:text-primary transition"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
