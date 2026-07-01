# Documentation

In-depth guides for Nova Command Center. Start with the
[project README](../README.md) for installation and a quick tour, then dive into
the topic you need below.

| Guide | What it covers |
| ----- | -------------- |
| [Configuration](configuration.md) | Every config key, with types and defaults. |
| [Command sources](command-sources.md) | Config, database (Nova resource) and custom sources. |
| [Security model](security.md) | The threat model and every layer of defence. |
| [Authorization](authorization.md) | The tool gate, the `authorize` ability and per-command policies. |
| [Queued execution & progress bars](progress-bars.md) | Running on the queue and reporting live progress. |
| [Frontend, theming & dark mode](frontend.md) | How the UI is built, themed and how to customise it. |

## At a glance

- **Secure by default** — allow-list only, no shell interpolation, bash disabled.
- **Nova 4 & 5** — one code path, Laravel Mix build, translation shim.
- **Config or database** — keep commands in version control, or manage them from
  a Nova resource when you accept the trade-off.
- **Sync & queued** — live, polled output and progress bars either way.
