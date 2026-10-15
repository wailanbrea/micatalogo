"""Validate and index the new F0 evidence; never rerun tests or approve NAV journeys."""
from datetime import datetime, timezone
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
ANDROID = Path('C:/Users/waila/AndroidStudioProjects/micatalogowebApp')
EVIDENCE = ROOT / 'docs/navigation/evidence'
parser = argparse.ArgumentParser()
parser.add_argument('--canonical', action='store_true')
args = parser.parse_args()
def evidence_hash(path):
    data = path.read_bytes()
    if args.canonical and path.suffix.lower() in {'.json', '.txt', '.md', '.xml'}:
        data = data.replace(b'\r\n', b'\n')
    return hashlib.sha256(data).hexdigest()
backend = ET.parse(EVIDENCE / 'closure-f0-backend.xml').getroot()
suites = backend.findall('testsuite')
assert suites and sum(int(suite.get('tests', '0')) for suite in suites) == 666
assert all(int(suite.get(key, '0')) == 0 for suite in suites for key in ('failures', 'errors', 'skipped'))
unit = dict(tests=0, failures=0, errors=0, skipped=0)
reports = sorted((ANDROID / 'app/build/test-results/testDebugUnitTest').glob('TEST-*.xml'))
for path in reports:
    suite = ET.parse(path).getroot()
    for key in unit:
        unit[key] += int(suite.get(key, '0'))
assert unit == dict(tests=147, failures=0, errors=0, skipped=0)
web = json.loads((EVIDENCE / 'closure-f0-web-navigation.json').read_text(encoding='utf-8'))
assert len(web['results']) == 6
for result in web['results']:
    assert result['comparison']['desktop_mobile_equal']
    assert set(result['comparison']['api_ids']) <= set(result['comparison']['web_ids'])
    assert set(result['comparison']['web_only']) <= {'shop_selector'}
android_results = {}
for factor in ('phone', 'tablet'):
    result = json.loads((EVIDENCE / f'closure-f0-android-{factor}/qa-results.json').read_text(encoding='utf-8'))
    assert result['gate'] == 'PASS' and result['declared'] == result['executed'] == 1
    assert result['counts'] == dict(PASS=1, FAIL=0, SKIP=0, ASSUMPTION=0)
    android_results[factor] = result
comparison = json.loads((EVIDENCE / 'closure-f0-data-comparison.json').read_text(encoding='utf-8'))
assert set(comparison['sql']) <= {'personal_access_tokens', 'shop_sellers', 'sqlite_sequence', 'users'}
assert comparison['sql'].get('shop_sellers', {}).get('changed_columns', {}) == {'updated_at': 2}
assert not set(comparison['room']) & {'operation_outbox', 'payment_sync_outbox', 'pos_sale_outbox', 'inventory_stock', 'inventory_movements', 'sales', 'payments'}
assert set(comparison['room']) <= {'products', 'units_of_measure'}
metadata = json.loads((ANDROID / 'app/build/outputs/apk/debug/output-metadata.json').read_text(encoding='utf-8'))
result = dict(captured_at=datetime.now(timezone.utc).isoformat(),
    backend_head=subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
    android_head=subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ANDROID, text=True).strip(),
    backend=dict(tests=666, assertions=7614, failures=0, errors=0),
    android_unit=unit, android_unit_reports=len(reports), android_instrumented=android_results,
    android_artifact=metadata, data_comparison=comparison,
    evidence_text_hash_normalization='LF; binary bytes unchanged' if args.canonical else 'raw working-tree bytes at capture',
    evidence_sha256={str(path.relative_to(EVIDENCE)).replace('\\', '/'): evidence_hash(path)
        for path in sorted(EVIDENCE.rglob('*')) if path.is_file() and path.relative_to(EVIDENCE).parts[0].startswith('closure-')},
    limits=['F0 baseline only; no NAV/E2E gate approved', 'Room cache status changes recorded as a finding, not hidden',
            'Historical SENT outboxes preserved byte-for-byte', 'Debug 1.0.112 APK retained privately for compatibility'])
destination = EVIDENCE / ('closure-f0-index-canonical.json' if args.canonical else 'closure-f0-index.json')
assert not destination.exists(), 'Preserve frozen index'
destination.write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps({key: value for key, value in result.items() if key in ('backend_head', 'android_head', 'backend', 'android_unit', 'data_comparison')}))
