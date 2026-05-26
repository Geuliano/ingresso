-- =============================================================================
-- MIGRATION 0006 — Módulo Line-up / Atrações
-- =============================================================================

CREATE TABLE IF NOT EXISTS lineup_atracoes (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(255)    NOT NULL,
    nivel       TINYINT UNSIGNED NOT NULL DEFAULT 3
                    COMMENT '1=Headliner | 2=Sub-headliner | 3=Suporte | 4=Abertura | 5=Extra',
    descricao   TEXT            NULL,
    ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo       TINYINT(1)      NOT NULL DEFAULT 1,
    criado_por  INT UNSIGNED    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME        NULL,
    INDEX idx_nivel   (nivel),
    INDEX idx_ativo   (ativo),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
