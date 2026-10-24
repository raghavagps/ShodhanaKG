"""Apply reviewed namespace corrections to historical expectations, never endpoint output.

Only identifier fields change. Row counts, edge identities, properties and entity
expectations stay frozen. Historical fixture files are never overwritten.
"""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
AUDIT = ROOT / 'docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json'

def main():
    audit = json.loads(AUDIT.read_bytes())
    ayush = {r['Reference'] for r in audit['rows'] if r['assessed_type'] == 'AYUSH'}
    def correct(value):
        if isinstance(value, list):
            return [correct(v) for v in value]
        if not isinstance(value, dict):
            return value
        result = {k: correct(v) for k, v in value.items()}
        if str(result.get('pubmed_id', '')) in ayush:
            result['ayush_id'], result['pubmed_id'] = result['pubmed_id'], ''
        if result.get('type') == 'pubmed_id' and str(result.get('value')) in ayush:
            result['type'] = 'ayush_id'
        return result
    for stem in ['expanded_factual_cases', 'factual_evaluation_cases', 'legacy_factual_evaluation_cases']:
        old = ROOT / 'webservice/api/tests/fixtures' / (stem + '_v2.json')
        new = old.with_name(stem + '_v3.json')
        cases = correct(json.loads(old.read_bytes()))
        for case in cases:
            case['namespace_audit'] = {'path': str(AUDIT.relative_to(ROOT)), 'sha256': hashlib.sha256(AUDIT.read_bytes()).hexdigest()}
        with new.open('x', encoding='utf-8') as stream:
            json.dump(cases, stream, indent=2, ensure_ascii=False)

if __name__ == '__main__':
    main()
