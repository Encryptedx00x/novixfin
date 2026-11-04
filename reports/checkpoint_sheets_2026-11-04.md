# Checkpoint — barra de fórmulas (2026-11-04)

- Tarefa: preservar edições da barra ao mudar o foco ou salvar.
- Arquivo: `public/scripts/sheet.js`.
- Diagnóstico reproduzido com DOM isolado: digitar e trocar o foco não gerava requisição; após a alteração gera uma requisição de autosave.
- A seleção anterior é confirmada antes de trocar de célula; Enter e Escape mantêm suas funções. Salvar marco e sair confirmam o editor ativo.
- Validação: `node --check`, `git diff --check` e reprodução direcionada em DOM simulado; não equivale a validação de persistência HTTP/banco.
- Riscos/pendências: requisições concorrentes, falhas de rede, teste de regressão completo em banco isolado, comparação de schema, suíte final e PR. Nenhum deploy.
- Confiança nesta etapa: 75%; validação integrada ainda pendente.
