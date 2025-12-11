<?php
session_start();
require_once __DIR__ . '/config/db.php';
$dbh = get_db();
require_once __DIR__ . '/helpers/date_helper.php';

// Helper: resolve file server path and appropriate URL to embed in <img>
function file_url_and_path(?string $filename) {
  $filename = (string)$filename;
  $filename = trim($filename);
  if ($filename === '') return ['path' => null, 'url' => null];

  // Normalize: remove leading ./, ../, slashes and common prefixes like 'src/files/', 'files/', or 'uploads/'
  $name = preg_replace('#^(?:\\.|\\.\\.|[\\/]+)+#', '', $filename);
  $name = preg_replace('#^(?:src[\\/])?(?:files[\\/]|uploads[\\/])#i', '', $name);
  $name = ltrim($name, '/\\');

  $candidates = [
    __DIR__ . '/files/' . $name,
    __DIR__ . '/../files/' . $name,
    __DIR__ . '/uploads/' . $name,
    __DIR__ . '/../uploads/' . $name,
  ];

  foreach ($candidates as $path) {
    if (file_exists($path)) {
      // normalize slashes for comparisons
      $pNorm = str_replace('\\', '/', $path);
      $dirNorm = str_replace('\\', '/', __DIR__);

      if (strpos($pNorm, $dirNorm . '/files/') === 0) {
        return ['path' => $path, 'url' => 'files/' . $name];
      }
      if (strpos($pNorm, $dirNorm . '/../files/') === 0) {
        return ['path' => $path, 'url' => '../files/' . $name];
      }
      if (strpos($pNorm, $dirNorm . '/uploads/') === 0) {
        return ['path' => $path, 'url' => 'uploads/' . $name];
      }
      if (strpos($pNorm, $dirNorm . '/../uploads/') === 0) {
        return ['path' => $path, 'url' => '../uploads/' . $name];
      }

      // fallback: use plain name
      return ['path' => $path, 'url' => $name];
    }
  }
  return ['path' => null, 'url' => null];
}

$isLoggedIn = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? null;
$username = $_SESSION['username'] ?? '';
$anggota_nama = $_SESSION['anggota_nama'] ?? '';

// Get user initials for profile circle (max 2 chars)
$userDisplay = '';
if ($isLoggedIn) {
    $userDisplay = strtoupper(substr($username, 0, 2));
}

// Mock organization identity (can be replaced by DB-driven settings later)
$org = [
  'name' => 'Organisasi Mahasiswa',
  'tagline' => 'Mewadahi aspirasi mahasiswa, mengembangkan kepemimpinan, dan menyelenggarakan kegiatan kampus',
  'address' => 'Jl. Prof. Sumantri Brojonegoro No.1, Gedong Meneng, Kecamatan Rajabasa, Kota Bandar Lampung, Lampung',
  'contact' => 'organisasimahasiswa@gmail.com',
];

// Fetch latest berita (3)
$latestBerita = [];
try {
    $stmt = $dbh->prepare("SELECT id, judul, isi, thumbnail, created_at FROM berita ORDER BY created_at DESC LIMIT 3");
    $stmt->execute();
    $latestBerita = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $latestBerita = []; }

// Fetch recent kegiatan (3)
$kegiatan = [];
try {
    $stmt = $dbh->prepare("SELECT id, judul AS nama, tanggal, deskripsi FROM kegiatan ORDER BY tanggal DESC LIMIT 3");
    $stmt->execute();
    $kegiatan = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $kegiatan = []; }

// Fetch all kegiatan publik (for the full list section)
$kegiatan_public = [];
try {
  $stmt = $dbh->prepare("SELECT id, judul AS nama, tanggal, deskripsi FROM kegiatan WHERE is_public = 1 ORDER BY tanggal DESC");
  $stmt->execute();
  $kegiatan_public = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // fallback to all kegiatan if is_public column missing
  try {
    $stmt = $dbh->prepare("SELECT id, judul AS nama, tanggal, deskripsi FROM kegiatan ORDER BY tanggal DESC");
    $stmt->execute();
    $kegiatan_public = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $kegiatan_public = [];
  }
}

