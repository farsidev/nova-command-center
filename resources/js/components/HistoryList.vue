<template>
  <div class="ncr-card">
    <div class="flex items-center justify-between px-4 py-3 ncr-hr-b">
      <div class="flex items-center gap-2">
        <h3 class="font-bold ncr-text-body">{{ __('History') }}</h3>
        <span v-if="history.length" class="text-xs font-medium ncr-text-fainter">{{ history.length }}</span>
      </div>
      <button
        v-if="history.length"
        type="button"
        class="text-xs font-medium ncr-link-danger"
        :class="{ 'is-armed': confirmingClear }"
        @click="onClearClick"
      >
        {{ confirmingClear ? __('Click again to confirm') : __('Clear') }}
      </button>
    </div>

    <ul v-if="history.length" class="ncr-history-list ncr-rows">
      <li
        v-for="item in history"
        :key="item.id"
        class="ncr-history-row flex items-center justify-between gap-2 px-4 py-2.5"
        :style="{ '--ncr-row-accent': statusColor(item.status) }"
        :title="isActive(item.status) ? __('Still in progress — click to follow it live') : ''"
        @click="$emit('select', item)"
      >
        <div class="ncr-history-body">
          <p class="text-sm font-medium ncr-text-body ncr-truncate" :title="item.name">{{ item.name }}</p>
          <p class="ncr-history-meta text-xs ncr-text-faint">
            <span class="ncr-truncate">{{ item.ran_by || '—' }} · <span :title="absolute(item.started_at)">{{ relative(item.started_at) }}</span></span>
            <span v-if="item.duration != null" class="shrink-0">· {{ item.duration }}s</span>
          </p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <svg v-if="isActive(item.status)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ncr-spin ncr-icon-xs ncr-text-faint">
            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
          </svg>
          <button
            v-else
            type="button"
            class="ncr-iconbtn ncr-history-act"
            :title="__('Run again')"
            @click.stop="$emit('rerun', item)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="9" />
        <polyline points="12 7 12 12 15 14" />
      </svg>
      <p class="text-sm">{{ __('No history yet.') }}</p>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { __ } from '../util/translate'

defineProps({
  history: { type: Array, default: () => [] },
})

const emit = defineEmits(['select', 'clear', 'rerun'])

// Two-step confirm: the first click arms it, the second (within 4s) clears.
// Avoids both a native confirm() dialog and an accidental full history wipe.
const confirmingClear = ref(false)
let resetTimer = null

function onClearClick() {
  if (confirmingClear.value) {
    clearTimeout(resetTimer)
    confirmingClear.value = false
    emit('clear')
    return
  }

  confirmingClear.value = true
  resetTimer = setTimeout(() => (confirmingClear.value = false), 4000)
}

onBeforeUnmount(() => clearTimeout(resetTimer))

function isActive(status) {
  return status === 'pending' || status === 'running'
}

// Mirrors the .ncr-dot-* palette in tool.css so the accent stripe always
// matches the status dot/label shown on the same row.
const STATUS_COLORS = {
  pending: '#94a3b8',
  running: '#3b82f6',
  success: '#22c55e',
  failed: '#ef4444',
  timed_out: '#f59e0b',
}

function statusColor(status) {
  return STATUS_COLORS[status] || STATUS_COLORS.pending
}

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
