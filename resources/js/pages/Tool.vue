<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <span class="ncr-icon-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="4 17 10 11 4 5" />
          <line x1="12" y1="19" x2="20" y2="19" />
        </svg>
      </span>
      <div class="flex-1 min-w-0">
        <h1 class="text-2xl font-normal leading-tight ncr-heading">{{ heading }}</h1>
        <p v-if="config.help" class="text-sm ncr-text-muted">{{ config.help }}</p>
        <div v-if="!loading && commands.length" class="flex items-center mt-1">
          <span class="ncr-stat">{{ commands.length }} {{ commands.length === 1 ? __('command') : __('commands') }}</span>
          <span v-if="categories.length" class="ncr-stat">{{ categories.length }} {{ categories.length === 1 ? __('category') : __('categories') }}</span>
        </div>
      </div>
    </div>

    <!-- Loading skeleton, mirroring the real rail/commands/history layout to
         avoid a jarring reflow once the data arrives. -->
    <div v-if="loading" class="space-y-6">
      <div class="ncr-skeleton ncr-skel-search"></div>

      <div class="ncr-columns">
        <div class="ncr-col-rail ncr-rail-desktop ncr-card p-3 space-y-2">
          <div v-for="n in 5" :key="n" class="ncr-skeleton ncr-skel-row"></div>
        </div>

        <div class="ncr-col-main space-y-3">
          <div v-for="n in 4" :key="n" class="ncr-card flex items-center justify-between px-4 py-3">
            <div class="flex-1 space-y-2">
              <div class="ncr-skeleton ncr-skel-title"></div>
              <div class="ncr-skeleton ncr-skel-sub"></div>
            </div>
            <div class="ncr-skeleton ncr-skel-btn shrink-0"></div>
          </div>
        </div>

        <div class="ncr-col-history ncr-card p-4 space-y-3">
          <div class="ncr-skeleton ncr-skel-head"></div>
          <div class="ncr-skeleton ncr-skel-line"></div>
          <div class="ncr-skeleton ncr-skel-line-short"></div>
        </div>
      </div>
    </div>

    <div v-else class="space-y-6">
      <transition name="ncr-slide">
        <output-console v-if="current" :execution="current" :progress="progress" />
      </transition>

      <!-- Search + custom command bar -->
      <div v-if="commands.length" class="space-y-3">
        <div class="ncr-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input v-model="search" type="text" :placeholder="__('Search commands…')" />
        </div>

        <div v-if="customTypes.length" class="ncr-card ncr-custombar">
          <select v-model="customType" class="ncr-select">
            <option v-for="type in customTypes" :key="type" :value="type">{{ type }}</option>
          </select>
          <input
            v-model="customRun"
            type="text"
            :placeholder="customType === 'bash' ? 'ls -la' : 'queue:work --once'"
            @keyup.enter="runCustom"
          />
          <button type="button" class="ncr-btn" :disabled="!customRun.trim() || runningId === 'custom'" @click="runCustom">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
            {{ __('Run') }}
          </button>
        </div>

        <!-- Category rail on narrow screens (horizontal) -->
        <div v-if="categories.length" class="ncr-rail-mobile">
          <category-nav :categories="categories" :active="activeCategory" :total="total" scroll @select="activeCategory = $event" />
        </div>
      </div>

      <div class="ncr-columns">
        <!-- Category rail on wide screens (vertical, sticky) -->
        <aside v-if="categories.length" class="ncr-col-rail ncr-rail-desktop">
          <div class="ncr-card p-2">
            <category-nav :categories="categories" :active="activeCategory" :total="total" @select="activeCategory = $event" />
          </div>
        </aside>

        <div class="ncr-col-main space-y-6">
          <div v-if="visibleGroups.length === 0" class="ncr-card ncr-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="4" width="18" height="16" rx="2" />
              <path d="M7 9l3 3-3 3M13 15h4" />
            </svg>
            <p class="text-sm font-medium">{{ commands.length ? __('No commands match your search.') : __('No commands have been configured.') }}</p>
            <p v-if="!commands.length" class="text-xs">{{ __('Add commands to the config file or database source to get started.') }}</p>
          </div>

          <div v-for="group in visibleGroups" :key="group.name" class="space-y-2">
            <div class="ncr-section">
              <span class="ncr-section-dot" :style="{ backgroundColor: categoryColor(group.name) }"></span>
              <h2 class="ncr-section-title">{{ group.name }}</h2>
              <span class="ncr-section-count">{{ group.commands.length }}</span>
            </div>
            <command-card
              v-for="command in group.commands"
              :key="command.id"
              :command="command"
              :running="runningId === command.id"
              @trigger="trigger"
            />
          </div>
        </div>

        <aside class="ncr-col-history">
          <history-list :history="history" @select="selectHistory" @clear="clearHistory" @rerun="rerun" />
        </aside>
      </div>
    </div>

    <run-modal
      v-if="modalCommand"
      :command="modalCommand"
      :errors="modalErrors"
      :submit-error="modalSubmitError"
      :submitting="modalSubmitting"
      @close="modalCommand = null"
      @run="onModalRun"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import CategoryNav from '../components/CategoryNav'
