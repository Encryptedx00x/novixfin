# Sonda privada aprovada e encerramento do preflight — 06/11/2026

> Atualização posterior: publicação autorizada foi tentada e revertida em contenção após interrupção da QA. Estado atual: planilhas HTTP 503; não considerar o GO histórico abaixo como publicação concluída. Relatório vigente: `sheets_publication_attempt_2026-11-06.md`.

**APTO PARA AGENDAR PUBLICAÇÃO.** A consulta real superou o único bloqueio pendente de observabilidade inicial. Não foi autorizado nem executado deploy. A aprovação refere-se ao agendamento do protocolo ensaiado; não declara que a produção já foi drenada ou atualizada.

## Estabilidade e instalação autorizada

A manutenção de capacidade foi confirmada pelo usuário, pelo término do chat Orcivo e por `INTEGRATION-COMPLETED-2026-11-06.md` do responsável. Nova inspeção antes da escrita: serviços com a mesma geração/restarts=0, sem lock de deploy ou processo de manutenção ativo, zero unidades systemd em falha. Em 04:04 UTC: RAM disponível 1.763.936 KiB, swap livre 832.556 KiB (~813 MiB), disco cerca de 6,9 GiB livres. Nenhum release Orcivo ocorreu nesta janela.

Foram instalados somente:

```text
/srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/fpm_request.php
/srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/fpm_sheets_admin.php
/srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/control.key
```

Diretórios root:33/0750; arquivos root:33/0640. Código extraído dos blobs Git de `9cdf034`, idêntico ao homologado, com hashes conferidos antes/depois. Chave gerada na própria VPS, sem aparecer em argumentos ou logs. Nenhum manifesto, trava, marcador, candidato ou arquivo da aplicação foi instalado.

Os caminhos estão fora de todos os roots da configuração ativa do Caddy. Quatro verificações HTTP, nos dois hosts NovixFin e nos caminhos normal e `/public`, retornaram a mesma página HTML de fallback (HTTP 200), sem código ou resposta da sonda. HTTP 200 isoladamente não significa exposição: a prova combina a separação de roots com a inspeção do corpo retornado. A sonda não foi publicada como endpoint HTTP.

## Consulta real — uma execução

Em **06/11 às 01:05:38 de Brasília (04:05:38 UTC)**, o cliente homologado executou somente:

```sh
# Registro do comando já executado; NÃO repetir sem necessidade/autorização.
sudo -n timeout 12s docker exec stack-php-1 php \
  /srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/fpm_request.php \
  admin tcp://127.0.0.1:9000 \
  /srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/fpm_sheets_admin.php \
  status /srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/control.key
```

Retorno: `ok=true`, SAPI `fpm-fcgi`, pool `www`, geração `1793209094`, fila `0`, total `3`, ativos `1`, ociosos `2`. O único ativo era a própria sonda, PID interno `789`, em `Running`.

| PID interno | PID no host | Estado na consulta |
|---:|---:|---|
| 788 | 4137295 | Idle |
| 789 | 4137331 | Running — própria sonda |
| 929 | 1738235 | Idle |

Master: PID interno `1`, host `2054`. Master e todos os workers já existiam antes da cópia dos utilitários e permaneceram iguais após a consulta. Nenhum PID desapareceu e nenhum sinal/configuração de FPM foi utilizado para obter a observação. A função nativa enxerga a geração anterior à instalação da própria sonda.

## Preservação e equivalência

- As 117 entradas modificadas/não rastreadas mantiveram conteúdos e modos antes/depois; os seis originais continuam correspondendo ao backup exato e aos metadados 0664/1000:1000. O Caddyfile e os hashes de configuração PHP/FPM ficaram iguais.
- Serviços mantiveram início, reinícios e saúde. Leitura posterior, 04:06:28 UTC: RAM 1.763.020 KiB, swap livre 832.796 KiB. Diretório privado contém exatamente os dois PHPs e a chave, sem manifesto/barreira.
- PHP 8.3.36, Caddy 2.11.4 e um pool relevante correspondem ao protocolo. Diferenças documentadas continuam: produção `dynamic`/máximo cinco (três no snapshot), laboratório `static`/cinco; OPcache de produção valida timestamps e protege atualização por 2s. O verificador aceita contagem observada e exige identidades estáveis; mudança legítima de workers pode abortar o checkpoint, sem alterar o pool.
- Nenhum comportamento funcional, teste ou controle de código mudou desde `ccf9ef8`. Evidências de 14 arquivos de testes, 33 verificações HTTP/MariaDB e Chrome permanecem aplicáveis. Não foram repetidas a suíte completa ou consultas de persistência no banco real.
- A observabilidade FPM está comprovada. **Drenagem completa ainda não foi executada**: não há barreira/trava nem métricas HTTP habilitadas. O teste local do checker com este snapshot aceita os processos válidos quando há métrica zero e recusa métrica ausente; não promove o snapshot a autorização de troca.

## Janela futura e rollback

Usar `sheets_publication_window_commands_2026-11-05.md`, atualizado para reutilizar esta instalação e preservar a chave. O pacote anterior permanece imutável; sua documentação interna é histórica, prevalecendo os comandos externos atualizados. Nenhum payload de publicação foi enviado agora.

Pré-condições da janela: autorização expressa, ausência de manutenção concorrente, capacidade e fontes/backup/permissões novamente conferidos, baseline FPM nova e validação Caddy com o ambiente real antes de aplicar. O candidato já passou no parser local e na comparação estrutural dos vhosts; `caddy validate` de provisionamento na VPS não foi executado nesta autorização restrita.

Depois, apenas na janela: barreira HTTP e métricas, primeiro checkpoint; bootstrap de trava/invalidação FPM e segundo checkpoint; cinco arquivos sob a trava/invalidação e terceiro checkpoint; bootstrap candidato por último, verificação e reabertura com QA autorizado. Não reconfigurar/recarregar/reiniciar FPM, não restaurar banco e não substituir a árvore inteira.

Abortar por fonte divergente, métricas ausentes/ativas, fila, outro worker Running/Finishing, geração/PIDs mudados, erro de cache/guarda ou degradação de infraestrutura. Prazo operacional limita a tentativa; não demonstra término. Rollback legado preserva os dados e mantém HTTP 503 + trava PHP, sem reabrir backend que aceite gravação sem revisão.

## Checkpoint final

Sonda instalada **SIM**; consulta real **APROVADA**; observabilidade para os checkpoints **SIM**; infraestrutura **COMPATÍVEL, com diferenças documentadas**; seis backups/hashes e árvore suja **APROVADOS**. Decisão **GO para agendamento; execução depende de decisão separada**.

Nenhuma barreira, reload, restart, invalidação de OPcache, alteração de aplicação/configuração, acesso SQL, merge ou deploy. Evidência sanitizada em `sheets_probe_approved_evidence_2026-11-06.json`; chave não incluída. Confiança alta no preflight observado, sem garantia de que a VPS continuará no mesmo estado até a futura janela.
