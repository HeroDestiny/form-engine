<script setup>
defineProps({
  tone: { type: String, default: 'muted' },
  label: { type: String, required: true },
})
</script>

<template>
  <span class="status-badge" :class="`status-${tone}`">
    <svg v-if="tone === 'success'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M5.2 8.2l1.9 1.9 3.8-4" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <svg v-else-if="tone === 'warning'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M8 4.75v3.5l2.25 1.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <svg v-else-if="tone === 'danger'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M8 4.75v3.75M8 11.25h.01" stroke-linecap="round" />
    </svg>
    <svg v-else viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
      <circle cx="8" cy="8" r="6.25" />
      <path d="M5.25 8h5.5" stroke-linecap="round" />
    </svg>
    <span>{{ label }}</span>
  </span>
</template>
