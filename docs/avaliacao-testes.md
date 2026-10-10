# Avaliação dos testes do backend

Data: 10/10/2026.

Este relatório registra a avaliação inicial de 75 testes. A seção “Evolução após a avaliação”, ao final, indica as lacunas já corrigidas; a matriz inicial permanece como referência do diagnóstico.

## Parecer

Os testes contemplam parcialmente o comportamento esperado. Existem testes associados aos 21 casos de uso, mas isso não significa que todas as regras, alternativas e verificações obrigatórias desses casos estejam cobertas. A suíte protege os fluxos básicos e alguns controles de acesso; ainda não serve como comprovação completa de aceitação da v1.

Executei `php artisan test --compact` em `backend`: **75 testes passaram, com 289 assertions**. São 73 testes de API, um teste da página inicial e um exemplo unitário que apenas verifica `true`. Não foi medida cobertura de linhas ou branches; estes números não representam percentual de requisitos atendidos.

A execução usou SQLite em memória. `tests/TestCase.php` bloqueia a inicialização dos testes Laravel se o ambiente não for `testing` ou o banco não for SQLite `:memory:`. Nenhum teste foi executado contra o PostgreSQL local nesta avaliação.

## Critério utilizado

Comparei os testes de `backend/tests` com os fluxos, regras de negócio e seções “Testes Requeridos” de `docs/casos-de-uso`, além da implementação atual. O próprio README dos casos de uso exige sucesso, validação, isolamento e permissão para cada caso. “Parcial” significa que há pelo menos um cenário coberto, mas faltam verificações previstas nos documentos.

## Matriz de cobertura funcional

| Caso de uso | Evidência atual | Lacunas principais |
| --- | --- | --- |
| UC01 — Criar tenant | Criação, slug duplicado e restrição de papel | Campos ausentes, formato do slug, acesso anônimo e auditoria |
| UC02 — Status do tenant | Desativação; login em tenant inativo coberto em autenticação | Reativação, APIs com token emitido antes da desativação, preservação de dados e auditoria |
| UC03 — Criar usuário | Criação como manager, hash da senha, e-mail único por tenant e manager bloqueado | Matriz de papéis, senha curta, role/e-mail inválidos, criação em outro tenant e auditoria |
| UC04 — Status do usuário | Desativação, proibição de desativar a si mesmo e usuário de outro tenant oculto | Reativação, preservação de formulários/submissões, outras permissões e auditoria |
| UC05 — Criar formulário | Versão inicial draft, nome único por tenant e user bloqueado | Nome ausente/curto, tentativa de criar em outro tenant, persistência completa e auditoria |
| UC06 — Adicionar campos | Campo text, select sem opções rejeitado e versão publicada bloqueada | Outros tipos, nomes duplicados/fora do padrão, opções válidas, ordenação, papéis e auditoria |
| UC07 — Editar campos | Alteração de label, name e order em draft | Edição após publicação, obrigatoriedade/opções, nomes duplicados, isolamento e auditoria |
| UC08 — Remover campo | Exclusão em draft e bloqueio em versão publicada | Campo de outra versão, preservação de outros campos e submissões históricas, isolamento e auditoria |
| UC09 — Publicar versão | Publicação com campo e rejeição sem campos | published_at, republicação, campo inválido, isolamento, integração com listagem e auditoria |
| UC10 — Nova versão | Incremento para versão 2, contagem declarada de campos copiados e bloqueio de draft existente | Comparar os campos realmente persistidos, opções/ordem, independência das versões, histórico e auditoria |
| UC11 — Status do formulário | Desativação; listagem e envio bloqueados em cenários separados | Reativação, consultas históricas após mudança de status, preservação dos dados, papéis e auditoria |
| UC12 — Listar formulários | Inclusão de formulário ativo e exclusão de inativo/outro tenant | Formulário sem versão publicada, ordenação, lista vazia, última versão publicada e scope administrativo |
| UC13 — Enviar formulário | Envio de text/select, obrigatórios, formulário inativo e isolamento antes da validação | email/number/date/radio/checkbox, valores opcionais vazios, conteúdo persistido, mudança de versão e auditoria |
| UC14 — Rascunhos | Criação parcial, rejeição de tipo inválido, listagem por proprietário e ausência de versão publicada | Recuperar valores, múltiplos drafts, editar/retomar, conversão final, isolamento completo e auditoria |
| UC15 — Consultar submissões | Filtro por formulário, visão por proprietário e per_page | Datas, usuário, combinação de filtros, ordenação, total/páginas e exclusão efetiva de drafts |
| UC16 — Detalhes da submissão | Detalhes para manager e bloqueio de outro proprietário | Acesso permitido ao próprio user, outro tenant, inexistente, labels de opções, ordem e campos vazios |
| UC17 — CSV | Resposta CSV, cabeçalho básico e user bloqueado | Conteúdo das linhas, BOM, escaping, datas, opções, múltiplas versões, filtros, isolamento e auditoria |
| UC18 — Consultar auditoria | Consulta de log previamente criado e bloqueio de manager | Outros tenants, filtros efetivos, ordenação, paginação, imutabilidade e geração automática dos logs |
| UC19 — Detalhes de auditoria | Log previamente criado, usuário e entidade ausente | Outro tenant, log inexistente, papéis negados, metadados completos e entidade existente |
| UC20 — Autenticar | Credenciais, token, usuário/tenant inativos, e-mail em múltiplos tenants, me e logout | Token expirado/inválido, reutilização após logout, isolamento entre tokens e auditoria de sucesso/falha |
| UC21 — Permissões | Amostra de permissões e isolamento de recursos | Matriz rota × papel, acesso anônimo por operação, tokens inválidos/expirados, status após login e auditoria de recusas |

