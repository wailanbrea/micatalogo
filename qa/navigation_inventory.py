"""Read-only source inventory for F0, excluding credentials, dependencies and build artifacts."""
import argparse
import csv
import hashlib
import json
import re
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--android', type=Path, required=True)
parser.add_argument('--routes', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
web = Path(__file__).resolve().parent.parent
args.output.mkdir(parents=True, exist_ok=True)
export = json.loads(args.routes.read_text(encoding='utf-8'))
rows = []
columns = ['platform', 'source', 'line', 'label_or_expression', 'route_screen', 'permission_evidence', 'capability_evidence',
           'plan_evidence', 'tenant_evidence', 'operation', 'classification', 'new_parent_child', 'risk', 'tests']
parents = {
    'sales':'sales','quotes':'sales','returns':'sales','cash':'sales','day_close':'sales',
    'orders':'orders','encargos':'orders','shipments':'orders',
    'products':'catalog','services':'catalog','photos':'catalog','attributes':'catalog','pricing':'catalog','price_health':'catalog','decants':'catalog',
    'inventory':'inventory','inventory_adjustments':'inventory','import':'inventory',
    'suppliers':'purchases','purchase_invoices':'purchases','loads':'purchases','containers':'purchases',
    'customers':'customers','credit':'customers','collections':'customers','finance':'finance','expenses':'finance','partners':'finance',
    'reports':'reports','metrics':'storefront','storefront':'storefront','public_catalog':'storefront',
    'routes':'distribution','settings':'settings','shop_settings':'settings','sellers':'settings','commissions':'settings',
    'authorizations':'settings','accountant':'settings','account':'account','help':'help','support':'help','updates':'help','practice':'help','more':'ui-container'
}
def add(platform, source, line, label, destination, operation, evidence='', classification='contextual'):
    found = sorted(set(re.findall(r"(?:menu:|canSeeMenu\(['\"]|required_menu_key[^\n]*?['\"])([a-z_]+)", evidence)))
    rows.append(dict(zip(columns, [platform, str(source).replace('\\','/'), line, label, destination,
        evidence, 'Inherited BusinessProfileService / effective capabilities', 'Inherited PlanLimitsService',
        'Shop route / ShopScope; account-only exceptions require review', operation, classification,
        ','.join(sorted({parents.get(k,'REVIEW') for k in found})) or 'REVIEW',
        'REVIEW concrete controller/policy; source tokens are not a security proof', 'REVIEW linked tests'])))

for route in export['routes']:
    add('api' if route['uri'].startswith('api/') else 'web', route['source'] or route['action'], route['line'] or 0,
        route['name'] or route['uri'], route['uri'], '/'.join(route['methods']),
        ' | '.join(route['middleware'] + route['guard_evidence']), 'action' if route['writes'] else 'destination')

patterns = {
    'blade route': r"(?:route|to_route)\(\s*['\"]([^'\"]+)['\"]",
    'blade URL': r'(?:href|action)="([^"\n]+)"',
    'web CTA': r'<(?:button|a|form)\b[^>]*>',
    'android route/alias': r'(?:composable|navigate)\([^\n]+',
    'android Screen reference': r'Screen\.([A-Za-z]+)(?:\.route|\.routeFor)?',
    'android CTA/tab': r'\b(?:Button|TextButton|IconButton|OutlinedButton|BSPOS\w*Button|Tab|FilterChip|NavigationBarItem|NavigationDrawerItem)\s*\('
}
files = list((web/'resources/views').rglob('*.blade.php')) + list((web/'routes').glob('*.php'))
files += list((args.android/'app/src/main/java').rglob('*.kt'))
for file in files:
    text = file.read_text(encoding='utf-8-sig')
    android = file.suffix == '.kt'
    source = file.relative_to(args.android if android else web)
    for kind, pattern in patterns.items():
        if android != kind.startswith('android'): continue
        for match in re.finditer(pattern, text):
            line = text.count('\n', 0, match.start()) + 1
            context = text[max(0, match.start()-500): min(len(text), match.end()+700)]
            guards = re.findall(r"[^\n]*(?:canSeeMenu|canAccessMiCatalogoMenu|RestrictedMenuDestination|FeatureDestination|@can|canManage|visibleForUser|menu_permissions|enabled_menu_keys|isAdmin|ownsShop)[^\n]*", context)
            add('android' if android else 'web', source, line, match.group(0)[:240],
                match.group(1) if match.lastindex else match.group(0)[:160], kind, ' | '.join(guards)[:1200])

navigation = (args.android/'app/src/main/java/com/example/bspos/presentation/navigation/BSPOSNavigation.kt').read_text(encoding='utf-8')
screens = []
for match in re.finditer(r'data object (\w+)\s*:\s*Screen\((.*?)(?=\n\s*data object|\n\s*\}|\n\s*//)', navigation, re.S):
    strings = re.findall(r'"([^"\n]*)"', match.group(2))
    screens.append({'screen': match.group(1), 'route': strings[0] if strings else '', 'title': strings[1] if len(strings)>1 else '', 'line': navigation.count('\n',0,match.start())+1})
menus = (args.android/'app/src/main/java/com/example/bspos/domain/model/MiCatalogoCatalog.kt').read_text(encoding='utf-8')
android_keys = dict(re.findall(r'SellerMenuOption\("([a-z_]+)",\s*"([^"]+)"\)', menus))
summary = {'web_head': subprocess.check_output(['git','rev-parse','HEAD'], cwd=web,text=True).strip(),
    'android_head': subprocess.check_output(['git','rev-parse','HEAD'],cwd=args.android,text=True).strip(),
    'routes':len(export['routes']), 'rows':len(rows), 'source_files_scanned':len(files), 'screens':screens,
    'web_menu_keys':export['menu_keys'], 'android_menu_keys':android_keys,
    'web_only_keys':sorted(set(export['menu_keys'])-set(android_keys)), 'android_only_keys':sorted(set(android_keys)-set(export['menu_keys'])),
    'source_sha256': {str(f.relative_to(args.android if f.suffix=='.kt' else web)).replace('\\','/'):hashlib.sha256(f.read_bytes()).hexdigest() for f in files}}
with (args.output/'MENU_CURRENT_INVENTORY.csv').open('w',encoding='utf-8-sig',newline='') as out:
    writer=csv.DictWriter(out, fieldnames=columns);writer.writeheader();writer.writerows(rows)
(args.output/'baseline.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
(args.output/'routes-before.json').write_text(json.dumps(export,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({k:summary[k] for k in ['routes','rows','source_files_scanned','web_only_keys','android_only_keys']},ensure_ascii=False))
