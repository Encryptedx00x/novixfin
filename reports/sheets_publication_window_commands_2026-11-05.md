# Comandos preparados — executar somente com autorização separada

Complementa `sheets_first_transition_2026-11-05.md` e o preflight de produção. Candidato funcional fixado em `ccf9ef8`. Em 06/11 foram instalados exclusivamente os dois utilitários privados e a chave; uma consulta `status` foi aprovada. Ver `sheets_probe_approved_2026-11-06.md`. **Os comandos de publicação abaixo não foram executados nem estão autorizados.** Não executar todo o documento de uma vez; cada checkpoint é um gate manual. Falha depois da ativação deixa a contenção ativa.

## 0. Reutilizar a sonda validada; preparar o restante somente na janela autorizada

A instalação mínima já existe em `/srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls`, sem manifesto, barreira ou candidatos. Não repetir a instalação nem gerar outra chave agora. O pacote e seu `.sha256` estão em `outputs` desta conversa; são imutáveis e mantêm os documentos históricos de preparação. Para a próxima janela, prevalece este documento atualizado. Transferir o pacote somente após autorização adicional. O hash esperado é o do arquivo local aprovado; não confiar só em checksum transferido pelo mesmo canal.

```powershell
# No computador local, somente depois da autorização da futura janela:
$out = 'C:\Users\Encryptedx\Documents\Codex\2026-11-04\trabalhe-em-uma-tarefa-por-vez\outputs'
scp "$out\novixfin-preflight-ccf9ef8-20261105.tar.gz" "$out\novixfin-preflight-ccf9ef8-20261105.tar.gz.sha256" ovh:
ssh ovh
```

Dentro de shell root autorizado (`sudo -i`), conferir o hash com o valor local antes de extrair. Reutilizar os controles já verificados, preservando a chave. O pacote não contém chave e a extração não sobrescreve arquivos existentes. Mounts/UID 33:33 e diretórios foram verificados no preflight; qualquer mudança exige parar.

```sh
set -euo pipefail
umask 077
SITE=/srv/sites/novixfin
OPS=/srv/sites/.novixfin-deploy/probe-20261106T040503Z
BUNDLE=/home/ubuntu/novixfin-preflight-ccf9ef8-20261105.tar.gz
cd /home/ubuntu
sha256sum -c novixfin-preflight-ccf9ef8-20261105.tar.gz.sha256
test -d "$OPS/controls"
test -f "$OPS/controls/control.key"
test ! -e "$OPS/candidate"
test ! -L /srv/sites/.novixfin-deploy
echo '30e3a0a5d2eb0cfb003d66c4527918aa33c1bedd7b64f9761c36163932c35058  /srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/fpm_request.php' | sha256sum -c -
echo 'f0b833e53c31b2f07007c46d48b62811e7944539c533372d59518a6748009411  /srv/sites/.novixfin-deploy/probe-20261106T040503Z/controls/fpm_sheets_admin.php' | sha256sum -c -
tar --skip-old-files --no-same-owner -xzf "$BUNDLE" -C "$OPS"
(cd "$OPS"; sha256sum -c payload.sha256)
test -z "$(find "$OPS" -type l -print -quit)"
chown -R root:33 "$OPS"
find "$OPS" -type d -exec chmod 0750 {} +
find "$OPS" -type f -exec chmod 0640 {} +
```

Não criar script público. Não colocar o segredo na linha de comando: só seu caminho. Definir funções na sessão autorizada:

