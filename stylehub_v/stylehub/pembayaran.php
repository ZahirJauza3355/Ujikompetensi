<?php
/**
 * PEMBAYARAN (KASIR)
 * Alur: pilih variasi (produk + ukuran + warna) -> qty -> cek stok -> keranjang
 *       -> total -> input uang bayar -> simpan (transaksi database)
 *       -> stok variasi berkurang -> cetak struk
 *
 * Keranjang disimpan di $_SESSION['cart'] = [ variant_id => qty ].
 * Harga TIDAK diambil dari browser, selalu dibaca dari database.
 */
require __DIR__ . '/includes/functions.php';
cek_kasir();                       // hanya role kasir; admin ditolak
$pdo = db();

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Label variasi untuk pesan: "Kaos Polos Cotton (S / Hitam)"
function label_variasi($v)
{
    return $v['nama_produk'] . ' (' . ($v['nama_ukuran'] ?: '-') . ' / ' . ($v['nama_warna'] ?: '-') . ')';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---- 1. Tambah ke keranjang ---- */
    if (isset($_POST['tambah'])) {
        $vid = (int)$_POST['variant_id'];
        $qty = (int)$_POST['qty'];

        if ($vid <= 0) {
            pesan('Pilih variasi (ukuran dan warna) terlebih dahulu.');
        } elseif ($qty <= 0) {
            pesan('Qty harus lebih dari 0.');
        } else {
            $st = $pdo->prepare('SELECT nama_produk, nama_ukuran, nama_warna, stok, status_produk FROM v_variants WHERE variant_id = ?');
            $st->execute([$vid]);
            $v = $st->fetch();
            $di_keranjang = isset($_SESSION['cart'][$vid]) ? $_SESSION['cart'][$vid] : 0;

            if (!$v || (int)$v['status_produk'] !== 1) {
                pesan('Variasi tidak ditemukan atau produk nonaktif.');
            } elseif ($di_keranjang + $qty > (int)$v['stok']) {         // aturan bisnis: qty tidak boleh melebihi stok
                pesan('Stok ' . label_variasi($v) . ' kurang. Tersedia ' . $v['stok'] . ', sudah di keranjang ' . $di_keranjang . '.');
            } else {
                $_SESSION['cart'][$vid] = $di_keranjang + $qty;
                pesan('Ditambahkan ke keranjang.');
            }
        }
        redirect('pembayaran.php');
    }

    /* ---- 2. Hapus item / kosongkan ---- */
    if (isset($_POST['hapus_item'])) {
        unset($_SESSION['cart'][(int)$_POST['variant_id']]);
        redirect('pembayaran.php');
    }
    if (isset($_POST['kosongkan'])) {
        $_SESSION['cart'] = [];
        pesan('Keranjang dikosongkan.');
        redirect('pembayaran.php');
    }

    /* ---- 3. Simpan pembayaran ---- */
    if (isset($_POST['bayar'])) {
        $cart   = $_SESSION['cart'];
        $metode = isset($_POST['metode']) ? $_POST['metode'] : 'Tunai';
        $diskon = trim($_POST['diskon']) === '' ? 0 : $_POST['diskon'];
        $bayar  = isset($_POST['dibayar']) ? $_POST['dibayar'] : '';

        if (!$cart) {
            pesan('Keranjang masih kosong.');
            redirect('pembayaran.php');
        }
        if (!in_array($metode, ['Tunai', 'Transfer', 'QRIS'], true)) {
            pesan('Metode pembayaran tidak valid.');
            redirect('pembayaran.php');
        }
        if (!is_numeric($diskon) || $diskon < 0) {
            pesan('Diskon harus angka dan tidak boleh negatif.');
            redirect('pembayaran.php');
        }
        if ($metode === 'Tunai' && (!is_numeric($bayar) || $bayar < 0)) {
            pesan('Uang bayar harus angka dan tidak boleh negatif.');
            redirect('pembayaran.php');
        }
        $diskon = (float)$diskon;

        try {
            // ===== MULAI TRANSAKSI DATABASE: semua langkah berhasil, atau batal semua =====
            $pdo->beginTransaction();

            // Kunci baris variasi (FOR UPDATE) supaya kasir lain tidak menjual stok yang sama bersamaan
            $ids = array_map('intval', array_keys($cart));
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $st  = $pdo->prepare("SELECT variant_id, nama_produk, nama_ukuran, nama_warna, stok, harga, status_produk FROM v_variants WHERE variant_id IN ($in) FOR UPDATE");
            $st->execute($ids);
            $data = [];
            foreach ($st->fetchAll() as $row) {
                $data[(int)$row['variant_id']] = $row;
            }

            // Cek ulang stok dan hitung total dari harga di database
            $subtotal = 0.0;
            $baris    = [];
            foreach ($cart as $vid => $qty) {
                if (!isset($data[$vid]) || (int)$data[$vid]['status_produk'] !== 1) {
                    throw new RuntimeException('Ada item yang sudah tidak tersedia. Hapus dari keranjang lalu coba lagi.');
                }
                if ($qty > (int)$data[$vid]['stok']) {
                    throw new RuntimeException('Stok ' . label_variasi($data[$vid]) . ' kurang (tersedia ' . $data[$vid]['stok'] . '). Pembayaran ditolak.');
                }
                $harga  = (float)$data[$vid]['harga'];
                $subtotal += $harga * $qty;
                $baris[] = [$vid, $harga, $qty, $harga * $qty];
            }

            if ($diskon > $subtotal) {
                throw new RuntimeException('Diskon tidak boleh melebihi subtotal.');
            }
            $total = $subtotal - $diskon;
            // Transfer / QRIS dibayar pas; hanya tunai yang punya kembalian
            $bayar = ($metode === 'Tunai') ? (float)$bayar : $total;

            if ($bayar < $total) {                           // aturan bisnis: uang bayar harus mencukupi
                throw new RuntimeException('Uang bayar kurang ' . rupiah($total - $bayar) . '. Pembayaran ditolak.');
            }
            $kembalian = $bayar - $total;

            // Nomor struk dibuat oleh FUNCTION fn_no_struk() di MySQL
            $no_struk = $pdo->query('SELECT fn_no_struk()')->fetchColumn();

            $pdo->prepare('INSERT INTO payments (user_id, no_struk, subtotal, diskon, total, metode, dibayar, kembalian) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$_SESSION['user_id'], $no_struk, $subtotal, $diskon, $total, $metode, $bayar, $kembalian]);
            $payment_id = (int)$pdo->lastInsertId();

            // Simpan semua detail. TRIGGER di database: (1) cek ulang stok, (2) kurangi stok variasi.
            $ins = $pdo->prepare('INSERT INTO payment_items (payment_id, variant_id, harga_jual, qty, subtotal) VALUES (?,?,?,?,?)');
            foreach ($baris as $b) {
                $ins->execute([$payment_id, $b[0], $b[1], $b[2], $b[3]]);
            }

            $pdo->commit();                                   // ===== SEMUA BERHASIL: simpan permanen =====
            $_SESSION['cart'] = [];
            pesan('Pembayaran berhasil. Struk ' . $no_struk . '.');
            redirect('struk.php?id=' . $payment_id);

        } catch (RuntimeException $e) {                       // aturan bisnis yang kita periksa sendiri
            if ($pdo->inTransaction()) $pdo->rollBack();
            pesan($e->getMessage());
        } catch (PDOException $e) {                           // kegagalan database (trigger menolak, dll.)
            if ($pdo->inTransaction()) $pdo->rollBack();      // ===== GAGAL: stok dan data kembali seperti semula =====
            pesan('Pembayaran gagal dan dibatalkan (rollback): ' . (isset($e->errorInfo[2]) ? $e->errorInfo[2] : 'kesalahan database'));
        }
        redirect('pembayaran.php');
    }
    redirect('pembayaran.php');
}

// ===== DATA UNTUK TAMPILAN =====
$variasi = $pdo->query('SELECT variant_id, nama_produk, nama_ukuran, nama_warna, stok, harga FROM v_variants WHERE status_produk = 1 ORDER BY nama_produk, variant_id')->fetchAll();

// Isi keranjang (dibaca dari DB supaya harga selalu terbaru)
$items    = [];
$subtotal = 0.0;
foreach ($_SESSION['cart'] as $vid => $qty) {
    $st = $pdo->prepare('SELECT * FROM v_variants WHERE variant_id = ?');
    $st->execute([(int)$vid]);
    $v = $st->fetch();
    if (!$v) { unset($_SESSION['cart'][$vid]); continue; }
    $sub    = (float)$v['harga'] * (int)$qty;
    $subtotal += $sub;
    $items[] = ['v' => $v, 'qty' => (int)$qty, 'sub' => $sub];
}

$judul = 'Pembayaran';
require __DIR__ . '/includes/header.php';
?>
<h3>1. Pilih barang</h3>
<form method="post">
    <table>
        <tr><td>Variasi</td><td>
            <select name="variant_id" required>
                <option value="">-- pilih produk / ukuran / warna --</option>
                <?php foreach ($variasi as $v): ?>
                    <option value="<?= $v['variant_id'] ?>" <?= $v['stok'] <= 0 ? 'disabled' : '' ?>>
                        <?= e($v['nama_produk'] . ' | ' . ($v['nama_ukuran'] ?: '-') . ' / ' . ($v['nama_warna'] ?: '-')
                            . ' | ' . rupiah($v['harga']) . ' | stok ' . $v['stok'] . ($v['stok'] <= 0 ? ' (HABIS)' : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select></td></tr>
        <tr><td>Qty</td><td><input type="number" name="qty" min="1" step="1" value="1" required></td></tr>
    </table>
    <br>
    <button type="submit" name="tambah">Tambah ke keranjang</button>
</form>

<h3>2. Keranjang</h3>
<table class="tabel">
    <tr><th>Item</th><th>Harga</th><th>Qty</th><th>Subtotal</th><th>Aksi</th></tr>
    <?php foreach ($items as $it): $v = $it['v']; ?>
    <tr>
        <td><?= e($v['nama_produk']) ?> (<?= e(($v['nama_ukuran'] ?: '-') . ' / ' . ($v['nama_warna'] ?: '-')) ?>)</td>
        <td><?= rupiah($v['harga']) ?></td>
        <td><?= $it['qty'] ?></td>
        <td><?= rupiah($it['sub']) ?></td>
        <td>
            <form method="post" class="inline">
                <input type="hidden" name="variant_id" value="<?= $v['variant_id'] ?>">
                <button type="submit" name="hapus_item" class="btn-danger btn-sm">Hapus</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="5">Keranjang kosong.</td></tr><?php endif; ?>
    <?php if ($items): ?><tr><td colspan="3"><b>Subtotal</b></td><td colspan="2"><b><?= rupiah($subtotal) ?></b></td></tr><?php endif; ?>
</table>

<?php if ($items): ?>
<h3>3. Pembayaran</h3>
<form method="post">
    <table>
        <tr><td>Subtotal</td><td><b><?= rupiah($subtotal) ?></b></td></tr>
        <tr><td>Diskon (Rp)</td><td><input type="number" name="diskon" min="0" max="<?= $subtotal ?>" step="any" value="0"></td></tr>
        <tr><td>Metode</td><td>
            <select name="metode" id="metode">
                <option>Tunai</option>
                <option>Transfer</option>
                <option>QRIS</option>
            </select></td></tr>
        <tr id="baris-bayar"><td>Uang bayar (Rp)</td><td><input type="number" name="dibayar" id="dibayar" min="0" step="any"></td></tr>
    </table>
    <p>Total = subtotal - diskon. Kembalian dihitung otomatis. Transfer dan QRIS dibayar pas.</p>
    <button type="submit" name="bayar">Bayar dan cetak struk</button>
</form>
<br>
<form method="post" onsubmit="return confirm('Kosongkan keranjang?')">
    <button type="submit" name="kosongkan" class="btn-danger btn-sm">Kosongkan keranjang</button>
</form>
<?php endif; ?>
<script>
// Uang bayar hanya diisi untuk metode Tunai
var metode = document.getElementById('metode');
if (metode) {
    metode.addEventListener('change', function () {
        var tunai = this.value === 'Tunai';
        document.getElementById('baris-bayar').style.display = tunai ? '' : 'none';
        document.getElementById('dibayar').required = tunai;
    });
    document.getElementById('dibayar').required = true;
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>