// Struktur inti (organization-level jabatan)
$struktur_inti = [];
try {
    $stmt = $dbh->prepare(
        "SELECT a.id AS anggota_id, a.nama, a.foto, j.nama AS jabatan_name
         FROM anggota_jabatan aj
         JOIN anggota a ON aj.anggota_id = a.id
         JOIN jabatan j ON aj.jabatan_id = j.id
         WHERE aj.departemen_id IS NULL AND aj.divisi_id IS NULL
         ORDER BY j.id ASC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $fotoPath = file_url_and_path($r['foto'] ?? '');
        $struktur_inti[] = [
            'jabatan' => $r['jabatan_name'] ?? 'Anggota',
            'nama' => $r['nama'],
            'foto' => $fotoPath['url']
        ];
    }
} catch (Exception $e) { $struktur_inti = []; }

// Also fetch the four canonical organization-level roles explicitly
$struktur_org_roles = [
  'ketua' => null,
  'wakil_ketua' => null,
  'sekretaris' => null,
  'bendahara' => null,
];
try {
  $wantedNames = [
    'Ketua Organisasi', 'Ketua',
    'Wakil Ketua Organisasi', 'Wakil Ketua',
    'Sekretaris Organisasi', 'Sekretaris',
    'Bendahara Organisasi', 'Bendahara'
  ];

  $mapToKey = [
    'Ketua Organisasi' => 'ketua', 'Ketua' => 'ketua',
    'Wakil Ketua Organisasi' => 'wakil_ketua', 'Wakil Ketua' => 'wakil_ketua',
    'Sekretaris Organisasi' => 'sekretaris', 'Sekretaris' => 'sekretaris',
    'Bendahara Organisasi' => 'bendahara', 'Bendahara' => 'bendahara'
  ];

  $placeholders = rtrim(str_repeat('?,', count($wantedNames)), ',');
  $sql = "
    SELECT a.id AS anggota_id, a.nama, a.foto, j.nama AS jabatan_name
    FROM anggota_jabatan aj
    JOIN anggota a ON aj.anggota_id = a.id
    JOIN jabatan j ON aj.jabatan_id = j.id
    WHERE j.nama IN ($placeholders)
      AND (aj.departemen_id IS NULL OR aj.departemen_id = 0)
      AND (aj.divisi_id IS NULL OR aj.divisi_id = 0)
    ORDER BY j.id ASC
  ";
  $stmt = $dbh->prepare($sql);
  $stmt->execute($wantedNames);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $r) {
    $jab = $r['jabatan_name'] ?? '';
    if (isset($mapToKey[$jab])) {
      $key = $mapToKey[$jab];
      if ($struktur_org_roles[$key] === null) {
        $fotoPath = file_url_and_path($r['foto'] ?? '');
        $struktur_org_roles[$key] = [
          'anggota_id' => (int)($r['anggota_id'] ?? 0),
          'nama' => $r['nama'] ?? '',
          'foto' => $fotoPath['url'],
          'jabatan' => $jab
        ];
      }
    }
  }
} catch (Exception $e) {

}

try {
  $stmt = $dbh->prepare("SELECT id, judul, isi, thumbnail, created_at FROM berita ORDER BY created_at DESC");
  $stmt->execute();
  $allBerita = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $allBerita = []; }

// Fetch departemen & divisi
$departemen = [];
$divisi = [];
try {
  $d = $dbh->query("SELECT id, nama, deskripsi FROM departemen ORDER BY nama");
  $departemen = $d->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $departemen = []; }
try {
  $dv = $dbh->query("SELECT id, departemen_id, nama, deskripsi FROM divisi ORDER BY nama");
  $divisi = $dv->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $divisi = []; }