import CommandCard from '../components/CommandCard'
import HistoryList from '../components/HistoryList'
import OutputConsole from '../components/OutputConsole'
import RunModal from '../components/RunModal'
import api from '../util/api'
import { categoryColor } from '../util/colors'
import { handleRunError } from '../util/errors'
import { __ } from '../util/translate'

// Collections and payloads are replaced wholesale from the API, so shallowRef
// avoids the cost of deep reactivity proxies on large output/history objects.
const loading = ref(true)
const commands = shallowRef([])
const history = shallowRef([])
const config = shallowRef({})
const current = shallowRef(null)
const progress = shallowRef(null)
const runningId = ref(null)
const modalCommand = shallowRef(null)
const modalErrors = shallowRef({})
const modalSubmitError = ref(null)
const modalSubmitting = ref(false)

const search = ref('')
const activeCategory = ref(null)
const customType = ref('artisan')
const customRun = ref('')

// Plain (non-reactive) timer handle — the template never reads it.
let poller = null

const heading = computed(() => config.value.navigation_label || __('Command Center'))

const customTypes = computed(() => config.value.custom_commands || [])

// Commands matching the search box (across name, run string and group).
const filteredCommands = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return commands.value
  return commands.value.filter((command) =>
    [command.name, command.run, command.group]
      .filter(Boolean)
      .some((field) => field.toLowerCase().includes(term)),
  )
})

// The category list (with live counts) derived from the search-filtered set.
const categories = computed(() => {
  const counts = {}
  filteredCommands.value.forEach((command) => {
    const name = command.group || 'General'
    counts[name] = (counts[name] || 0) + 1
  })
  return Object.keys(counts)
    .sort((a, b) => a.localeCompare(b))
    .map((name) => ({ name, count: counts[name] }))
})

const total = computed(() => filteredCommands.value.length)

// If a search hides the active category, fall back to showing everything.
watch(categories, (list) => {
  if (activeCategory.value !== null && !list.some((c) => c.name === activeCategory.value)) {
    activeCategory.value = null
  }
})

// Grouped commands shown in the main pane, narrowed to the active category.
const visibleGroups = computed(() => {
  const groups = {}
  filteredCommands.value.forEach((command) => {
    const name = command.group || 'General'
    if (activeCategory.value !== null && name !== activeCategory.value) return
    groups[name] = groups[name] || { name, commands: [] }
    groups[name].commands.push(command)
  })
  return Object.values(groups).sort((a, b) => a.name.localeCompare(b.name))
})

onMounted(() => {
  load()
  if (customTypes.value.length) customType.value = customTypes.value[0]
})
onBeforeUnmount(stopPolling)

async function load() {
  loading.value = true
  try {
    const { data } = await api.commands()
    commands.value = data.commands
    history.value = data.history
    config.value = data.config
    if (customTypes.value.length) customType.value = customTypes.value[0]
  } catch (error) {
    Nova.error(__('Failed to load commands.'))
  } finally {
    loading.value = false
  }
}

// A command opens the modal when it needs input OR the backend says it needs
// confirmation (needs_confirm — explicit `confirm` config, or danger/warning
// type by default); the modal doubles as the confirmation step. Safe commands
// run immediately.
function trigger(command) {
  const needsInput = command.variables.length > 0 || command.flags.length > 0
  const needsConfirm = command.needs_confirm ?? ['danger', 'warning'].includes(command.type)

  if (needsInput || needsConfirm) {
    modalErrors.value = {}
    modalSubmitError.value = null
    modalCommand.value = command
    return
  }

  execute({ command, values: {}, flags: {} })
}

async function onModalRun(payload) {
  modalSubmitting.value = true
  modalErrors.value = {}
  modalSubmitError.value = null

  const result = await execute(payload)

  modalSubmitting.value = false

  if (result.success) {
    modalCommand.value = null
    return
  }

  if (result.fieldErrors && Object.keys(result.fieldErrors).length) {
    modalErrors.value = result.fieldErrors
  } else {
    modalSubmitError.value = result.message
  }
}

