<script setup>
import { onMounted, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getForm, listFormVersions, publishFormVersion, createFormVersion, updateFormStatus, listSubmissions } from '../api'
import { formatDate } from '../utils/display.js'
import { canManageForms } from '../auth/session.js'
import { usePageBreadcrumbs } from '../composables/useBreadcrumbs.js'
import { pushToast } from '../composables/useToast.js'
import StatusBadge from '../components/StatusBadge.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import AppIcon from '../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const { setBreadcrumbs } = usePageBreadcrumbs()

const state = reactive({
  loading: false,
  error: '',
  form: null,
  versions: [],
  publishing: false,
  publishMessage: '',
  creatingVersion: false,
  togglingStatus: false,
  submissions: [],
  loadingSubmissions: false,
  submissionsError: '',
  confirmAction: '',
})

const formId = computed(() => route.params.formId)
const busy = computed(() => state.publishing || state.creatingVersion || state.togglingStatus)
const draftVersion = computed(() => state.versions.find((v) => !v.is_published) || null)
const canPublish = computed(() => !!draftVersion.value?.fields?.length)
const hasDraft = computed(() => state.versions.some((v) => !v.is_published))
const hasPublished = computed(() => state.versions.some((v) => v.is_published))
const canCreateNewVersion = computed(() => hasPublished.value && !hasDraft.value)
const isActive = computed(() => state.form?.is_active === true)

const orderedVersions = computed(() => (
  [...state.versions].sort((a, b) => b.version_number - a.version_number)
))

const confirmConfig = computed(() => {
  if (state.confirmAction === 'publish') {
    return {
      title: `Publicar versão ${draftVersion.value?.version_number ?? ''}?`,
      description: `Esta versão possui ${draftVersion.value?.fields?.length ?? 0} campo(s). Após a publicação, a estrutura desta versão torna-se imutável e passará a receber novos preenchimentos.`,
      confirmLabel: state.publishing ? 'Publicando…' : 'Publicar versão',
      tone: 'primary',
    }
  }
  if (state.confirmAction === 'new-version') {
    return {
      title: 'Criar nova versão em edição?',
      description: 'Uma nova versão em rascunho será criada copiando os campos e a ordem da última publicação. As versões publicadas e os envios anteriores permanecem intactos.',
      confirmLabel: state.creatingVersion ? 'Criando…' : 'Criar nova versão',
      tone: 'primary',
    }
  }
  if (state.confirmAction === 'toggle-status') {
    return {
      title: isActive.value ? 'Desativar este formulário?' : 'Ativar este formulário?',
      description: isActive.value
        ? 'Formulários inativos impedem novos preenchimentos e edições de rascunhos, preservando integralmente todos os envios já registrados.'
        : 'O formulário voltará a ficar disponível para preenchimento na versão publicada.',
      confirmLabel: state.togglingStatus ? 'Atualizando…' : (isActive.value ? 'Desativar formulário' : 'Ativar formulário'),
      tone: isActive.value ? 'danger' : 'primary',
    }
  }
  return { title: '', description: '', confirmLabel: 'Confirmar', tone: 'primary' }
})

async function loadForm() {
  state.loading = true
  state.error = ''

  try {
    const data = await getForm(formId.value)
    state.form = data?.data?.form ?? null
    if (state.form) {
      setBreadcrumbs([
        { label: 'Formulários', to: '/' },
        { label: state.form.name },
      ])
    }

    const versionsData = await listFormVersions(formId.value)
    state.versions = versionsData?.data?.versions ?? []

    await loadSubmissions()
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar formulário.'
  } finally {
    state.loading = false
  }
}

async function loadSubmissions() {
  state.loadingSubmissions = true
  state.submissionsError = ''

  try {
    const data = await listSubmissions({ form_id: formId.value, per_page: 20 })
    state.submissions = data?.data?.submissions ?? []
  } catch (error) {
    state.submissionsError = error?.body?.message || 'Erro ao carregar submissões.'
  } finally {
    state.loadingSubmissions = false
  }
}

async function executeConfirmedAction() {
  const action = state.confirmAction
  if (!action) return
  if (action === 'publish') await executePublish()
  else if (action === 'new-version') await executeCreateNewVersion()
  else if (action === 'toggle-status') await executeToggleActive()
  state.confirmAction = ''
}

async function executePublish() {
  if (busy.value || !canManageForms.value || !canPublish.value) return
  const draft = state.versions.find((v) => !v.is_published)
  if (!draft) return

  state.publishing = true
  state.publishMessage = ''
  state.error = ''

  try {
    const data = await publishFormVersion(formId.value, draft.id)
    state.publishMessage = data?.message || 'Versão publicada com sucesso.'
    pushToast(state.publishMessage)
    await loadForm()
  } catch (error) {
    if (error.status === 422) {
      state.error = error?.body?.message || 'Versão não pode ser publicada.'
    } else if (error.status === 403) {
      state.error = 'Você não tem permissão para publicar este formulário.'
    } else {
      state.error = error?.body?.message || 'Erro ao publicar versão.'
    }
  } finally {
    state.publishing = false
  }
}

