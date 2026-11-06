# Preflight de produção — NovixFin, candidato ccf9ef8

Inspeção de 05/11/2026 às 23:39 até 06/11 às 00:11, horário de São Paulo (06/11, 02:39–03:11 UTC). O nome do artefato conserva a data inicial. Escopo: leitura na VPS e preparação local. Nenhuma barreira, sonda instalada, invalidação de cache, reload, configuração alterada, deploy, merge ou escrita SQL.

## Decisão

**BLOQUEADO para encerrar o preflight e agendar uma publicação com todos os gates comprovados.** A configuração é compatível com a sonda sem reload, mas a primeira consulta ao scoreboard do FPM vivo ainda não foi executada. O diretório privado não existe; instalar seus arquivos/chave seria uma escrita fora da autorização atual. Não confundir inspeção do binário/configuração com prova de funcionamento da sonda no processo em serviço.

O próximo passo é autorizar separadamente a preparação privada e a consulta `status`, sem ativar barreira, alterar configuração ou publicar. Se ela funcionar e retornar identidades válidas, o bloqueio específico de observabilidade pode ser encerrado. A drenagem continua sendo verificada nos três checkpoints da janela, nunca por esta fotografia de preflight.

## Manutenção e estabilidade

- A manutenção de capacidade está concluída no relatório do responsável: `C:\Users\Encryptedx\Desktop\orcivo-post-deepseek-audit-20261104\docs\qa\FINAL-CONTINUITY-2026-11-05.md`, seções Backup/liberação e Capacidade. Foram removidas individualmente 15 imagens antigas, recuperados 4,97 GiB e validados backups/restaurações isolados. A conclusão também está registrada no chat “Auditar e preparar release Orcivo”. Esse histórico não autoriza operações de outro projeto.
- Na inspeção: cerca de 8.003.993.600 bytes livres (7,45 GiB), 1,73 GiB de RAM disponível e 480 MiB de swap livre; depois, 8.003.874.816 bytes livres e 492.088 KiB de swap livre. Sem troca de swap nas duas amostras intervalares do vmstat, sem OOM/erro de I/O/EXT4 registrado nas duas horas consultadas, zero unidades systemd em falha.
- Serviços Orcivo de produção com healthcheck `healthy`; Caddy, PHP e MariaDB compartilhados sem reinício desde 28/10, contagem de reinícios zero. Esses três containers não têm healthcheck Docker configurado: seu estado `Up` não foi rotulado como `healthy`. Landing/login NovixFin responderam HEAD HTTP 200. PHP/configuração e conectividade administrativa do Caddy foram inspecionados separadamente.
- A leitura final, 03:11:41 UTC, manteve as mesmas gerações e saúde dos serviços; RAM disponível 1.763.584 KiB e swap livre 493.432 KiB. Os conteúdos/modos das 117 entradas sujas e o hash do Caddyfile permaneceram iguais entre as duas fotografias.
- Nenhum processo de resize/rsync/manutenção encontrado na amostra nem lock de deploy Orcivo adquirido em `lslocks`. Isso não reserva a VPS. A janela precisa ser exclusiva, coordenada com o responsável pelo Orcivo.
- O gate de 512 MiB de swap do deploy do Orcivo permanece abaixo do requisito, segundo seu próprio relatório. Não executar aquele script nem alterar swap. O NovixFin não fará build de containers ou migrações; ainda assim, falta de capacidade, atividade concorrente ou degradação exige adiar sua janela.

## Infraestrutura observada e comparação

