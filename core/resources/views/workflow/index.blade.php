@extends('dash.layouts.app')

@section('title', 'Message Template')

@section('content')
<div class="mt-4">
    <div class="card">
        <div class="card-datatable table-responsive pt-0">
            <table class="datatables-basic table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Workflow</th>
                        <th>Device</th>
                        <th>Template</th>
                        <th>Created at</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-add" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFullTitle">Create Workflow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="store" action="{{ route('workflow.store') }}" method="post" style="display: contents" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title">Name</label>
                        <input type="text" class="form-control" name="title" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label for="session_id">Device (Sender)</label>
                        <select name="session_id" class="form-control">
                            @foreach ($sessions as $ss)
                                <option value="{{ $ss->id }}">{{ $ss->session_name }} ( {{ $ss->whatsapp_number }} )</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="template">Template Message</label>
                        <select name="message_template_id" class="form-control">
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
    <script>
        (function() {
            'use strict';
            var ilsya = new velixs()

            var dbs = ilsya.datatables({
                url: "{{ route('workflow') }}",
                header: `Workflows`,
                columns: [
                    {
                        data: 'responsive_id'
                    },
                    {
                        data: 'title'
                    },
                    {
                        data: 'device'
                    },
                    {
                        data: 'template'
                    },
                    {
                        data: 'created_at'
                    },
                    {
                        data: 'action'
                    }
                ],
                btn: [
                    {
                        text: '<i class="ti ti-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Create</span>',
                        className: 'is-button-add btn btn-primary me-2 ',
                        attr: {
                            'data-bs-toggle': 'modal',
                            'data-bs-target': '#modal-add'
                        }
                    }
                ],
            })

            $("form#store").submit(function(e) {
                e.preventDefault()
                ilsya.ajax({
                    url: $(this).attr('action'),
                    data: $(this).serialize(),
                    addons_success: function({ res }) {
                        window.location.href = res.url
                        $("#modal-add").modal('hide')
                    }
                })
            })

            $(document).on('click', ".is-btn-delete", function() {
                var id = $(this).data('id')
                Swal.fire({
                    text: "Are you sure you want to delete this data?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-outline-danger ms-1'
                    },
                    buttonsStyling: false
                }).then(function(result) {
                    if (result.value) {
                        ilsya.ajax({
                            url: "{{ route('workflow.delete', ':id') }}".replace(':id', id),
                            addons_success: function() {
                                dbs.ajax.reload()
                            }
                        })
                    }
                })
            })
        })()
    </script>
@endpush

@push('cssvendor')
    <link rel="stylesheet" href="{!! asset('assets') !!}/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="{!! asset('assets') !!}/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="{!! asset('assets') !!}/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css" />
    <link rel="stylesheet" href="{!! asset('assets') !!}/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />
@endpush

@push('jsvendor')
    <script src="{!! asset('assets') !!}/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
@endpush
