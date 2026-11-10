
USE dbstylehub;

ALTER TABLE payments
    ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tgl_pembayaran,
    ADD COLUMN diskon   DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN metode   VARCHAR(20)   NOT NULL DEFAULT 'Tunai' AFTER total;

-- Data lama: tidak ada diskon, jadi subtotal = total
UPDATE payments SET subtotal = total WHERE subtotal = 0;

ALTER TABLE payments
    ADD CONSTRAINT chk_payments_diskon CHECK (diskon >= 0 AND diskon <= subtotal),
    ADD CONSTRAINT chk_payments_metode CHECK (metode IN ('Tunai', 'Transfer', 'QRIS'));
