<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getSubmission } from '../api'

const route = useRoute()
const router = useRouter()

const submissionId = computed(() => route.params.submissionId)

const state = reactive({
  loading: false,
  error: '',
  submission: null,
})

async function loadSubmission() {
  state.loading = true
  state.error = ''

  try {
    const data = await getSubmission(submissionId.value)
    state.submission = data?.data ?? null
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
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Detalhe da submissão</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push('/submissions')">Voltar</button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <template v-if="state.loading">
          <p class="panel-subtitle">Carregando submissão…</p>
        </template>

        <template v-else-if="state.error">
          <p class="feedback error">{{ state.error }}</p>
        </template>

        <template v-else-if="state.submission">
          <h2>{{ state.submission.form.name }}</h2>
          <p class="panel-subtitle">
            Versão {{ state.submission.version.version_number }} · status {{ state.submission.submission.status }}
          </p>

          <ul class="fields-list">
            <li v-for="v in state.submission.values" :key="v.field_id" class="field-row">
              <div class="field-row-main">
                <strong>{{ v.label }}</strong>
                <span class="field-row-meta">{{ v.value }}</span>
              </div>
            </li>
          </ul>
        </template>

        <template v-else>
          <p class="panel-subtitle">Submissão não encontrada.</p>
        </template>
      </section>
    </main>
  </div>
</template>
