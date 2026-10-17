<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session } from '../auth/session.js'
import { formatDate } from '../utils/display.js'
import { getSubmission } from '../api'

const route = useRoute()
const router = useRouter()

const canResume = computed(() => state.submission?.submission?.status === 'draft' && String(state.submission?.user?.id) === String(session.user?.id))

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
  <section class="panel panel-forms">
    <template v-if="state.loading">
      <p class="panel-subtitle">Carregando submissão…</p>
    </template>

    <template v-else-if="state.error">
      <p class="feedback error" role="alert">{{ state.error }}</p><button class="ghost-button" type="button" @click="loadSubmission">Tentar novamente</button>
    </template>

    <template v-else-if="state.submission">
      <h1>{{ state.submission.form.name }}</h1>
      <p class="panel-subtitle">
        Versão {{ state.submission.version.version_number }} · {{ state.submission.submission.status === 'draft' ? 'Rascunho' : 'Enviada' }} · {{ formatDate(state.submission.submission.submitted_at || state.submission.submission.created_at) }}
      </p>

      <p v-if="state.submission.user" class="panel-subtitle">Usuário: {{ state.submission.user.name }} · {{ state.submission.user.email }} · ID {{ state.submission.user.id }}</p>
      <div class="panel-actions" style="margin-bottom: 1rem;">
        <RouterLink v-if="canResume" class="primary-button" :to="{ path: `/forms/${state.submission.form.id}/fill`, query: { draft: state.submission.submission.id, returnTo: route.fullPath } }">Retomar este rascunho</RouterLink>
        <RouterLink class="ghost-button" :to="{ path: `/forms/${state.submission.form.id}`, query: { returnTo: route.fullPath } }">Ver formulário</RouterLink>
      </div>
      <ul class="fields-list">
        <li v-for="v in state.submission.values" :key="v.field_id" class="field-row">
          <div class="field-row-main">
            <strong>{{ v.label }}</strong>
            <span class="field-row-meta">{{ v.value_formatted ?? v.value_label ?? (v.value_labels?.length ? v.value_labels.join('; ') : v.value === null || v.value === '' || Array.isArray(v.value) && !v.value.length ? 'Não informado' : v.value) }}</span>
          </div>
        </li>
      </ul>
    </template>

    <template v-else>
      <p class="panel-subtitle">Submissão não encontrada.</p>
    </template>
  </section>
</template>