## Lacunas prioritárias

### 1. Auditoria: a consulta é testada, a geração não

Em `backend/tests/Feature/Api/QueryAuditUseCasesContractTest.php`, os testes UC18/UC19 inserem `AuditLog` diretamente antes da consulta. Não verificam se criar usuário, publicar versão, submeter formulário, exportar ou autenticar gera um log com ator, tenant, ação, entidade, IP e user agent.

A implementação atual em `AuditLogService` e seu repositório fornece leitura de logs, sem gravação automática nos fluxos inspecionados. Portanto, aqui há uma lacuna de implementação que a suíte não detecta, além de falta de testes.

### 2. Campos dinâmicos: validação e persistência incompletamente protegidas

Os cenários de envio usam principalmente text/select. Faltam casos válidos e inválidos para cada tipo e verificações dos valores recuperados após a persistência. `SubmissionService::buildRules` aplica apenas `string` ao tipo email; não aplica uma regra de formato de e-mail. Checkbox não recebe validação de array/opções, e `persistValues` converte todos os valores para string.

Testes de e-mail inválido e de checkbox com múltiplas escolhas precisam detectar essas limitações. Contar duas linhas em `form_submission_values` não comprova que os valores e os vínculos com os campos estão corretos.

### 3. Acesso após desativação e ciclo de vida do token

O login rejeita tenant inativo, mas não há teste que faça login, desative o tenant e tente acessar formulários usando o token já emitido. UC02 exige formulários inacessíveis e APIs bloqueadas para tenant desativado. O middleware `EnsureTenantAccess` verifica o identificador do tenant, não seu status; as rotas gerais também não aplicam um bloqueio de tenant inativo.

Também faltam testes de token inválido/expirado e de nova requisição após logout. O teste de logout verifica a remoção no banco, mas não tenta reutilizar o token. `config/sanctum.php` mantém `expiration = null`; os requisitos de expiração precisam ser alinhados com a configuração pretendida.

### 4. Versionamento precisa provar preservação do histórico

O teste UC10 verifica `fields_copied = 2` na resposta e a existência da versão 2. Não compara os campos persistidos com os originais, suas opções/ordem, nem altera o draft para provar que a versão publicada e as submissões antigas permanecem intactas.

É necessário um fluxo integrado: criar formulário → adicionar campos → publicar v1 → submeter → criar/editar/publicar v2 → consultar submissão da v1 e enviar nova submissão na v2. Adição e remoção de campos publicados são testadas; edição de campo publicado ainda precisa de teste explícito.

### 5. Rascunho precisa cobrir continuidade do preenchimento

Criar e listar um draft não comprova que o usuário consegue recuperar os valores, continuar o preenchimento e convertê-lo em submissão final com validação completa. A API atual cria drafts, mas não expõe atualização/conversão de um draft existente por identificador.

O UC14 prevê múltiplos rascunhos na v1 e conversão final. Sobrescrever aparece como alternativa, enquanto criar novo é explicitamente marcado como v1; convém definir o contrato de edição antes de exigir a alternativa de sobrescrita. A conversão final e a recuperação dos valores continuam sendo lacunas centrais.

### 6. CSV e filtros têm verificações superficiais

O teste UC17 confirma Content-Type, Content-Disposition e um trecho do cabeçalho. Não interpreta o CSV nem compara as linhas com as submissões. Isso permite passar mesmo com valores faltando, cabeçalhos mal escapados ou colunas incompatíveis entre versões.

`SubmissionCsvService` usa `implode` no cabeçalho e combina campos por ID, que muda ao copiar versões. Cabeçalhos com vírgulas e combinação de versões precisam de testes específicos. O separador é fixo, embora UC17 RN06 o descreva como configurável.

