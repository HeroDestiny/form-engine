<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { signIn } from '../auth/session.js'
import { safeReturnPath } from '../auth/permissions.js'

const router = useRouter()
const route = useRoute()

const loginForm = reactive({
  email: '',
  password: '',
  tenantSlug: '',
})

const loading = ref(false)
const errorMessage = ref('')

async function handleLogin() {
  loading.value = true
  errorMessage.value = ''

  try {
    const user = await signIn({
      email: loginForm.email,
      password: loginForm.password,
      tenantSlug: loginForm.tenantSlug,
    })

    if (!user) throw new Error('Sua sessão não está disponível. Entre novamente.')
    await router.replace(safeReturnPath(route.query.redirect))
  } catch (error) {
    errorMessage.value = Object.values(error?.body?.errors || {})[0]?.[0] || (error.status ? error.message : 'Não foi possível acessar sua conta. Verifique sua conexão e tente novamente.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="panel panel-login">
    <h1>Acessar</h1>
    <p class="panel-subtitle">Entre com sua conta para acessar os formulários da sua organização.</p>

    <p v-if="route.query.reason === 'expired'" class="feedback" role="status">Sua sessão expirou. Entre novamente para continuar.</p>

    <form class="form" @submit.prevent="handleLogin">
      <label class="field">
        <span>E-mail</span>
        <input v-model="loginForm.email" type="email" autocomplete="username" required placeholder="voce@organizacao.com.br" />
      </label>

      <label class="field">
        <span>Senha</span>
        <input v-model="loginForm.password" type="password" autocomplete="current-password" required />
      </label>

      <label class="field">
        <span>Tenant (slug) <small>(opcional)</small></span>
        <input
          v-model="loginForm.tenantSlug"
          type="text"
          placeholder="prefeitura-demo"
          autocomplete="off"
        />
      </label>

      <p v-if="errorMessage" class="feedback error" role="alert">{{ errorMessage }}</p>

      <button class="primary-button" type="submit" :disabled="loading">
        <span v-if="!loading">Entrar</span>
        <span v-else>Autenticando…</span>
      </button>
    </form>
  </section>
</template>
