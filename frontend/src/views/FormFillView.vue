<script setup>
import { onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute } from 'vue-router'
import { session } from '../auth/session.js'
import { useFormFill } from '../composables/useFormFill.js'
import FormResponseField from '../components/FormResponseField.vue'
import { formatDate } from '../utils/display.js'

const route = useRoute()
const selectedDraftId = ref(typeof route.query.draft === 'string' ? route.query.draft : '')
const { state, values, busy, dirty, canWrite, initialize, switchContext, loadDrafts, save } = useFormFill(route.params.formId, session.user.id)
watch(() => state.activeDraftId, value => { selectedDraftId.value = value || '' })
function confirmDiscard() { return !dirty.value || window.confirm('Descartar as alterações que ainda não foram salvas?') }
async function changeContext(id = '') {
  if (busy.value || !confirmDiscard()) return
  await switchContext(id)
}
onBeforeRouteLeave(() => !session.user || (!state.saving && !state.submitting && confirmDiscard()))
onMounted(() => initialize(selectedDraftId.value))
</script>

<template>
  <section class="panel panel-forms">
    <h1>{{ state.form?.name || 'Preencher formulário' }}</h1>
    <p v-if="state.form?.description" class="panel-subtitle">{{ state.form.description }}</p>
    <p v-if="state.loading || state.switching" role="status">Carregando formulário…</p>
    <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>
    <p v-if="state.message" class="feedback" role="status">{{ state.message }}</p>
    <p v-if="state.draftsError" class="feedback error" role="alert">{{ state.draftsError }} <button class="ghost-button" type="button" :disabled="busy" @click="loadDrafts">Atualizar rascunhos</button></p>

    <div class="panel-actions" style="margin-bottom: 1rem;">
      <label v-if="state.drafts.length" class="field"><span>Rascunhos salvos</span><select v-model="selectedDraftId" :disabled="busy"><option value="">Selecione um rascunho…</option><option v-for="draft in state.drafts" :key="draft.id" :value="draft.id">#{{ draft.id }} · {{ formatDate(draft.updated_at || draft.created_at) }}</option></select></label>
      <button v-if="state.drafts.length" class="ghost-button" type="button" :disabled="busy || !selectedDraftId" @click="changeContext(selectedDraftId)">Retomar rascunho</button>
      <button class="ghost-button" type="button" :disabled="busy" @click="changeContext()">{{ state.completedId ? 'Preencher novamente' : 'Novo preenchimento' }}</button>
      <RouterLink v-if="state.completedId" class="primary-button" :to="`/submissions/${state.completedId}`">Ver envio #{{ state.completedId }}</RouterLink>
    </div>

    <template v-if="state.ready && !state.completedId">
      <p class="panel-subtitle">Versão {{ state.version.version_number }} · {{ state.activeDraftId ? `Editando rascunho #${state.activeDraftId}` : 'Novo preenchimento' }} <span v-if="dirty">· Alterações não salvas</span></p>
      <form class="form" novalidate @submit.prevent="save(true)">
        <fieldset class="response-fields form" :disabled="busy || !state.ready">
          <FormResponseField v-for="field in state.fields" :key="field.id" v-model="values[field.name]" :field="field" :error="state.fieldErrors[field.name] || ''" />
        </fieldset>
        <div class="panel-actions">
          <button class="ghost-button" type="button" :disabled="!canWrite" @click="save(false)">{{ state.saving ? 'Salvando…' : 'Salvar rascunho' }}</button>
          <button class="primary-button" type="submit" :disabled="!canWrite">{{ state.submitting ? 'Enviando…' : 'Enviar formulário' }}</button>
        </div>
      </form>
    </template>
    <button v-else-if="!state.completedId && !busy && state.error" class="ghost-button" type="button" @click="initialize(selectedDraftId)">Tentar novamente</button>
  </section>
</template>
