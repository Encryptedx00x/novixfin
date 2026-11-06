# Checkpoint — persistência HTTP (2026-11-04)

- Tarefa: regressão de persistência com servidor HTTP e MariaDB isolados.
- Arquivo: `tests/sheets_http_persist_test.php`. Cria e remove usuário sintético; só aceita HTTP local e banco com sufixo de teste/app.
- Testa criação, duas gravações sucessivas, consulta ao banco, recarga HTTP, abas, fórmula, Unicode, célula vazia, CSRF, conteúdo inválido e acesso anônimo.
- Validação: PHP 8.3 local e MariaDB 11.8.8 descartável, 16 verificações. Não usa dados reais ou VPS.
- Pendente: concorrência, falha de rede no cliente, regressão automatizada do fluxo de interface e suíte final. Sem deploy.
- Confiança: 85% no contrato HTTP testado; não comprova comportamento concorrente ou produção autenticada.
