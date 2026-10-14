@props(['rows', 'label' => 'Registro'])
<div class="customer-table-wrapper">
    <table class="customer-table">
        <thead><tr><th scope="col">{{ $label }}</th><th scope="col" class="customer-extra-column">Datos</th><th scope="col" class="customer-money-column">Total</th><th scope="col">Estado</th></tr></thead>
        @forelse ($rows as $row)
            <tbody x-data="{ open: false }">
                <tr class="customer-table-row" @click="open = !open" :class="open ? 'is-open' : ''">
                    <th scope="row"><button type="button" class="customer-row-toggle" @click.stop="open = !open" :aria-expanded="open"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :style="open ? 'transform:rotate(90deg)' : ''" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg><span>{{ $row['primary'] }}</span></button></th>
                    <td class="customer-extra-column">{{ $row['secondary'] ?? '—' }}</td>
                    <td class="customer-money-column">{{ $row['value'] ?? '—' }}</td>
                    <td><span class="customer-table-status">{{ $row['status'] ?? '—' }}</span></td>
                </tr>
                <tr x-show="open" x-cloak><td colspan="4" class="customer-detail-cell"><h3 class="font-bold">{{ $row['primary'] }}</h3><p class="mt-2 text-sm text-slate-600">{{ $row['secondary'] ?? 'Sin información adicional' }}</p>
                    @if (!empty($row['details']))<dl class="customer-detail-data">@foreach ($row['details'] as $key => $value)<div><dt>{{ $key }}</dt><dd>{{ $value ?: '—' }}</dd></div>@endforeach</dl>@endif
                </td></tr>
            </tbody>
        @empty
            <tbody><tr><td colspan="4" class="p-6 text-center text-slate-500">No hay registros para mostrar todavía.</td></tr></tbody>
        @endforelse
    </table>
</div>