```sh
ctl() {
    timeout 12s docker exec stack-php-1 php "$OPS/controls/fpm_request.php" \
        admin tcp://127.0.0.1:9000 "$OPS/controls/fpm_sheets_admin.php" \
        "$1" "$OPS/controls/control.key"
}
manifest() {
    install -o root -g 33 -m 0640 "$OPS/manifests/$1.json" "$OPS/controls/.manifest.new"
    mv -T "$OPS/controls/.manifest.new" "$OPS/controls/sheets-manifest.json"
}
guard_checks() {
    for ep in load.php save.php snapshot.php restore.php; do
        timeout 12s docker exec stack-php-1 php "$OPS/controls/fpm_request.php" \
            guard tcp://127.0.0.1:9000 "$SITE/actions/sheets/$ep" || return 1
    done
}
replace_file() {
    # Somente estes seis caminhos são permitidos; metadados verificados: 1000:1000/0664.
    case "$1" in
        actions/sheets/_bootstrap.php|actions/sheets/load.php|actions/sheets/save.php|actions/sheets/snapshot.php|actions/sheets/restore.php|public/scripts/sheet.js) ;;
        *) return 1 ;;
    esac
    test -f "$SITE/$1" && test ! -L "$SITE/$1" || return 1
    test ! -e "$SITE/$1.nvx-ccf9ef8.new" || return 1
    install -o 1000 -g 1000 -m 0664 "$2" "$SITE/$1.nvx-ccf9ef8.new" || return 1
    mv -T "$SITE/$1.nvx-ccf9ef8.new" "$SITE/$1"
}
drain() {
    ctl status > "$OPS/pool.current.tmp" || return 1
    mv -T "$OPS/pool.current.tmp" "$OPS/pool.current.json" || return 1
    docker exec stack-caddy-1 wget -qO- http://127.0.0.1:2019/metrics > "$OPS/proxy.metrics.tmp" || return 1
    mv -T "$OPS/proxy.metrics.tmp" "$OPS/proxy.metrics" || return 1
    python3 "$OPS/controls/check_sheets_drain.py" \
        "$OPS/pool.baseline.json" "$OPS/pool.current.json" "$OPS/proxy.metrics"
}
ctl status > "$OPS/pool.baseline.tmp"
mv -T "$OPS/pool.baseline.tmp" "$OPS/pool.baseline.json"
```

Esta coleta nova de baseline é **somente para a futura janela autorizada**; não repetir a consulta de 06/11 nesta etapa. Examinar o JSON: `ok=true`, SAPI FPM, pool `www`, geração/identidades completas, PID da própria sonda em Running. Baseline não prova ociosidade; pode conter trabalho antigo. A sonda deve responder sem alteração de configuração/reload. Erro, saturação ou campos incompletos: manter NO-GO. O timeout pode deixar consulta somente leitura enfileirada; não trocar arquivos nem reiniciar workers.

## 1. Janela de publicação — autorização expressa adicional

Confirmar com o responsável que não há manutenção/deploy concorrente, capacidade estável e aprovação do candidato. Não inferir isso de uptime ou da existência de lockfile. Revalidar status/hash da árvore suja, os metadados e o Caddyfile:

```sh
date -u
df -h /srv
free -m
systemctl --failed --no-pager
sudo lslocks -o PID,COMMAND,PATH
docker inspect stack-php-1 stack-caddy-1 --format '{{.Name}} {{.State.StartedAt}} {{.RestartCount}}'
GIT_OPTIONAL_LOCKS=0 git -C "$SITE" status --short --branch
(cd "$SITE"; sha256sum -c "$OPS/hashes/original.sha256")
echo '40f6e5ea50c7a2af6eff13cedf96189b12e313c123c630fc8bce4ff836421595  /srv/stack/Caddyfile' | sha256sum -c -
test -z "$(find "$SITE" -type l -print -quit)"
for rel in actions/sheets/_bootstrap.php actions/sheets/load.php actions/sheets/save.php actions/sheets/snapshot.php actions/sheets/restore.php public/scripts/sheet.js; do
    test "$(stat -c '%a %u:%g' "$SITE/$rel")" = '664 1000:1000'
done
```

