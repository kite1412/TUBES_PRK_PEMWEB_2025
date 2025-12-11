<?php
session_start();
// Pastikan hanya anggota yang login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'anggota') {
    header('Location: ../login.php');
    exit;
}

// Use central DB helper
$dbPath = __DIR__ . '/../../config/db.php';
if (!file_exists($dbPath)) {
    die('Database configuration not found.');
}
require_once $dbPath;
$dbh = get_db();
$user_id = $_SESSION['user_id'];
// Date helper (format dates in Bahasa Indonesia)
require_once __DIR__ . '/../../helpers/date_helper.php';

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
    __DIR__ . '/../uploads/' . $name,
    __DIR__ . '/../../files/' . $name,
    __DIR__ . '/../files/' . $name,
  ];

  foreach ($candidates as $path) {
    if (file_exists($path)) {
      // normalize slashes for comparisons
      $pNorm = str_replace('\\', '/', $path);
      $dirNorm = str_replace('\\', '/', __DIR__);

      if (strpos($pNorm, $dirNorm . '/../uploads/') === 0) {
        return ['path' => $path, 'url' => '../uploads/' . $name];
      }
      if (strpos($pNorm, $dirNorm . '/../../files/') === 0) {
        return ['path' => $path, 'url' => '../../files/' . $name];
      }
      if (strpos($pNorm, $dirNorm . '/../files/') === 0) {
        return ['path' => $path, 'url' => '../files/' . $name];
      }

      // fallback: use plain name
      return ['path' => $path, 'url' => $name];
    }
  }
  return ['path' => null, 'url' => null];
}

// Logout is handled centrally via ../logout.php

// === HANDLE EDIT PROFIL + UPLOAD FOTO ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profil') {
    $nama = trim($_POST['nama']);
    $npm = trim($_POST['npm']);
    $error = null;
    $foto = null;

    if (empty($nama) || empty($npm)) {
        $error = "Nama dan NPM wajib diisi.";
    } else {
        // Proses upload foto jika ada
        if (!empty($_FILES['foto']['name'])) {
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['foto']['size'];

            if (!in_array($file_ext, $allowed_ext)) {
                $error = "Hanya file JPG/PNG yang diizinkan.";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $error = "Ukuran file maksimal 2 MB.";
            } else {
                $foto_name = 'profil_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = '../uploads/' . $foto_name;

                if (!is_dir('../uploads')) {
                    mkdir('../uploads', 0777, true);
                }

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                    $foto = $foto_name;

                    // Hapus foto lama
                    $old_data = $dbh->prepare("SELECT foto FROM anggota WHERE id = ?");
                    $old_data->execute([$user_id]);
                    $old_foto = $old_data->fetchColumn();
                    if ($old_foto && file_exists('../uploads/' . $old_foto)) {
                        unlink('../uploads/' . $old_foto);
                    }
                } else {
                    $error = "Gagal menyimpan foto. Pastikan folder /uploads bisa ditulis.";
                }
            }
        }

        // Simpan ke database jika tidak ada error
        if (!$error) {
            if ($foto) {
                $dbh->prepare("UPDATE anggota SET nama = ?, npm = ?, foto = ? WHERE id = ?")
                    ->execute([$nama, $npm, $foto, $user_id]);
            } else {
                $dbh->prepare("UPDATE anggota SET nama = ?, npm = ? WHERE id = ?")
                    ->execute([$nama, $npm, $user_id]);
            }
            header("Location: profil.php?updated=1");
            exit;
        }
    }
}

// === AMBIL DATA ANGGOTA ===
$stmt = $dbh->prepare("SELECT * FROM anggota WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Anggota tidak ditemukan.");
}

