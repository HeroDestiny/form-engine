<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session, signOut } from '../auth/session.js'
import { safeReturnPath } from '../auth/permissions.js'

const route = useRoute()
const router = useRouter()
const sessionError = computed(() => route.name === 'session-error')
const forbidden = computed(() => route.name === 'forbidden')
const title = computed(() => sessionError.value ? 'Não foi possível acessar sua conta' : forbidden.value ? 'Acesso restrito' : 'Página não encontrada')

async function handleLogout() {
  const logout = signOut()
  await router.replace('/login')
  await logout
}
</script>

<template>
  <section class="panel panel-login">
    <h1>{{ title }}</h1>
    <p class="panel-subtitle" role="status">
      {{ sessionError ? session.error || 'Verifique sua conexão e tente novamente.' : forbidden ? 'Seu perfil não tem permissão para acessar esta página.' : 'O endereço solicitado não está disponível.' }}
    </p>
    <div v-if="sessionError" class="panel-actions">
      <RouterLink class="primary-button" :to="safeReturnPath(route.query.redirect)">Tentar novamente</RouterLink>
      <button class="ghost-button" type="button" @click="handleLogout">Voltar ao login</button>
    </div>
    <RouterLink v-else class="primary-button" to="/">Voltar ao início</RouterLink>
  </section>
</template>
