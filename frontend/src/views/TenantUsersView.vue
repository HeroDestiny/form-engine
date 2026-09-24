<script setup>
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { getCurrentUser, listTenantUsers, createTenantUser, updateTenantUserStatus } from '../api'

const router = useRouter()

const state = reactive({
  loading: false,
  error: '',
  users: [],
  pagination: null,
  saving: false,
  tenant: null,
})

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'user',
})

async function loadContextAndUsers() {
  state.loading = true
  state.error = ''

  try {
    const me = await getCurrentUser()
    const user = me?.data?.user ?? null
    const tenant = me?.data?.tenant ?? null

    if (!user || !tenant) {
      await router.replace('/login')
      return
    }

    if (user.role !== 'admin') {
      state.error = 'Apenas admin do tenant pode gerenciar usuários.'
      return
    }

    state.tenant = tenant

    const data = await listTenantUsers(tenant.id, { per_page: 50 })
    state.users = data?.data?.users ?? []
    state.pagination = data?.data?.pagination ?? null
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar usuários.'
  } finally {
    state.loading = false
  }
}

async function handleCreateUser() {
  if (!state.tenant) return
  state.saving = true
  state.error = ''

  try {
    await createTenantUser(state.tenant.id, {
      name: form.name,
      email: form.email,
      password: form.password,
      role: form.role,
    })

    form.name = ''
    form.email = ''
    form.password = ''
    form.role = 'user'

    await loadContextAndUsers()
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.error = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      state.error = error?.body?.message || 'Erro ao criar usuário.'
    }
  } finally {
    state.saving = false
  }
}

async function toggleUserStatus(user) {
  if (!state.tenant) return
  state.error = ''
  try {
    await updateTenantUserStatus(state.tenant.id, user.id, !user.is_active)
    await loadContextAndUsers()
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao alterar status do usuário.'
  }
}

onMounted(() => {
  loadContextAndUsers()
})
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Usuários do tenant</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push('/')">Voltar</button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <h2>Usuários</h2>
        <p class="panel-subtitle">Administração de usuários do tenant atual.</p>

        <p v-if="state.error" class="feedback error">{{ state.error }}</p>
        <p v-if="state.loading" class="panel-subtitle">Carregando usuários…</p>

        <div class="forms-grid" v-if="!state.loading">
          <div class="form-card">
            <h3>Lista</h3>
            <div v-if="!state.users.length" class="empty-state">
              <p>Nenhum usuário cadastrado.</p>
            </div>
            <ul v-else class="fields-list">
              <li v-for="u in state.users" :key="u.id" class="field-row">
                <div class="field-row-main">
                  <strong>{{ u.name }}</strong>
                  <span class="field-row-meta">
                    {{ u.email }} · {{ u.role }} · {{ u.is_active ? 'ativo' : 'inativo' }}
                  </span>
                </div>
                <button class="ghost-button" type="button" @click="toggleUserStatus(u)">
                  {{ u.is_active ? 'Desativar' : 'Ativar' }}
                </button>
              </li>
            </ul>
          </div>

          <div class="form-card">
            <h3>Novo usuário</h3>
            <form class="form" @submit.prevent="handleCreateUser">
              <label class="field">
                <span>Nome</span>
                <input v-model="form.name" type="text" required minlength="3" maxlength="255" />
              </label>

              <label class="field">
                <span>E-mail</span>
                <input v-model="form.email" type="email" required />
              </label>

              <label class="field">
                <span>Senha</span>
                <input v-model="form.password" type="password" required minlength="8" />
              </label>

              <label class="field">
                <span>Papel</span>
                <select v-model="form.role">
                  <option value="user">user</option>
                  <option value="manager">manager</option>
                  <option value="admin">admin</option>
                </select>
              </label>

              <button class="primary-button" type="submit" :disabled="state.saving">
                <span v-if="!state.saving">Criar usuário</span>
                <span v-else>Salvando…</span>
              </button>
            </form>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
