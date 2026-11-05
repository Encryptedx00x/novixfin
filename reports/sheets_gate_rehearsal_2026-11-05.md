# Ensaio de barreira e drenagem — 2026-11-05

**Decisão: AINDA BLOQUEADO.** A barreira foi ensaiada; o rollback foi ensaiado apenas com a barreira mantida. A drenagem de uma requisição instrumentada foi demonstrada no laboratório, mas não é comprovável para todas as requisições antigas na VPS com o estado observado. A remoção de bytecode anterior do PHP-FPM da VPS também não foi demonstrada.

## Evidência e limites

- Caddy 2.11.4, mesmo número de versão da VPS; PHP-CGI FastCGI 8.3.32 para Windows e MariaDB 11.8.8 local. A configuração replica os `handle` relevantes e dois hosts, em portas locais separadas; não é PHP-FPM/Linux nem a configuração completa da VPS.
- Após `caddy validate`, o reload ativou `/actions/sheets/*` → 503. Foram testados GET, POST, PUT, PATCH, DELETE, OPTIONS e HEAD em load, save, snapshot e restore (28 combinações), três variantes de caminho, outras rotas, ação diferente e outro host. A ativação/desativação passou no laboratório; não foi aplicada na VPS. O ensaio não comprova todos os eventuais caminhos alternativos ou acesso direto ao FastCGI na rede de produção.
- A requisição artificialmente lenta adquiriu um lock consultável no banco de **teste**, aguardou um sinal, atualizou uma linha e liberou o lock. Com barreira ativa, o teste observou lock ocupado e recusou trocar arquivos; após resposta, lock liberado e banco=1, permitiu a troca. São evidências para essa requisição instrumentada, não para outros handlers.
- Em outro caso, o cliente desconectou e o PHP continuou ativo e gravou no banco. Métricas somente do proxy ou desaparecimento de conexão não são suficientes para declarar drenagem do PHP. O teste usa um lock cooperativo de laboratório: handlers já em execução na VPS não o adquirem, e a própria liberação da conexão de banco por falha poderia liberar um lock enquanto o PHP ainda executa. Portanto `IS_USED_LOCK` não é um contador global seguro para produção.
- Troca isolada dos seis arquivos: hashes conferidos e modos locais preservados. Foram executadas 33 verificações HTTP de persistência no MariaDB descartável. Teste de falha alterou um arquivo, detectou hash divergente, abortou e restaurou os seis arquivos anteriores mantendo a barreira. O banco de teste conservou as gravações. O backend antigo aceita ausência de revisão: **o acesso não foi reaberto após esse rollback**, de modo que não se demonstrou rollback com retomada segura.
- OPcache estava habilitado no PHP-CGI isolado e `opcache_invalidate` foi aceito, porém não foi comprovado que um bytecode antigo estava armazenado antes da invalidação. O laboratório não prova invalidação do cache compartilhado no FPM de produção. Modos em Windows não provam propriedade/permissões Unix; devem ser checados na VPS após eventual autorização.
- Inspeção de produção somente leitura: PHP-FPM `pm.status_path` e `pm.status_listen` indefinidos; `pm.max_children=5`; `request_terminate_timeout=0s`; `request_terminate_timeout_track_finished=no`; slowlog desativado. CLI informou `opcache.enable=On`, `validate_timestamps=On`, `revalidate_freq=2`; é necessário confirmar configuração efetiva no contexto FPM antes de usar esses valores em decisão. Nenhuma alteração foi feita na VPS.

## Reprodução do laboratório

Requisitos: Windows, Python, PHP CLI e PHP-CGI 8.3 com `pdo_mysql` e DLL de OPcache disponível, Caddy 2.11.4, MariaDB 11.8 local em `127.0.0.1:33316`, dois bancos sintéticos migrados `novixfin_test` e `novixfin_app` (somente local). O teste cria uma tabela de nome aleatório em `novixfin_test` e a remove ao terminar; o teste HTTP cria e remove usuário sintético em `novixfin_app`. Não forneça credenciais ou bancos de produção. As portas 38127, 38128 e 39091 devem estar livres. O processo cria somente a árvore `app` dentro de um diretório novo cujo nome contenha `sheet-drain-lab`; ele se recusa a usá-la se não estiver vazia.

```powershell
# Na raiz do checkout; preencher os caminhos do próprio laboratório.
$env:LAB_DIR = 'C:\CAMINHO\sheet-drain-lab-novo'
New-Item -ItemType Directory -Force $env:LAB_DIR | Out-Null
$env:CADDY_EXE = 'C:\CAMINHO\caddy.exe'
$env:PHP_CGI = 'C:\Users\Encryptedx\AppData\Local\php83\php-cgi.exe'
$env:PHP_EXE = (Get-Command php).Source
$env:MARIADB_EXE = 'C:\CAMINHO\mariadb.exe'
$env:PYTHONIOENCODING = 'utf-8'
python tests/deploy_gate/lab.py
```

A DLL `C:/Users/Encryptedx/AppData/Local/php83/ext/php_opcache.dll` está fixada no script deste laboratório. A execução verifica explicitamente que o banco usa endereço local e nome de teste por configuração embutida; o servidor de aplicação sintético usa `novixfin_app` local. É responsabilidade do operador garantir que essas portas locais não redirecionem para produção. Não execute o teste contra a VPS. O `git archive HEAD` monta o checkout no laboratório; os seis arquivos anteriores vêm da base Git `2a2f976`, que é referência de laboratório, não cópia byte a byte da VPS.

