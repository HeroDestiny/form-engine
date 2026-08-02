<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminListTenants, adminCreateTenant, adminUpdateTenantStatus, getCurrentUser } from '../api'

const router = useRouter()

const state = reactive({
  loading: false,
  error: '',
  tenants: [],
  pagination: null,
  saving: false,
})

const form = reactive({
  name: '',
  slug: '',
})

const isAdminSistema = ref(false)

async function ensureAdminSistema() {
  try {
    const data = await getCurrentUser()
    const user = data?.data?.user ?? null
    if (!user) {
      await router.replace('/login')
      return false
    }
    isAdminSistema.value = user.role === 'admin-sistema'
    if (!isAdminSistema.value) {
      state.error = 'Apenas admin-sistema pode gerenciar tenants.'
      return false
    }
    return true
  } catch {
    await router.replace('/login')
    return false
  }
}

async function loadTenants() {
  state.loading = true
  state.error = ''

  try {
    const data = await adminListTenants({ per_page: 50 })
    state.tenants = data?.data?.tenants ?? []
    state.pagination = data?.data?.pagination ?? null
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar tenants.'
  } finally {
    state.loading = false
  }
}

async function handleCreateTenant() {
  state.saving = true
  state.error = ''
  try {
    await adminCreateTenant({ name: form.name, slug: form.slug })
    form.name = ''
    form.slug = ''
    await loadTenants()
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.error = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      state.error = error?.body?.message || 'Erro ao criar tenant.'
    }
  } finally {
    state.saving = false
  }
}

async function toggleTenantStatus(tenant) {
  state.error = ''
  try {
    await adminUpdateTenantStatus(tenant.id, !tenant.is_active)
    await loadTenants()
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao alterar status do tenant.'
  }
}

onMounted(async () => {
  const ok = await ensureAdminSistema()
  if (!ok) return
  await loadTenants()
})
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Administração de tenants</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push('/')">Voltar</button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <h2>Tenants</h2>
        <p class="panel-subtitle">Criação e ativação/desativação de tenants (admin-sistema).</p>

        <p v-if="state.error" class="feedback error">{{ state.error }}</p>
        <p v-if="state.loading" class="panel-subtitle">Carregando tenants…</p>

        <div class="forms-grid" v-if="!state.loading">
          <div class="form-card">
            <h3>Lista</h3>
            <div v-if="!state.tenants.length" class="empty-state">
              <p>Nenhum tenant cadastrado.</p>
            </div>
            <ul v-else class="fields-list">
              <li v-for="t in state.tenants" :key="t.id" class="field-row">
                <div class="field-row-main">
                  <strong>{{ t.name }}</strong>
                  <span class="field-row-meta">
                    slug: {{ t.slug }} · {{ t.is_active ? 'ativo' : 'inativo' }}
                  </span>
                </div>
                <button class="ghost-button" type="button" @click="toggleTenantStatus(t)">
                  {{ t.is_active ? 'Desativar' : 'Ativar' }}
                </button>
              </li>
            </ul>
          </div>

          <div class="form-card">
            <h3>Novo tenant</h3>
            <form class="form" @submit.prevent="handleCreateTenant">
              <label class="field">
                <span>Nome</span>
                <input v-model="form.name" type="text" required minlength="3" maxlength="255" />
              </label>
              <label class="field">
                <span>Slug</span>
                <input
                  v-model="form.slug"
                  type="text"
                  required
                  pattern="^[a-z0-9-]+$"
                  placeholder="ex: prefeitura-demo"
                />
              </label>
              <button class="primary-button" type="submit" :disabled="state.saving">
                <span v-if="!state.saving">Criar tenant</span>
                <span v-else>Salvando…</span>
              </button>
            </form>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
