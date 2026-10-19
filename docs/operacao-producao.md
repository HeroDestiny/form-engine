# Operação em produção

Atualizado em 2026-10-17. Os arquivos desta etapa foram preparados para Docker Compose em um único servidor, com TLS terminado em um proxy do host. O ensaio local usa projetos, redes, credenciais e volumes descartáveis. Ele não publica a aplicação nem valida o certificado, o DNS, a capacidade e os alertas do servidor definitivo.

## Artefatos e pré-requisitos

Os perfis de desenvolvimento, testes, produção e restauração fixam PostgreSQL `17.11-alpine`; servidor e ferramentas de backup/restauração usam a mesma versão. Desenvolvimento e produção fixam Redis `7.4.11-alpine`. Antes de atualizar serviços com dados, gere e valide um backup e ensaie sua restauração isolada; não remova volumes para aplicar atualizações de patch.

- `docker-compose.production.yml`: stack independente da configuração de desenvolvimento; código e dependências ficam nas imagens, sem montagem dos fontes do host.
- `.env.production.example`: modelo de configuração. Crie `.env.production` fora do controle de versão, com permissões restritas. Não use senhas ou a chave de desenvolvimento.
- `scripts/ops.ps1`: comandos operacionais; exige PowerShell 7 e Docker Compose v2.
- `scripts/validate-production.ps1`: ensaio destrutivo **somente para o projeto descartável que o próprio script cria**, sem utilizar volumes do desenvolvimento ou da produção. Exige a porta local 18080 livre, ou `-HttpPort` alternativo.

O PostgreSQL cria um usuário de aplicação sem superusuário, sem criação de roles ou databases, com criação de objetos no schema `public` para migrations. As credenciais de administração são separadas. O script de inicialização atua apenas em um volume novo: alterar o `.env.production` não troca automaticamente senhas de um banco existente. O Redis tem timeouts explícitos de conexão/leitura (1 s) e uma repetição na produção, exige senha, persiste AOF e usa `noeviction`, evitando expulsar contadores do limitador de login; falta de memória deve gerar alerta operacional.

As imagens PHP e web são identificadas por `RELEASE_TAG`. Use o SHA do commit e mantenha as imagens anteriores para rollback. O PHP executa como `appuser`, usa configuração de produção e dependências sem `require-dev`; o frontend é compilado e servido pelo Nginx. Os comandos Composer/Node rodam durante o build. Dependências transitivas podem atualizar de major quando permitido pelo framework, mas as restrições diretas do projeto e o suporte a PHP 8.3 são preservados pelo lockfile.

PostgreSQL e Redis não publicam portas; a rede dos serviços é interna. A porta HTTP da aplicação é publicada somente em `127.0.0.1:8080`. O proxy TLS deve rodar no mesmo host e encaminhar para essa porta. O Compose não inclui worker de filas: `QUEUE_CONNECTION=sync` corresponde ao backend atual, que não depende de jobs em segundo plano.

## Primeiro deploy

1. Defina servidor, domínio, certificado TLS, disco para dados/temporários/backups, destino externo de backup e responsável pelos alertas. Proteja `.env.production` e guarde uma cópia segura da `APP_KEY`, fora do servidor de aplicação. Não a regenere em atualizações.
2. Preencha todos os valores do modelo. Gere uma chave de 32 bytes em base64 e senhas aleatórias com ao menos 16 caracteres. Configure `APP_URL=https://seu-dominio` e `RELEASE_TAG` com o SHA do release.
3. Execute, na raiz do checkout escolhido:

```powershell
docker compose --env-file .env.production -f docker-compose.production.yml config --quiet
docker compose --env-file .env.production -f docker-compose.production.yml build
./scripts/ops.ps1 -Action preflight
docker compose --env-file .env.production -f docker-compose.production.yml up -d --wait postgres redis
docker compose --env-file .env.production -f docker-compose.production.yml run --rm --no-deps backend php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.production.yml up -d --wait backend nginx
./scripts/ops.ps1 -Action health
```

O entrypoint gera caches de configuração e rotas em cada container. Migrations são explícitas, nunca executadas automaticamente ao reiniciar o FPM. O preflight valida ambiente, debug desativado, URL HTTPS, chave, PostgreSQL, cache compartilhado, senhas e logs em stderr; não testa a validade real do certificado nem imprime os segredos. Não execute o seeder de demonstração em produção. O primeiro administrador deve ser provisionado de forma controlada, com credenciais próprias.

