#!/usr/bin/env python3
"""Captures the plugin's webhook calls on this machine (backlog story 9).

    python -I bin/webhook-capture.py --port <port> --out <capture.jsonl>

Stands in for n8n on a local walk: point the plugin's `n8n_webhook_url` at
http://127.0.0.1:<port>/<any path>. Listens on 127.0.0.1 only, so post content
never leaves the machine; answers every POST with 200 and `{}`, so the plugin
sees n8n accept the call; appends one JSON line per request to --out:
{"time", "path", "headers", "body"}, the body parsed when it is JSON, else
kept as text. Prints each request on stderr; Ctrl+C stops it. A port another
socket already holds: exit 2, `Cannot listen on 127.0.0.1:<port>`.

The capture holds what the plugin sends n8n: the post's content and the
site's webhook token (`X-PQM-Token`, `auth_token`). Write it outside the
repository (the session's scratchpad) and delete it when the walk ends.
How a walk uses it: docs/local-verification.md; offline checks:
tests/harness/local-wp-harness.py. Standard library only. Repository tooling:
not in the zip.
"""
from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, HTTPServer


def main() -> int:
    parser = argparse.ArgumentParser(description='Capture webhook POSTs on 127.0.0.1 as JSON lines.')
    parser.add_argument('--port', type=int, required=True)
    parser.add_argument('--out', required=True, help='the JSON lines file to append to')
    args = parser.parse_args()
    open(args.out, 'a', encoding='utf-8').close()  # a bad path fails now, not at the first request

    class Capture(BaseHTTPRequestHandler):
        def do_POST(self) -> None:
            text = self.rfile.read(int(self.headers.get('Content-Length') or 0)).decode('utf-8', 'replace')
            try:
                body = json.loads(text)
            except ValueError:
                body = text
            line = json.dumps({
                'time': datetime.now(timezone.utc).isoformat(timespec='seconds'),
                'path': self.path,
                'headers': dict(self.headers),
                'body': body,
            }, ensure_ascii=False)
            with open(args.out, 'a', encoding='utf-8', newline='\n') as f:
                f.write(line + '\n')
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', '2')
            self.end_headers()
            self.wfile.write(b'{}')

    class Server(HTTPServer):
        allow_reuse_address = False  # a busy port fails here, on Windows too (memory/local-verification.md)

    try:
        server = Server(('127.0.0.1', args.port), Capture)
    except OSError as error:
        print(f'Cannot listen on 127.0.0.1:{args.port}: {error}', file=sys.stderr)
        return 2
    print(f'Capturing POSTs on http://127.0.0.1:{args.port}/ into {args.out} (Ctrl+C stops).', flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print('Stopped.')
    finally:
        server.server_close()
    return 0


if __name__ == '__main__':
    sys.exit(main())
