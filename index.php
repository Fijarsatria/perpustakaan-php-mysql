<?php
session_start();
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function stop($code, $message) { http_response_code($code); exit(e($message)); }
try { require __DIR__ . '/config.php'; } catch (PDOException $ex) { error_log($ex->getMessage()); stop(500, 'Database belum terhubung. Periksa konfigurasi dan impor database/schema.sql.'); }
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
$errors = [];
$book = ['id'=>'', 'Judul'=>'', 'Penulis'=>'', 'Penerbit'=>'', 'Tahun Terbit'=>date('Y'), 'Stok'=>0];
$mode = $_GET['action'] ?? 'add';
if (!in_array($mode, ['add','edit','delete'], true)) stop(400, 'Aksi tidak dikenal.');
if ($mode !== 'add') {
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$id) stop(400, 'ID buku tidak valid.');
    $s = $pdo->prepare('SELECT * FROM buku WHERE id = ?'); $s->execute([$id]);
    $book = $s->fetch(); if (!$book) stop(404, 'Buku tidak ditemukan.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) stop(403, 'Sesi formulir tidak valid. Muat ulang halaman.');
    if ($mode === 'delete') {
        $s = $pdo->prepare('DELETE FROM buku WHERE id = ?'); $s->execute([$book['id']]);
        $_SESSION['flash'] = 'Buku berhasil dihapus.';
        header('Location: index.php'); exit;
    }
    foreach (['Judul','Penulis','Penerbit','Tahun Terbit','Stok'] as $key) {
        $value = $_POST[str_replace(' ', '_', $key)] ?? '';
        $book[$key] = is_string($value) ? trim($value) : '';
    }
    foreach (['Judul'=>150,'Penulis'=>100,'Penerbit'=>100] as $key=>$max) {
        $length = mb_strlen($book[$key], 'UTF-8');
        if ($length < ($key==='Judul' ? 5 : 1) || $length > $max) $errors[] = "$key harus diisi dengan " . ($key==='Judul' ? '5' : '1') . " sampai $max karakter.";
    }
    $year = filter_var($book['Tahun Terbit'], FILTER_VALIDATE_INT);
    $stock = filter_var($book['Stok'], FILTER_VALIDATE_INT);
    if ($year === false || $year < 1901 || $year > (int)date('Y')) $errors[] = 'Tahun terbit harus berupa bilangan bulat antara 1901 dan ' . date('Y') . '.';
    if ($stock === false || $stock < 0 || $stock > 2147483647) $errors[] = 'Stok harus berupa bilangan bulat 0 sampai 2147483647.';
    if (!$errors) {
        $values = [$book['Judul'],$book['Penulis'],$book['Penerbit'],$year,$stock];
        if ($mode === 'edit') { $sql = 'UPDATE buku SET Judul=?, Penulis=?, Penerbit=?, `Tahun Terbit`=?, Stok=? WHERE id=?'; $values[]=$book['id']; }
        else $sql = 'INSERT INTO buku (Judul,Penulis,Penerbit,`Tahun Terbit`,Stok) VALUES (?,?,?,?,?)';
        try { $pdo->prepare($sql)->execute($values); $_SESSION['flash'] = $mode==='edit' ? 'Perubahan buku berhasil disimpan.' : 'Buku berhasil ditambahkan.'; header('Location: index.php'); exit; }
        catch (PDOException $ex) { error_log($ex->getMessage()); $errors[]='Data belum tersimpan. Silakan coba kembali.'; }
    }
    http_response_code(422);
}
$q = is_string($_GET['q'] ?? '') ? trim($_GET['q'] ?? '') : '';
$s=$pdo->prepare('SELECT * FROM buku WHERE Judul LIKE ? OR Penulis LIKE ? OR Penerbit LIKE ? ORDER BY id DESC');
$s->execute(array_fill(0,3,'%'.$q.'%')); $books=$s->fetchAll();
$stats=$pdo->query('SELECT COUNT(*) AS titles, COALESCE(SUM(Stok),0) AS copies, COUNT(DISTINCT Penulis) AS authors FROM buku')->fetch();
$flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ruang Baca | Katalog Buku</title><link rel="stylesheet" href="assets/style.css"></head><body>
<aside class="sidebar"><a class="brand" href="index.php"><span class="logo">RB</span> Ruang Baca<span class="subbrand">PERPUSTAKAAN BUKU</span></a><p class="nav-label">RUANG KELOLA</p><a class="nav active" href="index.php">▤ &nbsp; Katalog buku</a><a class="nav" href="index.php#form-buku">＋ &nbsp; Tambah buku</a><div class="side-note">Setiap buku,<br>sebuah pengetahuan.<span>Kelola koleksi dengan lebih teratur.</span></div><small class="side-footer">PRAKTIK PEMROGRAMAN WEB · 2026</small></aside>
<main><header><span>Koleksi / <strong>Katalog buku</strong></span><span class="status">● &nbsp; Perpustakaan aktif</span></header>
<section class="intro"><div><p class="eyebrow">RUANG BACA / KATALOG</p><h1>Koleksi dalam satu ruang.</h1><p>Catat buku baru, perbarui informasi, dan rapikan koleksi perpustakaan.</p></div><a class="button" href="index.php#form-buku">＋ Tambah buku</a></section>
<section class="stats" aria-label="Ringkasan koleksi"><div><span>Judul dalam katalog</span><strong><?= e($stats['titles']) ?><small> judul</small></strong></div><div><span>Total persediaan</span><strong><?= e($stats['copies']) ?><small> eksemplar</small></strong></div><div><span>Penulis dalam koleksi</span><strong><?= e($stats['authors']) ?><small> penulis</small></strong></div></section>
<?php if ($flash): ?><div class="notice" role="status"><?= e($flash) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="notice error" role="alert"><strong>Periksa kembali isian buku.</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($mode==='delete'): ?>
<section class="panel danger-panel"><p class="eyebrow">KONFIRMASI HAPUS</p><h2>Hapus buku ini?</h2><p><strong><?= e($book['Judul']) ?></strong> oleh <?= e($book['Penulis']) ?> akan dihapus dari katalog. Tindakan ini tidak dapat dibatalkan.</p><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><button class="button danger" type="submit">Ya, hapus buku</button><a class="button secondary" href="index.php">Batal</a></form></section>
<?php else: ?>
<section class="panel" id="form-buku"><div class="section-heading"><div><p class="eyebrow"><?= $mode==='edit' ? 'PERBARUI KOLEKSI' : 'LENGKAPI KOLEKSI' ?></p><h2><?= $mode==='edit' ? 'Edit buku' : 'Tambah buku baru' ?></h2></div><span class="muted">Semua kolom wajib diisi</span></div>
<form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><div class="fields">
<?php foreach (['Judul'=>'Judul buku','Penulis'=>'Penulis','Penerbit'=>'Penerbit','Tahun Terbit'=>'Tahun terbit','Stok'=>'Stok'] as $key=>$label): $numeric=in_array($key,['Tahun Terbit','Stok'],true); ?><label><?= e($label) ?><input name="<?= e(str_replace(' ','_',$key)) ?>" value="<?= e($book[$key]) ?>" type="<?= $numeric?'number':'text' ?>" required <?= $numeric ? 'min="'.($key==='Stok'?0:1901).'" max="'.($key==='Stok'?2147483647:date('Y')).'" step="1"' : 'maxlength="'.($key==='Judul'?150:100).'"'.($key==='Judul'?' minlength="5"':'') ?>></label><?php endforeach; ?></div>
<div class="form-footer"><span class="muted">Informasi yang rapi memudahkan pencarian buku.</span><div><?php if ($mode==='edit'): ?><a class="button secondary" href="index.php">Batal</a><?php endif; ?><button class="button" type="submit"><?= $mode==='edit'?'Simpan perubahan':'Simpan buku' ?></button></div></div></form></section>
<?php endif; ?>
<section class="panel catalog"><div class="section-heading"><div><p class="eyebrow">DAFTAR KOLEKSI</p><h2>Katalog buku <span class="count"><?= count($books) ?></span></h2></div><form class="search" method="get"><label class="sr-only" for="q">Cari judul, penulis, atau penerbit</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Cari judul, penulis, penerbit..."><button class="button secondary" type="submit">Cari</button><?php if ($q!==''): ?><a class="reset" href="index.php">Reset</a><?php endif; ?></form></div>
<div class="table-wrap"><table><thead><tr><th>No.</th><th>Judul & penulis</th><th>Penerbit</th><th>Tahun</th><th>Stok</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($books as $i=>$row): ?><tr><td class="muted"><?= $i+1 ?></td><td><div class="book-cell"><span class="book-icon" aria-hidden="true">▤</span><div><strong><?= e($row['Judul']) ?></strong><span><?= e($row['Penulis']) ?></span></div></div></td><td><?= e($row['Penerbit'] ?: '-') ?></td><td><?= e($row['Tahun Terbit']) ?></td><td><span class="stock <?= $row['Stok']==0?'empty':'' ?>"><?= e($row['Stok']) ?> tersedia</span></td><td><div class="actions"><a href="index.php?action=edit&amp;id=<?= e($row['id']) ?>#form-buku">Edit</a><a class="delete-link" href="index.php?action=delete&amp;id=<?= e($row['id']) ?>">Hapus</a></div></td></tr><?php endforeach; ?>
<?php if (!$books): ?><tr><td colspan="6" class="empty-state"><?= $q!==''?'Tidak ada buku yang cocok. Coba kata kunci lain.':'Belum ada buku. Tambahkan koleksi pertama melalui formulir di atas.' ?></td></tr><?php endif; ?>
</tbody></table></div><div class="table-footer">Menampilkan <?= count($books) ?> judul<?= $q!==''?' untuk pencarian “'.e($q).'”':'' ?> · Data koleksi perpustakaan</div></section><footer>Ruang Baca <span>Kelola buku. Rawat pengetahuan.</span></footer></main></body></html>
