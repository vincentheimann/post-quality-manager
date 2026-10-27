#!/usr/bin/env python3
"""Offline checks of bin/local-wp.py and bin/webhook-capture.py (backlog story 9).

    python -I tests/harness/local-wp-harness.py

Runs both scripts as subprocesses with APPDATA and LOCALAPPDATA pointed at a
fake Local tree in a temporary folder (sites.json, empty php.exe, mysql.exe,
php.ini and wp-cli.phar, an app\\public folder), so every check of local-wp.py
ends before a binary runs: the rows of the story's I/O matrix below Local
(names, missing PHP or port, refused `db` subcommands, the `import` guard),
plus the listener on a busy and on a free port. The rows that need a running
site are the walk in docs/local-verification.md. Prints as the PHP harnesses
do; exit 1 on a failure, 2 off Windows (the scripts drive Local on Windows
only). The PHP sweep (`tests/harness/*.php`) does not run it: run it after
touching either script. Standard library only.
"""
from __future__ import annotations

import http.client
import json
import os
import socket
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LOCAL_WP = ROOT / 'bin' / 'local-wp.py'
CAPTURE = ROOT / 'bin' / 'webhook-capture.py'
NAMES = ('Harness Site', 'Harness Twice', 'Harness No PHP', 'Harness No MySQL', 'Harness No Port', 'Other Client Site')

passed = failed = 0


def check(label: str, cond: bool) -> None:
    global passed, failed
    if cond:
        passed += 1
        print(f'  ok  {label}')
    else:
        failed += 1
        print(f'  FAIL {label}')


def touch(path: Path, data: bytes = b'') -> Path:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)
    return path


def fake_local(tmp: Path) -> dict:
    """A Local tree with one complete site and one site per error row; returns the scripts' environment."""
    appdata, localappdata, public = tmp / 'appdata', tmp / 'localappdata', tmp / 'site' / 'app' / 'public'
    services = appdata / 'Local' / 'lightning-services'
    touch(services / 'php-8.5.12+0' / 'bin' / 'win64' / 'php.exe')
    touch(services / 'mysql-8.4.0' / 'bin' / 'win64' / 'bin' / 'mysql.exe')
    touch(localappdata / 'Programs' / 'Local' / 'resources' / 'extraResources' / 'bin' / 'wp-cli' / 'wp-cli.phar')
    public.mkdir(parents=True)

    def site(name: str, php: str = '8.5.12', mysql: str = '8.4.0', ports: dict | None = None) -> dict:
        return {'name': name, 'path': str(tmp / 'site'),
                'mysql': {'database': 'harness-db', 'user': 'harness-user', 'password': 'harness-pass'},
                'services': {'php': {'version': php},
                             'mysql': {'version': mysql, 'ports': {'MYSQL': [10001]} if ports is None else ports}}}

    sites = {'id-site': site('Harness Site'), 'id-twice-1': site('Harness Twice'), 'id-twice-2': site('Harness Twice'),
             'id-no-php': site('Harness No PHP', php='0.0.0'), 'id-no-mysql': site('Harness No MySQL', mysql='0.0.0'),
             'id-no-port': site('Harness No Port', ports={}), 'id-other': site('Other Client Site')}
    for site_id in sites:
        touch(appdata / 'Local' / 'run' / site_id / 'conf' / 'php' / 'php.ini')
    touch(appdata / 'Local' / 'sites.json', json.dumps(sites).encode())
    return dict(os.environ, APPDATA=str(appdata), LOCALAPPDATA=str(localappdata))


def local_wp(env: dict, *args: str) -> tuple[int, str]:
    run = subprocess.run([sys.executable, '-I', str(LOCAL_WP), *args], env=env, capture_output=True,
                         text=True, encoding='utf-8', errors='replace', timeout=60)
    return run.returncode, run.stdout + run.stderr


def other_names(output: str, sought: str) -> list[str]:
    return [name for name in NAMES if name != sought and name in output]


