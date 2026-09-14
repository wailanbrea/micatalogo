<?php

namespace App\Http\Controllers;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Report;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminModerationController extends Controller
{
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status', 'open');

        $query = Report::query()
            ->with(['reportable', 'resolvedBy'])
            ->latest('id');

        if ($currentStatus !== 'all') {
            $query->where('status', $currentStatus);
        }

        $reports = $query->paginate(15)->withQueryString();

        return view('admin.reports.index', compact('reports', 'currentStatus'));
    }

    public function show(Report $report): View
    {
        $report->loadMissing(['reportable', 'resolvedBy']);

        return view('admin.reports.show', compact('report'));
    }

    public function resolve(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'suspend_target'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['action'] === 'suspend_target' && $report->reportable) {
            if ($report->reportable instanceof Shop) {
                $report->reportable->update(['status' => 'suspended']);
            } elseif ($report->reportable instanceof Product) {
                $report->reportable->update(['moderation_status' => ProductModerationStatus::Suspended]);
            }
        }

        $report->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        $msg = $validated['action'] === 'suspend_target'
            ? 'Reporte resuelto y entidad reportada suspendida.'
            : 'Reporte desestimado y archivado como resuelto.';

        return redirect()->route('admin.reports.index')->with('status', $msg);
    }

    public function toggleShopStatus(Shop $shop): RedirectResponse
    {
        $newStatus = $shop->status === 'active' ? 'suspended' : 'active';
        $shop->update(['status' => $newStatus]);

        $label = $newStatus === 'active' ? 'activada' : 'suspendida';

        return back()->with('status', "La tienda {$shop->name} ha sido {$label}.");
    }

    public function toggleProductStatus(Product $product): RedirectResponse
    {
        $newStatus = $product->moderation_status === ProductModerationStatus::Active
            ? ProductModerationStatus::Suspended
            : ProductModerationStatus::Active;

        $product->update(['moderation_status' => $newStatus]);

        $label = $newStatus === ProductModerationStatus::Active ? 'activado' : 'suspendido';

        return back()->with('status', "El producto {$product->name} ha sido {$label}.");
    }
}
