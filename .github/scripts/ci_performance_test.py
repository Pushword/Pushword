import copy
from contextlib import redirect_stdout
from io import BytesIO
from io import StringIO
import json
import os
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest
from unittest.mock import patch
from zipfile import ZipFile

import ci_performance as ci


CONFIG = json.loads((Path(__file__).parents[1] / "ci-performance-budget.json").read_text())


def sample(number, seconds=300, commit=None):
    return {
        "run_id": number, "commit": commit or f"commit-{number}",
        "url": f"https://github.com/Pushword/Pushword/actions/runs/{number}",
        "job_ids": [number * 2, number * 2 + 1],
        "metrics": {"longest_job_seconds": seconds, "runner_seconds": seconds * 10},
        "jobs": {"browser": seconds}, "steps": {"browser / tests": seconds},
        "tests": {"chrome / example.spec.ts": seconds}, "counts": {"editor-chrome-1": 10},
    }


def calibrated():
    record = {}
    for number in range(1, CONFIG["calibration_runs"] + 1):
        record, findings = ci.assess(sample(number), record, CONFIG)
    return record


def archive(files):
    output = BytesIO()
    with ZipFile(output, "w") as zipped:
        for name, value in files.items():
            zipped.writestr(name, value)
    return output.getvalue()


class BudgetTest(unittest.TestCase):
    def test_observation_never_blocks_even_after_repeated_large_regressions(self):
        record = {}
        for number in range(1, 10):
            record, findings = ci.assess(sample(number, 300 if number < 6 else 600, "same"), record, CONFIG)
            self.assertFalse(any(finding["blocked"] for finding in findings))
        self.assertEqual(len(record["baseline"]), 9)

    def test_stable_runs_calibrate_and_reference_remains_fixed(self):
        record = calibrated()
        original = copy.deepcopy(record["baseline"])
        for number in range(21, 45):
            record, findings = ci.assess(sample(number, 600), record, CONFIG)
        self.assertEqual(record["baseline"], original)
        self.assertTrue(all(finding["reference"] in (300, 3000) for finding in findings))

    def test_warning_but_no_block_for_one_slow_run(self):
        _, findings = ci.assess(sample(21, 400), calibrated(), CONFIG)
        self.assertTrue(all(finding["warning"] for finding in findings))
        self.assertFalse(any(finding["blocked"] for finding in findings))

    def test_three_independent_runs_of_the_same_commit_confirm_regression(self):
        record = calibrated()
        for number in range(21, 24):
            record, findings = ci.assess(sample(number, 400, "regression"), record, CONFIG)
            self.assertEqual(any(finding["blocked"] for finding in findings), number == 23)

    def test_confirmation_uses_median_instead_of_one_outlier(self):
        record = calibrated()
        for number, duration in [(21, 400), (22, 310), (23, 400)]:
            record, findings = ci.assess(sample(number, duration, "regression"), record, CONFIG)
        self.assertTrue(all(finding["blocked"] for finding in findings))
        record, findings = ci.assess(sample(24, 310, "regression"), record, CONFIG)
        self.assertFalse(any(finding["blocked"] for finding in findings))

    def test_a_guard_only_rerun_neither_calibrates_nor_confirms(self):
        record = calibrated()
        for _ in range(4):
            record, findings = ci.assess(sample(21, 400, "regression"), record, CONFIG)
        self.assertFalse(any(finding["blocked"] for finding in findings))
        self.assertEqual(len(record["recent"]), 20)
        observing = {}
        for _ in range(4):
            observing, _ = ci.assess(sample(1), observing, CONFIG)
        self.assertEqual(len(observing["baseline"]), 1)

    def test_partial_reruns_do_not_confirm_old_measurements(self):
        record = calibrated()
        observing = {}
        for number in range(21, 25):
            current = sample(number, 400, "regression")
            current["job_ids"][0] = 9999
            record, findings = ci.assess(current, record, CONFIG)
            observing, _ = ci.assess(current, observing, CONFIG)
        self.assertFalse(any(finding["blocked"] for finding in findings))
        self.assertEqual(len(observing["baseline"]), 1)

    def test_different_commits_cannot_confirm_each_other(self):
        record = calibrated()
        for number in range(21, 25):
            record, findings = ci.assess(sample(number, 400), record, CONFIG)
        self.assertFalse(any(finding["blocked"] for finding in findings))

    def test_small_percentage_steps_are_detected_as_a_trend(self):
        record = calibrated()
        for number in range(21, 26):
            record, findings = ci.assess(sample(number, 318), record, CONFIG)
        self.assertTrue(all(finding["trend_warning"] for finding in findings))
        self.assertFalse(any(finding["warning"] or finding["blocked"] for finding in findings))

    def test_percentage_and_absolute_thresholds_are_both_required(self):
        config = {**CONFIG, "minimum_blocking_seconds": 1000}
        record = calibrated()
        for number in range(21, 24):
            record, findings = ci.assess(sample(number, 400, "regression"), record, config)
        self.assertFalse(any(finding["blocked"] for finding in findings))

    def test_exact_threshold_does_not_block(self):
        record = calibrated()
        for number in range(21, 24):
            record, findings = ci.assess(sample(number, 360, "regression"), record, CONFIG)
        self.assertFalse(any(finding["blocked"] for finding in findings))

    def test_a_cumulative_cost_regression_can_block_without_slowing_the_longest_job(self):
        record = calibrated()
        for number in range(21, 24):
            current = sample(number, 300, "regression")
            current["metrics"]["runner_seconds"] = 4000
            record, findings = ci.assess(current, record, CONFIG)
        self.assertFalse(findings[0]["blocked"])
        self.assertTrue(findings[1]["blocked"])

    def test_cache_coverage_and_matrix_profiles_are_separate(self):
        profile = {"coverage": True, "monaco": "warm", "jobs": ["chrome", "edge"]}
        key = ci.artifact_name(CONFIG, profile)
        for change in [{"coverage": False}, {"monaco": "cold"}, {"monaco": "mixed"}, {"jobs": ["chrome"]}]:
            self.assertNotEqual(key, ci.artifact_name(CONFIG, {**profile, **change}))
        self.assertNotEqual(key, ci.artifact_name({**CONFIG, "reference": "new"}, profile))
        self.assertEqual(key, ci.artifact_name({**CONFIG, "warning_ratio": 0.15}, profile))

    def test_report_contains_actionable_diagnostics_and_counts(self):
        previous = calibrated()
        current = sample(21, 400)
        current["tests"]["chrome / a|b.spec.ts"] = 50
        record, findings = ci.assess(current, previous, CONFIG)
        text = ci.summary(record, previous, findings, CONFIG, {"coverage": True, "monaco": "warm", "jobs": ["browser"]})
        self.assertIn("browser / tests", text)
        self.assertIn("chrome / a\\|b.spec.ts", text)
        self.assertIn("Executed test cases", text)
        self.assertIn("WARNING", text)
        self.assertLess(text.index("| Cumulative runner time |"), text.index("**WARNING:**"))
        self.assertIn("all jobs", text)


