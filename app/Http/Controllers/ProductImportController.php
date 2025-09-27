<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImport;
use App\Jobs\ProcessProductImport;
use App\Models\Image;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use League\Csv\Reader;

class ProductImportController extends Controller
{
    public function showForm() {
        return view('import');
    }

    public function import(Request $request) {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('csv_file')->store('uploads');

        $import = ProductImport::create([
            'file_path' => $path,
            'status' => 'pending',
        ]);

        // Respond immediately even if queue connection is 'sync'
        \App\Jobs\ProcessProductImport::dispatchAfterResponse($import->id);

        return redirect()->route('import.show', ['import' => $import->id])
            ->with('status', 'Import queued. This page will show progress.');
    }

    public function show(ProductImport $import) {
        $rows = $import->rows()->latest()->limit(50)->get();
        return view('import_show', compact('import','rows'));
    }
}