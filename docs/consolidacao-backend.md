# Consolidação funcional do backend — 10/10/2026

## Etapas implementadas

1. Auditoria automática em gestão de tenants/usuários, formulários/campos/versões, submissões, drafts, exportação e login. Recusas 403/404 de usuários autenticados também são registradas. Logs contêm ator, tenant, entidade, metadados e contexto da requisição, sem senha, token ou respostas do formulário. Tentativas de login sem conta identificável ficam sem tenant/ator, sem atribuí-las arbitrariamente a uma unidade.
2. Atualização e finalização de draft existente. Apenas o proprietário pode escrever, mesmo que outro usuário seja administrador do mesmo tenant. Finalização mantém o ID e a versão original, valida obrigatórios e atualiza status/data atomicamente.
3. Nova versão preserva campos/opções/ordem e histórico. Criação bloqueia a linha do formulário; publicação e alteração de campos bloqueiam a versão e conferem o estado dentro da transação. Um índice parcial impede dois drafts de versão para o mesmo formulário. Essa restrição é distinta dos múltiplos rascunhos de preenchimento permitidos por usuário.
4. CSV gerado com `fputcsv`, BOM UTF-8 e CRLF, incluindo escaping de cabeçalhos e valores. Campos copiados são combinados por `name`, com label da versão mais recente incluída na exportação. Nomes alterados geram colunas distintas; campos ausentes ficam vazios. Filtros de data/versão, labels das escolhas e separador configurável foram testados.
5. Perfil PostgreSQL exclusivo, executado em containers separados com banco temporário em `tmpfs`, sem porta publicada e sem volume ou rede do banco local. O CI tem uma matriz SQLite/PostgreSQL. Há teste com duas conexões comprovando bloqueios de escrita em formulário e versão.

## Contratos novos

| Operação | Endpoint | Payload / efeito |
| --- | --- | --- |
| Criar preenchimento final | `POST /api/forms/{formId}/submit` | `values` e `version_id` opcional. Sem versão explícita, utiliza a última publicada; com versão, exige uma versão publicada do mesmo formulário/tenant. |
| Criar draft | `POST /api/forms/{formId}/drafts` | `values` parciais e `version_id` opcional, seguindo a mesma seleção de versão. |
| Atualizar draft | `PATCH /api/forms/{formId}/drafts/{submissionId}` | `values` substitui o conjunto completo de respostas. `{}` limpa respostas; não altera versão. |
| Finalizar draft | `POST /api/forms/{formId}/drafts/{submissionId}/submit` | `values` completo, validado pela versão original. Retorna 200 com `data.submission`, preservando ID. |
| Exportar | `POST /api/submissions/export` | Aceita `separator` como `,` (padrão) ou `;`, além dos filtros existentes. |

Um draft já finalizado não pode ser alterado ou finalizado novamente. Dados de outro proprietário, formulário ou tenant retornam 404 antes da validação do payload. Formulário inativo bloqueia edição e finalização. A SPA utiliza os endpoints novos ao retomar um draft e envia a versão exibida ao criar novos preenchimentos.

## Auditoria e atomicidade

Gravações de negócio e seus logs compartilham a transação. Os testes verificam rollback na criação do formulário e na finalização do draft quando a auditoria falha. Exportação e recusas são eventos sem mutação de dados de negócio. Logs não podem ser alterados ou removidos pelo modelo nem por UPDATE/DELETE diretos: há triggers específicas em SQLite e PostgreSQL.

Os triggers também impedem modificar os vínculos de um log por cascata de exclusão. Uma futura funcionalidade de exclusão de tenants/usuários deverá preservar a auditoria ou definir uma estratégia de retenção antes de utilizar os `nullOnDelete` existentes. Não há endpoints de exclusão desses recursos na v1.

## Execução dos testes

Resultado final local: **112 testes passaram e um foi ignorado em SQLite (551 assertions)**; **113 passaram em PostgreSQL (553 assertions)**. O teste ignorado no SQLite verifica bloqueios entre conexões, executados no PostgreSQL. Pint e build da SPA passaram. O workflow remoto foi atualizado, mas ainda precisa executar após o envio das alterações.

SQLite, em `backend`:

```powershell
php artisan test --compact
```

PostgreSQL temporário, na raiz do projeto, após construir a imagem do backend e instalar suas dependências:

```powershell
docker compose -p form-engine-tests -f docker-compose.test.yml up --abort-on-container-exit --exit-code-from backend-tests
docker compose -p form-engine-tests -f docker-compose.test.yml down
```

O runner usa `php vendor/bin/phpunit --configuration=phpunit.postgres.xml` diretamente. `php artisan test --configuration=...` adiciona uma configuração duplicada nesta versão do comando Laravel e gera warning/exit code 1, mesmo quando os testes passam.

`TestCase` aceita SQLite sem URL de conexão, exclusivamente em `:memory:`, ou o perfil PostgreSQL explícito `isolated-postgres`. Esse perfil exige ambiente `testing`, database e username `form_engine_testing`, sem DB_URL, com host `postgres-test` ou `127.0.0.1`. O CI define `TEST_PG_HOST=127.0.0.1`; o Compose de testes usa `postgres-test`. A configuração normal do banco local não passa nessa proteção.

