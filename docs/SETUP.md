# Setup Guide — NovixFin v3

> Reescrito na Fase 1 do plano v3. A versão anterior listava credenciais de
> produção em texto puro — isso foi removido. As credenciais agora vivem
> apenas em arquivos `.env` (gitignored) ou em variáveis de ambiente do
> servidor.

## Requisitos

- PHP 8.1+ (extensões: pdo_mysql, mbstring; opcional: intl para formatação
  de moeda por locale).
- MySQL 8.0+ (ou MariaDB 10.6+).
- Sem Composer e sem build step: o projeto usa apenas PHP + PDO + sessões
  nativas. (composer/PHPUnit estão previstos para a Fase 10, mas são
  opc/dev-only.)

## Ambiente local

1. Clone o repositório.
2. Copie `.env.example` para `.env` na raiz do projeto e ajuste os valores
   locais (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD). Defina
   `APP_ENV=local` para ver erros detalhados durante o desenvolvimento.
3. Crie um banco MySQL local e importe `database/schema.sql` (a versão
   consolidada v3 vivirá em `migrations/` a partir da Fase 2; por enquanto,
   use a sequence de migrações em `database/` conforme a ordem documentada
   no relatório da Fase 2).
4. Opcional: rode `database/seed.sql` para inserir categorias padrão.
5. Aponte o document root do servidor para a pasta `public/` do projeto.
6. Acesse `http://localhost/.../public/` no navegador.

## Ambiente Hostinger (produção)

> A senha `@#$Novix200505` que estava hardcoded em `config/config.php` está
> em logs de git. **Ela deve ser rotacionada** no painel do Hostinger antes
> do deploy de produção. A nova senha nunca entra no repositório.

1. No painel Hostinger, crie/ajuste o aplicativo PHP com a **raiz pública**
   apontando para `public/`.
2. Crie (ou reutilize) o banco MySQL e **gere uma nova senha** para o
   usuário do banco (a antiga está comprometida).
3. Na raiz do projeto (acima de `public/`), crie o arquivo `.env` com:

   ```env
   APP_ENV=production
   APP_URL=https://SEU_DOMINIO
   APP_TIMEZONE=America/Sao_Paulo

   DB_HOST=srv811.hstgr.io
   DB_PORT=3306
   DB_DATABASE=u545165688_NovixFin
   DB_USERNAME=u545165688_NovixADM
   DB_PASSWORD=<NOVA_SENHA_ROTACIONADA>

   SESSION_NAME=novix_fin_session
   SESSION_COOKIE_SAMESITE=Lax
   ```

   Defina permissões `600` no `.env` (legível apenas pelo dono) e certifique-
   se de que ele **não** está dentro de `public/`.

4. Importe o schema e as migrações consolidadas via phpMyAdmin (ver
   `docs/migration-order.md` assim que a Fase 2 for concluída) — OU rode
   `php scripts/migrate.php` (a ser introduzido na Fase 2). **Faça um
   backup do banco de produção ANTES de aplicar qualquer migração.**
5. Permissões: 755 para pastas, 644 para arquivos PHP, 600 para `.env`.

## Variáveis de ambiente (referência)

Variáveis suportadas pelo `.env` (todas opcionais; defaults seguros):

| Variável | Default | Notas |
|---|---|---|
| `APP_ENV` | `production` | `local` liga `display_errors`, `production` garante que erros vão só para o log. |
| `APP_NAME` | `Novix Fin` | |
| `APP_URL` | `http://localhost` | |
| `APP_TIMEZONE` | `America/Sao_Paulo` | |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `novix_fin` | |
| `DB_USERNAME` | `root` | |
| `DB_PASSWORD` | *(vazio)* | |
| `DB_CHARSET` | `utf8mb4` | |
| `SESSION_NAME` | `novix_fin_session` | **NÃO alterar** em deployments existentes — invalida sessões. |
| `SESSION_COOKIE_LIFETIME` | `604800` (7 dias) | segundos |
| `SESSION_COOKIE_SAMESITE` | `Lax` | `Lax`/`Strict`/`None` |

## Rotacionamento de senha (obrigatório na Fase 1)

A senha vazada está comprometida. Passos:

1. No painel do Hostinger → MySQL Databases → altere a senha do usuário
   `u545165688_NovixADM` (ou crie um novo usuário e remova o antigo).
2. Atualize `.env` no servidor com a nova senha.
3. Reincida/reinicie o PHP-FPM se necessário para liberar caches de
   conexão (ou aguarde a próxima requisição instanciar o PDO).
4. Marque a conclusão em `state/progress.md`.

## Próximas Fases (roadmap)

- Fase 2: consolidation de schema + migration runner ordenado.
- Fase 3: transações DB + correção de bugs funcionais.
- … (ver `state/progress.md`)

## Cron Jobs (v3 — Fase 5)

A v3 introduziu um dispatcher de cron (`scripts/cron.php`, CLI-only) que
materializa transações recorrentes vencidas e efetiva transações com
`effective_date` no passado. Adicione no cPanel do Hostinger → Cron Jobs:

```sh
0 2 * * * /usr/bin/php /home/u545165688/<raiz_do_app>/scripts/cron.php all
```

Você também pode rodar manualmente (via SSH) para testar:

```sh
php scripts/cron.php recurring      # só gera recorrências vencidas
php scripts/cron.php auto-effective # só efetiva transações não-efetivadas
php scripts/cron.php all            # ambas
```

A tabela `recurring_generated` (migration
`20240101000003_add_recurring_generated_v3.sql`) garante idempotência — rodar
o cron duas vezes no mesmo dia não duplica transações.

## Throttle de Login / Cadastro (v3 — fix W2)

A v3 substituiu o throttle baseado em `$_SESSION` (facilmente burlado limpando
cookies) por um throttle server-side em tabela de banco — `login_attempts`
(migration `20240101000004_add_login_attempts_v3.sql`). Quem tenta 5 logins
falhos em 5 minutos (por `email + IP`) ou 10 cadastros em 10 minutos (por
`IP`) é bloqueado temporariamente, mesmo se limpar/suprimir cookies. As
funções em `includes/throttle.php` usam fail-open: se a tabela não existir
(migration ainda não aplicada), o login é permitido (não bloqueia todos os
usuários por defeito de deploy). Aplique a migration para habilitar o
limite de fato.