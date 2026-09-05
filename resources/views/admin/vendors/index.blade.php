@extends('admin.layouts.admin')

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .vendor-actions { display:flex; gap:4px; flex-wrap:wrap; }
        .vendor-metric { white-space:nowrap; }
        .vendor-ban-reason { min-height:120px; }
    </style>
@endsection

@section('content')
    <div class="card mt-4">
        <div class="card-header card-header-bg text-white">
            <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.vendors.title_list') }}</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-light border mb-3">
                <strong>Vendor moderation:</strong> Block temporarily stops a vendor from selling. Ban permanently marks the vendor as banned until an administrator unbans them. Existing orders and financial records are retained.
            </div>
            <table id="vendors-table" class="table table-bordered mt-4 dt-style w-100">
                <thead>
                    <tr>
                        <th>{{ __('cms.vendors.id') }}</th>
                        <th>{{ __('cms.vendors.name') }}</th>
                        <th>{{ __('cms.vendors.email') }}</th>
                        <th>Sales</th>
                        <th>Rating</th>
                        <th>{{ __('cms.vendors.status') }}</th>
                        <th>{{ __('cms.vendors.actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="modal fade" id="banVendorModal" tabindex="-1" aria-labelledby="banVendorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="banVendorModalLabel">Ban vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">The vendor will be unable to access vendor selling functions and their active products will not be shown in the marketplace.</p>
                    <label for="banReason" class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea id="banReason" class="form-control vendor-ban-reason" maxlength="2000" placeholder="Enter the reason for this ban..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBanVendor">Ban vendor</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteVendorModal" tabindex="-1" aria-labelledby="deleteVendorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteVendorModalLabel">{{ __('cms.vendors.modal_confirm_delete_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">{{ __('cms.vendors.modal_confirm_delete_body') }}</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('cms.vendors.cancel') }}</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteVendor">{{ __('cms.vendors.delete') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

@php $datatableLang = __('cms.datatables'); @endphp

@if (session('success'))
<script>
    toastr.success("{{ session('success') }}", "{{ __('cms.vendors.success') }}", { closeButton:true, progressBar:true, positionClass:"toast-top-right", timeOut:5000 });
</script>
@endif

<script>
$(document).ready(function() {
    $('#vendors-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: "{{ route('admin.vendors.data') }}", type: "GET" },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'email', name: 'email' },
            { data: 'performance', name: 'completed_sales', orderable: false, searchable: false },
            { data: 'rating', name: 'approved_reviews_avg_rating', orderable: false, searchable: false },
            { data: 'status', name: 'status', orderable: true, searchable: true },
            { data: 'action', orderable: false, searchable: false }
        ],
        pageLength: 10,
        language: @json($datatableLang)
    });
});

let vendorToBanId = null;
let vendorToDeleteId = null;

function setVendorStatus(id, status) {
    $.ajax({
        url: '{{ route('admin.vendors.status', ':id') }}'.replace(':id', id),
        method: 'POST',
        data: { _token: "{{ csrf_token() }}", status: status },
        success: function(response) {
            if (response.success) {
                $('#vendors-table').DataTable().ajax.reload(null, false);
                toastr.success(response.message, 'Vendor moderation', { closeButton:true, progressBar:true, positionClass:"toast-top-right", timeOut:5000 });
            }
        },
        error: function(xhr) {
            const message = xhr.responseJSON?.message || 'Unable to update vendor status.';
            toastr.error(message, 'Error');
        }
    });
}

function banVendor(id) {
    vendorToBanId = id;
    $('#banReason').val('');
    $('#banVendorModal').modal('show');
}

$('#confirmBanVendor').on('click', function() {
    if (!vendorToBanId) return;
    const reason = $('#banReason').val().trim();
    if (!reason) {
        $('#banReason').addClass('is-invalid');
        return;
    }
    $('#banReason').removeClass('is-invalid');
    setVendorStatusWithReason(vendorToBanId, reason);
});

function setVendorStatusWithReason(id, reason) {
    $.ajax({
        url: '{{ route('admin.vendors.status', ':id') }}'.replace(':id', id),
        method: 'POST',
        data: { _token: "{{ csrf_token() }}", status: 'banned', ban_reason: reason },
        success: function(response) {
            if (response.success) {
                $('#banVendorModal').modal('hide');
                $('#vendors-table').DataTable().ajax.reload(null, false);
                toastr.success(response.message, 'Vendor moderation', { closeButton:true, progressBar:true, positionClass:"toast-top-right", timeOut:5000 });
            }
        },
        error: function(xhr) {
            const message = xhr.responseJSON?.message || 'Unable to ban vendor.';
            toastr.error(message, 'Error');
        }
    });
}

function deleteVendor(id) {
    vendorToDeleteId = id;
    $('#deleteVendorModal').modal('show');

    $('#confirmDeleteVendor').off('click').on('click', function() {
        if (vendorToDeleteId === null) return;
        $.ajax({
            url: '{{ route('admin.vendors.destroy', ':id') }}'.replace(':id', vendorToDeleteId),
            method: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function(response) {
                if (response.success) {
                    $('#vendors-table').DataTable().ajax.reload(null, false);
                    toastr.success(response.message, "{{ __('cms.vendors.success') }}", { closeButton:true, progressBar:true, positionClass:"toast-top-right", timeOut:5000 });
                    $('#deleteVendorModal').modal('hide');
                }
            },
            error: function() {
                toastr.error("{{ __('cms.vendors.error_delete') }}", "Error");
                $('#deleteVendorModal').modal('hide');
            }
        });
    });
}
</script>
@endsection
