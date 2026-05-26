-- ============================================================
-- Migration: 0004_fix_and_cleanup.sql
-- Gerado em: 2026-05-22
-- ============================================================
--
-- EXECUTE COM:
--   mysql -u root ingresso < database/migrations/0004_fix_and_cleanup.sql
-- OU cole diretamente no phpMyAdmin (query completa).
-- ============================================================

-- ─────────────────────────────────────────────────────────────
-- 1. ADICIONAR COLUNA uid (sem UNIQUE ainda)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `ingresso_tickets`
    ADD COLUMN IF NOT EXISTS `uid` VARCHAR(32) NOT NULL DEFAULT ''
    AFTER `slug`;

-- ─────────────────────────────────────────────────────────────
-- 2. POPULAR uid NOS REGISTROS EXISTENTES
--    (precisa vir ANTES de criar o índice UNIQUE)
-- ─────────────────────────────────────────────────────────────
UPDATE `ingresso_tickets`
SET `uid` = LOWER(SUBSTRING(SHA2(CONCAT(id, '-', created_at, '-', RAND()), 256), 1, 16))
WHERE `uid` = '' OR `uid` IS NULL;

-- ─────────────────────────────────────────────────────────────
-- 3. AGORA sim: adicionar UNIQUE KEY em uid
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `ingresso_tickets`
    ADD UNIQUE KEY IF NOT EXISTS `uid` (`uid`);

-- ─────────────────────────────────────────────────────────────
-- 4. REMOVER ENUM 'pausado' NÃO UTILIZADO
-- ─────────────────────────────────────────────────────────────
UPDATE `ingresso_batches` SET `status` = 'ativo' WHERE `status` = 'pausado';
UPDATE `ingresso_tickets` SET `status` = 'ativo' WHERE `status` = 'pausado';

ALTER TABLE `ingresso_batches`
    MODIFY COLUMN `status`
    ENUM('ativo','em_breve','esgotado') NOT NULL DEFAULT 'ativo';

ALTER TABLE `ingresso_tickets`
    MODIFY COLUMN `status`
    ENUM('ativo','em_breve','esgotado') NOT NULL DEFAULT 'ativo';

-- ─────────────────────────────────────────────────────────────
-- FIM
-- ─────────────────────────────────────────────────────────────
