-- ============================================================
-- EXECUTE ESTE SQL AGORA no phpMyAdmin (banco: ingresso)
-- Corrige o estado deixado pela migration anterior com erro.
-- ============================================================

-- Passo 1: Remove o índice UNIQUE que foi criado com '' duplicado
ALTER TABLE `ingresso_tickets` DROP INDEX IF EXISTS `uid`;

-- Passo 2: Popula uid único em todos os registros vazios/nulos
UPDATE `ingresso_tickets`
SET `uid` = LOWER(SUBSTRING(SHA2(CONCAT(id, '-', created_at, '-', RAND()), 256), 1, 16))
WHERE `uid` = '' OR `uid` IS NULL;

-- Passo 3: Agora adiciona o UNIQUE KEY com segurança
ALTER TABLE `ingresso_tickets` ADD UNIQUE KEY `uid` (`uid`);

-- Passo 4: Remove enum 'pausado' não utilizado
UPDATE `ingresso_batches` SET `status` = 'ativo' WHERE `status` = 'pausado';
UPDATE `ingresso_tickets` SET `status` = 'ativo' WHERE `status` = 'pausado';

ALTER TABLE `ingresso_batches`
    MODIFY COLUMN `status` ENUM('ativo','em_breve','esgotado') NOT NULL DEFAULT 'ativo';

ALTER TABLE `ingresso_tickets`
    MODIFY COLUMN `status` ENUM('ativo','em_breve','esgotado') NOT NULL DEFAULT 'ativo';