Hashes diferentes, processo/geração/configuração alterados ou novo writer/rota exigem comparação e revisão. Não reconstruir manifestos a partir do estado inesperado. A baseline privada deve ser renovada numa preparação aprovada se passou tempo ou houve churn antes da janela; nunca renovar a baseline entre checkpoints para esconder mudança de geração.

Validar sem aplicar. Verificar todas as diferenças do Caddyfile candidato e ambiente real:

```sh
diff -u "$OPS/Caddyfile.original" "$OPS/Caddyfile.candidate" || test "$?" -eq 1
docker exec stack-caddy-1 caddy validate --config "$OPS/Caddyfile.candidate" --adapter caddyfile
cp -a /srv/stack/Caddyfile "$OPS/Caddyfile.pre-activation"
install -o root -g 33 -m 0640 /dev/null /srv/sites/.novixfin-deploy/sheets.pause
# Preservar inode do arquivo bind-mounted; NÃO substituir Caddyfile por rename.
cat "$OPS/Caddyfile.candidate" > /srv/stack/Caddyfile
docker exec stack-caddy-1 caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
```

Se validate/reload falhar, parar. Não sinalizar/reiniciar FPM nem containers. Confirmar na API administrativa que a configuração ativa é a esperada. O reload acima é **somente Caddy**, já ensaiado; não é o reload FPM reprovado. Métricas HTTP passam a cobrir também outros hosts do mesmo servidor, com pequeno custo de instrumentação; nenhum endpoint de métricas público deve ser criado.

Confirmar barreira para os dois hosts, todos os métodos/handlers; requisições sem autenticação/corpo:

```sh
for host in novixfin.cash www.novixfin.cash; do
    for ep in load.php save.php snapshot.php restore.php; do
        for method in GET HEAD POST PUT PATCH DELETE OPTIONS; do
            if test "$method" = HEAD; then
                code=$(curl -sS --max-time 10 --head -o /dev/null -w '%{http_code}' "https://$host/actions/sheets/$ep")
            else
                code=$(curl -sS --max-time 10 --request "$method" \
                    -o /dev/null -w '%{http_code}' "https://$host/actions/sheets/$ep")
            fi
            test "$code" = 503
        done
    done
done
curl -fsSI --max-time 10 https://novixfin.cash/ > "$OPS/landing.headers"
curl -fsSI --max-time 10 https://novixfin.cash/login.php > "$OPS/login.headers"
# Conferir também os healthchecks dos demais serviços, sem testes de escrita.
drain > "$OPS/checkpoint-1.json"
```

Timeout é falha, nunca aprovação. Prazo operacional sugerido: abortar o checkpoint após 120 s sem estado verificável; esses 120 s não são estimativa de duração do trabalho. Não automatizar passagem de fase por relógio. Outros workers ativos/fila/PIDs mudados ou métricas ausentes impedem a troca.

## 2. Trava e segundo checkpoint

```sh
replace_file actions/sheets/_bootstrap.php "$OPS/controls/sheets_entry_guard.php"
manifest guard-original
(cd "$SITE"; sha256sum -c "$OPS/hashes/guard-original.sha256")
ctl invalidate > "$OPS/invalidate-guard.json"
guard_checks > "$OPS/entry-guard-checks.txt"
drain > "$OPS/checkpoint-2.json"
```

Nenhum handler é substituído antes da segunda prova. Quem carregou o bootstrap anterior termina naturalmente; admissão tardia recebe a trava. Qualquer erro deixa a barreira ativa.

## 3. Cinco arquivos restantes, terceiro checkpoint e bootstrap por último

