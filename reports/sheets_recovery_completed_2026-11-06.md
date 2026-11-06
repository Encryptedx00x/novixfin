# Recuperação concluída — planilhas disponíveis — 06/11/2026

**RECUPERAÇÃO CONCLUÍDA. Planilhas disponíveis em `novixfin.cash` e `www.novixfin.cash`, com os seis arquivos funcionais exatos de `ccf9ef8`.** Barreira removida após os três checkpoints, bootstrap final, hashes/metadados e invalidação FPM aprovados. QA HTTP e Chrome concluída usando exclusivamente a identidade sintética já preparada.

## Entrega objetiva

| Item | Resultado |
|---|---|
| Recuperação concluída | SIM |
| Planilhas disponíveis nos dois hosts | SIM; novas sessões autenticadas retornam o mesmo conteúdo e revisão |
| A1 e recarga | PASS no HTTP e no Chrome, inclusive em aba inicialmente vazia |
| Fórmula sem Enter | PASS: `=2+3`, clique fora, salvamento e recarga real |
| Múltiplas abas/páginas | PASS; três abas persistidas e duas sessões com conflito controlado |
| HTTP 428/422/409 | PASS no domínio real; ausência/formato nos três handlers de escrita e conflito entre sessões |
| Snapshot/restauração | PASS por fluxos HTTP da aplicação, seguido de nova autenticação e recarga |
| Checkpoints FPM 1/2/3 | PASS; geração `1793209094`, workers `788/789/929`, fila zero e somente a sonda ativa |
| Bytecode FPM | PASS; três invalidações restritas com cinco PHPs `cached_after=false`; código novo também confirmado pelo comportamento 428/422/409 |
| Frontend servido | PASS; SHA-256 do JS aprovado em ambos os hosts e no navegador real |
| Barreira HTTP | INATIVA; marcador removido, regra preparada permanece sem bloquear |
| Serviços/dados | 22 IDs/início/restarts/saúde preservados; 111 entradas sujas restantes intactas; seis substituições autorizadas verificadas |
| Rollback nesta recuperação | NÃO necessário; nenhuma segunda aplicação após os gates desta execução |
| Merge PR #1 | NÃO executado |

## Pré-condições e preservação

Foi confirmada a ausência de manutenção concorrente: chat Orcivo encerrado, nenhuma operação ativa de swap/build/deploy encontrada e locks operacionais livres. Capacidade inicial: mais de 6 GiB de disco, 1,7 GiB de RAM disponível e aproximadamente 775 MiB de swap livre. Os 22 containers mantiveram os valores já registrados; não se exigiu contador zero de reinícios históricos.

A produção partiu do manifesto aprovado `rollback-guard`: bootstrap de trava `6d05fafb00a069ca65d567ba1a4de0e771176e161d1cc6abb31134250fdeac8b`, cinco originais, modos 0664 e UID/GID 1000:1000. Foram conferidos os 117 conteúdos/modos e os hashes PHP/FPM/Caddy. Pacote imutável SHA-256 `0a709475fce6c7a9a98d7f317fabcd704e765d96fb14c52f9dd154803628876e`, controles e chave existentes reutilizados, sem reconstrução de artefatos ou hashes.

Registros novos ficaram em `/srv/sites/.novixfin-deploy/probe-20261106T040503Z/recovery-20261106T095913Z`, preservando os arquivos e evidências da primeira tentativa. O primeiro smoke e sua falha HTTP 200/asserção indevida continuam intactos, sem substituição por um resultado verde. A correção do smoke mudou exclusivamente o contrato da QA, o reaproveitamento da conta existente e a captura de respostas; nenhum código funcional foi alterado além dos seis blobs homologados.

## Protocolo executado

1. Barreira existente mantida e verificada em 56 combinações (dois hosts, quatro endpoints, sete métodos), nova baseline FPM e checkpoint 1.
2. Guarda reafirmada, manifesto `guard-original`, hashes/metadados e invalidação FPM conferidos; quatro probes privados 503 e checkpoint 2.
3. Reaplicados somente load/save/snapshot/restore e sheet.js sob a guarda. Lint, hashes e invalidação aprovados, quatro probes privados e checkpoint 3.
4. Bootstrap candidato instalado por último, manifesto `candidate`, seis hashes/metadados, lint e invalidação/inspeção FPM aprovados. Estado dos 22 serviços conferido antes de reabrir.
5. Marcador removido em **06/11 às 07:01:01 de Brasília (10:01:01 UTC)**. Não houve reload/restart FPM, Caddy ou container nesta recuperação. A configuração Caddy já estava validada/ativa na tentativa anterior e foi revalidada por hash; não foi reaplicada.

