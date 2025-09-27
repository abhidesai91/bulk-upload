@extends('layouts.app')
@section('content')
<div class="container mt-4">
    <h2>Chunked Image Upload</h2>
    <div id="drop-area" class="border p-5 text-center bg-light">
        Drag & Drop Images Here
    </div>
    <div id="log" class="mt-3 small text-muted"></div>
</div>
@endsection

@section('scripts')
<script>
const dropArea = document.getElementById('drop-area');
const logEl = document.getElementById('log');

function log(msg){ logEl.innerText += msg + "\n"; }

async function sha256(blob) {
  const buf = await blob.arrayBuffer();
  const hashBuffer = await crypto.subtle.digest('SHA-256', buf);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

dropArea.addEventListener('dragover', e => {
    e.preventDefault();
    dropArea.classList.add('bg-success','text-white');
});

dropArea.addEventListener('dragleave', e => {
    dropArea.classList.remove('bg-success','text-white');
});

dropArea.addEventListener('drop', async e => {
    e.preventDefault();
    dropArea.classList.remove('bg-success','text-white');
    for (const f of e.dataTransfer.files) {
      await uploadFile(f);
    }
});

async function uploadFile(file) {
    const chunkSize = 2 * 1024 * 1024; // 2MB
    const total = Math.ceil(file.size / chunkSize);
    const checksum = await sha256(file);
    log(`Uploading ${file.name} (${file.size} bytes) total chunks=${total} checksum=${checksum.slice(0,8)}...`);

    for (let index = 0; index < total; index++) {
        const start = index * chunkSize;
        const chunk = file.slice(start, Math.min(start + chunkSize, file.size));
        const chunkHash = await sha256(chunk);
        const form = new FormData();
        form.append('file', chunk);
        form.append('name', file.name);
        form.append('index', index);
        form.append('chunk_hash', chunkHash);
        form.append('total', total);
        const res = await fetch('{{ route('upload.chunk') }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: form
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            log(`Chunk ${index} failed: ${data.status || res.status}`);
            return;
        }
        log(`Chunk ${index+1}/${total} uploaded`);
    }

    const finalizeRes = await fetch('{{ route('upload.complete') }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: new URLSearchParams({name: file.name, total, checksum})
    });
    const data = await finalizeRes.json().catch(() => ({}));
    if (finalizeRes.ok) {
        log(`Completed ${file.name} (image_id=${data.image_id})`);
    } else {
        log(`Finalize failed: ${data.status || finalizeRes.status}`);
    }
}
</script>
@endsection