def check_local_wp(tmp: Path, env: dict) -> None:
    code, out = local_wp(env, 'plugin', 'list')
    check('no --site: exit 2 with the usage', code == 2 and 'Usage:' in out)

    code, out = local_wp(env, '--site', 'No Such Site', 'plugin', 'list')
    check('unknown name: exit 2, the sought name', code == 2 and '"No Such Site" is not in' in out)
    check('unknown name: no other site named', other_names(out, 'No Such Site') == [])

    code, out = local_wp(env, '--site', 'Harness Twice', 'plugin', 'list')
    check('doubled name: exit 2, the count', code == 2 and '"Harness Twice" is 2 times in' in out)
    check('doubled name: no other site named', other_names(out, 'Harness Twice') == [])

    code, out = local_wp(env, '--site', 'Harness No PHP', 'plugin', 'list')
    check('missing PHP version: exit 2, the missing path', code == 2 and 'php-0.0.0' in out and 'php.exe' in out)
    check('missing PHP version: no other site named', other_names(out, 'Harness No PHP') == [])

    code, out = local_wp(env, '--site', 'Harness No MySQL', 'plugin', 'list')
    check('missing MySQL version: exit 2, the missing path', code == 2 and 'mysql-0.0.0' in out and 'mysql.exe' in out)

    code, out = local_wp(env, '--site', 'Harness No Port', 'plugin', 'list')
    check('no MySQL port: exit 2', code == 2 and 'no MySQL port' in out)

    site = ('--site', 'Harness Site')
    for sub in (['import', 'x.sql'], ['query', 'SELECT 1'], ['reset', '--yes'], ['cli'], ['check'], ['drop', '--yes'],
                ['create'], ['clean', '--yes'], ['optimize'], ['repair']):
        code, out = local_wp(env, *site, 'db', *sub)
        check(f'db {sub[0]}: exit 2, naming import <dump.sql> and eval',
              code == 2 and 'refused' in out and 'import <dump.sql>' in out and 'eval' in out)
    code, out = local_wp(env, *site, '--skip-plugins', 'db', 'query', 'SELECT 1')
    check('db query after a global flag: exit 2', code == 2 and 'refused' in out)
    code, out = local_wp(env, *site, '--skip-plugins', 'import', str(tmp / 'export.xml'))
    check('import after a global flag: the .sql guard, not WP-CLI\'s import', code == 2 and 'not named *.sql' in out)

    # Control: an allowed command passes every check and reaches php.exe, which is empty and cannot
    # start (exit 1, an OSError): the refusals above are not an incomplete fake tree.
    for sub in (['plugin', 'list'], ['db', 'export', 'x.sql'], ['db', 'tables'], ['db', 'size'], ['db', 'prefix'],
                ['db', 'columns', 'wp_options'], ['db', 'search', 'x']):
        code, out = local_wp(env, *site, *sub)
        check(f'{" ".join(sub[:2])}: passes the checks, reaches php.exe', code == 1 and 'OSError' in out)

    xml = touch(tmp / 'export.xml', b'<rss><item>CREATE TABLE in a post</item></rss>')
    code, out = local_wp(env, *site, 'import', str(xml))
    check('import of a .xml: exit 2 before reading it, nothing dropped', code == 2 and 'not named *.sql' in out)
    code, out = local_wp(env, *site, 'import', str(tmp / 'missing.sql'))
    check('import of a missing .sql: exit 2', code == 2 and 'No such dump' in out)
    not_a_dump = touch(tmp / 'notes.sql', b'-- no tables here\n')
    code, out = local_wp(env, *site, 'import', str(not_a_dump))
    check('import of a .sql without CREATE TABLE: exit 2, nothing dropped', code == 2 and 'no CREATE TABLE' in out)
    code, out = local_wp(env, *site, 'import', 'a.sql', 'b.sql')
    check('import of two files: exit 2 with the usage', code == 2 and 'Usage:' in out)


def free_port() -> int:
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0))
        return probe.getsockname()[1]


def check_capture(tmp: Path) -> None:
    # A stale listener as build 1 left it: an HTTPServer socket, SO_REUSEADDR set.
    with socket.socket() as holder:
        holder.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        holder.bind(('127.0.0.1', 0))
        holder.listen()
        port = holder.getsockname()[1]
        try:
            run = subprocess.run([sys.executable, '-I', str(CAPTURE), '--port', str(port), '--out', str(tmp / 'busy.jsonl')],
                                 capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=15)
            busy = run.returncode == 2 and f'Cannot listen on 127.0.0.1:{port}' in run.stderr
        except subprocess.TimeoutExpired:  # it bound the held port and served
            busy = False
    check('listener on a busy port: exit 2, Cannot listen on 127.0.0.1:<port>', busy)

    port, out = free_port(), tmp / 'capture.jsonl'
    listener = subprocess.Popen([sys.executable, '-I', str(CAPTURE), '--port', str(port), '--out', str(out)],
                                stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, encoding='utf-8')
    try:
        started = listener.stdout.readline().startswith(f'Capturing POSTs on http://127.0.0.1:{port}/')
        check('listener on a free port: starts', started)
        payload = {'command': 'review', 'post_id': 7, 'content': 'É text'}
        status, answer = None, b''
        if started:
            connection = http.client.HTTPConnection('127.0.0.1', port, timeout=10)
            connection.request('POST', '/webhook', json.dumps(payload), {'Content-Type': 'application/json'})
            response = connection.getresponse()
            status, answer = response.status, response.read()
            connection.close()
        check('a JSON POST answers 200 {}', status == 200 and answer == b'{}')
    finally:
        listener.terminate()
        listener.wait(timeout=10)
        listener.stdout.close()
    lines = out.read_text(encoding='utf-8').splitlines() if out.is_file() else []
    line = json.loads(lines[0]) if len(lines) == 1 else {}
    check('one JSON line: time, path, headers, body as sent',
          bool(line.get('time')) and line.get('path') == '/webhook' and line.get('body') == payload
          and line.get('headers', {}).get('Content-Type') == 'application/json')


def main() -> int:
    if os.name != 'nt':
        print('local-wp-harness.py runs on Windows only, like the scripts it checks.', file=sys.stderr)
        return 2
    with tempfile.TemporaryDirectory(prefix='local-wp-harness-', ignore_cleanup_errors=True) as tmp:
        env = fake_local(Path(tmp))
        check_local_wp(Path(tmp), env)
        check_capture(Path(tmp))
    print(f'\n{passed} passed, {failed} failed')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
