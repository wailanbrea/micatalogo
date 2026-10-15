// QA-only browser baseline. Password is supplied at runtime and never saved.
async (page) => {
  const password = page.__closureQAPassword;
  if (!password) throw new Error('Supply the synthetic QA password at runtime');
  const base = 'http://127.0.0.1:18768';
  const output = 'C:/xampp/php/www/MiCatalogo/docs/navigation/evidence';
  const identity = await (await page.request.get(base + '/__qa/navigation/identity')).json();
  if (identity.environment !== 'testing' || identity.driver !== 'sqlite' || identity.database !== 'C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-navigation-20261014.sqlite') throw new Error('Unsafe QA identity');
  const roles = { owner: 'owner', manager: 'manager', physical: 'sales', services: 'services', accountant: 'accountant', none: 'reports' };
  const results = [];
  for (const [role, account] of Object.entries(roles)) {
    await page.goto(`${base}/__qa/navigation/login/${account}`);
    const login = await page.request.post(base + '/api/v1/auth/login', { data: { email: `qa-nav-${account}@example.test`, password, device_name: 'QA_CLOSURE_BROWSER' }, headers: { Accept: 'application/json', 'X-MiCatalogo-Version-Code': '114' } });
    if (!login.ok()) throw new Error(`QA login ${role}: ${login.status()}`);
    const token = (await login.json()).access_token;
    const headers = { Authorization: 'Bearer ' + token, Accept: 'application/json', 'X-MiCatalogo-Version-Code': '114' };
    try {
      const response = await page.request.get(base + '/api/v1/shops', { headers });
      if (!response.ok()) throw new Error('QA shops unavailable');
      const shop = (await response.json()).find(item => item.name === 'QA_NAV_Perfumes');
      const summary = shop.navigation.sections.flatMap(section => section.items).find(item => item.id === 'summary').url;
      if (!summary.startsWith(base + '/')) throw new Error('Unexpected navigation host');
      const formats = {};
      for (const [format, viewport] of Object.entries({ desktop: { width: 1440, height: 1080 }, mobile: { width: 390, height: 844 } })) {
        await page.setViewportSize(viewport);
        const pageResponse = await page.goto(summary);
        if (pageResponse.status() !== 200) throw new Error(`QA summary ${role} failed`);
        if (format === 'mobile') await page.getByRole('button', { name: 'Menú', exact: true }).click();
        const tree = await page.locator(`[data-navigation-tree="${format}"]`).evaluate(element => ({
          sections: [...element.querySelectorAll('[data-navigation-section]')].map(node => node.dataset.navigationSection),
          links: [...element.querySelectorAll('[data-navigation-id]')].map(node => ({ id: node.dataset.navigationId, label: node.textContent.trim(), url: node.href, active: node.getAttribute('aria-current') === 'page' }))
        }));
        const screenshot = `${output}/closure-f0-web-${format}-${role}.png`;
        await page.screenshot({ path: screenshot, fullPage: true });
        formats[format] = tree;
      }
      if (JSON.stringify(formats.desktop.links) !== JSON.stringify(formats.mobile.links)) throw new Error(`Navigation mismatch ${role}`);
      const nativeOnly = new Set(['returns', 'settings', 'printers', 'routes', 'route_loads']);
      const apiIds = [...shop.navigation.sections.flatMap(section => section.items), ...shop.navigation.secondary].filter(item => item.url && !nativeOnly.has(item.id)).map(item => item.id);
      results.push({ role, synthetic_account: account, permissions: shop.menu_permissions, api_navigation: shop.navigation, formats,
        comparison: { desktop_mobile_equal: true, api_ids: apiIds, web_ids: formats.desktop.links.map(item => item.id),
          web_only: formats.desktop.links.map(item => item.id).filter(id => !apiIds.includes(id)) } });
    } finally {
      await page.request.post(base + '/api/v1/auth/logout', { headers });
    }
  }
  await page.evaluate(report => { window.__closureF0Report = report; }, { identity, results });
  delete page.__closureQAPassword;
  return results.map(result => ({ role: result.role, permissions: result.permissions, links: result.formats.desktop.links.length, comparison: result.comparison }));
}
