#!/usr/bin/env bash
# PreToolUse(Bash) guard.
#
# This repo shares ONE working tree across parallel agents. Tree-sweeping or
# destructive git commands can swallow or wipe another agent's uncommitted
# work, producing the "big mixed commit" problem (or data loss). This hook
# blocks them before they run.
#
# Protocol: read the tool call as JSON on stdin. Exit 2 = block (stderr is
# shown to the model). Exit 0 = allow. Anything unexpected → allow (fail open,
# never wedge the agent on a parsing glitch).

input=$(cat)
cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // empty' 2>/dev/null || true)
[ -z "$cmd" ] && exit 0

c=$(printf '%s' "$cmd" | tr '\t' ' ')

block() {
  {
    echo "⛔ Blocked: $1"
    echo ""
    echo "This repo shares one working tree AND one index with parallel agents."
    echo "Commit ONLY the files you touched, scoped at commit time:"
    echo "    git commit --only -m \"type(scope): subject\" -- <path1> <path2>"
    echo "New files need a scoped 'git add <path>' first. Never leave files"
    echo "staged — the index is not a save point."
    echo "Think you need a whole-tree commit? Stop and ask Robin — that is a"
    echo "human-terminal operation, not an agent one."
  } >&2
  exit 2
}

gitre='\bgit\b([[:space:]]+-C[[:space:]]+[^[:space:]]+)?[[:space:]]+'

# Tree-sweeping staging: git add -A / --all / -u / --update / . / :/  (also after `--`)
if printf '%s' "$c" | grep -Eq "${gitre}add[[:space:]]+(--[[:space:]]+)?(-A|--all|-u|--update|\.|:/)([[:space:]]|\$)"; then
  block "git add stages the whole tree (-A / --all / -u / .)"
fi

# git commit -a / -am / --all (stages every tracked modification)
if printf '%s' "$c" | grep -Eq "${gitre}commit[[:space:]]+(-[a-zA-Z]*a[a-zA-Z]*|--all)([[:space:]]|\$)"; then
  block "git commit -a/-am/--all stages every tracked change"
fi

# Bare `git commit` (no --only, no `--` pathspec): commits whatever ANY agent
# has staged at that instant — the shared-index sweep race. Scoped commits
# are immune.
if printf '%s' "$c" | grep -Eq "${gitre}commit\b" && \
   ! printf '%s' "$c" | grep -q -- '--only' && \
   ! printf '%s' "$c" | grep -Eq '[[:space:]]--([[:space:]]|$)'; then
  block "bare 'git commit' sweeps whatever any parallel agent has staged"
fi

# git stash (push/save) — read-only list/show are fine
if printf '%s' "$c" | grep -Eq "${gitre}stash\b" && \
   ! printf '%s' "$c" | grep -Eq "${gitre}stash[[:space:]]+(list|show)\b"; then
  block "git stash can hide another agent's in-progress work"
fi

# git reset --hard
if printf '%s' "$c" | grep -Eq "${gitre}reset\b[^&|;]*--hard\b"; then
  block "git reset --hard discards uncommitted work"
fi

# git clean -f (deletes untracked files)
if printf '%s' "$c" | grep -Eq "${gitre}clean\b[^&|;]*-[a-zA-Z]*f"; then
  block "git clean -f deletes untracked files (maybe another agent's)"
fi

# git checkout . / restore . — discard the whole working tree
if printf '%s' "$c" | grep -Eq "${gitre}(checkout|restore)[[:space:]]+(--[[:space:]]+)?\.([[:space:]]|\$)"; then
  block "git checkout/restore . discards the whole working tree"
fi

exit 0
