# Primeira transição sem reload do PHP-FPM — procedimento vigente

## Decisão e comparação

A opção selecionada é **sonda privada com `fpm_get_status()` + barreira HTTP + trava temporária na entrada PHP**, em duas etapas de admissão. Ela não precisa de `pm.status_path`, listener novo, mudança de configuração, sinal ou reinício do FPM. A sonda ocupa uma requisição normal curta; se não houver worker disponível, expira e o procedimento aborta sem liberar a troca.

| Alternativa | Preservação dos trabalhos antigos | Outros sites | Reversibilidade | Comprovação |
|---|---|---|---|---|
| Reload do FPM para instalar status | Reprovado no ensaio anterior; não repetido nesta etapa | Atingiria o pool compartilhado | Exige novo ciclo operacional | Descartado: 0/2 gravações pós-resposta no controle anterior |
| Pool dedicado + release imutável, isoladamente | Evita arquivos misturados, mas não prova fim dos writers no pool antigo | Bom isolamento futuro | Pode voltar a rota, mas writers antigos continuam existindo | Não resolve sozinho o primeiro corte; não adotado |
| Revogação de permissões, encerramento de sessões ou fence SQL | Exigiria alterações no banco e tratamento de operações antigas rejeitadas | Maior risco conforme compartilhamento de credenciais | Mais complexa e dependente de grants/DDL | Não adotado nem aplicado |
| Sonda no pool existente, sem trava de entrada | Observa PHP antigo, mas pode não enxergar ainda um corpo HTTP retido no proxy | Sem alteração de pool | Simples | Insuficiente isoladamente |
| **Sonda + trava de entrada PHP em duas etapas** | **Drena trabalho admitido sem interrompê-lo; novas admissões recebem 503** | **Só a entrada das planilhas e uma consulta normal no pool** | **Mantém 503 como contenção; banco não é restaurado** | **Selecionada e ensaiada em FPM Linux** |

A função nativa retorna o scoreboard completo do pool no contexto FPM, sem depender da página de status configurada: [manual PHP](https://www.php.net/manual/en/function.fpm-get-status.php). A prova operacional decorre do ensaio deste PR, incluindo o PID da própria sonda. Não se subtrai cegamente 1 do contador: o PID tem que existir no snapshot, estar `Running`, ser a própria sonda, e todos os demais workers precisam estar `Idle`, sem fila. Geração e identidades dos workers devem corresponder à linha de base.

## Por que são necessárias duas etapas

Um upload antigo pode estar no Caddy e ainda não ter entrado no PHP. Por isso, um snapshot ocioso não autoriza trocar todos os handlers de uma vez. O protocolo fecha a admissão no primeiro `require` comum aos quatro endpoints: `_bootstrap.php` passa temporariamente a conter somente `sheets_entry_guard.php`, que responde JSON/503 e encerra antes de sessão, banco ou lógica da planilha.

1. Aguardar ociosidade inicial sem sinalizar workers. Instalar atomicamente apenas a trava de entrada e invalidar pelo FPM o bytecode alvo. Ainda não trocar os demais handlers.
2. Quem já carregou o bootstrap antigo conserva seu código em execução e termina naturalmente. Quem chegar depois recebe 503. Repetir a prova de drenagem; só então substituir handlers e JS mantendo a trava. Invalidar/verificar e drenar novamente ainda com a trava ativa. Colocar o bootstrap candidato por último, invalidar e verificar a versão final.

O ensaio insere deliberadamente um writer já admitido na janela entre a primeira observação e a trava. Ele grava com sucesso antes de os handlers serem trocados. Um POST legado autenticado cujo corpo começou antes da primeira barreira termina o upload depois da trava e recebe 503, sem SQL. Isso cobre a lacuna que uma simples contagem momentânea deixaria.

O protocolo depende da estrutura auditada: load/save/snapshot/restore carregam `_bootstrap.php` antes de qualquer operação sobre planilhas. Novos endpoints que gravem fora dessa entrada, jobs que incluam esses arquivos, preload ou múltiplos pools devem ser inventariados antes de aplicar. Qualquer discrepância é NO-GO, não adaptação improvisada.

## Pré-condições de execução — produção ainda não alterada

- Conclusão da manutenção Orcivo e estabilização confirmadas pelo responsável. Uptime ou passagem de tempo não substituem essa confirmação.
- Autorização expressa para a janela e para a publicação. Nenhuma operação deste documento foi executada na VPS.
- Revalidar hashes e backups exatos dos seis arquivos da árvore suja; se divergirem, parar, preservar a nova versão e comparar. Nunca usar pull/reset ou substituir a pasta inteira.
- Confirmar versão/configuração do PHP alvo e disponibilidade de `fpm_get_status()` pela sonda privada. Indisponibilidade, falta de capacidade, retorno incompleto, restart inesperado, mudança de geração ou workers não explicada: abortar. Não alterar `pm`, disable_functions ou qualquer configuração global para fazer o teste passar.
- Confirmar um único pool relevante e ausência de entradas que contornem o bootstrap auditado. As requisições de outros sites não serão terminadas; se impedirem a observação ociosa, abortar ou adiar.
- Preparar em diretório privado, fora de TODOS os webroots e com nome de execução único: `fpm_request.php`, `fpm_sheets_admin.php`, `control.key` aleatória de pelo menos 64 caracteres, `sheets-manifest.json` e o verificador Python. Segredo/manifesto graváveis somente pelo administrador, legíveis pelo FPM quando necessário. Não publicar endpoint de status ou controle HTTP. O status dispensa manifesto, mas exige a chave.
- Preparar manifestos esperados por estágio: original, trava+originais, trava+cinco candidatos, seis candidatos e rollback com trava. Cada manifesto usa a ordem dos seis caminhos fixos do controle e root `/srv/sites/novixfin`. Não gerar hashes a partir de arquivos inesperados para simplesmente aceitá-los.

## Comandos e checkpoints para a janela autorizada

Os caminhos abaixo são exemplos definidos para a execução; `$OPS` fica no mount já visível `/srv/sites/.novixfin-deploy/<run-id>`, fora de qualquer webroot. `$STAGE` contém o commit aprovado; `$SITE=/srv/sites/novixfin`. Antes de cada alteração, confirmar os caminhos absolutos e os hashes esperados. A preparação do diretório, chave, manifestos e cópias deve preservar as permissões e o dono/grupo reais.

### A. Sonda no pool existente — nenhuma configuração FPM

```sh
# Executar dentro da janela aprovada; não expor chave em argumentos ou logs.
docker exec stack-php-1 php "$OPS/fpm_request.php" admin tcp://127.0.0.1:9000 "$OPS/fpm_sheets_admin.php" status "$OPS/control.key" > "$OPS/pool.baseline.json"
# Repetir para uma observação atual; erro/timeout/JSON incompleto => ABORTAR.
docker exec stack-php-1 php "$OPS/fpm_request.php" admin tcp://127.0.0.1:9000 "$OPS/fpm_sheets_admin.php" status "$OPS/control.key" > "$OPS/pool.current.json"
```

A sonda lê os workers que já existem, incluindo os anteriores à cópia da própria sonda. Ela não instala contador novo nem reconfigura o pool. O status sanitizado omite URI e usuário. Se o pool estiver cheio, a consulta pode expirar; isso nunca significa drenagem. Uma sonda enfileirada que execute mais tarde é somente leitura.

### B. Barreira HTTP controlada

Preparar cópia exata do Caddyfile com alteração somente no host NovixFin e ativação de métricas administrativas restritas. Validar a configuração inteira antes de aplicar. A adição inicial usa somente reload de configuração do **Caddy**, sem reiniciar seu container nem o FPM; as próximas alternâncias usam o marcador e não fazem reload.

```caddyfile
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

Criar `sheets.pause` antes de ativar a configuração inicial. Confirmar 503 em todos os métodos/endpoints e funcionamento dos demais hosts/rotas. Não interpretar as novas métricas como observação retroativa de toda a geração anterior; a trava PHP adiante protege contra admissões HTTP tardias. Nenhum arquivo da aplicação é substituído nessa fase.

A observação atual deve ser nova, da geração registrada e sem outro worker ativo. O medidor de subrota disponível deve estar zerado; ausente ou ativo aborta a checagem. O checker não autoriza deploy por si só e não substitui a confirmação da trava PHP:

```sh
# pool.current.json e proxy.metrics precisam ser recém-coletados (limite de 10s).
python3 "$OPS/check_sheets_drain.py" "$OPS/pool.baseline.json" "$OPS/pool.current.json" "$OPS/proxy.metrics"
```

Coletar métricas pela API administrativa interna do Caddy; não abrir `/metrics` publicamente. Se houver tráfego de outros sites na métrica agregada, a verificação pode ser conservadora e adiar a operação. Não retirar proteção para contornar isso.

### C. Instalar a trava de admissão, somente após a primeira prova

```sh
# Confirmar os valores de owner/group/mode a partir do backup; 1000:1000/0664 eram os observados.
install -m 0664 -o 1000 -g 1000 "$STAGE/scripts/deploy/sheets_entry_guard.php" "$SITE/actions/sheets/.bootstrap.guard.new"
mv -T "$SITE/actions/sheets/.bootstrap.guard.new" "$SITE/actions/sheets/_bootstrap.php"
# Usar manifesto TRAVA + demais arquivos ORIGINAIS, conferido previamente.
docker exec stack-php-1 php "$OPS/fpm_request.php" admin tcp://127.0.0.1:9000 "$OPS/fpm_sheets_admin.php" invalidate "$OPS/control.key"
# Verificar a trava diretamente no FPM, sem depender da barreira HTTP.
for endpoint in load.php save.php snapshot.php restore.php; do
    docker exec stack-php-1 php "$OPS/fpm_request.php" guard tcp://127.0.0.1:9000 "$SITE/actions/sheets/$endpoint" || exit 1
done
```

O controle valida todos os hashes esperados antes de invalidar somente os cinco PHPs alvo. Trabalhos que já carregaram o bootstrap antigo continuam; não são terminados. Repetir as sondas e a checagem até todos os demais workers estarem ociosos e fila vazia. **Essa segunda prova é obrigatória.** Se não ocorrer dentro do limite operacional, abortar a publicação, preservar a barreira e avaliar contenção. Nunca matar workers ou reiniciar o pool para atingir o estado esperado.

### D. Trocar os cinco arquivos restantes sob a trava

Com a segunda prova e a trava confirmadas, instalar por temporários completos e renomeação atômica, no mesmo filesystem, apenas `load.php`, `save.php`, `snapshot.php`, `restore.php` e `public/scripts/sheet.js`. Revalidar hashes/permissões e lint. Não mudar `_bootstrap.php` ainda.

Ativar o manifesto TRAVA + cinco candidatos e invalidar pelo FPM. Repetir os quatro checks privados de guarda e a drenagem. **Essa terceira prova evita liberar o bootstrap candidato enquanto algum handler anterior ainda executa a trava.**

Só então colocar o `_bootstrap.php` candidato completo, atomicamente, ativar o manifesto final e invalidar pelo FPM outra vez. Verificar hashes/SAPI/cache e comportamento esperado. Não invalidar pelo CLI nem usar reset global. Qualquer falha deixa a barreira HTTP ativa; não tentar publicar um conjunto parcial.

### E. Reabertura e QA

Remover `sheets.pause` somente após a autorização, os três checkpoints e a verificação final. Confirmar com conta QA autorizada load com revisão, 428 sem revisão, 422 para formato inválido, 409 para conflito, A1 inicialmente vazia, persistência e recarga, abas, snapshot/restore e duas páginas. Usar logs sanitizados e preservar a privacidade das cópias pendentes. Não executar factories ou migrações de testes sobre o banco real.

### F. Rollback

Reativar a barreira HTTP e executar a sequência de trava PHP + drenagem. Restaurar os cinco arquivos de backend/UI restantes do backup exato sob a trava; conferir manifestos/permissões/cache. Se o destino for o backend legado, **manter `_bootstrap.php` como trava 503**, em vez de recolocar o bootstrap antigo que aceitaria escritas sem revisão. O backup integral original continua preservado; a divergência deliberada da trava deve ficar registrada no manifesto de contenção.

Não reabrir a planilha legada. A retomada exige release estrita conhecida e verificada, pela mesma sequência. Não restaurar o banco por padrão: os testes conferem explicitamente que uma gravação feita depois da publicação sobrevive ao rollback de código. Outros sites e seus dados não fazem parte desse procedimento.

## Evidência e limites

- PHP-FPM 8.3.36, Caddy 2.11.4, MariaDB 11.8.8 locais, dados sintéticos; mesmo FPM/master, mesma geração/PIDs e mesmo hash de configuração do começo ao fim. Nenhum sinal ao FPM, nenhum listener/página de status configurados.
- Writer admitido antes da trava conclui; POST autenticado com upload iniciado antes da barreira recebe 503 depois da trava e não modifica dados. Saturação expira em vez de liberar troca; as cinco gravações antigas concluem naturalmente após a liberação da fixture.
- Verificações de OPcache no FPM e 33 verificações HTTP/MariaDB preservadas. Rollback mantém a trava e conserva a planilha gravada na release nova.
- A confirmação de manutenção encerrada e a autorização operacional continuam necessárias; nenhum comando deste documento foi executado na VPS.
- Não existe tempo máximo tecnicamente garantido para os trabalhos desconhecidos. Os 5s da conexão de controle, 10s de frescor das amostras e deadlines do teste são limites de aborto/validade de observação, nunca prova de conclusão.
- Verificar em produção que cada endpoint passa pela entrada comum antes do SQL. Auto-preload, múltiplos pools, extensões diferentes, caminhos alternativos ou geração/PIDs não confiáveis são NO-GO até revisão. Se `fpm_get_status()` não existir ou falhar, não reconfigurar o pool: abortar.

**GO técnico para o procedimento comprovado, condicionado à equivalência dessas pré-condições. NO-GO para execução imediata durante a manutenção ou sem autorização expressa.** O reload FPM anterior continua reprovado e não integra esta solução.

## Reprodução isolada e conclusão desta etapa

```sh
# Somente em Linux LOCAL; Docker remoto e hostname da VPS são recusados.
docker build -f tests/deploy_gate/Dockerfile.fpm -t novixfin-fpm-lab:8.3.36 tests/deploy_gate
NOVIXFIN_LOCAL_LAB=YES LAB_STATUS_FUNCTION=YES CADDY_EXE=/caminho/caddy python3 tests/deploy_gate/fpm_lab.py
```

Caddy deve ser 2.11.4; PHP-FPM 8.3.36 e MariaDB 11.8.8 locais, portas de laboratório livres. O código de laboratório não é um comando de publicação e nunca deve ser executado na VPS. Em WSL com worktree Windows, usar `LAB_SOURCE_ZIP` e `LAB_BASELINE_ZIP` conforme o relatório anterior. O modo histórico `LAB_BOOTSTRAP=YES` agora é recusado antes de qualquer chamada ao Docker; não repetir o reload reprovado.

A evidência atual está em `reports/sheets_first_transition_evidence_2026-11-05.json`: 3/3 writers da primeira fase preservados; saturação bloqueou a sonda sem interromper cinco outros trabalhos; o writer admitido na janela da trava concluiu; quatro probes FastCGI privados confirmaram a trava; configuração FPM e PIDs permaneceram iguais; nenhuma lista de sinais FPM foi enviada; planilha gravada na release nova permaneceu após rollback. Os controles de OPcache e as 33 verificações HTTP/MariaDB passaram. O comparador de observações aceitou amostra ociosa real e recusou worker ocupado, métrica ausente e proxy ativo.

A implementação funcional dos seis arquivos não foi refeita nesta etapa; por isso não se repetiu indiscriminadamente a suíte completa ou o Chrome. A validação direcionada exercitou a nova infraestrutura e os endpoints reais. Os dados permaneceram sintéticos e o banco descartável. Os contêineres exclusivos foram removidos por identificação e label da execução, sem reiniciar outros serviços.

Antes da janela, rejeitar também alvos com symlinks, root diferente do manifesto, preloading que mude a resolução do código ou credenciais/permissões que permitam alteração do controle privado por usuário não administrativo. Esses casos exigem nova análise, não ajustes durante o corte.

### Checkpoint de conclusão

Arquivos desta etapa: controles privados e trava em `scripts/deploy/`, ensaio em `tests/deploy_gate/fpm_lab.py`, evidência JSON e este runbook. Validação dirigida descrita acima; compilação/lint e diff sem erros; modo de reload histórico recusado antes de Docker. Confiança: 90% no protocolo/ambiente ensaiado. Pendências operacionais: equivalência de pré-condições na VPS, confirmação de estabilização da manutenção e autorização expressa. Nenhuma ação sobre outros projetos ou dados de produção.
