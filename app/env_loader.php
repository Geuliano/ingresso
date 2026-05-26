<?php

declare(strict_types=1);

/**
 * Loader minimal de arquivo .env, sem dependencia externa.
 *
 * - Procura por .env na raiz do projeto (um nivel acima de /app).
 * - Variaveis ja presentes em $_SERVER/$_ENV (ex.: SetEnv do Apache) tem precedencia.
 * - Suporta linhas tipo CHAVE=valor, comentarios com #, valores entre aspas.
 */
function load_env_file(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = $path ?? dirname(__DIR__) . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }

        // Strip aspas envolventes
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, $len - 2);
            }
        }

        // Nao sobrescrever variaveis ja definidas pelo ambiente / SetEnv
        if (array_key_exists($key, $_SERVER) || array_key_exists($key, $_ENV) || getenv($key) !== false) {
            continue;
        }

        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

load_env_file();
