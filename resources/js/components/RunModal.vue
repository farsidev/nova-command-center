<template>
  <div class="ncr-modal-backdrop" @click.self="$emit('close')">
    <div class="ncr-modal ncr-card shadow-lg" role="dialog" aria-modal="true">
      <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-100 dark:border-gray-700">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="ncr-badge" :class="command.command_type === 'bash' ? 'ncr-badge-bash' : 'ncr-badge-artisan'">
              {{ command.command_type === 'bash' ? 'bash' : 'artisan' }}
            </span>
            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 truncate">{{ command.name }}</h3>
          </div>
          <p v-if="command.help" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ command.help }}</p>
          <code class="block mt-2 text-xs text-gray-400 dark:text-gray-500 break-all">{{ command.run }}</code>
        </div>
        <button
          type="button"
          class="ncr-copy shrink-0 -mr-1"
          :aria-label="__('Close')"
          @click="$emit('close')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      <div class="ncr-modal-body">
        <div v-if="isRisky" class="ncr-notice" :class="command.type === 'danger' ? 'ncr-notice-danger' : 'ncr-notice-warning'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0 mt-0.5">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          <span>{{ __('This is a :type command. Make sure you want to run it.', { type: command.type }) }}</span>
        </div>

        <variable-field
          v-for="variable in command.variables"
          :key="variable.name"
          :variable="variable"
          v-model="values[variable.name]"
        />

        <div v-if="command.flags.length">
          <p class="mb-2 text-sm font-bold text-gray-700 dark:text-gray-300">{{ __('Flags') }}</p>
          <label v-for="flag in command.flags" :key="flag.key" class="ncr-flag">
            <input type="checkbox" v-model="flags[flag.key]" class="checkbox" />
            <span class="flex-1">{{ flag.label }}</span>
            <code class="text-xs text-gray-400">{{ flag.flag }}</code>
          </label>
        </div>

        <div v-if="command.variables.length === 0 && command.flags.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
          {{ __('This command takes no input. Run it to continue.') }}
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
        <button type="button" class="ncr-btn ncr-btn-link" @click="$emit('close')">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="ncr-btn" :class="`ncr-btn-${command.type}`" :disabled="!valid" @click="submit">
          <svg viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
            <path d="M8 5v14l11-7z" />
          </svg>
          {{ __('Run') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive } from 'vue'
import VariableField from './VariableField'
import { __ } from '../util/translate'

const props = defineProps({
  command: { type: Object, required: true },
})

const emit = defineEmits(['close', 'run'])

const values = reactive({})
const flags = reactive({})

props.command.variables.forEach((variable) => {
  values[variable.name] = variable.default || ''
})

props.command.flags.forEach((flag) => {
  flags[flag.key] = flag.default
})

const isRisky = computed(() => ['danger', 'warning'].includes(props.command.type))

const valid = computed(() =>
  props.command.variables.every(
    (variable) => !variable.required || (values[variable.name] || '').length > 0,
  ),
)

function submit() {
  if (!valid.value) return
  emit('run', { command: props.command, values: { ...values }, flags: { ...flags } })
}

function onKey(event) {
  if (event.key === 'Escape') emit('close')
  if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) submit()
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>
