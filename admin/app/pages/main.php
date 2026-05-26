<?php
require_once dirname(__DIR__) . '/core/config.php';

require_once APP_PATH . '/core/auth.php';
require_once APP_PATH . '/core/permissions.php';
require_once APP_PATH . '/core/router.php';
require_once APP_PATH . '/modules/notifications/notifications_helper.php';
require_once APP_PATH . '/modules/personalizar/personalizar_helper.php';

requireLogin();

// ── Cabeçalhos de segurança HTTP ──────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// CSP permissiva para o painel (permite CDNs e inline necessários pelo tema)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self';");
// ─────────────────────────────────────────────────────────────────────────

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$moduloParam = $_GET['mod'] ?? null;
$moduloSolicitado = resolveModuleId($moduloParam);
if (!$moduloSolicitado) {
    $moduloSolicitado = getModuleIdBySlug('dashboard');
}
$acaoSolicitada = $_GET['acao'] ?? 'listar';
$acaoSolicitada = preg_replace('/[^a-z0-9_\-]/i', '', (string)$acaoSolicitada);
if ($acaoSolicitada === '') {
    $acaoSolicitada = 'listar';
}

function buildModuleLink(string $slug, array $params = []): string
{
    $moduleId = getModuleIdBySlug($slug);
    $query = array_merge(['mod' => $moduleId ?? $slug], $params);
    return BASE_URL . '?' . http_build_query($query);
}

$brandingConfig = getBrandingConfig($pdo);
$pageTitleBrand = $brandingConfig['titulo_site'] ?? 'Painel';
$faviconPath = $brandingConfig['favicon'] ?? 'public/assets/images/favicon.ico';
$logoLightPath = $brandingConfig['logo_light'] ?? 'public/assets/images/logo-light.png';
$logoDarkPath = $brandingConfig['logo_dark'] ?? 'public/assets/images/logo-dark.png';
$logoSmallPath = $brandingConfig['logo_small'] ?? 'public/assets/images/logo-sm.png';

$usuarioId = $_SESSION['usuario_id'] ?? 0;

$usuarioAtual = [
    'nome' => $_SESSION['usuario_nome'] ?? 'Usu�rio',
    'email' => '',
    'avatar' => null,
    'nivel' => '',
    'nivel_id' => 0,
];