// compute number of divisi per departemen for enhanced departemen cards
$divisi_counts = [];
foreach ($divisi as $dv_item) {
  $deptId = isset($dv_item['departemen_id']) ? (int)$dv_item['departemen_id'] : 0;
  if ($deptId > 0) {
    if (!isset($divisi_counts[$deptId])) $divisi_counts[$deptId] = 0;
    $divisi_counts[$deptId]++;
  }
}

// Detail berita when ?id= is provided
$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$beritaDetail = null;
if ($detailId > 0) {
  try {
    $stmt = $dbh->prepare("SELECT id, judul, isi, thumbnail, created_at FROM berita WHERE id = ? LIMIT 1");
    $stmt->execute([$detailId]);
    $beritaDetail = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) { $beritaDetail = null; }
}

?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($org['name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* small helpers to match the reference look */
    .hero-bg { background: linear-gradient(180deg,#0b4ea1 0%, #0a4a95 60%); }
    .card-shadow { box-shadow: 0 10px 30px rgba(2,6,23,0.08); transition: transform .18s ease, box-shadow .18s ease; }
    .section-underline { height: 4px; width: 56px; background: #0466c8; border-radius: 4px; margin-top: .5rem; }
    .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
    .modal.active { display: flex; align-items: center; justify-content: center; }
    .modal-content { background-color: white; margin: auto; padding: 0; border-radius: 0.5rem; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto; }
  </style>
  <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: { primary: '#0466c8', canvas: '#F4F7FE', dark: '#2B3674', muted: '#A3AED0' }
                }
            }
        }
    </script>
    <style>body { background: #F4F7FE; font-family: Poppins, sans-serif; }</style>
</head>
<body class="antialiased text-slate-800">
  <!-- Navbar -->
  <nav class="hero-bg text-white">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <div>
          <div class="text-lg font-bold"><span class="text-yellow-300">Organisasi</span> Mahasiswa</div>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <?php if (!$isLoggedIn): ?>
          <a href="login/login.php" class="ml-4 bg-white text-blue-700 px-4 py-2 rounded-md font-semibold">Masuk</a>
        <?php else: ?>
          <div class="flex items-center gap-3">
            <?php if ($role === 'admin'): ?>
              <a href="admin/anggota.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-md">Admin</a>
            <?php else: ?>
              <a href="login/anggota/profil.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-md">Profil</a>
            <?php endif; ?>
          </div>
          <a href="login/logout.php" class="ml-2 bg-red-500 px-3 py-2 rounded-md text-white">Keluar</a>
          <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-blue-700 text-lg">
            <i class="fa-solid fa-user"></i>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <header class="hero-bg text-white">
    <div class="max-w-6xl mx-auto px-6 py-16 text-center">
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4"><span class="text-yellow-300">ORGANISASI</span> MAHASISWA</h1>
      <p class="max-w-2xl mx-auto text-lg md:text-xl opacity-90 mb-6"><?= htmlspecialchars($org['tagline']) ?></p>

      <div class="mt-10 max-w-3xl mx-auto">
        <div class="bg-white/10 rounded-xl card-shadow">
          <div class="h-80 rounded-md bg-cover bg-center" style="background-image:url('assets/rektorat.png')"></div>
        </div>
      </div>
    </div>
  </header>

  <main class="mt-12">
    <div class="max-w-6xl mx-auto px-6">
      <section class="flex flex-col gap-12 mb-20">
        <div class="bg-transparent border border-2 border-primary rounded-lg p-6">
          <div class="text-2xl font-bold text-primary text-center"><i class="fa-solid fa-eye mr-2 text-primary"></i>Visi</div>
          <p class="text-md text-slate-600 mt-4 text-center font-bold">Menjadi wadah pengembangan kepemimpinan dan advokasi mahasiswa yang profesional, inklusif, dan berdampak positif bagi kampus dan masyarakat.</p>
        </div>
        <div class="bg-transparent border border-2 border-primary rounded-lg p-6">
          <div class="text-2xl font-bold text-primary text-center"><i class="fa-solid fa-bullseye mr-2 text-primary"></i>Misi</div>
          <ul class="text-md text-slate-600 mt-4 list-disc list-inside font-bold">
            <li>Mengembangkan kapasitas kepemimpinan, keterampilan organisasi, dan karakter mahasiswa.</li>
            <li>Menyalurkan aspirasi dan melakukan advokasi kepentingan mahasiswa secara transparan dan akuntabel.</li>
            <li>Menyelenggarakan kegiatan akademik, sosial, dan budaya yang memberdayakan anggota dan komunitas kampus.</li>
          </ul>
        </div>
      </section>

      <!-- Struktur inti & Organisasi -->
      <section class="mb-20">
        <?php if (!empty($struktur_inti)): ?>
        <h2 class="text-2xl font-bold mb-4 text-primary"><i class="fa-solid fa-sitemap mr-2 text-primary"></i>Struktur Inti Organisasi</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
          <?php foreach ($struktur_inti as $s): ?>
            <div class="bg-white rounded-lg overflow-hidden text-center card-shadow">
              <?php if (!empty($s['foto'])): ?>
                <img src="<?= htmlspecialchars($s['foto']) ?>" alt="<?= htmlspecialchars($s['nama']) ?>" class="w-full h-56 object-cover">
              <?php else: ?>
                <div class="w-full h-56 bg-slate-100 flex items-center justify-center">No image</div>
              <?php endif; ?>
              <div class="p-4">
                <div class="font-semibold text-xl"><?= htmlspecialchars($s['jabatan']) ?></div>
                <div class="text-sm text-slate-600"><?= htmlspecialchars($s['nama']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div>
          <h3 class="text-2xl font-bold mb-4 text-primary"><i class="fa-solid fa-layer-group mr-2 text-primary"></i>Departemen</h3>
          <?php if (!empty($departemen)): ?>
            <div class="grid md:grid-cols-2 gap-6">
              <?php foreach ($departemen as $d): ?>
                <?php $count = $divisi_counts[$d['id']] ?? 0; ?>
                <div class="bg-blue-50 rounded-lg p-4 card-shadow flex flex-col justify-between">
                  <div>
                    <div class="flex items-start justify-between">
                      <div>
                        <div class="font-semibold text-lg"><?= htmlspecialchars($d['nama']) ?></div>
                        <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($d['deskripsi'] ?? '') ?></p>
                      </div>
                      <div class="ml-4 text-sm text-blue-700">
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-blue-100 rounded-full">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v4a1 1 0 001 1h3m10-6v4a1 1 0 01-1 1h-3m-6 4h6"/></svg>
                          <span><?= (int)$count ?> Divisi</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="bg-white rounded-lg p-4 card-shadow">Belum ada data departemen.</div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Nilai Organisasi -->
      <section class="mb-20">
        <h2 class="text-2xl font-bold mb-4 text-primary"><i class="fa-solid fa-heart mr-2 text-primary"></i>Nilai Organisasi</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div class="bg-white rounded-lg p-6 card-shadow text-center flex flex-col items-center">
            <i class="fa-solid fa-shield text-3xl text-primary mb-3"></i>
            <div class="font-semibold text-lg mb-2">Integritas</div>
            <p class="text-sm text-slate-600">Menjunjung tinggi kejujuran, tanggung jawab, dan etika dalam setiap tindakan dan keputusan.</p>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow text-center flex flex-col items-center">
            <i class="fa-solid fa-handshake text-3xl text-primary mb-3"></i>
            <div class="font-semibold text-lg mb-2">Kolaborasi</div>
            <p class="text-sm text-slate-600">Percaya bahwa hasil terbaik muncul dari kerja sama yang kuat dan saling menghargai antar anggota.</p>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow text-center flex flex-col items-center">
            <i class="fa-solid fa-lightbulb text-3xl text-primary mb-3"></i>
            <div class="font-semibold text-lg mb-2">Inovasi</div>
            <p class="text-sm text-slate-600">Mendorong ide-ide baru, pendekatan kreatif, serta keberanian untuk bereksperimen dalam mencapai tujuan.</p>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow text-center flex flex-col items-center">
            <i class="fa-solid fa-star text-3xl text-primary mb-3"></i>
            <div class="font-semibold text-lg mb-2">Profesionalisme</div>
            <p class="text-sm text-slate-600">Menjalankan tugas dengan disiplin, konsisten, dan berorientasi pada kualitas.</p>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow text-center flex flex-col items-center">
            <i class="fa-solid fa-people-group text-3xl text-primary mb-3"></i>
            <div class="font-semibold text-lg mb-2">Kebermanfaatan</div>
            <p class="text-sm text-slate-600">Setiap program dan kegiatan diarahkan untuk memberikan dampak nyata bagi anggota dan masyarakat.</p>
          </div>
        </div>
      </section>

      <!-- Prinsip Kerja Organisasi -->
      <section class="mb-20">
        <h2 class="text-2xl font-bold mb-4 text-primary"><i class="fa-solid fa-compass mr-2 text-primary"></i>Prinsip Kerja Organisasi</h2>
        <div class="space-y-4">
          <div class="bg-white rounded-lg p-6 card-shadow flex gap-4">
            <div class="flex-shrink-0">
              <i class="fa-solid fa-eye text-2xl text-primary mt-1"></i>
            </div>
            <div>
              <div class="font-semibold text-lg">Transparansi dalam proses dan keputusan</div>
              <p class="text-slate-600 mt-1">Setiap informasi penting dan alur kerja disampaikan secara terbuka demi membangun kepercayaan internal.</p>
            </div>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow flex gap-4">
            <div class="flex-shrink-0">
              <i class="fa-solid fa-person-hiking text-2xl text-primary mt-1"></i>
            </div>
            <div>
              <div class="font-semibold text-lg">Tanggung jawab pada setiap peran</div>
              <p class="text-slate-600 mt-1">Setiap anggota memegang peran strategis yang harus dijalankan sepenuh hati dan dengan kesadaran tanggung jawab.</p>
            </div>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow flex gap-4">
            <div class="flex-shrink-0">
              <i class="fa-solid fa-star text-2xl text-primary mt-1"></i>
            </div>
            <div>
              <div class="font-semibold text-lg">Berorientasi pada hasil</div>
              <p class="text-slate-600 mt-1">Setiap program dijalankan dengan tujuan jelas, indikator keberhasilan, serta evaluasi berkala.</p>
            </div>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow flex gap-4">
            <div class="flex-shrink-0">
              <i class="fa-solid fa-comments text-2xl text-primary mt-1"></i>
            </div>
            <div>
              <div class="font-semibold text-lg">Komunikasi yang efektif</div>
              <p class="text-slate-600 mt-1">Mengutamakan komunikasi yang jelas, sopan, dan tepat waktu untuk mendukung kerja tim yang sehat.</p>
            </div>
          </div>
          <div class="bg-white rounded-lg p-6 card-shadow flex gap-4">
            <div class="flex-shrink-0">
              <i class="fa-solid fa-arrows-rotate text-2xl text-primary mt-1"></i>
            </div>
            <div>
              <div class="font-semibold text-lg">Perbaikan berkelanjutan</div>
              <p class="text-slate-600 mt-1">Selalu melakukan evaluasi dan peningkatan agar organisasi berkembang lebih baik dari waktu ke waktu.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Latest news + kegiatan -->
      <section class="mb-20">
        <div class="flex flex-col md:flex-row gap-8">
          <!-- Berita Terkini -->
          <div class="md:w-[60%]">
            <h3 class="text-2xl font-bold mb-4 text-primary"><i class="fa-solid fa-newspaper mr-2 text-primary"></i>Berita Terkini</h3>
            <?php if (!empty($latestBerita)): ?>
              <div class="space-y-4">
                <?php foreach ($latestBerita as $b): ?>
                  <?php
                    $thumb = $b['thumbnail'] ?? '';
                    $thumb_url = '';
                    if ($thumb) {
                      if (strpos($thumb, 'src/') === 0) {
                        $thumb_url = '../' . substr($thumb, 4);
                      } else {
                        $thumb_url = $thumb;
                      }
                    }
                  ?>
                  <div class="bg-white rounded-lg p-4 card-shadow cursor-pointer hover:shadow-lg transition-shadow" onclick="showBeritaModal(<?= (int)$b['id'] ?>)">
                    <div class="flex gap-4">
                      <?php if ($thumb_url): ?>
                        <img src="<?= htmlspecialchars($thumb_url) ?>" class="w-32 h-24 object-cover rounded-md flex-shrink-0" alt="">
                      <?php else: ?>
                        <div class="w-32 h-24 bg-slate-100 rounded-md flex-shrink-0"></div>
                      <?php endif; ?>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-blue-700 hover:text-blue-900"><?= htmlspecialchars($b['judul']) ?></div>
                        <div class="text-sm text-slate-500 mt-1"><?= htmlspecialchars(format_date_id($b['created_at'], false)) ?></div>
                        <p class="text-sm text-slate-600 mt-2 line-clamp-2"><?= htmlspecialchars(substr(strip_tags($b['isi'] ?? ''), 0, 160)) ?><?= (strlen(strip_tags($b['isi'] ?? ''))>160)?'...':'' ?></p>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="bg-white rounded-lg p-4 card-shadow">Belum ada berita.</div>
            <?php endif; ?>
          </div>

          <!-- Kegiatan Publik -->
          <div class="md:w-[40%]">
            <h4 class="text-2xl font-bold mb-4 text-primary"><i class="fa-solid fa-calendar-days mr-2 text-primary"></i>Kegiatan Publik</h4>
            <?php if (!empty($kegiatan)): ?>
              <div class="space-y-4">
                <?php foreach ($kegiatan as $k): ?>
                  <?php
                    $now = new DateTime();
                    $tanggal = new DateTime($k['tanggal']);
                    $isPast = $tanggal < $now;
                  ?>
                  <div class="bg-white rounded-lg p-4 card-shadow <?= $isPast ? 'opacity-60' : '' ?>">
                    <div class="flex gap-4">
                      <div class="w-32 h-24 <?= $isPast ? 'bg-gray-100' : 'bg-blue-50' ?> rounded-md flex-shrink-0 flex items-center justify-center">
                        <i class="fa-solid <?= $isPast ? 'fa-calendar-xmark text-gray-400' : 'fa-calendar-check text-primary' ?> text-3xl"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="font-semibold text-lg <?= $isPast ? 'text-gray-500' : '' ?>"><?= htmlspecialchars($k['nama']) ?></div>
                        <div class="text-sm mt-1 <?= $isPast ? 'text-gray-400' : 'text-slate-500' ?>">
                          <?= htmlspecialchars(format_date_id($k['tanggal'], false)) ?>
                          <?php if ($isPast): ?>
                            <span class="ml-2 text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded">Selesai</span>
                          <?php endif; ?>
                        </div>
                        <p class="text-sm mt-2 line-clamp-2 <?= $isPast ? 'text-gray-400' : 'text-slate-600' ?>"><?= htmlspecialchars(substr($k['deskripsi'] ?? '',0,140)) ?><?= (strlen($k['deskripsi'] ?? '')>140)?'...':'' ?></p>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="bg-white rounded-lg p-4 card-shadow">Belum ada kegiatan publik.</div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- Detail Berita (if requested) -->
      <?php if (!empty($beritaDetail)): ?>
        <section class="mb-12">
          <div class="bg-white rounded-lg card-shadow overflow-hidden">
            <?php
              $bdThumb = $beritaDetail['thumbnail'] ?? '';
              $bdThumb_url = '';
              if ($bdThumb) {
                if (strpos($bdThumb, 'src/') === 0) {
                  $bdThumb_url = '../' . substr($bdThumb, 4);
                } else {
                  $bdThumb_url = $bdThumb;
                }
              }
            ?>
            <?php if ($bdThumb_url): ?>
              <img src="<?= htmlspecialchars($bdThumb_url) ?>" class="w-full h-64 object-cover" alt="">
            <?php endif; ?>
            <div class="p-6">
              <h1 class="text-2xl font-bold mb-2"><?= htmlspecialchars($beritaDetail['judul']) ?></h1>
              <div class="text-sm text-slate-500 mb-4"><?= htmlspecialchars(format_date_id($beritaDetail['created_at'], false)) ?></div>
              <div class="prose max-w-none text-slate-800"><?= $beritaDetail['isi'] ?></div>
              <div class="mt-6"><a href="index.php" class="text-blue-700 font-semibold">&larr; Kembali</a></div>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </main>

  <footer class="bg-primary text-white py-10 mt-12">
    <div class="max-w-7xl mx-auto px-6 text-center">
      <div class="font-bold mb-2"><?= htmlspecialchars($org['name']) ?></div>
      <div class="text-sm opacity-80 flex flex-col gap-2 items-center">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-location-dot"></i>
          <span><?= htmlspecialchars($org['address']) ?></span>
        </div>
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-envelope"></i>
          <a href="mailto:<?= htmlspecialchars($org['contact']) ?>" class="underline"><?= htmlspecialchars($org['contact']) ?></a>
        </div>
      </div>
      <div class="text-xs opacity-60 mt-4">&copy; <?= date('Y') ?> <?= htmlspecialchars($org['name']) ?>. All Rights Reserved.</div>
    </div>
  </footer>

  <!-- Berita Detail Modal -->
  <div id="beritaModal" class="modal">
    <div class="modal-content">
      <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between z-10">
        <h2 class="text-xl font-bold text-primary">Detail Berita</h2>
        <button onclick="closeBeritaModal()" class="text-slate-400 hover:text-slate-600 text-2xl">&times;</button>
      </div>
      <div id="beritaModalBody" class="p-6">
        <div class="text-center py-8"><i class="fa-solid fa-spinner fa-spin text-3xl text-primary"></i></div>
      </div>
    </div>
  </div>

  <script>
    const beritaData = <?= json_encode($latestBerita, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function showBeritaModal(id) {
      const modal = document.getElementById('beritaModal');
      const body = document.getElementById('beritaModalBody');
      
      const berita = beritaData.find(b => b.id == id);
      if (!berita) {
        body.innerHTML = '<div class="text-center py-8 text-slate-500">Berita tidak ditemukan.</div>';
        modal.classList.add('active');
        return;
      }

      let thumb = berita.thumbnail || '';
      let thumb_url = '';
      if (thumb) {
        if (thumb.indexOf('src/') === 0) {
          thumb_url = '../' + thumb.substring(4);
        } else {
          thumb_url = thumb;
        }
      }

      body.innerHTML = `
        ${thumb_url ? `<img src="${escapeHtml(thumb_url)}" class="w-full h-64 object-cover rounded-lg mb-4" alt="">` : ''}
        <h1 class="text-2xl font-bold mb-2">${escapeHtml(berita.judul)}</h1>
        <div class="text-sm text-slate-500 mb-4"><i class="fa-regular fa-clock mr-1"></i>${formatDateId(berita.created_at)}</div>
        <div class="prose max-w-none text-slate-800">${berita.isi}</div>
      `;
      
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeBeritaModal() {
      const modal = document.getElementById('beritaModal');
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }

    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, m => map[m]);
    }

    function formatDateId(dateStr) {
      const date = new Date(dateStr);
      const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      const day = date.getDate();
      const month = months[date.getMonth()];
      const year = date.getFullYear();
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      return `${day} ${month} ${year}, ${hours}:${minutes}`;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('beritaModal');
      if (event.target === modal) {
        closeBeritaModal();
      }
    }

    // Close modal on ESC key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        closeBeritaModal();
      }
    });
  </script>
</body>
</html>