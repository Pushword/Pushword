#!/bin/bash
#
# Kill only the browsers this test run started.
#
# Sourced by .scripts/test and .scripts/test-coverage, which both need it and must not
# drift apart. Requires TEST_RUN_ID.
#
# This used to be `pkill -f chromedriver; pkill -f 'chrome.*headless'`, which did two
# harmful things:
#
#   - It killed every concurrent run's browsers. Several agents share this working tree,
#     so a peer's suite would fail with `Curl error thrown for http POST to
#     /session/<id>` — the browser vanished mid-test, which reads like a Panther flake
#     rather than someone else's cleanup.
#   - `-f` matches the whole command line, so it also killed innocent processes that
#     merely mention chromedriver — a shell running `bdi detect drivers`, or the very
#     script doing the pkill.
#
# Chrome is identifiable per run because PANTHER_CHROME_ARGUMENTS pins --user-data-dir
# to the run id. chromedriver is not: its command line is just `--port=N`. It is reached
# instead through the browser it spawned, since the main chrome process is its direct
# child (chrome's other processes are children of that main one, so they never match).
# Every command here has to be `set -e`-safe, since callers run under `bash -e` and call
# this from a `trap ... EXIT`: `pgrep`/`pkill`/`kill` all report 1 when nothing matched,
# which is the normal case for a run that opened no browser. An unguarded one aborts the
# trap, which loses both the script's real exit code and the `rm -rf` lines that follow.
function killOwnBrowsers {
    [ -n "$TEST_RUN_ID" ] || return 0

    local pid parentPid victims=''

    for pid in $(pgrep -f "panther-chrome-${TEST_RUN_ID}" 2>/dev/null || true); do
        # Match on the process name, never on `pkill -f` alone: -f matches the whole
        # command line, so it also hits bystanders that merely mention the run's
        # user-data-dir — a shell cleaning that path up, or the very script running this.
        [ "$(ps -o comm= -p "$pid" 2>/dev/null || true)" = 'chrome' ] || continue
        victims="$victims $pid"

        # A pipeline's status is its last command's, so `tr` keeps this assignment green
        # even where the process has already gone.
        parentPid=$(ps -o ppid= -p "$pid" 2>/dev/null | tr -d ' ')
        [ -n "$parentPid" ] || continue
        [ "$(ps -o comm= -p "$parentPid" 2>/dev/null || true)" = 'chromedriver' ] || continue
        victims="$victims $parentPid"
    done

    if [ -n "$victims" ]; then
        # shellcheck disable=SC2086 # word splitting is the point: $victims is a pid list
        kill $victims 2>/dev/null || true
    fi

    return 0
}
