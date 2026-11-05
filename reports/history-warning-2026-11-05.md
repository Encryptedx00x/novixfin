# Histórico: aviso de compilação — 2026-11-05

- Causa: importação de nome simples no namespace global. O PHP emite o aviso
  antes do bootstrap e pode retornar zero em `php -l` mesmo com esse diagnóstico.
- Correção técnica: `1a3dcd70340581e9bcbe3052a945f8b264c6b985`, remoção de uma linha
  em `public/history.php`. A busca equivalente nos demais arquivos encontrou
  somente importações válidas em arquivos com namespace em `src/`.
- Regressão: `tests/php_entrypoints_test.php` falhou antes (1/15) e passou depois
  (15/15); verifica status e saída do lint com todos os diagnósticos habilitados.
- `php tests/run.php` executado em cópia isolada, sem `.env` da aplicação:
  14 arquivos retornaram zero, 0 falhas; 8 testes dependentes de banco foram
  ignorados. PDO SQLite habilitado apenas via configuração temporária do runner.
- Lint local e PHP do container sem avisos nos arquivos alterados.

## Publicação

Publicado em 2026-11-05, conferência às 16:50:52 UTC. O hash anterior local/VPS
coincidia; nenhuma edição de produção foi sobrescrita.

Somente `/srv/sites/novixfin/public/history.php` foi trocado. Backup anterior:
`/srv/sites/backups/novixfin-20261105-165400/srv/sites/novixfin/public/history.php`.
SHA-256 publicado: `7abd1fd32e3d2a201aea6aac05effd5d1a0194ae719fe84e28d20a09ecc7ec2c`.
Preservados dono/grupo `ubuntu:ubuntu` e modo `644`. Sem banco, migrations,
ambiente, uploads, Caddy ou reinícios de containers.

Rollback: restaurar somente esse arquivo com `cp -a` do backup e reconferir
lint/HTTP; não restaurar banco para desfazer esta alteração de PHP.

HTTP público: `/history.php` redireciona o visitante ao login, que responde 200.
Prints antes/depois salvos em `C:/Users/Encryptedx/Desktop/correcoes-20261105/`,
prefixos `novix-antes-` e `novix-depois-`, nos tamanhos 1280x800, 1440x900 e 390x844.
Esses prints mostram a rota pública não autenticada; não são prova da tela de
histórico autenticada. A correção do aviso foi comprovada pelo lint antes/depois
e pelo novo teste, sem entrar em conta real ou alterar dados financeiros.

Risco residual baixo: nenhuma lógica de histórico/finanças alterada. Confiança
na correção do aviso: 99%. Testes de banco e visual autenticado não exercitados.
