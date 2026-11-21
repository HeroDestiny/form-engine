<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useQueryListing } from '../composables/useQueryListing.js'
import { initialsFromName } from '../composables/useTheme.js'
import { pushToast } from '../composables/useToast.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import AppIcon from '../components/AppIcon.vue'
import { listTenantUsers, createTenantUser, updateTenantUserStatus } from '../api'
import { session } from '../auth/session.js'

const roleLabels = {
  user: 'Usuário (preenchimento)',
  manager: 'Gestor (formulários e CSV)',
  admin: 'Administrador da unidade',
  'admin-sistema': 'Administrador do sistema',
}

const createModalOpen = ref(false)
const createFormRef = ref(null)

const state = reactive({
  error: '',
  createError: '',
  saving: false,
  tenant: session.tenant,
  confirmUser: null,
})

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'user',
})

const { state: listing, perPage, load: loadContextAndUsers, setPage, setPerPage } = useQueryListing(
  (params, options) => listTenantUsers(session.tenant.id, params, options),
  'users',
)

function openCreateModal() {
  state.createError = ''
  createModalOpen.value = true
}

function submitCreateModal() {
  if (createFormRef.value && !createFormRef.value.reportValidity()) return
  void handleCreateUser()
}

async function handleCreateUser() {
  if (!state.tenant) return
  state.saving = true
  state.createError = ''

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
    createModalOpen.value = false

    pushToast('Usuário criado com sucesso.')
    await loadContextAndUsers()
    if (listing.pagination) await setPage(listing.pagination.last_page)
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.createError = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      state.createError = error?.body?.message || 'Erro ao criar usuário.'
    }
  } finally {
    state.saving = false
  }
}

