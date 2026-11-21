<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { canAdminister, canManageTenants, session, signOut } from '../auth/session.js'
import { safeReturnPath } from '../auth/permissions.js'
import AppBreadcrumbs from './AppBreadcrumbs.vue'
import AppIcon from './AppIcon.vue'
import AppToast from './AppToast.vue'
import { politeAnnouncement } from '../composables/useBreadcrumbs.js'
import { initialsFromName, setTheme, themeMode } from '../composables/useTheme.js'

const route = useRoute()
const router = useRouter()
const leaving = ref(false)
const mobileNavOpen = ref(false)
const mainContent = ref(null)

const isPrivate = computed(() => !route.meta.public && !!session.user)
const roleNames = {
  user: 'Usuário',
  manager: 'Gestor',
  admin: 'Administrador',
  'admin-sistema': 'Admin do Sistema',
}

const backPath = computed(() => (
  typeof route.query.returnTo === 'string'
    ? safeReturnPath(route.query.returnTo)
    : route.meta.formBack
      ? `/forms/${route.params.formId}`
      : route.meta.back
))

const isFormsSection = computed(() => route.path === '/' || route.path.startsWith('/forms/'))
const isSubmissionsSection = computed(() => route.path.startsWith('/submissions'))
const isAuditSection = computed(() => route.path.startsWith('/audit-logs'))
const userInitials = computed(() => initialsFromName(session.user?.name))

