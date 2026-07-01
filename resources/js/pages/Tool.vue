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

<script>
import CommandCard from '../components/CommandCard'
import HistoryList from '../components/HistoryList'
import OutputConsole from '../components/OutputConsole'
import RunModal from '../components/RunModal'
import api from '../util/api'
import { __ } from '../util/translate'

export default {
  components: { CommandCard, HistoryList, OutputConsole, RunModal },

  data() {
    return {
      loading: true,
      commands: [],
      history: [],
      config: {},
      current: null,
      progress: null,
      runningId: null,
      modalCommand: null,
      poller: null,
    }
  },

  computed: {
    heading() {
      return this.config.navigation_label || __('Command Center')
    },

    commandGroups() {
      const groups = {}
      this.commands.forEach((command) => {
        const name = command.group || 'General'
        groups[name] = groups[name] || { name, commands: [] }
        groups[name].commands.push(command)
      })
      return Object.values(groups)
    },
  },

  mounted() {
    this.load()
  },

  beforeUnmount() {
    this.stopPolling()
  },

  methods: {
    __,

    async load() {
      this.loading = true
      try {
        const { data } = await api.commands()
        this.commands = data.commands
        this.history = data.history
        this.config = data.config
      } catch (error) {
        Nova.error(__('Failed to load commands.'))
      } finally {
        this.loading = false
      }
    },

    trigger(command) {
      if (command.variables.length > 0 || command.flags.length > 0) {
        this.modalCommand = command
        return
      }

      if (['danger', 'warning'].includes(command.type)) {
        Nova.request && this.confirmAndRun(command)
        return
      }

      this.execute({ command, values: {}, flags: {} })
    },

    confirmAndRun(command) {
      if (window.confirm(__('Are you sure you want to run this command?'))) {
        this.execute({ command, values: {}, flags: {} })
      }
    },

    onModalRun(payload) {
      this.modalCommand = null
      this.execute(payload)
    },

    async execute({ command, values, flags }) {
      this.runningId = command.id
      this.stopPolling()
      this.progress = null

      try {
        const { data } = await api.run({ command: command.id, variables: values, flags })
        this.current = data.execution

        if (data.queued) {
          this.pollExecution(data.execution.id)
        } else {
          this.finishExecution(data.execution)
        }
      } catch (error) {
        const message = error?.response?.data?.message || __('Command failed to start.')
        Nova.error(message)
        this.runningId = null
      }
    },

    pollExecution(id) {
      this.poller = setInterval(async () => {
        try {
          const { data } = await api.execution(id)
          this.current = data.execution
          this.progress = data.progress

          if (!['pending', 'running'].includes(data.execution.status)) {
            this.finishExecution(data.execution)
          }
        } catch (error) {
          this.finishExecution(this.current)
        }
      }, 1500)
    },

    finishExecution(execution) {
      this.stopPolling()
      this.runningId = null
      this.progress = null
      if (execution) {
        this.notify(execution)
      }
      this.refreshHistory()
    },

    notify(execution) {
      if (execution.status === 'success') {
        Nova.success(__('Command completed successfully.'))
      } else if (execution.status !== 'pending' && execution.status !== 'running') {
        Nova.error(__('Command finished with status: :status', { status: execution.status }))
      }
    },

    async refreshHistory() {
      try {
        const { data } = await api.history()
        this.history = data.history
      } catch (error) {
        // History is non-critical; ignore failures.
      }
    },

    selectHistory(item) {
      this.current = item
      this.progress = null
    },

    async clearHistory() {
      try {
        await api.clearHistory()
        this.history = []
      } catch (error) {
        Nova.error(__('Failed to clear history.'))
      }
    },

    stopPolling() {
      if (this.poller) {
        clearInterval(this.poller)
        this.poller = null
      }
    },
  },
}
</script>
