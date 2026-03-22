# Documentação do Projeto

Este diretório contém toda a documentação técnica e conceitual do Form Engine.

---

## 📚 Documentos Principais

### [🎯 Visão do Produto](visao.md)
Documento de visão do produto, objetivos, público-alvo e requisitos de alto nível.

### [🏗️ Arquitetura](arquitetura.md)
Arquitetura do sistema, decisões técnicas, padrões e tecnologias utilizadas.

### [🗄️ Modelo de Dados](modelo_de_dados.md)
Estrutura do banco de dados, entidades, relacionamentos e diagramas.

---

## 📋 Casos de Uso

### [Documentação de Casos de Uso](casos-de-uso/)
**21 casos de uso detalhados** organizados por categoria:

- **01. Gestão de Tenants** (2 casos)
- **02. Gestão de Usuários** (2 casos)
- **03. Gestão de Formulários** (7 casos)
- **04. Preenchimento** (3 casos)
- **05. Consulta e Exportação** (3 casos)
- **06. Auditoria** (2 casos)
- **07. Autenticação** (2 casos)

Cada caso de uso inclui:
- Fluxos principal e alternativos
- Regras de negócio
- Validações e testes
- Exemplos de código
- Interfaces sugeridas

---

## 🌐 Especificação da API

### [API REST Documentation](api/)
Documentação completa da API REST v1.0:

- **[README](api/README.md)** - Guia completo com exemplos
- **[OpenAPI/Swagger](api/openapi.yaml)** - Especificação formal (28 endpoints)
- **[Quick Reference](api/quick-reference.md)** - Referência rápida
- **[Postman Collection](api/postman-collection.json)** - Collection para importar

**Recursos:**
- Autenticação Bearer Token
- 28 endpoints REST
- Padrão de resposta unificado
- Exemplos cURL e código
- Paginação e filtros

---

## 🗂️ Organização

```
docs/
├── README.md                  # Este arquivo
├── visao.md                   # Visão do produto
├── arquitetura.md             # Arquitetura do sistema
├── modelo_de_dados.md         # Modelo de dados
├── casos-de-uso/              # 21 casos de uso detalhados
│   ├── README.md
│   ├── 01-gestao-tenants/
│   ├── 02-gestao-usuarios/
│   ├── 03-gestao-formularios/
│   ├── 04-preenchimento/
│   ├── 05-consulta-exportacao/
│   ├── 06-auditoria/
│   └── 07-autenticacao/
└── api/                       # Especificação da API
    ├── README.md
    ├── openapi.yaml
    ├── quick-reference.md
    └── postman-collection.json
```

---

## 🚀 Início Rápido

### Para Desenvolvedores

1. **Entenda o Produto:** Leia [visao.md](visao.md)
2. **Arquitetura:** Estude [arquitetura.md](arquitetura.md)
3. **Modelo de Dados:** Revise [modelo_de_dados.md](modelo_de_dados.md)
4. **Casos de Uso:** Explore [casos-de-uso/](casos-de-uso/)
5. **API:** Use [api/quick-reference.md](api/quick-reference.md)

### Para Testar a API

1. Importe [postman-collection.json](api/postman-collection.json) no Postman
2. Configure `base_url`: `http://localhost:8000/api`
3. Execute "Login" para obter token
4. Teste os endpoints

### Para Visualizar OpenAPI

```bash
# Via Docker
docker run -p 8080:8080 -e SWAGGER_JSON=/api/openapi.yaml \
  -v $(pwd)/docs/api:/api swaggerapi/swagger-ui

# Acesse: http://localhost:8080
```

---

## 📊 Estatísticas

- **Casos de Uso:** 21 documentados (6.834 linhas)
- **Endpoints API:** 28 endpoints REST
- **Schemas:** 15+ modelos de dados
- **Exemplos:** 50+ exemplos de código

---

## 🔗 Links Externos

- **Laravel:** https://laravel.com/docs
- **PostgreSQL:** https://www.postgresql.org/docs/
- **OpenAPI:** https://spec.openapis.org/oas/v3.0.3
- **REST API Design:** https://restfulapi.net/

---

## 📝 Contribuindo

Ao atualizar a documentação:

- ✅ Mantenha o formato Markdown
- ✅ Use diagramas quando apropriado
- ✅ Mantenha sincronizado com o código
- ✅ Inclua data da última atualização
- ✅ Adicione exemplos práticos
- ✅ Teste os exemplos de código
- ✅ Atualize o OpenAPI ao modificar endpoints

---

**Última atualização:** 2026-03-22
**Versão do Projeto:** 1.0 (Fase 1 - Fundação)
