-- ============================================================
-- Migration: Soft Delete em lotes e ingressos
-- Executar uma única vez no banco de dados.
-- ============================================================

ALTER TABLE ingresso_batches
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE ingresso_tickets
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

-- Índices para acelerar queries que filtram registros não excluídos
CREATE INDEX IF NOT EXISTS idx_batches_deleted  ON ingresso_batches (deleted_at);
CREATE INDEX IF NOT EXISTS idx_tickets_deleted  ON ingresso_tickets  (deleted_at);