```sh
for rel in actions/sheets/load.php actions/sheets/save.php actions/sheets/snapshot.php actions/sheets/restore.php public/scripts/sheet.js; do
    replace_file "$rel" "$OPS/candidate/$rel"
done
for ep in _bootstrap.php load.php save.php snapshot.php restore.php; do
    docker exec stack-php-1 php -l "$SITE/actions/sheets/$ep"
done
manifest guard-candidate
(cd "$SITE"; sha256sum -c "$OPS/hashes/guard-candidate.sha256")
ctl invalidate > "$OPS/invalidate-handlers.json"
guard_checks > "$OPS/candidate-entry-checks.txt"
drain > "$OPS/checkpoint-3.json"
replace_file actions/sheets/_bootstrap.php "$OPS/candidate/actions/sheets/_bootstrap.php"
manifest candidate
(cd "$SITE"; sha256sum -c "$OPS/hashes/candidate.sha256")
docker exec stack-php-1 php -l "$SITE/actions/sheets/_bootstrap.php"
ctl invalidate > "$OPS/invalidate-final.json"
ctl inspect > "$OPS/inspect-final.json"
```

Revalidar metadados 1000:1000/0664 e endpoints pelo FPM. Hash em disco e lint CLI não comprovam bytecode servido: exigir sucesso da invalidação no próprio FPM e healthchecks comportamentais. Se `cached_after` não for falso, se a guarda não tiver sido comprovada, ou se a versão final não corresponder ao candidato: manter HTTP 503 e iniciar contenção. Nunca usar `opcache_reset` global.

Reabertura exige conferência dos três checkpoints, configuração e healthchecks. Só então:

```sh
test -f /srv/sites/.novixfin-deploy/sheets.pause
rm -- /srv/sites/.novixfin-deploy/sheets.pause
curl -fsSI --max-time 10 https://novixfin.cash/ > "$OPS/landing-after.headers"
curl -fsSI --max-time 10 https://novixfin.cash/login.php > "$OPS/login-after.headers"
```

QA com conta e registros sintéticos autorizados: load/revisão, 428 sem revisão, 422 inválida, 409 desatualizada, A1 vazia, fórmula sem Enter, salvar/recarga, duas páginas e snapshot/restore. Preservar/copy dados pendentes antes de atualizar. Não executar suíte, factories ou migrações no banco real. Se QA falhar, reativar a contenção e seguir rollback.

## 4. Rollback de contenção, preservando dados

Reativar marcador. Ainda com os arquivos candidatos, provar o primeiro checkpoint, instalar a trava, invalidar e provar o segundo, usando as mesmas funções. Não restaurar o bootstrap legado.

```sh
install -o root -g 33 -m 0640 /dev/null /srv/sites/.novixfin-deploy/sheets.pause
drain > "$OPS/rollback-checkpoint-1.json"
replace_file actions/sheets/_bootstrap.php "$OPS/controls/sheets_entry_guard.php"
manifest guard-candidate
ctl invalidate > "$OPS/rollback-guard-invalidate.json"
guard_checks > "$OPS/rollback-guard-checks.txt"
drain > "$OPS/rollback-checkpoint-2.json"
for rel in actions/sheets/load.php actions/sheets/save.php actions/sheets/snapshot.php actions/sheets/restore.php public/scripts/sheet.js; do
    replace_file "$rel" "$OPS/rollback/$rel"
done
manifest rollback-guard
(cd "$SITE"; sha256sum -c "$OPS/hashes/rollback-guard.sha256")
ctl invalidate > "$OPS/rollback-invalidate.json"
guard_checks > "$OPS/rollback-entry-checks.txt"
drain > "$OPS/rollback-checkpoint-3.json"
```

Em falha parcial, escolher somente o manifesto previamente aprovado que corresponda ao estágio efetivo; se nenhum corresponder, manter HTTP 503 e investigar. Não editar hashes para fazê-los passar. Não restaurar banco nem remover conteúdo posterior ao deploy. **Não remover marcador nem repor bootstrap legado:** ele aceitaria clientes sem revisão. Retomar somente com release estrita homologada.

Não restaurar Caddyfile inteiro de forma cega: outros sites podem ter mudado depois. A configuração com marcador ativo permanece como contenção até uma janela revisada. Nenhum comando de rollback reinicia/recarrega FPM, altera banco ou usa git pull/reset.
