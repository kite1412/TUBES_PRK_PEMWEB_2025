<?php
session_start();
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') || !isset($_SESSION['role'])) {
    header('Location: ../login/login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';

// Friendly popup message for UI (used when DB operations fail due to FK constraints)
$friendlyError = null;

// Handle create divisi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_divisi') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
        $departemen_id = (int)($_POST['departemen_id'] ?? 0) ?: null;
        $leader_id = (int)($_POST['leader_id'] ?? 0) ?: null;
        if ($nama === '') {
            $error = 'Nama divisi wajib diisi.';
        } else {
            try {
                $now = date('Y-m-d H:i:s');
                db_execute('INSERT INTO divisi (departemen_id, nama, deskripsi, created_at, updated_at) VALUES (:departemen_id, :nama, :deskripsi, :created_at, :updated_at)', [
                    'departemen_id' => $departemen_id,
                    'nama' => $nama,
                    'deskripsi' => $deskripsi,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                // if leader selected, assign jabatan 'Ketua Divisi' to that anggota for this divisi
                if ($leader_id) {
                    try {
                        $dbh = get_db();
                        $lastId = $dbh->lastInsertId();
                        $jab = db_fetch('SELECT id FROM jabatan WHERE nama = :n LIMIT 1', ['n' => 'Ketua Divisi']);
                        if ($jab && isset($jab['id'])) {
                            $jid = (int)$jab['id'];
                            // remove existing Ketua for this divisi (if any)
                            db_execute('DELETE FROM anggota_jabatan WHERE divisi_id = :div AND jabatan_id = :jid', ['div' => $lastId, 'jid' => $jid]);
                            // insert assignment; departemen_id intentionally left NULL (DB will store NULL)
                            db_execute('INSERT INTO anggota_jabatan (anggota_id, jabatan_id, divisi_id) VALUES (:anggota_id, :jabatan_id, :divisi_id)', [
                                'anggota_id' => $leader_id,
                                'jabatan_id' => $jid,
                                'divisi_id' => $lastId
                            ]);
                        }
                    } catch (Exception $ex) {
                        // non-fatal: leave as is but set error message for admin
                        $error = 'Divisi dibuat tetapi gagal meng-assign ketua: ' . $ex->getMessage();
                    }
                }
                header('Location: divisi.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Gagal menambah divisi: ' . $ex->getMessage();
            }
        }
    } elseif ($action === 'update_divisi') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim((string)($_POST['nama'] ?? ''));
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
        $departemen_id = (int)($_POST['departemen_id'] ?? 0) ?: null;
        $leader_id = (int)($_POST['leader_id'] ?? 0) ?: null;
        if ($id <= 0 || $nama === '') {
            $error = 'ID dan nama divisi wajib diisi.';
        } else {
            try {
                $now = date('Y-m-d H:i:s');
                db_execute('UPDATE divisi SET departemen_id = :departemen_id, nama = :nama, deskripsi = :deskripsi, updated_at = :updated_at WHERE id = :id', [
                    'departemen_id' => $departemen_id,
                    'nama' => $nama,
                    'deskripsi' => $deskripsi,
                    'updated_at' => $now,
                    'id' => $id,
                ]);
                // handle Ketua Divisi assignment changes
                    try {
                    $jab = db_fetch('SELECT id FROM jabatan WHERE nama = :n LIMIT 1', ['n' => 'Ketua Divisi']);
                    if ($jab && isset($jab['id'])) {
                        $jid = (int)$jab['id'];
                        // remove existing Ketua assignment for this divisi
                        db_execute('DELETE FROM anggota_jabatan WHERE divisi_id = :div AND jabatan_id = :jid', ['div' => $id, 'jid' => $jid]);
                        // if a leader selected, insert new assignment (departemen_id intentionally NULL)
                        if ($leader_id) {
                            db_execute('INSERT INTO anggota_jabatan (anggota_id, jabatan_id, divisi_id) VALUES (:anggota_id, :jabatan_id, :divisi_id)', [
                                'anggota_id' => $leader_id,
                                'jabatan_id' => $jid,
                                'divisi_id' => $id
                            ]);
                        }
                    }
                } catch (Exception $ex) {
                    // non-fatal: set error message
                    $error = 'Divisi diperbarui tetapi gagal meng-assign ketua: ' . $ex->getMessage();
                }
                header('Location: divisi.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Gagal mengubah divisi: ' . $ex->getMessage();
            }
        }
    } elseif ($action === 'delete_divisi') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'ID divisi tidak valid.';
        } else {
            try {
                db_execute('DELETE FROM divisi WHERE id = :id', ['id' => $id]);
                header('Location: divisi.php');
                exit;
            } catch (Exception $ex) {
                // If this is a foreign-key constraint (MySQL 1451 / SQLSTATE 23000), show a friendly popup instead
                $isFkError = false;
                $code = (string)$ex->getCode();
                $msg = $ex->getMessage();
                if (stripos($code, '23000') !== false) $isFkError = true;
                if (stripos($msg, '1451') !== false) $isFkError = true;
                if (stripos($msg, 'Cannot delete or update a parent row') !== false) $isFkError = true;

                if ($isFkError) {
                    $friendlyError = 'Tidak dapat menghapus divisi karena masih ada anggota atau data lain yang terkait. Hapus atau pindahkan anggota terlebih dahulu.';
                } else {
                    $error = 'Gagal menghapus divisi: ' . $ex->getMessage();
                }
            }
        }
    }
}

