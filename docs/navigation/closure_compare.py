"""Compare verified private QA backups without disclosing row contents or credentials."""
from collections import Counter
import json
from pathlib import Path
import sqlite3

ROOT = Path(__file__).resolve().parents[2]
TEMP = Path('C:/Users/waila/AppData/Local/Temp/opencode')
result = {}
for kind in ('sql', 'room'):
    before = TEMP / f'closure-f0-initial-{kind}.sqlite'
    after = TEMP / f'closure-f0-after-menus-{kind}.sqlite'
    assert before.is_file() and after.is_file()
    print(json.dumps(dict(environment='QA private snapshots', connection='sqlite', host='none', before=str(before), after=str(after))))
    with sqlite3.connect(before.as_uri() + '?mode=ro', uri=True) as old, sqlite3.connect(after.as_uri() + '?mode=ro', uri=True) as new:
        tables = [row[0] for row in old.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")]
        changes = {}
        for table in tables:
            assert table.replace('_', '').isalnum()
            columns = old.execute(f'PRAGMA table_info("{table}")').fetchall()
            names = [row[1] for row in columns]
            keys = [index for index, row in enumerate(columns) if row[5]] or list(range(len(columns)))
            def rows(db):
                return {tuple(row[index] for index in keys): row for row in db.execute(f'SELECT * FROM "{table}"')}
            left, right = rows(old), rows(new)
            changed = Counter()
            for key in left.keys() & right.keys():
                for index, name in enumerate(names):
                    if left[key][index] != right[key][index]:
                        changed[name] += 1
            if changed or left.keys() != right.keys():
                changes[table] = dict(added=len(right.keys() - left.keys()), removed=len(left.keys() - right.keys()), changed_columns=dict(changed))
        result[kind] = changes
destination = ROOT / 'docs/navigation/evidence/closure-f0-data-comparison.json'
assert not destination.exists()
destination.write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result))
