@extends('layouts.app')
@section('content')
<div class="container mt-4">
  <h2>Product Import Status</h2>
  @if(session('status'))
    <div class="alert alert-info">{{ session('status') }}</div>
  @endif
  <div class="card mb-3">
    <div class="card-body">
      <div>Status: <strong>{{ $import->status }}</strong></div>
      @if($import->status === 'failed')
        <div class="text-danger">Error: {{ $import->error }}</div>
      @endif
      <div class="mt-2">
        Total: {{ $import->total }} |
        Imported: {{ $import->imported }} |
        Updated: {{ $import->updated }} |
        Invalid: {{ $import->invalid }} |
        Duplicates: {{ $import->duplicates }}
      </div>
      <div class="mt-2">
        @if(!$import->finished_at)
          <small class="text-muted">Started: {{ optional($import->started_at)->toDateTimeString() ?? '-' }} — refreshing every 5s</small>
          <script>setTimeout(()=>location.reload(), 5000);</script>
        @else
          <small class="text-muted">Started: {{ optional($import->started_at)->toDateTimeString() ?? '-' }}, Finished: {{ $import->finished_at->toDateTimeString() }}</small>
        @endif
      </div>
    </div>
  </div>

  <h5>Recent rows (latest 50)</h5>
  <table class="table table-sm">
    <thead><tr><th>#</th><th>SKU</th><th>Status</th><th>Message</th><th>When</th></tr></thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->row_number }}</td>
          <td>{{ $r->sku }}</td>
          <td>{{ $r->status }}</td>
          <td>{{ $r->message }}</td>
          <td>{{ $r->created_at->toDateTimeString() }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-muted">No rows yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection

