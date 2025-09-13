@extends('dash.layouts.app')

@section('title', 'History Detail')


@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form id="form-update-general" action="{{ route('workflow.update-general') }}" enctype="multipart/form-data" method="POST">
                @csrf
                <input type="hidden" name="id" value="{{ $row->id }}">
                <div class="row">
                    <div class="col-12 col-xl-6 col-lg-6">
                        <div class="mb-3">
                            <label class="form-label">Workflow Name</label>
                            <input type="text" name="title" class="form-control" autocomplete="off" required value="{{ $row->title }}">
                        </div>
                    </div>
                    <div class="col-12 col-xl-6 col-lg-6">
                        <div class="mb-3">
                            <label class="form-label">Device (Sender)</label>
                            <select name="session_id" required class="form-select">
                                @foreach ($sessions as $ss)
                                    <option value="{{ $ss->id }}" {{ $ss->id == $row->session_id ? 'selected' : '' }}>{{ $ss->session_name }} ( {{ $ss->whatsapp_number }} )</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6 col-lg-6">
                        <div class="mb-3">
                            <label class="form-label">Message Template</label>
                            <select name="message_template_id" required class="form-select">
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" {{ $template->id == $row->message_template_id ? 'selected' : '' }}>{{ $template->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-12 col-xl-6 col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3 fw-bold fs-5">Webhook Callback Url</div>
                    <div class="p-3 bg-light rounded" style="user-select: all;" >{!! config('app.base_node') !!}/api/workflow/{{ $row->slug }}</div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3 fw-bold fs-5">Webhook Response Mapping</div>
                      <form id="form-update-mapping" action="{{ route('workflow.update-variable') }}" enctype="multipart/form-data" method="POST" style="display: none" >
                        @csrf
                        <input type="hidden" name="id" value="{{ $row->id }}">
                        <div class="mb-3">
                            <label for=""><span class="fw-semibold">Variable:</span> Receiver</label>
                            <select name="variables[receiver]" class="form-control" data-select-raw>
                            </select>
                        </div>
                        @php
                            preg_match_all('/\{(.*?)\}/', $row->template->message, $matches);
                            $variables = $matches[1];
                        @endphp
                        @foreach ($variables as $item)
                            <div class="mb-3">
                                <label for=""><span class="fw-semibold">Variable:</span> {{ $item }}</label>
                                <select name="variables[{{ $item }}]" class="form-control" data-select-raw>
                                </select>
                            </div>
                        @endforeach
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6 col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3 fw-bold fs-5">Raw</div>
                    <div class="mb-3" id="raw-render">

                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-primary" data-click-capture {{ $row->status == 'dev' ? 'disabled' : '' }}>
                        @if($row->status == 'dev')
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Waiting for response...
                        @else
                            Capture Response
                        @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script src="{!! asset('assets/libvelixs/client-dist/socket.io.js') !!}"></script>
<script>
    let limit_attempts = {{ config('app.attemp_socket') }};
    let attempts = 0;
    @if (config('app.socket_default'))
        const socket = io();
    @else
        const socket = io("{{ config('app.base_node') }}", {
            transports: ['websocket']
        });
    @endif

    socket.on('workflow_callback', (data) => {
        if (data.workflow_id != "{{ $row->id }}") return;

        const clone = { ...data };
        delete clone.workflow_id;

        $("#raw-render").html(`<pre>${JSON.stringify(clone, null, 2)}</pre>`);

        const wrapperSelectRaw = document.querySelectorAll('[data-select-raw]');
        wrapperSelectRaw.forEach((item) => {
            item.innerHTML = '';

            if (clone.body && Object.keys(clone.body).length > 0) {
                let options = '';
                Object.entries(clone.body).forEach(([key, value]) => {
                    options += `<option value="body.${key}">body.${key} : ${value}</option>`;
                });
                item.insertAdjacentHTML('beforeend', `<optgroup label="Body">${options}</optgroup>`);
            }

            if (clone.query && Object.keys(clone.query).length > 0) {
                let options = '';
                Object.entries(clone.query).forEach(([key, value]) => {
                    options += `<option value="query.${key}">query.${key} : ${value}</option>`;
                });
                item.insertAdjacentHTML('beforeend', `<optgroup label="Query">${options}</optgroup>`);
            }

            if (clone.headers && Object.keys(clone.headers).length > 0) {
                let options = '';
                Object.entries(clone.headers).forEach(([key, value]) => {
                    options += `<option value="headers.${key}">headers.${key} : ${value}</option>`;
                });
                item.insertAdjacentHTML('beforeend', `<optgroup label="Headers">${options}</optgroup>`);
            }
        });

        $("[data-click-capture]").removeAttr('disabled')
        $("[data-click-capture]").html('Capture Response')
        $("form#form-update-mapping").show();
    });

    var ilsya = new velixs()
    $("form#form-update-general").submit(function(e) {
        e.preventDefault()
        ilsya.ajax({
            url: $(this).attr('action'),
            data: $(this).serialize(),
            addons_success: function() {
                window.location.reload()
            }
        })
    })

    $("form#form-update-mapping").submit(function(e) {
        e.preventDefault()
        ilsya.ajax({
            url: $(this).attr('action'),
            data: $(this).serialize(),
            addons_success: function() {
            }
        })
    })

    $("[data-click-capture]").click(function() {
        $("[data-click-capture]").attr('disabled', 'disabled')
        $("[data-click-capture]").html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Waiting for response...')
        ilsya.ajax({
            url: "{{ route('workflow.capture-dev', ':id') }}".replace(':id', "{{ $row->id }}"),
            data: $(this).serialize(),
            success: function(res) {
                Swal.close()
            }
        })
    })
</script>
@endpush
