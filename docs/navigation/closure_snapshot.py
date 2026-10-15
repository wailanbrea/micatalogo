"""Read-only QA SQL/Room snapshots and verified private backups. No restore or cleanup."""
import argparse
import hashlib
import json
from pathlib import Path
import sqlite3
import subprocess
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
TEMP = Path('C:/Users/waila/AppData/Local/Temp/opencode')
SQL = TEMP / 'micatalogo-navigation-20261014.sqlite'
ADB = 'C:/Users/waila/AppData/Local/Android/Sdk/platform-tools/adb.exe'
PACKAGE = 'com.bsolutions.micatalogo.offlinecheck.syncqa'
parser = argparse.ArgumentParser()
parser.add_argument('--stage', required=True)
parser.add_argument('--room', action='store_true')
args = parser.parse_args()
assert args.stage.replace('-', '').isalnum()
assert TEMP.is_dir() and SQL.is_file()
identity = json.load(urllib.request.urlopen('http://127.0.0.1:18768/__qa/navigation/identity'))
assert identity == dict(environment='testing', driver='sqlite', host='none (local SQLite)', database=SQL.as_posix())
print(json.dumps(identity))

def digest(db):
    result = {}
    for (table,) in db.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"):
        assert table.replace('_', '').isalnum()
        rows = db.execute(f'SELECT * FROM "{table}"').fetchall()
        encoded = sorted(json.dumps(row, separators=(',', ':'), default=lambda value: {'blob_hex': value.hex()}) for row in rows)
        result[table] = dict(count=len(rows), sha256=hashlib.sha256('\n'.join(encoded).encode()).hexdigest())
    return dict(schema=db.execute('PRAGMA user_version').fetchone()[0], tables=result,
                foreign_key_violations=len(db.execute('PRAGMA foreign_key_check').fetchall()))

def backup(source, destination):
    assert not destination.exists(), 'Preserve existing evidence/backup'
    with sqlite3.connect(source.as_uri() + '?mode=ro', uri=True) as old, sqlite3.connect(destination) as new:
        assert old.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
        old.backup(new)
        assert new.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
        before, after = digest(old), digest(new)
        assert before == after, 'Backup differs from source'
    return dict(snapshot=before, private_backup=str(destination), backup_sha256=hashlib.sha256(destination.read_bytes()).hexdigest())

with sqlite3.connect(SQL.as_uri() + '?mode=ro', uri=True) as db:
    assert db.execute("SELECT COUNT(*) FROM shops WHERE slug NOT LIKE 'qa-nav-%'").fetchone()[0] == 0, 'Not the synthetic navigation fixture'
result = dict(identity=identity, sql=backup(SQL, TEMP / f'closure-{args.stage}-sql.sqlite'))

if args.room:
    def adb(*arguments, required=True):
        command = [ADB, '-s', 'emulator-5554', *arguments]
        output = subprocess.run(command, capture_output=True)
        if required and output.returncode:
            raise RuntimeError('QA adb operation failed: ' + ' '.join(arguments[:3]))
        return output
    assert adb('shell', 'getprop', 'ro.kernel.qemu').stdout.strip() == b'1'
    installed = adb('shell', 'dumpsys', 'package', PACKAGE).stdout.decode(errors='replace')
    assert 'DEBUGGABLE' in installed and 'E266FD48B028DC66DC4E530C0BC8AF06B9BE820545600C6C4B19C81B4B1FF775' in installed
    # Stop only the isolated debug process to take a stable DB + WAL copy.
    adb('shell', 'am', 'force-stop', PACKAGE)
    room = TEMP / f'closure-{args.stage}-room-raw.sqlite'
    for suffix in ('', '-wal', '-shm'):
        target = Path(str(room) + suffix)
        assert not target.exists()
        path = 'databases/micatalogo.db' + suffix
        exists = adb('shell', 'run-as', PACKAGE, 'test', '-f', path, required=False).returncode == 0
        if exists:
            target.write_bytes(adb('exec-out', 'run-as', PACKAGE, 'cat', path).stdout)
        else:
            assert suffix, 'Room QA database does not exist'
    result['room'] = backup(room, TEMP / f'closure-{args.stage}-room.sqlite')
    result['room']['package'] = PACKAGE
    apk = TEMP / f'closure-{args.stage}-legacy-debug.apk'
    assert not apk.exists()
    remote_apk = adb('shell', 'pm', 'path', PACKAGE).stdout.decode().strip().removeprefix('package:')
    assert remote_apk.startswith('/data/app/') and remote_apk.endswith('/base.apk')
    adb('pull', remote_apk, str(apk))
    result['room']['private_apk_backup'] = str(apk)
    result['room']['apk_sha256'] = hashlib.sha256(apk.read_bytes()).hexdigest()

destination = ROOT / 'docs/navigation/evidence' / f'closure-{args.stage}-data.json'
assert destination.parent.is_dir() and not destination.exists()
destination.write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps({key: dict(schema=value['snapshot']['schema'],
    nonempty_tables={table: row['count'] for table, row in value['snapshot']['tables'].items() if row['count']},
    foreign_key_violations=value['snapshot']['foreign_key_violations'], backup_sha256=value['backup_sha256'])
    for key, value in result.items() if key != 'identity'}))
