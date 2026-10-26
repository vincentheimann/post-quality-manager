#!/usr/bin/env python3
"""Runs WP-CLI on a Local site found by its name (backlog story 9).

    python -I bin/local-wp.py --site "<Local site name>" <WP-CLI command>
    python -I bin/local-wp.py --site "<Local site name>" import <dump.sql>

Finds the one site with that name in Local's own site list
(%APPDATA%\\Local\\sites.json) and runs the rest of the command line as WP-CLI
there: Local's PHP for the site's version with the site's php.ini, the
wp-cli.phar Local bundles, --path=<site folder>\\app\\public, with Local's MySQL
client and PHP's ImageMagick folder first on PATH (php_imagick.dll then loads
instead of printing a startup warning before every output).

The MySQL client reaches the site's server only with its port: `db export`
gets `--port=<the site's port> --protocol=tcp` added; `db import` and
`db query` ignore the port (WP-CLI runs a check without it first), so they are
refused here. Query with `eval` and `$wpdb`; restore with:

`import <dump.sql>` replaces the site's database with the dump through Local's
MySQL client (127.0.0.1, the site's port): it drops every table of the
database, feeds the dump and prints the table count. Dropping first is what
makes it a restore: a build installed during a walk can rename or add tables
(story 91 renames the reviews table), and a plain import would leave them.

Nothing site-specific lives in this repository: the site is named on the
command line and the rest is read from Local at run time; the database
password reaches the client through MYSQL_PWD and is never printed. Windows
only (Local on the owner's PC). Exit code: WP-CLI's, or the MySQL client's;
2 when the site, Local's PHP, WP-CLI or MySQL client cannot be found, or the
command is refused. How a walk uses it: docs/local-verification.md.
Standard library only. Repository tooling: not in the zip.
"""
from __future__ import annotations

import glob
import json
import os
import subprocess
import sys

USAGE = 'Usage: python -I bin/local-wp.py --site "<Local site name>" <WP-CLI command> | import <dump.sql>'


def fail(message: str) -> None:
    print(message, file=sys.stderr)
    sys.exit(2)


def parse(argv: list[str]) -> tuple[str, list[str]]:
    """`--site <name>` (or `--site=<name>`) first, then the command, untouched."""
    if argv and argv[0].startswith('--site='):
        name, rest = argv[0][len('--site='):], argv[1:]
    elif len(argv) >= 2 and argv[0] == '--site':
        name, rest = argv[1], argv[2:]
    else:
        fail(USAGE)
    if not rest:
        fail(USAGE)
    return name, rest


def find_site(name: str, appdata: str) -> tuple[str, dict]:
    sites_json = os.path.join(appdata, 'Local', 'sites.json')
    if not os.path.isfile(sites_json):
        fail(f'Local\'s site list is missing: {sites_json}')
    with open(sites_json, encoding='utf-8') as f:
        sites = json.load(f)
    matches = [(site_id, site) for site_id, site in sites.items() if site.get('name') == name]
    if len(matches) != 1:
        known = '\n  '.join(sorted(str(site.get('name')) for site in sites.values()))
        reason = 'is not in' if not matches else f'is {len(matches)} times in'
        fail(f'"{name}" {reason} Local\'s site list. The names Local knows:\n  {known}')
    return matches[0]


def service_exe(appdata: str, service: str, version: str, exe: str) -> str:
    """Local's folder for a service version carries a `+<build>` suffix sites.json lacks."""
    base = os.path.join(appdata, 'Local', 'lightning-services')
    folders = glob.glob(os.path.join(base, f'{service}-{version}')) + glob.glob(os.path.join(base, f'{service}-{version}+*'))
    found = sorted(path for path in (os.path.join(folder, exe) for folder in folders) if os.path.isfile(path))
    if not found:
        fail(f'Local\'s {service} {version} is not installed: no {os.path.join(base, f"{service}-{version}[+<build>]", exe)}')
    return found[-1]


def mysql_port(site: dict) -> int:
    ports = site.get('services', {}).get('mysql', {}).get('ports', {}).get('MYSQL')
    port = ports[0] if isinstance(ports, list) and ports else ports
    if not isinstance(port, int):
        fail(f'Local\'s site list gives no MySQL port for this site (services.mysql.ports.MYSQL = {ports!r}).')
    return port


