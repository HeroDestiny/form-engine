<script setup>
defineProps({
  loading: Boolean,
  error: { type: String, default: '' },
  empty: Boolean,
  loadingMessage: { type: String, default: 'Carregando registros…' },
  emptyMessage: { type: String, default: 'Nenhum registro encontrado com os filtros selecionados.' },
})
defineEmits(['retry'])
</script>

<template>
  <div class="list-content" :aria-busy="loading">
    <p v-if="loading" class="loading-state" role="status">{{ loadingMessage }}</p>
    <div v-else-if="error" class="list-error">
      <p class="feedback error" role="alert">{{ error }}</p>
      <button type="button" class="ghost-button" @click="$emit('retry')">Tentar novamente</button>
    </div>
    <div v-else-if="empty" class="empty-state" role="status"><p>{{ emptyMessage }}</p></div>
    <slot v-else />
  </div>
</template>
