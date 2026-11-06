# Auditoria final e PHP-FPM — 2026-11-05

> Atualização: o protocolo vigente sem reload está em `reports/sheets_first_transition_2026-11-05.md`. O bootstrap por reload descrito abaixo permanece reprovado e não foi repetido.

**NO-GO para a VPS atual.** A correção funcional está aprovada no escopo auditado. A drenagem com observação previamente instalada e a invalidação efetiva de OPcache foram comprovadas em PHP-FPM Linux. O primeiro corte da instalação atual, sem status e sem observador preexistente de todos os trabalhos, continua sem procedimento comprovadamente seguro. O reload gracioso usado para instalar o status foi REPROVADO no controle negativo com trabalho após resposta. Não foi executada nenhuma operação de escrita na VPS, nenhum deploy ou merge; a manutenção de armazenamento Orcivo não foi tratada como concluída por inferência.

Este documento substitui as recomendações operacionais anteriores quando houver divergência. O teste PHP-CGI anterior não é usado como prova de OPcache do FPM.

## 1. Revisão de código — APROVADA após correção

Foram revisados o diff completo da implementação e o contexto dos cinco handlers e de `sheet.js`: serialização, autenticação/CSRF, escopo de usuário, comparação da revisão sob bloqueio de linha, transações, fila de escritas, respostas perdidas e conflitos. O estado confirmado não é atualizado por resposta de uma edição anterior; o cliente obtém a revisão corrente no momento de cada escrita enfileirada. A revisão por hash é comparação de conteúdo, não segredo de autenticação nem relógio monotônico.

Foi encontrado e corrigido um defeito real: o listener global de `copy` capturava o textarea de recuperação e substituía o JSON selecionado pelo TSV da célula. O Chrome reproduziu a falha lendo o clipboard real; após a correção, Ctrl+C conserva o JSON de todas as abas. `paste` e atalhos da grade também respeitam a área de texto. As expectativas de persistência não foram relaxadas.

Evidências desta auditoria: suíte completa **14 arquivos aprovados, zero falhas, nenhum SKIP**, MariaDB local descartável; Chrome aprovado com comparação do clipboard real; teste HTTP mantido com **33 verificações**. Nenhuma funcionalidade da planilha foi reimplementada. Permanecem limites conhecidos: encerramento forçado pode perder alterações ainda não confirmadas; resposta perdida exige cópia manual e eventual conflito; não há mesclagem automática. A dependência externa do motor de cálculo e o modelo de documento existentes foram preservados.

## 2. O que foi comprovado no FPM real

Ambiente LOCAL exclusivo: Docker em WSL/Linux, PHP-FPM **8.3.36**, MariaDB **11.8.8**, Caddy **2.11.4**. Bases novas e sintéticas. Nenhum container compartilhado da VPS foi utilizado. O laboratório recusa Docker remoto e o hostname da VPS e remove apenas containers da execução com nome aleatório e label própria.

O listener independente `pm.status_listen` respondeu mesmo com os cinco workers da aplicação em `Finishing` e uma sexta requisição na fila. Após a resposta HTTP ou desconexão, o PHP permaneceu visivelmente ativo até concluir a gravação. A troca foi recusada enquanto havia worker ocupado, fila ou requisição HTTP antiga incompleta. O observador exige, simultaneamente:

1. Barreira confirmada por 503 nos quatro endpoints e todos os métodos ensaiados.
2. Contadores do Caddy da geração observada sem requisições de subrota em andamento. Métrica ausente/inacessível não significa zero; o laboratório soma as subrotas de forma conservadora, podendo atrasar a operação por tráfego de outros hosts.
3. Fila FPM zerada, `active processes=0`, todos os workers conhecidos `Idle`, mesma geração e mesmas identidades de processo. O laboratório usa pool estático de cinco workers para esse controle; não se deve alterar o pool compartilhado de produção para imitar o teste. Mudança inesperada de workers em produção exige aborto/reavaliação, não uma nova linha de base automática.
4. Ausência de entrada alternativa no FastCGI ou de jobs que executem os arquivos alvo fora desse pool. Esta exclusividade precisa ser comprovada no ambiente alvo.

Não se usou espera fixa como prova. Deadlines do teste apenas abortam. O pool real observado anteriormente tinha timeout de requisição ilimitado: **não existe máximo de drenagem demonstrado para a VPS**.

