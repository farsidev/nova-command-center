<template>
  <div class="mb-4">
    <label :for="fieldId" class="block mb-1 text-sm font-bold text-gray-700 dark:text-gray-300">
      {{ variable.label }}
      <span v-if="!variable.required" class="font-normal text-gray-400">({{ __('optional') }})</span>
    </label>

    <select
      v-if="variable.type === 'select'"
      :id="fieldId"
      :value="modelValue"
      class="w-full form-control form-input form-input-bordered"
      :class="{ 'ncr-field-invalid': error }"
      :aria-invalid="!!error"
      @change="onInput($event.target.value)"
    >
      <option v-if="!variable.required" value="">—</option>
      <option v-for="option in variable.options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <input
      v-else
      :id="fieldId"
      :value="modelValue"
      type="text"
      class="w-full form-control form-input form-input-bordered"
      :class="{ 'ncr-field-invalid': error }"
      :aria-invalid="!!error"
      :placeholder="variable.placeholder || ''"
      @input="onInput($event.target.value)"
    />

    <p v-if="error" class="mt-1 text-xs text-red-500 dark:text-red-400">
      {{ error }}
    </p>
    <p v-else-if="variable.help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      {{ variable.help }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { __ } from '../util/translate'

const props = defineProps({
  variable: { type: Object, required: true },
  modelValue: { type: String, default: '' },
  error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const fieldId = computed(() => `ncr-var-${props.variable.name}`)

function onInput(value) {
  emit('update:modelValue', value)
}
</script>
