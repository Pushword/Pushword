"""Track comparable CI runs without treating one slow runner as a regression."""

import argparse
from collections import Counter
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
import hashlib
from io import BytesIO
import json
import os
from pathlib import Path
from statistics import median
import subprocess
import sys
from urllib.parse import urlencode
import xml.etree.ElementTree as ET
from zipfile import ZipFile


GUARD_JOB = "CI performance"
METRICS = {
    "longest_job_seconds": "Longest job (queue excluded)",
    "runner_seconds": "Cumulative runner time",
}


def github(path, binary=False):
    output = subprocess.check_output(["gh", "api", path])
    return output if binary else json.loads(output)


def github_items(path, key, **parameters):
    page = 1
    while True:
        data = github(f"{path}?{urlencode({**parameters, 'per_page': 100, 'page': page})}")[key]
        yield from data
        if len(data) < 100:
            return
        page += 1


def seconds(start, end):
    value = (datetime.fromisoformat(end.replace("Z", "+00:00"))
             - datetime.fromisoformat(start.replace("Z", "+00:00"))).total_seconds()
    if value < 0:
        raise ValueError("Negative job or step duration")
    return value


def job_sample(run, jobs):
    jobs = [job for job in jobs if job["name"] != GUARD_JOB]
    unsuccessful = [job["name"] for job in jobs if job["conclusion"] != "success"]
    if not jobs or unsuccessful:
        raise ValueError(f"All upstream jobs must pass before measuring CI: {', '.join(unsuccessful) or 'no jobs'}")
    durations = {job["name"]: seconds(job["started_at"], job["completed_at"]) for job in jobs}
    steps = {
        f"{job['name']} / {step['name']}": seconds(step["started_at"], step["completed_at"])
        for job in jobs for step in job["steps"] if step["conclusion"] == "success"
    }
    monaco = [step["conclusion"] for job in jobs for step in job["steps"]
              if step["name"] == "Build Monaco and its language workers"]
    if not monaco or any(state not in ("success", "skipped") for state in monaco):
        raise ValueError("Missing Monaco cache profile")
    cache = "mixed"
    if all(state == "skipped" for state in monaco):
        cache = "warm"
    elif all(state == "success" for state in monaco):
        cache = "cold"
    profile = {
        "coverage": any(step["name"] == "Merge coverage" and step["conclusion"] == "success"
                        for job in jobs for step in job["steps"]),
        "monaco": cache,
        "jobs": sorted(durations),
    }
    return profile, {
        "run_id": run["id"], "commit": run["head_sha"], "url": run["html_url"],
        "job_ids": sorted(job["id"] for job in jobs),
        "metrics": {"longest_job_seconds": max(durations.values()),
                    "runner_seconds": sum(durations.values())},
        "jobs": durations, "steps": steps,
    }


def test_timings(name, archive):
    groups, outcomes = {}, Counter()

    def add(group, duration, state):
        groups[group] = groups.get(group, 0) + duration
        outcomes[state] += 1

    with ZipFile(BytesIO(archive)) as files:
        if name.startswith("phpunit-"):
            reports = [path for path in files.namelist() if Path(path).name.startswith("junit-")
                       and path.endswith(".xml")]
            if len(reports) != 3:
                raise ValueError(f"{name}: expected parallel, serial and worker reports")
            for report in reports:
                for case in ET.fromstring(files.read(report)).iter("testcase"):
                    state = next((state for state in ("failure", "error", "skipped")
                                  if case.find(state) is not None), "passed")
                    file = case.get("file", case.get("class", "unknown")).split("/packages/")[-1]
                    add(f"{name} / {file}", float(case.get("time", 0)), state)
        else:
            reports = [path for path in files.namelist() if Path(path).name == "results.json"]
            if len(reports) != 1:
                raise ValueError(f"{name}: expected one Playwright report")
            report = json.loads(files.read(reports[0]))

            def walk(suites):
                for suite in suites:
                    walk(suite.get("suites", []))
                    for spec in suite.get("specs", []):
                        for test in spec["tests"]:
                            results = test["results"]
                            state = "passed" if (len(results) == 1 and results[0]["status"] == "passed"
                                                 and results[0].get("retry", 0) == 0) else "failed"
                            add(f"{test['projectName']} / {spec['file']}",
                                sum(result["duration"] for result in results) / 1000, state)
            walk(report["suites"])
            if report.get("errors"):
                raise ValueError(f"{name}: Playwright reported global errors")
    if not outcomes or any(state != "passed" for state in outcomes):
        raise ValueError(f"{name}: incomplete or unsuccessful test results: {dict(outcomes)}")
    return groups, outcomes["passed"]


