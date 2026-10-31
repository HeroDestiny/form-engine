<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { activeBreadcrumbs, resolveBreadcrumbs } from '../composables/useBreadcrumbs.js'

const route = useRoute()
const items = computed(() => resolveBreadcrumbs(route, activeBreadcrumbs.value))
</script>

<template>
  <nav v-if="items.length" class="breadcrumbs" aria-label="Trilha de navegação">
    <ol class="breadcrumbs-list">
      <li v-for="(item, index) in items" :key="`${index}-${item.label}`">
        <template v-if="index < items.length - 1 && item.to">
          <RouterLink :to="item.to">{{ item.label }}</RouterLink>
          <span class="breadcrumbs-sep" aria-hidden="true">/</span>
        </template>
        <span v-else aria-current="page">{{ item.label }}</span>
      </li>
    </ol>
  </nav>
</template>
