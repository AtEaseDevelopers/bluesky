@extends('layouts.admin')
@section('title', 'Public Order Links')
@section('content')
    <div class="card shadow no-border">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="card-title mb-1">Public Order Links</h5>
                    <p class="text-muted mb-0">Generate a one-time link for walk-in / public customers. Each link expires after one order is submitted.</p>
                </div>
                <form action="{{ route('admin.public-order-links.generate') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary">Generate New Link</button>
                </form>
            </div>

            @if (session('generated_link'))
                <div class="alert alert-success">
                    <strong>New link ready — copy and share:</strong>
                    <div class="input-group mt-2">
                        <input type="text" class="form-control" id="generated-link" value="{{ session('generated_link') }}" readonly>
                        <button type="button" class="btn btn-outline-secondary" onclick="copyGeneratedLink()">Copy</button>
                        <a href="{{ session('generated_link') }}" class="btn btn-outline-primary" target="_blank">Open</a>
                    </div>
                </div>
            @endif

            <hr>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>Link</th>
                            <th>Status</th>
                            <th>Order</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($links as $link)
                            <tr>
                                <td>{{ $link->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if ($link->isActive())
                                        <input type="text" class="form-control form-control-sm" value="{{ $link->url }}" readonly style="min-width: 280px;">
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($link->isActive())
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Used</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($link->order_id)
                                        <a href="{{ route('admin.orders.summary', $link->order_id) }}">#{{ $link->order_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $link->creator->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No links generated yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        function copyGeneratedLink() {
            var input = document.getElementById('generated-link');
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
        }
    </script>
@endsection
