@extends('layouts.app')
@section('content')
<div class="container mt-4">
    <h2>Bulk Product CSV Import</h2>
    <div class="alert alert-info">CSV columns: sku, name, price (optional), image (optional filename)</div>
    @if(session('status'))
      @php($s = session('status'))
      <div class="alert alert-success">
        Total: {{ $s['total'] ?? 0 }}, Imported: {{ $s['imported'] ?? 0 }}, Updated: {{ $s['updated'] ?? 0 }}, Invalid: {{ $s['invalid'] ?? 0 }}, Duplicates: {{ $s['duplicates'] ?? 0 }}
      </div>
    @endif
    <form action="{{ route('import.csv') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
        </div>
        <button class="btn btn-primary">Import</button>
        <a class="btn btn-outline-secondary" href="{{ route('upload.form') }}">Go to Chunk Upload</a>
    </form>
</div>
@endsection
