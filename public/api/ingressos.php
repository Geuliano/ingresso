<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../app/bootstrap.php';
require_once dirname(__DIR__, 1) . '/../app/ingressos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Metodo nao permitido'], 405);
}

$pdo = db();
ensure_ingresso_tables($pdo);

$batches = fetch_ingresso_batches($pdo, true);

json_response([
    'success' => true,
    'batches' => $batches,
]);
