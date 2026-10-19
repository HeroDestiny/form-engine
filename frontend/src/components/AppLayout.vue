<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { canAdminister, canManageTenants, session, signOut } from '../auth/session.js'
import { safeReturnPath } from '../auth/permissions.js'

const route = useRoute()
const router = useRouter()
const leaving = ref(false)
const mainContent = ref(null)
const isPrivate = computed(() => !route.meta.public && !!session.user)
const roleNames = { user: 'Usuário', manager: 'Gestor', admin: 'Administrador', 'admin-sistema': 'Administrador do sistema' }
const backPath = computed(() => typeof route.query.returnTo === 'string' ? safeReturnPath(route.query.returnTo) : route.meta.formBack ? `/forms/${route.params.formId}` : route.meta.back)

watch(() => route.fullPath, async () => {
  await nextTick()
  mainContent.value?.focus()
})

async function handleLogout() {
  leaving.value = true
  const logout = signOut()
  await router.replace('/login')
  await logout
  leaving.value = false
}
</script>

<template>
  <div class="app-shell">
    <a class="skip-link" href="#main-content">Ir para o conteúdo</a>
    <header class="app-header">
      <RouterLink class="brand" :to="isPrivate ? '/' : '/login'" aria-label="Form Engine — início">
        <span class="brand-mark" aria-hidden="true">FE</span>
        <span class="brand-text"><strong>Form Engine</strong><span>Formulários e informações</span></span>
      </RouterLink>
      <div v-if="isPrivate" class="user-chip">
        <div class="user-main">
          <span class="user-name">{{ session.user.name }}</span>
          <span class="user-role">{{ roleNames[session.user.role] || session.user.role }}</span>
          <span v-if="session.tenant" class="user-sub">{{ session.tenant.name }}</span>
        </div>
        <button class="ghost-button" type="button" :disabled="leaving" @click="handleLogout">Sair</button>
      </div>
    </header>
    <nav v-if="isPrivate" class="header-nav" aria-label="Navegação principal">
      <RouterLink to="/" :class="{ active: route.path === '/' || route.path.startsWith('/forms/') }">Formulários</RouterLink>
      <RouterLink to="/submissions" :class="{ active: route.path.startsWith('/submissions') }">Submissões</RouterLink>
      <RouterLink v-if="canAdminister" to="/admin/users">Usuários</RouterLink>
      <RouterLink v-if="canManageTenants" to="/admin/tenants">Tenants</RouterLink>
      <RouterLink v-if="canAdminister" to="/audit-logs" :class="{ active: route.path.startsWith('/audit-logs') }">Auditoria</RouterLink>
    </nav>
    <main id="main-content" ref="mainContent" class="app-main" tabindex="-1">
      <div class="page-content">
        <RouterLink v-if="isPrivate && backPath" class="back-link" :to="backPath">← Voltar</RouterLink>
        <slot />
      </div>
    </main>
    <footer class="app-footer">Form Engine · Gestão de formulários</footer>
  </div>
</template>
