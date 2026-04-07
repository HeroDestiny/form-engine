<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getForm, listFormVersions, publishFormVersion } from '../api'

const route = useRoute()
const router = useRouter()

const state = reactive({
  loading: false,
  error: '',
  form: null,
  versions: [],
  publishing: false,
  publishMessage: '',
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
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar formulário.'
  } finally {
    state.loading = false
  }
}

const canPublish = computed(() => {
  if (!state.versions.length) return false
  const draft = state.versions.find((v) => !v.is_published)
  return !!draft
})

const draftVersion = computed(() => state.versions.find((v) => !v.is_published) || null)

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
              <p class="panel-subtitle">{{ state.form.description || 'Sem descrição.' }}</p>
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
                class="ghost-button"
                type="button"
                :disabled="!canPublish || state.publishing"
                @click="handlePublish"
              >
                <span v-if="!state.publishing">Publicar versão em edição</span>
                <span v-else>Publicando…</span>
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
        </template>

        <template v-else>
          <p class="panel-subtitle">Formulário não encontrado.</p>
        </template>
      </section>
    </main>
  </div>
</template>
