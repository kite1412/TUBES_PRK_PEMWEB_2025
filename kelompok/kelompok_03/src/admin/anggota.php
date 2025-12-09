<?php
require_once __DIR__ . '/../config/db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error = null;
$anggota_list = [];
// Handle anggota create / update / delete POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_anggota' || $action === 'update_anggota') {
        $isUpdate = $action === 'update_anggota';
        $anggota_id = $isUpdate ? (int)($_POST['anggota_id'] ?? 0) : null;
        $nama = trim((string)($_POST['nama'] ?? ''));
        $npm = trim((string)($_POST['npm'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $foto = trim((string)($_POST['foto'] ?? ''));
        $departemen_id = trim((string)($_POST['departemen_id'] ?? ''));
        $divisi_id = trim((string)($_POST['divisi_id'] ?? ''));

        if ($nama === '' || $npm === '' || $username === '' || (!$isUpdate && $password === '')) {
            $error = 'Nama, NPM, username, dan password wajib diisi.';
        } else {
            try {
                $now = date('Y-m-d H:i:s');
                if ($isUpdate) {
                    // build update fields
                    $params = [
                        'id' => $anggota_id,
                        'nama' => $nama,
                        'npm' => $npm,
                        'username' => $username,
                        'foto' => $foto,
                        'updated_at' => $now,
                    ];
                    $setSql = 'nama = :nama, npm = :npm, username = :username, foto = :foto, updated_at = :updated_at';
                    if ($password !== '') {
                        // store plain-text password as requested
                        $params['password'] = $password;
                        $setSql = 'nama = :nama, npm = :npm, username = :username, password = :password, foto = :foto, updated_at = :updated_at';
                    }
                    db_execute("UPDATE anggota SET $setSql WHERE id = :id", $params);
                    // update anggota_jabatan: remove old links and add new one if provided
                    db_execute('DELETE FROM anggota_jabatan WHERE anggota_id = :id', ['id' => $anggota_id]);
                    $depVal = $departemen_id !== '' ? (int)$departemen_id : null;
                    $divVal = $divisi_id !== '' ? (int)$divisi_id : null;
                    if ($depVal || $divVal) {
                        db_execute('INSERT INTO anggota_jabatan (anggota_id, jabatan_id, departemen_id, divisi_id) VALUES (:anggota_id, NULL, :departemen_id, :divisi_id)', [
                            'anggota_id' => $anggota_id,
                            'departemen_id' => $depVal,
                            'divisi_id' => $divVal,
                        ]);
                    }
                } else {
                    // create
                    // store plain-text password as requested
                    db_execute(
                        'INSERT INTO anggota (nama, npm, username, password, foto, created_at, updated_at) VALUES (:nama, :npm, :username, :password, :foto, :created_at, :updated_at)',
                        [
                            'nama' => $nama,
                            'npm' => $npm,
                            'username' => $username,
                            'password' => $password,
                            'foto' => $foto,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                    // link anggota to departemen/divisi via anggota_jabatan if provided
                    $dbh = get_db();
                    $lastId = $dbh->lastInsertId();
                    $depVal = $departemen_id !== '' ? (int)$departemen_id : null;
                    $divVal = $divisi_id !== '' ? (int)$divisi_id : null;
                    if ($depVal || $divVal) {
                        db_execute('INSERT INTO anggota_jabatan (anggota_id, jabatan_id, departemen_id, divisi_id) VALUES (:anggota_id, NULL, :departemen_id, :divisi_id)', [
                            'anggota_id' => $lastId,
                            'departemen_id' => $depVal,
                            'divisi_id' => $divVal,
                        ]);
                    }
                }
                // redirect to avoid resubmission
                header('Location: anggota.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Gagal menyimpan anggota: ' . $ex->getMessage();
            }
        }
    } elseif (($action === 'delete_anggota')) {
        $anggota_id = (int)($_POST['anggota_id'] ?? 0);
        if ($anggota_id <= 0) {
            $error = 'ID anggota tidak valid.';
        } else {
            try {
                // delete related anggota_jabatan first
                db_execute('DELETE FROM anggota_jabatan WHERE anggota_id = :id', ['id' => $anggota_id]);
                db_execute('DELETE FROM anggota WHERE id = :id', ['id' => $anggota_id]);
                header('Location: anggota.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Gagal menghapus anggota: ' . $ex->getMessage();
            }
        }
    }
}
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
    
    	// fetch departemen and divisi lists for modal selects
    	$departemen_list = db_fetch_all('SELECT id, nama FROM departemen ORDER BY nama ASC');
    	$divisi_list = db_fetch_all('SELECT id, departemen_id, nama FROM divisi ORDER BY nama ASC');
} catch (Exception $ex) {
    $error = $ex->getMessage();
    $total = 0;
    $anggota_baru = 0;
    $total_departemen = 0;
    	$departemen_list = [];
    	$divisi_list = [];
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
        /* Modal visibility helpers (used by toggle script) */
        .modal { transition: opacity 0.25s ease, visibility 0.25s ease; }
        .modal-content { transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
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
                    <div class="relative group" id="departemenFilterWrap">
                        <button id="departemenButton" onclick="toggleDepartemenPanel()" class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition flex items-center gap-2">
                            <span id="departemenButtonLabel">Semua Departemen</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </button>
                        <div id="departemenPanel" class="absolute mt-2 right-0 w-56 bg-white rounded-lg shadow-lg ring-1 ring-black/5 hidden z-30">
                            <div class="py-2">
                                <button data-dep-id="" data-dep-name="Semua Departemen" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 filter-dep-item">Semua Departemen</button>
                                <?php foreach ($departemen_list as $dep): ?>
                                    <button data-dep-id="<?= e($dep['id']) ?>" data-dep-name="<?= e($dep['nama']) ?>" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 filter-dep-item"><?= e($dep['nama']) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>
                
                <button onclick="toggleModal(true)" class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
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
                        <?php
                            // load latest link for this anggota (departemen/divisi)
                            $link = db_fetch('SELECT departemen_id, divisi_id FROM anggota_jabatan WHERE anggota_id = :id ORDER BY id DESC LIMIT 1', ['id' => $anggota['id']]);
                            $link_dep = $link ? ($link['departemen_id'] ?? '') : '';
                            $link_div = $link ? ($link['divisi_id'] ?? '') : '';
                        ?>
                            <div class="group bg-white rounded-[20px] p-4 grid grid-cols-12 gap-4 items-center shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20" 
                                data-id="<?= e($anggota['id']) ?>" data-nama="<?= e($nama) ?>" data-npm="<?= e($npm) ?>" data-username="<?= e($username) ?>" data-foto="<?= e($foto) ?>" data-password="<?= e($anggota['password'] ?? '') ?>" data-departemen="<?= e($link_dep) ?>" data-divisi="<?= e($link_div) ?>">
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
                            <div class="col-span-1 text-right flex justify-end items-center gap-2">
                                <button type="button" onclick="openEditModalFromRow(this)" class="text-sm px-3 py-1 rounded-lg bg-blue-50 text-primary hover:bg-blue-100">Edit</button>
                                <button type="button" onclick='confirmDeleteAnggota(<?= e($anggota['id']) ?>, <?= json_encode($nama) ?>)' class="text-sm px-3 py-1 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">Hapus</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-between items-center mt-8 text-sm px-2">
                <span class="text-muted font-medium">Menampilkan <?php echo e(min(10, $total)); ?> dari <?php echo e($total); ?> data</span>
            </div>

        </div>
    </main>

    <!-- Tambah Anggota Modal -->
    <div id="modalTambahAnggota" class="fixed inset-0 z-50 flex items-center justify-center bg-dark/60 backdrop-blur-sm hide-modal modal">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl p-8 modal-content relative">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-dark">Tambah Anggota</h2>
                <button onclick="toggleModal(false)" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-muted hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 bg-red-50 text-red-700 rounded"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" id="formTambahAnggota" class="space-y-4">
                <input type="hidden" name="action" value="create_anggota">
                <input type="hidden" name="anggota_id" id="anggotaIdInput" value="">
                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Nama</label>
                    <input name="nama" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Nama lengkap">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-dark uppercase mb-1">NPM</label>
                        <input name="npm" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="NPM">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-dark uppercase mb-1">Username</label>
                        <input name="username" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="username">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Password">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Foto (URL)</label>
                    <input name="foto" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="https://...">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-dark uppercase mb-1">Departemen</label>
                        <select name="departemen_id" id="selectDepartemen" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition">
                            <option value="">-- Pilih Departemen --</option>
                            <?php foreach ($departemen_list as $dep): ?>
                                <option value="<?= e($dep['id']) ?>" <?= (isset($_POST['departemen_id']) && $_POST['departemen_id'] == $dep['id']) ? 'selected' : '' ?>><?= e($dep['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-dark uppercase mb-1">Divisi</label>
                        <select name="divisi_id" id="selectDivisi" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition">
                            <option value="">-- Pilih Divisi --</option>
                            <?php foreach ($divisi_list as $dv): ?>
                                <option value="<?= e($dv['id']) ?>" data-departemen="<?= e($dv['departemen_id']) ?>" <?= (isset($_POST['divisi_id']) && $_POST['divisi_id'] == $dv['id']) ? 'selected' : '' ?>><?= e($dv['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" id="submitAnggotaBtn" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">Simpan Anggota</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function(){
            const modal = document.getElementById('modalTambahAnggota');
            let escHandler = null;

            function openModal() {
                if (!modal) return;
                modal.classList.remove('hide-modal');
                modal.classList.add('show-modal');
                // focus first input
                const first = modal.querySelector('input[name="nama"]');
                if (first) first.focus();

                // add overlay click handler
                modal.addEventListener('click', overlayClick);

                // add escape handler
                escHandler = function(e) {
                    if (e.key === 'Escape') closeModal();
                };
                document.addEventListener('keydown', escHandler);
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.remove('show-modal');
                modal.classList.add('hide-modal');
                // remove listeners
                modal.removeEventListener('click', overlayClick);
                if (escHandler) {
                    document.removeEventListener('keydown', escHandler);
                    escHandler = null;
                }
            }

            function overlayClick(e) {
                // if click target is the overlay (modal itself), close
                if (e.target === modal) closeModal();
            }

            // expose toggle function used by buttons
            window.toggleModal = function(show) {
                if (show) openModal(); else closeModal();
            };

            // make sure modal is hidden on load
            if (modal) {
                modal.classList.remove('show-modal');
                modal.classList.add('hide-modal');
            }
        })();
    </script>
    <script>
        // helper to open modal for creating a new anggota
        function openCreateModal() {
            const form = document.getElementById('formTambahAnggota');
            if (!form) return;
            form.reset();
            document.querySelector('input[name="action"]').value = 'create_anggota';
            document.getElementById('anggotaIdInput').value = '';
            const submitBtn = document.getElementById('submitAnggotaBtn');
            if (submitBtn) submitBtn.textContent = 'Simpan Anggota';
            // apply divisi filter after reset
            const evt = new Event('change');
            const dep = document.getElementById('selectDepartemen');
            if (dep) dep.dispatchEvent(evt);
            window.toggleModal(true);
        }

        // open edit modal, filling values from a row element or button
        function openEditModalFromRow(btn) {
            // find parent row with data attributes
            let row = btn.closest('[data-id]');
            if (!row) return;
            const id = row.getAttribute('data-id');
            const nama = row.getAttribute('data-nama') || '';
            const npm = row.getAttribute('data-npm') || '';
            const username = row.getAttribute('data-username') || '';
            const foto = row.getAttribute('data-foto') || '';
            const dep = row.getAttribute('data-departemen') || '';
            const div = row.getAttribute('data-divisi') || '';

            const form = document.getElementById('formTambahAnggota');
            if (!form) return;
            form.reset();
            document.querySelector('input[name="action"]').value = 'update_anggota';
            document.getElementById('anggotaIdInput').value = id;
            form.querySelector('input[name="nama"]').value = nama;
            form.querySelector('input[name="npm"]').value = npm;
            form.querySelector('input[name="username"]').value = username;
            form.querySelector('input[name="foto"]').value = foto;
            // populate password field from row data (plain-text stored)
            const pwd = row.getAttribute('data-password') || '';
            const pwdInput = form.querySelector('input[name="password"]');
            if (pwdInput) pwdInput.value = pwd;
            // set departemen and trigger divisi filter
            const depSelect = document.getElementById('selectDepartemen');
            const divSelect = document.getElementById('selectDivisi');
            if (depSelect) depSelect.value = dep;
            // trigger change to filter divisi
            const evt = new Event('change');
            if (depSelect) depSelect.dispatchEvent(evt);
            if (divSelect && div) divSelect.value = div;

            const submitBtn = document.getElementById('submitAnggotaBtn');
            if (submitBtn) submitBtn.textContent = 'Perbarui Anggota';

            window.toggleModal(true);
        }

        // confirm and submit delete
        function confirmDeleteAnggota(id, nama) {
            const label = nama || 'anggota ini';
            if (!confirm('Hapus ' + label + '? Tindakan ini tidak dapat dibatalkan.')) return;
            // create and submit a hidden form
            let f = document.getElementById('formDeleteAnggota');
            if (!f) {
                f = document.createElement('form');
                f.method = 'POST';
                f.style.display = 'none';
                f.id = 'formDeleteAnggota';
                const a = document.createElement('input'); a.name = 'action'; a.value = 'delete_anggota'; f.appendChild(a);
                const b = document.createElement('input'); b.name = 'anggota_id'; b.id = 'deleteAnggotaId'; f.appendChild(b);
                document.body.appendChild(f);
            }
            document.getElementById('deleteAnggotaId').value = id;
            f.submit();
        }
    </script>
    <script>
        // departemen filter panel + client-side filtering
        (function(){
            const panel = document.getElementById('departemenPanel');
            const btn = document.getElementById('departemenButton');
            const label = document.getElementById('departemenButtonLabel');
            const items = panel ? panel.querySelectorAll('.filter-dep-item') : [];
            const rows = Array.from(document.querySelectorAll('[data-id]'));
            let selectedDep = '';

            function showPanel() { panel.classList.remove('hidden'); document.addEventListener('click', outsideClick); document.addEventListener('keydown', escHandler); }
            function hidePanel() { panel.classList.add('hidden'); document.removeEventListener('click', outsideClick); document.removeEventListener('keydown', escHandler); }
            function toggleDepartemenPanel() { if (!panel) return; if (panel.classList.contains('hidden')) showPanel(); else hidePanel(); }

            function escHandler(e){ if (e.key === 'Escape') hidePanel(); }
            function outsideClick(e){ if (!panel.contains(e.target) && !btn.contains(e.target)) hidePanel(); }

            function applyFilter(depId, depName){
                selectedDep = depId || '';
                if (label) label.textContent = depName || 'Semua Departemen';
                // show/hide rows
                for (const r of rows){
                    const rowDep = r.getAttribute('data-departemen') || '';
                    if (!selectedDep) { r.style.display = ''; } else { r.style.display = (rowDep === String(selectedDep)) ? '' : 'none'; }
                }
                hidePanel();
            }

            // attach click handlers
            for (const it of items){
                it.addEventListener('click', function(){
                    const id = this.getAttribute('data-dep-id') || '';
                    const name = this.getAttribute('data-dep-name') || '';
                    applyFilter(id, name);
                });
            }

            // expose toggle to global so button onclick works
            window.toggleDepartemenPanel = toggleDepartemenPanel;
            // expose apply for external use
            window.applyDepartemenFilter = applyFilter;
        })();
    </script>
    <script>
        // filter divisi based on selected departemen
        (function(){
            const departemenSelect = document.getElementById('selectDepartemen');
            const divisiSelect = document.getElementById('selectDivisi');
            if (!departemenSelect || !divisiSelect) return;

            function filterDivisi() {
                const dep = departemenSelect.value;
                for (const opt of Array.from(divisiSelect.options)) {
                    const optDep = opt.getAttribute('data-departemen') || '';
                    if (!dep) {
                        // show all
                        opt.style.display = '';
                    } else {
                        if (opt.value === '') { opt.style.display = ''; continue; }
                        opt.style.display = (optDep === dep) ? '' : 'none';
                    }
                }
                // if current selected option is hidden, reset to empty
                if (divisiSelect.selectedOptions.length > 0) {
                    const sel = divisiSelect.selectedOptions[0];
                    if (sel && sel.style.display === 'none') divisiSelect.value = '';
                }
            }

            departemenSelect.addEventListener('change', filterDivisi);
            // run once on load to apply initial filtering
            filterDivisi();
        })();
    </script>

</body>
</html>
