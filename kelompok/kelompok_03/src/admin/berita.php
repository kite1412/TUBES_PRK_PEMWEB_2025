<?php
require_once __DIR__ . '/../config/db.php';
// Date helper (Bahasa Indonesia)
require_once __DIR__ . '/../helpers/date_helper.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error = null;
$berita_list = [];
// Handle create/update/delete for berita
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // ensure files dir exists
    $filesDir = __DIR__ . '/../files';
    if (!is_dir($filesDir)) @mkdir($filesDir, 0755, true);

    if ($action === 'create_berita' || $action === 'update_berita') {
        $isUpdate = $action === 'update_berita';
        $id = $isUpdate ? (int)($_POST['id'] ?? 0) : null;
        $judul = trim((string)($_POST['judul'] ?? ''));
        $isi = trim((string)($_POST['isi'] ?? ''));

        if ($judul === '' || $isi === '') {
            $error = 'Judul dan isi wajib diisi.';
        } else {
            try {
                // handle upload if provided with validation (only JPG/PNG)
                $thumbnailPath = null;
                if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['thumbnail'];
                    $orig = $file['name'];
                    $tmp = $file['tmp_name'];
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

                    // allowed extensions
                    $allowedExt = ['jpg', 'jpeg', 'png'];
                    if (!in_array($ext, $allowedExt, true)) {
                        throw new Exception('Hanya file gambar JPG atau PNG yang diperbolehkan.');
                    }

                    // validate mime type (finfo preferred, fallback to getimagesize)
                    $mime = null;
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo) {
                            $mime = finfo_file($finfo, $tmp);
                            finfo_close($finfo);
                        }
                    }
                    if ($mime === null && function_exists('mime_content_type')) {
                        $mime = mime_content_type($tmp);
                    }
                    if ($mime === null && function_exists('getimagesize')) {
                        $info = getimagesize($tmp);
                        $mime = $info['mime'] ?? null;
                    }
                    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                        throw new Exception('Tipe file tidak valid. Hanya JPG/PNG diperbolehkan.');
                    }

                    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
                    $name = time() . '_' . uniqid() . '_' . $safe . ($ext ? '.' . $ext : '');
                    $dest = $filesDir . '/' . $name;
                    if (!@move_uploaded_file($tmp, $dest)) {
                        throw new Exception('Gagal menyimpan file upload.');
                    }
                    // store relative path
                    $thumbnailPath = 'src/files/' . $name;
                }

                $now = date('Y-m-d H:i:s');
                if ($isUpdate && $id) {
                    // if thumbnail not uploaded, keep existing
                    if ($thumbnailPath === null) {
                        $row = db_fetch('SELECT thumbnail FROM berita WHERE id = :id', ['id' => $id]);
                        $thumbnailPath = $row['thumbnail'] ?? null;
                    }
                    db_execute('UPDATE berita SET judul = :judul, isi = :isi, thumbnail = :thumbnail, updated_at = :updated_at WHERE id = :id', [
                        'judul' => $judul,
                        'isi' => $isi,
                        'thumbnail' => $thumbnailPath,
                        'updated_at' => $now,
                        'id' => $id,
                    ]);
                } else {
                    // For new articles, set updated_at = NULL
                    db_execute('INSERT INTO berita (judul, isi, thumbnail, created_at, updated_at) VALUES (:judul, :isi, :thumbnail, :created_at, :updated_at)', [
                        'judul' => $judul,
                        'isi' => $isi,
                        'thumbnail' => $thumbnailPath,
                        'created_at' => $now,
                        'updated_at' => null,
                    ]);
                }
                header('Location: berita.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Gagal menyimpan berita: ' . $ex->getMessage();
            }
        }
    } elseif ($action === 'delete_berita') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // try to remove associated thumbnail file from disk if present
                $rowThumb = db_fetch('SELECT thumbnail FROM berita WHERE id = :id', ['id' => $id]);
                if (!empty($rowThumb['thumbnail'])) {
                    $thumb = $rowThumb['thumbnail'];
                    if (strpos($thumb, 'src/files/') === 0) {
                        $basename = substr($thumb, strlen('src/files/'));
                        $filePath = __DIR__ . '/../files/' . $basename;
                        if (is_file($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
                db_execute('DELETE FROM berita WHERE id = :id', ['id' => $id]);
                header('Location: berita.php');
                exit;
            } catch (Exception $ex) {
                $error = 'Gagal menghapus berita: ' . $ex->getMessage();
            }
        } else {
            $error = 'ID berita tidak valid.';
        }
    }
}
try {
    $berita_list = db_fetch_all('SELECT * FROM berita ORDER BY created_at DESC');
    $total = count($berita_list);

    // Count berita created this month (current year & month)
    $row = db_fetch("SELECT COUNT(*) as c FROM berita WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())");
    $berita_this_month = isset($row['c']) ? (int)$row['c'] : 0;

    // Count berita that have been updated (updated_at IS NOT NULL)
    $row2 = db_fetch("SELECT COUNT(*) as c FROM berita WHERE updated_at IS NOT NULL");
    $berita_updated_count = isset($row2['c']) ? (int)$row2['c'] : 0;

} catch (Exception $ex) {
    $error = $ex->getMessage();
    $total = 0;
    $berita_this_month = 0;
    $berita_updated_count = 0;
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
    <script>
        // Client-side search filter for berita by judul (case-insensitive)
        (function(){
            function initBeritaSearch(){
                const inp = document.getElementById('searchBerita');
                const listRoot = document.getElementById('beritaList');
                if (!inp || !listRoot) return;
                function applyFilter() {
                    const q = (inp.value || '').trim().toLowerCase();
                    const items = Array.from(listRoot.querySelectorAll('div[id^="berita-"]'));
                    let visible = 0;
                    items.forEach(it => {
                        const title = (it.getAttribute('data-judul') || '').toLowerCase();
                        if (q === '' || title.indexOf(q) !== -1) {
                            it.style.display = '';
                            visible++;
                        } else {
                            it.style.display = 'none';
                        }
                    });
                    const totalEl = document.getElementById('totalBerita');
                    if (totalEl) {
                        if (q === '') {
                            totalEl.textContent = '<?php echo e($total); ?>';
                        } else {
                            totalEl.textContent = String(visible);
                        }
                    }
                }
                inp.addEventListener('input', applyFilter);
                // run once to initialize
                applyFilter();
            }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initBeritaSearch); else initBeritaSearch();
        })();
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
                <input id="searchBerita" type="text" placeholder="Cari berita..." class="bg-transparent flex-1 outline-none text-sm text-dark placeholder:text-muted/70">
                <div class="h-8 w-[1px] bg-gray-100"></div>
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
                        <h2 class="text-2xl font-bold text-dark"><?php echo e($berita_this_month ?? 0); ?></h2>
                        <p class="text-xs text-green-500 font-bold mt-1">Artikel Baru</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-calendar-plus"></i></div>
                </div>

                <div class="bg-white p-5 rounded-[20px] shadow-card border-l-4 border-orange-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted font-medium">Update Terakhir</p>
                        <h2 class="text-2xl font-bold text-dark"><?php echo e($berita_updated_count ?? 0); ?></h2>
                        <p class="text-xs text-muted mt-1">Artikel Diperbarui</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-clock-rotate-left"></i></div>
                </div>
            </div>

            <div class="flex justify-end items-center mb-6">
                <button onclick="openCreateBerita()" class="bg-primary hover:bg-blue-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-primary/30 font-semibold text-sm flex items-center gap-2 transition-transform active:scale-95">
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
                            $created = isset($row['created_at']) && $row['created_at'] ? format_date_id($row['created_at'], false) : '';
                            $snippet = mb_substr(strip_tags($isi), 0, 160) . (mb_strlen(strip_tags($isi)) > 160 ? '...' : '');
                            // convert stored thumbnail path (e.g. 'src/files/..') to a URL relative to this file (src/admin)
                            $thumb_url = '';
                            if ($thumb) {
                                if (strpos($thumb, 'src/') === 0) {
                                    // stored as 'src/files/...' -> from src/admin page the correct relative URL is '../files/...'
                                    $thumb_url = '../' . substr($thumb, 4);
                                } else {
                                    $thumb_url = $thumb;
                                }
                            }
                            $img = $thumb_url ? e($thumb_url) : 'https://source.unsplash.com/random/400x300/?news';
                        ?>
                        <div class="group bg-white rounded-[20px] p-4 flex gap-6 shadow-card hover:shadow-soft transition-all cursor-pointer border border-transparent hover:border-primary/20 animate-fade-in" id="berita-<?php echo e($id); ?>" data-judul="<?php echo e($judul); ?>" data-isi="<?php echo e($isi); ?>" data-thumbnail="<?php echo e($thumb_url); ?>">
                        <div class="w-48 h-32 bg-gray-200 rounded-xl overflow-hidden flex-shrink-0 relative">
                            <?php if ($thumb_url): ?>
                                <img src="<?php echo $img; ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-sm text-muted">Tidak Ada Thumbnail</div>
                            <?php endif; ?>
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
                            <button onclick="openEditBerita('<?php echo e($id); ?>')" class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-primary hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-pen"></i></button>
                            <button onclick='confirmDeleteBerita(<?php echo e($id); ?>, <?php echo json_encode($judul); ?>)' class="w-10 h-10 rounded-xl bg-gray-50 text-muted hover:bg-red-500 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-trash"></i></button>
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

            <form id="formBerita" method="post" action="berita.php" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="action" id="beritaAction" value="create_berita">
                <input type="hidden" name="id" id="beritaId" value="">
                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Judul Berita</label>
                    <input type="text" id="inputJudul" name="judul" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Judul yang menarik...">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Thumbnail (Gambar Sampul)</label>
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 flex flex-col items-center justify-center text-muted hover:bg-gray-50 transition cursor-pointer relative">
                            <input type="file" id="inputThumbnail" name="thumbnail" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="absolute inset-0 opacity-0 cursor-pointer">
                            <img id="previewThumbnail" src="" alt="Preview" class="hidden w-full h-40 object-cover rounded-md mb-3">
                            <i class="fa-solid fa-image text-3xl mb-2"></i>
                            <span class="text-xs">Klik untuk upload gambar</span>
                            <div id="inputThumbnailError" class="text-sm text-red-600 mt-2 hidden"></div>
                        </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark uppercase mb-1">Isi Berita</label>
                    <textarea id="inputIsi" name="isi" rows="6" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-primary transition" placeholder="Tulis konten berita di sini..."></textarea>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="toggleModal(false)" class="flex-1 bg-gray-100 hover:bg-gray-200 text-dark font-bold py-4 rounded-xl transition">Batal</button>
                    <button type="submit" class="flex-1 bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform active:scale-95">Publikasikan</button>
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

        function openEditBerita(id) {
            const item = document.getElementById('berita-' + id);
            if (!item) return;
            const judul = item.getAttribute('data-judul') || '';
            const isi = item.getAttribute('data-isi') || '';
            const thumb = item.getAttribute('data-thumbnail') || '';
            // set form
            document.getElementById('inputJudul').value = judul;
            document.getElementById('inputIsi').value = isi;
            document.getElementById('beritaAction').value = 'update_berita';
            document.getElementById('beritaId').value = id;
            // show existing thumbnail preview if any
            const preview = document.getElementById('previewThumbnail');
            const thumbErr = document.getElementById('inputThumbnailError');
            if (thumb) {
                preview.src = thumb;
                preview.classList.remove('hidden');
                thumbErr.classList.add('hidden');
            } else {
                preview.src = '';
                preview.classList.add('hidden');
                thumbErr.classList.add('hidden');
            }
            // clear file input value so re-selecting same file triggers change
            const fileInput = document.getElementById('inputThumbnail');
            if (fileInput) fileInput.value = '';
            toggleModal(true);
        }

        function openCreateBerita() {
            document.getElementById('formBerita').reset();
            document.getElementById('beritaAction').value = 'create_berita';
            document.getElementById('beritaId').value = '';
            const preview = document.getElementById('previewThumbnail');
            const thumbErr = document.getElementById('inputThumbnailError');
            if (preview) { preview.src = ''; preview.classList.add('hidden'); }
            if (thumbErr) { thumbErr.innerText = ''; thumbErr.classList.add('hidden'); }
            const fileInput = document.getElementById('inputThumbnail');
            if (fileInput) fileInput.value = '';
            toggleModal(true);
        }

        function confirmDeleteBerita(id, judul) {
            if (!confirm("Hapus berita '" + (judul || '') + "' ?")) return;
            let f = document.getElementById('formDeleteBerita');
            if (!f) {
                f = document.createElement('form');
                f.method = 'POST';
                f.style.display = 'none';
                f.id = 'formDeleteBerita';
                const a = document.createElement('input'); a.type = 'hidden'; a.name = 'action'; a.value = 'delete_berita'; f.appendChild(a);
                const b = document.createElement('input'); b.type = 'hidden'; b.name = 'id'; b.id = 'deleteBeritaId'; f.appendChild(b);
                document.body.appendChild(f);
            }
            document.getElementById('deleteBeritaId').value = id;
            f.submit();
        }

        // Client-side file preview & validation
        (function(){
            const fileInput = document.getElementById('inputThumbnail');
            const preview = document.getElementById('previewThumbnail');
            const errEl = document.getElementById('inputThumbnailError');
            const form = document.getElementById('formBerita');
            const allowedExt = ['jpg','jpeg','png'];

            function validateFile(file) {
                if (!file) return null;
                const name = file.name || '';
                const size = file.size || 0;
                const ext = (name.split('.').pop() || '').toLowerCase();
                if (!allowedExt.includes(ext)) return 'Hanya file JPG atau PNG yang diperbolehkan.';
                if (!file.type || (file.type !== 'image/jpeg' && file.type !== 'image/png')) return 'Tipe file tidak valid.';
                return null;
            }

            if (fileInput) {
                fileInput.addEventListener('change', function(e){
                    const f = fileInput.files && fileInput.files[0];
                    const v = validateFile(f);
                    if (v) {
                        errEl.innerText = v;
                        errEl.classList.remove('hidden');
                        if (preview) { preview.src = ''; preview.classList.add('hidden'); }
                        return;
                    }
                    errEl.innerText = '';
                    errEl.classList.add('hidden');
                    if (f && preview) {
                        const reader = new FileReader();
                        reader.onload = function(ev){ preview.src = ev.target.result; preview.classList.remove('hidden'); };
                        reader.readAsDataURL(f);
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function(e){
                    const f = fileInput && fileInput.files && fileInput.files[0];
                    const v = validateFile(f);
                    if (v) {
                        e.preventDefault();
                        errEl.innerText = v;
                        errEl.classList.remove('hidden');
                        if (preview) { preview.src = ''; preview.classList.add('hidden'); }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            }
        })();
    </script>
</body>
</html>
