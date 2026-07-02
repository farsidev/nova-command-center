<template>
  <div class="ncr-card ncr-card--interactive ncr-cmd" :class="`ncr-accent-${command.type}`">
    <div class="ncr-cmd-body">
      <div class="flex items-center gap-2">
        <span class="ncr-badge shrink-0" :class="command.command_type === 'bash' ? 'ncr-badge-bash' : 'ncr-badge-artisan'">
          {{ command.command_type === 'bash' ? 'bash' : 'artisan' }}
        </span>
        <p class="font-bold text-gray-800 dark:text-gray-200 ncr-truncate" :title="command.name">{{ command.name }}</p>
        <span
          v-if="command.queued"
          class="text-gray-400"
          :title="__('Runs on the queue')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
            <circle cx="12" cy="12" r="9" />
            <polyline points="12 7 12 12 15 14" />
          </svg>
        </span>
      </div>
      <code class="ncr-cmd-run ncr-truncate text-xs text-gray-400 dark:text-gray-500" :title="command.run">{{ command.run }}</code>
      <p v-if="command.help" class="mt-1 text-xs text-gray-500 dark:text-gray-400 ncr-truncate" :title="command.help">{{ command.help }}</p>
    </div>

    <button
      type="button"
      class="ncr-btn shrink-0"
      :class="`ncr-btn-${command.type}`"
      :disabled="running"
      @click="$emit('trigger', command)"
    >
      <svg v-if="running" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ncr-spin w-3.5 h-3.5">
        <path d="M21 12a9 9 0 1 1-6.219-8.56" />
      </svg>
      <svg v-else viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
        <path d="M8 5v14l11-7z" />
      </svg>
      {{ running ? __('Running…') : __('Run') }}
    </button>
  </div>
</template>

<script setup>
import { __ } from '../util/translate'

defineProps({
  command: { type: Object, required: true },
  running: { type: Boolean, default: false },
})

defineEmits(['trigger'])
</script>