class TimingTest(unittest.TestCase):
    def test_partial_reruns_keep_unchanged_jobs_and_replace_old_failed_attempts(self):
        old = {"id": 1, "name": "editor", "conclusion": "failure"}
        unchanged = {"id": 2, "name": "php", "conclusion": "success"}
        replacement = {"id": 3, "name": "editor", "conclusion": "success"}
        guard = {"id": 4, "name": ci.GUARD_JOB, "conclusion": None}
        self.assertEqual(ci.latest_jobs([guard, replacement, unchanged, old]), [replacement, unchanged, guard])

    def test_profiles_follow_actual_cache_and_coverage_step_outcomes(self):
        run = {"id": 1, "head_sha": "abc", "html_url": "https://example.test/1"}
        for states, cache in [(["skipped", "skipped"], "warm"), (["success", "success"], "cold"), (["success", "skipped"], "mixed")]:
            for coverage in ("success", "skipped"):
                jobs = [{"id": index, "name": f"editor-{index}", "conclusion": "success",
                         "started_at": "2026-11-17T00:01:00Z", "completed_at": "2026-11-17T00:02:00Z",
                         "steps": [{"name": "Build Monaco and its language workers", "conclusion": state,
                                    "started_at": "2026-11-17T00:01:00Z", "completed_at": "2026-11-17T00:01:01Z"},
                                   {"name": "Merge coverage", "conclusion": coverage,
                                    "started_at": "2026-11-17T00:01:01Z", "completed_at": "2026-11-17T00:01:02Z"}]}
                        for index, state in enumerate(states)]
                with self.subTest(cache=cache, coverage=coverage):
                    profile, _ = ci.job_sample(run, jobs)
                    self.assertEqual(profile["monaco"], cache)
                    self.assertEqual(profile["coverage"], coverage == "success")

    def test_job_runtime_excludes_queue_and_guard(self):
        run = {"id": 1, "head_sha": "abc", "html_url": "https://example.test/1", "created_at": "2026-11-17T00:00:00Z"}
        step = {"name": "Build Monaco and its language workers", "conclusion": "skipped"}
        job = {"id": 1, "name": "editor", "conclusion": "success", "started_at": "2026-11-17T00:02:00Z", "completed_at": "2026-11-17T00:03:00Z", "steps": [step]}
        profile, current = ci.job_sample(run, [job, {"name": ci.GUARD_JOB, "conclusion": None}])
        self.assertEqual(current["metrics"], {"longest_job_seconds": 60, "runner_seconds": 60})
        self.assertEqual(current["job_ids"], [1])
        self.assertEqual(profile["monaco"], "warm")

    def test_failed_upstream_and_negative_durations_cannot_be_measured(self):
        for state in ("failure", "cancelled", "skipped", None):
            with self.subTest(state=state), self.assertRaisesRegex(ValueError, "tests"):
                ci.job_sample({}, [{"name": "tests", "conclusion": state}])
        with self.assertRaisesRegex(ValueError, "no jobs"):
            ci.job_sample({}, [])
        with self.assertRaises(ValueError):
            ci.seconds("2026-11-17T00:02:00Z", "2026-11-17T00:01:00Z")

    def test_phpunit_counts_actual_outcomes_across_all_batches(self):
        case = '<testcase name="works" file="/home/runner/work/repo/repo/packages/core/tests/PageTest.php" time="1.25"/>'
        zipped = archive({f"junit-{batch}.xml": f"<testsuite>{case}</testsuite>" for batch in ("parallel", "serial", "worker")})
        groups, count = ci.test_timings("phpunit-24", zipped)
        self.assertEqual(count, 3)
        self.assertEqual(groups, {"phpunit-24 / core/tests/PageTest.php": 3.75})

    def test_missing_batch_or_skipped_phpunit_case_is_rejected(self):
        with self.assertRaises(ValueError):
            ci.test_timings("phpunit-24", archive({"junit-parallel.xml": "<testsuite/>"}))
        for outcome in ("failure", "error", "skipped"):
            zipped = archive({f"junit-{batch}.xml": f'<testsuite><testcase time="1"><{outcome}/></testcase></testsuite>' for batch in ("parallel", "serial", "worker")})
            with self.subTest(outcome=outcome), self.assertRaises(ValueError):
                ci.test_timings("phpunit-24", zipped)

    def browser_report(self, results):
        return {"suites": [{"suites": [{"specs": [{"file": "editor.spec.ts", "tests": [{"projectName": "chrome", "results": results}]}]}]}], "errors": []}

    def test_browser_cases_are_counted_in_nested_suites(self):
        report = self.browser_report([{"status": "passed", "duration": 1500}])
        groups, count = ci.test_timings("editor-chrome-1", archive({"report/results.json": json.dumps(report)}))
        self.assertEqual(count, 1)
        self.assertEqual(groups, {"chrome / editor.spec.ts": 1.5})

    def test_retries_and_global_browser_errors_are_rejected(self):
        for results, errors in [([{"status": "failed", "duration": 10}, {"status": "passed", "duration": 10}], []),
                                ([{"status": "passed", "duration": 10, "retry": 1}], []),
                                ([{"status": "passed", "duration": 10}], [{"message": "Test ended"}]),
                                ([], [])]:
            report = {**self.browser_report(results), "errors": errors}
            with self.assertRaises(ValueError):
                ci.test_timings("editor-chrome-1", archive({"results.json": json.dumps(report)}))


