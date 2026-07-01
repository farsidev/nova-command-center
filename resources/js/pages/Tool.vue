<template>
  <div>
    <div class="flex items-center mb-6">
      <h1 class="text-90 font-normal text-2xl flex-1">{{ heading }}</h1>
    </div>

    <p v-if="config.help" class="mb-6 text-sm text-gray-500 dark:text-gray-400">{{ config.help }}</p>

    <div v-if="loading" class="py-12 text-center text-gray-400">{{ __('Loading…') }}</div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div v-if="current" class="mb-2">
          <output-console :execution="current" :progress="progress" />
        </div>

        <p v-if="commandGroups.length === 0" class="py-12 text-center text-gray-400">
          {{ __('No commands have been configured.') }}
        </p>

        <div v-for="group in commandGroups" :key="group.name" class="space-y-2">
          <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400">{{ group.name }}</h2>
          <command-card
            v-for="command in group.commands"
            :key="command.id"
            :command="command"
            :running="runningId === command.id"
            @trigger="trigger"
          />
        </div>
      </div>

      <div>
        <history-list :history="history" @select="selectHistory" @clear="clearHistory" />
      </div>
    </div>

    <run-modal v-if="modalCommand" :command="modalCommand" @close="modalCommand = null" @run="onModalRun" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue'
import CommandCard from '../components/CommandCard'
import HistoryList from '../components/HistoryList'
import OutputConsole from '../components/OutputConsole'
import RunModal from '../components/RunModal'
import api from '../util/api'
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

// Plain (non-reactive) timer handle — the template never reads it.
let poller = null

const heading = computed(() => config.value.navigation_label || __('Command Center'))

const commandGroups = computed(() => {
  const groups = {}
  commands.value.forEach((command) => {
    const name = command.group || 'General'
    groups[name] = groups[name] || { name, commands: [] }
    groups[name].commands.push(command)
  })
  return Object.values(groups)
})

onMounted(load)
onBeforeUnmount(stopPolling)

async function load() {
  loading.value = true
  try {
    const { data } = await api.commands()
    commands.value = data.commands
    history.value = data.history
    config.value = data.config
  } catch (error) {
    Nova.error(__('Failed to load commands.'))
  } finally {
    loading.value = false
  }
}

function trigger(command) {
  if (command.variables.length > 0 || command.flags.length > 0) {
    modalCommand.value = command
    return
  }

  if (['danger', 'warning'].includes(command.type)) {
    Nova.request && confirmAndRun(command)
    return
  }

  execute({ command, values: {}, flags: {} })
}

function confirmAndRun(command) {
  if (window.confirm(__('Are you sure you want to run this command?'))) {
    execute({ command, values: {}, flags: {} })
  }
}

function onModalRun(payload) {
  modalCommand.value = null
  execute(payload)
}

async function execute({ command, values, flags }) {
  runningId.value = command.id
  stopPolling()
  progress.value = null

  try {
    const { data } = await api.run({ command: command.id, variables: values, flags })
    current.value = data.execution

    if (data.queued) {
      pollExecution(data.execution.id)
    } else {
      finishExecution(data.execution)
    }
  } catch (error) {
    const message = error?.response?.data?.message || __('Command failed to start.')
    Nova.error(message)
    runningId.value = null
  }
}

function pollExecution(id) {
  poller = setInterval(async () => {
    try {
      const { data } = await api.execution(id)
      current.value = data.execution
      progress.value = data.progress

      if (!['pending', 'running'].includes(data.execution.status)) {
        finishExecution(data.execution)
      }
    } catch (error) {
      finishExecution(current.value)
    }
  }, 1500)
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

function selectHistory(item) {
  current.value = item
  progress.value = null
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