def artifact_name(config, profile):
    key = json.dumps([config["reference"], profile], sort_keys=True).encode()
    return "ci-performance-" + hashlib.sha256(key).hexdigest()[:16]


def compact(sample):
    return {key: sample[key] for key in ("run_id", "commit", "url", "job_ids", "metrics")}


def independent_samples(samples, commit=None):
    selected, used = [], set()
    for sample in reversed(samples):
        ids = set(sample["job_ids"])
        # Re-running only this guard, or a subset of jobs, is not a fresh full run.
        if (commit is None or sample["commit"] == commit) and ids.isdisjoint(used):
            selected.append(sample)
            used.update(ids)
    return selected


def assess(sample, previous, config):
    baseline = list(previous.get("baseline", []))
    recent = [item for item in previous.get("recent", []) if item["job_ids"] != sample["job_ids"]]
    current = compact(sample)
    if len(baseline) < config["calibration_runs"] and not any(
        item["job_ids"] == current["job_ids"] for item in baseline
    ):
        # Only independent full runs contribute to calibration.
        if all(set(item["job_ids"]).isdisjoint(current["job_ids"]) for item in baseline):
            baseline.append(current)
    recent = [*recent, current][-max(config["calibration_runs"], config["trend_runs"]):]
    calibrated = len(baseline) >= config["calibration_runs"]
    confirmations = independent_samples(recent, sample["commit"])[:config["confirmation_runs"]]
    trend_samples = independent_samples(recent)[:config["trend_runs"]]
    findings = []
    for key in METRICS:
        reference = median(item["metrics"][key] for item in baseline)
        if reference <= 0:
            raise ValueError("A performance reference must be positive")
        value = sample["metrics"][key]
        trend = median(item["metrics"][key] for item in trend_samples)
        blocking_delta = max(reference * config["blocking_ratio"], config["minimum_blocking_seconds"])
        confirmed = (calibrated and len(confirmations) == config["confirmation_runs"]
                     and value - reference > blocking_delta
                     and median(item["metrics"][key] for item in confirmations) - reference > blocking_delta)
        findings.append({
            "metric": key, "reference": reference, "value": value,
            "ratio": value / reference - 1, "trend_ratio": trend / reference - 1,
            "warning": len(baseline) >= config["trend_runs"]
            and value - reference > reference * config["warning_ratio"],
            "trend_warning": calibrated and len(trend_samples) == config["trend_runs"]
            and trend - reference > reference * config["trend_ratio"],
            "blocked": confirmed,
        })
    return {"baseline": baseline, "recent": recent, "current": sample}, findings


def markdown(value):
    return str(value).replace("|", "\\|").replace("\n", " ").replace("\r", " ").replace("`", "'")


