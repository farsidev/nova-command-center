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
      @change="$emit('update:modelValue', $event.target.value)"
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
      :placeholder="variable.placeholder || ''"
      @input="$emit('update:modelValue', $event.target.value)"
    />

    <p v-if="variable.help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
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
})

defineEmits(['update:modelValue'])

const fieldId = computed(() => `ncr-var-${props.variable.name}`)
</script>
