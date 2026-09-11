# Ingresso — Pulse Festival

Plataforma de venda de ingressos do Pulse Festival. PHP 8 sem framework, MySQL, JS vanilla no front. Os códigos de verificação saem por um fluxo no n8n (e-mail/WhatsApp).

## Requisitos

- PHP 8.1+ com `pdo_mysql`, `curl`, `mbstring` e `iconv`
- MySQL 5.7+ ou MariaDB 10.4+
- Apache com `mod_rewrite` e `AllowOverride All` no diretório do projeto
- Composer só para os testes — a aplicação em si não tem dependência externa

## Subindo local

```bash
cp .env.example .env
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Cola a saída em `APP_KEY`. Sem ela a verificação não funciona: os códigos são gravados como HMAC dessa chave, então trocar a `APP_KEY` depois invalida todos os códigos pendentes.

Banco:

```sql
CREATE DATABASE ingresso CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php database/migrate.php            # aplica
php database/migrate.php --status   # só lista o que falta
```

O webroot é `public/`. Em XAMPP dá pra jogar o projeto direto em `htdocs/` e acessar pela subpasta — é o que a maioria faz aqui. Em VPS, aponte o `DocumentRoot` para `public/` e confira a `APP_URL` no `.env`, senão os helpers `url()`/`asset()` geram link quebrado.

Testes:

```bash
composer install
composer test
```

Sem Composer, o phar resolve:

```bash
wget https://phar.phpunit.de/phpunit-10.phar -O phpunit && chmod +x phpunit
./phpunit --bootstrap tests/bootstrap.php tests/Unit
```

## Variáveis de ambiente

A precedência é `$_SERVER` (inclui `SetEnv` do Apache) → `$_ENV` → `.env` → default. Isso importa principalmente em ambiente compartilhado, onde o Apache sobrescreve o `.env` sem avisar.

| Variável | Default | Observação |
| --- | --- | --- |
| `APP_KEY` | — | Obrigatória. 32+ chars hex. |
| `APP_ENV` | `production` | Em `local` o DDL em runtime fica ativo. |
| `APP_DEBUG` | `false` | |
| `APP_TIMEZONE` | `America/Porto_Velho` | Usado no cálculo de status dos lotes. |
| `DB_*` | — | Credenciais MySQL. |
| `VERIFICATION_*` | ver `.env.example` | Tamanho do código, expiração, cooldown, tentativas. |
| `DEBUG_EXPOSE_CODES` | `false` | Devolve o código na resposta da API. **Nunca** em produção. |
| `N8N_*` | — | Webhook de envio. Se estiver vazio o envio falha silencioso. |

## Como o código está organizado

Tem duas pastas fazendo a mesma coisa, e isso é histórico: `app/` é o código antigo, baseado em `require_once` e função global; `src/` é o novo, PSR-4 sob `App\`. A migração está no meio do caminho — código novo vai em `src/`, e as funções de `app/` deveriam ser wrappers finos das classes, mas boa parte ainda tem lógica dentro.

O que vale saber antes de abrir qualquer coisa:

- `app/bootstrap.php` — carrega `.env`, autoload e helpers. Entra em tudo.
- `app/auth.php` — sessão, CSRF, remember-me e códigos. É o arquivo mais bagunçado do projeto.
- `app/helpers.php` — `url()`, `asset()`, `e()`, `send_security_headers()`.
- `src/Auth/` — `AuthService`, `VerificationService`, `RememberTokenService`. É pra onde `app/auth.php` está indo.
- `public/api/` — endpoints JSON.
- `admin/` — painel administrativo, com bootstrap próprio e quase nenhuma documentação.
- `database/migrations/` — `.sql` aplicados em ordem alfabética.

## Coisas que é fácil quebrar sem querer

- **`innerHTML` com dado do banco.** Use `textContent` ou os helpers `el()`/`escapeHtml()` de `public/assets/js/common.js`. Já teve XSS aqui, não repete.
- **Endpoint POST novo sem CSRF.** Todo POST em `public/api/` chama `validate_csrf_or_fail()`. Não tem verificação automática disso, é na disciplina mesmo.
- **HTML dinâmico sem `e()`.** Mesma história do lado do PHP.
- **Mexer no remember-me.** É selector/validator com rotação a cada uso (modelo da Paragon Initiative). Roda `tests/Unit` antes de commitar.
- **Cadastro de conta já existente.** O `register.php` não pode sobrescrever dados de conta não verificada com identidade diferente — isso foi um bug de account takeover, tem commit corrigindo. Se for refatorar o fluxo, preserve o comportamento.

Os headers de hardening (CSP, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`) vêm de `send_security_headers()`, chamado no layout base e nos endpoints JSON. A CSP ainda carrega `unsafe-inline` por causa das fontes do Google.

`.env` está no `.gitignore`. O `.htaccess` já teve credencial hardcoded em algum momento — se aparecer alguma em branch antiga, é resquício e precisa sair.

Contexto mais completo das decisões está em `ANALISE_MELHORIAS.md`.

## Migrations

Cria o arquivo com prefixo numérico e roda:

```
database/migrations/0003_orders.sql
```

```bash
php database/migrate.php
```

O migrator ignora os erros 1050, 1060 e 1061 (tabela / coluna / índice já existe), então dá pra rodar em cima de base antiga sem quebrar. Não existe rollback: se precisar desfazer alguma coisa, escreve outra migration.

## Convenções

- Código novo em `src/`, namespace `App\`, PSR-4.
- Acentuação normal em UTF-8 nas mensagens de erro (`não`, `código`, `inválido`) — tem string antiga sem acento espalhada por aí, corrige quando passar.
- CSS é um arquivo só e o JS não tem build. Não foi uma decisão, é como começou.

## Pendências

- `mail()` puro — trocar por PHPMailer/Symfony Mailer com SMTP. A entregabilidade está ruim.
- Checkout ainda guarda o carrinho no `localStorage`. Falta `orders` + `order_items`.
- Self-host das fontes do Google pra conseguir tirar o `unsafe-inline` da CSP.
- Terminar a migração `app/` → `src/`.
- i18n: as strings de UI estão soltas dentro das views, precisa de um `__('chave')`.
