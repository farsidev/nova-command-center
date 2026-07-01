<template>
  <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-900">
      <div class="flex items-center gap-2">
        <span class="inline-block w-2 h-2 rounded-full" :class="statusDotClass"></span>
        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ execution.name }}</span>
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ statusLabel }}</span>
      </div>
      <div class="text-xs text-gray-400" v-if="execution.duration != null">
        {{ execution.duration }}s
      </div>
    </div>

    <div v-if="progress && progress.total > 0" class="px-4 pt-3">
      <div class="h-2 w-full rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
        <div class="h-2 bg-primary-500 transition-all" :style="{ width: (progress.percentage || 0) + '%' }"></div>
      </div>
      <p v-if="progress.message" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ progress.message }}</p>
    </div>

    <pre class="ncr-console px-4 py-3 text-xs leading-relaxed overflow-auto max-h-96"><code>{{ execution.output || placeholder }}</code></pre>
  </div>
</template>

<script>
import { __ } from '../util/translate'

export default {
  props: {
    execution: { type: Object, required: true },
    progress: { type: Object, default: null },
  },

  computed: {
    placeholder() {
      return this.isRunning ? __('Waiting for output…') : __('No output.')
    },

    isRunning() {
      return ['pending', 'running'].includes(this.execution.status)
    },

    statusLabel() {
      return {
        pending: __('Queued'),
        running: __('Running…'),
        success: __('Success'),
        failed: __('Failed'),
        timed_out: __('Timed out'),
      }[this.execution.status] || this.execution.status
    },

    statusDotClass() {
      return {
        pending: 'bg-gray-400',
        running: 'bg-blue-400 animate-pulse',
        success: 'bg-green-500',
        failed: 'bg-red-500',
        timed_out: 'bg-yellow-500',
      }[this.execution.status] || 'bg-gray-400'
    },
  },
}
</script>
