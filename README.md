# Ingresso — Pulse Festival

Plataforma de venda de ingressos para o Pulse Festival. Backend em PHP 8 (sem framework), MySQL/MariaDB, JS vanilla no front, integração com n8n para envio de códigos de verificação.

## Stack

- PHP 8.1+ (PDO, cURL, mbstring, iconv)
- MySQL/MariaDB
- Apache com `mod_rewrite`
- JS vanilla (sem build), CSS único
- (opcional) Composer + PHPUnit para testes

## Estrutura

```
.
├── app/                # PHP "legado" (require_once-based)
│   ├── bootstrap.php   # carrega .env, autoload, helpers
│   ├── env_loader.php  # leitor de .env sem dependencia
│   ├── helpers.php     # url(), asset(), e(), send_security_headers()
│   ├── db.php          # singleton PDO
│   ├── auth.php        # sessao, CSRF, codigos, remember-me
│   ├── ingressos.php   # acesso a lotes/ingressos
│   ├── n8n.php         # webhook de envio de codigos
│   └── views/          # layouts e paginas
├── src/                # Novo codigo namespaced (PSR-4 App\)
│   ├── Auth/           # AuthService, VerificationService, RememberTokenService
│   ├── Http/           # JsonResponse
│   ├── Ingresso/       # BatchStatus
│   └── Support/        # Env, Validator
├── public/             # webroot (assets, paginas, /api)
├── admin/              # painel administrativo (separado)
├── database/
│   ├── migrate.php     # CLI: php database/migrate.php
│   └── migrations/     # *.sql aplicadas em ordem
├── tests/              # PHPUnit (PSR-4 Tests\)
│   ├── bootstrap.php   # autoloader manual de testes
│   └── Unit/
├── .env.example        # template - copie para .env
├── .gitignore
├── composer.json
└── phpunit.xml.dist
```

## Setup

1. **Clone e configure ambiente**

   ```bash
   cp .env.example .env
   # Gere uma APP_KEY forte (32+ chars):
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   # Cole a saida em APP_KEY=... no .env
   ```

   Edite `.env` com as credenciais do seu banco, URL base, etc.

2. **Aponte o Apache para o diretorio**

   No `httpd.conf` ou `apache2.conf` garanta que o `mod_rewrite` esta ativo e que o `AllowOverride All` esta habilitado para o diretorio do projeto. Em XAMPP basta colocar o projeto em `htdocs/`.

3. **Crie o banco e rode as migrations**

   ```sql
   CREATE DATABASE ingresso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   ```bash
   php database/migrate.php
   ```

   Para apenas listar o que seria aplicado:

   ```bash
   php database/migrate.php --status
   ```

4. **(Opcional) Composer + testes**

   ```bash
   composer install
   composer test
   ```

   Sem o Composer voce ainda pode rodar os testes via PHPUnit phar:

   ```bash
   wget https://phar.phpunit.de/phpunit-10.phar -O phpunit
   chmod +x phpunit
   ./phpunit --bootstrap tests/bootstrap.php tests/Unit
   ```

## Variaveis de ambiente

A precedencia e: `$_SERVER` (incluindo Apache `SetEnv`) > `$_ENV` > `.env` > default.

Variavel | Default | Descricao
--- | --- | ---
`APP_KEY` | (obrigatoria) | Segredo HMAC dos codigos de verificacao. 32+ chars hex.
`APP_ENV` | `production` | `local`, `staging` ou `production`. Em `local` o DDL runtime fica ativo.
`APP_DEBUG` | `false` | Liga erros visiveis.
`APP_TIMEZONE` | `America/Porto_Velho` | Timezone usado nos calculos de status.
`DB_*` | varia | Credenciais MySQL.
`VERIFICATION_*` | ver `.env.example` | Tamanho do codigo, expiracao, cooldown, tentativas.
`DEBUG_EXPOSE_CODES` | `false` | **Apenas dev.** Expoe codigos via API.
`N8N_*` | varia | Webhook de envio de codigos (email/WhatsApp).

## Seguranca

Algumas decisoes ja aplicadas (ver tambem `ANALISE_MELHORIAS.md`):

- **Segredos fora do repositorio.** `.env` esta no `.gitignore`. O `.htaccess` nao contem mais valores reais.
- **Cabecalhos de hardening.** `app/helpers.php::send_security_headers()` envia CSP, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`. Chamado pelo layout base e por endpoints JSON.
- **XSS no front.** Dados vindos do servidor sao injetados via `textContent` / `document.createElement` (helpers em `public/assets/js/common.js`). NUNCA use `innerHTML` com strings interpoladas de dados de usuario/banco.
- **CSRF.** Todos os endpoints `POST` em `/public/api/` validam `csrf_token` via `validate_csrf_or_fail()`.
- **Sessoes.** Cookies `HttpOnly`, `Secure` quando HTTPS, `SameSite=Lax` para sessao e `Strict` para remember-token. `session_regenerate_id(true)` apos login.
- **Remember-me.** Selector/validator com rotacao a cada uso (modelo Paragon Initiative).
- **Codigos de verificacao.** Armazenados como HMAC-SHA256 da `APP_KEY`, com tentativas decrementadas, expiracao, cooldown e rate-limit por IP.
- **Account takeover.** `register.php` nao sobrescreve dados de contas existentes nao verificadas com identidade diferente (ver commit que corrigiu o bug).

## Adicionando uma nova migration

```bash
# database/migrations/0003_orders.sql
CREATE TABLE IF NOT EXISTS orders ( ... );
```

Depois rode:

```bash
php database/migrate.php
```

O migrator tolera `CREATE INDEX` / `ALTER TABLE ADD COLUMN` ja existentes (codigos MySQL 1060/1061/1050), entao migrations podem ser re-rodadas com seguranca contra bases pre-existentes.

## Convencoes de codigo

- Codigo novo deve ir para `src/` com namespace `App\…` (PSR-4).
- Funcoes em `app/*.php` sao mantidas por retrocompat e devem evitar logica nova — prefira metodos em classes.
- Strings de erro: usar acentuacao UTF-8 normal (`não`, `código`, `inválido`).
- Toda saida HTML de dados dinamicos: passa por `e()` (PHP) ou `escapeHtml()` / `el()` (JS).

## TODO conhecido

- Migrar do `mail()` puro para PHPMailer/Symfony Mailer com SMTP.
- Implementar `orders` + `order_items` para mover o checkout fora do `localStorage`.
- Self-host das fontes Google + remover `unsafe-inline` da CSP.
- Refatorar `app/auth.php` para que as funcoes globais virem wrappers finos das classes em `src/Auth/`.
- Internacionalizacao: extrair strings de UI para um helper `__('chave')`.
