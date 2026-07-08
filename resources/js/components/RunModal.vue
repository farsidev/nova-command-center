<template>
  <div class="ncr-modal-backdrop" @click.self="onBackdropClick">
    <div class="ncr-modal ncr-card shadow-lg" role="dialog" aria-modal="true">
      <div class="flex items-start justify-between gap-4 px-6 py-4 ncr-hr-b">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="ncr-badge" :class="command.command_type === 'bash' ? 'ncr-badge-bash' : 'ncr-badge-artisan'">
              {{ command.command_type === 'bash' ? 'bash' : 'artisan' }}
            </span>
            <h3 class="text-lg font-bold ncr-text-strong ncr-truncate" :title="command.name">{{ command.name }}</h3>
          </div>
          <p v-if="command.help" class="mt-1 text-sm ncr-text-muted">{{ command.help }}</p>
          <code class="block mt-2 text-xs ncr-text-faint break-all">{{ command.run }}</code>
        </div>
        <button
          type="button"
          class="ncr-copy shrink-0 -mr-1"
          :aria-label="__('Close')"
          @click="$emit('close')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ncr-icon-sm">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      <div class="ncr-modal-body">
        <div v-if="submitError" class="ncr-notice ncr-notice-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <span>{{ submitError }}</span>
        </div>

        <div v-else-if="isRisky" class="ncr-notice" :class="command.type === 'danger' ? 'ncr-notice-danger' : 'ncr-notice-warning'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          <span>{{ __('This is a :type command. Make sure you want to run it.', { type: capitalize(command.type) }) }}</span>
        </div>

        <variable-field
          v-for="variable in command.variables"
          :key="variable.name"
          :variable="variable"
          :command-id="command.id"
          :error="fieldError(variable.name)"
          v-model="values[variable.name]"
          @update:model-value="touch(variable.name)"
        />

        <div v-if="command.flags.length">
          <p class="mb-2 text-sm font-bold ncr-text-body">{{ __('Flags') }}</p>
          <label v-for="flag in command.flags" :key="flag.key" class="ncr-flag">
            <input type="checkbox" v-model="flags[flag.key]" class="checkbox" />
            <span class="flex-1 min-w-0">
              <span class="block">{{ flag.label }}</span>
              <span v-if="flag.help" class="block mt-1 text-xs ncr-text-muted">{{ flag.help }}</span>
            </span>
            <code class="text-xs ncr-text-faint">{{ flag.flag }}</code>
          </label>
        </div>

        <div v-if="command.variables.length === 0 && command.flags.length === 0" class="text-sm ncr-text-muted">
          {{ __('This command takes no input. Run it to continue.') }}
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 px-6 py-4 ncr-hr-t">
        <button type="button" class="ncr-btn ncr-btn-link" :disabled="submitting" @click="$emit('close')">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="ncr-btn" :class="`ncr-btn-${command.type}`" :disabled="!valid || submitting" @click="submit">
          <svg v-if="submitting" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ncr-spin">
            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="currentColor">
            <path d="M8 5v14l11-7z" />
          </svg>
          {{ submitting ? __('Running…') : __('Run') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import VariableField from './VariableField'
import { __ } from '../util/translate'

const props = defineProps({
  command: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  submitError: { type: String, default: null },
  submitting: { type: Boolean, default: false },
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

// Fields the operator has edited since the last failed submission — their
// server-side error is hidden immediately so correcting a value doesn't leave
// a stale red border until the next round-trip.
const touchedFields = ref(new Set())

watch(
  () => props.errors,
  () => (touchedFields.value = new Set()),
)

function touch(name) {
  touchedFields.value.add(name)
}

function fieldError(name) {
  return touchedFields.value.has(name) ? null : props.errors[name] || null
}

const isRisky = computed(() => props.command.needs_confirm ?? ['danger', 'warning'].includes(props.command.type))

function capitalize(value) {
  return value ? value.charAt(0).toUpperCase() + value.slice(1) : value
}

const valid = computed(() =>
  props.command.variables.every(
    (variable) => !variable.required || (values[variable.name] || '').length > 0,
  ),
)

function submit() {
  if (!valid.value || props.submitting) return
  emit('run', { command: props.command, values: { ...values }, flags: { ...flags } })
}

function onBackdropClick() {
  if (!props.submitting) emit('close')
}

function onKey(event) {
  if (event.key === 'Escape' && !props.submitting) emit('close')
  if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) submit()
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>
