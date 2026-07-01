<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
      <h3 class="font-bold text-gray-700 dark:text-gray-200">{{ __('History') }}</h3>
      <button v-if="history.length" type="button" class="text-xs text-red-500 hover:underline" @click="$emit('clear')">
        {{ __('Clear') }}
      </button>
    </div>

    <ul v-if="history.length" class="divide-y divide-gray-100 dark:divide-gray-700">
      <li
        v-for="item in history"
        :key="item.id"
        class="flex items-center justify-between px-4 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700"
        @click="$emit('select', item)"
      >
        <div class="min-w-0 pr-2">
          <p class="text-sm text-gray-700 dark:text-gray-200 truncate">{{ item.name }}</p>
          <p class="text-xs text-gray-400 truncate">{{ item.ran_by || '—' }} · {{ formatDate(item.started_at) }}</p>
        </div>
        <span class="text-xs shrink-0" :class="statusClass(item.status)">{{ item.status }}</span>
      </li>
    </ul>

    <p v-else class="px-4 py-6 text-sm text-center text-gray-400">{{ __('No history yet.') }}</p>
  </div>
</template>

<script>
import { __ } from '../util/translate'

export default {
  props: {
    history: { type: Array, default: () => [] },
  },

  emits: ['select', 'clear'],

  methods: {
    __,
    formatDate(value) {
      if (!value) return ''
      const date = new Date(value)
      return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
    },
    statusClass(status) {
      return {
        success: 'text-green-500',
        failed: 'text-red-500',
        timed_out: 'text-yellow-500',
        running: 'text-blue-500',
        pending: 'text-gray-400',
      }[status] || 'text-gray-400'
    },
  },
}
</script>
