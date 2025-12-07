<?php
session_start();
// Pastikan hanya anggota yang login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'anggota') {
    header('Location: ../login.php');
    exit;
}

include '../includes/db.php';
$user_id = $_SESSION['user_id'];

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
                    $old_data = $pdo->prepare("SELECT foto FROM anggota WHERE id = ?");
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
                $pdo->prepare("UPDATE anggota SET nama = ?, npm = ?, foto = ? WHERE id = ?")
                    ->execute([$nama, $npm, $foto, $user_id]);
            } else {
                $pdo->prepare("UPDATE anggota SET nama = ?, npm = ? WHERE id = ?")
                    ->execute([$nama, $npm, $user_id]);
            }
            header("Location: profil.php?updated=1");
            exit;
        }
    }
}

// === AMBIL DATA ANGGOTA ===
$stmt = $pdo->prepare("SELECT * FROM anggota WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Anggota tidak ditemukan.");
}

// === AMBIL JABATAN & UNIT ===
$jabatan_stmt = $pdo->prepare("
    SELECT j.nama AS jabatan, d.nama AS departemen, div.nama AS divisi
    FROM anggota_jabatan aj
    JOIN jabatan j ON aj.jabatan_id = j.id
    LEFT JOIN departemen d ON aj.departemen_id = d.id
    LEFT JOIN divisi div ON aj.divisi_id = div.id
    WHERE aj.anggota_id = ?
");
$jabatan_stmt->execute([$user_id]);
$jabatan_list = $jabatan_stmt->fetchAll();

// === AMBIL PENGUMUMAN INTERNAL ===
$pengumuman_stmt = $pdo->prepare("
    SELECT judul, konten, tanggal FROM pengumuman
    WHERE target = 'semua'
       OR (target = 'departemen' AND departemen_id IN (
            SELECT departemen_id FROM anggota_jabatan WHERE anggota_id = ?
          ))
       OR (target = 'divisi' AND divisi_id IN (
            SELECT divisi_id FROM anggota_jabatan WHERE anggota_id = ?
          ))
    ORDER BY tanggal DESC
");
$pengumuman_stmt->execute([$user_id, $user_id]);
$pengumuman_list = $pengumuman_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Anggota - Abelian</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        header {
            background: #4F46E5;
            color: white;
            padding: 24px;
            text-align: center;
        }
        header h1 { font-size: 1.8rem; }
        .content { padding: 24px; }
        .profil-section {
            text-align: center;
            margin-bottom: 32px;
        }
        .profil-foto {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e2e8f0;
            background: #f1f5f9;
            margin-bottom: 16px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        .btn:hover { background: #4338CA; }
        .btn-secondary {
            background: #94a3b8;
        }
        .btn-secondary:hover {
            background: #64748b;
        }
        .section {
            margin-bottom: 28px;
        }
        .section h2 {
            font-size: 1.3rem;
            margin-bottom: 16px;
            color: #1e293b;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .pengumuman-item {
            background: #f8fafc;
            padding: 14px;
            border-left: 4px solid #4F46E5;
            margin-bottom: 12px;
            border-radius: 0 6px 6px 0;
        }
        .pengumuman-item h4 { margin-bottom: 6px; color: #0f172a; }
        .pengumuman-item p { color: #475569; font-size: 0.95rem; }
        .pengumuman-item small { color: #94a3b8; font-size: 0.85rem; }
        .form-group {
            margin: 16px 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1e293b;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4F46E5;
        }
        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        #editForm {
            display: none;
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Profil Anggota</h1>
        </header>
        <div class="content">

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">✅ Profil berhasil diperbarui!</div>
            <?php endif; ?>

            <div class="profil-section">
                <img 
                    src="<?= !empty($user['foto']) 
                        ? '../uploads/' . htmlspecialchars($user['foto']) 
                        : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgZmlsbD0iI2Y4ZmFmYyIvPjx0ZXh0IHg9IjYwIiB5PSI3NSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjOTQ5NGE4Ij5OT1RPIFBBU1Q8L3RleHQ+PC9zdmc+' ?>" 
                    alt="Foto Profil" 
                    class="profil-foto"
                >
                <h2><?= htmlspecialchars($user['nama']) ?></h2>
                <p><?= htmlspecialchars($user['npm']) ?></p>
                <button class="btn" onclick="document.getElementById('editForm').style.display='block'">
                    Edit Profil
                </button>
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
                <?php if ($jabatan_list): ?>
                    <?php foreach ($jabatan_list as $j): ?>
                        <p>
                            <strong><?= htmlspecialchars($j['jabatan']) ?></strong>
                            <?php if ($j['departemen']): ?> — Departemen <?= htmlspecialchars($j['departemen']) ?><?php endif; ?>
                            <?php if ($j['divisi']): ?> — Divisi <?= htmlspecialchars($j['divisi']) ?><?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Belum memiliki jabatan.</p>
                <?php endif; ?>
            </div>

            <!-- Pengumuman Internal -->
            <div class="section">
                <h2>Pengumuman Internal</h2>
                <?php if ($pengumuman_list): ?>
                    <?php foreach ($pengumuman_list as $p): ?>
                        <div class="pengumuman-item">
                            <h4><?= htmlspecialchars($p['judul']) ?></h4>
                            <p><?= htmlspecialchars($p['konten']) ?></p>
                            <small><?= date('d M Y H:i', strtotime($p['tanggal'])) ?></small>
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