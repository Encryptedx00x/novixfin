# Publicação interrompida; planilhas em contenção HTTP 503 — 06/11/2026

> Atualização posterior: recuperação autorizada concluída em 06/11 às 07:01 de Brasília, com QA posterior HTTP/Chrome aprovada. Planilhas disponíveis; relatório vigente `sheets_recovery_completed_2026-11-06.md`. A falha e o rollback desta primeira tentativa abaixo permanecem como evidência histórica.

**Publicação funcional NÃO concluída. Planilhas temporariamente indisponíveis nos dois hosts.** O candidato `ccf9ef8` foi aplicado, os três checkpoints e as invalidações FPM passaram, mas a QA posterior foi interrompida. Conforme a autorização, foi executado rollback de contenção: cinco originais restaurados, bootstrap de trava e barreira HTTP mantidos. Não reabrir o backend legado.

## Resultado objetivo

| Item | Resultado |
|---|---|
| Deploy executado | SIM, aplicação temporária do candidato e rollback posterior; não é sucesso funcional |
| Candidato aplicado | Seis blobs exatos de `ccf9ef8`; PR inicial `a8728a3`, sem merge |
| Versão atual | Cinco arquivos originais da VPS + bootstrap temporário `sheets_entry_guard.php`; não é a release `ccf9ef8` |
| Checkpoints 1/2/3 | APROVADOS, mesma geração e workers, fila zero, sem trabalho antigo ativo |
| OPcache | Invalidações restritas no FPM APROVADAS na troca e no rollback; sem reset global/reload/restart |
| Persistência salvar/recarregar | NÃO validada nesta tentativa; QA parou antes da primeira escrita de célula |
| HTTP 428/422/409 | NÃO executados no domínio real nesta tentativa; evidências anteriores locais preservadas |
| Barreira final | ATIVA; 56/56 combinações retornam HTTP 503 |
| Landing/login | Disponíveis nos dois hosts; HTTP de sucesso |
| Serviços | Mesmos 22 IDs/início/contadores de reinícios/saúde da baseline |
| Rollback | NECESSÁRIO e concluído em contenção; sem restauração do banco |
| Decisão | NO-GO para declarar publicação concluída ou remover a contenção |

## Falha da validação e limite da conclusão

O smoke novo usado nesta tentativa criou uma conta sintética pelo cadastro normal, autenticou e recebeu HTTP 200 de `load.php`. Em seguida, reprovou a asserção `initial empty sheet is JSON object map`, que exigia `cells == {}`. O corpo dessa resposta não foi guardado, portanto não há evidência para identificar todos os campos concretos recebidos ou atribuir a falha a uma regressão do candidato.

**Essa exigência extra do smoke foi incorreta.** O código homologado do frontend (`public/scripts/sheet.js`, linha 835 no candidato) aceita arrays vazios vindos do PHP e os normaliza para um mapa antes de editar/serializar. O teste HTTP homologado exige HTTP 200 e `ok=true` na carga inicial; não exige objeto vazio nesse ponto do transporte. A asserção nova não representa esse contrato completo. Ela foi adicionada pelo agente e interrompeu esta execução; não deve ser apresentada como prova de perda de dados ou defeito confirmado da correção.

Mesmo assim, a QA desta tentativa ficou incompleta, e a regra expressa de interromper/seguir rollback foi respeitada. A asserção reprovada e os resultados foram preservados; não foram trocados por um resultado verde nem houve nova publicação após o rollback. Não há teste real desta tentativa que comprove salvar A1, recarga ou conflitos. A auditoria funcional anterior não foi refeita nem o código alterado.

## Gates e execução comprovados

- Manutenção Orcivo encerrada, chat sem operação ativa e ausência de locks/processos de manutenção na amostra. Cerca de 6,9 GiB de disco, 1,69 GiB de RAM e 816 MiB de swap livres. N8n tinha dois reinícios históricos, com início em 01/11; a baseline registrou esses valores sem exigir falsamente contador zero para toda a vida dos containers.
- Pacote imutável SHA-256 `0a709475fce6c7a9a98d7f317fabcd704e765d96fb14c52f9dd154803628876e`, payload completo, seis blobs Git e backup exato conferidos. Nenhum hash foi reconstruído para aceitar divergências.
- Originais/metadados 0664/1000:1000, Caddyfile original e 117 entradas sujas conferidos antes da troca. Payload preparado apenas no diretório privado; chave existente preservada.
- Caddyfile candidato acessível no container e `caddy validate` com ambiente real retornou `Valid configuration`. Aplicação somente via reload Caddy previsto, preservando inode do bind mount. Não houve sinal/reload/restart do FPM ou container.
- Barreira conferida em dois hosts, quatro endpoints e sete métodos (56 combinações). Configuração altera a rota NovixFin prevista e habilita métricas; os demais vhosts não foram editados.
- Checkpoint 1 antes da trava; checkpoint 2 após trava e invalidação; checkpoint 3 sob a trava com os outros cinco candidatos. Todos: geração `1793209094`, workers internos `788/789/929`, fila zero e nenhum worker antigo ativo além da sonda validada. Métricas reais zeradas coletadas junto de cada snapshot, com verificador de frescor.
- Bootstrap candidato instalado por último; seis hashes/metadados, lint e invalidação FPM final aprovados. Barreira removida somente após esses gates.
- QA parou na carga inicial, antes dos testes de salvamento. Rollback repetiu três provas, restaurou somente os cinco arquivos restantes e manteve o bootstrap 503. Não foi reposto o bootstrap legado que aceitaria gravação sem revisão.

