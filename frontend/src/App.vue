<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { logout, getCurrentUser, listForms } from './api'

const router = useRouter()

const state = reactive({
  user: null,
  tenant: null,
  forms: [],
})

const loading = ref(false)
const errorMessage = ref('')

async function loadSession() {
  loading.value = true
  errorMessage.value = ''

  try {
    const data = await getCurrentUser()
    state.user = data?.data?.user ?? null
    state.tenant = data?.data?.tenant ?? null

    if (!state.user) {
      await router.replace('/login')
      return
    }

    await loadForms()
  } catch {
    state.user = null
    state.tenant = null
    await router.replace('/login')
  } finally {
    loading.value = false
  }
}

async function loadForms() {
  try {
    const data = await listForms()
    state.forms = data?.data?.forms ?? []
  } catch (error) {
    errorMessage.value = error?.body?.message || 'Erro ao carregar formulários.'
  }
}

async function handleLogout() {
  await logout()
  state.user = null
  state.tenant = null
  state.forms = []
  await router.push('/login')
}

onMounted(() => {
  loadSession()
})
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Frontend · Vue 3 SPA</p>
        </div>
      </div>

      <div v-if="state.user" class="user-chip">
        <div class="user-main">
          <span class="user-name">{{ state.user.name }}</span>
          <span class="user-role">{{ state.user.role }}</span>
        </div>
        <div class="user-sub" v-if="state.tenant">
          {{ state.tenant.name }}
        </div>
        <button class="ghost-button" type="button" @click="handleLogout">Sair</button>
      </div>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <div class="panel-header">
          <div>
            <h2>Formulários disponíveis</h2>
            <p class="panel-subtitle">
              Lista de formulários ativos com versão publicada para o seu tenant.
            </p>
          </div>
          <div class="panel-actions">
            <button class="ghost-button" type="button" @click="loadForms">Atualizar</button>
            <button
              v-if="state.user && (state.user.role === 'manager' || state.user.role === 'admin')"
              class="ghost-button"
              type="button"
              @click="router.push('/forms/new')"
            >
              Novo formulário
            </button>
          </div>
        </div>

        <p v-if="loading" class="panel-subtitle">Carregando…</p>
        <p v-if="!loading && errorMessage" class="feedback error">{{ errorMessage }}</p>

        <div v-if="!loading && !state.forms.length && !errorMessage" class="empty-state">
          <p>Nenhum formulário disponível no momento.</p>
        </div>

        <div v-else class="forms-grid">
          <article v-for="form in state.forms" :key="form.id" class="form-card">
            <RouterLink :to="`/forms/${form.id}`">
              <h3>{{ form.name }}</h3>
              <p class="form-description">{{ form.description || 'Sem descrição.' }}</p>
              <div class="form-meta" v-if="form.latest_version">
                <span>Versão {{ form.latest_version.version_number }}</span>
                <span v-if="form.latest_version.fields_count !== undefined">
                  {{ form.latest_version.fields_count }} campo(s)
                </span>
              </div>
            </RouterLink>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>
