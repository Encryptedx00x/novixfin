# PR #1 — preparação para produção (2026-11-05)

Este relatório substitui as limitações e o procedimento do relatório de 04/11: revisão agora é obrigatória, inclusive para clientes antigos.

## Revisão e validação

- Escritas `save`, `snapshot`, `restore`: sem `_revision`, HTTP 428 e orientação para copiar e atualizar; revisão malformada, 422; revisão válida porém antiga, 409. A verificação ocorre após autenticação/CSRF e antes de criar ou alterar uma planilha. O frontend envia a revisão corrente na fila de cada escrita.
- Se a resposta se perde, o cliente informa que não confirmou a gravação e oferece seleção/cópia manual em JSON de todas as abas. Não recomenda recarregar antes de preservar os dados. Outra página não é sobrescrita por uma repetição desatualizada.
- MariaDB 11.8.8 descartável, PHP 8.3: `php tests/run.php` — 14 arquivos, zero falhas e nenhum SKIP. Teste HTTP: 33 verificações. Chrome: A1 inicialmente vazia, edição sem Enter, gravações sucessivas, múltiplas páginas, falhas de rede e HTTP, resposta perdida depois de gravar, cópia e proteção de conflito. Foram observados diálogos reais de atualização, navegação e fechamento com pendência e envio antecipado ao ocultar a página. Snapshot/restore e múltiplas abas também cobertos pelos testes HTTP.
- VPS: HEAD `a006bd3`, alterações locais preservadas. As seis cópias obtidas em leitura tiveram SHA-256 comparado com o servidor e ZIP; manifesto registra também modo e UID/GID. Backup local: `outputs/novixfin-vps-arquivos-20261105.zip` no diretório desta conversa. Revalidar hashes imediatamente antes de qualquer deploy: o servidor pode mudar depois do backup.

## Publicação proposta — requer autorização expressa

1. Revisar o PR, confirmar novamente hashes, diferenças locais, Caddy, saúde e backup dos dados. Se houver divergência, parar e refazer a comparação e o backup; não sobrescrever a árvore. Preparar os seis arquivos do commit aprovado e verificar seus hashes. Não há migração.
2. Na configuração do host NovixFin somente, antes de `handle @actions`, instalar e validar no ambiente de homologação uma barreira temporária que responda 503 para **todas** as requisições `/actions/sheets/*`, inclusive as de leitura, mantendo as demais rotas e sites. Exemplo a adaptar à configuração efetiva: `@sheet_pause path /actions/sheets/*` seguido de `handle @sheet_pause { respond "Planilha temporariamente indisponível" 503 }`. Validar a configuração inteira antes do reload. Confirmar de fora que save, snapshot, restore e load recebem 503. Garantir que todas as requisições iniciadas antes da barreira terminaram; se não houver meio confiável de confirmar drenagem, não prosseguir. A barreira deve permanecer ativa durante toda a troca.
3. Com a barreira ativa e requisições anteriores drenadas, copiar os cinco handlers e o JS de forma coordenada, preservando modo 664 e dono/grupo verificados, sem git pull/reset ou substituição da árvore. Conferir hashes dos seis e lint no PHP publicado. Garantir que PHP-FPM não está executando bytecode antigo conforme sua configuração de opcache; não reiniciar serviços compartilhados indiscriminadamente.
4. Remover a barreira apenas após confirmar os seis arquivos e cache. Healthcheck: landing e login HTTP esperados, rota `/actions/sheets/load.php` anônima redireciona ao login; com conta QA autorizada, verificar load com revision, POST legado 428 sem gravação, token inválido 422, conflito 409, salvar A1 e recarregar, snapshot/restore e duas abas. Verificar logs sanitizados e saúde dos containers relevantes. Não usar contas ou dados reais para testes de escrita.
5. Rollback: reinstalar a barreira e drenar novamente; preservar conteúdo pendente dos usuários e verificar cópias dos seis arquivos exatos da VPS anterior ao deploy, inclusive permissões. Restaurar esses arquivos coordenadamente e validar hashes, rota e cache. O backend anterior aceita clientes sem revisão: antes de liberar a barreira, comunicar o risco, coordenar fechamento/recarga de todas as páginas antigas e avaliar indisponibilidade até essa condição ser satisfeita. Não restaurar o banco por padrão, pois descartaria gravações posteriores; eventual restauração do banco exige decisão separada e reconciliação.

## Riscos e limites

- Nenhuma barreira, deploy ou migração foi aplicada; a sequência acima precisa ser ensaiada antes do deploy real e o controle de drenagem é condição de entrada.
- Fechamento forçado, falha do navegador ou do sistema pode impedir eventos e avisos. A cópia manual deve ser feita antes da saída; não há mesclagem automática.
- Uma resposta perdida deixa o resultado incerto; eventual conflito exige copiar o conteúdo local antes de atualizar. As cópias podem conter informações financeiras, portanto devem ser tratadas como privadas.
- Durante um rollback para backend antigo, a proteção de revisão deixará de existir. A troca não é segura sem a barreira e a coordenação das sessões.

## Atualização de ensaio — 2026-11-05
A barreira foi ensaiada isoladamente, mas a drenagem na VPS e a invalidação de bytecode no FPM continuam sem comprovação. Leia reports/sheets_gate_rehearsal_2026-11-05.md; a decisão permanece AINDA BLOQUEADO.
