<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session } from '../auth/session.js'
import { formatDate } from '../utils/display.js'
import { getSubmission, downloadSubmissionAttachment } from '../api'
import { usePageBreadcrumbs } from '../composables/useBreadcrumbs.js'
import { pushToast } from '../composables/useToast.js'
import StatusBadge from '../components/StatusBadge.vue'
import AppIcon from '../components/AppIcon.vue'
import { getFieldWidthClass, groupFieldsBySection } from '../utils/formLayout.js'

const route = useRoute()
const router = useRouter()
const { setBreadcrumbs } = usePageBreadcrumbs()

const submissionId = computed(() => route.params.submissionId)
const canResume = computed(() => (
  state.submission?.submission?.status === 'draft' &&
  String(state.submission?.user?.id) === String(session.user?.id)
))

const state = reactive({
  loading: false,
  error: '',
  submission: null,
})

const groupedSections = computed(() => {
  return groupFieldsBySection(state.submission?.values ?? [])
})

function formatAnswer(v) {
  if (v.value_formatted !== undefined && v.value_formatted !== null && v.value_formatted !== '') return v.value_formatted
  if (v.value_label) return v.value_label
  if (v.value_labels?.length) return v.value_labels.join('; ')
  if (v.value === null || v.value === '' || (Array.isArray(v.value) && !v.value.length)) return null
  return String(v.value)
}

