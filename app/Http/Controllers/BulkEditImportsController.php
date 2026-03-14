<?php

namespace App\Http\Controllers;

use App\BulkEditImport;
use App\BulkEditImportRow;
use App\Services\BulkEditImportService;
use App\Services\FamilyScopeResolver;
use Illuminate\Http\Request;

class BulkEditImportsController extends Controller
{
    public function __construct(
        private BulkEditImportService $bulkEditImportService,
        private FamilyScopeResolver $familyScopeResolver
    ) {
        $this->middleware(['auth', 'admin']);
    }

    public function index()
    {
        $imports = BulkEditImport::query()
            ->forTenant($this->familyScopeResolver)
            ->with('uploader')
            ->latest()
            ->paginate(15);

        return view('bulk-edit-imports.index', compact('imports'));
    }

    public function template()
    {
        $path = $this->bulkEditImportService->createTemplateFile();
        $host = $this->familyScopeResolver->currentHost() ?: 'all-hosts';

        return response()->download($path, 'bulk-edit-template-'.$host.'.xlsx')->deleteFileAfterSend(true);
    }

    public function store(Request $request)
    {
        $request->validate([
            'workbook' => 'required|file|mimes:xlsx|max:12000',
        ]);

        $import = $this->bulkEditImportService->createImportFromUpload($request->file('workbook'), auth()->user());

        return redirect()->route('bulk-edit-imports.show', $import)
            ->with('status', 'Workbook berhasil diupload dan diparsing ke staging review.');
    }

    public function show(Request $request, BulkEditImport $bulkEditImport)
    {
        $this->abortIfImportOutsideScope($bulkEditImport);

        $bulkEditImport = $this->bulkEditImportService->refreshImport($bulkEditImport->load(['uploader', 'rows.targetUser', 'rows.reviewer']));
        $rows = $bulkEditImport->rows
            ->when($request->filled('status'), fn ($collection) => $collection->where('status', $request->get('status')))
            ->when($request->filled('sheet'), fn ($collection) => $collection->where('sheet_name', $request->get('sheet')))
            ->values();

        $visibleUserOptions = $this->bulkEditImportService->visibleUserOptions();
        $visibleCoupleOptions = $this->bulkEditImportService->visibleCoupleOptions();

        return view('bulk-edit-imports.show', compact('bulkEditImport', 'rows', 'visibleUserOptions', 'visibleCoupleOptions'));
    }

    public function updateRow(Request $request, BulkEditImport $bulkEditImport, BulkEditImportRow $bulkEditImportRow)
    {
        $this->abortIfRowOutsideScope($bulkEditImport, $bulkEditImportRow);

        $attributes = $request->validate([
            'resolved_target_user_id' => 'nullable|string',
            'resolved_anchor_type' => 'nullable|string',
            'resolved_anchor_ref_id' => 'nullable|string',
            'resolved_relation_action' => 'nullable|string',
        ]);

        $this->bulkEditImportService->updateRowResolution($bulkEditImportRow, $attributes);

        return redirect()->route('bulk-edit-imports.show', $bulkEditImport)
            ->with('status', 'Resolusi row berhasil diperbarui.');
    }

    public function approveRow(Request $request, BulkEditImport $bulkEditImport, BulkEditImportRow $bulkEditImportRow)
    {
        $this->abortIfRowOutsideScope($bulkEditImport, $bulkEditImportRow);
        $validated = $request->validate(['review_notes' => 'nullable|string|max:2000']);

        try {
            $this->bulkEditImportService->approveRow($bulkEditImportRow, auth()->user(), $validated['review_notes'] ?? null);
        } catch (\RuntimeException $exception) {
            return redirect()->route('bulk-edit-imports.show', $bulkEditImport)
                ->withErrors(['bulk_edit_import' => $exception->getMessage()]);
        }

        return redirect()->route('bulk-edit-imports.show', $bulkEditImport)
            ->with('status', 'Row berhasil di-approve.');
    }

    public function rejectRow(Request $request, BulkEditImport $bulkEditImport, BulkEditImportRow $bulkEditImportRow)
    {
        $this->abortIfRowOutsideScope($bulkEditImport, $bulkEditImportRow);
        $validated = $request->validate(['review_notes' => 'nullable|string|max:2000']);

        $this->bulkEditImportService->rejectRow($bulkEditImportRow, auth()->user(), $validated['review_notes'] ?? null);

        return redirect()->route('bulk-edit-imports.show', $bulkEditImport)
            ->with('status', 'Row berhasil ditolak.');
    }

    public function approveReady(BulkEditImport $bulkEditImport)
    {
        $this->abortIfImportOutsideScope($bulkEditImport);

        try {
            $approved = $this->bulkEditImportService->approveReadyRows($bulkEditImport, auth()->user());
        } catch (\RuntimeException $exception) {
            return redirect()->route('bulk-edit-imports.show', $bulkEditImport)
                ->withErrors(['bulk_edit_import' => $exception->getMessage()]);
        }

        return redirect()->route('bulk-edit-imports.show', $bulkEditImport)
            ->with('status', $approved.' row siap berhasil di-approve.');
    }

    private function abortIfImportOutsideScope(BulkEditImport $bulkEditImport): void
    {
        if (! $this->familyScopeResolver->hasActiveScope()) {
            return;
        }

        if ($bulkEditImport->tenant_host !== $this->familyScopeResolver->currentHost()) {
            abort(404);
        }
    }

    private function abortIfRowOutsideScope(BulkEditImport $bulkEditImport, BulkEditImportRow $bulkEditImportRow): void
    {
        $this->abortIfImportOutsideScope($bulkEditImport);

        if ($bulkEditImportRow->bulk_edit_import_id !== $bulkEditImport->id) {
            abort(404);
        }
    }
}
