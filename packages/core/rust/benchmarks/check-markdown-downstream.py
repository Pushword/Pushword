#!/usr/bin/env python3
"""Compare a private downstream Markdown snapshot with the experimental probe."""

import collections
import hashlib
import json
import re
import subprocess
import sys


def sha256(path):
    digest = hashlib.sha256()
    with open(path, "rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def features(case):
    source = case["markdown"]
    html = case["php"]
    checks = {
        "obfuscated_link": bool(re.search(r"#\[[^]]+\]\(", source)),
        "notice": "> [!" in source,
        "image": "![" in source,
        "phone": 'data-rot="gry:' in html,
        "email": "class=nojs" in html,
        "date": "date(" in source,
        "attributes": bool(re.search(r"\{[.#]|\{(?:id|class)=", source)),
    }
    return [name for name, present in checks.items() if present] or ["unclassified"]


def compare(binary, cases, counts, page_equal, supported_only):
    if not cases:
        return
    request = json.dumps(
        {
            "documents": [case["markdown"] for case in cases],
            "fenced_code_pre_class": cases[0]["pre_class"],
            "supported_only": supported_only,
        },
        ensure_ascii=False,
    )
    result = subprocess.run([binary], input=request, text=True, capture_output=True, check=True)
    output = json.loads(result.stdout)
    if len(output) != len(cases):
        raise RuntimeError("Probe returned a different document count")
    for case, actual in zip(cases, output):
        counts["blocks"] += 1
        page = case["page"]
        page_equal.setdefault(page, True)
        if actual is None and supported_only:
            counts["declined_to_php"] += 1
            continue
        if actual == case["php"]:
            counts["exact"] += 1
            if supported_only:
                counts["accepted_native"] += 1
        else:
            counts["mismatch"] += 1
            page_equal[page] = False
            names = features(case)
            if any(name in {"obfuscated_link", "notice", "image", "phone", "email", "date"} for name in names):
                counts["mismatch_with_site_features"] += 1
            else:
                counts["mismatch_without_site_features"] += 1
            for name in names:
                counts["mismatch_" + name] += 1


def main():
    if len(sys.argv) not in (3, 4) or (len(sys.argv) == 4 and sys.argv[3] != "--supported-only"):
        raise SystemExit("Usage: check-markdown-downstream.py <private.ndjson> <probe-binary> [--supported-only]")
    source, binary = sys.argv[1:3]
    supported_only = len(sys.argv) == 4
    counts = collections.Counter()
    page_equal = {}
    batch = []
    batch_bytes = 0
    with open(source, encoding="utf-8") as stream:
        for line in stream:
            case = json.loads(line)
            size = len(case["markdown"].encode())
            if size > 4_000_000:
                raise RuntimeError("A Markdown block exceeds the probe batch limit")
            if batch and (
                len(batch) >= 250
                or batch_bytes + size > 4_000_000
                or case["pre_class"] != batch[0]["pre_class"]
            ):
                compare(binary, batch, counts, page_equal, supported_only)
                batch = []
                batch_bytes = 0
            batch.append(case)
            batch_bytes += size
        compare(binary, batch, counts, page_equal, supported_only)
    print(
        json.dumps(
            {
                "snapshot_sha256": sha256(source),
                "probe_sha256": sha256(binary),
                "supported_only": supported_only,
                "pages": len(page_equal),
                "pages_exact": sum(page_equal.values()),
                "counts": dict(sorted(counts.items())),
            },
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
