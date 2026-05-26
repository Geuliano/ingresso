-- ============================================================
-- Migration 0003: Registra o módulo 'ingressos' no painel admin
-- ============================================================
-- Execute manualmente ou via: php database/migrate.php
--
-- O módulo aparece no menu lateral do /admin e permite que
-- administradores gerenciem lotes (ingresso_batches) e tipos
-- de ingresso (ingresso_tickets) de forma completa.
-- ============================================================

-- 1. Insere o módulo (idempotente via INSERT IGNORE)
INSERT IGNORE INTO modulos (nome, slug, descricao, icone, ordem, id_pai, ativo, ver_menu)
VALUES (
    'Ingressos',
    'ingressos',
    'Gerenciamento de lotes e tipos de ingresso do evento',
    'las la-ticket-alt',
    5,
    NULL,
    1,
    1
);

-- 2. Concede todas as permissões ao nível de acesso de ID 1 (Administrador)
--    Ajuste o id_nivel conforme necessário para outros níveis.
INSERT IGNORE INTO permissoes (id_modulo, id_nivel, pode_visualizar, pode_criar, pode_editar, pode_excluir)
SELECT
    m.id,
    n.id,
    1, 1, 1, 1
FROM modulos m
CROSS JOIN niveis_acesso n
WHERE m.slug = 'ingressos'
  AND n.id = 1   -- ID 1 = Administrador (ajuste se necessário)
ON DUPLICATE KEY UPDATE
    pode_visualizar = 1,
    pode_criar      = 1,
    pode_editar     = 1,
    pode_excluir    = 1;

-- ── Verificação ──────────────────────────────────────────────
-- SELECT * FROM modulos WHERE slug = 'ingressos';
-- SELECT * FROM permissoes WHERE id_modulo = (SELECT id FROM modulos WHERE slug = 'ingressos');
