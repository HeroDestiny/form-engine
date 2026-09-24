<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { createForm, getCurrentUser } from '../api'

const router = useRouter()

const form = reactive({
  name: '',
  description: '',
})

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const roleAllowed = ref(true)

async function ensureCanCreate() {
  try {
    const data = await getCurrentUser()
    const user = data?.data?.user ?? null

    if (!user) {
      await router.replace('/login')
      return
    }

    const role = user.role
    roleAllowed.value = role === 'manager' || role === 'admin'

    if (!roleAllowed.value) {
      errorMessage.value = 'Você não tem permissão para criar formulários (apenas manager/admin).'
    }
  } catch {
    await router.replace('/login')
  }
}

async function handleSubmit() {
  if (!roleAllowed.value) return

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await createForm({ name: form.name, description: form.description || null })

    successMessage.value = data?.message || 'Formulário criado com sucesso.'

    const createdFormId = data?.data?.form?.id
    if (createdFormId) {
      await router.push(`/forms/${createdFormId}`)
    } else {
      await router.push('/')
    }
  } catch (error) {
    if (error.status === 403) {
      errorMessage.value = 'Você não tem permissão para criar formulários.'
    } else if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      errorMessage.value = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      errorMessage.value = error?.body?.message || 'Erro ao criar formulário.'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  ensureCanCreate()
})
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Novo formulário</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push('/')">Cancelar</button>
    </header>

    <main class="app-main">
      <section class="panel panel-login">
        <h2>Criar formulário</h2>
        <p class="panel-subtitle">Defina as informações básicas. Os campos serão adicionados depois.</p>

        <form class="form" @submit.prevent="handleSubmit">
          <label class="field">
            <span>Nome do formulário</span>
            <input
              v-model="form.name"
              type="text"
              required
              minlength="3"
              maxlength="255"
              placeholder="Cadastro de Benefícios"
            />
          </label>

          <label class="field">
            <span>Descrição <small>(opcional)</small></span>
            <input
              v-model="form.description"
              type="text"
              maxlength="1000"
              placeholder="Formulário para coleta de dados de beneficiários."
            />
          </label>

          <p v-if="errorMessage" class="feedback error">{{ errorMessage }}</p>
          <p v-if="successMessage" class="panel-subtitle">{{ successMessage }}</p>

          <button class="primary-button" type="submit" :disabled="loading || !roleAllowed">
            <span v-if="!loading">Salvar</span>
            <span v-else>Salvando…</span>
          </button>
        </form>
      </section>
    </main>
  </div>
</template>
