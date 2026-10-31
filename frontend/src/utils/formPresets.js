/**
 * Presets e Modelos Prontos de Campos e Blocos para o Form Builder
 */

export const FIELD_PRESETS = [
  {
    id: 'preset-cpf',
    category: 'identificacao',
    label: 'CPF',
    name: 'cpf',
    type: 'text',
    is_required: true,
    description: 'Documento de identificação individual (Cadastro de Pessoa Física).',
    badge: 'Documento',
  },
  {
    id: 'preset-cnpj',
    category: 'identificacao',
    label: 'CNPJ',
    name: 'cnpj',
    type: 'text',
    is_required: true,
    description: 'Cadastro Nacional da Pessoa Jurídica para empresas.',
    badge: 'Empresarial',
  },
  {
    id: 'preset-telefone',
    category: 'contato',
    label: 'Telefone / WhatsApp',
    name: 'telefone',
    type: 'text',
    is_required: true,
    description: 'Contato celular ou fixo com DDD.',
    badge: 'Contato',
  },
  {
    id: 'preset-data-nasc',
    category: 'identificacao',
    label: 'Data de Nascimento',
    name: 'data_nascimento',
    type: 'date',
    is_required: true,
    description: 'Data de nascimento para cálculo de maioridade.',
    badge: 'Data',
  },
  {
    id: 'preset-cep',
    category: 'localizacao',
    label: 'CEP',
    name: 'cep',
    type: 'text',
    is_required: true,
    description: 'Código de Endereçamento Postal brasileiro.',
    badge: 'Endereço',
  },
  {
    id: 'preset-valor',
    category: 'financeiro',
    label: 'Valor Estimado (R$)',
    name: 'valor_estimado',
    type: 'number',
    is_required: false,
    description: 'Campo numérico para valores em reais ou orçamento.',
    badge: 'Moeda',
  },
  {
    id: 'preset-doc-anexo',
    category: 'anexos',
    label: 'Documento Oficial com Foto',
    name: 'documento_oficial',
    type: 'file',
    is_required: true,
    options: [
      { value: 'pdf', label: 'PDF' },
      { value: 'png', label: 'PNG' },
      { value: 'jpg', label: 'JPG' },
    ],
    description: 'Upload de RG, CNH ou Passaporte em PDF ou imagem.',
    badge: 'Anexo',
  },
  {
    id: 'preset-comprovante-residencia',
    category: 'anexos',
    label: 'Comprovante de Residência',
    name: 'comprovante_residencia',
    type: 'file',
    is_required: true,
    options: [
      { value: 'pdf', label: 'PDF' },
      { value: 'png', label: 'PNG' },
      { value: 'jpg', label: 'JPG' },
    ],
    description: 'Conta de luz, água ou telefone emitida há até 90 dias.',
    badge: 'Anexo',
  },
]