async function executeCreateNewVersion() {
  if (busy.value || !canManageForms.value || !canCreateNewVersion.value) return

  state.creatingVersion = true
  state.publishMessage = ''
  state.error = ''

  try {
    const data = await createFormVersion(formId.value)
    state.publishMessage = data?.message || 'Nova versão criada com sucesso.'
    pushToast(state.publishMessage)
    if (data?.data?.version?.id) {
      await router.push({ path: `/forms/${formId.value}/versions/${data.data.version.id}/fields`, query: { returnTo: route.fullPath } })
    } else {
      await loadForm()
    }
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao criar nova versão.'
  } finally {
    state.creatingVersion = false
  }
}

async function executeToggleActive() {
  if (busy.value || !canManageForms.value || !state.form) return

  state.togglingStatus = true
  state.error = ''
  state.publishMessage = ''

  try {
    const data = await updateFormStatus(formId.value, !isActive.value)
    state.form = data?.data ?? state.form
    state.publishMessage = data?.message || 'Status do formulário atualizado.'
    pushToast(state.publishMessage)
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao alterar status do formulário.'
  } finally {
    state.togglingStatus = false
  }
}

onMounted(() => {
  if (!formId.value) {
    router.replace('/')
    return
  }
  loadForm()
})
</script>

