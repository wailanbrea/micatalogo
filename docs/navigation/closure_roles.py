"""Temporarily map two synthetic QA actors for the six-role baseline, then restore."""
import argparse
import json
import os
from pathlib import Path
import urllib.request

BASE = 'http://127.0.0.1:18768'
TEMP = Path('C:/Users/waila/AppData/Local/Temp/opencode')
SAVED = TEMP / 'closure-f0-role-permissions.json'
args = argparse.ArgumentParser()
args.add_argument('action', choices=['prepare', 'restore'])
action = args.parse_args().action
identity = json.load(urllib.request.urlopen(BASE + '/__qa/navigation/identity'))
assert identity['environment'] == 'testing' and identity['driver'] == 'sqlite'
assert identity['database'] == str(TEMP / 'micatalogo-navigation-20261014.sqlite').replace('\\', '/')
print(json.dumps(identity))

def request(path, method='GET', payload=None, token=None):
    headers = {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-MiCatalogo-Version-Code': '114'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    body = json.dumps(payload).encode() if payload is not None else None
    with urllib.request.urlopen(urllib.request.Request(BASE + path, data=body, headers=headers, method=method)) as response:
        content = response.read()
        return json.loads(content) if content else None

token = request('/api/v1/auth/login', 'POST', dict(email='qa-nav-owner@example.test',
    password=os.environ['CLOSURE_QA_PASSWORD'], device_name='QA_CLOSURE_BASELINE'))['access_token']
try:
    shop = next(item for item in request('/api/v1/shops', token=token) if item['name'] == 'QA_NAV_Perfumes')
    if action == 'prepare':
        assert TEMP.is_dir() and not SAVED.exists(), 'Preserve an existing role backup'
        targets = [item for item in shop['sellers'] if item['email'] in ['qa-nav-sales@example.test', 'qa-nav-reports@example.test']]
        assert len(targets) == 2
        SAVED.write_text(json.dumps(dict(shop=shop['id'], targets=targets)), encoding='utf-8')
        for target in targets:
            permissions = ['products', 'account', 'help'] if target['email'] == 'qa-nav-sales@example.test' else ['account', 'help']
            request(f"/api/v1/shops/{shop['id']}/sellers/{target['id']}/menus", 'PUT', dict(menu_permissions=permissions), token)
    else:
        saved = json.loads(SAVED.read_text(encoding='utf-8'))
        assert saved['shop'] == shop['id']
        for target in saved['targets']:
            assert target['email'] in ['qa-nav-sales@example.test', 'qa-nav-reports@example.test']
            request(f"/api/v1/shops/{shop['id']}/sellers/{target['id']}/menus", 'PUT', dict(menu_permissions=target['menu_permissions']), token)
    actual = next(item for item in request('/api/v1/shops', token=token) if item['id'] == shop['id'])
    print(json.dumps(dict(action=action, shop=shop['id'], summary=actual['navigation']['sections'][0]['items'][0]['url'],
        roles={item['email']: item['menu_permissions'] for item in actual['sellers']
               if item['email'] in ['qa-nav-sales@example.test', 'qa-nav-reports@example.test']})))
finally:
    request('/api/v1/auth/logout', 'POST', {}, token)
