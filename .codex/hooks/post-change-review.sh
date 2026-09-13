#!/usr/bin/env bash
# PostToolUse/Stop gate for the mandatory post-change review and scoped commit.
#
# A write within this repository records the current HEAD and invalidates any earlier
# commit. A successful scoped commit advances HEAD and unlocks completion after both
# review skills are reported.

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
      if [ "$tool_name" = "apply_patch" ]; then
        write_paths=$(printf '%s' "$input" | jq -r \
          'if (.tool_input | type) == "string" then
             .tool_input
           else
             .tool_input.patch // .tool_input.input // empty
           end' 2>/dev/null | \
          sed -n 's/^\*\*\* \(Add\|Update\|Delete\) File: //p')
      else
        write_paths=$(printf '%s' "$input" | jq -r \
          '.tool_input.file_path // .tool_input.path // empty' 2>/dev/null)
      fi

      # A pathless edit cannot be attributed to this repository.
      [ -z "$write_paths" ] && exit 0

      repo_root=$(git -C "$working_directory" rev-parse --show-toplevel 2>/dev/null || true)
      [ -z "$repo_root" ] && exit 0

      touches_repo=false
      while IFS= read -r write_path; do
        case "$write_path" in
          /*) absolute_path=$(realpath -m "$write_path") ;;
          *) absolute_path=$(realpath -m "$working_directory/$write_path") ;;
        esac
        case "$absolute_path" in
          "$repo_root"|"$repo_root"/*) touches_repo=true ;;
        esac
      done <<< "$write_paths"
      [ "$touches_repo" = false ] && exit 0

      mkdir -p "$state_dir"
      git_head > "$state_file"
      rm -f "$commit_file"
      ;;
    *)
      command=$(printf '%s' "$input" | jq -r \
        '.tool_input.command // .tool_input.cmd // empty' 2>/dev/null || true)

      if [ "$tool_name" = "functions.exec" ] && [ -z "$command" ]; then
        code=$(printf '%s' "$input" | jq -r '.tool_input.code // empty' 2>/dev/null || true)
        if printf '%s' "$code" | grep -Eq 'tools[.]exec_command[[:space:]]*[(]'; then
          command="$code"
        fi
      fi

      # Git's global -C and -c options may precede a scoped commit.
      scoped_commit_pattern="(^|[;&|[:space:]\"'])git([[:space:]]+(-C[[:space:]]+[^[:space:]]+|-c[[:space:]]+[^[:space:]]+))*[[:space:]]+commit[[:space:]][^;&|]*--only([[:space:]\"']|$)"
      if [ -f "$state_file" ] && printf '%s' "$command" | grep -Eq "$scoped_commit_pattern"; then
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
