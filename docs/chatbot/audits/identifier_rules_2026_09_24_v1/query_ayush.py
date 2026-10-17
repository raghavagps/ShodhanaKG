"""Read-only public registry queries; exact Art ID matches, not search-hit inference."""
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
import hashlib
import html
import json
from pathlib import Path
import re
import time
import urllib.parse
import urllib.request

ROOT = Path(__file__).parent
BASE = 'https://arp.ayush.gov.in/'


def query(ref):
    record = {'reference': ref, 'checked_at': datetime.now(timezone.utc).isoformat(),
              'endpoint': BASE + 'getFilter_Search_data?=', 'search': ref, 'matches': [], 'requests': []}
    start = 0
    try:
        while True:
            data = {'startPage': str(start), 'pageLength': '100', 'Search': ref,
                    'orderColunm': '0', 'orderType': 'asc', 'arp_id': '0', 'artid': '0',
                    'system_id': '0', 'app_status': '1', 'category': '0', 'keyword_string': '', 'jsonData': '[]'}
            req = urllib.request.Request(record['endpoint'], urllib.parse.urlencode(data).encode(),
                                         {'Content-Type': 'application/x-www-form-urlencoded', 'Referer': BASE + 'research_advance_search'})
            with urllib.request.urlopen(req, timeout=25) as response:
                body = response.read()
                record['requests'].append({'start': start, 'http_status': response.status, 'sha256': hashlib.sha256(body).hexdigest()})
            rows = json.loads(body)
            if not isinstance(rows, list):
                raise ValueError('Unexpected registry response')
            for row in rows:
                match = re.fullmatch(r'(\d+)(?:\s*\([A-Za-z]+\))?', str(row.get('artid', '')).strip())
                if not match or match[1] != ref:
                    continue
                markup = row.get('journal_details', '')
                title = re.search(r'custom-dv-title[^>]*>(.*?)</a>', markup, re.S)
                title = html.unescape(re.sub('<[^>]+>', '', title[1])).strip() if title else None
                record['matches'].append({'artid': row['artid'], 'arp_id': row['arp_id'], 'id': row['id'],
                                          'system': row['system'], 'title': title,
                                          'url': BASE + 'View_Res_Landing_Url?' + urllib.parse.urlencode({'rp6': row['id'], 'rptypeReport': '0', 'rpreportname': '0'})})
            if len(rows) < 100:
                break
            start += 100
            if start >= 10000:
                raise ValueError('Paging limit reached; search incomplete')
            time.sleep(.2)
        record['status'] = 'exact_artid_match' if record['matches'] else 'no_exact_artid_returned'
    except Exception as error:
        record['status'] = 'lookup_error'
        record['error'] = str(error)
    time.sleep(.15)
    return record


if __name__ == '__main__':
    raw = (ROOT / 'stored_references.json').read_bytes()
    refs = sorted(str(r['raw']) for r in json.loads(raw)['references'] if re.fullmatch(r'\d+', str(r['raw'])))
    report = {'snapshot_sha256': hashlib.sha256(raw).hexdigest(), 'scope': 'Public AYUSH registry identity only, not scientific support or automatic KG-source matching', 'results': []}
    with (ROOT / 'ayush_registry.json').open('x', encoding='utf-8') as output:
        with ThreadPoolExecutor(max_workers=2) as executor:
            for future in as_completed([executor.submit(query, ref) for ref in refs]):
                report['results'].append(future.result())
                output.seek(0); json.dump(report, output, indent=2, ensure_ascii=False); output.truncate(); output.flush()
                if len(report['results']) % 25 == 0:
                    print('Checked', len(report['results']), '/', len(refs), flush=True)
        print('Complete', len(report['results']), 'matched', sum(bool(r['matches']) for r in report['results']), flush=True)
