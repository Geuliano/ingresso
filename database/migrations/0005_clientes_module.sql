-- ============================================================================
-- Migration 0005: Módulo Clientes (Gestão de usuários do site)
-- ============================================================================
-- Adiciona colunas operacionais na tabela `users` do site principal.
-- Idempotente: usa IF NOT EXISTS / ignora erros se coluna já existir.
-- ============================================================================

-- Coluna para bloqueio de conta (null = ativo, data = bloqueado desde)
ALTER TABLE users ADD COLUMN IF NOT EXISTS blocked_at DATETIME NULL DEFAULT NULL;

-- Coluna para anotações internas do operador
ALTER TABLE users ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL DEFAULT NULL;

-- Coluna de soft-delete (null = ativo, data = excluído)
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

-- Índices para buscas frequentes
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_blocked_at (blocked_at);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_deleted_at (deleted_at);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_email_verified (email_verified_at);

-- ============================================================================
-- Registro do módulo na tabela de módulos do painel (executar manualmente
-- após inserir no banco, ajustando id_pai e ordem conforme necessário).
-- ============================================================================
-- INSERT IGNORE INTO modulos (nome, slug, descricao, icone, ordem, ativo, ver_menu)
-- VALUES ('Clientes', 'clientes', 'Gerenciamento de clientes do site', 'las la-users', 30, 1, 1);
