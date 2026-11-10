-- =====================================================================
--  SKRIP PENGUJIAN DATABASE STYLEHUB  (jalankan SETELAH database.sql diimpor)
--  Cara pakai: buka phpMyAdmin > tab SQL, jalankan BLOK demi BLOK (jangan sekaligus),
--  lalu bandingkan hasilnya dengan baris "HASIL YANG DIHARAPKAN".
--  Screenshot setiap hasil sebagai bukti pengujian.
-- =====================================================================
USE dbstylehub;

-- ---------------------------------------------------------------------
-- UJI 1 : Kombinasi produk + ukuran + warna ganda  ->  ditolak oleh aplikasi
-- (dicek di variasi.php, bukan di database; uji lewat menu Variasi & Stok)
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- UJI 2 : Stok rendah  ->  variasi ditandai RESTOK (FUNCTION fn_status_stok)
-- HASIL YANG DIHARAPKAN: Variasi dengan stok <= 5 berstatus RESTOK, stok 0 berstatus HABIS
-- ---------------------------------------------------------------------
SELECT nama_produk, nama_ukuran, nama_warna, stok, status_stok FROM v_variants WHERE status_stok <> 'AMAN' ORDER BY stok;

-- ---------------------------------------------------------------------
-- UJI 3 : Perubahan stok tercatat  ->  TRIGGER trg_variants_au mengisi stock_log
-- HASIL YANG DIHARAPKAN: 1 baris baru di stock_log (stok_lama 15, stok_baru 14)
-- ---------------------------------------------------------------------
UPDATE product_variants SET stok = 14 WHERE variant_id = 4;
SELECT * FROM stock_log ORDER BY log_id DESC LIMIT 1;
UPDATE product_variants SET stok = 15 WHERE variant_id = 4;   -- kembalikan stok awal

-- ---------------------------------------------------------------------
-- UJI 4 : Stok kurang  ->  pembayaran ditolak (oleh TRIGGER trg_payment_items_bi)
-- HASIL YANG DIHARAPKAN: ERROR 1644 "Stok tidak mencukupi"
--                        lalu stok variasi 5 tetap 3 dan struk STR-TEST-0001 tidak ada (0 baris)
-- ---------------------------------------------------------------------
START TRANSACTION;
INSERT INTO payments (user_id, no_struk, total, dibayar) VALUES (2, 'STR-TEST-0001', 100, 100);
INSERT INTO payment_items (payment_id, variant_id, harga_jual, qty, subtotal)
VALUES (LAST_INSERT_ID(), 5, 75000, 99, 7425000);        -- variasi 5 stoknya hanya 3
ROLLBACK;

SELECT stok FROM product_variants WHERE variant_id = 5;                   -- 3
SELECT COUNT(*) AS jumlah FROM payments WHERE no_struk = 'STR-TEST-0001'; -- 0

-- ---------------------------------------------------------------------
-- UJI 5 : Pembayaran sukses  ->  stok berkurang; ROLLBACK mengembalikan stok
-- HASIL YANG DIHARAPKAN: stok variasi 4 = 15 (awal) -> 14 (setelah insert) -> 15 (setelah rollback)
-- ---------------------------------------------------------------------
SELECT stok AS stok_awal FROM product_variants WHERE variant_id = 4;    -- 15

START TRANSACTION;
INSERT INTO payments (user_id, no_struk, total, dibayar) VALUES (2, 'STR-TEST-0002', 75000, 75000);
INSERT INTO payment_items (payment_id, variant_id, harga_jual, qty, subtotal)
VALUES (LAST_INSERT_ID(), 4, 75000, 1, 75000);
SELECT stok AS stok_setelah_insert FROM product_variants WHERE variant_id = 4;   -- 14 (trigger mengurangi)
ROLLBACK;
SELECT stok AS stok_setelah_rollback FROM product_variants WHERE variant_id = 4; -- 15 (kembali)

-- ---------------------------------------------------------------------
-- FUNCTION nomor struk
-- HASIL YANG DIHARAPKAN: STR-<tanggal hari ini>-0001 (atau nomor urut berikutnya)
-- ---------------------------------------------------------------------
SELECT fn_no_struk() AS nomor_struk_berikutnya;

-- ---------------------------------------------------------------------
-- Aturan bisnis lain (masing-masing HARUS menghasilkan ERROR 3819 "Check constraint ... is violated")
-- ---------------------------------------------------------------------
-- UPDATE products SET harga_jual = -1 WHERE product_id = 1;                 -- harga negatif ditolak
-- UPDATE product_variants SET stok = -1 WHERE variant_id = 1;               -- stok negatif ditolak
-- INSERT INTO payments (user_id, no_struk, total, dibayar) VALUES (1,'X',100,50);  -- uang bayar kurang ditolak

-- ---------------------------------------------------------------------
-- Riwayat perubahan stok (diisi otomatis oleh trigger trg_variants_au)
-- ---------------------------------------------------------------------
SELECT * FROM stock_log ORDER BY log_id DESC LIMIT 10;
