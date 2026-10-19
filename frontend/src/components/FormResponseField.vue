<script setup>
import { computed } from 'vue'
const props = defineProps({ field: { type: Object, required: true }, modelValue: { default: '' }, error: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])
const value = computed({ get: () => props.modelValue, set: next => emit('update:modelValue', next) })
const id = computed(() => `response-${props.field.id}`)
</script>

<template>
  <div class="field">
    <template v-if="!['radio', 'checkbox'].includes(field.type)">
      <label :for="id">{{ field.label }} <small v-if="field.is_required">(obrigatório)</small></label>
      <textarea v-if="field.type === 'textarea'" :id="id" v-model="value" rows="4" :required="field.is_required" :aria-invalid="!!error" :aria-describedby="error ? `${id}-error` : undefined" />
      <select v-else-if="field.type === 'select'" :id="id" v-model="value" :required="field.is_required" :aria-invalid="!!error" :aria-describedby="error ? `${id}-error` : undefined">
        <option value="">Selecione…</option><option v-for="option in field.options || []" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <input v-else :id="id" v-model="value" :type="field.type" :step="field.type === 'number' ? 'any' : undefined" :required="field.is_required" :aria-invalid="!!error" :aria-describedby="error ? `${id}-error` : undefined" />
    </template>
    <fieldset v-else class="response-options" :aria-describedby="error ? `${id}-error` : undefined" :aria-invalid="!!error">
      <legend>{{ field.label }} <small v-if="field.is_required">(obrigatório)</small></legend>
      <label v-for="option in field.options || []" :key="option.value" class="field-option">
        <input v-model="value" :type="field.type" :name="id" :value="option.value" /><span>{{ option.label }}</span>
      </label>
    </fieldset>
    <span v-if="error" :id="`${id}-error`" class="field-error" role="alert">{{ error }}</span>
  </div>
</template>
