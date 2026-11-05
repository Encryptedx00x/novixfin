# Checkpoint — ativação inicial do observador (2026-11-05)

- O laboratório começou sem status FPM. Duas requisições executavam após fastcgi_finish_request quando o container exclusivo recebeu USR2 para carregar o status. Foram acompanhados PID+start_ticks antes/depois; a geração antiga desapareceu sem executar as duas gravações esperadas. A aplicação do observador por reload durante trabalho pós-resposta foi REPROVADA neste ensaio. Nenhum arquivo de aplicação foi substituído antes de esse estágio terminar.
- Depois da ativação isolada, o ensaio de fila, workers Finishing, uploads antigos, desconexão, troca dos seis arquivos e OPcache efetivo passou novamente; 33 verificações HTTP/MariaDB passaram. Rollback manteve barreira e dados posteriores.
- Evidência: reports/sheets_fpm_bootstrap_evidence_2026-11-05.json. O modo LAB_BOOTSTRAP é um controle negativo, não uma recomendação operacional. O laboratório também recusa Docker remoto e execução no hostname da VPS.
- Confiança: 90% nas conclusões do ambiente testado; não autoriza equivalência com pool compartilhado nem ativação na VPS. Restam documentação final e atualização do PR. Sem operações de escrita na VPS.