try {
    $stmtInfo = $pdo->prepare("
        SELECT 
            u.nome,
            u.email,
            u.avatar,
            u.tema,
            COALESCE(n.nome, '') AS nivel_nome,
            COALESCE(un.id_nivel, u.nivel, 0) AS nivel_id
        FROM usuarios u
        LEFT JOIN usuario_nivel un ON un.id_usuario = u.id
        LEFT JOIN niveis_acesso n ON n.id = un.id_nivel
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmtInfo->execute([$usuarioId]);
    $rowInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    if ($rowInfo) {
        $usuarioAtual['nome'] = $rowInfo['nome'] ?: $usuarioAtual['nome'];
        $usuarioAtual['email'] = $rowInfo['email'] ?? '';
        $usuarioAtual['avatar'] = $rowInfo['avatar'] ?? null;
        $usuarioAtual['nivel'] = $rowInfo['nivel_nome'] ?? '';
        $usuarioAtual['nivel_id'] = (int) ($rowInfo['nivel_id'] ?? 0);
        // Sincroniza o tema do banco na sessão
        if (!empty($rowInfo['tema'])) {
            $_SESSION['tema'] = $rowInfo['tema'];
        }
    }
} catch (Throwable $e) {
    // ignora, usa dados da sess�o
}

$userTheme = $_SESSION['tema'] ?? 'light';
if (!in_array($userTheme, ['light', 'dark'], true)) {
    $userTheme = 'light';
}

$avatarHeader = $usuarioAtual['avatar']
    ? BASE_URL . 'public/uploads/avatars/' . $usuarioAtual['avatar']
    : BASE_URL . 'public/assets/images/users/default.png';

$notificacoesAtivas = [];
ensureNotificationModulesTable($pdo);
$moduloPermitidoParaAvisos = $usuarioId
    ? getAuthorizedModuleSlugForUser($usuarioId, $moduloSolicitado, $acaoSolicitada)
    : null;
try {
    $stmtNotif = $pdo->prepare("
        SELECT 
            n.id,
            n.titulo,
            n.mensagem,
            n.tipo_alerta,
            n.pode_fechar
        FROM notificacoes n
        LEFT JOIN notificacoes_leituras l
            ON l.id_notificacao = n.id
           AND l.id_usuario = ?
        WHERE n.ativo = 1
          AND (n.vigencia_inicio IS NULL OR n.vigencia_inicio <= NOW())
          AND (n.fixa = 1 OR n.vigencia_fim IS NULL OR n.vigencia_fim >= NOW())
          AND (
                n.destino_tipo = 'todos'
             OR (n.destino_tipo = 'nivel' AND n.destino_valor = ?)
             OR (n.destino_tipo = 'usuario' AND FIND_IN_SET(?, n.destino_valor))
          )
          AND (
                NOT EXISTS (
                    SELECT 1
                    FROM notificacoes_modulos nm
                    WHERE nm.id_notificacao = n.id
                )
             OR (
                    ? IS NOT NULL
                AND EXISTS (
                        SELECT 1
                        FROM notificacoes_modulos nm2
                        WHERE nm2.id_notificacao = n.id
                          AND nm2.slug_modulo = ?
                    )
                )
          )
          AND (n.pode_fechar = 0 OR l.id_notificacao IS NULL)
        ORDER BY n.vigencia_inicio IS NULL DESC, n.vigencia_inicio DESC, n.id DESC
    ");
    $stmtNotif->execute([
        $usuarioId,
        $usuarioAtual['nivel_id'],
        $usuarioId,
        $moduloPermitidoParaAvisos,
        $moduloPermitidoParaAvisos
    ]);
    $notificacoesAtivas = $stmtNotif->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $notificacoesAtivas = [];
}
$notificationAlertMeta = [
    'danger'    => ['icon' => 'las la-times-circle'],
    'warning'   => ['icon' => 'las la-exclamation-triangle'],
    'success'   => ['icon' => 'las la-check-circle'],
    'info'      => ['icon' => 'las la-info-circle'],
    'primary'   => ['icon' => 'las la-info-circle'],
    'secondary' => ['icon' => 'las la-info-circle'],
];
?>
<!DOCTYPE html>
<html lang="pt_BR" dir="ltr" data-startbar="<?= $userTheme ?>" data-bs-theme="<?= $userTheme ?>">

<head>


    <meta charset="utf-8" />
    <title><?= htmlspecialchars($pageTitleBrand) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="<?= BASE_URL . $faviconPath ?>">

    <!-- App css -->
    <link href="<?= BASE_URL ?>public/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>public/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>public/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>public/assets/css/theme-contrast.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>public/assets/css/admin-brand.css" rel="stylesheet" type="text/css" />
    <link href="<?= BASE_URL ?>public/assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Sora", sans-serif !important; }
        .startbar .brand .logo-lg { height: 35px !important; }
        .startbar .brand .logo-sm { height: 35px !important; }
    </style>





</head>


<!-- Top Bar Start -->

<body>
<script>
/* Aplica estado da sidebar antes do primeiro paint para evitar flash */
(function(){
    try {
        var s = localStorage.getItem('pulse_sidebar_size');
        if (s === 'collapsed' || s === 'default') {
            document.body.setAttribute('data-sidebar-size', s);
        }
    } catch(e) {}
})();
</script>
    <!-- Top Bar Start -->
    <div class="topbar d-print-none">
        <div class="container-fluid">
            <nav class="topbar-custom d-flex justify-content-between" id="topbar-custom">


                <ul class="topbar-item list-unstyled d-inline-flex align-items-center mb-0">
                    <li>
                        <button class="nav-link mobile-menu-btn nav-icon" id="togglemenu">
                            <i class="iconoir-menu"></i>
                        </button>
                    </li>
                </ul>
                <ul class="topbar-item list-unstyled d-inline-flex align-items-center mb-0">

                    <li class="dropdown topbar-item">
                        <a class="nav-link dropdown-toggle arrow-none nav-icon" data-bs-toggle="dropdown" href="#"
                            role="button" aria-haspopup="false" aria-expanded="false" data-bs-offset="0,19">
                            <img src="<?= htmlspecialchars($avatarHeader) ?>" alt="Avatar"
                                class="thumb-md rounded-circle">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end py-0">
                            <div class="d-flex align-items-center dropdown-item py-2 bg-secondary-subtle">
                                <div class="flex-shrink-0">
                                    <img src="<?= htmlspecialchars($avatarHeader) ?>" alt="Avatar"
                                        class="thumb-md rounded-circle">
                                </div>
                                <div class="flex-grow-1 ms-2 text-truncate align-self-center">
                                    <h6 class="my-0 fw-medium text-dark fs-13">
                                        <?= htmlspecialchars($usuarioAtual['nome']) ?></h6>
                                    <small class="text-muted mb-0">
                                        <?= htmlspecialchars($usuarioAtual['nivel'] ?: 'Usu�rio') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="dropdown-divider mt-0"></div>
                            <small class="text-muted px-2 pb-1 d-block">Conta</small>
                            <a class="dropdown-item" href="<?= htmlspecialchars(buildModuleLink('meus-dados')) ?>">
                                <i class="las la-user fs-18 me-1 align-text-bottom"></i> Meus dados
                            </a>
                            <a class="dropdown-item" href="<?= htmlspecialchars(buildModuleLink('meus-dados', ['acao' => 'listar'])) ?>#formSenha">
                                <i class="las la-lock fs-18 me-1 align-text-bottom"></i> Alterar senha
                            </a>
                            <div class="dropdown-divider mb-0"></div>
                            <a class="dropdown-item text-danger" href="<?= htmlspecialchars(buildModuleLink('logout')) ?>">
                                <i class="las la-power-off fs-18 me-1 align-text-bottom"></i> Sair
                            </a>
                        </div>
                    </li>
                </ul><!--end topbar-nav-->
            </nav>
            <!-- end navbar-->
        </div>
    </div>
    <!-- Top Bar End -->
    <!-- leftbar-tab-menu -->
    <div class="startbar d-print-none">
        <!--start brand-->
        <div class="brand">
            <a href="<?= BASE_URL ?>" class="logo" style="text-decoration:none;">
                <!-- Ícone compacto: tema controla display (none por padrão, inline-block quando collapsed) -->
                <img class="logo-sm" alt="Pulse"
                     src="data:image/svg+xml,%3Csvg viewBox='0 0 36 36' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cdefs%3E%3ClinearGradient id='psm' x1='0' y1='0' x2='0' y2='1'%3E%3Cstop offset='0%25' stop-color='%235affd6'/%3E%3Cstop offset='100%25' stop-color='%239d7aff'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect x='2' y='22' width='4' height='12' rx='2' fill='url(%23psm)' opacity='.55'/%3E%3Crect x='9' y='12' width='4' height='22' rx='2' fill='url(%23psm)' opacity='.75'/%3E%3Crect x='16' y='2' width='4' height='32' rx='2' fill='url(%23psm)'/%3E%3Crect x='23' y='16' width='4' height='18' rx='2' fill='url(%23psm)' opacity='.75'/%3E%3Crect x='30' y='20' width='4' height='14' rx='2' fill='url(%23psm)' opacity='.55'/%3E%3C/svg%3E"
                     style="height:32px;width:auto;">
                <!-- Wordmark: span.logo-lg recebe width:0/overflow:hidden quando collapsed -->
                <span class="logo-lg" style="overflow:hidden;display:inline-flex;align-items:center;">
                    <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" style="height:30px;min-width:36px;">
                        <defs>
                            <linearGradient id="pulse-lg" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#5affd6"/>
                                <stop offset="100%" stop-color="#9d7aff"/>
                            </linearGradient>
                        </defs>
                        <rect x="0"  y="22" width="4" height="12" rx="2" fill="url(#pulse-lg)" opacity="0.55"/>
                        <rect x="7"  y="12" width="4" height="22" rx="2" fill="url(#pulse-lg)" opacity="0.75"/>
                        <rect x="14" y="2"  width="4" height="32" rx="2" fill="url(#pulse-lg)"/>
                        <rect x="21" y="16" width="4" height="18" rx="2" fill="url(#pulse-lg)" opacity="0.75"/>
                        <rect x="28" y="20" width="4" height="14" rx="2" fill="url(#pulse-lg)" opacity="0.55"/>
                    </svg>
                </span>
            </a>
        </div>
        <!--end brand-->
        <!--start startbar-menu-->
        <div class="startbar-menu">
            <div class="startbar-collapse" id="startbarCollapse" data-simplebar>
                <div class="d-flex align-items-start flex-column w-100">
                    <!-- Navigation -->
                    <ul class="navbar-nav mb-auto w-100">
                        <li class="menu-label mt-2">
                            <span>NAVEGAÇÃO</span>
                        </li>

                        <?php require_once __DIR__ . '/../core/menu.php'; ?>
                    </ul><!--end navbar-nav--->
                </div>
            </div><!--end startbar-collapse-->
        </div><!--end startbar-menu-->
    </div><!--end startbar-->
    <div class="startbar-overlay d-print-none"></div>
    <!-- end leftbar-tab-menu-->


    <div class="page-wrapper">

        <!-- Page Content-->
        <div class="page-content">
            <div class="container-fluid">
                <?php if (!empty($notificacoesAtivas)): ?>
                    <div class="mb-3 mt-3">
                        <?php foreach ($notificacoesAtivas as $notificacao): ?>
                            <?php
                            $tipoAlerta = $notificacao['tipo_alerta'] ?? 'info';
                            $meta = $notificationAlertMeta[$tipoAlerta] ?? $notificationAlertMeta['info'];
                            ?>
                            <div class="alert alert-<?= htmlspecialchars($tipoAlerta) ?> <?= (int) $notificacao['pode_fechar'] === 1 ? 'alert-dismissible' : '' ?> fade show shadow-sm border-start border-2 border-<?= htmlspecialchars($tipoAlerta) ?> mb-2"
                                role="alert" data-notification-container="<?= (int) $notificacao['id'] ?>">
                                <div class="d-flex align-items-center gap-2 w-100">
                                    <i
                                        class="<?= htmlspecialchars($meta['icon']) ?> align-self-center fs-30 text-<?= htmlspecialchars($tipoAlerta) ?>"></i>
                                    <div class="flex-grow-1 ms-2 text-truncate">
                                        <h5 class="mb-1 fw-bold mt-0 text-<?= htmlspecialchars($tipoAlerta) ?>">
                                            <?= htmlspecialchars($notificacao['titulo']) ?></h5>
                                        <div class="mb-0"><?= $notificacao['mensagem'] ?></div>
                                        <?php if ((int) $notificacao['pode_fechar'] === 1): ?>
                                            <button type="button" class="btn-close mt-2" aria-label="Fechar" data-close-notification
                                                data-notification-id="<?= (int) $notificacao['id'] ?>"></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php
                echo runRouter();
                ?>
            </div>
            <!--end footer-->
        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <!-- vendor js -->

    <script src="<?= BASE_URL ?>public/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>public/assets/libs/simplebar/simplebar.min.js"></script>
    <script src="<?= BASE_URL ?>public/assets/libs/clipboard/clipboard.min.js"></script>
    <script src="<?= BASE_URL ?>public/assets/js/pages/clipboard.init.js"></script>
    <script>
    window.APP_THEME = {
        current:  '<?= $userTheme ?>',
        endpoint: '<?= BASE_URL ?>app/core/theme_preferences.php',
        csrf:     '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'
    };
</script>
<script src="<?= BASE_URL ?>public/assets/js/app.js"></script>
    <script src="<?= BASE_URL ?>public/assets/libs/sweetalert2/sweetalert2.all.min.js"></script>
    <script>
    /* ── Helpers globais de feedback (SweetAlert2) ───────────────────────── */
    (function () {
        function getSwalTheme() {
            const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            return {
                background: dark ? '#1f2937' : '#fff',
                color:      dark ? '#f3f4f6' : '#111827',
            };
        }

        /** Toast não-invasivo no canto superior direito */
        function adminToast(message, type) {
            type = type || 'success';
            const iconMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info', error: 'error' };
            const t = getSwalTheme();
            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                background: t.background,
                color: t.color,
                didOpen: function (el) {
                    el.addEventListener('mouseenter', Swal.stopTimer);
                    el.addEventListener('mouseleave', Swal.resumeTimer);
                }
            }).fire({ icon: iconMap[type] || 'info', title: message });
        }

        /** Alerta modal centralizado (erros persistentes) */
        function adminAlert(message, type) {
            type = type || 'danger';
            const iconMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info', error: 'error' };
            const t = getSwalTheme();
            Swal.fire({
                icon: iconMap[type] || 'error',
                text: message,
                confirmButtonColor: '#4361ee',
                background: t.background,
                color: t.color,
            });
        }

        /**
         * Diálogo de confirmação antes de ações destrutivas.
         * Retorna Promise<boolean>.
         */
        function adminConfirm(opts) {
            opts = opts || {};
            const t = getSwalTheme();
            return Swal.fire({
                title:              opts.title        || 'Confirmar?',
                text:               opts.text         || 'Esta ação não poderá ser desfeita.',
                icon:               opts.icon         || 'warning',
                showCancelButton:   true,
                confirmButtonColor: opts.confirmColor || '#d33',
                cancelButtonColor:  opts.cancelColor  || '#6c757d',
                confirmButtonText:  opts.confirmText  || 'Confirmar',
                cancelButtonText:   opts.cancelText   || 'Cancelar',
                background: t.background,
                color:      t.color,
            }).then(function (r) { return r.isConfirmed; });
        }

        window.getSwalTheme   = getSwalTheme;
        window.adminToast     = adminToast;
        window.adminAlert     = adminAlert;
        window.adminConfirm   = adminConfirm;
    })();
    </script>
    <script>
        (function () {
            const controllerUrl = '<?= BASE_URL ?>app/modules/notifications/notifications_controller.php';
            const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

            function parseJson(text) {
                if (!text) {
                    throw new Error('Resposta vazia do servidor.');
                }
                try {
                    return JSON.parse(text);
                } catch (err) {
                    throw new Error(text);
                }
            }

            document.addEventListener('click', function (event) {
                const btn = event.target.closest('[data-close-notification]');
                if (!btn) {
                    return;
                }
                const id = btn.getAttribute('data-notification-id');
                if (!id) {
                    return;
                }
                event.preventDefault();
                const container = document.querySelector(`[data-notification-container="${id}"]`);

                const fd = new FormData();
                fd.append('action', 'close_notification');
                fd.append('id', id);
                fd.append('csrf_token', csrfToken);

                fetch(controllerUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                    .then(resp => resp.text())
                    .then(parseJson)
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Não foi possível atualizar a notificação.');
                        }
                        if (container) {
                            container.classList.remove('show');
                            setTimeout(() => container.remove(), 200);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        if (container) {
                            container.classList.add('shake');
                            setTimeout(() => container.classList.remove('shake'), 600);
                        }
                    });
            });
        })();
    </script>

</body>




</html>
