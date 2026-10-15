"""Summarize existing F0 evidence without executing tests or changing fixtures."""
import argparse
import hashlib
import json
from pathlib import Path
import xml.etree.ElementTree as ET

parser = argparse.ArgumentParser()
parser.add_argument('--android', type=Path, required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
output = root / 'docs/navigation'
assert output.is_dir(), 'Create the navigation evidence directory first'
reports = sorted((args.android / 'app/build/test-results/testDebugUnitTest').glob('TEST-*.xml'))
assert reports, 'No Android unit reports found'
totals = dict(tests=0, failures=0, errors=0, skipped=0)
for report in reports:
    suite = ET.parse(report).getroot()
    for key in totals:
        totals[key] += int(suite.get(key, '0'))
evidence = {
    str(path.relative_to(output)).replace('\\', '/'): hashlib.sha256(path.read_bytes()).hexdigest()
    for path in sorted((output / 'evidence').rglob('*')) if path.is_file()
}
result = {'android_unit_reports': len(reports), 'android_unit_results': totals,
          'evidence_sha256': evidence}
baseline = json.loads((output / 'baseline.json').read_text(encoding='utf-8'))
matrix = (output / 'MATRIZ_NAV_ACTUAL.md').read_text(encoding='utf-8')
result['inventory_coverage'] = {
    'menu_keys': len(baseline['web_menu_keys']),
    'screens': len(baseline['screens']),
    'missing_menu_keys': [key for key in baseline['web_menu_keys'] if f'| `{key}` |' not in matrix],
    'missing_screens': [screen['screen'] for screen in baseline['screens'] if f"`Screen.{screen['screen']}`" not in matrix],
}
room = output / 'evidence/navigation-qa'
result['room_snapshots'] = {}
for form_factor in ('phone', 'tablet'):
    before, after = [room / f'baseline-room-{form_factor}-{stage}.json' for stage in ('before', 'after')]
    if before.exists() and after.exists():
        old, new = [json.loads(path.read_text(encoding='utf-8')) for path in (before, after)]
        result['room_snapshots'][form_factor] = {
            'schema': new['schema'], 'package': new['package'],
            'changed_table_counts': {name: [old['tables'].get(name), count] for name, count in new['tables'].items()
                                     if old['tables'].get(name) != count},
            'table_counts': new['tables'],
        }
current_routes = next((output / name for name in ('routes-after-f4-credit.json', 'routes-after-f4.json', 'routes-after-f2.json')
                      if (output / name).exists()), output / 'routes-after-f2.json')
if current_routes.exists():
    old = json.loads((output / 'routes-before.json').read_text(encoding='utf-8'))['routes']
    new = json.loads(current_routes.read_text(encoding='utf-8'))['routes']
    identity = lambda route: (route['name'], route['uri'], tuple(route['methods']), route['action'])
    old_ids, new_ids = {identity(route) for route in old}, {identity(route) for route in new}
    result['route_compatibility'] = {'before': len(old), 'after': len(new),
                                   'snapshot': current_routes.name,
                                   'removed_or_changed': sorted(old_ids - new_ids, key=str),
                                   'added': sorted(new_ids - old_ids, key=str)}
(output / 'verification-baseline.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps({'android_unit_reports': len(reports), **totals, 'evidence_files': len(evidence),
                  'inventory_coverage': result['inventory_coverage'], 'room_snapshots': result['room_snapshots']}))
assert not result['inventory_coverage']['missing_menu_keys'], 'Menu keys missing from the reviewed matrix'
assert not result['inventory_coverage']['missing_screens'], 'Screen destinations missing from the reviewed matrix'
if 'route_compatibility' in result:
    print(json.dumps({'route_compatibility': result['route_compatibility']}))
    assert not result['route_compatibility']['removed_or_changed'], 'A frozen route was removed or its contract changed'
