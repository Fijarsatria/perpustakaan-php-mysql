<?php
session_start();

$host = "localhost";
$user = "root";
$password = "";
$database = "perpustakaan";

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Koneksi database gagal. Periksa MySQL dan database perpustakaan.");
}

// Token digunakan untuk memeriksa formulir yang dikirim.
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$id = '';
$judul = '';
$penulis = '';
$penerbit = '';
$tahunTerbit = '';
$stok = '';
$edit = false;
$error = [];
$pesan = $_SESSION['pesan'] ?? '';
$jenisPesan = 'success';
unset($_SESSION['pesan']);

// Mengambil data buku yang akan diedit.
if (isset($_GET['edit'])) {
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        http_response_code(400);
        exit("ID buku tidak valid.");
    }

    $stmt = $conn->prepare("SELECT * FROM buku WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $bukuEdit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bukuEdit) {
        http_response_code(404);
        exit("Buku tidak ditemukan.");
    }

    $edit = true;
    $judul = $bukuEdit['Judul'];
    $penulis = $bukuEdit['Penulis'];
    $penerbit = $bukuEdit['Penerbit'];
    $tahunTerbit = $bukuEdit['Tahun Terbit'];
    $stok = $bukuEdit['Stok'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['token'], $token)) {
        http_response_code(403);
        exit("Formulir tidak valid. Silakan muat ulang halaman.");
    }

    $aksi = $_POST['aksi'] ?? '';
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    if ($aksi !== 'simpan' && $aksi !== 'hapus') {
        http_response_code(400);
        exit("Aksi tidak valid.");
    }

    // Pastikan ID yang dikirim benar-benar ada.
    if (($_POST['id'] ?? '') !== '' || $aksi === 'hapus') {
        if (!$id || $id < 1) {
            http_response_code(400);
            exit("ID buku tidak valid.");
        }

        $stmt = $conn->prepare("SELECT id FROM buku WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch()) {
            http_response_code(404);
            exit("Buku tidak ditemukan.");
        }
    }

    // Menghapus buku setelah pengguna menyetujui konfirmasi.
    if ($aksi === 'hapus') {
        $stmt = $conn->prepare("DELETE FROM buku WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $_SESSION['pesan'] = 'Buku berhasil dihapus.';
        header("Location: index.php");
        exit();
    }

    $judul = is_string($_POST['judul'] ?? null) ? trim($_POST['judul']) : '';
    $penulis = is_string($_POST['penulis'] ?? null) ? trim($_POST['penulis']) : '';
    $penerbit = is_string($_POST['penerbit'] ?? null) ? trim($_POST['penerbit']) : '';
    $tahunTerbit = is_string($_POST['tahun_terbit'] ?? null) ? trim($_POST['tahun_terbit']) : '';
    $stok = is_string($_POST['stok'] ?? null) ? trim($_POST['stok']) : '';
    $edit = (bool) $id;

    if (mb_strlen($judul) < 5 || mb_strlen($judul) > 150) {
        $error[] = "Judul buku harus terdiri dari 5 sampai 150 karakter.";
    }
    if ($penulis === '' || mb_strlen($penulis) > 100) {
        $error[] = "Penulis wajib diisi, maksimal 100 karakter.";
    }
    if ($penerbit === '' || mb_strlen($penerbit) > 100) {
        $error[] = "Penerbit wajib diisi, maksimal 100 karakter.";
    }
    if (filter_var($tahunTerbit, FILTER_VALIDATE_INT) === false ||
        $tahunTerbit < 1901 || $tahunTerbit > date('Y')) {
        $error[] = "Tahun terbit harus antara 1901 sampai " . date('Y') . ".";
    }
    if (filter_var($stok, FILTER_VALIDATE_INT) === false ||
        $stok < 0 || $stok > 2147483647) {
        $error[] = "Stok harus berupa bilangan bulat dari 0 sampai 2147483647.";
    }

    if (empty($error)) {
        $data = [
            ':judul' => $judul,
            ':penulis' => $penulis,
            ':penerbit' => $penerbit,
            ':tahun_terbit' => $tahunTerbit,
            ':stok' => $stok
        ];

        try {
            if ($edit) {
                // Memperbarui buku berdasarkan ID.
                $stmt = $conn->prepare(
                    "UPDATE buku SET Judul = :judul, Penulis = :penulis,
                     Penerbit = :penerbit, `Tahun Terbit` = :tahun_terbit,
                     Stok = :stok WHERE id = :id"
                );
                $data[':id'] = $id;
                $stmt->execute($data);
                $_SESSION['pesan'] = 'Data buku berhasil diperbarui.';
            } else {
                // Menambahkan buku baru ke database.
                $stmt = $conn->prepare(
                    "INSERT INTO buku (Judul, Penulis, Penerbit, `Tahun Terbit`, Stok)
                     VALUES (:judul, :penulis, :penerbit, :tahun_terbit, :stok)"
                );
                $stmt->execute($data);
                $_SESSION['pesan'] = 'Buku berhasil ditambahkan.';
            }

            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error[] = "Data buku belum berhasil disimpan. Silakan coba kembali.";
        }
    }

    if (!empty($error)) {
        http_response_code(422);
        $pesan = implode(' ', $error);
        $jenisPesan = 'danger';
    }
}