<template>
  <div class="page-stack" :aria-busy="state.loading">
    <template v-if="state.loading">
      <span class="sr-only" role="status">Carregando formulário…</span>
      <div class="surface-card skeleton-list" aria-hidden="true">
        <div class="skeleton-block w-50" style="height: 1.75rem;" />
        <div class="skeleton-block w-80" />
        <div class="skeleton-row">
          <div class="skeleton-lines">
            <div class="skeleton-block w-35" />
            <div class="skeleton-block w-65" />
          </div>
          <div class="skeleton-block btn" />
        </div>
      </div>
    </template>

    <section v-else-if="state.error && !state.form" class="surface-card">
      <p class="feedback error" role="alert">{{ state.error }}</p>
      <button class="ghost-button" type="button" @click="loadForm">Tentar novamente</button>
    </section>

    <template v-else-if="state.form">
      <!-- Hero Card do Formulário -->
      <section class="panel">
        <div class="panel-header" style="margin-bottom: 1rem;">
          <div>
            <div class="card-heading" style="margin-bottom: 0.4rem;">
              <h1>{{ state.form.name }}</h1>
              <StatusBadge
                :tone="isActive ? 'success' : 'muted'"
                :label="isActive ? 'Ativo' : 'Inativo'"
              />
            </div>
            <p class="panel-subtitle">{{ state.form.description || 'Sem descrição cadastrada.' }}</p>
          </div>

          <div class="panel-actions">
            <RouterLink
              v-if="isActive && hasPublished"
              class="primary-button"
              :to="{ path: `/forms/${formId}/fill`, query: { returnTo: route.fullPath } }"
            >
              <span>Preencher formulário</span>
              <AppIcon name="arrow-right" :size="15" />
            </RouterLink>

            <RouterLink
              v-if="canManageForms && draftVersion"
              class="ghost-button"
              :to="{ path: `/forms/${formId}/versions/${draftVersion.id}/fields`, query: { returnTo: route.fullPath } }"
            >
              Editar campos (v{{ draftVersion.version_number }})
            </RouterLink>

            <button
              v-if="canManageForms && canCreateNewVersion"
              class="ghost-button"
              type="button"
              :disabled="busy"
              @click="state.confirmAction = 'new-version'"
            >
              <AppIcon name="plus" :size="15" />
              <span>{{ state.creatingVersion ? 'Criando…' : 'Nova versão' }}</span>
            </button>

            <button
              v-if="canManageForms && draftVersion"
              class="primary-button"
              type="button"
              :disabled="!canPublish || busy"
              @click="state.confirmAction = 'publish'"
            >
              <AppIcon name="check" :size="15" />
              <span>{{ state.publishing ? 'Publicando…' : `Publicar v${draftVersion.version_number}` }}</span>
            </button>

            <button
              v-if="canManageForms"
              class="ghost-button"
              :class="{ 'ghost-danger': isActive }"
              type="button"
              :disabled="busy"
              @click="state.confirmAction = 'toggle-status'"
            >
              {{ state.togglingStatus ? 'Atualizando…' : (isActive ? 'Desativar' : 'Ativar') }}
            </button>
          </div>
        </div>

        <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>

        <div v-if="state.form.latest_version" class="form-meta">
          <StatusBadge
            :tone="state.form.latest_version.is_published ? 'success' : 'warning'"
            :label="state.form.latest_version.is_published ? `Versão ${state.form.latest_version.version_number} vigente` : `Versão ${state.form.latest_version.version_number} em edição`"
          />
          <span v-if="state.form.latest_version.fields_count !== undefined" class="tabular">
            {{ state.form.latest_version.fields_count }} campo(s) configurado(s)
          </span>
        </div>
      </section>

      <!-- Grade Assimétrica: Submissões Recentes + Timeline de Versões -->
      <div :class="canManageForms ? 'detail-grid' : 'page-stack'">
        <section class="table-card" aria-labelledby="recent-submissions-heading">
          <div class="panel-header" style="padding: 1.25rem 1.25rem 0;">
            <div>
              <h2 id="recent-submissions-heading">Submissões recentes</h2>
              <p class="panel-subtitle">Últimos envios registrados para este formulário.</p>
            </div>
            <RouterLink class="ghost-button" :to="`/submissions?form_id=${formId}`">
              Ver todas
            </RouterLink>
          </div>

          <div v-if="state.loadingSubmissions" class="skeleton-list" aria-hidden="true" style="padding: 1rem;">
            <div v-for="n in 2" :key="n" class="skeleton-row">
              <div class="skeleton-lines">
                <div class="skeleton-block w-35" />
                <div class="skeleton-block w-65" />
              </div>
              <div class="skeleton-block btn" />
            </div>
          </div>
          <div v-else-if="state.submissionsError" style="padding: 1rem;">
            <p class="feedback error" role="alert">{{ state.submissionsError }}</p>
          </div>
          <div v-else-if="!state.submissions.length" style="padding: 1.25rem;">
            <div class="empty-state">
              <span class="empty-state-icon" aria-hidden="true"><AppIcon name="inbox" :size="20" /></span>
              <p>Nenhuma submissão enviada para este formulário até o momento.</p>
            </div>
          </div>
          <div v-else class="data-table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">Protocolo</th>
                  <th scope="col">Versão</th>
                  <th scope="col">Data</th>
                  <th scope="col" class="col-right">Ação</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in state.submissions" :key="s.id">
                  <td data-label="Protocolo">
                    <span class="mono-badge">#{{ s.id }}</span>
                  </td>
                  <td data-label="Versão" class="tabular">
                    v{{ s.version.version_number }} ({{ s.fields_count }} campos)
                  </td>
                  <td data-label="Data" class="tabular">
                    {{ formatDate(s.submitted_at) }}
                  </td>
                  <td data-label="Ação" class="col-right">
                    <RouterLink
                      class="ghost-button"
                      :to="{ path: `/submissions/${s.id}`, query: { returnTo: route.fullPath } }"
                    >
                      Prontuário
                    </RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Coluna Lateral: Timeline Vertical de Versões -->
        <section v-if="canManageForms" class="surface-card" aria-labelledby="versions-heading">
          <div class="card-heading">
            <h2 id="versions-heading">Linha do tempo de versões</h2>
            <AppIcon name="layers" :size="18" />
          </div>
          <p class="panel-subtitle">Evolução estrutural e imutabilidade das versões.</p>

          <p v-if="draftVersion && !draftVersion.fields?.length" class="feedback warning" role="status" style="margin-top: 0.85rem;">
            Adicione ao menos um campo à versão em rascunho antes de publicá-la.
          </p>

          <ol class="version-timeline">
            <li
              v-for="version in orderedVersions"
              :key="version.id"
              class="timeline-item"
              :class="version.is_published ? 'is-published' : 'is-draft'"
            >
              <span class="timeline-dot" aria-hidden="true" />
              <div class="cell-stack" style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                  <strong>Versão {{ version.version_number }}</strong>
                  <StatusBadge
                    :tone="version.is_published ? 'success' : 'warning'"
                    :label="version.is_published ? 'Publicada' : 'Em edição'"
                  />
                </div>
                <small class="tabular">
                  {{ version.fields?.length ?? 0 }} campo(s)
                  <template v-if="version.published_at"> · {{ formatDate(version.published_at) }}</template>
                </small>
              </div>
              <RouterLink
                class="ghost-button"
                :to="{ path: `/forms/${formId}/versions/${version.id}/fields`, query: { returnTo: route.fullPath } }"
              >
                {{ version.is_published ? 'Campos' : 'Editar' }}
              </RouterLink>
            </li>
          </ol>
        </section>
      </div>

      <ConfirmDialog
        :open="!!state.confirmAction"
        dialog-id="form-detail-confirm"
        :title="confirmConfig.title"
        :description="confirmConfig.description"
        :confirm-label="confirmConfig.confirmLabel"
        :tone="confirmConfig.tone"
        :busy="busy"
        @confirm="executeConfirmedAction"
        @cancel="state.confirmAction = ''"
      />
    </template>

    <section v-else class="surface-card">
      <p class="panel-subtitle">Formulário não encontrado.</p>
    </section>
  </div>
</template>