### Barreira sem reload durante a drenagem

O mecanismo validado mantém uma configuração permanente e alterna somente um arquivo administrativo fora dos webroots. Isso preserva a geração observada do Caddy durante a operação. Não basta instalar métricas depois que os trabalhos começaram ou aceitar contadores de uma geração diferente.

```caddyfile
# Dentro apenas do host NovixFin, antes de handle @actions.
@sheet_pause {
    path /actions/sheets/*
    file {
        root /srv/sites/.novixfin-deploy
        try_files sheets.pause
    }
}
handle @sheet_pause {
    respond "Planilha temporariamente indisponível" 503
}
```

Foram aprovadas 28 combinações dos métodos GET/POST/PUT/PATCH/DELETE/OPTIONS/HEAD com load/save/snapshot/restore; outro host e outras rotas permaneceram acessíveis. Um upload com corpo incompleto impediu a drenagem; cabeçalhos incompletos passaram pela barreira quando completados. O mecanismo não foi instalado na VPS.

### OPcache — VALIDADO em FPM, sem reinício durante a troca

O controle negativo carregou o script A no OPcache, gravou B no disco preservando o mtime e confirmou que o FPM continuava executando A com `validate_timestamps=0`. Uma invalidação pelo CLI não mudou esse resultado. Após invalidação autenticada feita pelo próprio FPM, os mesmos cinco workers executaram B. Não se considerou um retorno booleano isolado prova de versão executada.

Os seis arquivos reais foram conferidos por SHA-256 no contexto FPM, os cinco PHPs foram invalidados pelo controle privado e o candidato passou nas 33 verificações HTTP/MariaDB. Modos 0664 e UID:GID 1000:1000 foram verificados no Linux. O procedimento não desativa OPcache globalmente nem reinicia o pool durante a troca. Os valores de cache usados no laboratório são um controle de teste, não uma configuração recomendada para produção.

### Rollback — VALIDADO como contenção, sem reabertura do legado

Uma divergência de hash foi injetada e detectada antes de liberar acesso. Os seis arquivos anteriores foram restaurados, com hashes, modos e código A executado no FPM; o banco conservou as gravações posteriores. A barreira permaneceu ativa porque o backend antigo aceita escritas sem revisão. Isso é rollback seguro com a planilha indisponível, não retomada segura do backend antigo. Reabertura só é permitida com implementação estrita homologada; não restaurar banco por padrão.

## 3. Ativação inicial — NÃO COMPROVADA / controle negativo reprovado

O modo `LAB_BOOTSTRAP=YES` iniciou o pool sem status e colocou duas requisições em trabalho após `fastcgi_finish_request`. O container exclusivo recebeu USR2 para carregar a configuração de status. As identidades PID+start_ticks antigas desapareceram **sem executar as duas gravações esperadas**: 0 realizadas, 2 esperadas, antes e depois da liberação do sinal do laboratório. Portanto, neste cenário de PHP-FPM 8.3.36, "reload gracioso terminou" ou "PIDs antigos sumiram" NÃO comprova preservação do trabalho pós-resposta. Esse modo é um teste negativo e não uma instrução de produção.

Na VPS, a última inspeção em leitura ainda apontava status desativado e timeout ilimitado. Os hashes dos seis arquivos continuavam iguais ao backup verificado. A ativação do observador não será aplicada durante a manutenção Orcivo, nem após ela sem uma janela autorizada e uma estratégia inicial que preserve trabalho existente. Uma nova instância dedicada a NovixFin pode nascer com observação e releases imutáveis, mas o primeiro corte ainda precisa cercar os writers antigos ou provar sua conclusão: apenas desviar tráfego ou fazer um contador começar em zero não é suficiente. Essa alternativa não foi implementada nem validada como migração de produção neste PR.

Assim, a solução operacional está validada **para um pool e proxy já observáveis antes das requisições relevantes**. Não há autorização técnica para transplantar essa conclusão à geração não observada atualmente em produção.

## 4. Utilitários preparados e segurança

