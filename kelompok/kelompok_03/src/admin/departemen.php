<?php
require_once __DIR__ . '/../config/db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error = null;
$departemen_list = [];
try {
    // fetch all departemen
    $departemen_list = db_fetch_all('SELECT * FROM departemen ORDER BY nama ASC');
} catch (Exception $ex) {
    $error = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departemen - Atics</title>
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
            <a href="anggota.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-users w-5 text-center"></i>
                <span>Anggota</span>
            </a>
            <a href="departemen.php" class="flex items-center gap-4 px-4 py-4 bg-primary text-white rounded-2xl shadow-lg shadow-primary/30 font-bold hover:scale-105 transition-transform">
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
                <h1 class="text-3xl font-bold text-dark mt-1">Manajemen Departemen</h1>
            </div>

            <div class="bg-white p-2 pl-6 pr-2 rounded-full shadow-card flex items-center gap-4 w-[400px]">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                <input type="text" placeholder="Cari departemen..." class="bg-transparent flex-1 outline-none text-sm text-dark placeholder:text-muted/70">
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
                        <p class="text-sm text-muted font-medium mb-1">Total Departemen</p>
                        <h2 class="text-2xl font-bold text-dark"><?php echo e(count($departemen_list)); ?></h2>
                        <p class="text-xs text-muted mt-1">Aktif beroperasi</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-primary rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-indigo-500">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Total Divisi</p>
                        <?php
                            $totalDivisi = 0;
                            if (!$error) {
                                $r = db_fetch_all('SELECT COUNT(*) as c FROM divisi');
                                $totalDivisi = isset($r[0]['c']) ? (int)$r[0]['c'] : 0;
                            }
                        ?>
                        <h2 class="text-2xl font-bold text-dark"><?php echo e($totalDivisi); ?></h2>
                        <p class="text-xs text-indigo-500 font-bold mt-1">Sub-unit kerja</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-[20px] shadow-card flex items-center justify-between border-l-4 border-orange-500">
                    <div>
                        <p class="text-sm text-muted font-medium mb-1">Total Staf Dept.</p>
                        <?php
                            $totalStaff = 0;
                            if (!$error) {
                                $r2 = db_fetch_all('SELECT COUNT(*) as c FROM anggota_jabatan');
                                $totalStaff = isset($r2[0]['c']) ? (int)$r2[0]['c'] : 0;
                            }
                        ?>
                        <h2 class="text-2xl font-bold text-dark"><?php echo e($totalStaff); ?></h2>
                        <p class="text-xs text-muted mt-1">Anggota fungsionaris</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                </div>
            </div>

            <div class="flex justify-end items-center mb-6">
                <button class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Departemen
                </button>
            </div>

            <div class="w-full">
                <div class="grid grid-cols-12 gap-4 px-6 mb-2 text-xs font-bold text-muted uppercase tracking-wider">
                    <div class="col-span-4">Departemen</div>
                    <div class="col-span-2">Statistik</div>
                    <div class="col-span-3">Kepala Departemen</div>
                    <div class="col-span-2">Deskripsi</div>
                    <div class="col-span-1 text-right">Aksi</div>
                </div>

                <div class="space-y-4">
                    <?php if ($error): ?>
                        <div class="p-4 bg-red-50 text-red-700 rounded">Error: <?php echo e($error); ?></div>
                    <?php endif; ?>

                    <?php foreach ($departemen_list as $dept): ?>
                        <?php
                            $id = $dept['id'];
                            $nama = $dept['nama'] ?? '';
                            $deskripsi = $dept['deskripsi'] ?? '';
                            // staff count from anggota_jabatan where departemen_id = id
                            $staffCount = 0;
                            $divisiCount = 0;
                            $kepala = null;
                            try {
                                $c = db_fetch('SELECT COUNT(*) as c FROM anggota_jabatan WHERE departemen_id = :d', [':d' => $id]);
                                $staffCount = isset($c['c']) ? (int)$c['c'] : 0;
                                $d = db_fetch('SELECT COUNT(*) as c FROM divisi WHERE departemen_id = :d', [':d' => $id]);
                                $divisiCount = isset($d['c']) ? (int)$d['c'] : 0;
                                // try to get a kepala candidate: pick first anggota assigned to this departemen
                                $kep = db_fetch('SELECT a.* FROM anggota a JOIN anggota_jabatan aj ON aj.anggota_id = a.id WHERE aj.departemen_id = :d LIMIT 1', [':d' => $id]);
                                if ($kep) $kepala = $kep;
                            } catch (Exception $ex) {
                                // ignore per-row error and continue
                            }
                        ?>
                        <div class="group bg-white rounded-[20px] p-4 grid grid-cols-12 gap-4 items-center shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20">
                            <div class="col-span-4 flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-sm">
                                    <i class="fa-solid fa-sitemap"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-dark text-sm group-hover:text-primary transition"><?php echo e($nama); ?></h3>
                                    <p class="text-[10px] text-muted uppercase tracking-wide"><?php echo e($nama); ?></p>
                                </div>
                            </div>
                            <div class="col-span-2 flex flex-col gap-1">
                                <span class="text-xs font-bold text-dark flex items-center gap-1.5"><i class="fa-solid fa-user-group text-muted"></i> <?php echo e($staffCount); ?> Staf</span>
                                <span class="text-xs font-medium text-muted flex items-center gap-1.5"><i class="fa-solid fa-layer-group text-muted"></i> <?php echo e($divisiCount); ?> Divisi</span>
                            </div>
                            <div class="col-span-3 flex items-center gap-2">
                                <?php if ($kepala): ?>
                                    <img src="<?php echo e($kepala['foto'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($kepala['nama'])); ?>" class="w-8 h-8 rounded-full border border-white shadow-sm">
                                    <span class="text-sm font-bold text-dark"><?php echo e($kepala['nama']); ?></span>
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-full bg-gray-100"></div>
                                    <span class="text-sm font-bold text-dark">-</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2 text-xs text-muted line-clamp-2 leading-relaxed">
                                <?php echo e($deskripsi); ?>
                            </div>
                            <div class="col-span-1 text-right">
                                <button class="text-muted hover:text-primary p-2 text-lg"><i class="fa-solid fa-pen-to-square"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </main>
</body>
</html>
