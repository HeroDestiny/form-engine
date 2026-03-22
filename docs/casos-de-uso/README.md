# Casos de Uso — Form Engine

Este diretório contém a especificação detalhada de todos os casos de uso do Form Engine, organizados por categoria.

---

## 📂 Estrutura de Organização

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

---

## 👥 Atores do Sistema

### Administrador do Sistema
Responsável pela gestão global do sistema, criação de tenants e configurações gerais.

### Administrador do Tenant
Usuário com papel `admin` dentro de uma unidade administrativa, responsável por gerenciar usuários e configurações do tenant.

### Gestor (Manager)
Usuário com papel `manager`, responsável por criar, versionar e publicar formulários dentro de seu tenant.

### Usuário Final
Usuário com papel `user`, responsável por preencher e submeter formulários.

### Sistema
Responsável por auditoria automática, validações e processamento de dados.

---

## 📊 Matriz de Casos de Uso por Ator

| Caso de Uso | Admin Sistema | Admin Tenant | Manager | User |
|-------------|---------------|--------------|---------|------|
| UC01 - Criar Tenant | ✓ | | | |
| UC02 - Ativar/Desativar Tenant | ✓ | | | |
| UC03 - Criar Usuário | | ✓ | | |
| UC04 - Ativar/Desativar Usuário | | ✓ | | |
| UC05 - Criar Formulário | | ✓ | ✓ | |
| UC06 - Adicionar Campos | | ✓ | ✓ | |
| UC07 - Editar Campos | | ✓ | ✓ | |
| UC08 - Remover Campo | | ✓ | ✓ | |
| UC09 - Publicar Versão | | ✓ | ✓ | |
| UC10 - Criar Nova Versão | | ✓ | ✓ | |
| UC11 - Ativar/Desativar Formulário | | ✓ | ✓ | |
| UC12 - Listar Formulários | | ✓ | ✓ | ✓ |
| UC13 - Preencher Formulário | | ✓ | ✓ | ✓ |
| UC14 - Salvar Rascunho | | ✓ | ✓ | ✓ |
| UC15 - Consultar Submissões | | ✓ | ✓ | (próprias) |
| UC16 - Visualizar Submissão | | ✓ | ✓ | (próprias) |
| UC17 - Exportar Dados | | ✓ | ✓ | |
| UC18 - Consultar Auditoria | | ✓ | | |
| UC19 - Visualizar Ação Auditada | | ✓ | | |
| UC20 - Autenticar | ✓ | ✓ | ✓ | ✓ |
| UC21 - Verificar Permissões | Sistema | Sistema | Sistema | Sistema |

---

## 🎯 Regras de Negócio Transversais

### Isolamento Multi-Tenant
- **RN01:** Todos os dados principais devem ter `tenant_id`
- **RN02:** Usuário nunca acessa dados de outro tenant
- **RN03:** Queries devem sempre filtrar por tenant ativo
- **RN04:** Validação de tenant é obrigatória em cada operação

### Versionamento
- **RN05:** Versão publicada é imutável
- **RN06:** Apenas uma versão draft por formulário
- **RN07:** Submissions sempre vinculadas a versão específica
- **RN08:** Nova versão copia estrutura da anterior

### Auditoria
- **RN09:** Todas ações relevantes devem ser auditadas
- **RN10:** Logs são imutáveis
- **RN11:** Auditoria inclui: tenant, usuário, ação, entidade, timestamp

### Validação
- **RN12:** Validação de entrada via Form Requests
- **RN13:** Validação de negócio na camada Service
- **RN14:** Mensagens de erro devem ser claras e padronizadas

### Segurança
- **RN15:** Senhas devem ser hasheadas (bcrypt)
- **RN16:** Tokens de autenticação com expiração
- **RN17:** Rate limiting para APIs sensíveis
- **RN18:** Sanitização de inputs obrigatória

---

## 🔮 Casos de Uso Futuros (Fora do Escopo v1)

### Gestão Avançada
- Hierarquia de tenants (estadual → municipal)
- Permissões granulares por recurso
- Workflows de aprovação de formulários
- Templates de formulários

### Colaboração
- Compartilhamento de formulários entre tenants
- Comentários em submissões
- Histórico de revisões de campos

### Análise e Relatórios
- Dashboard analítico
- Gráficos e indicadores
- Relatórios customizados
- Alertas e notificações

### Integrações
- Webhooks para eventos
- API de importação em lote
- Integração com sistemas externos
- SSO institucional

---

## 📝 Padrão de Resposta da API

Todos os casos de uso seguirão o padrão de resposta:

```json
{
  "success": true,
  "data": {},
  "message": "Operação realizada com sucesso",
  "errors": null
}
```

### Tratamento de Erros

Exceções específicas por tipo de erro:
- `ValidationException`: Erros de validação de entrada
- `BusinessException`: Regras de negócio violadas
- `NotFoundException`: Recurso não encontrado
- `UnauthorizedException`: Sem permissão
- `TenantMismatchException`: Violação de isolamento

---

## ✅ Testes Obrigatórios

Cada caso de uso deve ter:
- Teste de sucesso (caminho feliz)
- Testes de validação (entradas inválidas)
- Testes de isolamento (tentativa de acesso cross-tenant)
- Testes de permissão (verificação de papéis)

---

**Versão:** 1.0
**Data:** 2026-03-22
**Base:** Documentação de Visão, Arquitetura e Modelo de Dados
