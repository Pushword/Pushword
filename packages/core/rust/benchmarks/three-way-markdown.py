"""Compare three Markdown renderers on a synthetic or supplied site corpus."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import statistics
import subprocess
import sys
import tempfile
import time

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
MODES = ('php', 'tempest', 'rust')
SYNTHETIC = (
    'Parcours {n} dans les collines.',
    '## Étape {n} **facile**',
    'Voir [la carte](/carte) pour l’étape {n}.',
    'Un `code` simple pour le trajet {n}.',
    '- Départ {n}\n- Arrivée {n}',
    '1. Première étape {n}\n2. Deuxième étape {n}',
    '| Nom | Valeur |\n|---|---|\n| Étape {n} | {n} |',
    '> Une question {n}\n> Une réponse simple.',
    'Prix & transport pour l’étape {n}.',
    'Une _petite marche_ avant l’étape {n}.',
    'Le numéro 01 23 45 67 89 pour l’étape {n}.',
    '> [!faq] Question {n}\n>\n> Une réponse courte.',
)


def rss_kib(pid):
    try:
        with open(f'/proc/{pid}/status', encoding='ascii') as stream:
            for line in stream:
                if line.startswith('VmRSS:'):
                    return int(line.split()[1])
    except OSError:
        pass
    return 0


def descendants(pid):
    try:
        with open(f'/proc/{pid}/task/{pid}/children', encoding='ascii') as stream:
            direct = [int(value) for value in stream.read().split()]
    except OSError:
        return []
    return direct + [nested for child in direct for nested in descendants(child)]


def worker_command(mode, site, snapshot, binary):
    return ['php', str(HERE / 'three-way-markdown.php'), mode, str(site), str(snapshot), str(binary)]


def sample(mode, site, snapshot, binary, cpu, env=None):
    command = worker_command(mode, site, snapshot, binary)
    if cpu is not None:
        command = ['taskset', '-c', str(cpu), *command]
    process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    parent_peak = child_peak = tree_peak = 0
    while process.poll() is None:
        parent = rss_kib(process.pid)
        child = sum(rss_kib(pid) for pid in descendants(process.pid))
        parent_peak = max(parent_peak, parent)
        child_peak = max(child_peak, child)
        tree_peak = max(tree_peak, parent + child)
        time.sleep(0.002)
    stdout, stderr = process.communicate()
    if process.returncode:
        raise RuntimeError(f'{mode}: {stderr}')
    result = json.loads(stdout)
    result.update(parent_peak_kib=parent_peak, child_peak_kib=child_peak, tree_peak_kib=tree_peak)
    return result


def sha256(path):
    digest = hashlib.sha256()
    with open(path, 'rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def git_revision(path):
    return subprocess.check_output(['git', '-C', str(path), 'rev-parse', 'HEAD'], text=True).strip()


def synthetic_snapshot(path, blocks, site, binary, env):
    raw = path.with_name('raw.ndjson')
    with open(raw, 'w', encoding='utf-8') as stream:
        for index in range(blocks):
            record = {
                'page': 'localhost.dev/benchmark',
                'block': index,
                'markdown': SYNTHETIC[index % len(SYNTHETIC)].format(n=index),
                'pre_class': '',
            }
            stream.write(json.dumps(record, ensure_ascii=False) + '\n')
    with open(path, 'w', encoding='utf-8') as stream:
        subprocess.run(worker_command('prepare', site, raw, binary), cwd=ROOT, env=env, stdout=stream, check=True)


def run(site, snapshot, binary, cpu, runs, corpus, env=None):
    samples = []
    for round_number in range(runs):
        offset = round_number % len(MODES)
        for mode in MODES[offset:] + MODES[:offset]:
            result = sample(mode, site, snapshot, binary, cpu, env)
            samples.append(result)
            print(f'{mode}: {result["conversion_seconds"]:.3f}s, {result["tree_peak_kib"] / 1024:.1f} MiB', file=sys.stderr, flush=True)

    if len({result['digest'] for result in samples}) != 1 or len({result['blocks'] for result in samples}) != 1 or any(result['different'] for result in samples):
        raise RuntimeError('Output parity failed')

    medians = {
        mode: {
            key: statistics.median(result[key] for result in samples if result['mode'] == mode)
            for key in ('conversion_seconds', 'parent_peak_kib', 'child_peak_kib', 'tree_peak_kib', 'zend_peak_bytes')
        }
        for mode in MODES
    }
    return {
        'corpus': corpus,
        'site_revision': git_revision(site),
        'pushword_revision': git_revision(ROOT),
        'snapshot_sha256': sha256(snapshot),
        'binary_sha256': sha256(binary),
        'blocks': samples[0]['blocks'],
        'cpu_affinity': cpu,
        'passes': runs,
        'output_sha256': samples[0]['digest'],
        'samples': samples,
        'medians': medians,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--site', type=Path)
    parser.add_argument('--snapshot', type=Path)
    parser.add_argument('--blocks', type=int, default=24000)
    parser.add_argument('--binary', type=Path, default=ROOT / 'packages/core/rust/target/release/pushword-content-analyzer')
    parser.add_argument('--cpu', type=int)
    parser.add_argument('--runs', type=int, default=3)
    args = parser.parse_args()
    if (args.site is None) != (args.snapshot is None):
        parser.error('--site and --snapshot must be supplied together')
    if args.blocks < 1 or args.runs < 1:
        parser.error('--blocks and --runs must be positive')

    binary = args.binary.resolve()
    if args.site is not None:
        site = args.site.resolve()
        snapshot = args.snapshot.resolve()
        report = run(site, snapshot, binary, args.cpu, args.runs, 'site snapshot')
    else:
        site = ROOT / 'packages/dev-app'
        with tempfile.TemporaryDirectory(prefix='pushword-markdown-bench-') as directory:
            paths = {name: Path(directory) / name for name in ('var', 'media', 'media-cache', 'content')}
            for path in paths.values():
                path.mkdir()
            database = Path(directory) / 'benchmark.db'
            database.touch()
            env = os.environ.copy()
            env.update({
                'PUSHWORD_TEST_VAR_DIR': str(paths['var']),
                'PUSHWORD_TEST_MEDIA_DIR': str(paths['media']),
                'PUSHWORD_TEST_MEDIA_CACHE_DIR': str(paths['media-cache']),
                'PUSHWORD_TEST_FLAT_CONTENT_DIR': str(paths['content']),
                'PUSHWORD_TEST_DATABASE_URL': f'sqlite:///{database}',
            })
            snapshot = Path(directory) / 'synthetic.ndjson'
            synthetic_snapshot(snapshot, args.blocks, site, binary, env)
            report = run(site, snapshot, binary, args.cpu, args.runs, f'synthetic {args.blocks} blocks / {len(SYNTHETIC)} patterns', env)
    print(json.dumps(report, indent=2))


if __name__ == '__main__':
    main()