| Item | VPS | Laboratório/implicação |
|---|---|---|
| PHP/FPM | 8.3.36; master em serviço usa o mesmo binário em disco | Mesma versão. SHA-256 do executável e `/proc/1/exe`: `c05fc7b4fa52c240c93ee21f2497bf897bd0bcdea3da0166c18cb06354ee4cdb` |
| Pool | Um `www`, UID/GID 33:33; listener `9000`, sem status_path/status_listen, sem chroot | Lab tinha listener loopback 39091 e pool estático de cinco workers |
| Política | dynamic; max_children=5, start=2, spare=1..3, max_requests=0; três workers observados | Não presumir cinco processos. O checker exige identidades estáveis entre baseline e checkpoint; spawn/saída exige aborto e nova avaliação, sem alterar `pm` |
| Função nativa | `fpm_get_status` presente no binário; nenhuma função desabilitada | Compatibilidade estática confirmada; chamada no FPM vivo pendente |
| Execução | `max_execution_time=30`, `request_terminate_timeout=0`, track_finished=no | Não há prazo máximo garantido para trabalho pós-resposta; timeout serve para abortar |
| OPcache | enable=On, enable_cli=Off, validate_timestamps=On, revalidate_freq=2, file_update_protection=2; sem preload/file_cache/restrict_api | Lab usou timestamps=0 e file_update_protection=0, controle mais estrito de cache antigo. Não mudar INI da VPS; invalidar só os cinco PHPs no FPM e exigir prova de guarda/versão |
| Configuração de entrada | Sem auto_prepend/append, open_basedir ou .user.ini encontrados; configurações anteriores ao master | Nenhum desvio identificado; não é leitura do estado vivo do OPcache |
| Caddy | 2.11.4, mesmo build do lab; API administrativa interna, porta 2019 não publicada | Métrica HTTP `caddy_http_requests_in_flight` ausente. Habilitar métricas somente na janela autorizada; sem interpretar métricas novas como observação retroativa |
| Mounts | PHP e Caddy enxergam `/srv/sites` no mesmo caminho; Caddyfile é bind mount de arquivo individual | Diretório privado pode ser preparado sem trocar mounts/FPM. Substituir Caddyfile por rename no host quebraria a equivalência do inode montado; usar escrita no arquivo existente após validar o candidato |
| MariaDB | Cliente do container 11.8.8; nenhum SQL executado neste preflight | Lab 11.8.8. Sem migração, reset ou restore de banco |

`php-fpm -tt` testou a configuração em disco sem sinalizar o processo em serviço. `php-fpm -i` inspecionou as opções do binário/SAPI em processo separado; não constitui leitura do OPcache dos workers atuais. Nenhum diagnóstico usou reload gracioso ou restart.

## Rotas, processos e uploads

- Configuração ativa obtida pela API administrativa somente em leitura: hosts `novixfin.cash` e `www.novixfin.cash`, docroot `/srv/sites/novixfin/public`, `/actions/*` usa raiz `/srv/sites/novixfin` e upstream `php:9000`. `/public/*` remove o prefixo; fallback segue o docroot público.
- A rota NovixFin ativa coincide estruturalmente com a adaptação do arquivo em disco. Comparação normalizou apenas identificadores de grupos gerados e o caminho do Caddyfile que o adapter oculta automaticamente.
- Há exatamente os quatro handlers e o bootstrap na pasta de planilhas. Todos os handlers incluem o bootstrap antes da operação. Busca nos diretórios ativos de aplicação e nos crons acessíveis não encontrou outro writer/job de planilhas. Não foram encontrados symlinks na árvore NovixFin. Scripts externos/alterações futuras exigem nova revisão.
- Outros sites compartilham `www`; não matar seus trabalhos. No host, master PID 2054 e workers 4137295, 4137331, 1738235 permaneceram iguais nas observações. Esses são PIDs do host: **não usá-los como baseline dos PIDs internos retornados pela sonda**.
- Duas amostras de sockets no namespace do PHP não tinham conexões TCP estabelecidas; o Caddy mostrava somente uma conexão externa não FastCGI. **Isso não prova ausência de uploads, execução após resposta ou drenagem**. O scoreboard não foi consultado e a métrica HTTP ainda não existe. Nenhuma troca está autorizada por essas amostras.

## Arquivos, backups e preservação

