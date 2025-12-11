<?php
session_start();
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') || !isset($_SESSION['role'])) {
    header('Location: ../login/login.php');
    exit;
}
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

        // If updating and no new foto file uploaded, preserve existing foto from database
        if ($isUpdate && empty($_FILES['foto_file']['name']) && empty($foto)) {
            $old_data = db_fetch('SELECT foto FROM anggota WHERE id = ?', [$anggota_id]);
            if ($old_data && !empty($old_data['foto'])) {
                $foto = $old_data['foto'];
            }
        }

        // Handle foto file upload
        if (!empty($_FILES['foto_file']['name'])) {
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($_FILES['foto_file']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['foto_file']['size'];

            if (!in_array($file_ext, $allowed_ext)) {
                $error = "Hanya file JPG/PNG yang diizinkan.";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $error = "Ukuran file maksimal 2 MB.";
            } else {
                $filesDir = __DIR__ . '/../files';
                if (!is_dir($filesDir)) {
                    @mkdir($filesDir, 0755, true);
                }

                $foto_name = 'anggota_' . ($isUpdate ? $anggota_id : 'new') . '_' . time() . '.' . $file_ext;
                $upload_path = $filesDir . '/' . $foto_name;

                if (move_uploaded_file($_FILES['foto_file']['tmp_name'], $upload_path)) {
                    $foto = 'src/files/' . $foto_name;

                    // Delete old foto if updating
                    if ($isUpdate) {
                        $old_data = db_fetch('SELECT foto FROM anggota WHERE id = ?', [$anggota_id]);
                        if ($old_data && !empty($old_data['foto'])) {
                            $old_foto_filename = basename($old_data['foto']);
                            $old_path = $filesDir . '/' . $old_foto_filename;
                            if (file_exists($old_path)) {
                                @unlink($old_path);
                            }
                        }
                    }
                } else {
                    $error = "Gagal menyimpan foto. Pastikan folder /files bisa ditulis.";
                }
            }
        }

        if (!empty($error)) {
            // Error already set
        } elseif ($nama === '' || $npm === '' || $username === '' || (!$isUpdate && $password === '')) {
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
                    
                    // Only update anggota_jabatan if departemen or divisi was explicitly changed
                    // If both are empty strings, preserve existing jabatan assignments
                    $depVal = $departemen_id !== '' ? (int)$departemen_id : null;
                    $divVal = $divisi_id !== '' ? (int)$divisi_id : null;
                    
                    // Only modify jabatan if user provided departemen or divisi values
                    if ($departemen_id !== '' || $divisi_id !== '') {
                        // User is explicitly updating departemen/divisi, so update jabatan
                        db_execute('DELETE FROM anggota_jabatan WHERE anggota_id = :id', ['id' => $anggota_id]);
                        if ($depVal || $divVal) {
                            db_execute('INSERT INTO anggota_jabatan (anggota_id, jabatan_id, departemen_id, divisi_id) VALUES (:anggota_id, NULL, :departemen_id, :divisi_id)', [
                                'anggota_id' => $anggota_id,
                                'departemen_id' => $depVal,
                                'divisi_id' => $divVal,
                            ]);
                        }
                    }
                    // If neither departemen nor divisi was changed, preserve existing jabatan assignments
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
    } elseif ($action === 'remove_jabatan') {
        $anggota_jabatan_id = (int)($_POST['anggota_jabatan_id'] ?? 0);
        
        if ($anggota_jabatan_id <= 0) {
            $error = 'ID anggota_jabatan tidak valid.';
        } else {
            try {
                // Get the jabatan_id to check if it has 'Organisasi' suffix
                $ajRow = db_fetch('SELECT aj.jabatan_id FROM anggota_jabatan aj WHERE aj.id = :id', ['id' => $anggota_jabatan_id]);
                
                if ($ajRow && !empty($ajRow['jabatan_id'])) {
                    $jabRow = db_fetch('SELECT nama FROM jabatan WHERE id = :id', ['id' => $ajRow['jabatan_id']]);
                    
                    if ($jabRow && !empty($jabRow['nama']) && strpos($jabRow['nama'], 'Organisasi') !== false) {
                        // Delete the anggota_jabatan entry
                        db_execute('DELETE FROM anggota_jabatan WHERE id = :id', ['id' => $anggota_jabatan_id]);
                        header('Location: anggota.php');
                        exit;
                    } else {
                        $error = 'Hanya jabatan dengan suffix "Organisasi" yang bisa dihapus.';
                    }
                } else {
                    $error = 'Data anggota_jabatan tidak ditemukan.';
                }
            } catch (Exception $ex) {
                $error = 'Gagal menghapus jabatan: ' . $ex->getMessage();
            }
        }
    } elseif ($action === 'assign_jabatan') {
        $anggota_id = (int)($_POST['anggota_id'] ?? 0);
        $jabatan_id = (int)($_POST['jabatan_id'] ?? 0);
        
        if ($anggota_id <= 0 || $jabatan_id <= 0) {
            $error = 'ID anggota atau jabatan tidak valid.';
        } else {
            try {
                // Delete this jabatan from any other anggota (jabatan is exclusive to one anggota)
                db_execute('DELETE FROM anggota_jabatan WHERE jabatan_id = :jabatan_id', ['jabatan_id' => $jabatan_id]);
                
                // Delete old jabatan assignments for this anggota
                db_execute('DELETE FROM anggota_jabatan WHERE anggota_id = :anggota_id', ['anggota_id' => $anggota_id]);
                
                // Insert new jabatan assignment
                db_execute(
                    'INSERT INTO anggota_jabatan (anggota_id, jabatan_id, departemen_id, divisi_id) VALUES (:anggota_id, :jabatan_id, NULL, NULL)',
                    ['anggota_id' => $anggota_id, 'jabatan_id' => $jabatan_id]
                );
                header('Location: anggota.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Gagal mengassign jabatan: ' . $ex->getMessage();
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
    	
    	// fetch jabatan list for assignment modal - filter for 'Organisasi' suffix only
    	$jabatan_list = db_fetch_all("SELECT id, nama FROM jabatan WHERE nama LIKE '%Organisasi' ORDER BY nama ASC");
} catch (Exception $ex) {
    $error = $ex->getMessage();
    $total = 0;
    $anggota_baru = 0;
    $total_departemen = 0;
    	$departemen_list = [];
    	$divisi_list = [];
    	$jabatan_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggota - Organisasi Mahasiswa</title>
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
            <div class="text-2xl font-bold text- tracking-tight">Organisasi<span class="text-primary"> Mahasiswa</span></div>
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
        <form method="POST" action="../login/logout.php" style="padding:12px">
            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl font-semibold">Keluar</button>
        </form>
    </aside>

    <main class="flex-1 flex flex-col relative overflow-hidden">
        <header class="h-24 flex items-center justify-between px-10 pt-4 flex-shrink-0">
            <div>
                <p class="text-muted text-sm font-medium">Dashboard Admin</p>
                <h1 class="text-3xl font-bold text-dark mt-1">Manajemen Anggota</h1>
            </div>

            <div class="bg-white p-2 pl-6 pr-2 rounded-full shadow-card flex items-center gap-4 w-[400px]">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                <input id="searchAnggota" type="text" placeholder="Cari anggota berdasarkan nama..." class="bg-transparent flex-1 outline-none text-sm text-dark placeholder:text-muted/70">
                <div class="h-8 w-[1px] bg-gray-100"></div>
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
                        <div id="departemenPanel" class="absolute mt-2 left-0 w-56 bg-white rounded-lg shadow-lg ring-1 ring-black/5 hidden z-30">
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
                    <div class="col-span-3">Profil Anggota</div>
                    <div class="col-span-2">NPM</div>
                    <div class="col-span-3">Departemen / Divisi</div>
                    <div class="col-span-2">Jabatan</div>
                    <div class="col-span-2 text-right">Aksi</div>
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
                            // Use stored foto path if available, otherwise generate avatar from name
                            $avatar = !empty($foto) ? '../files/' . basename(e($foto)) : 'https://ui-avatars.com/api/?name=' . urlencode($nama) . '&background=random';
                        ?>
                        <?php
                            // load latest link for this anggota (departemen/divisi/jabatan) and fetch their names
                            $link = db_fetch(
                                'SELECT aj.id AS anggota_jabatan_id, aj.departemen_id, aj.divisi_id, aj.jabatan_id, dep.nama AS departemen_nama, dv.nama AS divisi_nama, jab.nama AS jabatan_nama
                                 FROM anggota_jabatan aj
                                 LEFT JOIN departemen dep ON aj.departemen_id = dep.id
                                 LEFT JOIN divisi dv ON aj.divisi_id = dv.id
                                 LEFT JOIN jabatan jab ON aj.jabatan_id = jab.id
                                 WHERE aj.anggota_id = :id
                                 ORDER BY aj.id DESC LIMIT 1',
                                ['id' => $anggota['id']]
                            );
                            $link_aj_id = $link ? ($link['anggota_jabatan_id'] ?? '') : '';
                            $link_dep = $link ? ($link['departemen_id'] ?? '') : '';
                            $link_div = $link ? ($link['divisi_id'] ?? '') : '';
                            $link_jabatan_id = $link ? ($link['jabatan_id'] ?? '') : '';
                            $link_dep_name = $link ? ($link['departemen_nama'] ?? '') : '';
                            $link_div_name = $link ? ($link['divisi_nama'] ?? '') : '';
                            $link_jabatan_name = $link ? ($link['jabatan_nama'] ?? '') : '';
                        ?>
                            <div class="group bg-white rounded-[20px] p-4 grid grid-cols-12 gap-4 items-center shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20" 
                                data-id="<?= e($anggota['id']) ?>" data-nama="<?= e($nama) ?>" data-npm="<?= e($npm) ?>" data-username="<?= e($username) ?>" data-foto="<?= e($foto) ?>" data-password="<?= e($anggota['password'] ?? '') ?>" data-departemen="<?= e($link_dep) ?>" data-divisi="<?= e($link_div) ?>" data-jabatan="<?= e($link_jabatan_id) ?>" data-jabatan-name="<?= e($link_jabatan_name) ?>" data-anggota-jabatan-id="<?= e($link_aj_id) ?>">
                            <div class="col-span-3 flex items-center gap-4">
                                <img src="<?php echo $avatar; ?>" class="w-12 h-12 rounded-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=' + encodeURIComponent('<?php echo e($nama); ?>')">
                                <div>
                                    <h3 class="font-bold text-dark text-sm group-hover:text-primary transition"><?php echo e($nama); ?></h3>
                                    <p class="text-xs text-muted">@<?php echo e($username); ?></p>
                                </div>
                            </div>
                            <div class="col-span-2 font-bold text-dark text-sm"><?php echo e($npm); ?></div>
                            <div class="col-span-3 text-sm text-dark font-medium"><?php echo e($link_dep_name ?: '-'); ?> <span class="text-muted text-xs"><?php echo $link_div_name ? '• ' . e($link_div_name) : ''; ?></span></div>
                            <div class="col-span-2">
                                <span class="bg-blue-50 text-primary px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1">
                                    <?php echo e($link_jabatan_name ?: 'Anggota'); ?>
                                </span>
                            </div>
                            <div class="col-span-2 text-right flex justify-end items-center gap-2">
                                <button type="button" onclick="openAssignJabatanModal(this)" class="text-sm px-3 py-1 rounded-lg bg-purple-50 text-purple-600 hover:bg-purple-100" title="Assign Jabatan">
                                    Jabatan
                                </button>
                                <button type="button" onclick="openEditModalFromRow(this)" class="text-sm px-3 py-1 rounded-lg bg-blue-50 text-primary hover:bg-blue-100">Edit</button>
                                <button type="button" onclick='confirmDeleteAnggota(<?= e($anggota['id']) ?>, <?= json_encode($nama) ?>)' class="text-sm px-3 py-1 rounded-lg bg-red-50 text-red-600 hover:bg-red-100">Hapus</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-between items-center mt-8 text-sm px-2">
                <span id="listCountLabel" data-total="<?= e($total) ?>" class="text-muted font-medium">Menampilkan <?= e(min(10, $total)) ?> dari <?= e($total) ?> data</span>
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

            <form method="post" id="formTambahAnggota" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="create_anggota">
                <input type="hidden" name="anggota_id" id="anggotaIdInput" value="">
                <input type="hidden" id="fotoHidden" name="foto" value="">
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
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Foto Profil</label>
                    <div class="relative">
                        <input type="file" name="foto_file" id="fotoFileInput" class="hidden" accept="image/jpeg,image/png,image/jpg">
                        <div id="fotoPreviewContainer" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition flex items-center gap-3 bg-gray-50 cursor-pointer hover:bg-gray-100">
                            <img id="fotoPreview" src="" class="w-12 h-12 rounded-lg object-cover hidden">
                            <div id="fotoPlaceholder" class="flex-1">
                                <p class="text-dark font-medium">Pilih foto...</p>
                                <p class="text-xs text-muted">JPG, PNG (Max 2MB)</p>
                            </div>
                            <i class="fa-solid fa-cloud-arrow-up text-xl text-muted"></i>
                        </div>
                    </div>
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

    <!-- Assign Jabatan Modal -->
    <div id="modalAssignJabatan" class="fixed inset-0 z-50 flex items-center justify-center bg-dark/60 backdrop-blur-sm hide-modal modal">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-8 modal-content relative">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-dark">Assign Jabatan</h2>
                <button onclick="closeAssignJabatanModal()" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-muted hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form method="post" id="formAssignJabatan" class="space-y-4">
                <input type="hidden" name="action" value="assign_jabatan">
                <input type="hidden" name="anggota_id" id="assignAnggotaId" value="">
                <input type="hidden" id="assignAnggotaJabatanId" value="">
                
                <div>
                    <p class="text-sm font-medium text-dark mb-3">Pilih Jabatan untuk:</p>
                    <p id="assignAnggotaNama" class="text-lg font-bold text-primary mb-4"></p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Jabatan</label>
                    <select name="jabatan_id" id="selectAssignJabatan" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition">
                        <option value="">-- Pilih Jabatan --</option>
                        <?php foreach ($jabatan_list as $jab): ?>
                            <option value="<?= e($jab['id']) ?>"><?= e($jab['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="submit" class="flex-1 bg-primary hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">Simpan</button>
                    <button type="button" id="deleteJabatanBtn" style="display: none;" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-xl transition">Hapus Jabatan</button>
                    <button type="button" onclick="closeAssignJabatanModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-dark font-bold py-3 rounded-xl transition">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function(){
            const modal = document.getElementById('modalTambahAnggota');
            const form = document.getElementById('formTambahAnggota');
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

            // Prevent form submission if file input is being clicked
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Allow form submission - file input will be included automatically
                    // because form has enctype="multipart/form-data"
                }, false);
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
        // Search-by-name filter for anggota list
        (function(){
            const searchInput = document.getElementById('searchAnggota');
            if (!searchInput) return;

            function updateCount(visible){
                const labelEl = document.getElementById('listCountLabel');
                if (!labelEl) return;
                const total = parseInt(labelEl.getAttribute('data-total') || '0', 10) || 0;
                labelEl.textContent = 'Menampilkan ' + visible + ' dari ' + total + ' data';
            }

            searchInput.addEventListener('input', function(){
                const q = (this.value || '').trim().toLowerCase();
                // try to read current departemen filter id from the label (if any)
                let depFilterId = '';
                const depLabel = document.getElementById('departemenButtonLabel');
                const depPanel = document.getElementById('departemenPanel');
                if (depLabel && depPanel) {
                    const name = depLabel.textContent.trim();
                    if (name && name !== 'Semua Departemen'){
                        // find button with matching name to get id
                        const btn = Array.from(depPanel.querySelectorAll('button[data-dep-name]')).find(b => (b.getAttribute('data-dep-name')||'').trim() === name);
                        if (btn) depFilterId = btn.getAttribute('data-dep-id') || '';
                    }
                }

                const rows = Array.from(document.querySelectorAll('[data-id]'));
                let visible = 0;
                rows.forEach(r => {
                    const name = (r.getAttribute('data-nama') || '').toLowerCase();
                    const rowDep = (r.getAttribute('data-departemen') || '').trim();
                    const matchName = q === '' ? true : name.indexOf(q) !== -1;
                    const matchDep = depFilterId ? (String(rowDep) === String(depFilterId)) : true;
                    if (matchName && matchDep) { r.style.display = ''; visible++; } else { r.style.display = 'none'; }
                });

                updateCount(visible);
            });
        })();
        // helper to open modal for creating a new anggota
        function openCreateModal() {
            const form = document.getElementById('formTambahAnggota');
            if (!form) return;
            form.reset();
            document.querySelector('input[name="action"]').value = 'create_anggota';
            document.getElementById('anggotaIdInput').value = '';
            const submitBtn = document.getElementById('submitAnggotaBtn');
            if (submitBtn) submitBtn.textContent = 'Simpan Anggota';
            
            // reset foto preview
            const fotoFileInput = document.getElementById('fotoFileInput');
            const fotoPreview = document.getElementById('fotoPreview');
            const fotoPlaceholder = document.getElementById('fotoPlaceholder');
            if (fotoFileInput) fotoFileInput.value = '';
            fotoPreview.classList.add('hidden');
            fotoPlaceholder.classList.remove('hidden');
            
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
            // populate password field from row data (plain-text stored)
            const pwd = row.getAttribute('data-password') || '';
            const pwdInput = form.querySelector('input[name="password"]');
            if (pwdInput) pwdInput.value = pwd;
            
            // reset file input and show preview if foto exists
            const fotoFileInput = document.getElementById('fotoFileInput');
            const fotoPreview = document.getElementById('fotoPreview');
            const fotoPlaceholder = document.getElementById('fotoPlaceholder');
            const fotoHidden = document.getElementById('fotoHidden');
            
            if (fotoFileInput) fotoFileInput.value = '';
            fotoHidden.value = '';
            
            if (foto) {
                // show existing foto preview from files directory
                fotoPreview.src = '../files/' + foto.split('/').pop();
                fotoPreview.classList.remove('hidden');
                fotoPlaceholder.classList.add('hidden');
            } else {
                fotoPreview.classList.add('hidden');
                fotoPlaceholder.classList.remove('hidden');
            }
            
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

        // confirm and remove jabatan (for Organisasi suffix only)
        function confirmRemoveJabatan(ajId, jabatanName) {
            if (!confirm('Hapus jabatan ' + jabatanName + '?')) return;
            let f = document.getElementById('formRemoveJabatan');
            if (!f) {
                f = document.createElement('form');
                f.method = 'POST';
                f.style.display = 'none';
                f.id = 'formRemoveJabatan';
                const a = document.createElement('input'); a.name = 'action'; a.value = 'remove_jabatan'; f.appendChild(a);
                const b = document.createElement('input'); b.name = 'anggota_jabatan_id'; b.id = 'removeJabatanId'; f.appendChild(b);
                document.body.appendChild(f);
            }
            document.getElementById('removeJabatanId').value = ajId;
            f.submit();
        }

        // Assign Jabatan Modal Functions
        function openAssignJabatanModal(btn) {
            const row = btn.closest('[data-id]');
            if (!row) return;
            const id = row.getAttribute('data-id');
            const nama = row.getAttribute('data-nama') || '';
            const jabatan = row.getAttribute('data-jabatan') || '';
            const jabatanName = row.getAttribute('data-jabatan-name') || '';
            const ajId = row.getAttribute('data-anggota-jabatan-id') || '';

            document.getElementById('assignAnggotaId').value = id;
            document.getElementById('assignAnggotaNama').textContent = nama;
            document.getElementById('assignAnggotaJabatanId').value = ajId;
            
            const selectJabatan = document.getElementById('selectAssignJabatan');
            if (selectJabatan) selectJabatan.value = jabatan;

            // Show delete button only if jabatan has 'Organisasi' suffix and ajId exists
            const deleteBtn = document.getElementById('deleteJabatanBtn');
            if (deleteBtn) {
                deleteBtn.style.display = (jabatanName.includes('Organisasi') && ajId) ? 'inline-block' : 'none';
                deleteBtn.onclick = () => confirmRemoveJabatan(ajId, jabatanName);
            }

            const modal = document.getElementById('modalAssignJabatan');
            if (modal) {
                modal.classList.remove('hide-modal');
                modal.classList.add('show-modal');
            }
        }

        function closeAssignJabatanModal() {
            const modal = document.getElementById('modalAssignJabatan');
            if (modal) {
                modal.classList.remove('show-modal');
                modal.classList.add('hide-modal');
            }
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
                // update visible count
                updateListCount();
            }

            // attach click handlers
            for (const it of items){
                it.addEventListener('click', function(){
                    const id = this.getAttribute('data-dep-id') || '';
                    const name = this.getAttribute('data-dep-name') || '';
                    applyFilter(id, name);
                });
            }

            // function to update the visible count label
            function updateListCount() {
                const labelEl = document.getElementById('listCountLabel');
                if (!labelEl) return;
                const total = parseInt(labelEl.getAttribute('data-total') || '0', 10) || 0;
                // count visible rows that represent anggota (have data-id attribute)
                const rows = Array.from(document.querySelectorAll('[data-id]'));
                let visible = 0;
                for (const r of rows) {
                    // consider element visible if not explicitly display:none
                    const style = window.getComputedStyle(r);
                    if (style.display !== 'none') visible++;
                }
                labelEl.textContent = 'Menampilkan ' + visible + ' dari ' + total + ' data';
            }

            // expose toggle to global so button onclick works
            window.toggleDepartemenPanel = toggleDepartemenPanel;
            // expose apply for external use
            window.applyDepartemenFilter = applyFilter;

            // update count on load
            document.addEventListener('DOMContentLoaded', function(){ updateListCount(); });
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

    <script>
        // file preview handler for foto input
        (function(){
            const fotoFileInput = document.getElementById('fotoFileInput');
            const fotoPreview = document.getElementById('fotoPreview');
            const fotoPlaceholder = document.getElementById('fotoPlaceholder');
            const fotoContainer = document.getElementById('fotoPreviewContainer');

            if (!fotoFileInput || !fotoContainer) return;

            // Make container clickable - open file picker
            fotoContainer.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                fotoFileInput.click();
            }, false);

            // Handle file selection - validate and show preview
            fotoFileInput.addEventListener('change', function(e){
                e.stopPropagation();
                const file = this.files && this.files[0];
                if (!file) return;

                // Validate file size
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2 MB.');
                    this.value = ''; // Reset input
                    fotoPreview.classList.add('hidden');
                    fotoPlaceholder.classList.remove('hidden');
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Hanya file JPG/PNG yang diizinkan.');
                    this.value = ''; // Reset input
                    fotoPreview.classList.add('hidden');
                    fotoPlaceholder.classList.remove('hidden');
                    return;
                }

                // Show preview using FileReader
                const reader = new FileReader();
                reader.onload = function(readerEvent) {
                    fotoPreview.src = readerEvent.target.result;
                    fotoPreview.classList.remove('hidden');
                    fotoPlaceholder.classList.add('hidden');
                };
                reader.onerror = function() {
                    alert('Gagal membaca file.');
                    fotoFileInput.value = '';
                    fotoPreview.classList.add('hidden');
                    fotoPlaceholder.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }, false);

            // Prevent drag-and-drop from causing issues
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fotoContainer.addEventListener(eventName, function(e){
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
        })();
    </script>
</body>
</html>
