#!/usr/bin/env python3
"""Compare current Pushword PHP/Rust page rendering on an isolated Altimood copy."""

import argparse
import hashlib
import json
from pathlib import Path
import shutil
import sqlite3
import statistics
import subprocess
import sys
import tempfile
import time


HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
WORKER = HERE / "e2e-altimood.php"
BINARY = ROOT / "packages/core/rust/target/release/pushword-content-analyzer"


def prepare_site(source, destination, native, binary):
    destination.mkdir()
    for name in ("config", "src"):
        shutil.copytree(source / name, destination / name)
    shutil.copy2(source / "composer.json", destination / "composer.json")
    for name in (".env", ".env.local", ".env.test", "vendor", "templates", "translations", "content", "assets"):
        path = source / name
        if path.exists():
            (destination / name).symlink_to(path, target_is_directory=path.is_dir())
    (destination / "media").mkdir()
    (destination / "var").mkdir()
    with sqlite3.connect(f"file:{source / 'var/app.db'}?mode=ro", uri=True) as upstream:
        with sqlite3.connect(destination / "var/app.db") as copy:
            upstream.backup(copy)
    (destination / "public").mkdir()
    for name in ("assets", "build", "bundles"):
        (destination / "public" / name).symlink_to(source / "public" / name, target_is_directory=True)
    (destination / "public/media").mkdir()

    bundles = destination / "config/bundles.php"
    content = bundles.read_text()
    additions = (
        "    KnpU\\OAuth2ClientBundle\\KnpUOAuth2ClientBundle::class => ['all' => true],\n"
        "    Pushword\\Snippet\\PushwordSnippetBundle::class => ['all' => true],\n"
    )
    content = content.replace("return [\n", "return [\n" + additions, 1)
    bundles.write_text(content)
    if native:
        config = destination / "config/packages/pushword.yaml"
        content = config.read_text()
        marker = "pushword:\n"
        if not content.startswith(marker):
            raise RuntimeError("Altimood pushword.yaml has an unexpected layout")
        content = content.replace(marker, f"{marker}  native_content_analyzer: {binary}\n  native_markdown_renderer: {binary}\n", 1)
        config.write_text(content)


def source_digest(snapshot):
    digest = hashlib.sha256()
    for path in sorted(path for path in (snapshot / "packages").rglob("*") if path.is_file()):
        digest.update(str(path.relative_to(snapshot)).encode())
        digest.update(b"\0")
        digest.update(path.read_bytes())
    return digest.hexdigest()


def rss_kib(pid):
    try:
        with open(f"/proc/{pid}/status", encoding="ascii") as stream:
            for line in stream:
                if line.startswith("VmRSS:"):
                    return int(line.split()[1])
    except OSError:
        pass
    return 0


def descendants(pid):
    try:
        with open(f"/proc/{pid}/task/{pid}/children", encoding="ascii") as stream:
            children = [int(value) for value in stream.read().split()]
    except OSError:
        return []
    return children + [nested for child in children for nested in descendants(child)]


