"""Export every frozen route boundary and candidate test references; no DB access."""
import argparse
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
output = root / 'docs/navigation'
parser = argparse.ArgumentParser()
parser.add_argument('--routes', type=Path, default=output / 'routes-before.json')
parser.add_argument('--output', type=Path, default=output / 'OPERATION_ACCESS_MATRIX.md')
parser.add_argument('--phase', default='F0')
args = parser.parse_args()
routes = json.loads(args.routes.read_text(encoding='utf-8'))['routes']
tests = {path.name: path.read_text(encoding='utf-8-sig') for path in (root / 'tests').rglob('*.php')}
def escape(value):
    return str(value).replace('|', '\\|').replace('\n', ' ').replace('\r', '')
lines = [f'# {args.phase} — límites por ruta y operación', '',
         f'Exportación read-only de {len(routes)} rutas desde `{args.routes.name}`. Los textos son evidencia estática, no una aprobación de seguridad.',
         'Las referencias de test se detectan por nombre de ruta explícito; no prueban que todas sus acciones estén cubiertas.',
         'RouteActorTenantMatrixTest y RouteProtectionInvariantTest cubren límites de actor/tenant por reflexión; la matriz por permiso/tab continúa en F4.', '',
         '| Método | Ruta | Acción / fuente | Menú/policy middleware | Guard en controlador | Tests con referencia explícita |',
         '|---|---|---|---|---|---|']
suspects = []
for route in routes:
    name = route['name'] or ''
    boundaries = [value for value in route['middleware'] if value.startswith(('menu:', 'can:', 'catalog.operation', 'abilities', 'auth', 'admin'))]
    guards = route['guard_evidence']
    references = [name_ for name_, text in tests.items() if name and (f"'{name}'" in text or f'"{name}"' in text)]
    source = f"{route['source']}:{route['line']}" if route['source'] else route['action']
    lines.append('| ' + ' | '.join(escape(value) for value in (
        '/'.join(route['methods']), name or route['uri'], source,
        '; '.join(boundaries) or 'Sin boundary específico extraído',
        '; '.join(guards) or 'Sin guard extraído del método; revisar delegación/policy',
        ', '.join(references) or 'Revisión por matriz/reflexión; referencia literal no encontrada')) + ' |')
    if route['writes'] and '{shop}' in route['uri'] and not any(value.startswith('menu:') for value in boundaries):
        suspects.append({'name': name, 'uri': route['uri'], 'action': route['action'], 'guards': guards})
lines += ['', '## Mutaciones de tienda sin middleware menu explícito', '',
          'Revisar autorización dentro de controlador/servicio. No todas son fallos: mobile-operations despacha por tipo; el editor owner-only tiene policy adicional.', '']
for route in suspects:
    lines.append(f"- `{route['name'] or route['uri']}` — `{route['action']}`")
args.output.write_text('\n'.join(lines) + '\n', encoding='utf-8')
print(json.dumps({'routes_documented': len(routes), 'shop_mutations_without_menu_middleware': len(suspects)}))
