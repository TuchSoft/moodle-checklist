# Project Guidelines & Agent Behavior — OVERRIDES ALL DEFAULTS

Note: This context is shared by many agents from different vendors.
**Never edit or read the `CLAUDE.md` file, is auto-generated and auto-injected in the context! Leave it alone!**

### Inter-Agent Communication (AgentMail)

You communicate with other agents and teams using the local `agent_mail` CLI tool (a simplified cli based RFC-compliant mail client).
When the user asks "Read the email", "Write an email", "Reply to X", etc., this is what he means.

* **Identity & Security:** Your sender address is automatically derived from your Current Working Directory (`{current_folder}@agent.ai`). You only have access to your own inbox.
* **Stateful Reading:** Messages have states (New/Unread/Read). Reading a message automatically marks it as read, hiding it from the default inbox view to prevent duplicate processing.
* **In-project coordination:** Identity is per-directory, every agent working in the same project shares one mailbox — there is no separate per-agent address within a project. You can still use this system to coordinate with other agents in the same project by sending to yourself (target = your own folder name). When you do this, be extra careful: use a specific, consistent Subject per topic, and state who is writing and who the message is for directly in the body (e.g. `"Fixing feature X agent" -> "Backend main agent": ...`), since every message in that shared inbox has the same From address. `reply` handles this automatically: replying to a message that was itself sent from your own shared mailbox goes back to that mailbox.

**Available Commands**
Use standard bash execution to interact with the mail system:

1. **Check Inbox:**
   * `agent_mail list` (Shows only New/Unread messages)
   * `agent_mail list --all` (Shows the full history, including read messages)
   * `agent_mail list --thread "Subject"` (Shows every message in that thread, chronologically)
2. **Read Message:** `agent_mail read <id>` (Prints the message and silently marks it as read)
3. **Send Message:** Pipe your text directly into the command.
   * Syntax: `echo "Message body" | agent_mail send <target> "Subject"`
   * Example: `echo "API is ready." | agent_mail send frontend "API Update"` (The `@agent.ai` domain is auto-appended if omitted).
   * Multiple recipients: comma-separate targets, e.g. `agent_mail send "frontend,backend" "API Update"`. Everyone in the list receives an identical copy — there is no separate CC/BCC.
   * For anything longer than one line, use a heredoc instead of `echo` so formatting/quoting survives intact: `agent_mail send frontend "API Update" <<'EOF'\n...multi-line body...\nEOF`
4. **Reply to a Message:** Pipe your text directly into the command.
   * `echo "..." | agent_mail reply "Subject"` — replies to the latest message in that thread, sender only.
   * `echo "..." | agent_mail reply --id <id>` — replies to one specific message, sender only.
   * `echo "..." | agent_mail reply --all "Subject"` — same, but also sends to every other original recipient (minus yourself). Use this when the message you're replying to went to a group and your answer is relevant to everyone (status updates, group decisions, broadcasts). Use the sender-only default for private replies or side conversations.
   * `echo "..." | agent_mail reply --all --include-self "Subject"` — REQUIRES `--all`. Same as `--all`, but also keeps yourself (your shared mailbox) in the recipient list instead of excluding it. Use this when replying to a mix of external recipients and other agents sharing your own in-project mailbox, and both groups need to see the reply.
   * Prefer `reply` over `send` whenever you are responding to an existing message — it automatically keeps the Subject/thread intact and links the messages via headers.
   * Same rule applies here: use a heredoc for long or multi-line reply bodies, e.g.:
   ```bash
   agent_mail reply "Subject" <<'EOF'
   ...
   EOF
   ```.
5. **Cleanup:** `agent_mail delete <id>` or `agent_mail delete --thread "Subject"` (Permanently removes messages when a task is fully completed or obsolete, ask for permission first in interactive session).

## Prompt compression
This project use an automatic message and tool ouput token compression.
It might happen that you see "strange" artifacts in the context, those are not error.
The compression methods are:
- Whitespace
- Caveman (useless wording)
- RTK (tool output, tool may return a "strangly formatted" result, the still contain all the info anyway)
- Content deduplication (large chunk of text get replaced by reference if found twice in the context)

