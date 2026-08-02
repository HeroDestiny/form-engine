<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getForm, listFormVersions, publishFormVersion, createFormVersion, updateFormStatus, listSubmissions } from '../api'

const route = useRoute()
const router = useRouter()

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
})

const formId = computed(() => route.params.formId)

async function loadForm() {
  state.loading = true
  state.error = ''

  try {
    const data = await getForm(formId.value)
    state.form = data?.data?.form ?? null

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

const canPublish = computed(() => {
  if (!state.versions.length) return false
  const draft = state.versions.find((v) => !v.is_published)
  return !!draft
})

const draftVersion = computed(() => state.versions.find((v) => !v.is_published) || null)

const hasDraft = computed(() => state.versions.some((v) => !v.is_published))
const hasPublished = computed(() => state.versions.some((v) => v.is_published))
const canCreateNewVersion = computed(() => hasPublished.value && !hasDraft.value)

const isActive = computed(() => state.form?.is_active === true)

async function handlePublish() {
  if (!canPublish.value) return

  const draft = state.versions.find((v) => !v.is_published)
  if (!draft) return

  state.publishing = true
  state.publishMessage = ''
  state.error = ''

  try {
    const data = await publishFormVersion(formId.value, draft.id)
    state.publishMessage = data?.message || 'Versão publicada com sucesso.'
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

async function handleCreateNewVersion() {
  if (!canCreateNewVersion.value) return

  state.creatingVersion = true
  state.publishMessage = ''
  state.error = ''

  try {
    const data = await createFormVersion(formId.value)
    state.publishMessage = data?.message || 'Nova versão criada com sucesso.'
    await loadForm()
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao criar nova versão.'
  } finally {
    state.creatingVersion = false
  }
}

async function handleToggleActive() {
  if (!state.form) return

  state.togglingStatus = true
  state.error = ''
  state.publishMessage = ''

  try {
    const data = await updateFormStatus(formId.value, !isActive.value)
    state.form = data?.data ?? state.form
    state.publishMessage = data?.message || 'Status do formulário atualizado.'
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao alterar status do formulário.'
  } finally {
    state.togglingStatus = false
  }
}

function goBack() {
  router.push('/')
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
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Detalhe do formulário</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="goBack">Voltar</button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <template v-if="state.loading">
          <p class="panel-subtitle">Carregando formulário…</p>
        </template>

        <template v-else-if="state.error">
          <p class="feedback error">{{ state.error }}</p>
          <button class="ghost-button" type="button" @click="loadForm">Tentar novamente</button>
        </template>

        <template v-else-if="state.form">
          <div class="panel-header">
            <div>
              <h2>{{ state.form.name }}</h2>
              <p class="panel-subtitle">
                {{ state.form.description || 'Sem descrição.' }} ·
                {{ isActive ? 'ativo' : 'inativo' }}
              </p>
            </div>

            <div class="panel-actions">
              <RouterLink
                v-if="state.form.latest_version"
                class="ghost-button"
                :to="`/forms/${formId}/fill`"
              >
                Preencher formulário
              </RouterLink>

              <RouterLink
                v-if="draftVersion"
                class="ghost-button"
                :to="`/forms/${formId}/versions/${draftVersion.id}/fields`"
              >
                Gerenciar campos da versão em edição
              </RouterLink>

              <button
                v-if="canCreateNewVersion"
                class="ghost-button"
                type="button"
                :disabled="state.creatingVersion"
                @click="handleCreateNewVersion"
              >
                <span v-if="!state.creatingVersion">Criar nova versão</span>
                <span v-else>Criando…</span>
              </button>

              <button
                class="ghost-button"
                type="button"
                :disabled="!canPublish || state.publishing"
                @click="handlePublish"
              >
                <span v-if="!state.publishing">Publicar versão em edição</span>
                <span v-else>Publicando…</span>
              </button>

              <button
                class="ghost-button"
                type="button"
                :disabled="state.togglingStatus"
                @click="handleToggleActive"
              >
                <span v-if="!state.togglingStatus">
                  {{ isActive ? 'Desativar formulário' : 'Ativar formulário' }}
                </span>
                <span v-else>Atualizando…</span>
              </button>
            </div>
          </div>

          <div class="form-meta" v-if="state.form.latest_version">
            <span>Versão publicada {{ state.form.latest_version.version_number }}</span>
            <span v-if="state.form.latest_version.fields_count !== undefined">
              {{ state.form.latest_version.fields_count }} campo(s)
            </span>
          </div>

          <p v-if="state.publishMessage" class="panel-subtitle">{{ state.publishMessage }}</p>

          <div class="form-card" style="margin-top: 1.5rem;">
            <div class="panel-header">
              <div>
                <h3>Submissões deste formulário</h3>
                <p class="panel-subtitle">
                  Listagem resumida das submissões vinculadas a este formulário.
                </p>
              </div>
            </div>

            <p v-if="state.loadingSubmissions" class="panel-subtitle">Carregando submissões…</p>
            <p v-else-if="state.submissionsError" class="feedback error">{{ state.submissionsError }}</p>

            <div v-else>
              <div v-if="!state.submissions.length" class="empty-state">
                <p>Nenhuma submissão encontrada para este formulário.</p>
              </div>
              <ul v-else class="fields-list">
                <li v-for="s in state.submissions" :key="s.id" class="field-row">
                  <div class="field-row-main">
                    <strong>#{{ s.id }}</strong>
                    <span class="field-row-meta">
                      versão {{ s.version.version_number }} · {{ s.submitted_at }} ·
                      {{ s.fields_count }} campo(s)
                    </span>
                  </div>
                  <button
                    class="ghost-button"
                    type="button"
                    @click="router.push(`/submissions/${s.id}`)"
                  >
                    Ver detalhes
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </template>

        <template v-else>
          <p class="panel-subtitle">Formulário não encontrado.</p>
        </template>
      </section>
    </main>
  </div>
</template>
