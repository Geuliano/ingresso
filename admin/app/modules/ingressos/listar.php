<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';
require_once APP_PATH . '/core/permissions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Auto-migration: garante colunas deleted_at (soft delete) ─────────────────
// Roda apenas uma vez por sessão para não ter overhead a cada request.
if (empty($_SESSION['_ing_softdelete_migrated'])) {
    try {
        $pdo->exec("ALTER TABLE ingresso_batches ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE ingresso_tickets  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL");
        $_SESSION['_ing_softdelete_migrated'] = true;
    } catch (Throwable $_migErr) {
        // Se não suportar IF NOT EXISTS, tenta via information_schema
        try {
            $stmtChk = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'deleted_at'
            ");
            $stmtChk->execute(['ingresso_batches']);
            if (!(int)$stmtChk->fetchColumn()) {
                $pdo->exec("ALTER TABLE ingresso_batches ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            }
            $stmtChk->execute(['ingresso_tickets']);
            if (!(int)$stmtChk->fetchColumn()) {
                $pdo->exec("ALTER TABLE ingresso_tickets ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            }
            $_SESSION['_ing_softdelete_migrated'] = true;
        } catch (Throwable $_) { /* silencioso */ }
    }
}

// ── Estatísticas ─────────────────────────────────────────────────────────────
$stats = ['total_lotes' => 0, 'lotes_visiveis' => 0, 'total_ingressos' => 0, 'total_disponivel' => 0];
try {
    $stats['total_lotes']     = (int)$pdo->query("SELECT COUNT(*) FROM ingresso_batches WHERE deleted_at IS NULL")->fetchColumn();
    $stats['lotes_visiveis']  = (int)$pdo->query("SELECT COUNT(*) FROM ingresso_batches WHERE visible = 1 AND deleted_at IS NULL")->fetchColumn();
    $stats['total_ingressos'] = (int)$pdo->query("SELECT COUNT(*) FROM ingresso_tickets WHERE deleted_at IS NULL")->fetchColumn();
    $stats['total_disponivel']= (int)$pdo->query("SELECT COALESCE(SUM(available),0) FROM ingresso_tickets WHERE status='ativo' AND deleted_at IS NULL")->fetchColumn();
} catch (Throwable $e) { /* silencioso */ }

// ── Lotes ─────────────────────────────────────────────────────────────────────
$lotes = [];
try {
    $stmt = $pdo->query("
        SELECT
            b.id, b.slug, b.nome, b.status, b.start_at, b.end_at,
            b.highlight, b.visible, b.ordem,
            COUNT(t.id)                  AS qtd_ingressos,
            COALESCE(SUM(t.available),0) AS total_disponivel
        FROM ingresso_batches b
        LEFT JOIN ingresso_tickets t ON t.batch_id = b.id AND t.deleted_at IS NULL
        WHERE b.deleted_at IS NULL
        GROUP BY b.id, b.slug, b.nome, b.status, b.start_at, b.end_at, b.highlight, b.visible, b.ordem
        ORDER BY b.ordem ASC, b.id ASC
    ");
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $lotes = []; }

// ── Lotes excluídos (soft-deleted) ───────────────────────────────────────────
$lotesExcluidos = [];
try {
    $stmt = $pdo->query("
        SELECT
            b.id, b.slug, b.nome, b.status, b.start_at, b.end_at,
            b.visible, b.deleted_at,
            COUNT(t.id) AS qtd_ingressos
        FROM ingresso_batches b
        LEFT JOIN ingresso_tickets t ON t.batch_id = b.id
        WHERE b.deleted_at IS NOT NULL
        GROUP BY b.id, b.slug, b.nome, b.status, b.start_at, b.end_at, b.visible, b.deleted_at
        ORDER BY b.deleted_at DESC
    ");
    $lotesExcluidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $lotesExcluidos = []; }

// ── Ingressos ─────────────────────────────────────────────────────────────────
$ingressos = [];
try {
    // Verifica dinamicamente se a coluna uid existe para não quebrar
    // em bancos que ainda não rodaram a migration de adição do uid.
    $hasUid = false;
    try {
        $stmtUid = $pdo->query("SELECT uid FROM ingresso_tickets LIMIT 0");
        $hasUid  = ($stmtUid !== false);
    } catch (Throwable $_) {}

    $uidField = $hasUid ? 't.uid,' : "''" . ' AS uid,';

    $stmt = $pdo->query("
        SELECT
            t.id, t.batch_id, t.slug, {$uidField}
            t.nome, t.base_price, t.fee, t.available, t.status,
            t.progress, t.highlight, t.show_highlight, t.ordem,
            t.start_at, t.end_at, t.deleted_at,
            b.nome AS lote_nome,
            b.start_at AS batch_start_at,
            b.end_at   AS batch_end_at
        FROM ingresso_tickets t
        INNER JOIN ingresso_batches b ON b.id = t.batch_id AND b.deleted_at IS NULL
        ORDER BY b.ordem ASC, t.deleted_at ASC, t.ordem ASC, t.id ASC
    ");
    $ingressos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $ingressos = []; }

// ── Agrupa ingressos por lote (separa ativos de excluídos) ───────────────────
$ingressosPorLote = [];
foreach ($ingressos as $ing) {
    $bid = (int)$ing['batch_id'];
    if (!isset($ingressosPorLote[$bid])) {
        $ingressosPorLote[$bid] = [
            'lote_nome' => $ing['lote_nome'],
            'batch_id'  => $bid,
            'ativos'    => [],
            'excluidos' => [],
        ];
    }
    if ($ing['deleted_at']) {
        $ingressosPorLote[$bid]['excluidos'][] = $ing;
    } else {
        $ingressosPorLote[$bid]['ativos'][] = $ing;
    }
}

// ── Lotes para select nos forms ───────────────────────────────────────────────
$lotesParaSelect = [];
try {
    $stmt = $pdo->query("SELECT id, nome, start_at, end_at FROM ingresso_batches WHERE deleted_at IS NULL ORDER BY ordem ASC, nome ASC");
    $lotesParaSelect = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $lotesParaSelect = []; }

$csrfToken        = $_SESSION['csrf_token'] ?? '';
$possuiLotes      = !empty($lotesParaSelect);
$podeExcluir      = userHasPermission($_SESSION['usuario_id'] ?? 0, 'ingressos', 'pode_excluir');

// ── Helpers de renderização ────────────────────────────────────────────────
/** Calcula o status exibido em tempo real, sem depender do valor armazenado. */
function computeIngressoStatus(array $ing): string {
    $now   = time();
    $start = !empty($ing['start_at'])       ? strtotime($ing['start_at'])       :
             (!empty($ing['batch_start_at']) ? strtotime($ing['batch_start_at']) : null);
    $end   = !empty($ing['end_at'])         ? strtotime($ing['end_at'])         :
             (!empty($ing['batch_end_at'])   ? strtotime($ing['batch_end_at'])   : null);

    if ($start && $now < $start) return 'em_breve';
    if ($end   && $now > $end)   return 'esgotado';
    return (int)$ing['available'] > 0 ? 'ativo' : 'esgotado';
}

function statusBadge(string $status): string {
    $map = ['ativo' => ['success','Ativo'], 'em_breve' => ['warning','Em breve'], 'esgotado' => ['danger','Esgotado']];
    [$cls, $label] = $map[$status] ?? ['secondary', $status];
    return "<span class='badge bg-{$cls}-subtle text-{$cls} fw-semibold px-2'>{$label}</span>";
}
function fmtDate(?string $dt): string {
    if (!$dt) return '<span class="text-muted">—</span>';
    try { return (new DateTime($dt, new DateTimeZone('America/Porto_Velho')))->format('d/m/Y H:i'); }
    catch (Throwable $e) { return htmlspecialchars($dt); }
}
function fmtMoney(float $v): string { return 'R$&nbsp;' . number_format($v, 2, ',', '.'); }
?>

<link href="<?= BASE_URL ?>public/assets/libs/simple-datatables/style.css" rel="stylesheet">

<!-- ── Cards de estatísticas ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-primary-subtle rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="las la-layer-group fs-24 text-primary"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 fs-13">Total de Lotes</p>
                    <h3 class="mb-0 fw-bold"><?= $stats['total_lotes'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-success-subtle rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="las la-eye fs-24 text-success"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 fs-13">Lotes Visíveis</p>
                    <h3 class="mb-0 fw-bold"><?= $stats['lotes_visiveis'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-info-subtle rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="las la-ticket-alt fs-24 text-info"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 fs-13">Tipos de Ingresso</p>
                    <h3 class="mb-0 fw-bold"><?= $stats['total_ingressos'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-md bg-warning-subtle rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="las la-users fs-24 text-warning"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 fs-13">Unidades Disponíveis</p>
                    <h3 class="mb-0 fw-bold"><?= number_format($stats['total_disponivel'], 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Card principal com abas ───────────────────────────────────────────── -->
<div class="card shadow-sm border-0">
    <div class="card-header pb-0 border-bottom-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <ul class="nav nav-tabs card-header-tabs mb-0" id="tabsIngressos" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="tab-lotes-btn"
                        data-bs-toggle="tab" data-bs-target="#tab-lotes" type="button" role="tab">
                        <i class="las la-layer-group me-1"></i>Lotes
                        <span class="badge bg-secondary ms-1"><?= count($lotes) ?></span>
                        <?php if (!empty($lotesExcluidos)): ?>
                        <span class="badge bg-danger ms-1" title="Lotes excluídos"><?= count($lotesExcluidos) ?> exc.</span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="tab-ingressos-btn"
                        data-bs-toggle="tab" data-bs-target="#tab-ingressos" type="button" role="tab">
                        <i class="las la-ticket-alt me-1"></i>Ingressos
                        <?php $totalAtivos = array_sum(array_map(fn($g) => count($g['ativos']), $ingressosPorLote)); ?>
                        <span class="badge bg-secondary ms-1"><?= $totalAtivos ?></span>
                    </button>
                </li>
            </ul>
            <div id="botaoNovo">
                <button type="button" class="btn bg-primary text-white btn-tab-lotes" onclick="openCreateLote()">
                    <i class="las la-plus me-1"></i> Novo Lote
                </button>
                <button type="button" class="btn bg-primary text-white btn-tab-ingressos d-none"
                    onclick="openCreateIngresso()"
                    <?= !$possuiLotes ? 'disabled title="Cadastre um lote primeiro"' : '' ?>>
                    <i class="las la-plus me-1"></i> Novo Ingresso
                </button>
            </div>
        </div>
    </div>

    <div class="tab-content">

        <!-- ═════════════ TAB: LOTES ═════════════ -->
        <div class="tab-pane fade show active" id="tab-lotes" role="tabpanel">
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table mb-0" id="tabela_lotes">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:36px;"></th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Período</th>
                                <th class="text-center">Visível</th>
                                <th class="text-center">Ingressos</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="lotes-sortable-body">
                            <?php foreach ($lotes as $lote): ?>
                            <tr data-lote-id="<?= (int)$lote['id'] ?>">
                                <td class="text-muted" style="cursor:grab;">
                                    <i class="las la-grip-vertical fs-18 drag-handle"></i>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($lote['nome']) ?></div>
                                    <code class="text-muted fs-12"><?= htmlspecialchars($lote['slug']) ?></code>
                                </td>
                                <td><?= statusBadge($lote['status']) ?></td>
                                <td class="fs-12 text-muted">
                                    <?php if ($lote['start_at'] || $lote['end_at']): ?>
                                        <?= fmtDate($lote['start_at']) ?><br>até <?= fmtDate($lote['end_at']) ?>
                                    <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$lote['visible']): ?>
                                        <span class="badge bg-success-subtle text-success">Sim</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-muted">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info-subtle text-info fw-semibold"><?= (int)$lote['qtd_ingressos'] ?></span>
                                    <?php if ((int)$lote['qtd_ingressos'] > 0): ?>
                                        <small class="text-muted ms-1">(<?= number_format((int)$lote['total_disponivel'], 0, ',', '.') ?> disp.)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-link text-secondary p-0 me-1"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Editar lote"
                                        onclick="openEditLote(<?= (int)$lote['id'] ?>)">
                                        <i class="las la-pen fs-18"></i>
                                    </button>
                                    <button type="button"
                                        class="btn btn-link p-0 me-1 <?= (int)$lote['visible'] ? 'text-success' : 'text-secondary' ?>"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="<?= (int)$lote['visible'] ? 'Ocultar do site' : 'Exibir no site' ?>"
                                        onclick="toggleVisivelLote(<?= (int)$lote['id'] ?>, <?= (int)$lote['visible'] ?>)">
                                        <i class="las <?= (int)$lote['visible'] ? 'la-eye' : 'la-eye-slash' ?> fs-18"></i>
                                    </button>
                                    <?php if ($podeExcluir): ?>
                                    <button type="button" class="btn btn-link text-danger p-0"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir lote"
                                        onclick="confirmDeleteLote(<?= (int)$lote['id'] ?>)">
                                        <i class="las la-trash-alt fs-18"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($lotes) && empty($lotesExcluidos)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="las la-layer-group fs-36 d-block mb-2 opacity-50"></i>
                                    Nenhum lote cadastrado. Clique em <strong>Novo Lote</strong> para começar.
                                </td>
                            </tr>
                            <?php elseif (empty($lotes)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="las la-layer-group fs-28 d-block mb-1 opacity-40"></i>
                                    Nenhum lote ativo.
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($lotesExcluidos as $lote): ?>
                            <tr class="table-secondary opacity-75" data-lote-excluido-id="<?= (int)$lote['id'] ?>">
                                <td class="text-muted">
                                    <i class="las la-ban fs-18 text-danger opacity-50"></i>
                                </td>
                                <td>
                                    <div class="fw-semibold text-muted text-decoration-line-through"><?= htmlspecialchars($lote['nome']) ?></div>
                                    <span class="badge bg-danger-subtle text-danger fw-semibold px-2 py-1">Excluído</span>
                                    <small class="text-muted ms-1">em <?= fmtDate($lote['deleted_at']) ?></small>
                                </td>
                                <td class="text-muted">—</td>
                                <td class="fs-12 text-muted">
                                    <?php if ($lote['start_at'] || $lote['end_at']): ?>
                                        <?= fmtDate($lote['start_at']) ?><br>até <?= fmtDate($lote['end_at']) ?>
                                    <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
                                </td>
                                <td class="text-center text-muted">—</td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-muted fw-semibold"><?= (int)$lote['qtd_ingressos'] ?></span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Restaurar lote e seus ingressos"
                                        onclick="restaurarLote(<?= (int)$lote['id'] ?>)">
                                        <i class="las la-undo me-1"></i>Restaurar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═════════════ TAB: INGRESSOS ═════════════ -->
        <div class="tab-pane fade" id="tab-ingressos" role="tabpanel">
            <div class="card-body pt-3">

                <?php if (empty($ingressosPorLote)): ?>
                <div class="text-center text-muted py-5">
                    <i class="las la-ticket-alt fs-36 d-block mb-2 opacity-50"></i>
                    Nenhum ingresso cadastrado ainda.
                </div>
                <?php else: ?>

                <!-- Filtro rápido -->
                <div class="mb-3">
                    <div class="input-group input-group-sm" style="max-width:320px;">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="las la-search text-muted"></i>
                        </span>
                        <input type="search" id="filtroIngressos" class="form-control border-start-0 ps-0"
                            placeholder="Filtrar ingressos...">
                    </div>
                </div>

                <!-- Accordion: um item por lote -->
                <div class="accordion accordion-flush" id="acordeaoLotes">
                    <?php $loteIdx = 0; foreach ($ingressosPorLote as $bid => $grupo): $loteIdx++; ?>
                    <?php
                        $totalDisp  = array_sum(array_column($grupo['ativos'], 'available'));
                        $qtdIng     = count($grupo['ativos']);
                        $qtdExc     = count($grupo['excluidos']);
                        $collapseId = 'loteCollapse' . $bid;
                        $headerId   = 'loteHeader'   . $bid;
                    ?>
                    <div class="accordion-item border rounded mb-2 ingresso-grupo" data-lote="<?= strtolower(htmlspecialchars($grupo['lote_nome'])) ?>">
                        <h2 class="accordion-header d-flex align-items-center pe-2" id="<?= $headerId ?>">
                            <button class="accordion-button fw-semibold rounded collapsed flex-grow-1"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= $collapseId ?>"
                                aria-expanded="<?= $loteIdx === 1 ? 'true' : 'false' ?>"
                                aria-controls="<?= $collapseId ?>">
                                <i class="las la-layer-group me-2 text-primary fs-18"></i>
                                <span class="me-2"><?= htmlspecialchars($grupo['lote_nome']) ?></span>
                                <span class="badge bg-info-subtle text-info fw-semibold me-1" title="Tipos de ingresso">
                                    <?= $qtdIng ?> ingresso<?= $qtdIng !== 1 ? 's' : '' ?>
                                </span>
                                <span class="badge bg-success-subtle text-success fw-semibold me-1" title="Unidades disponíveis">
                                    <?= number_format($totalDisp, 0, ',', '.') ?> disp.
                                </span>
                                <?php if ($qtdExc > 0): ?>
                                <span class="badge bg-danger-subtle text-danger fw-semibold" title="Ingressos excluídos">
                                    <?= $qtdExc ?> excl.
                                </span>
                                <?php endif; ?>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-primary ms-2 flex-shrink-0"
                                style="white-space:nowrap;"
                                title="Adicionar ingresso neste lote"
                                onclick="event.stopPropagation(); openCreateIngressoNoLote(<?= $bid ?>, <?= htmlspecialchars(json_encode($grupo['lote_nome'])) ?>)">
                                <i class="las la-plus me-1"></i>Ingresso
                            </button>
                        </h2>
                        <div id="<?= $collapseId ?>"
                            class="accordion-collapse collapse <?= $loteIdx === 1 ? 'show' : '' ?>"
                            aria-labelledby="<?= $headerId ?>">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 align-middle ingresso-tabela">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:36px;"></th>
                                                <th>Nome</th>
                                                <th>Preço</th>
                                                <th class="text-center" style="width:110px;">Disponíveis</th>
                                                <th style="min-width:130px;">Progresso</th>
                                                <th style="width:110px;">Status</th>
                                                <th class="text-end pe-3" style="width:90px;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="ingresso-sortable-body" data-batch-id="<?= $bid ?>">
                                            <?php foreach ($grupo['ativos'] as $ing): ?>
                                            <?php $prog = max(0, min(100, (int)$ing['progress'])); ?>
                                            <tr class="ingresso-linha"
                                                data-ingresso-id="<?= (int)$ing['id'] ?>"
                                                data-search="<?= strtolower(htmlspecialchars($ing['nome'] . ' ' . $grupo['lote_nome'])) ?>">
                                                <td class="ps-2 text-muted" style="cursor:grab;">
                                                    <i class="las la-grip-vertical fs-18 drag-handle"></i>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($ing['nome']) ?></div>
                                                    <code class="text-muted fs-12"><?= htmlspecialchars($ing['slug']) ?></code>
                                                </td>
                                                <td class="fs-13">
                                                    <span class="fw-semibold"><?= fmtMoney((float)$ing['base_price']) ?></span>
                                                    <?php if ((float)$ing['fee'] > 0): ?>
                                                        <br><small class="text-muted">+ <?= fmtMoney((float)$ing['fee']) ?> taxa</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center fw-semibold"><?= number_format((int)$ing['available'], 0, ',', '.') ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height:6px;" title="<?= $prog ?>%">
                                                            <div class="progress-bar <?= $prog >= 80 ? 'bg-danger' : ($prog >= 50 ? 'bg-warning' : 'bg-success') ?>"
                                                                style="width:<?= $prog ?>%"></div>
                                                        </div>
                                                        <small class="text-muted fs-12"><?= $prog ?>%</small>
                                                    </div>
                                                </td>
                                                <td><?= statusBadge(computeIngressoStatus($ing)) ?></td>
                                                <td class="text-end pe-3">
                                                    <button type="button" class="btn btn-link text-secondary p-0 me-1"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Editar ingresso"
                                                        onclick="openEditIngresso(<?= (int)$ing['id'] ?>)">
                                                        <i class="las la-pen fs-18"></i>
                                                    </button>
                                                    <?php if ($podeExcluir): ?>
                                                    <button type="button" class="btn btn-link text-danger p-0"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir ingresso"
                                                        onclick="confirmDeleteIngresso(<?= (int)$ing['id'] ?>)">
                                                        <i class="las la-trash-alt fs-18"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>

                                            <?php if (empty($grupo['ativos'])): ?>
                                            <tr class="ingresso-linha" data-search="<?= strtolower(htmlspecialchars($grupo['lote_nome'])) ?>">
                                                <td colspan="7" class="text-center text-muted py-3 fst-italic fs-13">
                                                    Nenhum ingresso ativo neste lote.
                                                </td>
                                            </tr>
                                            <?php endif; ?>

                                            <?php foreach ($grupo['excluidos'] as $ing): ?>
                                            <tr class="ingresso-linha table-secondary opacity-75"
                                                data-search="<?= strtolower(htmlspecialchars($ing['nome'] . ' ' . $grupo['lote_nome'])) ?>">
                                                <td class="ps-2 text-muted">
                                                    <i class="las la-ban fs-16 text-danger opacity-50"></i>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-muted text-decoration-line-through"><?= htmlspecialchars($ing['nome']) ?></div>
                                                    <span class="badge bg-danger-subtle text-danger fw-semibold px-2">Excluído</span>
                                                    <small class="text-muted ms-1">em <?= fmtDate($ing['deleted_at']) ?></small>
                                                </td>
                                                <td class="fs-13 text-muted"><?= fmtMoney((float)$ing['base_price']) ?></td>
                                                <td class="text-center text-muted"><?= number_format((int)$ing['available'], 0, ',', '.') ?></td>
                                                <td class="text-muted">—</td>
                                                <td><span class="badge bg-secondary-subtle text-muted">Excluído</span></td>
                                                <td class="text-end pe-3">
                                                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Restaurar ingresso"
                                                        onclick="restaurarIngresso(<?= (int)$ing['id'] ?>)">
                                                        <i class="las la-undo me-1"></i>Restaurar
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div><!-- /accordion -->

                <?php endif; ?>
            </div>
        </div>

    </div><!-- /tab-content -->
</div>


<!-- ════════════════════════════════════════════════════════════════════════
     MODAL: LOTE
════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLoteTitulo">Novo Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formLote" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="lote_id" id="campo_lote_id">
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-12">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nome" id="campo_lote_nome"
                                placeholder="Ex.: 1º Lote, Lote VIP" required maxlength="160">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="campo_lote_status">
                                <option value="ativo">Ativo</option>
                                <option value="em_breve">Em breve</option>
                                <option value="esgotado">Esgotado</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Data de início</label>
                            <input type="datetime-local" class="form-control" name="start_at" id="campo_lote_start_at">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Data de fim</label>
                            <input type="datetime-local" class="form-control" name="end_at" id="campo_lote_end_at">
                        </div>

                        <div class="col-md-12 d-flex align-items-center pt-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="visible"
                                    id="campo_lote_visible" value="1" checked>
                                <label class="form-check-label" for="campo_lote_visible">Visível no site</label>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnSalvarLote">Salvar Lote</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════════════════
     MODAL: INGRESSO
════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalIngresso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalIngressoTitulo">Novo Ingresso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formIngresso" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="ingresso_id" id="campo_ingresso_id">
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-5">
                            <label class="form-label">Lote <span class="text-danger">*</span></label>
                            <select class="form-select" name="batch_id" id="campo_ingresso_batch" required>
                                <option value="">Selecione um lote...</option>
                                <?php foreach ($lotesParaSelect as $l): ?>
                                <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nome" id="campo_ingresso_nome"
                                placeholder="Ex.: Pista, VIP, Camarote" required maxlength="160">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Preço base (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control" name="base_price" id="campo_ingresso_preco"
                                    placeholder="0,00" required inputmode="decimal">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Taxa (R$)</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control" name="fee" id="campo_ingresso_taxa"
                                    placeholder="0,00" inputmode="decimal">
                            </div>
                        </div>

                        <!-- Unidades disponíveis: editável só na criação -->
                        <div class="col-md-4" id="wrapper_ingresso_available">
                            <div id="available_create_mode">
                                <label class="form-label">Unidades disponíveis</label>
                                <input type="number" class="form-control" name="available" id="campo_ingresso_available"
                                    value="0" min="0">
                            </div>
                            <div id="available_edit_mode" class="d-none">
                                <label class="form-label">Unidades disponíveis</label>
                                <p class="form-control-plaintext fw-semibold mb-0" id="available_readonly_val">—</p>
                                <small class="text-muted">Gerenciado automaticamente pelas vendas.</small>
                            </div>
                        </div>

                        <div class="col-12 d-flex align-items-center gap-2">
                            <span class="text-muted fs-13 fw-semibold">Período de venda</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0"
                                id="btnHerdarDatas" onclick="herdarDatasDoLote()">
                                <i class="las la-link me-1"></i>Herdar do lote
                            </button>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Início específico</label>
                            <input type="datetime-local" class="form-control" name="start_at" id="campo_ingresso_start_at">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fim específico</label>
                            <input type="datetime-local" class="form-control" name="end_at" id="campo_ingresso_end_at">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Se deixar vazio, o ingresso herda o período do lote. O status é recalculado automaticamente pelas datas e disponibilidade.</small>
                        </div>

                        <div class="col-md-9">
                            <label class="form-label">Destaque</label>
                            <input type="text" class="form-control" name="highlight" id="campo_ingresso_highlight"
                                placeholder="Ex.: Mais vendido!" maxlength="190">
                        </div>

                        <div class="col-md-3 d-flex align-items-end pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_highlight"
                                    id="campo_ingresso_show_highlight" value="1">
                                <label class="form-check-label" for="campo_ingresso_show_highlight">
                                    Exibir destaque
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnSalvarIngresso">Salvar Ingresso</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════════════════════════════════ -->
<script src="<?= BASE_URL ?>public/assets/libs/simple-datatables/umd/simple-datatables.js"></script>
<!-- SortableJS — mesmo usado no módulo FAQ -->
<script src="<?= BASE_URL ?>public/assets/libs/sortablejs/Sortable.min.js"></script>
<style>
.drag-handle          { cursor: grab; color: var(--bs-secondary-color, #aaa); user-select: none; }
.drag-handle:active   { cursor: grabbing; }
tr.sortable-ghost     { opacity: .35; background: var(--bs-primary-bg-subtle, #cfe2ff); }
tr.sortable-chosen    { background: var(--bs-warning-bg-subtle, #fff3cd); }
</style>
<script>
const controllerUrl = '<?= BASE_URL ?>app/modules/ingressos/ingressos_controller.php';
const csrfToken     = '<?= htmlspecialchars($csrfToken) ?>';

// Mapa de datas dos lotes: { id: { start_at, end_at } }
const lotesDatas = <?= json_encode(
    array_column(
        array_map(fn($l) => [
            'id'       => (int)$l['id'],
            'start_at' => $l['start_at'] ?? '',
            'end_at'   => $l['end_at']   ?? '',
        ], $lotesParaSelect),
        null, 'id'
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

// ── State persistence across reloads ─────────────────────────────────────────
const _STATE_KEY = 'ing_page_state';

function savePageState() {
    const activeTabBtn = document.querySelector('#tabsIngressos .nav-link.active');
    const openAccordions = Array.from(
        document.querySelectorAll('#acordeaoLotes .accordion-collapse.show')
    ).map(el => el.id);
    const filtro = document.getElementById('filtroIngressos')?.value || '';

    sessionStorage.setItem(_STATE_KEY, JSON.stringify({
        tab:        activeTabBtn?.dataset.bsTarget || '#tab-lotes',
        accordions: openAccordions,
        filtro:     filtro,
    }));
}

function reloadWithState() {
    savePageState();
    window.location.reload();
}

function restorePageState() {
    const raw = sessionStorage.getItem(_STATE_KEY);
    if (!raw) return;
    sessionStorage.removeItem(_STATE_KEY);

    let state;
    try { state = JSON.parse(raw); } catch { return; }

    // Restaura aba ativa
    if (state.tab) {
        const tabBtn = document.querySelector(`#tabsIngressos [data-bs-target="${state.tab}"]`);
        if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    }

    // Restaura acordeões abertos
    (state.accordions || []).forEach(id => {
        const el = document.getElementById(id);
        if (el) bootstrap.Collapse.getOrCreateInstance(el).show();
    });

    // Restaura filtro e dispara o evento para atualizar a lista
    if (state.filtro) {
        const filtroInput = document.getElementById('filtroIngressos');
        if (filtroInput) {
            filtroInput.value = state.filtro;
            filtroInput.dispatchEvent(new Event('input'));
        }
    }
}

// ── Utilitário: parse JSON seguro ────────────────────────────────────────────
async function parseJsonResponse(response) {
    const text = await response.text();
    if (!text) throw new Error('Resposta vazia do servidor.');
    try { return JSON.parse(text); }
    catch (err) { throw new Error(text.substring(0, 300)); }
}

// ── Inicialização ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // DataTables
    const dtOpts = {
        searchable: true,
        fixedHeight: false,
        perPage: 15,
        perPageSelect: [10, 15, 25, 50],
        labels: {
            placeholder: 'Pesquisar...',
            perPage: 'por página',
            noRows: 'Nenhum registro encontrado',
            info: 'Mostrando {start} a {end} de {rows}',
            previous: '‹',
            next: '›',
        }
    };

    if (window.initTooltips) window.initTooltips();

    // ── Filtro de ingressos agrupados ─────────────────────────────────────────
    const filtroInput = document.getElementById('filtroIngressos');
    if (filtroInput) {
        filtroInput.addEventListener('input', function () {
            const termo = this.value.trim().toLowerCase();

            document.querySelectorAll('.ingresso-grupo').forEach(grupo => {
                const linhas = grupo.querySelectorAll('.ingresso-linha');
                let visiveis = 0;

                linhas.forEach(linha => {
                    const texto = linha.dataset.search || '';
                    const match = !termo || texto.includes(termo);
                    linha.style.display = match ? '' : 'none';
                    if (match) visiveis++;
                });

                // Mostra/oculta o grupo inteiro
                grupo.style.display = visiveis > 0 || !termo ? '' : 'none';

                // Abre automaticamente grupos que têm resultado ao filtrar
                if (termo && visiveis > 0) {
                    const collapse = grupo.querySelector('.accordion-collapse');
                    if (collapse && !collapse.classList.contains('show')) {
                        bootstrap.Collapse.getOrCreateInstance(collapse).show();
                    }
                }
            });
        });
    }

    // Alterna botão "Novo" conforme aba ativa
    document.getElementById('tab-ingressos-btn')?.addEventListener('shown.bs.tab', function () {
        document.querySelectorAll('.btn-tab-lotes').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.btn-tab-ingressos').forEach(el => el.classList.remove('d-none'));
    });
    document.getElementById('tab-lotes-btn')?.addEventListener('shown.bs.tab', function () {
        document.querySelectorAll('.btn-tab-ingressos').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.btn-tab-lotes').forEach(el => el.classList.remove('d-none'));
    });

    // Máscara de moeda ao sair do campo
    ['campo_ingresso_preco', 'campo_ingresso_taxa'].forEach(id => {
        document.getElementById(id)?.addEventListener('blur', function () {
            const v = parseMoney(this.value);
            this.value = isNaN(v) ? '0,00' : v.toFixed(2).replace('.', ',');
        });
    });

    // Submit handlers
    document.getElementById('formLote')?.addEventListener('submit', submitLoteForm);
    document.getElementById('formIngresso')?.addEventListener('submit', submitIngressoForm);

    // Drag-and-drop para ordenação de lotes e ingressos
    initSortableLotes();
    initSortableIngressos();

    // Restaura estado da página após um reload pós-save
    restorePageState();
});

// ── Helpers ──────────────────────────────────────────────────────────────────
function parseMoney(val) {
    if (!val) return 0;
    val = String(val).trim().replace(/[^\d,.\-]/g, '');
    if (/,\d{1,2}$/.test(val)) {
        val = val.replace(/\./g, '').replace(',', '.');
    }
    return parseFloat(val) || 0;
}

function brToDatetimeLocal(str) {
    if (!str) return '';
    // "2026-05-01 20:00:00" → "2026-05-01T20:00"
    return str.replace(' ', 'T').substring(0, 16);
}

function herdarDatasDoLote() {
    const batchId = parseInt(document.getElementById('campo_ingresso_batch').value, 10);
    if (!batchId) {
        adminAlert('Selecione um lote primeiro.', 'warning');
        return;
    }
    const datas = lotesDatas[batchId];
    if (!datas || (!datas.start_at && !datas.end_at)) {
        adminAlert('O lote selecionado não possui datas definidas.', 'warning');
        return;
    }
    document.getElementById('campo_ingresso_start_at').value = brToDatetimeLocal(datas.start_at);
    document.getElementById('campo_ingresso_end_at').value   = brToDatetimeLocal(datas.end_at);
}


// ════════════════════════════════════════════════════════════════════════════
// LOTES
// ════════════════════════════════════════════════════════════════════════════
function resetFormLote() {
    const form = document.getElementById('formLote');
    form.reset();
    document.getElementById('campo_lote_id').value     = '';
    document.getElementById('campo_lote_status').value = 'ativo';
    document.getElementById('campo_lote_visible').checked = true;
}

function openCreateLote() {
    resetFormLote();
    document.getElementById('modalLoteTitulo').textContent = 'Novo Lote';
    bootstrap.Modal.getOrCreateInstance('#modalLote').show();
}

async function openEditLote(id) {
    resetFormLote();
    document.getElementById('modalLoteTitulo').textContent = 'Editar Lote';

    try {
        const resp = await fetch(`${controllerUrl}?action=get_lote&id=${id}`, { credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (!data.success) { adminAlert(data.message || 'Não foi possível carregar o lote.', 'danger'); return; }

        const l = data.lote;
        document.getElementById('campo_lote_id').value        = l.id;
        document.getElementById('campo_lote_nome').value      = l.nome || '';
        document.getElementById('campo_lote_status').value    = l.status || 'ativo';
        document.getElementById('campo_lote_start_at').value  = brToDatetimeLocal(l.start_at);
        document.getElementById('campo_lote_end_at').value    = brToDatetimeLocal(l.end_at);
        document.getElementById('campo_lote_visible').checked = parseInt(l.visible) === 1;

        bootstrap.Modal.getOrCreateInstance('#modalLote').show();
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    }
}

async function submitLoteForm(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    fd.append('action', 'save_lote');
    fd.set('visible', document.getElementById('campo_lote_visible').checked ? '1' : '0');

    const btn = document.getElementById('btnSalvarLote');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (data.success) {
            adminToast(data.message || 'Lote salvo.', 'success');
            setTimeout(reloadWithState, 700);
        } else {
            adminAlert(data.message || 'Falha ao salvar o lote.', 'danger');
        }
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Salvar Lote';
    }
}

async function toggleVisivelLote(id, visivelAtual) {
    const novoVisivel = visivelAtual ? 0 : 1;
    const acao = novoVisivel ? 'Exibir' : 'Ocultar';

    const ok = await adminConfirm({
        title: `${acao} este lote no site?`,
        icon: 'question',
        confirmText: acao,
        confirmColor: '#4361ee',
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append('action', 'toggle_visivel_lote');
    fd.append('id', id);
    fd.append('visible', novoVisivel);
    fd.append('csrf_token', csrfToken);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (data.success) {
            adminToast(data.message || 'Visibilidade atualizada.', 'success');
            setTimeout(reloadWithState, 700);
        } else {
            adminAlert(data.message || 'Não foi possível atualizar.', 'danger');
        }
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    }
}

async function restaurarLote(id) {
    if (!id) { adminAlert('Lote inválido.', 'danger'); return; }

    const ok = await adminConfirm({
        title: 'Restaurar este lote?',
        text: 'O lote e todos os seus ingressos serão reativados.',
        confirmText: 'Sim, restaurar',
        confirmColor: '#0ab39c',
        icon: 'question',
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append('action', 'restore_lote');
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (data.success) {
            adminToast(data.message || 'Lote restaurado.', 'success');
            setTimeout(reloadWithState, 700);
        } else {
            adminAlert(data.message || 'Não foi possível restaurar o lote.', 'danger');
        }
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    }
}

async function confirmDeleteLote(id) {
    if (!id) { adminAlert('Lote inválido.', 'danger'); return; }

    const ok = await adminConfirm({
        title: 'Excluir este lote?',
        text: 'O lote e seus ingressos serão marcados como excluídos e ocultados do site. Você poderá restaurá-los depois.',
        confirmText: 'Sim, excluir',
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append('action', 'delete_lote');
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (data.success) {
            adminToast(data.message || 'Lote excluído.', 'success');
            setTimeout(reloadWithState, 700);
        } else {
            adminAlert(data.message || 'Não foi possível excluir o lote.', 'danger');
        }
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    }
}


// ════════════════════════════════════════════════════════════════════════════
// INGRESSOS
// ════════════════════════════════════════════════════════════════════════════
function resetFormIngresso() {
    const form = document.getElementById('formIngresso');
    form.reset();
    document.getElementById('campo_ingresso_id').value                    = '';
    document.getElementById('campo_ingresso_available').value             = '0';
    document.getElementById('campo_ingresso_preco').value                 = '0,00';
    document.getElementById('campo_ingresso_taxa').value                  = '0,00';
    document.getElementById('campo_ingresso_show_highlight').checked      = false;

    // Modo criação: campo de unidades disponíveis editável
    document.getElementById('available_create_mode').classList.remove('d-none');
    document.getElementById('available_edit_mode').classList.add('d-none');
}

function openCreateIngresso() {
    resetFormIngresso();
    document.getElementById('modalIngressoTitulo').textContent = 'Novo Ingresso';
    bootstrap.Modal.getOrCreateInstance('#modalIngresso').show();
}

// Abre o modal de novo ingresso já com o lote pré-selecionado (chamado pelo botão + de cada lote)
function openCreateIngressoNoLote(batchId, batchNome) {
    resetFormIngresso();
    document.getElementById('modalIngressoTitulo').textContent = 'Novo Ingresso — ' + batchNome;
    const select = document.getElementById('campo_ingresso_batch');
    if (select) select.value = batchId;
    bootstrap.Modal.getOrCreateInstance('#modalIngresso').show();
}

async function openEditIngresso(id) {
    resetFormIngresso();
    document.getElementById('modalIngressoTitulo').textContent = 'Editar Ingresso';

    try {
        const resp = await fetch(`${controllerUrl}?action=get_ingresso&id=${id}`, { credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (!data.success) { adminAlert(data.message || 'Não foi possível carregar o ingresso.', 'danger'); return; }

        const t = data.ingresso;
        document.getElementById('campo_ingresso_id').value                = t.id;
        document.getElementById('campo_ingresso_batch').value             = t.batch_id;
        document.getElementById('campo_ingresso_nome').value              = t.nome || '';
        document.getElementById('campo_ingresso_preco').value             = Number(t.base_price || 0).toFixed(2).replace('.', ',');
        document.getElementById('campo_ingresso_taxa').value              = Number(t.fee || 0).toFixed(2).replace('.', ',');
        document.getElementById('campo_ingresso_start_at').value          = brToDatetimeLocal(t.start_at);
        document.getElementById('campo_ingresso_end_at').value            = brToDatetimeLocal(t.end_at);
        document.getElementById('campo_ingresso_highlight').value         = t.highlight || '';
        document.getElementById('campo_ingresso_show_highlight').checked  = parseInt(t.show_highlight) === 1;

        // Modo edição: unidades disponíveis somente leitura
        document.getElementById('available_create_mode').classList.add('d-none');
        document.getElementById('available_edit_mode').classList.remove('d-none');
        document.getElementById('available_readonly_val').textContent = t.available ?? 0;

        bootstrap.Modal.getOrCreateInstance('#modalIngresso').show();
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    }
}

async function submitIngressoForm(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    fd.append('action', 'save_ingresso');
    fd.set('base_price', parseMoney(document.getElementById('campo_ingresso_preco').value));
    fd.set('fee', parseMoney(document.getElementById('campo_ingresso_taxa').value));
    fd.set('show_highlight', document.getElementById('campo_ingresso_show_highlight').checked ? '1' : '0');

    const btn = document.getElementById('btnSalvarIngresso');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (data.success) {
            adminToast(data.message || 'Ingresso salvo.', 'success');
            setTimeout(reloadWithState, 700);
        } else {
            adminAlert(data.message || 'Falha ao salvar o ingresso.', 'danger');
        }
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Salvar Ingresso';
    }
}

async function restaurarIngresso(id) {
    if (!id) { adminAlert('Ingresso inválido.', 'danger'); return; }

    const ok = await adminConfirm({
        title: 'Restaurar este ingresso?',
        text: 'O ingresso será reativado dentro do seu lote.',
        confirmText: 'Sim, restaurar',
        confirmColor: '#0ab39c',
        icon: 'question',
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append('action', 'restore_ingresso');
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (data.success) {
            adminToast(data.message || 'Ingresso restaurado.', 'success');
            setTimeout(reloadWithState, 700);
        } else {
            adminAlert(data.message || 'Não foi possível restaurar o ingresso.', 'danger');
        }
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    }
}

async function confirmDeleteIngresso(id) {
    if (!id) { adminAlert('Ingresso inválido.', 'danger'); return; }

    const ok = await adminConfirm({
        title: 'Excluir este ingresso?',
        text: 'O ingresso será marcado como excluído e ocultado do site. Vendas existentes não são afetadas e você poderá restaurá-lo depois.',
        confirmText: 'Sim, excluir',
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append('action', 'delete_ingresso');
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (data.success) {
            adminToast(data.message || 'Ingresso excluído.', 'success');
            setTimeout(reloadWithState, 700);
        } else {
            adminAlert(data.message || 'Não foi possível excluir o ingresso.', 'danger');
        }
    } catch (err) {
        adminAlert(err.message || 'Erro inesperado.', 'danger');
    }
}


// ════════════════════════════════════════════════════════════════════════════
// DRAG-AND-DROP — SortableJS (mesmo padrão do módulo FAQ)
// ════════════════════════════════════════════════════════════════════════════

// ── Lotes ────────────────────────────────────────────────────────────────────
function initSortableLotes() {
    const tbody = document.getElementById('lotes-sortable-body');
    if (!tbody) return;

    new Sortable(tbody, {
        handle:      '.drag-handle',
        animation:   150,
        ghostClass:  'sortable-ghost',
        chosenClass: 'sortable-chosen',
        // Linhas de lotes excluídos não são arrastáveis
        filter: '[data-lote-excluido-id]',
        onEnd: function () {
            const rows = [...tbody.querySelectorAll('tr[data-lote-id]')];
            salvarOrdemLotes(rows.map((r, i) => ({ id: parseInt(r.dataset.loteId, 10), ordem: i })));
        }
    });
}

async function salvarOrdemLotes(items) {
    const fd = new FormData();
    fd.append('action',     'update_ordem_lotes');
    fd.append('csrf_token', csrfToken);
    fd.append('items',      JSON.stringify(items));
    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (!data.success) adminAlert(data.message || 'Erro ao salvar ordem dos lotes.', 'danger');
    } catch (err) {
        adminAlert(err.message || 'Erro ao salvar ordem.', 'danger');
    }
}

// ── Ingressos ─────────────────────────────────────────────────────────────────
function initSortableIngressos() {
    document.querySelectorAll('.ingresso-sortable-body').forEach(tbody => {
        new Sortable(tbody, {
            handle:      '.drag-handle',
            animation:   150,
            ghostClass:  'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: function () {
                const rows = [...tbody.querySelectorAll('tr[data-ingresso-id]')];
                salvarOrdemIngressos(rows.map((r, i) => ({ id: parseInt(r.dataset.ingressoId, 10), ordem: i })));
            }
        });
    });
}

async function salvarOrdemIngressos(items) {
    const fd = new FormData();
    fd.append('action',     'update_ordem_ingressos');
    fd.append('csrf_token', csrfToken);
    fd.append('items',      JSON.stringify(items));
    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await parseJsonResponse(resp);
        if (!data.success) adminAlert(data.message || 'Erro ao salvar ordem.', 'danger');
    } catch (err) {
        adminAlert(err.message || 'Erro ao salvar ordem.', 'danger');
    }
}
</script>