// Menampilkan daftar buku dan hasil pencarian.
$cari = is_string($_GET['cari'] ?? '') ? trim($_GET['cari'] ?? '') : '';
$stmt = $conn->prepare(
    "SELECT * FROM buku
     WHERE Judul LIKE :judul OR Penulis LIKE :penulis
     ORDER BY created_at DESC, id DESC"
);
$stmt->execute([
    ':judul' => '%' . $cari . '%',
    ':penulis' => '%' . $cari . '%'
]);
$bukus = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Daftar Buku</title>
</head>
<body class="bg-light">
    <header class="bg-primary text-white p-3">
        <h1 class="h3 m-0">Website Peminjaman Buku</h1>
    </header>

    <nav class="navbar navbar-dark bg-primary border-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Perpustakaan Buku</a>
            <form class="d-flex" role="search" method="get" action="index.php">
                <input class="form-control me-2"
                    type="search"
                    name="cari"
                    value="<?= htmlspecialchars($cari, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Cari judul atau penulis"
                    aria-label="Cari buku">
                <button class="btn btn-outline-light"
                        type="submit">Cari</button>
            </form>
        </div>
    </nav>

    <div class="container-fluid my-4">
        <div class="row">
            <aside class="col-md-3">
                <div class="bg-white border rounded p-3">
                <h2>Menu</h2>
                <ul class="list-group">
                    <li class="list-group-item"><a href="index.php" class="text-decoration-none">Beranda</a></li>
                    <li class="list-group-item"><a href="#daftarBuku" class="text-decoration-none">Daftar Buku</a></li>
                    <li class="list-group-item"><a href="index.php#formBuku" class="text-decoration-none"> Tambah Buku</a></li>
                </ul>
                </div>
            </aside>

            <main class="col-md-9">
                    <?php if ($pesan !== ''): ?>
                        <div class="alert alert-<?= htmlspecialchars($jenisPesan, ENT_QUOTES, 'UTF-8') ?>" role="alert">
                            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                <section class="bg-white border rounded p-3 mb-4">
                    <h2 class="h4 mb-3"><?= $edit ? 'Form Edit Buku' : 'Form Tambah Buku' ?></h2>
                    <form action="index.php" method="post" id="formBuku" novalidate>
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="aksi" value="simpan">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="judul" class="form-label">Judul Buku</label>
                                <input type="text" class="form-control" id="judul" maxlength="150" name="judul" value="<?= htmlspecialchars((string) $judul, ENT_QUOTES, 'UTF-8') ?>" minlength="5" required>
                                <div class="invalid-feedback">Judul buku minimal 5 karakter.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="penulis" class="form-label">Penulis</label>
                                <input type="text" class="form-control" id="penulis" maxlength="100" name="penulis" value="<?= htmlspecialchars((string) $penulis, ENT_QUOTES, 'UTF-8') ?>" required>
                                <div class="invalid-feedback">Penulis wajib diisi.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="penerbit" class="form-label">Penerbit</label>
                                <input type="text" class="form-control" id="penerbit" maxlength="100" name="penerbit" value="<?= htmlspecialchars((string) $penerbit, ENT_QUOTES, 'UTF-8') ?>" required>
                                <div class="invalid-feedback">Penerbit wajib diisi.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tahunTerbit" class="form-label">Tahun Terbit</label>
                                <input type="number" class="form-control" id="tahunTerbit" name="tahun_terbit" value="<?= htmlspecialchars((string) $tahunTerbit, ENT_QUOTES, 'UTF-8') ?>" min="1901" max="<?= date('Y') ?>" step="1" required>
                                <div class="invalid-feedback">Tahun terbit harus antara 1901 sampai <?= date('Y') ?>.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stok" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="stok" max="2147483647" name="stok" value="<?= htmlspecialchars((string) $stok, ENT_QUOTES, 'UTF-8') ?>" min="0" step="1" required>
                                <div class="invalid-feedback">Stok minimal 0.</div>
                            </div>
                        </div>
                        <button type="submit" id="tombolSimpan" class="btn btn-primary">
                            <?= $edit ? 'Simpan Perubahan' : 'Tambah Buku' ?>
                        </button>
                        <?php if ($edit): ?>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        <?php endif; ?>
                    </form>
                </section>
                <section class="bg-white border rounded p-3" id="daftarBuku">
                    <h2 class="h4 mb-3">Daftar Buku</h2>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul</th>
                                    <th>Penulis</th>
                                    <th>Penerbit</th>
                                    <th>Tahun Terbit</th>
                                    <th>Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bukus)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <?= $cari !== '' ? 'Buku yang dicari tidak ditemukan.' : 'Belum ada data buku.' ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bukus as $index => $buku): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars((string) $buku['Judul'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $buku['Penulis'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($buku['Penerbit'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $buku['Tahun Terbit'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $buku['Stok'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="index.php?edit=<?= (int) $buku['id'] ?>"
                                                   class="btn btn-sm btn-warning">
                                                    Edit
                                                </a>
                                                <form action="index.php" method="post"
                                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus buku ini?')">
                                                    <input type="hidden" name="aksi" value="hapus">
                                                    <input type="hidden" name="id" value="<?= (int) $buku['id'] ?>">
                                                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <footer class="bg-primary text-white text-center p-3 mt-4">
        <p class="mb-0">&copy; 2026 Website Peminjaman Buku.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("formBuku").addEventListener("submit", function (event) {
            this.classList.add("was-validated");
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    </script>
</body>
</html>