- VPS permanece em `a006bd3856bac9c3b2bacacb10b73da3734a0eff`, branch main adiantada em 19 commits e 117 entradas modificadas/não rastreadas. Nenhum fetch/pull/reset, index refresh deliberado ou alteração da árvore publicada.
- Os seis arquivos conferem byte a byte com `outputs/novixfin-vps-arquivos-20261105.zip`; modo `0664`, UID/GID `1000:1000`, sem symlinks. SHA-256 do Caddyfile em disco: `40f6e5ea50c7a2af6eff13cedf96189b12e313c123c630fc8bce4ff836421595`.
- O pacote local usa os blobs Git de `ccf9ef8`, não a cópia CRLF do worktree Windows. Os hashes são diferentes por conversão de fim de linha; comparar manifesto e bytes do mesmo artefato, sem “corrigir” o manifesto para aceitar o que estiver no servidor.
- Evidência sanitizada em `sheets_production_preflight_evidence_2026-11-05.json`: seis hashes originais/candidatos, estado Git, fingerprint dos arquivos sujos, saúde e resultado da comparação Caddy. Backup exato existente mantido; nenhum backup de banco novo foi produzido nem declarado validado neste preflight.

## Pacote e comandos preparados

O pacote local `outputs/novixfin-preflight-ccf9ef8-20261105.tar.gz` contém seis candidatos exatos, seis originais, controles sem segredo, cinco manifestos de estágios, hashes e Caddyfiles original/candidato. A chave será gerada somente no diretório privado autorizado. Não inclui `.env`, dados de usuários ou credenciais expandidas de outros sites.

O Caddyfile candidato passou por `caddy adapt` local 2.11.4. Foi usado placeholder somente na expansão de uma variável de outro host; nenhum segredo foi transferido. Todos os demais vhosts são estruturalmente iguais. As únicas alterações previstas são a rota 503 por marcador no host NovixFin e métricas HTTP. Isso não substitui `caddy validate` com o ambiente real na janela: essa validação de provisionamento não foi feita na VPS neste escopo.

Comandos exatos e ordem manual em `sheets_publication_window_commands_2026-11-05.md`, complemento do runbook `sheets_first_transition_2026-11-05.md`. A preparação não concede autorização de execução.

## Gates restantes e aborto

1. Autorizar a instalação **somente privada** dos controles e consulta real `status`; nenhuma configuração FPM, script público ou chave em argumentos/logs. Confirmar pool, PID da sonda, geração e todos os campos completos. Falha implica manter bloqueio, sem habilitar status por reload.
2. Confirmar janela exclusiva após estabilização, capacidade suficiente e nenhum deploy/manutenção concorrente. Não herdar autorização do Orcivo.
3. Revalidar artefato, Caddyfile, hashes/permissões, symlinks, configurações e árvore suja no instante da execução; divergência exige parar e comparar, não sobrescrever.
4. Na janela autorizada, validar Caddy com ambiente real e observar a métrica HTTP. Só depois dos três checkpoints e invalidações FPM poderá haver reabertura. Saturação, ausência de métricas, outro worker ativo/Finishing, fila, troca de geração/PIDs ou cache/guarda incorretos: abortar a troca, preservar a contenção já ativada e investigar.
5. Não existe tempo máximo técnico de drenagem. Prazo operacional serve para abortar; relógio, sockets vazios, sumiço de PID e HTTP concluído não provam preservação.
6. Rollback mantém barreira HTTP e bootstrap 503 ao retornar ao backend legado; conserva o banco e as gravações posteriores. Não restaurar o bootstrap legado nem remover a barreira para “voltar ao ar”. Nenhum rollback integral da configuração compartilhada sem comparar alterações posteriores dos outros projetos.

## Checkpoint

Preflight readonly concluído dentro do escopo; gate vivo da sonda pendente. Nenhuma implementação funcional refeita, nenhuma suíte completa repetida. Confiança alta nas comparações estáticas; não há afirmação de drenagem/OPcache vivo comprovados na VPS. A decisão é **BLOQUEADO**, pelos gates ainda não executados, não por uma incompatibilidade técnica demonstrada.