// === AMBIL JABATAN & UNIT ===
 $jabatan_stmt = $dbh->prepare(
     "SELECT j.nama AS jabatan, d.nama AS departemen, di.nama AS divisi, aj.jabatan_id, aj.departemen_id, aj.divisi_id
    FROM anggota_jabatan aj
    LEFT JOIN jabatan j ON aj.jabatan_id = j.id
    LEFT JOIN departemen d ON aj.departemen_id = d.id
    LEFT JOIN divisi di ON aj.divisi_id = di.id
    WHERE aj.anggota_id = ?
");
$jabatan_stmt->execute([$user_id]);
$jabatan_list = $jabatan_stmt->fetchAll(PDO::FETCH_ASSOC);

// Some rows may have NULL for jabatan name (j.nama). If so, fetch missing names by jabatan_id.
$missingJabatanIds = [];
foreach ($jabatan_list as $r) {
    $jid = $r['jabatan_id'] ?? null;
    $jname = trim((string)($r['jabatan'] ?? ''));
    if ($jid && $jname === '') {
        $missingJabatanIds[$jid] = $jid;
    }
}
if (!empty($missingJabatanIds)) {
    $placeholders = implode(',', array_fill(0, count($missingJabatanIds), '?'));
    $sql = "SELECT id, nama FROM jabatan WHERE id IN ($placeholders)";
    $stmt2 = $dbh->prepare($sql);
    $stmt2->execute(array_values($missingJabatanIds));
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $jabMap = [];
    foreach ($rows as $rr) {
        $jabMap[$rr['id']] = $rr['nama'];
    }
    // populate back into $jabatan_list
    foreach ($jabatan_list as &$r) {
        $jid = $r['jabatan_id'] ?? null;
        if ($jid && empty(trim((string)($r['jabatan'] ?? ''))) && isset($jabMap[$jid])) {
            $r['jabatan'] = $jabMap[$jid];
        }
    }
    unset($r);
}

// Build typed display entries for all jabatan rows according to the anggota_jabatan structure
$jabatan_displays = [];
if (!empty($jabatan_list) && is_array($jabatan_list)) {
    foreach ($jabatan_list as $row) {
        $jabName = trim((string)($row['jabatan'] ?? ''));
        $depName = trim((string)($row['departemen'] ?? ''));
        $divName = trim((string)($row['divisi'] ?? ''));

        // Determine role type: organisasi | departemen | divisi
        if ($divName !== '') {
            $type = 'divisi';
        } elseif ($depName !== '') {
            $type = 'departemen';
        } else {
            $type = 'organisasi';
        }

        // Build human-friendly label. If jabatan name is missing, synthesize a generic label using unit info.
        if ($jabName !== '') {
            if ($type === 'divisi') {
                $label = $jabName . ' ' . $divName;
            } elseif ($type === 'departemen') {
                $label = $jabName . ' ' . $depName;
            } else {
                $label = $jabName;
            }
        } else {
            // jabatan name empty: synthesize
            if ($type === 'divisi') {
                $label = 'Anggota Divisi ' . $divName;
            } elseif ($type === 'departemen') {
                $label = 'Anggota Departemen ' . $depName;
            } else {
                $label = 'Anggota Organisasi';
            }
        }

        $jabatan_displays[] = ['label' => $label, 'type' => $type];
    }
}

// Choose primary display by priority: Organisasi -> Departemen -> Divisi
$primary_display = null;
$primary_type = null;
if (!empty($jabatan_displays)) {
    // search for organisasi
    foreach ($jabatan_displays as $entry) {
        if ($entry['type'] === 'organisasi') { $primary_display = $entry['label']; $primary_type = 'organisasi'; break; }
    }
    // if not found, search departemen
    if ($primary_display === null) {
        foreach ($jabatan_displays as $entry) {
            if ($entry['type'] === 'departemen') { $primary_display = $entry['label']; $primary_type = 'departemen'; break; }
        }
    }
    // if still not found, pick first divisi
    if ($primary_display === null) {
        foreach ($jabatan_displays as $entry) {
            if ($entry['type'] === 'divisi') { $primary_display = $entry['label']; $primary_type = 'divisi'; break; }
        }
    }
}

// === AMBIL PENGUMUMAN INTERNAL ===
 $pengumuman_stmt = $dbh->prepare(
         "SELECT p.judul, p.konten, p.tanggal, p.target, p.departemen_id, p.divisi_id,
                         d.nama AS departemen_nama, di.nama AS divisi_nama
        FROM pengumuman p
        LEFT JOIN departemen d ON p.departemen_id = d.id
        LEFT JOIN divisi di ON p.divisi_id = di.id
        WHERE p.target = 'semua'
             OR (p.target = 'departemen' AND p.departemen_id IN (
                        SELECT departemen_id FROM anggota_jabatan WHERE anggota_id = ?
                    ))
             OR (p.target = 'divisi' AND p.divisi_id IN (
                        SELECT divisi_id FROM anggota_jabatan WHERE anggota_id = ?
                    ))
        ORDER BY p.tanggal DESC
");
$pengumuman_stmt->execute([$user_id, $user_id]);
$pengumuman_list = $pengumuman_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Anggota - <?= htmlspecialchars($user['nama'] ?? '') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{ --primary: #0466c8; --muted:#94a3b8; --bg:#f4f7fe; --text:#1f2937 }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background:var(--bg); color:var(--text); line-height:1.6; padding:28px}
        .container{max-width:1100px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(13,38,76,0.08);overflow:hidden}
        header{background:var(--primary);color:#fff;padding:22px 28px;text-align:left}
        header h1{font-size:1.4rem;margin:0}
        .content{padding:28px}
        .profil-section{display:flex;align-items:center;gap:20px;margin-bottom:28px;padding:20px;border-bottom:1px solid #eef2ff;background:linear-gradient(180deg, rgba(4,102,200,0.04), transparent)}
        .profil-foto{width:140px;height:140px;border-radius:18px;object-fit:cover;border:6px solid #fff;background:linear-gradient(180deg,#f8fafc,#eef2ff);box-shadow:0 8px 30px rgba(4,102,200,0.08)}
        .meta{flex:1}
        .meta h2{font-size:1.45rem;margin:0 0 6px 0}
        .meta p.meta-sub{margin:0;color:var(--muted);font-weight:600}
        .meta .actions{margin-top:12px;display:flex;gap:10px}
        .btn{display:inline-block;padding:10px 18px;background:var(--primary);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;cursor:pointer;border:none;font-size:14px;box-shadow:0 6px 18px rgba(4,102,200,0.14)}
        .btn:hover{transform:translateY(-1px)}
        .btn-secondary{background:#fff;color:var(--primary);border:1px solid rgba(4,102,200,0.12);box-shadow:none;padding:9px 16px}
        .btn{display:inline-block;padding:10px 18px;background:var(--primary);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;cursor:pointer;border:none;font-size:14px}
        .btn:hover{opacity:.95}
        .btn-secondary{background:var(--muted);color:#fff;border-radius:10px;padding:8px 14px}
        .btn-danger{display:inline-block;padding:10px 18px;background:#ef4444;color:#fff;border-radius:10px;border:none;font-weight:600;cursor:pointer}
        .section{margin-bottom:22px}
        .section h2{font-size:1.1rem;margin-bottom:12px;color:var(--text);padding-bottom:8px;border-bottom:1px solid #f1f5f9}
        .pengumuman-item{background:#fff;padding:14px;border-radius:12px;margin-bottom:12px;box-shadow:0 6px 20px rgba(11,17,32,0.04);border-left:4px solid rgba(4,102,200,0.12)}
        .pengumuman-item h4{margin:0 0 6px 0;color:#0b1220;font-size:1.02rem}
        .pengumuman-item p{color:#475569;font-size:.97rem;margin:0 0 8px 0}
        .pengumuman-item small{color:#64748b;font-size:.85rem}
        .role-badge{display:inline-block;padding:8px 12px;border-radius:999px;font-weight:700;font-size:13px;color:#fff;box-shadow:0 6px 18px rgba(15,23,42,0.06)}
        .role-org{background:linear-gradient(90deg,#06b6d4,#0369a1)}
        .role-dept{background:linear-gradient(90deg,#fb923c,#f97316)}
        .role-div{background:linear-gradient(90deg,#8b5cf6,#6d28d9)}
        .target-badge{display:inline-block;padding:6px 8px;border-radius:999px;font-weight:700;font-size:11px;color:#fff}
        .target-all{background:#64748b}
        .target-dept{background:#f59e0b}
        .target-div{background:#7c3aed}
        .form-group{margin:14px 0}
        .form-group label{display:block;margin-bottom:6px;font-weight:600;color:var(--text)}
        .form-group input{width:100%;padding:10px;border:1px solid #e6eef8;border-radius:8px;font-size:15px}
        .form-group input:focus{outline:none;box-shadow:0 0 0 4px rgba(4,102,200,0.08);border-color:var(--primary)}
        .alert{padding:10px;border-radius:8px;margin-bottom:16px}
        .alert-success{background:#e6ffef;color:#065f46}
        .alert-error{background:#ffe8e8;color:#7f1d1d}
        #editForm{display:none;background:#ffffff;padding:20px;border-radius:12px;margin-top:18px;border:1px solid #eef2ff}
        .jabatan-list{display:flex;flex-wrap:wrap;gap:8px}
        .jabatan-list .role-badge{font-size:12px;padding:6px 10px}
        @media (max-width:640px){body{padding:18px}.container{border-radius:10px}header{padding:18px}.content{padding:18px}}
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:12px">
                    <a href="../../index.php" style="color:#fff;text-decoration:none;font-size:14px;display:flex;align-items:center;gap:6px;padding:8px 12px;background:rgba(255,255,255,0.2);border-radius:8px;transition:background 0.2s;hover:background 0.3s">
                        <i class="fa-solid fa-arrow-left" style="font-size:16px"></i>
                        Kembali
                    </a>
                    <h1>Profil Anggota</h1>
                </div>
            </div>
        </header>
        <div class="content">

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">✅ Profil berhasil diperbarui!</div>
            <?php endif; ?>

            <div class="profil-section">
                <?php
                    // resolve avatar path if available
                    $avatar = '';
                    if (!empty($user['foto'])) {
                        $fotoPath = file_url_and_path($user['foto']);
                        $avatar = $fotoPath['url'] ? $fotoPath['url'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['nama']) . '&background=0466c8&color=fff';
                    } else {
                        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($user['nama']) . '&background=0466c8&color=fff';
                    }
                ?>
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Foto" class="profil-foto">
                <div class="meta">
                    <h2><?= htmlspecialchars($user['nama']) ?></h2>
                    <?php if (!empty($primary_display)): ?>
                        <p class="meta-sub">
                            <span class="role-badge <?php echo ($primary_type==='organisasi'?'role-org':($primary_type==='departemen'?'role-dept':'role-div')); ?>">
                                <?= htmlspecialchars($primary_display) ?>
                            </span>
                        </p>
                    <?php endif; ?>
                    <p class="meta-sub" style="margin-top:6px"><?= htmlspecialchars($user['npm']) ?></p>
                    <div class="actions">
                        <form method="POST" action="../logout.php" style="display:inline">
                            <button type="submit" class="btn btn-danger">Keluar</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Form Edit Profil (sembunyi) -->
            <div id="editForm">
                <h2>Edit Profil</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_profil">
                    <div class="form-group">
                        <label for="nama">Nama Lengkap</label>
                        <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="npm">NPM</label>
                        <input type="text" id="npm" name="npm" value="<?= htmlspecialchars($user['npm']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="foto">Foto Profil (opsional)</label>
                        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png">
                        <small style="color:#64748b">Format: JPG/PNG | Maks: 2 MB</small>
                    </div>
                    <button type="submit" class="btn">Simpan Perubahan</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editForm').style.display='none'">
                        Batal
                    </button>
                </form>
            </div>

            <!-- Jabatan -->
            <div class="section">
                <h2>Jabatan</h2>
                <?php
                    if (!empty($jabatan_displays) && is_array($jabatan_displays)) {
                        foreach ($jabatan_displays as $entry) {
                            $label = $entry['label'];
                            $type = $entry['type'];
                            // choose class
                            $cls = ($type === 'organisasi') ? 'role-org' : (($type === 'departemen') ? 'role-dept' : 'role-div');
                            ?>
                            <p style="margin-bottom:8px">
                                <span class="role-badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                            </p>
                            <?php
                        }
                    } else {
                        echo '<p>Belum memiliki jabatan.</p>';
                    }
                ?>
            </div>

            <!-- Pengumuman Internal -->
            <div class="section">
                <h2>Pengumuman Internal</h2>
                <?php if ($pengumuman_list): ?>
                    <?php foreach ($pengumuman_list as $p): ?>
                        <?php
                            $t = $p['target'] ?? 'semua';
                            $targetLabel = 'Semua Anggota';
                            $targetClass = 'target-all';
                            if ($t === 'departemen') {
                                $targetLabel = 'Departemen' . (!empty($p['departemen_nama']) ? ': ' . $p['departemen_nama'] : '');
                                $targetClass = 'target-dept';
                            } elseif ($t === 'divisi') {
                                $targetLabel = 'Divisi' . (!empty($p['divisi_nama']) ? ': ' . $p['divisi_nama'] : '');
                                $targetClass = 'target-div';
                            }
                        ?>
                        <div class="pengumuman-item">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                <h4 style="margin:0"><?= htmlspecialchars($p['judul']) ?></h4>
                                <span class="target-badge <?= $targetClass ?>" title="Target: <?= htmlspecialchars($t) ?>"><?= htmlspecialchars($targetLabel) ?></span>
                            </div>
                            <p><?= htmlspecialchars($p['konten']) ?></p>
                            <small>Dikirim pada <?= htmlspecialchars(format_date_id($p['tanggal'], true)) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Tidak ada pengumuman untuk Anda.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        // Tutup form jika klik di luar
        document.addEventListener('click', function(e) {
            const form = document.getElementById('editForm');
            if (form.style.display === 'block') {
                const isClickInside = form.contains(e.target) || e.target.classList.contains('btn') && e.target.textContent.trim() === 'Edit Profil';
                if (!isClickInside) {
                    form.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>