async function confirmToggleUserStatus() {
  const user = state.confirmUser
  if (!user || !state.tenant) return
  state.error = ''
  state.saving = true
  try {
    const nextActive = !user.is_active
    await updateTenantUserStatus(state.tenant.id, user.id, nextActive)
    state.confirmUser = null
    pushToast(`Conta de “${user.name}” ${nextActive ? 'ativada' : 'desativada'} com sucesso.`)
    await loadContextAndUsers()
  } catch (error) {
    state.confirmUser = null
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
  <div class="page-stack">
    <div class="page-header">
      <div>
        <h1>Usuários da Unidade</h1>
        <p class="panel-subtitle">
          Gerencie credenciais e perfis de acesso vinculados à unidade <strong>{{ state.tenant?.name || 'atual' }}</strong>.
        </p>
      </div>
      <div class="panel-actions">
        <button class="ghost-button" type="button" :disabled="listing.loading" @click="loadContextAndUsers">
          <AppIcon name="refresh" :size="16" />
          <span>Atualizar</span>
        </button>
        <button class="primary-button" type="button" @click="openCreateModal">
          <AppIcon name="plus" :size="16" />
          <span>Novo usuário</span>
        </button>
      </div>
    </div>

    <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>

    <div class="table-card">
      <ListContent
        :loading="listing.loading"
        :error="listing.error"
        :empty="!listing.items.length"
        empty-message="Nenhum usuário cadastrado nesta unidade."
        @retry="loadContextAndUsers"
      >
        <div class="data-table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Usuário</th>
                <th scope="col">Perfil de Acesso</th>
                <th scope="col">Status</th>
                <th scope="col" class="col-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in listing.items" :key="u.id">
                <td data-label="Usuário">
                  <div class="cell-user">
                    <span class="avatar-badge" aria-hidden="true">{{ initialsFromName(u.name) }}</span>
                    <div class="cell-stack">
                      <div>
                        <strong>{{ u.name }}</strong>
                        <span v-if="String(u.id) === String(session.user.id)" class="field-hint"> (sua conta)</span>
                      </div>
                      <small>{{ u.email }} · ID #{{ u.id }}</small>
                    </div>
                  </div>
                </td>
                <td data-label="Perfil de Acesso">
                  {{ roleLabels[u.role] || u.role }}
                </td>
                <td data-label="Status">
                  <StatusBadge
                    :tone="u.is_active ? 'success' : 'muted'"
                    :label="u.is_active ? 'Ativo' : 'Inativo'"
                  />
                </td>
                <td data-label="Ações" class="col-right">
                  <button
                    class="ghost-button"
                    :class="{ 'ghost-danger': u.is_active && String(u.id) !== String(session.user.id) }"
                    type="button"
                    :disabled="state.saving || listing.loading || String(u.id) === String(session.user.id)"
                    :aria-label="`${u.is_active ? 'Desativar' : 'Ativar'} usuário ${u.name}`"
                    @click="state.confirmUser = u"
                  >
                    {{ u.is_active ? 'Desativar' : 'Ativar' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </ListContent>

      <ListPagination
        select-id="users-per-page"
        :pagination="listing.pagination"
        :loading="listing.loading"
        :per-page="perPage"
        @page="setPage"
        @per-page="setPerPage"
      />
    </div>

    <!-- Modal de Criação de Usuário -->
    <ConfirmDialog
      :open="createModalOpen"
      dialog-id="create-user-modal"
      title="Cadastrar novo usuário"
      :description="`A conta será criada na unidade ${state.tenant?.name || 'atual'}.`"
      :confirm-label="state.saving ? 'Salvando…' : 'Criar usuário'"
      :busy="state.saving"
      @confirm="submitCreateModal"
      @cancel="createModalOpen = false"
    >
      <form ref="createFormRef" class="form" style="margin-top: 0.75rem;" @submit.prevent="handleCreateUser">
        <div class="field">
          <label for="user-name">
            <span>Nome completo</span>
            <span class="required-indicator" aria-hidden="true">*</span>
          </label>
          <input
            id="user-name"
            v-model="form.name"
            type="text"
            required
            minlength="3"
            maxlength="255"
            placeholder="Ex.: Maria Oliveira"
          />
        </div>

        <div class="field">
          <label for="user-email">
            <span>E-mail institucional</span>
            <span class="required-indicator" aria-hidden="true">*</span>
          </label>
          <input
            id="user-email"
            v-model="form.email"
            type="email"
            required
            placeholder="maria.oliveira@organizacao.gov.br"
          />
        </div>

        <div class="field">
          <label for="user-password">
            <span>Senha inicial</span>
            <span class="required-indicator" aria-hidden="true">*</span>
          </label>
          <input
            id="user-password"
            v-model="form.password"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            aria-describedby="user-password-hint"
          />
          <small id="user-password-hint" class="field-hint">Mínimo de 8 caracteres.</small>
        </div>

        <div class="field">
          <label for="user-role">Perfil de acesso</label>
          <select id="user-role" v-model="form.role">
            <option value="user">Usuário — Preenchimento e próprios envios</option>
            <option value="manager">Gestor — Criação de formulários e exportação CSV</option>
            <option value="admin">Administrador — Gestão de usuários e auditoria</option>
          </select>
        </div>

        <p v-if="state.createError" class="feedback error" role="alert" style="margin-bottom: 0;">
          {{ state.createError }}
        </p>
      </form>
    </ConfirmDialog>

    <!-- Modal de Confirmação de Ativação/Desativação -->
    <ConfirmDialog
      :open="!!state.confirmUser"
      dialog-id="toggle-user-confirm"
      :title="state.confirmUser?.is_active ? `Desativar a conta de “${state.confirmUser.name}”?` : `Ativar a conta de “${state.confirmUser?.name || ''}”?`"
      :description="state.confirmUser?.is_active
        ? 'O usuário não poderá realizar novos logins. Seus dados históricos, auditoria e submissões permanecem preservados.'
        : 'O usuário voltará a poder autenticar-se nesta unidade administrativa.'"
      :confirm-label="state.saving ? 'Atualizando…' : (state.confirmUser?.is_active ? 'Desativar usuário' : 'Ativar usuário')"
      :tone="state.confirmUser?.is_active ? 'danger' : 'primary'"
      :busy="state.saving"
      @confirm="confirmToggleUserStatus"
      @cancel="state.confirmUser = null"
    />
  </div>
</template>