def render(site, source_snapshot, url, kind, cpu):
    command = ["php", str(WORKER), str(site), str(source_snapshot), url, kind, "2"]
    if cpu is not None:
        command = ["taskset", "-c", str(cpu), *command]
    process = subprocess.Popen(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    peak = 0
    peak_child = 0
    while process.poll() is None:
        child = sum(rss_kib(pid) for pid in descendants(process.pid))
        peak_child = max(peak_child, child)
        peak = max(peak, rss_kib(process.pid) + child)
        time.sleep(0.005)
    output, error = process.communicate()
    if process.returncode:
        raise RuntimeError(error)
    result = json.loads(output)
    result["sampled_peak_tree_rss_kib"] = peak
    result["sampled_peak_child_rss_kib"] = peak_child
    if any(rendered["status"] != 200 for rendered in result["results"]):
        raise RuntimeError(f"A benchmark response was not HTTP 200: {url} {kind}")
    return result


def clear_fragments(site):
    pool = site / "var/cache/pushword-pools"
    if not pool.is_dir():
        raise RuntimeError(f"The persistent fragment cache was not created: {site}")
    shutil.rmtree(pool)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--site", type=Path, default=ROOT.parent / "altimood")
    parser.add_argument("--url", action="append", required=True)
    parser.add_argument("--kind", action="append", choices=("public", "preview", "static-render"))
    parser.add_argument("--runs", type=int, default=3)
    parser.add_argument("--cpu", type=int)
    args = parser.parse_args()
    if args.runs < 1 or not BINARY.is_file():
        parser.error("A positive run count and a built Rust worker are required")

    source = args.site.resolve()
    kinds = args.kind or ["public", "preview", "static-render"]
    pushword_revision = subprocess.check_output(["git", "-C", str(ROOT), "rev-parse", "HEAD"], text=True).strip()
    altimood_revision = subprocess.check_output(["git", "-C", str(source), "rev-parse", "HEAD"], text=True).strip()
    with tempfile.TemporaryDirectory(prefix="pw-altimood-e2e-") as temporary:
        source_snapshot = Path(temporary) / "pushword"
        (source_snapshot / "packages/core").mkdir(parents=True)
        (source_snapshot / "packages/static-generator").mkdir(parents=True)
        for package in ("core", "static-generator"):
            shutil.copytree(ROOT / "packages" / package / "src", source_snapshot / "packages" / package / "src")
        (source_snapshot / "vendor").symlink_to(ROOT / "vendor", target_is_directory=True)
        binary = source_snapshot / "pushword-content-analyzer"
        shutil.copy2(BINARY, binary)
        frozen_source_sha256 = source_digest(source_snapshot)
        frozen_binary_sha256 = hashlib.sha256(binary.read_bytes()).hexdigest()
        sites = {mode: Path(temporary) / mode for mode in ("php", "rust")}
        for mode, site in sites.items():
            prepare_site(source, site, native=mode == "rust", binary=binary)

        # Compile each site's container and templates before timing fragments.
        for url in args.url:
            for kind in kinds:
                for mode, site in sites.items():
                    render(site, source_snapshot, url, kind, args.cpu)

        cases = []
        for url in args.url:
            for kind in kinds:
                samples = []
                for round_number in range(args.runs):
                    for mode in (("php", "rust") if round_number % 2 == 0 else ("rust", "php")):
                        clear_fragments(sites[mode])
                        result = render(sites[mode], source_snapshot, url, kind, args.cpu)
                        if result["native_configured"] != (mode == "rust"):
                            raise RuntimeError(f"Unexpected native configuration: {mode}")
                        if mode == "rust" and result["sampled_peak_child_rss_kib"] == 0:
                            raise RuntimeError(f"The Rust process was not observed: {url} {kind}")
                        samples.append({"mode": mode, "round": round_number + 1, **result})
                    pair = {sample["mode"]: sample for sample in samples[-2:]}
                    for index in (0, 1):
                        left, right = pair["php"]["results"][index], pair["rust"]["results"][index]
                        if (left["status"], left["sha256"]) != (right["status"], right["sha256"]):
                            raise RuntimeError(f"HTML parity failure for {url} {kind} round {round_number + 1} render {index + 1}")
                    print(f"{kind} {url} round {round_number + 1}: exact", file=sys.stderr, flush=True)

                medians = {
                    mode: {
                        phase: statistics.median(sample["results"][index]["milliseconds"] for sample in samples if sample["mode"] == mode)
                        for index, phase in enumerate(("fragment_cold_ms", "warm_ms"))
                    }
                    for mode in ("php", "rust")
                }
                cases.append({"url": url, "kind": kind, "samples": samples, "medians": medians})

    print(json.dumps({
        "workload": "Isolated Altimood SQLite snapshot and templates, current Pushword source; PHP vs Rust Markdown and split",
        "pushword_revision": pushword_revision,
        "source_snapshot_sha256": frozen_source_sha256,
        "altimood_revision": altimood_revision,
        "binary_sha256": frozen_binary_sha256,
        "runs": args.runs,
        "cpu": args.cpu,
        "cases": cases,
    }, indent=2))


if __name__ == "__main__":
    main()