try {
    // Fetch all divisi with their parent departemen name
    $divisis = db_fetch_all('SELECT d.*, dep.nama AS departemen_nama FROM divisi d LEFT JOIN departemen dep ON d.departemen_id = dep.id ORDER BY d.nama ASC');
    // fetch departemen list for filter panel
    $departemen_list = db_fetch_all('SELECT id, nama FROM departemen ORDER BY nama ASC');

    // Precompute stats
    $totalDivisi = count($divisis);
    $totalStaff = 0;
    $divisiStats = [];
    $largestDivisiName = '--';
    $largestDivisiCount = 0;

    foreach ($divisis as $div) {
        $divId = $div['id'];

        $staffRow = db_fetch('SELECT COUNT(DISTINCT anggota_id) AS c FROM anggota_jabatan WHERE divisi_id = :id', ['id' => $divId]);
        $staffCount = $staffRow ? (int)$staffRow['c'] : 0;
        $totalStaff += $staffCount;

        // Find the Ketua Divisi (leader) for this divisi by exact jabatan name match
        $leader = db_fetch(
            'SELECT a.* FROM anggota a JOIN anggota_jabatan aj ON aj.anggota_id = a.id JOIN jabatan j ON j.id = aj.jabatan_id WHERE aj.divisi_id = :id AND j.nama = :jabatan LIMIT 1',
            ['id' => $divId, 'jabatan' => 'Ketua Divisi']
        );

        $divisiStats[$divId] = [
            'staffCount' => $staffCount,
            'leader' => $leader,
        ];

        // Track largest divisi
        if ($staffCount > $largestDivisiCount) {
            $largestDivisiCount = $staffCount;
            $largestDivisiName = $div['nama'] ?: '--';
        }
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
    <title>Divisi - Organisasi Mahasiswa</title>
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

    <?php if (!empty($friendlyError)): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function(){
                alert(<?= json_encode($friendlyError) ?>);
            });
        </script>
    <?php endif; ?>

    <aside class="w-72 bg-white h-full flex flex-col py-8 px-5 z-20 shadow-xl shadow-blue-900/5">
        <div class="flex items-center gap-3 px-4 mb-10">
            <div class="text-2xl font-bold text-dark tracking-tight">Organisasi<span class="text-primary"> Mahasiswa</span></div>
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
        <form method="POST" action="../login/logout.php" style="padding:12px">
            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl font-semibold">Keluar</button>
        </form>
    </aside>

    <main class="flex-1 flex flex-col relative overflow-hidden">
        <header class="h-24 flex items-center justify-between px-10 pt-4 flex-shrink-0">
            <div>
                <p class="text-muted text-sm font-medium">Dashboard Admin</p>
                <h1 class="text-3xl font-bold text-dark mt-1">Manajemen Divisi</h1>
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
                        <h2 class="text-lg font-bold text-dark truncate w-32"><?php echo htmlspecialchars($largestDivisiName); ?></h2>
                        <p class="text-xs text-muted mt-1"><?php echo htmlspecialchars($largestDivisiCount); ?> Anggota</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-ranking-star"></i></div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6">
                <div class="relative" style="position:relative;">
                    <button id="filterDeptBtn" class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition flex items-center gap-2">
                        <span id="filterDeptLabel">Semua Departemen</span>
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <div id="filterDeptPanel" class="absolute top-full left-0 mt-2 w-56 bg-white rounded-xl shadow-xl hidden z-10 border border-gray-100 overflow-hidden">
                        <div class="py-2">
                            <button data-dep-id="" data-dep-name="Semua Departemen" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 filter-dep-item">Semua Departemen</button>
                            <?php if (!empty($departemen_list)): foreach ($departemen_list as $dp): ?>
                                <button data-dep-id="<?= htmlspecialchars($dp['id']) ?>" data-dep-name="<?= htmlspecialchars($dp['nama']) ?>" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 filter-dep-item"><?= htmlspecialchars($dp['nama']) ?></button>
                            <?php endforeach; endif; ?>
                        </div>
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
                               <div class="group bg-white rounded-[20px] p-4 grid grid-cols-12 gap-4 items-center shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20"
                                   data-id="<?= htmlspecialchars($div['id']) ?>"
                                   data-nama="<?= htmlspecialchars($div['nama']) ?>"
                                   data-deskripsi="<?= htmlspecialchars($div['deskripsi'] ?? '') ?>"
                                   data-departemen-id="<?= htmlspecialchars($div['departemen_id'] ?? '') ?>"
                                   data-leader-id="<?= htmlspecialchars($leader['id'] ?? '') ?>">
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
                                        <?php 
                                            $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($leader['nama']) . '&background=random';
                                            if (!empty($leader['foto'])) {
                                                // Convert src/files/<filename> to ../files/<filename> for this directory level
                                                $avatar = '../files/' . basename($leader['foto']);
                                            }
                                        ?>
                                        <img src="<?= htmlspecialchars($avatar) ?>" class="w-8 h-8 rounded-full border border-white shadow-sm object-cover" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($leader['nama']); ?>&background=random'">
                                        <span class="text-xs font-bold text-dark"><?= htmlspecialchars($leader['nama']) ?></span>
                                    <?php else: ?>
                                        <img src="https://ui-avatars.com/api/?name=—&background=random" class="w-8 h-8 rounded-full border border-white shadow-sm">
                                        <span class="text-xs font-bold text-dark">Belum Ada</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-span-1 text-right">
                                    <button type="button" onclick="openEditModalFromRow(this)" class="text-sm px-3 py-1 rounded-lg bg-blue-50 text-primary hover:bg-blue-100">Edit</button>
                                    <button type="button" onclick='confirmDeleteDivisi(<?= htmlspecialchars($div['id']) ?>, <?= json_encode($div['nama']) ?>)' class="text-sm px-3 py-1 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 ml-2">Hapus</button>
                                </div>
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

            <form id="addDivisiForm" method="post" action="divisi.php" class="space-y-5">
                <input type="hidden" id="divisiAction" name="action" value="create_divisi">
                <input type="hidden" id="divisiId" name="id" value="">
                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Nama Divisi</label>
                    <input type="text" id="divisiName" name="nama" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition" placeholder="Contoh: Multimedia">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Deskripsi</label>
                    <input type="text" id="divisiTag" name="deskripsi" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition" placeholder="Contoh: Creative Team">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Induk Departemen</label>
                    <div class="relative">
                        <select id="deptSelect" name="departemen_id" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition appearance-none bg-white cursor-pointer">
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
                        <select id="leaderSelect" name="leader_id" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition appearance-none bg-white cursor-pointer">
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
                    <button type="submit" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">
                        Simpan Divisi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <form id="formDeleteDivisi" method="post" action="divisi.php" style="display:none;">
        <input type="hidden" name="action" value="delete_divisi">
        <input type="hidden" id="deleteDivisiId" name="id" value="">
    </form>

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
        
        function openEditModalFromRow(btn) {
            const row = btn.closest('.group');
            if (!row) return;
            const id = row.dataset.id || '';
            const nama = row.dataset.nama || '';
            const deskripsi = row.dataset.deskripsi || '';
                const dept = row.dataset.departemenId || '';
                const leader = row.dataset.leaderId || '';

            document.getElementById('divisiName').value = nama;
            document.getElementById('divisiTag').value = deskripsi;
            const deptSelect = document.getElementById('deptSelect');
            if (deptSelect) deptSelect.value = dept;
            const leaderSelect = document.getElementById('leaderSelect');
            if (leaderSelect) leaderSelect.value = leader;

            document.getElementById('divisiAction').value = 'update_divisi';
            document.getElementById('divisiId').value = id;
            toggleModal(true);
        }

        function confirmDeleteDivisi(id, name) {
            const text = 'Hapus divisi "' + (name || '') + '"? Tindakan ini tidak dapat dibatalkan.';
            if (!confirm(text)) return;
            const form = document.getElementById('formDeleteDivisi');
            document.getElementById('deleteDivisiId').value = id;
            form.submit();
        }
        
        // Filter Departemen panel + client-side filtering
        (function(){
            const panel = document.getElementById('filterDeptPanel');
            const btn = document.getElementById('filterDeptBtn');
            const label = document.getElementById('filterDeptLabel');
            const items = panel ? panel.querySelectorAll('.filter-dep-item') : [];
            const rows = Array.from(document.querySelectorAll('[data-departemen-id]'));

            // Use direct style toggling to avoid relying on utility classes
            function showPanel(){ if (!panel) return; panel.style.display = 'block'; document.addEventListener('click', outsideClick); document.addEventListener('keydown', escHandler); }
            function hidePanel(){ if (!panel) return; panel.style.display = 'none'; document.removeEventListener('click', outsideClick); document.removeEventListener('keydown', escHandler); }
            function togglePanel(){ if (!panel) return; if (panel.style.display === 'block') hidePanel(); else showPanel(); }

            function escHandler(e){ if (e.key === 'Escape') hidePanel(); }
            function outsideClick(e){ if (!panel.contains(e.target) && !btn.contains(e.target)) hidePanel(); }

            function applyFilter(depId, depName){
                if (label) label.textContent = depName || 'Semua Departemen';
                for (const r of rows){
                    const rowDep = r.getAttribute('data-departemen-id') || '';
                    if (!depId) { r.style.display = ''; } else { r.style.display = (rowDep === String(depId)) ? '' : 'none'; }
                }
                hidePanel();
            }

            if (btn) btn.addEventListener('click', function(e){ e.stopPropagation(); togglePanel(); });
            // ensure panel is hidden initially (in case CSS classes are missing)
            if (panel) panel.style.display = 'none';
            for (const it of items){ it.addEventListener('click', function(){ const id = this.getAttribute('data-dep-id') || ''; const name = this.getAttribute('data-dep-name') || ''; applyFilter(id, name); }); }
        })();
    </script>
</body>
</html>
