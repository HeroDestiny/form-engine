<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session, signOut } from '../auth/session.js'
import { safeReturnPath } from '../auth/permissions.js'
import StatusBadge from '../components/StatusBadge.vue'

const route = useRoute()
const router = useRouter()
const sessionError = computed(() => route.name === 'session-error')
const forbidden = computed(() => route.name === 'forbidden')
const title = computed(() => (
  sessionError.value
    ? 'Não foi possível verificar sua sessão'
    : forbidden.value
      ? 'Acesso restrito'
      : 'Página não encontrada'
))

async function handleLogout() {
  const logout = signOut()
  await router.replace('/login')
  await logout
}
</script>

<template>
  <section class="panel panel-login">
    <div class="card-heading" style="margin-bottom: 0.5rem;">
      <h1>{{ title }}</h1>
      <StatusBadge
        :tone="sessionError ? 'warning' : forbidden ? 'danger' : 'muted'"
        :label="sessionError ? 'Falha temporária' : forbidden ? '403 Sem permissão' : '404 Não encontrada'"
      />
    </div>
    <p class="panel-subtitle" role="status">
      {{ sessionError ? session.error || 'Verifique sua conexão com o servidor e tente novamente.' : forbidden ? 'Seu perfil atual não possui permissão para acessar esta funcionalidade.' : 'O endereço solicitado não existe ou foi movido.' }}
    </p>
    <div v-if="sessionError" class="panel-actions">
      <RouterLink class="primary-button" :to="safeReturnPath(route.query.redirect)">Tentar novamente</RouterLink>
      <button class="ghost-button" type="button" @click="handleLogout">Voltar ao login</button>
    </div>
    <RouterLink v-else class="primary-button" to="/">Voltar ao início</RouterLink>
  </section>
</template>
