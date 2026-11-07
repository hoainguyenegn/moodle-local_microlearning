"""Local-only preview of the speech engine, independent of a configured Moodle site."""
import json
import os
from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.request import Request, urlopen
from urllib.error import HTTPError

ROOT = Path(__file__).resolve().parent
for line in (ROOT / '.env').read_text().splitlines():
    if '=' in line and not line.startswith('#'):
        key, value = line.split('=', 1)
        os.environ.setdefault(key, value)
TOKEN = os.environ['SPEECH_TOKEN']
UPSTREAM = 'http://127.0.0.1:8010'


class Handler(BaseHTTPRequestHandler):
    def reply(self, status, body, mime='application/json'):
        self.send_response(status)
        self.send_header('Content-Type', mime)
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        self.handle_request()

    def do_POST(self):
        self.handle_request()

    def handle_request(self):
        if self.headers.get('Host') not in ('127.0.0.1:8011', 'localhost:8011'):
            return self.reply(403, b'{}')
        if self.command == 'POST' and self.headers.get('Origin') not in ('http://127.0.0.1:8011', 'http://localhost:8011'):
            return self.reply(403, b'{}')
        if self.path == '/' and self.command == 'GET':
            return self.reply(200, (ROOT / 'demo.html').read_bytes(), 'text/html; charset=utf-8')
        import re
        if not re.fullmatch(r'/v1/(health|voices|jobs|jobs/[a-f0-9]{32}(/audio)?)', self.path):
            return self.reply(404, b'{}')
        length = int(self.headers.get('Content-Length', 0))
        if length > 8 * 1024 * 1024:
            return self.reply(413, b'{}')
        data = self.rfile.read(length) if self.command == 'POST' else None
        try:
            req = Request(UPSTREAM + self.path, data=data, method=self.command,
                          headers={'Authorization': 'Bearer ' + TOKEN, 'Content-Type': 'application/json'})
            with urlopen(req, timeout=120) as response:
                self.reply(response.status, response.read(), response.headers.get('Content-Type', 'application/json'))
        except HTTPError as error:
            self.reply(error.code, error.read())
        except Exception:
            self.reply(503, json.dumps({'detail': 'Dịch vụ tạo giọng chưa sẵn sàng. Đang chờ cài đặt hoặc tải mô hình.'}).encode())


if __name__ == '__main__':
    print('Preview: http://127.0.0.1:8011', flush=True)
    ThreadingHTTPServer(('127.0.0.1', 8011), Handler).serve_forever()