function formatFileSize(bytes) {
  if (!bytes || isNaN(bytes)) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function handleDownloadAttachment(field) {
  try {
    const filename = field.file_name || (typeof field.value === 'object' ? field.value?.name : '') || 'anexo'
    await downloadSubmissionAttachment(submissionId.value, field.field_id, filename)
    pushToast(`Download iniciado: ${filename}`)
  } catch (err) {
    pushToast(err.message || 'Erro ao baixar anexo.', 'error')
  }
}

function handlePrint() {
  if (typeof window !== 'undefined' && typeof window.print === 'function') {
    window.print()
  }
}

async function loadSubmission() {
  state.loading = true
  state.error = ''

  try {
    const data = await getSubmission(submissionId.value)
    state.submission = data?.data ?? null
    if (state.submission) {
      setBreadcrumbs([
        { label: 'Submissões', to: '/submissions' },
        { label: `Protocolo #${submissionId.value}` },
      ])
    }
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar submissão.'
  } finally {
    state.loading = false
  }
}

onMounted(() => {
  if (!submissionId.value) {
    router.replace('/submissions')
    return
  }
  loadSubmission()
})
</script>

<template>
  <div class="page-stack" :aria-busy="state.loading">
    <template v-if="state.loading">
      <span class="sr-only" role="status">Carregando prontuário da submissão…</span>
      <div class="surface-card skeleton-list" aria-hidden="true">
        <div class="skeleton-block w-50" style="height: 1.75rem;" />
        <div class="skeleton-block w-65" />
        <div v-for="n in 3" :key="n" class="skeleton-row">
          <div class="skeleton-lines">
            <div class="skeleton-block w-35" />
            <div class="skeleton-block w-80" />
          </div>
        </div>
      </div>
    </template>

    <section v-else-if="state.error" class="surface-card">
      <p class="feedback error" role="alert">{{ state.error }}</p>
      <button class="ghost-button" type="button" @click="loadSubmission">Tentar novamente</button>
    </section>

    <template v-else-if="state.submission">
      <!-- Cabeçalho do Prontuário -->
      <div class="page-header">
        <div>
          <div class="card-heading" style="margin-bottom: 0.35rem;">
            <h1>{{ state.submission.form.name }}</h1>
            <StatusBadge
              :tone="state.submission.submission.status === 'draft' ? 'warning' : 'success'"
              :label="state.submission.submission.status === 'draft' ? 'Rascunho' : 'Submissão confirmada'"
            />
          </div>
          <p class="panel-subtitle">Prontuário estruturado de respostas registradas no sistema.</p>
        </div>

        <div class="panel-actions no-print">
          <RouterLink
            v-if="canResume"
            class="primary-button"
            :to="{ path: `/forms/${state.submission.form.id}/fill`, query: { draft: state.submission.submission.id, returnTo: route.fullPath } }"
          >
            Retomar preenchimento
          </RouterLink>
          <button class="ghost-button" type="button" @click="handlePrint">
            <AppIcon name="printer" :size="16" />
            <span>Imprimir comprovante</span>
          </button>
          <RouterLink
            class="ghost-button"
            :to="{ path: `/forms/${state.submission.form.id}`, query: { returnTo: route.fullPath } }"
          >
            Ver formulário
          </RouterLink>
        </div>
      </div>

      <!-- Grade de Metadados de Protocolo -->
      <div class="record-meta-grid">
        <div class="record-meta-cell">
          <small>Protocolo</small>
          <strong class="tabular">#{{ state.submission.submission.id }}</strong>
        </div>
        <div class="record-meta-cell">
          <small>Versão Utilizada</small>
          <strong class="tabular">Versão {{ state.submission.version.version_number }}</strong>
        </div>
        <div class="record-meta-cell">
          <small>{{ state.submission.submission.submitted_at ? 'Data do Envio' : 'Criado em' }}</small>
          <strong class="tabular">
            {{ formatDate(state.submission.submission.submitted_at || state.submission.submission.created_at) }}
          </strong>
        </div>
        <div v-if="state.submission.user" class="record-meta-cell">
          <small>Respondente</small>
          <strong>{{ state.submission.user.name }}</strong>
          <span class="field-hint">{{ state.submission.user.email }} · ID #{{ state.submission.user.id }}</span>
        </div>
      </div>

      <!-- Respostas em Cards de Grupos Lógicos -->
      <div v-if="!state.submission.values?.length" class="surface-card empty-state">
        <p>Nenhum valor preenchido neste registro.</p>
      </div>

      <div v-else class="cards-layout">
        <section
          v-for="sec in groupedSections"
          :key="sec.id"
          class="form-section-card"
          :aria-labelledby="`sub-sec-title-${sec.id}`"
        >
          <div v-if="!sec.isDefault || groupedSections.length > 1" class="section-card-header">
            <div>
              <h2 :id="`sub-sec-title-${sec.id}`" class="section-card-title">
                <span>{{ sec.title }}</span>
              </h2>
              <p v-if="sec.description" class="section-card-desc">
                {{ sec.description }}
              </p>
            </div>
            <span class="mono-badge">{{ sec.fields.length }} resposta(s)</span>
          </div>

          <dl class="form-grid qa-list">
            <div
              v-for="v in sec.fields"
              :key="v.field_id"
              :class="getFieldWidthClass(v)"
              class="qa-item"
            >
              <dt>{{ v.label }}</dt>
              <dd v-if="v.type === 'file' && v.value" class="attachment-value-cell">
                <div class="attachment-preview-badge">
                  <div class="attachment-badge-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                      <polyline points="14 2 14 8 20 8" />
                      <line x1="16" y1="13" x2="8" y2="13" />
                      <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                  </div>
                  <div class="attachment-badge-meta">
                    <strong class="attachment-badge-name">{{ v.file_name || (typeof v.value === 'object' ? v.value?.name : v.value) }}</strong>
                    <span v-if="v.file_size" class="attachment-badge-size">{{ formatFileSize(v.file_size) }}</span>
                  </div>
                  <button
                    type="button"
                    class="secondary-button btn-sm no-print"
                    :aria-label="`Baixar arquivo ${v.file_name || 'anexo'}`"
                    @click="handleDownloadAttachment(v)"
                  >
                    <AppIcon name="download" :size="14" />
                    <span>Baixar arquivo</span>
                  </button>
                </div>
              </dd>
              <dd v-else :class="{ 'is-empty': formatAnswer(v) === null }">
                {{ formatAnswer(v) ?? 'Não informado' }}
              </dd>
            </div>
          </dl>
        </section>
      </div>
    </template>

    <section v-else class="surface-card">
      <p class="panel-subtitle">Submissão não encontrada.</p>
    </section>
  </div>
</template>
