# Arquitetura

## Componentes

O Form Engine tem uma API Laravel 13 (PHP 8.3 ou superior) e uma SPA Vue 3. PostgreSQL persiste os dados; Redis fornece cache compartilhado e os contadores de limitação de login. Os perfis Docker fixam PostgreSQL 17.11 e Redis 7.4.11. Filas usam execução síncrona, sem worker separado.

No desenvolvimento, o Vite serve o frontend e o Nginx encaminha a API ao PHP-FPM. No perfil de produção, o Nginx também serve a SPA compilada; o TLS termina em um proxy do host. A configuração operacional está no [guia de operação](operacao-producao.md).

## Camadas

O fluxo principal é Controller → Service → Repository → Model. Form Requests validam entradas; middleware aplica autenticação, permissões e verificações transversais. Controllers coordenam o HTTP, Services implementam regras e transações, Repositories encapsulam consultas e Models representam relacionamentos e atributos. As interfaces de repositório são vinculadas às implementações Eloquent em `AppServiceProvider`.

As rotas estão em `backend/routes/api.php`; o tratamento de exceções e a composição de middleware ficam em `backend/bootstrap/app.php`. O [guia da API](api/README.md) e o [OpenAPI](api/openapi.yaml) definem payloads, respostas e limites.

## Isolamento e autorização

O banco é compartilhado, com isolamento lógico por tenant. Consultas e serviços restringem os recursos ao tenant do usuário; versões e campos são verificados pelos vínculos de seus formulários. A implementação não depende de um escopo global automático. Recursos de outro tenant são tratados como não encontrados nos fluxos protegidos.

Os papéis são `user`, `manager`, `admin` e `admin-sistema`. O último satisfaz permissões de admin no próprio tenant; somente a gestão de tenants em `/api/admin/tenants` é global. Rascunhos só podem ser editados e finalizados pelo proprietário, inclusive quando outro usuário é administrador do mesmo tenant.

## Autenticação e entrada

Sanctum emite tokens Bearer com validade absoluta padrão de 60 minutos, configurável por `SANCTUM_EXPIRATION`. Atividade não renova a sessão; logout revoga apenas o token atual. Desativar usuário impede novos logins e preserva sessões existentes até expiração. Desativar tenant bloqueia operações de dados imediatamente, mantendo logout disponível.

O login limita tentativas por IP e por combinação e-mail/tenant em cache compartilhado. O slug desambigua e-mails presentes em vários tenants. Senhas usam bcrypt e aceitam no máximo 72 bytes UTF-8, sem NUL. Cabeçalhos encaminhados só são confiados pelos proxies configurados.

Middleware limita o corpo a 2 MiB e rejeita JSON inválido e caracteres NUL antes da lógica de negócio. Datas validam calendário entre os anos 0001 e 9999. Erros da API seguem envelope JSON e preservam status/cabeçalhos HTTP; falhas internas não expõem detalhes. Conflitos dos índices únicos de negócio conhecidos retornam 422, inclusive sob concorrência.

## Versionamento e transações

Um formulário tem várias versões e no máximo uma versão draft, garantida por índice parcial. Nova versão copia campos, opções e ordem. Versões publicadas são imutáveis nos fluxos da aplicação. Criação de versão bloqueia o formulário; publicação e alteração de campos bloqueiam a versão e verificam o estado dentro da transação.

Submissões permanecem vinculadas à versão utilizada. Rascunhos de preenchimento são distintos do draft de versão e podem ser múltiplos por usuário. Edição substitui respostas; finalização valida obrigatórios e preserva ID e versão original. Formulário inativo bloqueia edição e finalização.

## Auditoria

`AuditRecorder` registra gestão de recursos, submissões/rascunhos, exportação, login/logout e recusas de acesso previstas. Logs incluem ator, tenant, entidade, metadados e contexto HTTP, sem senha, token ou respostas do formulário. Tentativas sem conta identificável não recebem tenant/ator arbitrário.

Gravações de negócio e auditoria compartilham a transação; falha na auditoria desfaz a mutação, inclusive a revogação no logout. Models e triggers SQLite/PostgreSQL impedem alteração/exclusão dos logs. As triggers também impedem alterações indiretas dos vínculos por cascata: futura exclusão de tenants/usuários exige uma política de retenção compatível.

## Exportação

O CSV combina campos entre versões por nome técnico, preserva labels e usa escaping, BOM UTF-8 e CRLF. A seleção inicial de IDs fixa os participantes e a ordem; dados são lidos em lotes de 25 e escritos em arquivo temporário, liberado após transmissão ou erro. Um cache de até dois esquemas resolve opções sem reter todos os esquemas em memória.

Novas submissões não entram durante a geração. Não há snapshot transacional para alterações administrativas de nomes de usuários. O evento `EXPORT_DATA` registra o arquivo preparado, sem confirmar que o cliente concluiu o download. Tetos e tratamento de células potencialmente interpretadas como fórmulas estão no [guia da API](api/README.md#limites-de-entrada-e-exportação).

## Qualidade e operação

O CI executa testes em SQLite e PostgreSQL, estilo PHP, auditoria de dependências e build do frontend. A suíte PostgreSQL usa banco descartável e inclui concorrência entre processos reais. Consulte [testes](testes.md) para comandos e isolamento.

Liveness usa `/up`; readiness usa `/ready` e verifica banco, cache e armazenamento temporário. Imagens de produção, preflight, backup/restauração e ensaio de falha/recuperação estão descritos no [guia operacional](operacao-producao.md). O projeto ainda não está implantado em produção.