- `scripts/deploy/fpm_request.php`: CLI consulta FastCGI somente por loopback e falha em resposta incompleta, timeout ou listener ausente. A saída de status omite URI e usuário.
- `scripts/deploy/fpm_sheets_admin.php`: deve ser copiado para diretório privado fora de TODOS os webroots, com `control.key` aleatória de 64 caracteres ou mais e `sheets-manifest.json` root-owned. Exige SAPI `fpm-fcgi`, chave em parâmetro FastCGI próprio e manifesto contendo exatamente os seis caminhos fixos. Confere todos os hashes antes de invalidar somente os cinco PHPs. Não chama reset global. Acesso sem segredo foi testado e recusado.
- O manifesto contém `root` absoluto do NovixFin e `sha256` dos seis arquivos na ordem `_bootstrap.php`, `load.php`, `save.php`, `snapshot.php`, `restore.php`, `sheet.js`, com seus caminhos relativos completos. O manifesto, a chave e o diretório devem ser graváveis apenas pelo administrador, legíveis pelo usuário FPM quando necessário. Nunca versionar chave ou `.env`.
- Os utilitários não aplicam configurações, não decidem sozinhos que houve drenagem e não autorizam deploy. Não publicar o controle administrativo como endpoint HTTP. O socket de status deve ficar restrito ao loopback do container, sem porta publicada e sem rota pública no Caddy.

## 5. Reprodução isolada

Em Linux LOCAL com Docker local, Git, Python 3.8+ e Caddy 2.11.4, bases novas são criadas dentro de um MariaDB exclusivo em tmpfs e removidas ao terminar. Portas locais 33317, 38127, 38128, 39091 e 39092 devem estar livres. O contexto Docker deve usar `unix:///var/run/docker.sock`. Não executar na VPS. O teste requer Docker com imagens previamente disponíveis; os digests de origem estão nas evidências JSON.

```sh
docker build -f tests/deploy_gate/Dockerfile.fpm -t novixfin-fpm-lab:8.3.36 tests/deploy_gate
# Imagem MariaDB 11.8.8 deve estar disponível localmente.
NOVIXFIN_LOCAL_LAB=YES CADDY_EXE=/caminho/caddy python3 tests/deploy_gate/fpm_lab.py
# Controle negativo separado: não é uma forma aprovada de instalar o status.
NOVIXFIN_LOCAL_LAB=YES LAB_BOOTSTRAP=YES CADDY_EXE=/caminho/caddy python3 tests/deploy_gate/fpm_lab.py
```

No checkout Windows/WSL, cujo `.git` de worktree não é interpretável pelo Git Linux, podem ser fornecidos `LAB_SOURCE_ZIP` (archive do commit aprovado) e `LAB_BASELINE_ZIP` (archive dos seis arquivos da base `2a2f976`). As cópias de baseline do laboratório não substituem o backup exato da árvore suja da VPS. O script retém evidência em `/tmp/nvx-fpm-.../evidence.json`; os JSONs sanitizados deste PR registram as duas execuções aprovadas do ensaio e a reprovação de segurança do bootstrap.

## 6. Procedimento de publicação — condicionado, NÃO liberado na VPS atual

**Checkpoint 0 — NO-GO obrigatório hoje.** Confirmar conclusão e estabilização da manutenção Orcivo com o responsável. Não inferir isso de uptime ou passagem de tempo. A geração atual de writers precisa de uma solução inicial validada que não os interrompa; o reload reprovado acima não atende. Sem essa pré-condição, parar aqui. Não instalar status, métricas ou barreira como se isso retroativamente drenasse trabalhos antigos.

**Checkpoint 1 — pré-condições para uma geração já observada.** Configurações validadas antes de ela receber o tráfego relevante; Caddy com métricas e barreira permanente por arquivo; status completo em listener separado no FPM, por exemplo:

```ini
; Configuração de pool a preparar e validar em janela autorizada.
; NÃO aplicar por reload ao pool atual com trabalhos não observados.
[www]
pm.status_path = /__novixfin_fpm_status
pm.status_listen = 127.0.0.1:9001
```

Não modificar `pm` ou os timeouts globais para adaptar o ambiente ao ensaio. Registrar geração/PIDs, confirmar que não existem rotas alternativas ou processos derivados que executem os arquivos alvo, e preparar controle privado fora dos webroots. O listener adicional tem custo de um mecanismo/pool de status; a primeira configuração tem impacto no pool compartilhado e não foi autorizada. Validar a configuração completa do Caddy em cópia antes de qualquer reload autorizado, preservando outros hosts.