watch(() => route.fullPath, async () => {
  mobileNavOpen.value = false
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
    <a class="skip-link" href="#main-content">Pular para o conteúdo principal</a>
    <div class="sr-only" aria-live="polite" aria-atomic="true">{{ politeAnnouncement }}</div>

    <!-- Workspace Autenticado: Sidebar Lateral com Ícones + Topbar Enxuta -->
    <div v-if="isPrivate" class="workspace-shell">
      <div
        v-if="mobileNavOpen"
        class="sidebar-backdrop"
        aria-hidden="true"
        @click="mobileNavOpen = false"
      />

      <aside
        class="app-sidebar"
        :class="{ 'is-open': mobileNavOpen }"
        aria-label="Menu lateral do sistema"
      >
        <div class="sidebar-top">
          <div class="sidebar-brand-row">
            <RouterLink class="brand" to="/" aria-label="Form Engine — página inicial">
              <span class="brand-mark" aria-hidden="true">FE</span>
              <span class="brand-text">
                <strong>Form Engine</strong>
                <span>Plataforma Executiva</span>
              </span>
            </RouterLink>
            <button
              type="button"
              class="sidebar-close-btn"
              aria-label="Fechar menu lateral"
              @click="mobileNavOpen = false"
            >
              <AppIcon name="close" :size="18" />
            </button>
          </div>

          <nav class="sidebar-nav" aria-label="Navegação principal">
            <div class="sidebar-group">
              <span class="sidebar-group-label">Operação</span>
              <RouterLink
                to="/"
                class="sidebar-link"
                :class="{ active: isFormsSection }"
                :aria-current="route.path === '/' ? 'page' : undefined"
              >
                <AppIcon name="forms" :size="18" />
                <span>Formulários</span>
              </RouterLink>
              <RouterLink
                to="/submissions"
                class="sidebar-link"
                :class="{ active: isSubmissionsSection }"
                :aria-current="route.path === '/submissions' ? 'page' : undefined"
              >
                <AppIcon name="inbox" :size="18" />
                <span>Submissões</span>
              </RouterLink>
            </div>

            <div v-if="canAdminister || canManageTenants" class="sidebar-group">
              <span class="sidebar-group-label">Administração</span>
              <RouterLink
                v-if="canAdminister"
                to="/admin/users"
                class="sidebar-link"
                :aria-current="route.path === '/admin/users' ? 'page' : undefined"
              >
                <AppIcon name="users" :size="18" />
                <span>Usuários</span>
              </RouterLink>
              <RouterLink
                v-if="canManageTenants"
                to="/admin/tenants"
                class="sidebar-link"
                :aria-current="route.path === '/admin/tenants' ? 'page' : undefined"
              >
                <AppIcon name="building" :size="18" />
                <span>Unidades (Tenants)</span>
              </RouterLink>
              <RouterLink
                v-if="canAdminister"
                to="/audit-logs"
                class="sidebar-link"
                :class="{ active: isAuditSection }"
                :aria-current="route.path === '/audit-logs' ? 'page' : undefined"
              >
                <AppIcon name="shield" :size="18" />
                <span>Auditoria</span>
              </RouterLink>
            </div>
          </nav>
        </div>

        <div class="sidebar-footer">
          <div
            v-if="session.tenant"
            class="tenant-card"
            :title="`Unidade administrativa ativa: ${session.tenant.name}`"
          >
            <span class="tenant-card-icon">
              <AppIcon name="building" :size="16" />
            </span>
            <div class="tenant-card-body">
              <small>Unidade ativa</small>
              <strong>{{ session.tenant.name }}</strong>
            </div>
          </div>
        </div>
      </aside>

      <div class="workspace-body">
        <header class="app-topbar">
          <div class="topbar-left">
            <button
              type="button"
              class="mobile-menu-btn"
              aria-label="Abrir menu de navegação"
              :aria-expanded="mobileNavOpen"
              @click="mobileNavOpen = true"
            >
              <AppIcon name="menu" :size="19" />
            </button>

            <RouterLink v-if="backPath" class="back-link" :to="backPath">
              <span aria-hidden="true">←</span>
              <span>Voltar</span>
            </RouterLink>

            <AppBreadcrumbs />
          </div>

          <div class="topbar-right">
            <div class="theme-switcher" role="group" aria-label="Alternar tema de cores">
              <button
                type="button"
                class="theme-btn"
                :aria-pressed="themeMode === 'light'"
                aria-label="Tema claro"
                title="Tema claro"
                @click="setTheme('light')"
              >
                <AppIcon name="sun" :size="15" />
                <span>Claro</span>
              </button>
              <button
                type="button"
                class="theme-btn"
                :aria-pressed="themeMode === 'dark'"
                aria-label="Tema escuro"
                title="Tema escuro"
                @click="setTheme('dark')"
              >
                <AppIcon name="moon" :size="15" />
                <span>Escuro</span>
              </button>
              <button
                type="button"
                class="theme-btn"
                :aria-pressed="themeMode === 'system'"
                aria-label="Tema automático do sistema"
                title="Seguir sistema operacional"
                @click="setTheme('system')"
              >
                <AppIcon name="monitor" :size="15" />
                <span>Auto</span>
              </button>
            </div>

            <div class="user-profile">
              <span class="avatar-badge" aria-hidden="true">{{ userInitials }}</span>
              <div class="user-meta">
                <span class="user-name">{{ session.user.name }}</span>
                <span class="user-role">{{ roleNames[session.user.role] || session.user.role }}</span>
              </div>
              <button
                class="ghost-button"
                type="button"
                :disabled="leaving"
                aria-label="Sair da conta"
                @click="handleLogout"
              >
                <AppIcon name="logout" :size="16" />
                <span>{{ leaving ? 'Saindo…' : 'Sair' }}</span>
              </button>
            </div>
          </div>
        </header>

        <main id="main-content" ref="mainContent" class="app-main" tabindex="-1">
          <div class="page-content">
            <slot />
          </div>
        </main>

        <footer class="app-footer">
          Form Engine · Gestão executiva e versionamento seguro de formulários
        </footer>
      </div>
    </div>

    <!-- Rotas Públicas (/login, /session-error) -->
    <div v-else class="public-shell">
      <div class="public-topbar">
        <div class="theme-switcher" role="group" aria-label="Alternar tema de cores">
          <button
            type="button"
            class="theme-btn"
            :aria-pressed="themeMode === 'light'"
            aria-label="Tema claro"
            @click="setTheme('light')"
          >
            <AppIcon name="sun" :size="15" />
            <span>Claro</span>
          </button>
          <button
            type="button"
            class="theme-btn"
            :aria-pressed="themeMode === 'dark'"
            aria-label="Tema escuro"
            @click="setTheme('dark')"
          >
            <AppIcon name="moon" :size="15" />
            <span>Escuro</span>
          </button>
          <button
            type="button"
            class="theme-btn"
            :aria-pressed="themeMode === 'system'"
            aria-label="Tema automático do sistema"
            @click="setTheme('system')"
          >
            <AppIcon name="monitor" :size="15" />
            <span>Auto</span>
          </button>
        </div>
      </div>

      <main id="main-content" ref="mainContent" class="public-main" tabindex="-1">
        <slot />
      </main>
    </div>

    <AppToast />
  </div>
</template>
