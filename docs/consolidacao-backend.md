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

## Pendências para aceitação completa e produção

- Definir e configurar expiração padrão de tokens. Alinhar a documentação sobre sessões de usuário desativado e rate limiting; essa política não foi alterada nesta sequência.
- Ampliar a matriz de permissões por rota e os cenários de filtros, paginação e entradas inválidas de consulta. Filtros existentes não devem ser confundidos com cobertura completa de todos os casos.
- Definir limites de tamanho dos valores e volume de exportação; CSV ainda carrega a coleção completa para exportar. Avaliar proteção contra fórmulas ao abrir arquivos em planilhas.
- Validar operação em produção: configuração de ambiente, backups/restauração, monitoramento e deploy. O CI remoto precisa executar após enviar as alterações.

As cinco etapas desta sequência foram implementadas e verificadas; isso não é uma declaração de atendimento a todos os cenários dos 21 casos de uso nem de prontidão completa para produção.
