@extends('admin.layouts.master')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4"><h3>Audit Log</h3><span class="text-muted">Administrative activity</span></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Method</th><th>IP</th><th>Status</th></tr></thead><tbody>
    @forelse($logs as $log)<tr><td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td><td>{{ class_basename($log->actor_type) }} #{{ $log->actor_id }}</td><td><code>{{ $log->action }}</code></td><td>{{ $log->method }}</td><td>{{ $log->ip_address }}</td><td>{{ data_get($log->metadata, 'status') }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No audit events.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $logs->links() }}</div></div>
</div>
@endsection
