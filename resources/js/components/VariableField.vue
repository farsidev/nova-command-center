<template>
  <div class="mb-4">
    <label :for="fieldId" class="ncr-label">
      {{ variable.label }}
      <span v-if="!variable.required" class="font-normal ncr-text-faint">({{ __('optional') }})</span>
    </label>

    <select
      v-if="variable.type === 'select'"
      :id="fieldId"
      :value="modelValue"
      class="w-full form-control form-input form-input-bordered ncr-select"
      :class="{ 'ncr-field-invalid': error }"
      :aria-invalid="error ? 'true' : 'false'"
      :aria-required="variable.required ? 'true' : 'false'"
      :aria-describedby="describedBy"
      @change="onInput($event.target.value)"
    >
      <option v-if="!variable.required" value="">—</option>
      <option v-else-if="!modelValue" value="" disabled>{{ __('Choose an option…') }}</option>
      <option v-for="option in variable.options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <model-search-field
      v-else-if="variable.type === 'model'"
      :field-id="fieldId"
      :command-id="commandId"
      :variable-name="variable.name"
      :model-value="modelValue"
      :placeholder="variable.placeholder || ''"
      :error="error"
      :described-by="describedBy"
      :required="variable.required"
      @update:model-value="onInput"
    />

    <input
      v-else
      :id="fieldId"
      :value="modelValue"
      type="text"
      class="w-full form-control form-input form-input-bordered"
      :class="{ 'ncr-field-invalid': error }"
      :aria-invalid="error ? 'true' : 'false'"
      :aria-required="variable.required ? 'true' : 'false'"
      :aria-describedby="describedBy"
      :placeholder="variable.placeholder || ''"
      @input="onInput($event.target.value)"
    />

    <p v-if="error" :id="errorId" class="mt-1 text-xs ncr-text-error" role="alert">
      {{ error }}
    </p>
    <p v-else-if="variable.help" :id="helpId" class="mt-1 text-xs ncr-text-muted">
      {{ variable.help }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ModelSearchField from './ModelSearchField'
import { __ } from '../util/translate'

const props = defineProps({
  variable: { type: Object, required: true },
  commandId: { type: String, default: '' },
  modelValue: { type: String, default: '' },
  error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const fieldId = computed(() => `ncr-var-${props.variable.name}`)
const errorId = computed(() => `${fieldId.value}-error`)
const helpId = computed(() => `${fieldId.value}-help`)
const describedBy = computed(() => {
  if (props.error) return errorId.value
  if (props.variable.help) return helpId.value
  return null
})

function onInput(value) {
  emit('update:modelValue', value)
}
</script>
