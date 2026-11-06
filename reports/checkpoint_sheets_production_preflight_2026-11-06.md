# Checkpoint — preflight readonly de produção, 06/11/2026

- Tarefa: finalizar exclusivamente o preflight do candidato funcional `ccf9ef8`, PR #1, sem publicar ou instalar observabilidade na VPS.
- Arquivos: relatório `sheets_production_preflight_2026-11-05.md`, evidência JSON correspondente, comandos `sheets_publication_window_commands_2026-11-05.md`, este checkpoint e entradas de continuidade em `state/context.md`/`state/progress.md`.
- Manutenção de capacidade encerrada no registro Orcivo; saúde estável nas amostras. Swap livre ainda inferior ao gate do deploy Orcivo, que não foi executado nem flexibilizado.
- VPS: mesmos seis arquivos do backup exato, metadados 0664/1000:1000, sem symlinks; conteúdos/modos das 117 entradas sujas e Caddyfile preservados entre observações. Sem SQL, reload, sinais, configuração alterada ou deploy.
- Compatibilidade estática: PHP 8.3.36/FPM nativo, função disponível no binário em serviço, um pool www/9000, mount compartilhado correto; produção dynamic/max5 versus lab static5, e OPcache/métricas descritos no relatório. Parser Caddy local e comparação estrutural aprovados, sem mudança dos outros vhosts.
- Artefatos locais: candidato extraído de blobs Git exatos, originais preservados, cinco manifestos de estágio, controles sem chave e Caddyfile candidato. Publicação/rollback preparados como comandos manuais, sujeitos a checkpoints.
- Decisão: BLOQUEADO para concluir preflight/agendar com todos os gates comprovados. Falta autorização da instalação privada e execução de status no FPM vivo; não é falha técnica demonstrada, mas execução ainda não comprovada. Revalidar capacidade/exclusividade/hashes e validar Caddy com ambiente real na janela.
- Validação: comparação de backups/hash e estabilidade somente leitura na VPS; sintaxe dos blocos shell e integridade do pacote verificadas localmente antes do commit. Suíte funcional/Chrome não repetidos, pois não houve mudança de aplicação.
- Confiança alta nas evidências estáticas; nenhuma afirmação de drenagem ou OPcache vivo comprovados na VPS. Pendência independente: agendamento e publicação sempre exigem autorização expressa adicional.
