<template>
  <div class="ncr-console-shell">
    <div class="ncr-console-head">
      <div class="flex items-center gap-2 ncr-shrink">
        <span class="ncr-traffic"><span class="r" /><span class="y" /><span class="g" /></span>
        <span class="ncr-dot" :class="`ncr-dot-${execution.status}`"></span>
        <span class="text-sm font-bold ncr-text-body ncr-truncate">{{ execution.name }}</span>
        <span class="text-xs" :class="`ncr-status-${execution.status}`">{{ statusLabel }}</span>
      </div>
      <div class="flex items-center gap-3 shrink-0">
        <span class="ncr-timer text-xs ncr-text-faint">{{ elapsedLabel }}</span>
        <button v-if="execution.output" type="button" class="ncr-copy" @click="copy">
          <svg v-if="!copied" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" />
            <path d="M5 15V5a2 2 0 0 1 2-2h10" />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          {{ copied ? __('Copied') : __('Copy') }}
        </button>
      </div>
    </div>

    <div v-if="isStalePending" class="ncr-notice ncr-notice-warning" style="margin: 0.75rem 1rem 0;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <polyline points="12 6 12 12 16 14" />
      </svg>
      <span>{{ __('Still queued after :seconds. If no queue worker is processing jobs, this may never start — check your queue configuration.', { seconds: pendingSecondsLabel }) }}</span>
    </div>

    <div v-if="progress && progress.total > 0" class="ncr-progress-strip">
      <div class="flex items-center justify-between mb-1">
        <p v-if="progress.message" class="text-xs ncr-text-muted ncr-truncate">{{ progress.message }}</p>
        <span class="text-xs font-medium ncr-text-faint shrink-0 ml-2">{{ Math.round(progress.percentage || 0) }}%</span>
      </div>
      <div class="ncr-progress">
        <div class="ncr-progress-bar" :style="{ width: (progress.percentage || 0) + '%' }"></div>
      </div>
    </div>

    <pre ref="body" class="ncr-console"><code>{{ execution.output || placeholder }}</code><span v-if="isRunning" class="ncr-cursor" /></pre>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { __ } from '../util/translate'

const props = defineProps({
  execution: { type: Object, required: true },
  progress: { type: Object, default: null },
})

const body = ref(null)
const copied = ref(false)
const now = ref(Date.now())
let ticker = null

const isRunning = computed(() => ['pending', 'running'].includes(props.execution.status))

// A live, ticking clock only while the command runs; stopped otherwise so we
// never leave an interval behind.
watch(
  isRunning,
  (running) => {
    stopTicker()
    if (running) {
      now.value = Date.now()
      ticker = setInterval(() => (now.value = Date.now()), 1000)
    }
  },
  { immediate: true },
)

onBeforeUnmount(stopTicker)

function stopTicker() {
  if (ticker) {
    clearInterval(ticker)
    ticker = null
  }
}

// Auto-scroll to the newest output as it streams in.
watch(
  () => props.execution.output,
  () => {
    nextTick(() => {
      if (body.value) body.value.scrollTop = body.value.scrollHeight
    })
  },
)

const elapsedSeconds = computed(() => {
  if (!isRunning.value || !props.execution.started_at) return null
  const start = new Date(props.execution.started_at).getTime()
  if (Number.isNaN(start)) return null
  return Math.max(0, (now.value - start) / 1000)
})

const elapsedLabel = computed(() => {
  if (props.execution.duration != null) return `${props.execution.duration}s`
  return elapsedSeconds.value == null ? '' : `${elapsedSeconds.value.toFixed(0)}s`
})

// A command still "pending" (never even started) after a while is a much
// stronger signal something is wrong than one merely taking a while to
// finish — most likely nothing is consuming the queue. "running" for a long
// time is often entirely normal for a genuinely slow command.
const STALE_PENDING_THRESHOLD = 20

const isStalePending = computed(
  () => props.execution.status === 'pending' && (elapsedSeconds.value ?? 0) > STALE_PENDING_THRESHOLD,
)

const pendingSecondsLabel = computed(() => `${Math.round(elapsedSeconds.value ?? 0)}s`)

const placeholder = computed(() => (isRunning.value ? __('Waiting for output…') : __('No output.')))

const statusLabel = computed(
  () =>
    ({
      pending: __('Queued'),
      running: __('Running…'),
      success: __('Success'),
      failed: __('Failed'),
      timed_out: __('Timed out'),
    })[props.execution.status] || props.execution.status,
)

function copy() {
  const text = props.execution.output || ''

  const done = () => {
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  }

  if (navigator.clipboard?.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(() => {})
  }
}
</script>
