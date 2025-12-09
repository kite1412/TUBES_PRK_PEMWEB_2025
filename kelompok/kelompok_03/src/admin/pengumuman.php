<?php
session_start();
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') || !isset($_SESSION['role'])) {
    header('Location: ../login/login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';

try {
    // Handle create/update/delete pengumuman
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_pengumuman' || $action === 'update_pengumuman') {
            $isUpdate = $action === 'update_pengumuman';
            $id = $isUpdate ? (int)($_POST['id'] ?? 0) : 0;
            $judul = trim((string)($_POST['judul'] ?? ''));
            $konten = trim((string)($_POST['konten'] ?? ''));
            $target = ($_POST['target'] ?? 'semua');
            $departemen_id = null;
            $divisi_id = null;
            if ($target === 'departemen') {
                $departemen_id = (int)($_POST['departemen_id'] ?? 0) ?: null;
            } elseif ($target === 'divisi') {
                $divisi_id = (int)($_POST['divisi_id'] ?? 0) ?: null;
            }

            if ($judul === '' || $konten === '') {
                $error = 'Judul dan Konten wajib diisi.';
            } else {
                $now = date('Y-m-d H:i:s');
                if ($isUpdate && $id > 0) {
                    db_execute('UPDATE pengumuman SET judul = :judul, konten = :konten, target = :target, departemen_id = :dept, divisi_id = :div, tanggal = :tanggal, updated_at = :updated_at WHERE id = :id', [
                        'judul' => $judul,
                        'konten' => $konten,
                        'target' => $target,
                        'dept' => $departemen_id,
                        'div' => $divisi_id,
                        'tanggal' => $now,
                        'updated_at' => $now,
                        'id' => $id,
                    ]);
                } else {
                    db_execute('INSERT INTO pengumuman (judul, konten, target, departemen_id, divisi_id, tanggal, created_at, updated_at) VALUES (:judul, :konten, :target, :dept, :div, :tanggal, :created_at, :updated_at)', [
                        'judul' => $judul,
                        'konten' => $konten,
                        'target' => $target,
                        'dept' => $departemen_id,
                        'div' => $divisi_id,
                        'tanggal' => $now,
                        'created_at' => $now,
                        'updated_at' => null,
                    ]);
                }
                header('Location: pengumuman.php');
                exit;
            }
        } elseif ($action === 'delete_pengumuman') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db_execute('DELETE FROM pengumuman WHERE id = :id', ['id' => $id]);
                header('Location: pengumuman.php');
                exit;
            }
        }
    }

    $rows = db_fetch_all(
        'SELECT p.*, dep.nama AS departemen_nama, dv.nama AS divisi_nama FROM pengumuman p LEFT JOIN departemen dep ON p.departemen_id = dep.id LEFT JOIN divisi dv ON p.divisi_id = dv.id ORDER BY p.tanggal DESC'
    );

    $totalPengumuman = count($rows);
    $infoPenting = 0; // target != 'semua'
    $baru24 = 0;
    $now = new DateTime();
    foreach ($rows as $r) {
        if ($r['target'] !== 'semua') $infoPenting++;
        $t = new DateTime($r['tanggal']);
        $interval = $now->getTimestamp() - $t->getTimestamp();
        if ($interval <= 24 * 3600) $baru24++;
    }

    // For modal selects
    $departemenList = db_fetch_all('SELECT id, nama FROM departemen ORDER BY nama ASC');
    $divisiList = db_fetch_all('SELECT id, nama FROM divisi ORDER BY nama ASC');

} catch (Exception $e) {
    $error = $e->getMessage();
    $rows = [];
    $totalPengumuman = 0;
    $infoPenting = 0;
    $baru24 = 0;
    $departemenList = [];
    $divisiList = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman - Tics</title>
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
        .show-modal { opacity: 1; visibility: visible; }
        .hide-modal { opacity: 0; visibility: hidden; }
        .slide-down { animation: slideDown 0.3s ease-out forwards; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
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
            <a href="divisi.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-solid fa-network-wired w-5 text-center"></i><span>Divisi</span>
            </a>
            <a href="kegiatan.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-regular fa-calendar-check w-5 text-center"></i><span>Kegiatan</span>
            </a>
            <a href="berita.php" class="flex items-center gap-4 px-4 py-4 text-muted hover:text-primary hover:bg-blue-50 rounded-2xl transition-colors font-medium">
                <i class="fa-regular fa-newspaper w-5 text-center"></i><span>Berita</span>
            </a>
            <a href="pengumuman.php" class="flex items-center gap-4 px-4 py-4 bg-primary text-white rounded-2xl shadow-lg shadow-primary/30 font-bold hover:scale-105 transition-transform">
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
                <h1 class="text-3xl font-bold text-dark mt-1">Broadcast Pengumuman</h1>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto px-10 pb-10 no-scrollbar">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 mb-8">
                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-primary flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Total Broadcast</p>
                        <h2 class="text-2xl font-bold text-dark" id="totalPengumuman"><?= htmlspecialchars($totalPengumuman) ?></h2>
                        <p class="text-xs text-muted mt-1">Aktif saat ini</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-primary rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-bullhorn"></i></div>
                </div>
                <!-- Info Penting card removed per UI update -->
                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-green-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Baru (24 Jam)</p>
                        <h2 class="text-2xl font-bold text-dark"><?= htmlspecialchars($baru24) ?></h2>
                        <p class="text-xs text-muted mt-1">Update terbaru</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-clock"></i></div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6">
                <div class="relative">
                    <button id="filterBtn" onclick="toggleFilter()" class="px-5 py-2.5 rounded-full bg-white shadow-card text-muted font-medium text-sm hover:text-primary hover:shadow-md transition flex items-center gap-2">
                        <span id="filterBtnLabel">Filter Target</span>
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <div id="filterPanel" class="absolute top-full left-0 mt-2 w-64 bg-white rounded-xl shadow-xl hidden z-10 border border-gray-100 overflow-hidden">
                        <a href="#" data-action="all" class="block px-4 py-2 text-sm text-dark hover:bg-blue-50 hover:text-primary">Semua</a>
                        <div class="border-t border-gray-100 px-3 py-2 text-xs text-muted">Per Departemen</div>
                        <?php foreach ($departemenList as $d): ?>
                            <a href="#" data-action="dept" data-id="<?= htmlspecialchars($d['id']) ?>" data-name="<?= htmlspecialchars($d['nama']) ?>" class="block px-4 py-2 text-sm text-dark hover:bg-blue-50 hover:text-primary"><?= htmlspecialchars($d['nama']) ?></a>
                        <?php endforeach; ?>
                        <div class="border-t border-gray-100 px-3 py-2 text-xs text-muted">Per Divisi</div>
                        <?php foreach ($divisiList as $dv): ?>
                            <a href="#" data-action="div" data-id="<?= htmlspecialchars($dv['id']) ?>" data-name="<?= htmlspecialchars($dv['nama']) ?>" class="block px-4 py-2 text-sm text-dark hover:bg-blue-50 hover:text-primary"><?= htmlspecialchars($dv['nama']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button onclick="toggleModal(true)" class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
                    <i class="fa-solid fa-paper-plane"></i>
                    Buat Pengumuman Baru
                </button>
            </div>

            <div class="w-full grid grid-cols-1 gap-6" id="pengumumanList">
                <?php if (!empty($error)): ?>
                    <div class="text-red-600 p-4 bg-white rounded-lg shadow-card"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php foreach ($rows as $r):
                    $tanggal = new DateTime($r['tanggal']);
                    $now = new DateTime();
                    $diff = $now->diff($tanggal);
                    if ($diff->d > 0) {
                        $ago = $diff->d . ' Hari yang lalu';
                    } elseif ($diff->h > 0) {
                        $ago = $diff->h . ' Jam yang lalu';
                    } elseif ($diff->i > 0) {
                        $ago = $diff->i . ' Menit yang lalu';
                    } else {
                        $ago = 'Baru saja';
                    }

                    // target label and class
                    if ($r['target'] === 'semua') {
                        $targetLabel = 'Semua Anggota';
                        $labelClass = 'bg-gray-100 text-dark';
                    } elseif ($r['target'] === 'departemen') {
                        $targetLabel = 'Dept. ' . ($r['departemen_nama'] ?? '—');
                        $labelClass = 'bg-blue-50 text-blue-700';
                    } else {
                        $targetLabel = 'Divisi ' . ($r['divisi_nama'] ?? '—');
                        $labelClass = 'bg-purple-50 text-purple-700';
                    }
                ?>
                <div class="bg-white rounded-[20px] p-6 shadow-card border-l-8 <?= ($r['target'] === 'departemen') ? 'border-blue-400' : (($r['target'] === 'divisi') ? 'border-green-500' : 'border-red-500') ?> flex flex-col gap-3 relative hover:shadow-soft transition-all group" id="p-<?= htmlspecialchars($r['id']) ?>" data-judul="<?= htmlspecialchars($r['judul']) ?>" data-konten="<?= htmlspecialchars($r['konten']) ?>" data-target="<?= htmlspecialchars($r['target']) ?>" data-departemen-id="<?= htmlspecialchars($r['departemen_id'] ?? '') ?>" data-divisi-id="<?= htmlspecialchars($r['divisi_id'] ?? '') ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <span class="<?= ($r['target'] === 'semua') ? 'bg-gray-100 text-dark' : (($r['target'] === 'departemen') ? 'bg-blue-50 text-blue-600' : 'bg-green-100 text-green-600') ?> text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wide flex items-center gap-1">
                                <i class="fa-solid <?= ($r['target'] === 'departemen') ? 'fa-circle-info' : (($r['target'] === 'divisi') ? 'fa-check' : 'fa-bullhorn') ?>"></i>
                                <?= ($r['target'] === 'semua') ? 'Umum' : ($r['target'] === 'departemen' ? 'Info' : 'Baru') ?>
                            </span>
                            <span class="text-xs text-muted">Target: <strong class="text-dark <?= $labelClass ?> px-2 py-0.5 rounded"><?= htmlspecialchars($targetLabel) ?></strong></span>
                        </div>
                        <span class="text-xs text-muted flex items-center gap-1"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($ago) ?></span>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-bold text-dark group-hover:text-primary transition"><?= htmlspecialchars($r['judul']) ?></h3>
                        <p class="text-sm text-muted mt-2 leading-relaxed"><?= nl2br(htmlspecialchars($r['konten'])) ?></p>
                    </div>

                        <div class="absolute top-6 right-6 flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button onclick="openEditPengumuman('p-<?= htmlspecialchars($r['id']) ?>')" class="text-muted hover:text-primary p-2 bg-gray-50 rounded-lg"><i class="fa-solid fa-pen"></i></button>
                            <button onclick="deleteItem('p-<?= htmlspecialchars($r['id']) ?>')" class="text-muted hover:text-red-500 p-2 bg-gray-50 rounded-lg"><i class="fa-solid fa-trash"></i></button>
                        </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </main>

    <div id="modalPengumuman" class="fixed inset-0 z-50 flex items-center justify-center bg-dark/60 backdrop-blur-sm hide-modal modal">
        <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl p-8 modal-content relative">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-dark">Broadcast Pesan</h2>
                <button onclick="toggleModal(false)" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-muted hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="formPengumuman" method="post" action="pengumuman.php" class="space-y-5">
                <input type="hidden" name="action" id="pengumumanAction" value="create_pengumuman">
                <input type="hidden" name="id" id="pengumumanId" value="">
                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Judul Pengumuman</label>
                    <input type="text" id="inputJudul" name="judul" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Cth: Perubahan Jadwal Rapat">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Target Audiens</label>
                    <select id="inputTarget" name="target" onchange="handleTargetChange()" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary bg-white cursor-pointer">
                        <option value="semua">Semua Anggota</option>
                        <option value="departemen">Spesifik Departemen</option>
                        <option value="divisi">Spesifik Divisi</option>
                    </select>
                </div>

                <div id="wrapperDept" class="hidden slide-down">
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Pilih Departemen</label>
                    <select id="selectDept" name="departemen_id" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary bg-white cursor-pointer">
                        <?php foreach ($departemenList as $d): ?>
                            <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="wrapperDivisi" class="hidden slide-down">
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Pilih Divisi</label>
                    <select id="selectDivisi" name="divisi_id" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary bg-white cursor-pointer">
                        <?php foreach ($divisiList as $dv): ?>
                            <option value="<?= htmlspecialchars($dv['id']) ?>"><?= htmlspecialchars($dv['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Isi Pesan</label>
                    <textarea id="inputKonten" name="konten" rows="4" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Tulis pengumuman di sini..."></textarea>
                </div>

                <div class="pt-2">
                    <button type="button" onclick="kirimPengumuman()" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">
                        <i class="fa-solid fa-paper-plane mr-2"></i> Kirim Pengumuman
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            const modal = document.getElementById('modalPengumuman');
            if (show) {
                modal.classList.remove('hide-modal');
                modal.classList.add('show-modal');
            } else {
                modal.classList.remove('show-modal');
                modal.classList.add('hide-modal');
            }
        }

        function handleTargetChange() {
            const target = document.getElementById('inputTarget').value;
            const wrapDept = document.getElementById('wrapperDept');
            const wrapDiv = document.getElementById('wrapperDivisi');

            wrapDept.classList.add('hidden');
            wrapDiv.classList.add('hidden');

            if (target === 'departemen') wrapDept.classList.remove('hidden');
            if (target === 'divisi') wrapDiv.classList.remove('hidden');
        }

        // Filter dropdown behavior and filtering logic
        function toggleFilter() {
            const panel = document.getElementById('filterPanel');
            if (!panel) return;
            panel.classList.toggle('hidden');
        }

        // Close filter panel when clicking outside
        document.addEventListener('click', function(e){
            const panel = document.getElementById('filterPanel');
            const btn = document.getElementById('filterBtn');
            if (!panel || !btn) return;
            if (panel.classList.contains('hidden')) return;
            if (!panel.contains(e.target) && !btn.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });

        // Delegated click handling for filter panel links (robust across browsers)
        (function(){
            const panelEl = document.getElementById('filterPanel');
            if (!panelEl) return;
            panelEl.addEventListener('click', function(e){
                const a = e.target.closest('a');
                if (!a || !panelEl.contains(a)) return;
                e.stopPropagation();
                e.preventDefault();
                const action = a.dataset.action || '';
                if (action === 'all') {
                    filterByAll();
                } else if (action === 'dept') {
                    const id = a.dataset.id || '';
                    const name = a.dataset.name || '';
                    filterByDepartemen(id, name);
                } else if (action === 'div') {
                    const id = a.dataset.id || '';
                    const name = a.dataset.name || '';
                    filterByDivisi(id, name);
                }
                // hide panel after action
                panelEl.classList.add('hidden');
            });
        })();

        function _getItems() {
            return Array.from(document.querySelectorAll('#pengumumanList > div[id^="p-"]'));
        }

        function filterByAll() {
            _getItems().forEach(i => i.style.display = '');
            document.getElementById('filterBtnLabel').innerText = 'Semua';
            document.getElementById('filterPanel').classList.add('hidden');
        }

        function filterByDepartemen(id, name) {
            const selId = Number(String(id).trim());
            _getItems().forEach(i => {
                const target = (i.getAttribute('data-target') || '').trim();
                const deptAttr = (i.getAttribute('data-departemen-id') || '').trim();
                const deptNum = deptAttr === '' ? null : Number(deptAttr);
                // show only items that explicitly target this departemen
                if (target === 'departemen' && deptNum !== null && deptNum === selId) {
                    i.style.display = '';
                } else {
                    i.style.display = 'none';
                }
            });
            const label = name || 'Per Departemen';
            const lblEl = document.getElementById('filterBtnLabel'); if (lblEl) lblEl.innerText = label;
            const panel = document.getElementById('filterPanel'); if (panel) panel.classList.add('hidden');
        }

        function filterByDivisi(id, name) {
            const selId = Number(String(id).trim());
            _getItems().forEach(i => {
                const target = (i.getAttribute('data-target') || '').trim();
                const divAttr = (i.getAttribute('data-divisi-id') || '').trim();
                const divNum = divAttr === '' ? null : Number(divAttr);
                // show only items that explicitly target this divisi
                if (target === 'divisi' && divNum !== null && divNum === selId) {
                    i.style.display = '';
                } else {
                    i.style.display = 'none';
                }
            });
            const label = name || 'Per Divisi';
            const lblEl = document.getElementById('filterBtnLabel'); if (lblEl) lblEl.innerText = label;
            const panel = document.getElementById('filterPanel'); if (panel) panel.classList.add('hidden');
        }

        function openEditPengumuman(itemId) {
            const item = document.getElementById(itemId);
            if (!item) return;
            const judul = item.getAttribute('data-judul') || '';
            const konten = item.getAttribute('data-konten') || '';
            const target = item.getAttribute('data-target') || 'semua';
            const deptId = item.getAttribute('data-departemen-id') || '';
            const divId = item.getAttribute('data-divisi-id') || '';

            document.getElementById('inputJudul').value = judul;
            document.getElementById('inputKonten').value = konten;
            document.getElementById('inputTarget').value = target;
            handleTargetChange();
            if (target === 'departemen' && deptId) document.getElementById('selectDept').value = deptId;
            if (target === 'divisi' && divId) document.getElementById('selectDivisi').value = divId;

            // set form action to update and id
            const formAction = document.getElementById('pengumumanAction');
            const formId = document.getElementById('pengumumanId');
            if (formAction) formAction.value = 'update_pengumuman';
            // itemId is 'p-<id>'
            const realId = itemId.replace(/^p-/, '');
            if (formId) formId.value = realId;

            toggleModal(true);
        }

        function deleteItem(id) {
            if (!confirm('Hapus pengumuman ini?')) return;
            // submit delete via hidden form to server
            let f = document.getElementById('formDeletePengumuman');
            if (!f) {
                f = document.createElement('form');
                f.method = 'POST';
                f.style.display = 'none';
                f.id = 'formDeletePengumuman';
                const a = document.createElement('input'); a.type = 'hidden'; a.name = 'action'; a.value = 'delete_pengumuman'; f.appendChild(a);
                const b = document.createElement('input'); b.type = 'hidden'; b.name = 'id'; b.id = 'deletePengumumanId'; f.appendChild(b);
                document.body.appendChild(f);
            }
            document.getElementById('deletePengumumanId').value = id.replace(/^p-/, '');
            f.submit();
        }

        function kirimPengumuman() {
            const judul = document.getElementById('inputJudul').value;
            const konten = document.getElementById('inputKonten').value;
            if (!judul || !konten) { alert('Judul dan Konten wajib diisi!'); return; }
            // submit the form to server (form has proper names)
            const form = document.getElementById('formPengumuman');
            if (!form) return;
            form.submit();
        }
    </script>
</body>
</html>
