-- ============================================================================
-- Migration 0007: Módulo de Pedidos e Pagamentos (Mercado Pago)
-- ============================================================================
-- Cria as tabelas `pedidos` e `pedido_items` para registrar compras
-- e vincular cada ingresso ao seu titular.
-- Idempotente: usa IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS pedidos (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uid         VARCHAR(32)     NOT NULL UNIQUE,  -- referência interna (ex.: link de confirmação)
    user_id     INT UNSIGNED    NULL,             -- NULL = guest checkout
    status      ENUM(
        'pendente',       -- criado, aguardando pagamento
        'processando',    -- enviado ao MP, aguardando retorno
        'aprovado',       -- pagamento confirmado
        'pendente_pix',   -- PIX gerado, aguardando pagamento
        'rejeitado',      -- cartão recusado
        'cancelado',      -- cancelado manualmente
        'reembolsado'     -- estornado
    ) NOT NULL DEFAULT 'pendente',
    total           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    -- Dados do pagador
    payer_email     VARCHAR(255)    NULL,
    payer_name      VARCHAR(255)    NULL,
    payer_cpf       VARCHAR(20)     NULL,
    -- Dados do Mercado Pago
    mp_payment_id       BIGINT      NULL,
    mp_payment_status   VARCHAR(60) NULL,   -- approved, pending, rejected, etc.
    mp_payment_method   VARCHAR(60) NULL,   -- pix, credit_card, debit_card
    mp_idempotency_key  VARCHAR(80) NULL,
    -- Dados do PIX (quando aplicável)
    pix_qr_code         TEXT        NULL,
    pix_qr_code_base64  TEXT        NULL,
    pix_expiration_date DATETIME    NULL,
    -- Timestamps
    paid_at     DATETIME    NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME    NULL,

    PRIMARY KEY (id),
    KEY idx_pedidos_uid         (uid),
    KEY idx_pedidos_status      (status),
    KEY idx_pedidos_user_id     (user_id),
    KEY idx_pedidos_mp_payment  (mp_payment_id),
    KEY idx_pedidos_created_at  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================

CREATE TABLE IF NOT EXISTS pedido_items (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id       BIGINT UNSIGNED NOT NULL,
    -- Snapshot do ticket no momento da compra
    ticket_id       INT UNSIGNED    NOT NULL,
    ticket_uid      VARCHAR(32)     NOT NULL,
    ticket_nome     VARCHAR(160)    NOT NULL,
    batch_nome      VARCHAR(160)    NOT NULL DEFAULT '',
    -- Titular do ingresso
    titular_nome    VARCHAR(255)    NOT NULL,
    titular_cpf     VARCHAR(20)     NOT NULL,
    -- Preços
    unit_price      DECIMAL(10,2)   NOT NULL,
    fee             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    subtotal        DECIMAL(10,2)   NOT NULL,
    -- Emissão do ingresso
    ingresso_uid    VARCHAR(32)     NULL UNIQUE, -- gerado após aprovação
    emitido_at      DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_pedido_items_pedido     (pedido_id),
    KEY idx_pedido_items_ticket     (ticket_id),
    KEY idx_pedido_items_ingresso   (ingresso_uid),
    CONSTRAINT fk_pedido_items_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
