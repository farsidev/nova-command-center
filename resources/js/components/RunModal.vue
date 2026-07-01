<template>
  <div class="ncr-modal-backdrop" @click.self="$emit('close')">
    <div class="ncr-modal bg-white dark:bg-gray-800 rounded-lg shadow-lg">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ command.name }}</h3>
        <p v-if="command.help" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ command.help }}</p>
        <code class="block mt-2 text-xs text-gray-400">{{ command.run }}</code>
      </div>

      <div class="px-6 py-4 max-h-[60vh] overflow-auto">
        <variable-field
          v-for="variable in command.variables"
          :key="variable.name"
          :variable="variable"
          v-model="values[variable.name]"
        />

        <div v-if="command.flags.length" class="mt-2">
          <p class="mb-2 text-sm font-bold text-gray-700 dark:text-gray-300">{{ __('Flags') }}</p>
          <label
            v-for="flag in command.flags"
            :key="flag.key"
            class="flex items-center gap-2 mb-2 text-sm text-gray-600 dark:text-gray-300"
          >
            <input type="checkbox" v-model="flags[flag.key]" class="checkbox" />
            <span>{{ flag.label }} <code class="text-xs text-gray-400">{{ flag.flag }}</code></span>
          </label>
        </div>

        <div v-if="command.variables.length === 0 && command.flags.length === 0" class="text-sm text-gray-500">
          {{ __('This command takes no input. Run it to continue.') }}
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
        <button type="button" class="ncr-btn ncr-btn-link" @click="$emit('close')">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="ncr-btn" :class="`ncr-btn-${command.type}`" :disabled="!valid" @click="submit">
          {{ __('Run') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import VariableField from './VariableField'
import { __ } from '../util/translate'

export default {
  components: { VariableField },

  props: {
    command: { type: Object, required: true },
  },

  emits: ['close', 'run'],

  data() {
    const values = {}
    this.command.variables.forEach((variable) => {
      values[variable.name] = variable.default || ''
    })

    const flags = {}
    this.command.flags.forEach((flag) => {
      flags[flag.key] = flag.default
    })

    return { values, flags }
  },

  computed: {
    valid() {
      return this.command.variables.every(
        (variable) => !variable.required || (this.values[variable.name] || '').length > 0,
      )
    },
  },

  methods: {
    __,
    submit() {
      if (!this.valid) return
      this.$emit('run', { command: this.command, values: { ...this.values }, flags: { ...this.flags } })
    },
  },
}
</script>
