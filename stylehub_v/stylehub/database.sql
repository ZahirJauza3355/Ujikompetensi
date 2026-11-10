-- =====================================================================
--  DATABASE  : stylehub  (Toko Fashion "StyleHub")
--  Teknologi : MySQL / MariaDB  (disarankan MySQL 8.0.16+ atau MariaDB 10.5+)
--  Cara impor: phpMyAdmin > Import > pilih file ini  (atau: mysql -u root -p < database.sql)
--  Isi file  : 1) tabel + relasi  2) function  3) stored procedure
--              4) view            5) trigger   6) data awal / data uji
--  PERHATIAN : baris DROP DATABASE di bawah akan menghapus database lama
--              bernama "stylehub" supaya impor selalu bersih.
-- =====================================================================

DROP DATABASE IF EXISTS dbstylehub;
CREATE DATABASE dbstylehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dbstylehub;

-- ---------------------------------------------------------------------
-- 1. TABEL
-- ---------------------------------------------------------------------

-- 1.1 users : akun yang boleh login (admin / kasir)
CREATE TABLE users (
    user_id   INT          NOT NULL AUTO_INCREMENT,
    username  VARCHAR(30)  NOT NULL,
    password  VARCHAR(255) NOT NULL,            -- disimpan sebagai hash (password_hash PHP)
    nama      VARCHAR(100) NOT NULL,
    role      VARCHAR(20)  NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    CONSTRAINT chk_users_role CHECK (role IN ('admin', 'kasir'))
) ENGINE=InnoDB;

-- 1.2 categories : jenis pakaian / aksesori
CREATE TABLE categories (
    category_id   INT         NOT NULL AUTO_INCREMENT,
    nama_kategori VARCHAR(60) NOT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_nama (nama_kategori)
) ENGINE=InnoDB;