Use `docker/nginx/tls-proxy.example.conf` como base para o proxy do host, substituindo domínio e caminhos de certificados, e valide a configuração antes de recarregar. O modelo contém redirecionamento HTTP→HTTPS, HSTS e limite de corpo. Certificado, renovação e resolução DNS dependem do servidor e não foram instalados nesta etapa.

O Nginx interno tem endereço fixo `PROXY_ADDRESS=172.30.240.2` na rede `PRIVATE_SUBNET=172.30.240.0/24`; os demais serviços usam a faixa dinâmica `PRIVATE_DYNAMIC_RANGE=172.30.240.128/25`, que exclui esse endereço. O Laravel confia somente no endereço do Nginx para `X-Forwarded-For` e `X-Forwarded-Proto`. Se a sub-rede conflitar com outra rede local, ajuste os três valores em conjunto antes de subir o Compose, mantendo o proxy fora da faixa dinâmica. O proxy TLS do host deve **substituir**, como no exemplo, os cabeçalhos recebidos pelo IP e protocolo da conexão real. A porta interna permanece limitada ao loopback: expô-la publicamente permitiria que clientes falsificassem o IP encaminhado pelo Nginx confiável. Fora deste Compose, `TRUSTED_PROXIES` é uma lista explícita de endereços/CIDRs separados por vírgula, vazia por padrão. Cookies stateful, se introduzidos, também exigirão configuração dos domínios correspondentes.

## Atualização e rollback

Antes da atualização, execute um backup e seu teste de restauração. Registre o SHA/tag dos dois artefatos, estado das migrations e arquivo de configuração utilizado. Construa as novas imagens antes da janela de manutenção.

Para um servidor único, use janela de manutenção: coloque o backend atual em manutenção, pare o Nginx da aplicação, execute as migrations com a imagem nova e recrie backend/Nginx com `--wait`. Valide `/ready`, login, isolamento, envio de formulário e exportação antes de reabrir o proxy. Essa estratégia tem indisponibilidade; não é um deploy sem interrupção. Manutenção do container antigo não é compartilhada com um container novo, por isso o Nginx permanece parado durante a troca.

Se a nova aplicação falhar e o schema continuar compatível, retorne `RELEASE_TAG` ao par de imagens anterior e recrie backend/Nginx com `--no-build`. Preserve o checkout/configuração compatível com esse release. Não execute `migrate:rollback` automaticamente: migrations podem remover dados ou estruturas. Se houver incompatibilidade de schema, restaure o backup em um **banco novo** ou outro projeto/volume, valide-o isoladamente e altere a configuração da aplicação para esse destino. Essa recuperação perde alterações posteriores ao backup; o banco anterior deve ser preservado para análise e reconciliação. Nunca use `down --volumes` na stack real como procedimento de atualização/rollback.

## Backup, integridade e restauração

```powershell
./scripts/ops.ps1 -Action backup
./scripts/ops.ps1 -Action restore-check -BackupFile form-engine-AAAAmmddTHHMMSSZ-XXXXXX.dump
```

O backup é um `pg_dump` em formato custom, com nome único, arquivo temporário, verificação do catálogo por `pg_restore --list`, renomeação após sucesso e manifesto SHA-256. Não usa redirecionamento binário do PowerShell. Os arquivos são criados em `backups/`, ignorado pelo Git, com `umask 077` dentro do container; no Windows, configure também ACLs do diretório no host. O checksum detecta corrupção, não autentica origem contra adulteração: controle acesso ao arquivo e ao manifesto.

`restore-check` aceita apenas um nome de arquivo dentro desse diretório, verifica o checksum e restaura em uma única transação no database `form_engine_restore_check`, em rede/instância temporária sem portas publicadas. Verifica tabelas e contagens, presença do trigger de auditoria e rejeição real de UPDATE/DELETE em uma ação de prova, revertida depois do teste. O projeto de restauração é exclusivo a cada execução e é removido ao terminar. Não existe comando de restauração destrutiva sobre o banco ativo nestes scripts.

Para recuperação real, pare as escritas, crie um destino novo e vazio e use a versão compatível do cliente PostgreSQL. Restaure com `pg_restore --exit-on-error --single-transaction --no-owner --no-privileges --dbname=DESTINO_NOVO arquivo.dump`; utilize a role de aplicação que deve possuir os objetos, ou ajuste a propriedade de forma controlada. Verifique contagens, relacionamentos, migrations e invariantes da auditoria, configure a aplicação para o novo destino e execute o smoke test antes de permitir novas escritas. Não restaure por cima de tabelas existentes nem desative os triggers de auditoria para contornar erros.

