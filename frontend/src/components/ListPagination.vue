<script setup>
defineProps({
  pagination: { type: Object, default: null },
  loading: Boolean,
  perPage: { type: Number, default: 20 },
  selectId: { type: String, default: 'pagination-per-page' },
})
defineEmits(['page', 'per-page'])
</script>

<template>
  <div v-if="pagination" class="pagination">
    <p class="result-summary" role="status" aria-live="polite">
      {{ pagination.total ? `Exibindo ${pagination.from}–${pagination.to} de ${pagination.total} registros` : '0 registros' }}
    </p>
    <div class="page-size">
      <label :for="selectId">Itens por página</label>
      <select
        :id="selectId"
        :value="perPage"
        :disabled="loading"
        @change="$emit('per-page', Number($event.target.value))"
      >
        <option :value="10">10</option>
        <option :value="20">20</option>
        <option :value="50">50</option>
        <option :value="100">100</option>
      </select>
    </div>
    <nav class="page-navigation" aria-label="Paginação da listagem">
      <button
        class="ghost-button"
        type="button"
        aria-label="Ir para a página anterior"
        :disabled="loading || pagination.current_page <= 1"
        @click="$emit('page', pagination.current_page - 1)"
      >
        ← Anterior
      </button>
      <span aria-current="page">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
      <button
        class="ghost-button"
        type="button"
        aria-label="Ir para a próxima página"
        :disabled="loading || pagination.current_page >= pagination.last_page"
        @click="$emit('page', pagination.current_page + 1)"
      >
        Próxima →
      </button>
    </nav>
  </div>
</template>
