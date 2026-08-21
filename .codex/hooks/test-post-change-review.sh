#!/usr/bin/env bash
set -euo pipefail

hook_path=$(cd "$(dirname "$0")" && pwd)/post-change-review.sh
test_tmp_dir=$(mktemp -d)
trap 'rm -rf "$test_tmp_dir"' EXIT

export TMPDIR="$test_tmp_dir"
session_id='post-change-review-test'
state_file="$TMPDIR/pushword-post-change-review-${UID}/$session_id"
commit_file="$state_file.commit"
test_repo="$test_tmp_dir/repository"

git init -q "$test_repo"
git -C "$test_repo" config user.email 'test@example.tld'
git -C "$test_repo" config user.name 'Hook Test'
printf '%s\n' 'initial' > "$test_repo/tracked.txt"
git -C "$test_repo" add tracked.txt
git -C "$test_repo" commit -qm 'initial'

post_write() {
  jq -nc --arg cwd "$test_repo" --arg session_id "$session_id" \
    '{hook_event_name: "PostToolUse", tool_name: "apply_patch", session_id: $session_id, cwd: $cwd}' | \
    "$hook_path"
}

post_terminal_command() {
  if [ "$1" = "Bash" ]; then
    jq -nc --arg command "$2" --arg cwd "$test_repo" --arg session_id "$session_id" \
      '{hook_event_name: "PostToolUse", tool_name: "Bash", session_id: $session_id, cwd: $cwd, tool_input: {command: $command}}' | \
      "$hook_path"
    return
  fi

  jq -nc --arg command "$2" --arg cwd "$test_repo" --arg session_id "$session_id" \
    '{hook_event_name: "PostToolUse", tool_name: "unknown_terminal_tool", session_id: $session_id, cwd: $cwd, tool_input: {cmd: $command}}' | \
    "$hook_path"
}

stop_with_message() {
  jq -nc --arg message "$1" --arg cwd "$test_repo" --arg session_id "$session_id" \
    '{hook_event_name: "Stop", session_id: $session_id, cwd: $cwd, last_assistant_message: $message}' | \
    "$hook_path"
}

post_write
test -f "$state_file"
test ! -e "$commit_file"

block_output=$(stop_with_message 'Done')
printf '%s' "$block_output" | jq -e \
  '.decision == "block" and (.reason | contains("git commit --only"))' >/dev/null
test -f "$state_file"

block_output=$(stop_with_message 'Post-change review: is-it-well-tested complete; code-simplifier complete; committed deadbee')
printf '%s' "$block_output" | jq -e '.decision == "block"' >/dev/null

printf '%s\n' 'unsafe change' > "$test_repo/tracked.txt"
git -C "$test_repo" commit -am 'unsafe' -q
post_terminal_command Bash 'git commit -m unsafe'
test ! -e "$commit_file"
post_write

printf '%s\n' 'changed' > "$test_repo/tracked.txt"
git -C "$test_repo" commit --only -qm 'test' -- tracked.txt
post_terminal_command unknown_terminal_tool 'git commit --only -m test -- tracked.txt'
test -f "$commit_file"

first_commit_hash=$(git -C "$test_repo" rev-parse --short HEAD)
post_write
test ! -e "$commit_file"

block_output=$(stop_with_message "Post-change review: is-it-well-tested complete; code-simplifier complete; committed $first_commit_hash")
printf '%s' "$block_output" | jq -e '.decision == "block"' >/dev/null

printf '%s\n' 'changed again' > "$test_repo/tracked.txt"
git -C "$test_repo" commit --only -qm 'test again' -- tracked.txt
post_terminal_command Bash 'git commit --only -m "test again" -- tracked.txt'

commit_hash=$(git -C "$test_repo" rev-parse --short HEAD)
stop_with_message "Post-change review: is-it-well-tested complete; code-simplifier complete; committed $commit_hash"
test ! -e "$state_file"
test ! -e "$commit_file"

post_write
stop_with_message 'Post-change review: awaiting user confirmation from is-it-well-tested'
test ! -e "$state_file"
test ! -e "$commit_file"

printf '%s\n' 'post-change-review hook tests passed'
