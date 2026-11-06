# Checkpoint — testes de planilha portáveis (2026-11-04)

- `tests/sheets_persist_test.php` e `tests/sheets_tabs_test.php`: o caminho dos handlers é resolvido a partir do checkout, sem depender de `/app`.
- Validação dirigida em PHP 8.3 e MariaDB local descartável: 11 e 19 verificações, respectivamente; `git diff --check` sem erros.
- Risco baixo: somente o caminho do harness foi alterado. Falta suíte final, comparação VPS e PR.
- Confiança: 95% para esta mudança no harness.
