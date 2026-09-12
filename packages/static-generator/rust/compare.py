#!/usr/bin/env python3
"""Compare two native binaries on rendered HTML (Linux, one CPU, 1,000 documents)."""
import hashlib
import json
import os
from pathlib import Path
import random
import subprocess
import sys
import time

if len(sys.argv) < 4:
    raise SystemExit("Usage: compare.py baseline-binary candidate-binary page.html [another.html ...]")

os.sched_setaffinity(0, {min(os.sched_getaffinity(0))})
engines = {"before": sys.argv[1], "candidate": sys.argv[2]}
pages = [Path(path).read_text() for path in sys.argv[3:]]
request = (json.dumps({"version": 1, "id": 1, "operation": "minify_html",
                      "documents": [pages[i % len(pages)] for i in range(1000)]},
                     ensure_ascii=False) + "\n").encode()
report = {
    "methodology": "One CPU, 1000 documents repeating supplied HTML; 9 interleaved samples. "
                   "Wall time includes startup, stdin, native work and stdout collection. "
                   "No PHP adapter. Every output compared byte for byte.",
    "binary_sha256": {key: hashlib.sha256(Path(path).read_bytes()).hexdigest()
                      for key, path in engines.items()},
    "input_sha256": [hashlib.sha256(page.encode()).hexdigest() for page in pages],
    "request_bytes": len(request),
    "samples": [],
}
rng = random.Random(290912)
expected = None
for repeat in range(9):
    modes = list(engines)
    rng.shuffle(modes)
    for mode in modes:
        start = time.perf_counter()
        result = subprocess.run([engines[mode]], input=request, capture_output=True,
                                check=True, timeout=30)
        elapsed = (time.perf_counter() - start) * 1000
        if expected is None:
            response = json.loads(result.stdout)
            if (not isinstance(response, dict) or response.get("version") != 1
                    or response.get("id") != 1
                    or not isinstance(response.get("documents"), list)
                    or len(response["documents"]) != 1000
                    or not all(isinstance(document, str) for document in response["documents"])):
                raise RuntimeError("The first binary did not return a valid minification response")
            expected = result.stdout
        if result.stdout != expected:
            raise RuntimeError("Baseline and candidate outputs differ")
        report["samples"].append({"mode": mode, "repeat": repeat, "ms": elapsed})
print(json.dumps(report, indent=2))