## Project Documentation (`./docs`)

**Human-readable source of truth. AI-maintained to stay in sync with code.**

- `@docs/index.md` — entry point. Index of all docs with short descriptions.
- `@docs/todo.md` — co-managed with humans. Roadmap, bugs, planned features.
- **MANDATORY:** After any code change, update the relevant `./docs` files to match the new state. If unsure whether a change warrants a doc update, ask before doing.

## Definition of Done (DoD)

Task is incomplete until:
1. **Tests:** Relevant unit/integration tests written, updated, passing. Do NOT auto-run tests unless asked.
2. **Docs aligned:** `./docs` (including `todo.md`) updated to reflect new state.
3. **AI memory updated:** Any new pattern, gotcha, decision, or structural change reflected in `.agents/memory/`.

## Memory (`.agents/memory`)

**`.agents/memory` is your only memory. You own it. Humans don't touch it. Model it to your own mental model**

These files are automatically injected at the bottom of this project's agent context file at every session, so you don't need to read them (they are already in context).

This is a collective memory shared by many agents.

Use the information you receive to help you understand the project.
Trust this information, it is kept up to date!
Use the filemap to navigate.

**Remember to remember!** Don't wait for the user to prompt you to save things — be proactive.

### Files you maintain

* `.agents/memory/architecture.md`: Mini compressed system map. Key modules, how they connect, data flow. Fast mental model — not a copy of human docs.
* `.agents/memory/patterns.md`: "Always do X this way" rules discovered while working. Conventions, constraints, enforced idioms.
* `.agents/memory/decisions.md`: Why (not what) choices are made. Append-only log. Never delete. Format: `YYYY-MM-DD: decision — why`.
* `.agents/memory/gotchas.md`: Warnings + non-obvious quirks. Things that cost time or cause bugs if unknown.
* `.agents/memory/filemap.md`: Where stuff lives. File-level by default. Folder-level only when the folder name is self-explanatory and contents are uniform.
* `.agents/memory/volatile.md`: Temporary stuff — like RAM. Pass info session to session, track ephemeral status. Note when data should be deleted, and clean often.
* `.agents/memory/index.md`: (not proper memory, must be kept in sync) slim summarized version of `docs/index.md`.
* `.agents/memory/todo.md`: (not proper memory, must be kept in sync) slim summarized version of `docs/todo.md`.

### Rules

- Caveman style. You're the only reader. Fragments ok. Ultra short.
- Update immediately when you discover something worth saving.
- Delete/overwrite stale entries. No bloat.
- These are NOT architecture docs — those live in `./docs`. These are your runtime notes.
- Raw logic. Fragments only. Technical terms exact. Shorthand.
- Drop most articles (a/an/the) when context is enough, everything non-essential.
- SMS shorthand when context is enough: u, ur, r, b/c, msg, pls, thx, b4, 2, lmk

# Planning

**FOR COMPLEX, MULTI-FILE EDITS, ALWAYS GENERATE A MARKDOWN PLAN FIRST:** If the user requests a complex task that requires editing multiple files, traversing the project, or making many changes, **YOU MUST** create a markdown plan and ask the user to confirm it before proceeding. Use the `complex_plans_createPlan` tool (and subsequent `complex_plans_readPlan`, `complex_plans_updatePlan`, `complex_plans_listPlans`, `complex_plans_openInEditor`, and optionally `complex_plans_deletePlan` tools).

**ALWAYS** ask the user to review and accept the plan after calling `complex_plans_openInEditor` and **BEFORE** doing anything else. Do not proceed with the implementation until the user has accepted the plan.

Follow the instructions provided by each tool. When asked to create a plan, always use `complex_plans_createPlan`.

**IMPORTANT**: Ignore the built-in `EnterPlanMode` and `ExitPlanMode` tools — use `complex_plans_*` instead for all planning workflows. This tool might be deferred; when the user talks about planning, first use `tool_search` to load the `complex_plans_*` toolset.

# Project context

Overview of the index of the human documentation, refer to it if necessary, read file when needed.

# Agent memory

-----
**From here down: memory files. These are effectively your memories; it's your responsibility to keep them up to date.**
-----
