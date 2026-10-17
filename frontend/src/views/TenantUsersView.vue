<script setup>
import { onMounted, reactive } from 'vue'
import { useQueryListing } from '../composables/useQueryListing.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'
import { listTenantUsers, createTenantUser, updateTenantUserStatus } from '../api'

import { session } from '../auth/session.js'

const state = reactive({
  error: '',
  message: '',
  saving: false,
  tenant: session.tenant,
})

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'user',
})

const { state: listing, perPage, load: loadContextAndUsers, setPage, setPerPage } = useQueryListing(
  (params, options) => listTenantUsers(session.tenant.id, params, options), 'users',
)

async function handleCreateUser() {
  if (!state.tenant) return
  state.saving = true
  state.error = ''
  state.message = ''

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

    state.message = 'Usuário criado com sucesso.'
    await loadContextAndUsers()
    if (listing.pagination) await setPage(listing.pagination.last_page)
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
  if (!window.confirm(`${user.is_active ? 'Desativar' : 'Ativar'} o usuário ${user.name}? Seus dados e submissões serão preservados.`)) return
  if (!state.tenant) return
  state.error = ''
  state.message = ''
  state.saving = true
  try {
    await updateTenantUserStatus(state.tenant.id, user.id, !user.is_active)
    await loadContextAndUsers()
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao alterar status do usuário.'
  } finally {
    state.saving = false
  }
}

onMounted(() => {
  loadContextAndUsers()
})
</script>

<template>
  <section class="panel panel-forms">
    <h1>Usuários</h1>
    <p class="panel-subtitle">Administração de usuários do tenant atual.</p>

    <p v-if="state.message" class="feedback" role="status">{{ state.message }}</p>
    <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>

    <div class="forms-grid">
      <div class="form-card">
        <h3>Lista</h3>
        <ListContent :loading="listing.loading" :error="listing.error" :empty="!listing.items.length" empty-message="Nenhum usuário cadastrado." @retry="loadContextAndUsers">
          <ul class="fields-list">
            <li v-for="u in listing.items" :key="u.id" class="field-row">
              <div class="field-row-main">
                <strong>{{ u.name }}</strong>
                <span class="field-row-meta">
                  {{ u.email }} · {{ u.role }} · {{ u.is_active ? 'ativo' : 'inativo' }}
                </span>
              </div>
              <button class="ghost-button" type="button" :disabled="state.saving || listing.loading || String(u.id) === String(session.user.id)" @click="toggleUserStatus(u)">
                {{ u.is_active ? 'Desativar' : 'Ativar' }}
              </button>
            </li>
          </ul>
        </ListContent>
        <ListPagination :pagination="listing.pagination" :loading="listing.loading" :per-page="perPage" @page="setPage" @per-page="setPerPage" />
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

          <button class="primary-button" type="submit" :disabled="state.saving || listing.loading">
            <span v-if="!state.saving">Criar usuário</span>
            <span v-else>Salvando…</span>
          </button>
        </form>
      </div>
    </div>
  </section>
</template>
