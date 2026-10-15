<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Support\InventorySpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventorySpreadsheetController extends Controller
{
    public function export(Request $request, InventorySpreadsheet $spreadsheet): StreamedResponse
    {
        Gate::authorize('viewAny', InventoryItem::class);
        $format = $request->query('format', 'csv');
        abort_unless(is_string($format), 404);

        return $spreadsheet->export($format, $request->boolean('template'));
    }

    public function import(Request $request, InventorySpreadsheet $spreadsheet): RedirectResponse
    {
        Gate::authorize('create', InventoryItem::class);
        $file = $request->validate(['file' => ['required', 'file', 'max:2048', 'extensions:csv,xlsx']])['file'];
        try {
            $count = $spreadsheet->import($request->user(), $spreadsheet->read($file));
        } catch (ValidationException $exception) {
            return redirect()->route('inventory.index')
                ->withErrors($exception->errors())
                ->with('inventory_import_errors', $exception->errors()['file'] ?? []);
        }

        return redirect()->route('inventory.index')->with('inventoryStatus', $count.' produk berhasil diimpor. Stok awal tercatat dalam mutasi.');
    }
}
