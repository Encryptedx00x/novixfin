# Checkpoint — conflitos entre páginas (2026-11-04)

- `actions/sheets/{_bootstrap,load,save,snapshot,restore}.php`: versão SHA-256 do conteúdo, verificação sob bloqueio de linha e HTTP 409 para versão antiga. Sem migração. Chamadas legadas sem versão continuam compatíveis.
- `public/scripts/sheet.js`: associa a versão corrente no momento da operação enfileirada, bloqueia novas escritas após conflito e mantém edição local; interface desativada até carregar.
- Validação: regressão HTTP 22 verificações, histórico 13, lint dos cinco handlers, Chrome real com edição, fila, rede, HTTP 500 e conflito entre duas páginas.
- Riscos: clientes antigos sem versão podem sobrescrever dados; resposta perdida após gravação pode exigir cópia manual por conflito. Aviso de saída não é garantido em encerramento forçado.
- Pendente: suíte completa, comparação final VPS, documentação, push e PR. Nenhum deploy.
- Confiança: 85%; homologação depende da validação final.
