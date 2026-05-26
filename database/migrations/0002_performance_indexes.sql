-- Indices compostos para queries quentes detectadas na analise.
-- O migrator e idempotente: cada CREATE INDEX e protegido por checagem.

-- latest_code() + ensure_not_on_cooldown() filtram por user_id + purpose
-- e ordenam por id DESC. Indice composto evita filesort.
CREATE INDEX idx_codes_user_purpose_id
  ON verification_codes (user_id, purpose, id);

-- Lista de tickets por batch sempre ordena por ordem ASC, id ASC.
CREATE INDEX idx_ingresso_tickets_batch_ordem
  ON ingresso_tickets (batch_id, ordem, id);

-- Lookups por purpose + created_ip (rate limit) podem ficar mais rapidos.
CREATE INDEX idx_codes_purpose_ip_created
  ON verification_codes (purpose, created_ip, created_at);