## Linha do tempo — Brasília / UTC

| Evento | Brasília | UTC |
|---|---|---|
| Barreira e checkpoint 1 | 01:19:52–01:19:56 | 04:19:52–04:19:56 |
| Trava e checkpoint 2 | 01:20:12–01:20:13 | 04:20:12–04:20:13 |
| Cinco candidatos e checkpoint 3 | 01:20:34–01:20:36 | 04:20:34–04:20:36 |
| Bootstrap final e reabertura | 01:21:09–01:21:10 | 04:21:09–04:21:10 |
| QA interrompida | 01:23:25 | 04:23:25 |
| Contenção/rollback | 01:24:26–01:24:30 | 04:24:26–04:24:30 |
| Verificação HTTP final | 01:27:38–01:27:41 | 04:27:38–04:27:41 |

## Preservação e estado de recuperação

Diretório privado: `/srv/sites/.novixfin-deploy/probe-20261106T040503Z`. Marcador ativo: `/srv/sites/.novixfin-deploy/sheets.pause`. Manifesto atual: `controls/sheets-manifest.json`, bytes do manifesto aprovado `rollback-guard.json`.

SHA-256 do bootstrap de contenção: `6d05fafb00a069ca65d567ba1a4de0e771176e161d1cc6abb31134250fdeac8b`. Os outros cinco arquivos correspondem aos originais do backup. Todas as 117 entradas continuam presentes; as outras 116 têm os mesmos conteúdos/modos da baseline, e o bootstrap difere deliberadamente pela trava. O bootstrap original permanece no backup exato e no payload de rollback.

Não houve SQL direto, migration ou restauração de banco. A QA utilizou somente os fluxos da aplicação para criar uma conta sintética e carregar sua planilha inicialmente vazia. Esses registros foram preservados. Nenhuma planilha existente de usuário foi usada pelo agente, e gravações de terceiros eventualmente realizadas na breve reabertura não foram descartadas por restore de banco. Credenciais da conta QA estão somente no arquivo local privado, fora do Git e das evidências publicáveis.

A verificação posterior confirmou os mesmos IDs/início/restarts/saúde dos 22 containers e a configuração FPM inalterada. Nenhum outro projeto foi modificado. O Caddyfile candidato permanece ativo para manter a barreira; não restaurar a configuração compartilhada inteira cegamente.

## Ações necessárias para recuperação — não executadas

1. Revisar o smoke de domínio contra o contrato já homologado e preservar a evidência da falha. Capturar resposta sanitizada da conta sintética e verificar o comportamento observável correto: editar/salvar A1 e recarregar; não usar a forma de uma coleção vazia isoladamente como prova funcional.
2. Confirmar nova decisão de retomada, exclusividade/capacidade e hashes do estado **rollback-guard**. Não comparar com o bootstrap original esperando que a trava coincida, nem editar os hashes aprovados para fazê-los passar.
3. Reutilizar o mesmo candidato/pacote, sem alterações de aplicação: manter HTTP 503; nova baseline e primeiro checkpoint; reafirmar a trava/invalidação e segundo checkpoint; cinco candidatos sob a trava/invalidação e terceiro checkpoint; bootstrap candidato por último, invalidação e verificação final.
4. Só então reabrir e executar QA completa em conta sintética: persistência/recarga, múltiplas páginas, 428/422/409, snapshot/restore e frontend servido. Nova reprovação mantém a contenção. Nenhum SQL direto ou restore de banco.

Não houve nova tentativa de publicação depois do rollback. Os comandos de janela anteriores são referência histórica desta execução, não devem ser repetidos cegamente no estado atual, em que os diretórios/candidatos já existem e o bootstrap é uma trava.

## Checkpoint

Evidência sanitizada em `sheets_publication_attempt_evidence_2026-11-06.json`. Código do repositório, candidato e testes homologados inalterados; novos arquivos são documentação/evidências/continuidade. Risco principal atual: indisponibilidade deliberada das planilhas. A recuperação permanece pendente; esta tarefa não produziu uma publicação funcional concluída.
