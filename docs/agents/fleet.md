# Fleet maintenance

Fleet can enroll this repository in event-controlled maintenance. Installed state, not this document, determines whether events or legacy calendars currently start the work. Inspect `fleet-manager events status` and the actual loaded definitions before changing installation.

## Inputs and work

- Changed GitHub issue content, relevant replies, and explicit reassessment requests cause Triage to reconsider the queue.
- Readiness changes on already-assessed input cause a fresh read of the existing bug or enhancement queue. An event naming an issue does not move that issue ahead of older eligible work.
- An origin default-branch change causes Releases to inspect the project's current release state.
- Finding Bugs, Exploratory Testing, and Architecture Review keep their discovery calendars.

GitHub issue/readiness inputs and the default branch can use Fleet's explicitly configured polling fallback when no authenticated webhook subscription is available. Polling retains its last valid snapshot through interruptions and backs off on failures. An unchanged snapshot starts no new work.

## Ownership and configuration

The issue rules remain in [issue-tracker.md](issue-tracker.md) and [triage-labels.md](triage-labels.md). Human assignments, prerequisites, current readiness, workspace locks, retained CI work, and provider capacity remain execution gates. Event delivery does not waive CI or the checks in [AGENTS.md](../../AGENTS.md).

Fleet's registry owns repository membership and discovery schedules. Source configuration, saved delivery positions, release verification policy, and installation progress live in Fleet's private state directory. This checkout's git-excluded `.fleet/` directory holds its repository-owned ready-queue adapters. Inspect these authorities rather than copying their intervals, thresholds, or loaded-mode claims here.

Use Fleet's installation workflow to change control mode, one repository at a time. Keep existing triggers and active work until replacement coverage and loaded roles are verified. Use `fleet-manager --help` for supported manual requests, status, and recovery commands; never edit saved checkpoints to manufacture delivery evidence.