export const BLOCK_PRESETS = [
  {
    id: 'block-pessoal',
    name: 'Identificação Básica',
    description: 'Conjunto essencial para cadastro de pessoas físicas (4 campos).',
    badge: 'Essencial',
    icon: 'user',
    fields: [
      { label: 'Nome Completo', name: 'nome_completo', type: 'text', is_required: true, width: 'full', section: 'Identificação Básica' },
      { label: 'E-mail para Contato', name: 'email_contato', type: 'email', is_required: true, width: 'full', section: 'Identificação Básica' },
      { label: 'Celular / WhatsApp', name: 'celular', type: 'text', is_required: true, width: 'half', section: 'Identificação Básica' },
      { label: 'CPF', name: 'cpf', type: 'text', is_required: true, width: 'half', section: 'Identificação Básica' },
    ],
  },
  {
    id: 'block-endereco',
    name: 'Endereço Completo',
    description: 'Estrutura completa padronizada de logradouro e município (7 campos).',
    badge: 'Localização',
    icon: 'pin',
    fields: [
      { label: 'CEP', name: 'cep', type: 'text', is_required: true, width: 'half', section: 'Endereço Completo', section_description: 'Dados de localização do solicitante' },
      { label: 'Logradouro / Rua', name: 'logradouro', type: 'text', is_required: true, width: 'half', section: 'Endereço Completo' },
      { label: 'Número', name: 'numero', type: 'text', is_required: true, width: 'third', section: 'Endereço Completo' },
      { label: 'Complemento (apto, bloco)', name: 'complemento', type: 'text', is_required: false, width: 'third', section: 'Endereço Completo' },
      { label: 'Bairro', name: 'bairro', type: 'text', is_required: true, width: 'third', section: 'Endereço Completo' },
      { label: 'Cidade', name: 'cidade', type: 'text', is_required: true, width: 'half', section: 'Endereço Completo' },
      {
        label: 'Estado (UF)',
        name: 'estado',
        type: 'select',
        is_required: true,
        width: 'half',
        section: 'Endereço Completo',
        options: [
          { value: 'AC', label: 'Acre (AC)' },
          { value: 'AL', label: 'Alagoas (AL)' },
          { value: 'AP', label: 'Amapá (AP)' },
          { value: 'AM', label: 'Amazonas (AM)' },
          { value: 'BA', label: 'Bahia (BA)' },
          { value: 'CE', label: 'Ceará (CE)' },
          { value: 'DF', label: 'Distrito Federal (DF)' },
          { value: 'ES', label: 'Espírito Santo (ES)' },
          { value: 'GO', label: 'Goiás (GO)' },
          { value: 'MA', label: 'Maranhão (MA)' },
          { value: 'MT', label: 'Mato Grosso (MT)' },
          { value: 'MS', label: 'Mato Grosso do Sul (MS)' },
          { value: 'MG', label: 'Minas Gerais (MG)' },
          { value: 'PA', label: 'Pará (PA)' },
          { value: 'PB', label: 'Paraíba (PB)' },
          { value: 'PR', label: 'Paraná (PR)' },
          { value: 'PE', label: 'Pernambuco (PE)' },
          { value: 'PI', label: 'Piauí (PI)' },
          { value: 'RJ', label: 'Rio de Janeiro (RJ)' },
          { value: 'RN', label: 'Rio Grande do Norte (RN)' },
          { value: 'RS', label: 'Rio Grande do Sul (RS)' },
          { value: 'RO', label: 'Rondônia (RO)' },
          { value: 'RR', label: 'Roraima (RR)' },
          { value: 'SC', label: 'Santa Catarina (SC)' },
          { value: 'SP', label: 'São Paulo (SP)' },
          { value: 'SE', label: 'Sergipe (SE)' },
          { value: 'TO', label: 'Tocantins (TO)' },
        ],
      },
    ],
  },
  {
    id: 'block-satisfacao',
    name: 'Pesquisa de Satisfação & NPS',
    description: 'Métrica de atendimento com nota, motivo e observações (3 campos).',
    badge: 'Feedback',
    icon: 'star',
    fields: [
      {
        label: 'Como você avalia nosso atendimento?',
        name: 'avaliacao_atendimento',
        type: 'radio',
        is_required: true,
        width: 'full',
        section: 'Pesquisa de Satisfação & NPS',
        section_description: 'Sua opinião é fundamental para aprimorarmos nossos serviços',
        options: [
          { value: '5', label: '⭐⭐⭐⭐⭐ Excelente' },
          { value: '4', label: '⭐⭐⭐⭐ Bom' },
          { value: '3', label: '⭐⭐⭐ Regular' },
          { value: '2', label: '⭐⭐ Ruim' },
          { value: '1', label: '⭐ Péssimo' },
        ],
      },
      {
        label: 'Qual o principal motivo da sua nota?',
        name: 'motivo_avaliacao',
        type: 'select',
        is_required: false,
        width: 'full',
        section: 'Pesquisa de Satisfação & NPS',
        options: [
          { value: 'agilidade', label: 'Agilidade e tempo de resposta' },
          { value: 'cordialidade', label: 'Cordialidade da equipe' },
          { value: 'clareza', label: 'Clareza das informações' },
          { value: 'solucao', label: 'Resolução do problema' },
          { value: 'outro', label: 'Outro motivo' },
        ],
      },
      {
        label: 'Sugestões de melhoria ou comentários',
        name: 'sugestoes_comentarios',
        type: 'textarea',
        is_required: false,
        width: 'full',
        section: 'Pesquisa de Satisfação & NPS',
      },
    ],
  },
  {
    id: 'block-documentos',
    name: 'Documentação Cadastral (Anexos)',
    description: 'Coleta de arquivos PDF e imagem para conferência e validação (2 campos).',
    badge: 'Anexos',
    icon: 'document',
    fields: [
      {
        label: 'Documento de Identificação (RG/CNH)',
        name: 'documento_identificacao',
        type: 'file',
        is_required: true,
        width: 'half',
        section: 'Documentação Cadastral',
        section_description: 'Envio de comprovantes digitais (PDF, JPG ou PNG)',
        options: [
          { value: 'pdf', label: 'PDF' },
          { value: 'png', label: 'PNG' },
          { value: 'jpg', label: 'JPG' },
        ],
      },
      {
        label: 'Comprovante de Endereço Residencial',
        name: 'comprovante_endereco',
        type: 'file',
        is_required: true,
        width: 'half',
        section: 'Documentação Cadastral',
        options: [
          { value: 'pdf', label: 'PDF' },
          { value: 'png', label: 'PNG' },
          { value: 'jpg', label: 'JPG' },
        ],
      },
    ],
  },
]

export function generateUniqueFieldName(baseName, existingFields = []) {
  const cleanBase = String(baseName || 'campo')
    .toLowerCase()
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9_]/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '') || 'campo'

  const takenNames = new Set(existingFields.map(f => String(f?.name || '').toLowerCase()))
  if (!takenNames.has(cleanBase)) {
    return cleanBase
  }

  let suffix = 2
  while (takenNames.has(`${cleanBase}_${suffix}`)) {
    suffix++
  }
  return `${cleanBase}_${suffix}`
}

export function duplicateFieldDraft(field, existingFields = []) {
  const uniqueName = generateUniqueFieldName(field.name, existingFields)
  const sec = field.section || field.options?.section || null
  const secDesc = field.section_description || field.options?.section_description || null
  const w = field.width || field.options?.width || 'full'
  return {
    label: `${field.label} (Cópia)`,
    name: uniqueName,
    type: field.type,
    is_required: field.is_required,
    order: (Math.max(0, ...existingFields.map(f => Number(f.order) || 0))) + 1,
    options: Array.isArray(field.options) ? structuredClone(field.options) : null,
    condition: field.condition ? structuredClone(field.condition) : (field.options?.condition ? structuredClone(field.options.condition) : null),
    section: sec,
    section_description: secDesc,
    width: w,
  }
}