-- 1.3 products : data induk produk (belum ada ukuran/warna)
CREATE TABLE products (
    product_id  INT           NOT NULL AUTO_INCREMENT,
    category_id INT           NOT NULL,
    kode_produk VARCHAR(25)   NOT NULL,
    nama_produk VARCHAR(100)  NOT NULL,
    harga_jual  DECIMAL(12,2) NOT NULL,
    status      TINYINT       NOT NULL DEFAULT 1,   -- 1 aktif, 0 nonaktif
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_products_kode (kode_produk),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (category_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_products_harga CHECK (harga_jual >= 0)      -- aturan bisnis: harga tidak boleh negatif
) ENGINE=InnoDB;

-- 1.4 sizes : master ukuran
CREATE TABLE sizes (
    size_id     INT         NOT NULL AUTO_INCREMENT,
    nama_ukuran VARCHAR(20) NOT NULL,
    PRIMARY KEY (size_id),
    UNIQUE KEY uq_sizes_nama (nama_ukuran)
) ENGINE=InnoDB;

-- 1.5 colors : master warna
CREATE TABLE colors (
    color_id   INT         NOT NULL AUTO_INCREMENT,
    nama_warna VARCHAR(40) NOT NULL,
    PRIMARY KEY (color_id),
    UNIQUE KEY uq_colors_nama (nama_warna)
) ENGINE=InnoDB;

-- 1.6 product_variants : satu baris = satu variasi (produk + ukuran + warna) + stoknya
CREATE TABLE product_variants (
    variant_id INT           NOT NULL AUTO_INCREMENT,
    product_id INT           NOT NULL,
    size_id    INT           NULL,
    color_id   INT           NULL,
    stok       INT           NOT NULL DEFAULT 0,
    harga_jual DECIMAL(12,2) NULL,                  -- harga khusus variasi (NULL = ikut harga produk)
    PRIMARY KEY (variant_id),
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_variants_size FOREIGN KEY (size_id)
        REFERENCES sizes (size_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_variants_color FOREIGN KEY (color_id)
        REFERENCES colors (color_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_variants_stok  CHECK (stok >= 0),
    CONSTRAINT chk_variants_harga CHECK (harga_jual IS NULL OR harga_jual >= 0)
) ENGINE=InnoDB;

-- 1.7 stock_log : riwayat perubahan stok (diisi otomatis oleh trigger)
CREATE TABLE stock_log (
    log_id     INT          NOT NULL AUTO_INCREMENT,
    variant_id INT          NOT NULL,
    stok_lama  INT          NOT NULL,
    stok_baru  INT          NOT NULL,
    waktu      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    keterangan VARCHAR(100) NULL,
    PRIMARY KEY (log_id),
    KEY idx_stocklog_variant (variant_id)
) ENGINE=InnoDB;

-- 1.8 payments : header pembayaran (satu baris = satu struk)
CREATE TABLE payments (
    payment_id     INT           NOT NULL AUTO_INCREMENT,
    user_id        INT           NOT NULL,            -- kasir yang melayani
    no_struk       VARCHAR(30)   NOT NULL,
    tgl_pembayaran DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,  -- jumlah semua item sebelum diskon
    diskon         DECIMAL(12,2) NOT NULL DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,  -- subtotal - diskon
    metode         VARCHAR(20)   NOT NULL DEFAULT 'Tunai',
    dibayar        DECIMAL(12,2) NOT NULL DEFAULT 0,
    kembalian      DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (payment_id),
    UNIQUE KEY uq_payments_struk (no_struk),
    KEY idx_payments_tgl (tgl_pembayaran),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_payments_bayar  CHECK (dibayar >= total),   -- aturan bisnis: uang bayar harus cukup
    CONSTRAINT chk_payments_diskon CHECK (diskon >= 0 AND diskon <= subtotal),
    CONSTRAINT chk_payments_metode CHECK (metode IN ('Tunai', 'Transfer', 'QRIS'))
) ENGINE=InnoDB;

-- 1.9 payment_items : detail pembayaran, mengacu ke VARIASI karena stok disimpan per variasi
CREATE TABLE payment_items (
    item_id    INT           NOT NULL AUTO_INCREMENT,
    payment_id INT           NOT NULL,
    variant_id INT           NOT NULL,
    harga_jual DECIMAL(12,2) NOT NULL,              -- harga saat pembayaran (snapshot)
    qty        INT           NOT NULL,
    subtotal   DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (item_id),
    KEY idx_pitems_payment (payment_id),
    CONSTRAINT fk_pitems_payment FOREIGN KEY (payment_id)
        REFERENCES payments (payment_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pitems_variant FOREIGN KEY (variant_id)
        REFERENCES product_variants (variant_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_pitems_qty   CHECK (qty > 0),
    CONSTRAINT chk_pitems_harga CHECK (harga_jual >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. FUNCTION
-- ---------------------------------------------------------------------
DELIMITER $$

-- 2.1 Status stok: HABIS / RESTOK (stok <= 5) / AMAN
CREATE FUNCTION fn_status_stok(p_stok INT)
RETURNS VARCHAR(10)
DETERMINISTIC
BEGIN
    IF p_stok <= 0 THEN
        RETURN 'HABIS';
    ELSEIF p_stok <= 5 THEN
        RETURN 'RESTOK';
    END IF;
    RETURN 'AMAN';
END$$

-- 2.2 Membuat nomor struk otomatis, format STR-YYYYMMDD-0001 (urut per hari)
CREATE FUNCTION fn_no_struk()
RETURNS VARCHAR(30)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(20);
    DECLARE v_urut   INT;

    SET v_prefix = CONCAT('STR-', DATE_FORMAT(NOW(), '%Y%m%d'), '-');

    SELECT COALESCE(MAX(CAST(RIGHT(no_struk, 4) AS UNSIGNED)), 0) + 1
      INTO v_urut
      FROM payments
     WHERE no_struk LIKE CONCAT(v_prefix, '%');

    RETURN CONCAT(v_prefix, LPAD(v_urut, 4, '0'));
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 3. VIEW : gabungan produk + kategori + ukuran + warna + stok (hasil JOIN)
-- ---------------------------------------------------------------------
CREATE VIEW v_variants AS
SELECT v.variant_id,
       v.product_id,
       p.kode_produk,
       p.nama_produk,
       p.status                          AS status_produk,
       c.category_id,
       c.nama_kategori,
       s.nama_ukuran,
       cl.nama_warna,
       v.stok,
       COALESCE(v.harga_jual, p.harga_jual) AS harga,
       fn_status_stok(v.stok)            AS status_stok
  FROM product_variants v
  JOIN products   p  ON p.product_id  = v.product_id
  JOIN categories c  ON c.category_id = p.category_id
  LEFT JOIN sizes  s  ON s.size_id    = v.size_id
  LEFT JOIN colors cl ON cl.color_id  = v.color_id;

-- ---------------------------------------------------------------------
-- 4. TRIGGER
-- ---------------------------------------------------------------------
DELIMITER $$

-- 4.1 SEBELUM detail pembayaran disimpan: tolak jika qty salah atau melebihi stok variasi
CREATE TRIGGER trg_payment_items_bi
BEFORE INSERT ON payment_items
FOR EACH ROW
BEGIN
    DECLARE v_stok INT DEFAULT NULL;

    SELECT stok INTO v_stok FROM product_variants WHERE variant_id = NEW.variant_id;

    IF v_stok IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Variasi tidak ditemukan';
    END IF;
    IF NEW.qty <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Qty harus lebih dari 0';
    END IF;
    IF NEW.qty > v_stok THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok tidak mencukupi';
    END IF;
END$$

-- 4.2 SETELAH detail pembayaran disimpan: kurangi stok variasi otomatis
CREATE TRIGGER trg_payment_items_ai
AFTER INSERT ON payment_items
FOR EACH ROW
BEGIN
    UPDATE product_variants
       SET stok = stok - NEW.qty
     WHERE variant_id = NEW.variant_id;
END$$

-- 4.3 SETELAH stok berubah: catat ke stock_log (pencatatan perubahan data)
CREATE TRIGGER trg_variants_au
AFTER UPDATE ON product_variants
FOR EACH ROW
BEGIN
    IF OLD.stok <> NEW.stok THEN
        INSERT INTO stock_log (variant_id, stok_lama, stok_baru, keterangan)
        VALUES (NEW.variant_id, OLD.stok, NEW.stok, 'Perubahan stok');
    END IF;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 5. DATA AWAL / DATA UJI
-- ---------------------------------------------------------------------

-- Akun login  ->  admin / admin123   dan   kasir / kasir123
INSERT INTO users (user_id, username, password, nama, role) VALUES
(1, 'admin', '$2y$10$OrbW0chRE/xz//mXa5MOge69maCC/CBZk2Y/t6ezYjl/80Vw1zGvu', 'Administrator', 'admin'),
(2, 'kasir', '$2y$10$4BuQ.LBxykr4rqihrPEPIO.Pqf5WbGu/mFbRYJnqSz2LyVHddQGkO', 'Kasir StyleHub', 'kasir');

INSERT INTO categories (category_id, nama_kategori) VALUES
(1, 'Kaos'), (2, 'Kemeja'), (3, 'Celana'), (4, 'Jaket'), (5, 'Aksesori');

INSERT INTO sizes (size_id, nama_ukuran) VALUES
(1, 'S'), (2, 'M'), (3, 'L'), (4, 'XL'), (5, 'XXL'), (6, 'All Size');

INSERT INTO colors (color_id, nama_warna) VALUES
(1, 'Hitam'), (2, 'Putih'), (3, 'Navy'), (4, 'Merah'), (5, 'Abu-abu'), (6, 'Coklat');

INSERT INTO products (product_id, category_id, kode_produk, nama_produk, harga_jual, status) VALUES
(1, 1, 'KS-001', 'Kaos Polos Cotton',      75000, 1),
(2, 1, 'KS-002', 'Kaos Oversize Graphic', 120000, 1),
(3, 2, 'KM-001', 'Kemeja Flanel',         185000, 1),
(4, 2, 'KM-002', 'Kemeja Oxford Putih',   210000, 1),
(5, 3, 'CL-001', 'Celana Chino',          225000, 1),
(6, 3, 'CL-002', 'Celana Jeans Slim',     260000, 1),
(7, 4, 'JK-001', 'Jaket Bomber',          350000, 1),
(8, 5, 'AK-001', 'Topi Baseball',          65000, 1),
(9, 5, 'AK-002', 'Ikat Pinggang Kulit',    95000, 1);

-- (variant_id, product_id, size_id, color_id, stok, harga_jual khusus)
INSERT INTO product_variants (variant_id, product_id, size_id, color_id, stok, harga_jual) VALUES
( 1, 1, 1, 1, 20, NULL),
( 2, 1, 2, 1, 25, NULL),
( 3, 1, 3, 1, 18, NULL),
( 4, 1, 2, 2, 15, NULL),
( 5, 1, 3, 2, 3, NULL),     -- stok rendah
( 6, 2, 2, 1, 12, NULL),
( 7, 2, 3, 1, 10, NULL),
( 8, 2, 4, 5, 4, 125000),   -- stok rendah + harga khusus
( 9, 3, 2, 4, 8, NULL),
(10, 3, 3, 4, 8, NULL),
(11, 3, 3, 3, 6, NULL),
(12, 4, 2, 2, 10, NULL),
(13, 4, 3, 2, 10, NULL),
(14, 5, 2, 6, 7, NULL),
(15, 5, 3, 3, 9, NULL),
(16, 6, 3, 1, 5, NULL),     -- stok rendah (batas)
(17, 6, 4, 1, 6, 270000),   -- harga khusus
(18, 7, 3, 1, 4, NULL),     -- stok rendah
(19, 7, 4, 1, 5, NULL),
(20, 8, 6, 1, 30, NULL),
(21, 8, 6, 3, 22, NULL),
(22, 9, 6, 6, 14, NULL),
(23, 9, 6, 1, 0, NULL);     -- stok habis