O teste `test_submissions_listing_applies_filters_and_pagination` prepara um registro antigo, mas não envia filtro de datas. Como o filtro de formulário já deixa apenas um registro, `per_page=1` também não comprova a navegação entre páginas. O teste de auditoria por ação cria apenas um log, sem um log de outra ação que deveria ser excluído. O teste com nome “own_tenant” não cria um log de outro tenant para provar isolamento.

## Estratégia de consolidação sugerida

1. Priorizar testes de segurança: tenant desativado com token existente, recursos de outro tenant nas operações de escrita e exportação, papéis por rota e reutilização de token revogado.
2. Cobrir cada tipo de campo com valores válidos, inválidos, obrigatórios/ opcionais e leitura após persistência.
3. Implementar e testar geração de auditoria nos fluxos reais, incluindo login e recusas previstas nos documentos.
4. Adicionar os fluxos completos de versionamento/histórico e recuperação/conversão de draft.
5. Interpretar o CSV nos testes e conferir cabeçalhos, células, quantidade de registros, filtros e versões. Testar paginação com mais registros que o tamanho da página.
6. Criar posteriormente uma suíte de integração com PostgreSQL em banco exclusivo de testes, com proteção equivalente. SQLite em memória não comprova todas as diferenças de constraints, tipos e SQL do banco utilizado pela aplicação.

Não é necessário criar testes unitários para cada método apenas para aumentar a quantidade. Validação dinâmica e geração de CSV são candidatos úteis a testes focados; os contratos de API e os fluxos integrados devem continuar comprovando o comportamento do sistema.

## Limites da avaliação

Esta avaliação cobre o backend. Não certifica os comportamentos de interface descritos nos casos de uso. Rate limiting está marcado como futuro no UC20; expiração automática de rascunhos está explicitamente fora da v1 no UC14. Esses itens não foram tratados como bloqueadores da aceitação atual.

Não foram alterados testes nem funcionalidades durante esta avaliação. As limitações de implementação acima foram identificadas por leitura do código, sem adicionar ou executar cenários novos para reproduzi-las. Os 75 testes existentes passaram; esse resultado garante apenas os cenários e assertions que a suíte contém.

## Evolução após a avaliação — acesso e campos dinâmicos

A consolidação seguinte adicionou 19 cenários e corrigiu as falhas reproduzidas pelos novos testes. A suíte passou com **94 testes e 392 assertions**, em SQLite em memória. O build do frontend também passou.

- Tokens existentes recebem 403 nas APIs de dados e em `/api/auth/me` enquanto o tenant estiver desativado. A reativação restabelece o acesso, preservando o token e os dados. Logout permanece disponível para revogar o token mesmo com tenant inativo.
- Há testes de token inválido, token com `expires_at` vencido e reutilização após logout. A política de duração padrão dos tokens continua pendente; `sanctum.expiration` permanece nulo.
- E-mail inválido é recusado tanto no envio final quanto no rascunho. Checkbox exige uma lista de strings com opções existentes, incluindo rejeição de valores escalares, objetos, opções desconhecidas e arrays aninhados.
- Checkbox é armazenado como JSON em coluna de texto e recuperado como array nos detalhes. O frontend envia arrays; valores legados separados por vírgula continuam legíveis. A exportação apresenta os labels das escolhas separados por ponto e vírgula.
- Os oito tipos de campo têm um cenário de envio e leitura dos valores persistidos. Campos opcionais vazios são aceitos, e rascunhos podem omitir campos obrigatórios.

As pendências de auditoria automática, ciclo completo de rascunhos, histórico de versões, filtros/paginação, formatação geral do CSV e integração com PostgreSQL continuam abertas. Esta etapa não certifica todos os requisitos da v1.

## Evolução seguinte — auditoria, drafts, histórico, CSV e PostgreSQL

Auditoria automática e imutabilidade no banco foram implementadas, incluindo testes de identidade/contexto e rollback quando a gravação falha. A suíte cobre atualização e conversão do mesmo draft, isolamento do proprietário, preservação da versão original após nova publicação, cópia de opções/ordem e leitura histórica após edição/exclusão na versão seguinte.

O CSV é interpretado nos testes para comparar headers/células com vírgulas, aspas e quebras de linha, múltiplas versões, filtros, BOM e separador. O índice de draft único e o perfil PostgreSQL exclusivo foram adicionados. O teste de bloqueio entre duas conexões é executado apenas no PostgreSQL.

Consulte [a consolidação funcional](consolidacao-backend.md) para os contratos e pendências atuais. A matriz no início continua sendo o diagnóstico anterior às correções. Ainda faltam a matriz completa de permissões, cobertura adicional de filtros/paginação, limites de entradas/exportação e definição da política de expiração; não há certificado de aceitação integral da v1 baseado apenas na contagem de testes.