## Migrations

As migrations `2026_10_10_000000` e `2026_10_10_000001` acrescentam a unicidade de draft de versão e a proteção de auditoria. Foram aplicadas ao ambiente local após conferir que não existiam drafts de versão duplicados. Elas não limpam as tabelas existentes. Bancos com duplicidades anteriores precisam resolver essas duplicidades antes de adicionar o índice.

## Autenticação e autorização — etapa seguinte

- Tokens têm validade absoluta de 60 minutos, configurável por `SANCTUM_EXPIRATION` (mínimo 1 minuto). Novos tokens persistem `expires_at`; o limite global do Sanctum também expira tokens antigos a partir de `created_at`. Atividade não renova a sessão. Logout revoga apenas o token utilizado.
- Login aceita até 5 requisições por minuto por IP e por e-mail/tenant, incluindo sucesso e dados inválidos. `LOGIN_ATTEMPTS_PER_MINUTE` configura o limite. Bloqueios devolvem 429, `Retry-After` e o envelope padrão; `LOGIN_THROTTLED` registra contexto sem credenciais. O Docker usa `CACHE_STORE=redis` para compartilhar os contadores.
- `admin-sistema` herda permissões de `admin` nas operações do seu próprio tenant. Somente `/admin/tenants` permite gestão global; não há acesso implícito a formulários, submissões, usuários ou auditoria de outros tenants. Os menus acompanham a herança.
- Conforme UC04 RN07, desativar usuário impede novos logins e preserva sessões existentes até expiração. Desativar tenant bloqueia imediatamente as operações de dados, mantendo logout disponível.
- A matriz cobre as 30 operações protegidas com os quatro papéis, incluindo recusas auditadas e respostas de sucesso. Um teste compara a matriz com as rotas registradas, exigindo atualização quando novas operações são adicionadas. Todas também são exercitadas com token ausente, inválido e expirado. Recursos de outros tenants retornam 404, inclusive ao listar/criar rascunhos; rascunhos só podem ser editados/finalizados pelo dono.

Os testes de política cobrem expiração sem renovação, isolamento entre duas sessões, limite por IP/conta, recuperação após a janela e resposta genérica para credenciais inválidas. A validação final foi executada em SQLite e PostgreSQL isolado; os resultados estão na atualização da avaliação de testes.

Para aplicar as variáveis de ambiente ao container existente: `docker compose up -d backend`. Mudanças de configuração em ambientes que usam cache do Laravel exigem renovar esse cache. Não há migration nova nesta etapa.

## Consultas, filtros e paginação — etapa seguinte

- Listagens paginadas de submissões, auditoria, tenants e usuários validam `page` (1..2147483647, padrão 1) e `per_page` (1..100, padrão 20). Valores inválidos retornam 422 no envelope padrão, em vez de conversão silenciosa. Páginas além da última retornam lista vazia com total preservado e `from`/`to` nulos.
- Submissões validam status (`submitted`/`draft`), IDs positivos de 32 bits e datas exatas `YYYY-MM-DD`. Filtros são combinados; datas incluem os dias inicial/final. Rascunhos filtram/ordenam pela criação; submissões finais pelo envio. O ID decrescente desempata datas iguais. IDs inexistentes ou de outros tenants retornam lista vazia; não existe validação global de existência que exponha outro tenant. Usuário final continua limitado às próprias submissões, mesmo fornecendo `user_id` alheio.
- Auditoria valida IDs e filtros textuais (máximo 100 caracteres). Datas aceitam `YYYY-MM-DD` ou ISO 8601 com segundos e fuso (`Z` ou `±HH:MM`), normalizado para UTC. Uma data final sem hora inclui até 23:59:59; timestamps informam o instante exato. Intervalos invertidos são rejeitados. Ordenação desempata por ID decrescente.
- Formulários validam busca (máximo 255 caracteres), `sort=name|created_at`, `order=asc|desc` e `scope=available|all`. Busca por nome não distingue caixa em SQLite/PostgreSQL; ordenação desempata por ID. O retorno permanece uma coleção com total, compatível com a interface atual. Escopo administrativo inclui inativos/rascunhos apenas para manager/admin/admin-sistema.
- `QueryValidationAndPaginationTest` cobre entradas inválidas, combinações de filtros, datas de fronteira, fusos, consulta de versões, rascunhos, ordenação e metadados entre páginas, lista vazia e isolamento de tenant/usuário. Os resultados finais estão na avaliação de testes.

Não há migration nova. As mudanças usam os arquivos montados no backend local.

## Pendências para aceitação completa e produção

- Definir limites de tamanho dos valores e volume de exportação; CSV ainda carrega a coleção completa para exportar. Avaliar proteção contra fórmulas ao abrir arquivos em planilhas.
- Validar operação em produção: configuração de ambiente, backups/restauração, monitoramento e deploy. O CI remoto precisa executar após enviar as alterações.

As cinco etapas desta sequência foram implementadas e verificadas; isso não é uma declaração de atendimento a todos os cenários dos 21 casos de uso nem de prontidão completa para produção.
