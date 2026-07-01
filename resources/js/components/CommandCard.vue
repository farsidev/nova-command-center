<template>
  <div class="flex items-center justify-between px-4 py-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="min-w-0 pr-4">
      <p class="font-bold text-gray-800 dark:text-gray-200 truncate">{{ command.name }}</p>
      <code class="block text-xs text-gray-400 truncate">
        <span v-if="command.command_type === 'bash'" class="text-yellow-600">bash&nbsp;</span>{{ command.run }}
      </code>
      <p v-if="command.help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ command.help }}</p>
    </div>

    <div class="flex items-center gap-2 shrink-0">
      <span v-if="command.queued" class="text-xs text-gray-400" :title="__('Runs on the queue')">⏱</span>
      <button
        type="button"
        class="ncr-btn"
        :class="`ncr-btn-${command.type}`"
        :disabled="running"
        @click="$emit('trigger', command)"
      >
        {{ running ? __('Running…') : __('Run') }}
      </button>
    </div>
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