## Pré-condições e procedimento proposto — NÃO EXECUTAR enquanto bloqueado

1. Antes de qualquer publicação autorizada, obter evidência confiável de todos os workers FastCGI que poderiam executar requisições NovixFin, inclusive trabalhos após `fastcgi_finish_request`, processos filhos e acessos que contornem Caddy. Uma possível solução é disponibilizar status completo do pool por listener de administração separado e restrito, de forma controlada e previamente autorizada, validar em homologação sua fidelidade para requisições longas, desconexões e processamento após resposta, e comprovar que **todos** os workers pertinentes estão ociosos e não existem tarefas derivadas. Como esse status está atualmente desativado, e mudanças/reloads no FPM compartilhado não estão autorizados, isso não foi estabelecido. Um contador instalado apenas depois da barreira não enxerga as requisições antigas e não resolve o problema.
2. Também estabelecer procedimento FPM autenticado e restrito de invalidação/verificação de OPcache, validado em um pool equivalente, incluindo checagem comportamental da versão servida. `php` CLI, `opcache_reset()` no CLI, o timestamp dos arquivos, uma chamada de invalidação retornando sucesso isoladamente ou uma espera fixa não comprovam bytecode do FPM. Não reiniciar o FPM compartilhado.
3. Verificar hashes, backup e permissões exatas dos seis arquivos, revisar diff da árvore não commitada, e confirmar inexistência de caminhos alternativos. Preservar o backup anterior e refazê-lo caso os hashes atuais mudem.
4. Somente após autorização e validação em homologação, inserir no host NovixFin, antes de `handle @actions`, a barreira ensaiada:

```caddyfile
@sheet_pause path /actions/sheets/*
handle @sheet_pause {
    respond "Planilha temporariamente indisponível" 503
}
```

Validar o Caddyfile completo com `caddy validate` em cópia controlada; manter outros hosts inalterados e não aplicar se houver discrepância. Depois do reload autorizado, validar cada endpoint e método, além de rotas e hosts não afetados. Drenar pelo mecanismo comprovado na etapa 1. Confirmar sinal verificável de ociosidade de todos os workers pertinentes antes de substituir arquivos. Em qualquer incerteza, manter barreira e abortar a troca; não usar tempo transcorrido como sinal.
5. Com barreira ativa e drenagem demonstrada, trocar os cinco handlers e o JS, preservar dono/grupo/modo, verificar `sha256sum`, validar lint e invalidar/verificar OPcache no contexto FPM, conforme a etapa 2. Somente então retirar a barreira. Com conta QA autorizada, testar load e revision, 428 para cliente legado, 422 para revisão malformada, 409 para conflito, salvar A1 e recarregar, snapshot/restore e duas abas; observar logs sanitizados. Não gravar dados reais.
6. Em falha, manter ou reinstalar a barreira e demonstrar drenagem antes de restaurar os seis arquivos exatos do backup com hashes, propriedades e OPcache verificados. **Não reabrir o backend antigo enquanto aceita escritas sem revisão.** É necessário manter a planilha indisponível até uma mitigação aprovada e validada que impeça clientes sem revisão de escrever, ou prosseguir com uma correção compatível já homologada. Coordenar usuários e preservar dados locais pendentes. Não restaurar banco por padrão, preservando gravações posteriores.

## Tempo de espera e abortos

`request_terminate_timeout=0s` não estabelece um máximo de duração: **não há tempo máximo de drenagem tecnicamente comprovado**. Um prazo operacional escolhido pelo responsável (por exemplo, 60 segundos) pode apenas disparar aborto/escalonamento; nunca autoriza a troca. Abortar em status indisponível ou inconclusivo, worker ativo, divergência de hash, validação da barreira ou OPcache malsucedida, tráfego alternativo não controlado ou erro em QA. Nenhum procedimento aqui autoriza alterar Caddy, FPM, banco de produção ou fazer deploy.

## Comandos de verificação somente leitura na VPS

Os comandos abaixo não ativam a barreira e não demonstram drenagem. Foram usados para identificar a configuração atual; executar novamente antes de qualquer decisão e comparar com o manifesto do backup.

```sh
ssh ovh 'cd /srv/sites/novixfin && git status --short -- actions/sheets public/scripts/sheet.js && sha256sum actions/sheets/_bootstrap.php actions/sheets/load.php actions/sheets/save.php actions/sheets/snapshot.php actions/sheets/restore.php public/scripts/sheet.js && stat -c "%a %u:%g %n" actions/sheets/_bootstrap.php actions/sheets/load.php actions/sheets/save.php actions/sheets/snapshot.php actions/sheets/restore.php public/scripts/sheet.js'
ssh ovh 'docker exec stack-caddy-1 caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile'
ssh ovh 'docker exec stack-php-1 php-fpm -tt 2>&1 | grep -E "(pm.status|request_terminate_timeout|pm.max_children)"'
```

A validação do Caddyfile **atual** retornou `Valid configuration`; isso não valida uma futura alteração, que precisará ser testada em cópia. A documentação oficial descreve [métricas do Caddy](https://caddyserver.com/docs/metrics); o medidor de requisições no proxy não resolve, por si só, o caso de PHP continuar após desconexão comprovado no laboratório.
