# Casos de uso

Os 21 casos de uso descrevem o escopo funcional e as regras de negócio da v1. Os exemplos de implementação e interface nos documentos são ilustrativos; payloads e rotas atuais estão no [guia da API](../api/README.md) e no [OpenAPI](../api/openapi.yaml).

### 01. Gestão de Tenants
- [UC01 - Criar Tenant](01-gestao-tenants/UC01-criar-tenant.md)
- [UC02 - Ativar/Desativar Tenant](01-gestao-tenants/UC02-ativar-desativar-tenant.md)

### 02. Gestão de Usuários
- [UC03 - Criar Usuário](02-gestao-usuarios/UC03-criar-usuario.md)
- [UC04 - Ativar/Desativar Usuário](02-gestao-usuarios/UC04-ativar-desativar-usuario.md)

### 03. Gestão de Formulários
- [UC05 - Criar Formulário](03-gestao-formularios/UC05-criar-formulario.md)
- [UC06 - Adicionar Campos ao Formulário](03-gestao-formularios/UC06-adicionar-campos.md)
- [UC07 - Editar Campos do Formulário](03-gestao-formularios/UC07-editar-campos.md)
- [UC08 - Remover Campo do Formulário](03-gestao-formularios/UC08-remover-campo.md)
- [UC09 - Publicar Versão de Formulário](03-gestao-formularios/UC09-publicar-versao.md)
- [UC10 - Criar Nova Versão de Formulário](03-gestao-formularios/UC10-criar-nova-versao.md)
- [UC11 - Ativar/Desativar Formulário](03-gestao-formularios/UC11-ativar-desativar-formulario.md)

### 04. Preenchimento de Formulários
- [UC12 - Listar Formulários Disponíveis](04-preenchimento/UC12-listar-formularios.md)
- [UC13 - Preencher Formulário](04-preenchimento/UC13-preencher-formulario.md)
- [UC14 - Salvar Rascunho de Formulário](04-preenchimento/UC14-salvar-rascunho.md)

### 05. Consulta e Exportação
- [UC15 - Consultar Submissões](05-consulta-exportacao/UC15-consultar-submissoes.md)
- [UC16 - Visualizar Detalhes de Submissão](05-consulta-exportacao/UC16-visualizar-detalhes-submissao.md)
- [UC17 - Exportar Dados em CSV](05-consulta-exportacao/UC17-exportar-csv.md)

### 06. Auditoria
- [UC18 - Consultar Log de Auditoria](06-auditoria/UC18-consultar-log-auditoria.md)
- [UC19 - Visualizar Detalhes de Ação Auditada](06-auditoria/UC19-visualizar-detalhes-acao.md)

### 07. Autenticação e Autorização
- [UC20 - Autenticar no Sistema](07-autenticacao/UC20-autenticar.md)
- [UC21 - Verificar Permissões de Acesso](07-autenticacao/UC21-verificar-permissoes.md)

## Regras transversais

- Recursos comuns permanecem restritos ao tenant. Admin-sistema herda permissões de admin no próprio tenant; apenas a gestão de tenants é global.
- Versão publicada não é alterada nos fluxos da aplicação; há no máximo uma versão draft por formulário. Rascunhos de preenchimento podem ser múltiplos e só o proprietário pode editá-los/finalizá-los.
- Auditoria é automática, imutável e transacional nas mutações. Logs não armazenam credenciais nem respostas dos formulários.
- Entradas e regras de negócio são validadas; recusas usam o envelope da API. Autenticação usa tokens com expiração e limitação de login.
- Mudanças funcionais devem verificar sucesso, validação, isolamento e permissões, além de atomicidade e concorrência quando pertinentes. Consulte [testes](../testes.md).

Funcionalidades indicadas como futuras ou fora da v1 nos documentos não integram o escopo atual.