def summary(record, previous, findings, config, profile):
    count = len(record["baseline"])
    state = (f"Observation: **{count}/{config['calibration_runs']} independent runs**. Blocking is disabled."
             if count < config["calibration_runs"] else
             f"Calibrated: **{count} runs**. Blocking requires {config['confirmation_runs']} independent full runs of the same commit.")
    lines = ["## CI performance", "", state, "",
             f"Profile: coverage **{'on' if profile['coverage'] else 'off'}**, Monaco cache **{profile['monaco']}**, **{len(profile['jobs'])} jobs**.",
             "Queue time and this guard's own runtime are excluded. References remain fixed after calibration.", "",
             "| Metric | Reference | Current | Change | Recent median |", "|---|---:|---:|---:|---:|"]
    for finding in findings:
        lines.append(f"| {METRICS[finding['metric']]} | {finding['reference']:.1f}s | {finding['value']:.1f}s | {finding['ratio']:+.1%} | {finding['trend_ratio']:+.1%} |")
    for finding in findings:
        if finding["blocked"]:
            lines.extend(["", f"**BLOCKED:** confirmed regression in {METRICS[finding['metric']] }."])
        elif finding["warning"] or finding["trend_warning"]:
            lines.extend(["", f"**WARNING:** regression signal in {METRICS[finding['metric']] }."])
    lines.extend(["", "### Largest changes since the previous comparable run", ""])
    prior = previous.get("current")
    if prior:
        lines.append(f"Compared with [{prior['run_id']}]({prior['url']}). These diagnostics do not block individual jobs or tests.")
        for key, label in [("steps", "Step"), ("tests", "Test file (sum of case durations)")]:
            lines.extend(["", f"| {label} | Previous | Current | Added |", "|---|---:|---:|---:|"])
            values = record["current"][key]
            old = prior[key]
            for name in sorted(values, key=lambda name: values[name] - old.get(name, 0), reverse=True)[:10]:
                before = f"{old[name]:.1f}s" if name in old else "new"
                lines.append(f"| {markdown(name)} | {before} | {values[name]:.1f}s | {values[name] - old.get(name, 0):+.1f}s |")
    else:
        lines.append("First measurement for this profile; no comparable previous run yet.")
    lines.extend(["", "### Executed test cases", "", "| Report | Passed | Previous |", "|---|---:|---:|"])
    for name, count in sorted(record["current"]["counts"].items()):
        before = prior["counts"].get(name, "new") if prior else "—"
        lines.append(f"| {markdown(name)} | {count} | {before} |")
    lines.extend(["", "To confirm a suspected regression, re-run **all jobs** twice on the same commit. Re-running only this guard cannot confirm it.",
                  "Review `.github/ci-performance-budget.json` to change thresholds or deliberately reset the reference.", ""])
    return "\n".join(lines)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repository", default=os.environ.get("GITHUB_REPOSITORY"), required="GITHUB_REPOSITORY" not in os.environ)
    parser.add_argument("--run-id", default=os.environ.get("GITHUB_RUN_ID"), required="GITHUB_RUN_ID" not in os.environ)
    parser.add_argument("--config", type=Path, default=Path(".github/ci-performance-budget.json"))
    parser.add_argument("--output", type=Path, default=Path("ci-performance.json"))
    args = parser.parse_args()
    config = json.loads(args.config.read_text())
    root = f"repos/{args.repository}/actions"
    run = github(f"{root}/runs/{args.run_id}")
    jobs = list(github_items(f"{root}/runs/{args.run_id}/jobs", "jobs", filter="latest"))
    profile, sample = job_sample(run, jobs)
    name = artifact_name(config, profile)
    artifacts = list(github_items(f"{root}/runs/{args.run_id}/artifacts", "artifacts"))
    reports = [artifact for artifact in artifacts if artifact["name"].startswith(("phpunit-", "editor-"))]
    expected = sum(step["name"] in ("Upload PHPUnit timings", "Upload results, failure screenshots and traces")
                   for job in jobs for step in job["steps"])
    if len(reports) != expected or not reports:
        raise ValueError(f"Expected {expected} test artifacts, found {len(reports)}")

    def read_tests(artifact):
        return test_timings(artifact["name"], github(f"{root}/artifacts/{artifact['id']}/zip", binary=True))

    sample["tests"], sample["counts"] = {}, {}
    with ThreadPoolExecutor(max_workers=4) as pool:
        for artifact, (groups, count) in zip(reports, pool.map(read_tests, reports)):
            sample["counts"][artifact["name"]] = count
            for key, duration in groups.items():
                sample["tests"][key] = sample["tests"].get(key, 0) + duration
    previous = {}
    for artifact in github_items(f"{root}/artifacts", "artifacts", name=name):
        origin = artifact["workflow_run"]
        if artifact["expired"] or origin["repository_id"] != origin["head_repository_id"]:
            continue
        with ZipFile(BytesIO(github(f"{root}/artifacts/{artifact['id']}/zip", binary=True))) as archive:
            candidate = json.loads(archive.read("ci-performance.json"))
        if candidate["profile"] == profile and candidate["config"]["reference"] == config["reference"]:
            previous = candidate
            break
    record, findings = assess(sample, previous, config)
    record.update({"profile": profile, "config": config})
    args.output.write_text(json.dumps(record, sort_keys=True))
    report = summary(record, previous, findings, config, profile)
    print(report)
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
            output.write(report)
    if os.environ.get("GITHUB_OUTPUT"):
        with open(os.environ["GITHUB_OUTPUT"], "a") as output:
            output.write(f"artifact-name={name}\n")
    for finding in findings:
        if finding["blocked"] or finding["warning"] or finding["trend_warning"]:
            level = "error" if finding["blocked"] else "warning"
            print(f"::{level} title=CI performance::{METRICS[finding['metric']]}: {finding['ratio']:+.1%}; recent median {finding['trend_ratio']:+.1%}")
    return int(any(finding["blocked"] for finding in findings))


if __name__ == "__main__":
    sys.exit(main())
