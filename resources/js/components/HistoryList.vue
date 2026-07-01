<template>
  <div class="ncr-card overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
      <div class="flex items-center gap-2">
        <h3 class="font-bold text-gray-700 dark:text-gray-200">{{ __('History') }}</h3>
        <span v-if="history.length" class="text-xs font-medium text-gray-300 dark:text-gray-600">{{ history.length }}</span>
      </div>
      <button
        v-if="history.length"
        type="button"
        class="text-xs font-medium text-red-500 hover:text-red-600 hover:underline"
        @click="$emit('clear')"
      >
        {{ __('Clear') }}
      </button>
    </div>

    <ul v-if="history.length" class="divide-y divide-gray-100 dark:divide-gray-700">
      <li
        v-for="item in history"
        :key="item.id"
        class="group flex items-center justify-between gap-2 px-4 py-2.5 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700"
        @click="$emit('select', item)"
      >
        <div class="min-w-0 pr-2">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">{{ item.name }}</p>
          <p class="text-xs text-gray-400 truncate">
            {{ item.ran_by || '—' }} · <span :title="absolute(item.started_at)">{{ relative(item.started_at) }}</span>
          </p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button
            type="button"
            class="ncr-iconbtn opacity-0 group-hover:opacity-100 focus:opacity-100"
            :title="__('Run again')"
            @click.stop="$emit('rerun', item)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
              <path d="M23 4v6h-6M1 20v-6h6" />
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
            </svg>
          </button>
          <span class="flex items-center gap-1.5 text-xs font-medium" :class="`ncr-status-${item.status}`">
            <span class="ncr-dot" :class="`ncr-dot-${item.status}`"></span>
            {{ item.status.replace('_', ' ') }}
          </span>
        </div>
      </li>
    </ul>

    <div v-else class="ncr-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-8 h-8">
        <circle cx="12" cy="12" r="9" />
        <polyline points="12 7 12 12 15 14" />
      </svg>
      <p class="text-sm">{{ __('No history yet.') }}</p>
    </div>
  </div>
</template>

<script setup>
import { __ } from '../util/translate'

defineProps({
  history: { type: Array, default: () => [] },
})

defineEmits(['select', 'clear', 'rerun'])

function absolute(value) {
  if (!value) return ''
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

function relative(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  const seconds = Math.round((Date.now() - date.getTime()) / 1000)
  if (seconds < 45) return __('just now')
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m ${__('ago')}`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ${__('ago')}`
  return `${Math.round(hours / 24)}d ${__('ago')}`
}
</script>
