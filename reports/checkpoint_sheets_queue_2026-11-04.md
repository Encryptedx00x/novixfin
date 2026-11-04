# Checkpoint — aba vazia e fila de salvamento (2026-11-04)

- `public/scripts/sheet.js`: normaliza o mapa de células devolvido como array vazio; serializa gravações da mesma página; mantém pendências e alerta ao sair; tenta salvar ao perder visibilidade e ao recuperar rede.
- Causa reproduzida: PHP devolvia `{}` de células como `[]`; propriedades A1 na lista eram descartadas por JSON.stringify, embora save.php respondesse sucesso.
- `tests/ui/`: Chrome real com usuário e MariaDB locais descartáveis. Recarregamento, edição rápida com requisição retida, falha de rede e HTTP 500 foram verificados; teste HTTP anterior passou 16 verificações.
- Risco: a fila protege apenas uma página; páginas simultâneas ainda podem sobrescrever mudanças de outra. Falta proteção concorrente no servidor, suíte completa e PR. Navegadores podem não avisar em encerramento forçado.
- Confiança: 80% na sessão testada, ainda não pronto para homologação.