| Checkpoint | Horário UTC | Resultado |
|---|---|---|
| 1 | 09:59:44–09:59:48 | PASS |
| 2 | 10:00:02–10:00:04 | PASS |
| 3 | 10:00:25–10:00:27 | PASS |
| Bootstrap final/reabertura | 10:01:00–10:01:01 | PASS |

As provas combinam scoreboard nativo com métrica HTTP real e amostras frescas. Não houve espera fixa usada como drenagem, alteração de baseline entre checkpoints, morte de workers ou relaxamento do verificador.

## QA e evidência real

**31 verificações HTTP PASS:** carga inicial vazia, A1 e duas abas, persistência em novo GET, três salvamentos consecutivos, ausência de revisão 428 e revisão malformada 422 em save/snapshot/restore, CSRF inválido 419, duas sessões com revisão antiga 409 sem sobrescrita, snapshot/restauração, nova autenticação, ambos os hosts, acesso anônimo e JS servido com hash exato. Foram registrados corpos JSON reais apenas da conta sintética, sem senhas/cookies/tokens.

**17 verificações Chrome PASS:** o frontend servido corresponde ao blob aprovado; A1 pela barra de fórmulas, clique fora e recarga; fórmula `=2+3` sem Enter e recarga; segunda sessão lê a fórmula, tenta gravar revisão antiga, recebe 409 e mantém o texto pendente, enquanto o valor mais recente permanece no servidor; abas anteriores preservadas. Uma aba nova vazia foi criada pela UI, salva e recarregada. O HTTP retornou suas células como **array vazio**; o frontend normalizou corretamente, A1 foi editada sem Enter e persistiu após nova recarga. Isso valida o comportamento necessário, sem impor `{}` ao transporte.

O primeiro início do teste Chrome encontrou a tela de onboarding, sem elementos ou requisição de planilha e sem erros JS. O fixture homologado cria também uma conta financeira de saldo zero; esse pré-requisito faltava à identidade sintética usada no cadastro normal. Foi criada **somente nessa identidade**, pela própria UI, uma conta `QA planilhas - saldo zero`, tipo banco, sem transações. O mesmo teste Chrome foi então executado sem relaxar suas asserções. O timeout e a captura de diagnóstico foram preservados; não houve nova publicação entre diagnóstico, preparação do fixture e QA.

Depois de todos os testes, sessões novas nos dois hosts receberam a mesma revisão e as três abas, incluindo A1 da aba inicialmente vazia. A conferência final às **07:14:41 de Brasília (10:14:41 UTC)** confirmou landing/login HTTP 200 nos dois hosts, marcador ausente, manifesto candidato, arquivos exatos e serviços preservados. RAM disponível 1.824.228 KiB; swap livre 805.652 KiB.

## Integridade e limites

- Os seis arquivos publicados têm hashes do manifesto `ccf9ef8`, modo 0664 e dono/grupo 1000:1000. As outras 111 entradas sujas têm os mesmos conteúdos/modos; o conjunto de 117 caminhos permanece. Os originais completos continuam nos backups exatos. O checkout publicado não foi resetado, puxado ou substituído por inteiro.
- Não houve SQL direto, migrations ou restauração de banco. Toda QA ocorreu nos dados sintéticos existentes; foram mantidos seus snapshots/abas e o fixture de saldo zero. Nenhuma planilha real de outro usuário foi usada ou alterada pelo agente.
- Não foram alterados Orcivo, outros vhosts, serviços compartilhados ou configurações FPM. A configuração Caddy e seus hashes permanecem iguais aos do início desta recuperação; a regra dormente e as métricas administrativas continuam disponíveis.
- Não se repetiu a suíte completa ou a auditoria anterior: código funcional idêntico ao homologado. A validação focada de produção foi concluída e suas respostas reais estão no JSON correspondente.
- Persistem os riscos conhecidos: cliente antigo recebe 428 e precisa preservar/copiar pendências antes de atualizar; resposta perdida exige recuperação manual; encerramento forçado do navegador pode impedir avisos. Não há garantia de execução futura baseada apenas nesta fotografia.

## Checkpoint e próximos passos

Evidência sanitizada: `sheets_recovery_evidence_2026-11-06.json`. Capturas e fontes exatas dos probes, sem credenciais, estão nos entregáveis locais. A primeira falha, o smoke antigo e os registros de rollback foram preservados.

Nenhuma pendência bloqueia o uso das planilhas após esta recuperação. PR #1 permanece aberto, sem merge. Limpeza opcional dos dados sintéticos e eventual integração do PR são decisões separadas; não foram iniciadas. Qualquer rollback futuro deve novamente conter/drenar e manter o legado bloqueado, preservando o banco.