Política inicial a confirmar com o responsável pela operação: backup diário, 14 cópias diárias e cópias externas criptografadas; ensaio de restauração pelo menos mensal e antes de mudanças de schema. RPO de até 24 horas só é plausível se essa frequência e as cópias externas forem efetivamente cumpridas; RTO deve ser medido com volume real. Para menor RPO, será necessário WAL/PITR e uma configuração adicional.

Exemplo de agendamento no servidor Linux, após instalar PowerShell 7 e ajustar caminhos/permissões:

```cron
0 2 * * * cd /srv/form-engine && pwsh -NoProfile -File scripts/ops.ps1 -Action backup >> /var/log/form-engine-backup.log 2>&1
```

No Windows, use o Agendador de Tarefas com `pwsh -NoProfile -File C:\caminho\form-engine\scripts\ops.ps1 -Action backup`, no contexto de uma conta com acesso ao Docker e aos arquivos de configuração. Agendamento, retenção, criptografia e transferência externa não são ativados automaticamente pelos scripts. Não remova backups antes de confirmar cópia externa e restauração válida. A cópia do banco não inclui `APP_KEY`, configuração, certificados ou futuros arquivos enviados; esses itens precisam de política própria. Dados atuais de formulário ficam no PostgreSQL; Redis é cache/sessão/limitador e não é a fonte desses dados.

## Saúde, logs e alertas

A conexão PostgreSQL recebe `PGCONNECT_TIMEOUT=3`, conforme o parâmetro de conexão da [libpq](https://www.postgresql.org/docs/17/libpq-connect.html). O Redis usa limites explícitos de conexão/leitura para que uma dependência silenciosa não retenha o check até o timeout do proxy; resolução DNS e condições reais de rede ainda precisam ser medidas no destino.

| Sinal | Comportamento e ação |
|---|---|
| `GET /up` | Liveness do Laravel/FPM, sem depender de sessão ou banco. Serve ao healthcheck do Nginx. |
| `GET /ready` | 200 ou 503; verifica SELECT 1, escrita/leitura/exclusão de uma chave temporária no cache e arquivo temporário gravável. É stateless, não expõe senha, host ou stack trace e retorna `Cache-Control: no-store`. |
| `php artisan app:health` | Mesmas verificações, com exit code 0/1. Usado pelo healthcheck do backend. |
| Logs | Laravel em stderr; Docker `json-file` com rotação de 10 MiB × 3 por serviço. Auditoria de negócio permanece no PostgreSQL. |
| Disco | Monitorar volumes PostgreSQL/Redis, temporários do PHP e backups; exportações são preparadas em disco. |

Configure no monitor real uma consulta a `/ready` a cada 60 segundos, alerta após três falhas consecutivas e aviso de recuperação. Monitore também idade do último backup (alerta acima de 26 horas para política diária), exit codes dos backups/restaurações, erros 5xx, latência, disco, memória e reinícios de containers. O monitor deve rodar fora do host e usar o canal de alerta definido pela operação. Healthcheck do Docker não envia notificações e não reinicia um processo apenas por estar `unhealthy`.

O endpoint `/ready` público retorna somente booleanos, é limitado no Nginx a 5 requisições/segundo por origem e pode ser restrito no proxy ao monitor escolhido. A verificação usa uma chave aleatória com TTL curto e não remove dados de usuários. Uma falha é registrada apenas pelo nome do check; não são publicados detalhes de conexão. Revisar o volume de avisos durante indisponibilidade e a retenção do coletor externo de logs.

## Validação reproduzível e limites da conclusão

```powershell
./scripts/validate-production.ps1
```

O ensaio constrói as imagens, inicia um banco novo, executa preflight e migrations, confere SPA/login/liveness/readiness/acesso anônimo recusado, cria dados sintéticos, gera e restaura backup e simula indisponibilidade/retorno do Redis com pause/unpause do container. Remove apenas seus próprios containers, volumes, credenciais temporárias e backup sintético. O mesmo ensaio integra o workflow de CI. Testes de API em SQLite/PostgreSQL continuam separados dos dados de desenvolvimento.

A conclusão desta etapa cobre os artefatos operacionais e o ensaio local. A liberação real ainda depende de servidor/domínio/TLS, armazenamento e cópias externas, agendamento, canal de alertas, medição de carga/recuperação e execução do workflow remoto com estas alterações. Não há ambiente público provisionado por esta mudança.
