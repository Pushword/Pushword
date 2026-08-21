#!/usr/bin/env bash
# PostToolUse/Stop gate for the mandatory post-change review and scoped commit.
#
# A write tool records the current HEAD and invalidates any earlier commit. A successful
# scoped commit advances HEAD and unlocks completion after both review skills are reported.

input=$(cat)
event=$(printf '%s' "$input" | jq -r '.hook_event_name // empty' 2>/dev/null || true)
session_id=$(printf '%s' "$input" | jq -r '.session_id // empty' 2>/dev/null || true)
tool_name=$(printf '%s' "$input" | jq -r '.tool_name // empty' 2>/dev/null || true)
working_directory=$(printf '%s' "$input" | jq -r '.cwd // empty' 2>/dev/null || true)

[ -z "$event" ] && exit 0
[ -z "$session_id" ] && exit 0

safe_session_id=$(printf '%s' "$session_id" | tr -cd '[:alnum:]_.-')
[ -z "$safe_session_id" ] && exit 0

state_dir="${TMPDIR:-/tmp}/pushword-post-change-review-${UID}"
state_file="$state_dir/$safe_session_id"
commit_file="$state_file.commit"

git_head() {
  if [ -n "$working_directory" ] && [ -d "$working_directory" ]; then
    git -C "$working_directory" rev-parse HEAD 2>/dev/null || true
    return
  fi

  git rev-parse HEAD 2>/dev/null || true
}

if [ "$event" = "PostToolUse" ]; then
  case "$tool_name" in
    apply_patch|Edit|Write|MultiEdit)
      mkdir -p "$state_dir"
      git_head > "$state_file"
      rm -f "$commit_file"
      ;;
    Bash)
      command=$(printf '%s' "$input" | jq -r '.tool_input.command // empty' 2>/dev/null || true)

      if [ -f "$state_file" ] && printf '%s' "$command" | grep -Eq \
        '(^|[;&|[:space:]])git([[:space:]]+-C[[:space:]]+[^[:space:]]+)?[[:space:]]+commit[[:space:]][^;&|]*--only([[:space:]]|$)'; then
        previous_head=$(cat "$state_file")
        current_head=$(git_head)

        if [ -n "$previous_head" ] && [ -n "$current_head" ] && \
          [ "$previous_head" != "$current_head" ]; then
          printf '%s\n' "$current_head" > "$commit_file"
        fi
      fi
      ;;
  esac

  exit 0
fi

[ "$event" != "Stop" ] && exit 0
[ ! -f "$state_file" ] && exit 0

last_message=$(printf '%s' "$input" | jq -r '.last_assistant_message // empty' 2>/dev/null || true)
reported_commit=$(printf '%s' "$last_message" | sed -n \
  's/.*Post-change review: is-it-well-tested complete; code-simplifier complete; committed \([0-9a-f]\{7,40\}\).*/\1/p' | tail -n 1)

if [ -f "$commit_file" ] && [ -n "$reported_commit" ]; then
  committed_head=$(cat "$commit_file")

  case "$committed_head" in
    "$reported_commit"*)
      rm -f "$state_file" "$commit_file"
      exit 0
      ;;
  esac
fi

if printf '%s' "$last_message" | grep -Fq \
  'Post-change review: awaiting user confirmation from is-it-well-tested'; then
  rm -f "$state_file" "$commit_file"
  exit 0
fi

jq -n --arg reason \
  'Before ending this modification task, invoke $is-it-well-tested, then $code-simplifier, apply their findings, rerun the relevant checks, create a scoped git commit with git commit --only, and report its actual hash in the required Post-change review status.' \
  '{decision: "block", reason: $reason}'