// Re-run a past execution by finding its source command; if that command needs
// input it opens the modal, otherwise it runs straight away.
function rerun(item) {
  const command = commands.value.find((c) => c.id === item.command_id)
  if (!command) {
    Nova.error(__('That command is no longer available.'))
    return
  }
  trigger(command)
}

async function runCustom() {
  const run = customRun.value.trim()
  if (!run) return

  runningId.value = 'custom'
  stopPolling()
  progress.value = null
  scrollToConsole()

  try {
    const { data } = await api.run({ custom: { type: customType.value, run } })
    current.value = data.execution
    if (data.queued) {
      pollExecution(data.execution.id)
      refreshHistory()
    } else {
      finishExecution(data.execution)
    }
  } catch (error) {
    const { message } = handleRunError(error)
    Nova.error(message)
    runningId.value = null
  }
}

/**
 * Run a command and report back whether it succeeded.
 *
 * @returns {Promise<{success: boolean, fieldErrors?: object, message?: string}>}
 */
async function execute({ command, values, flags }) {
  runningId.value = command.id
  stopPolling()
  progress.value = null
  scrollToConsole()

  try {
    const { data } = await api.run({ command: command.id, variables: values, flags })
    current.value = data.execution

    if (data.queued) {
      pollExecution(data.execution.id)
      // The backend records a pending entry the instant a command is queued
      // (so it survives a reload even if nothing ever consumes the job); pull
      // it in now rather than waiting for the command to finish.
      refreshHistory()
    } else {
      finishExecution(data.execution)
    }

    return { success: true }
  } catch (error) {
    runningId.value = null

    const result = handleRunError(error)
    // Field-level errors are shown inline in the modal instead of a toast;
    // anything else (auth, rate limit, lock conflicts) still gets a toast so
    // it's visible even for commands that ran without opening the modal.
    if (!result.fieldErrors) Nova.error(result.message)

    return { success: false, ...result }
  }
}

function scrollToConsole() {
  if (typeof window !== 'undefined' && window.scrollY > 0) {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const POLL_INTERVAL = 1500
const POLL_MAX_FAILURES = 4

function pollExecution(id) {
  let failures = 0

  poller = setInterval(async () => {
    try {
      const { data } = await api.execution(id)
      failures = 0
      current.value = data.execution
      progress.value = data.progress

      if (!['pending', 'running'].includes(data.execution.status)) {
        finishExecution(data.execution)
      }
    } catch (error) {
      const status = error?.response?.status

      // Session expired or the execution record is gone (cache eviction) —
      // retrying will not help, so stop immediately with an honest message.
      if (status === 401 || status === 419) {
        stopPolling()
        runningId.value = null
        Nova.error(__('Your session has expired. Refresh the page to continue.'))
        return
      }

      if (status === 404) {
        stopPolling()
        runningId.value = null
        Nova.error(__('Lost track of this execution. It may still be running — check History shortly.'))
        return
      }

      // Transient failure (network blip). Retry a few times before giving up
      // — and when we do give up, say so honestly instead of silently
      // pretending the command finished.
      failures += 1

      if (failures >= POLL_MAX_FAILURES) {
        stopPolling()
        runningId.value = null
        Nova.error(__('Lost connection while checking status. The command may still be running — check History shortly.'))
        refreshHistory()
      }
    }
  }, POLL_INTERVAL)
}

function finishExecution(execution) {
  stopPolling()
  runningId.value = null
  progress.value = null
  if (execution) {
    notify(execution)
  }
  refreshHistory()
}

function notify(execution) {
  if (execution.status === 'success') {
    Nova.success(__('Command completed successfully.'))
  } else if (execution.status !== 'pending' && execution.status !== 'running') {
    Nova.error(__('Command finished with status: :status', { status: execution.status }))
  }
}

async function refreshHistory() {
  try {
    const { data } = await api.history()
    history.value = data.history
  } catch (error) {
    // History is non-critical; ignore failures.
  }
}

// Selecting a history entry normally just shows a snapshot. But a pending or
// still-running entry (e.g. a queued command the operator navigated away from,
// or reloaded the page on) has no live tracking yet — resume polling it so the
// console updates instead of showing a permanently frozen "Running…".
function selectHistory(item) {
  current.value = item
  progress.value = null

  if (['pending', 'running'].includes(item.status)) {
    runningId.value = item.command_id
    stopPolling()
    pollExecution(item.id)
  }
}

async function clearHistory() {
  try {
    await api.clearHistory()
    history.value = []
  } catch (error) {
    Nova.error(__('Failed to clear history.'))
  }
}

function stopPolling() {
  if (poller) {
    clearInterval(poller)
    poller = null
  }
}
</script>