**Checkpoint 2 — backup e entrada na barreira.** Revalidar hashes contra o backup local já preparado. Em divergência, refazer backup exato e revisar o diff antes de prosseguir. Registrar permissões/dono/grupo dos seis arquivos. Gerar manifesto do candidato e do backup. Criar o marcador somente sob condições dos checkpoints 0/1. Os comandos seguintes são exemplos para a futura janela, não foram executados:

```sh
# Apenas APÓS a liberação explícita dos checkpoints anteriores.
# Diretório privado já preparado e validado, gravável só pelo administrador.
touch /srv/sites/.novixfin-deploy/sheets.pause
# Confirmar 503 para todos os métodos/endpoints e rotas não afetadas.
# Consultar de dentro do container, sem publicar o listener:
docker exec stack-php-1 php /srv/sites/.novixfin-deploy/fpm_request.php status tcp://127.0.0.1:9001
# Obter métricas na API administrativa restrita do Caddy e comparar geração.
# Ausência de métrica ou status, fila >0, active >0 ou worker não Idle => ABORTAR.
```

A prova exige métricas zeradas da mesma geração + status sem fila/ativos e identidades estáveis. O código de referência do observador está em `require_drained` no laboratório. Não usar apenas o resumo do status nem ignorar `Finishing`. Um prazo operacional pode somente abortar; com o timeout ilimitado existente não há tempo máximo comprovado de drenagem.

**Checkpoint 3 — troca e cache.** Com a barreira ativa e prova revalidada imediatamente antes da troca, substituir somente os seis arquivos aprovados, por arquivos temporários completos seguidos de renomeação no mesmo filesystem, preservando dono/grupo/modo e hashes; nunca usar pull/reset nem sobrescrever a pasta. Conferir novamente hashes, lint e o manifesto privado. Invalidar pelo FPM, não pelo CLI:

```sh
docker exec stack-php-1 php /srv/sites/.novixfin-deploy/fpm_request.php admin tcp://127.0.0.1:9000 /srv/sites/.novixfin-deploy/fpm_sheets_admin.php invalidate /srv/sites/.novixfin-deploy/control.key
```

Conferir `sapi=fpm-fcgi`, hashes, geração de OPcache e ausência dos cinco alvos em cache após invalidação. Revalidar status após o controle administrativo e executar sondas comportamentais privadas da versão antes de reabrir. Um booleano `true` isolado não basta. A escrita dos arquivos só deve ocorrer após a prova de drenagem; manter os checkpoints e a barreira se qualquer verificação falhar.

**Checkpoint 4 — reabertura e QA.** Somente após os checkpoints e autorização de publicação, remover o marcador; conferir load autenticado com revisão, 428/422/409 esperados, salvar A1 e recarregar, snapshot/restore e duas abas com conta QA autorizada. Verificar logs sanitizados e saúde dos demais hosts sem escrever seus dados. Não executar os testes de fábrica de banco local na VPS.

**Rollback.** Reativar marcador, drenar pela mesma prova e restaurar somente os seis arquivos exatos do backup com hashes, modo/dono/grupo e invalidação FPM verificados. Preservar banco, inclusive gravações posteriores. Se o destino é o backend antigo sem controle de revisão, manter a barreira da planilha: não há liberação segura demonstrada desse backend. O retorno de disponibilidade exige uma release estrita conhecida e homologada. Nenhum rollback restaura o banco por padrão.

## 7. Decisão e riscos residuais

Revisão funcional APROVADA após correção do clipboard; drenagem, OPcache e barreira COMPROVADOS/VALIDADOS no FPM isolado com observadores preexistentes. Rollback VALIDADO como contenção com barreira. **NO-GO na VPS atual**: primeiro corte não resolvido, manutenção não confirmada estável e operações não autorizadas. A próxima decisão precisa escolher e validar o bootstrap de observabilidade ou um corte por releases imutáveis com fencing de writers antigos; não deve repetir uma espera ou o reload reprovado.

Fontes primárias de apoio: [status FPM](https://www.php.net/manual/en/fpm.status.php), [opcache_invalidate](https://www.php.net/manual/en/function.opcache-invalidate.php), [métricas Caddy](https://caddyserver.com/docs/metrics) e [matcher de arquivo](https://caddyserver.com/docs/caddyfile/matchers#file). As conclusões sobre `Finishing`, reload e bytecode decorrem dos ensaios registrados, não de uma promessa genérica dessas fontes.
