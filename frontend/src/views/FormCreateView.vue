<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createForm } from '../api'
import { canManageForms } from '../auth/session.js'

const router = useRouter()

const form = reactive({
  name: '',
  description: '',
})

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
async function handleSubmit() {
  if (!canManageForms.value) return

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const data = await createForm({ name: form.name, description: form.description || null })

    successMessage.value = data?.message || 'Formulário criado com sucesso.'

    const createdFormId = data?.data?.form?.id
    if (createdFormId) {
      const versionId = data?.data?.version?.id
      await router.push(versionId ? `/forms/${createdFormId}/versions/${versionId}/fields` : `/forms/${createdFormId}`)
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

</script>

<template>
  <section class="panel panel-login">
    <h1>Criar formulário</h1>
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

      <p v-if="errorMessage" class="feedback error" role="alert">{{ errorMessage }}</p>
      <p v-if="successMessage" class="panel-subtitle">{{ successMessage }}</p>

      <button class="primary-button" type="submit" :disabled="loading || !canManageForms">
        <span v-if="!loading">Salvar</span>
        <span v-else>Salvando…</span>
      </button>
    </form>
  </section>
</template>