class IntegrationTest(unittest.TestCase):
    def test_cli_carries_a_fixed_reference_and_writes_summary_and_outputs(self):
        with TemporaryDirectory() as directory:
            root = Path(directory)
            config_file = root / "config.json"
            config_file.write_text(json.dumps(CONFIG))
            report = {"suites": [{"specs": [{"file": "editor.spec.ts", "tests": [{
                "projectName": "chrome", "results": [{"status": "passed", "duration": 1000}],
            }]}]}]}
            source = archive({"results.json": json.dumps(report)})
            step = {"name": "Upload results, failure screenshots and traces", "conclusion": "success",
                    "started_at": "2026-11-17T00:01:59Z", "completed_at": "2026-11-17T00:02:00Z"}
            job = {"id": 100, "name": "editor", "conclusion": "success",
                   "started_at": "2026-11-17T00:01:00Z", "completed_at": "2026-11-17T00:02:00Z",
                   "steps": [{"name": "Build Monaco and its language workers", "conclusion": "skipped"}, step]}
            run = {"id": 10, "head_sha": "current", "html_url": "https://example.test/10"}
            profile, _ = ci.job_sample(run, [job])
            previous = calibrated()
            previous.update({"profile": profile, "config": CONFIG})
            previous_archive = archive({"ci-performance.json": json.dumps(previous)})
            name = ci.artifact_name(CONFIG, profile)
            calls = []

            def fake_github(path, binary=False):
                calls.append(path)
                if path == "repos/example/repo/actions/runs/10":
                    return run
                if path.startswith("repos/example/repo/actions/runs/10/jobs?"):
                    self.assertIn("filter=all", path)
                    return {"jobs": [job, {"id": 101, "name": ci.GUARD_JOB, "conclusion": None, "steps": []}]}
                if path.startswith("repos/example/repo/actions/runs/10/artifacts?"):
                    return {"artifacts": [{"id": 1, "name": "editor-chrome-1"}]}
                if path.startswith("repos/example/repo/actions/artifacts?"):
                    self.assertIn(f"name={name}", path)
                    return {"artifacts": [
                        {"id": 99, "expired": False, "workflow_run": {"repository_id": 1, "head_repository_id": 2}},
                        {"id": 98, "expired": True, "workflow_run": {"repository_id": 1, "head_repository_id": 1}},
                        {"id": 2, "expired": False, "workflow_run": {"repository_id": 1, "head_repository_id": 1}},
                    ]}
                if path == "repos/example/repo/actions/artifacts/1/zip":
                    self.assertTrue(binary)
                    return source
                if path == "repos/example/repo/actions/artifacts/2/zip":
                    self.assertTrue(binary)
                    return previous_archive
                self.fail(f"Unexpected API call: {path}")

            environment = {"GITHUB_OUTPUT": str(root / "outputs"), "GITHUB_STEP_SUMMARY": str(root / "summary")}
            argv = ["ci_performance.py", "--repository", "example/repo", "--run-id", "10", "--config", str(config_file), "--output", str(root / "result.json")]
            with patch.object(ci, "github", side_effect=fake_github), patch("sys.argv", argv), patch.dict(os.environ, environment), redirect_stdout(StringIO()):
                self.assertEqual(ci.main(), 0)
            saved = json.loads((root / "result.json").read_text())
            self.assertEqual(saved["baseline"], previous["baseline"])
            self.assertEqual(saved["current"]["counts"], {"editor-chrome-1": 1})
            self.assertEqual(saved["current"]["metrics"]["longest_job_seconds"], 60)
            self.assertIn("Calibrated", (root / "summary").read_text())
            self.assertIn(name, (root / "outputs").read_text())
            self.assertFalse(any("/99/zip" in call or "/98/zip" in call for call in calls))

    def test_pagination_does_not_truncate_large_matrices_or_artifact_lists(self):
        with patch.object(ci, "github", side_effect=[{"jobs": list(range(100))}, {"jobs": [100]}]) as api:
            self.assertEqual(list(ci.github_items("example", "jobs", filter="latest")), list(range(101)))
            self.assertIn("page=2", api.call_args.args[0])


if __name__ == "__main__":
    unittest.main()
