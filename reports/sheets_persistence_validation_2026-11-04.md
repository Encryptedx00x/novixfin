# Planilhas — validação e procedimento de homologação (2026-11-04)

## Diagnóstico e correção

`load.php` decodificava o objeto vazio de células como array PHP e o reemitia como `[]`. Ao editar uma aba carregada vazia, `sheet.js` adicionava propriedades como `A1` ao array; `JSON.stringify` descartava essas propriedades. A requisição recebia HTTP 200, mas gravava uma aba vazia. Também se perdia a edição da barra de fórmulas quando se mudava o foco sem Enter. O teste de navegador reproduziu o primeiro defeito antes da correção e confirmou a persistência após recarga depois dela.

A versão candidata normaliza células vazias, confirma a edição ao mudar foco, serializa gravações por página e usa SHA-256 do conteúdo com verificação transacional para rejeitar versões antigas com HTTP 409. Mostra pendência, protege saída quando o navegador permite, tenta concluir ao perder visibilidade e permite nova tentativa ao voltar a ficar online. Conflitos mantêm os dados na tela para cópia antes de recarregar. Os handlers legados que não enviam versão permanecem aceitos.

## Evidência

- MariaDB 11.8.8 local descartável, PHP 8.3, usuário sintético e servidor local: `php tests/run.php` — 14 arquivos passaram, 0 falhas, sem SKIP. A primeira tentativa falhou somente porque `pdo_sqlite` estava desativada localmente; a segunda usou uma cópia temporária do php.ini com a extensão habilitada.
- Chrome real: `node tests/ui/sheets.test.cjs` — barra de fórmulas e recarga; requisição retida e edições sucessivas; falha de rede; HTTP 500; duas páginas em conflito. Requer `npm ci --prefix tests/ui`, Chrome (`CHROME_PATH` quando necessário), `HTTP_BASE` local e as variáveis `DB_*` apontando para banco local descartável já migrado com sufixo `_test` ou `_app`. O fixture cria e remove usuário e conta sintéticos.
- `tests/sheets_http_persist_test.php`: 22 verificações de resposta, persistência, recarga, autenticação, CSRF e conflitos em save/snapshot/restore; histórico: 13 verificações. Nenhum banco de produção foi escrito.
- Produção: HEAD `a006bd3`; GitHub main `2a2f976`. Arquivos efetivos de planilha e testes examinados coincidem com o HEAD da VPS ao ignorar diferenças de final de linha; esses arquivos do HEAD também coincidem com a base `2a2f976`. Permanecem alterações não commitadas na VPS, preservadas.

## Procedimento proposto, sujeito a autorização de deploy

1. Revisar PR e conferir novamente o conteúdo real da VPS e as alterações não commitadas. Fazer backup verificável do banco e cópias exatas dos arquivos a substituir, preservando permissões; não usar reset, pull ou sincronização destrutiva.
2. Publicar de forma coordenada os cinco handlers e o `sheet.js` revisados, sobre o conteúdo efetivamente inspecionado, com plano de restauração desses arquivos. Não há migração. Manter a rota Caddy existente para `/actions/*`.
3. Com conta de QA autorizada, testar nova aba vazia, alteração, status de salvo, recarga, falha de rede e conflito entre duas páginas; validar histórico e outros dados existentes. Observar logs sanitizados e HTTP 409 esperado.
4. Rollback: restaurar as cópias exatas dos arquivos anteriores, preservando permissões; reiniciar/recarregar apenas o que for necessário para invalidar cache de código e pedir recarga das páginas abertas. Não restaurar banco por padrão: isso descartaria alterações posteriores. Conferir leitura da planilha e integridade com QA.

## Limitações

Clientes antigos sem token de revisão são aceitos para compatibilidade e podem sobrescrever gravações de clientes novos até serem recarregados; planejar a troca coordenada e renovação das páginas. Se o servidor gravar mas a resposta for perdida, uma nova tentativa pode resultar em conflito; copiar o conteúdo local antes de recarregar. Navegadores podem encerrar processos sem disparar eventos ou mostrar confirmação, especialmente em dispositivos móveis. A recuperação de conflito é manual; não há mesclagem automática. HTTP 200 e JSON válidos são necessários, mas quedas no momento exato da resposta permanecem ambíguas.