def mysql(client: str, port: int, site: dict, args: list[str], stdin=None) -> subprocess.CompletedProcess:
    db = site.get('mysql', {})
    env = dict(os.environ)
    if db.get('password'):
        env['MYSQL_PWD'] = db['password']
    command = [client, '-h', '127.0.0.1', '-P', str(port), '--protocol=tcp', '-u', db.get('user', 'root'),
               '--default-character-set=utf8mb4', *args, db.get('database', 'local')]
    return subprocess.run(command, env=env, stdin=stdin, stdout=subprocess.PIPE, text=stdin is None)


def import_dump(client: str, port: int, site: dict, dump: str) -> int:
    if not os.path.isfile(dump):
        fail(f'No such dump: {dump}')
    with open(dump, 'rb') as f:
        if b'CREATE TABLE' not in f.read():
            fail(f'{dump} holds no CREATE TABLE: not a database dump, nothing changed.')
    listed = mysql(client, port, site, ['-N', '-B', '-e', "SELECT table_name FROM information_schema.tables "
                                        "WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"])
    if listed.returncode:
        return listed.returncode
    tables = listed.stdout.split()
    if tables:
        drop = 'SET FOREIGN_KEY_CHECKS = 0; DROP TABLE ' + ', '.join(f'`{t}`' for t in tables) + ';'
        dropped = mysql(client, port, site, ['-e', drop])
        if dropped.returncode:
            return dropped.returncode
    with open(dump, 'rb') as f:
        imported = mysql(client, port, site, [], stdin=f)
    if imported.returncode:
        print(f'The import of {dump} stopped with exit {imported.returncode}; the database is incomplete: import again.',
              file=sys.stderr)
        return imported.returncode
    counted = mysql(client, port, site, ['-N', '-B', '-e', 'SELECT COUNT(*) FROM information_schema.tables '
                                         'WHERE table_schema = DATABASE()'])
    if counted.returncode:
        return counted.returncode
    print(f'Imported {dump}: {len(tables)} tables dropped first, {counted.stdout.strip()} tables now.')
    return 0


def main() -> int:
    if os.name != 'nt':
        fail('local-wp.py drives Local on Windows only (it reads %APPDATA%\\Local and Local\'s win64 binaries).')
    sys.stderr.reconfigure(encoding='utf-8', errors='backslashreplace')  # site names with accents, piped
    if sys.argv[1:2] in (['-h'], ['--help']):
        print(__doc__)
        return 0
    name, rest = parse(sys.argv[1:])
    appdata, localappdata = os.environ.get('APPDATA', ''), os.environ.get('LOCALAPPDATA', '')
    site_id, site = find_site(name, appdata)

    services = site.get('services', {})
    if 'mysql' not in services:
        fail(f'This site runs no MySQL service in Local (services: {", ".join(services)}); local-wp.py knows Local\'s MySQL only.')
    php = service_exe(appdata, 'php', services.get('php', {}).get('version', '?'), os.path.join('bin', 'win64', 'php.exe'))
    client = service_exe(appdata, 'mysql', services['mysql'].get('version', '?'), os.path.join('bin', 'win64', 'bin', 'mysql.exe'))
    port = mysql_port(site)
    ini = os.path.join(appdata, 'Local', 'run', site_id, 'conf', 'php', 'php.ini')
    phar = os.path.join(localappdata, 'Programs', 'Local', 'resources', 'extraResources', 'bin', 'wp-cli', 'wp-cli.phar')
    public = os.path.join(os.path.expanduser(site.get('path', '')), 'app', 'public')
    for path in (ini, phar, public):
        if not os.path.exists(path):
            fail(f'Missing: {path}')

    if rest[0] == 'import':
        if len(rest) != 2:
            fail(USAGE)
        return import_dump(client, port, site, rest[1])

    commands = [arg for arg in rest if not arg.startswith('-')]
    if commands[:2] in (['db', 'import'], ['db', 'query']):
        fail(f'wp db {commands[1]} does not reach Local\'s MySQL (it ignores the port): use `import <dump.sql>`, '
             'or `eval` with $wpdb for a query.')
    if commands[:2] == ['db', 'export'] and not any(arg.startswith('--port') for arg in rest):
        rest = [*rest, f'--port={port}', '--protocol=tcp']

    env = dict(os.environ)
    path = [os.path.dirname(client), os.path.join(os.path.dirname(php), 'ImageMagick'), env.get('PATH', '')]
    env['PATH'] = os.pathsep.join(path)
    return subprocess.run([php, '-c', ini, phar, f'--path={public}', *rest], env=env).returncode


if __name__ == '__main__':
    sys.exit(main())
