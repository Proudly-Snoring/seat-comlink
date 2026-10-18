@extends('web::layouts.grids.12')

@section('title', trans('comlink::comlink.menu_servers'))
@section('page_header', trans('comlink::comlink.menu_servers'))

@section('full')

  @forelse($servers as $server)
    <div class="card">
      <form method="post" action="{{ route('seatcomlink::servers.update', $server) }}" class="comlink-server">
        {{ csrf_field() }}
        <div class="card-header">
          <h3 class="card-title">
            @if($server->icon_url)
              <img src="{{ $server->icon_url }}" class="img-circle" width="24" height="24" alt="">
            @endif
            {{ $server->name }}
            <small class="text-muted">({{ $server->server_id }})</small>
          </h3>
          <div class="card-tools text-muted">
            {{ trans('comlink::comlink.last_synced') }}:
            {{ $server->last_synced_at ? $server->last_synced_at->toDateTimeString() : trans('comlink::comlink.never') }}
          </div>
        </div>
        <div class="card-body">
          <div class="form-check form-check-inline">
            <input type="hidden" name="enforce_nickname" value="0">
            <input class="form-check-input comlink-enforce-nickname" type="checkbox" name="enforce_nickname" value="1"
                   id="enforce_nickname_{{ $server->id }}" @checked($server->enforce_nickname)>
            <label class="form-check-label" for="enforce_nickname_{{ $server->id }}">{{ trans('comlink::comlink.enforce_nickname') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input type="hidden" name="enforce_roles" value="0">
            <input class="form-check-input" type="checkbox" name="enforce_roles" value="1"
                   id="enforce_roles_{{ $server->id }}" @checked($server->enforce_roles)>
            <label class="form-check-label" for="enforce_roles_{{ $server->id }}">{{ trans('comlink::comlink.enforce_roles') }}</label>
          </div>

          <div class="form-group mt-3 mb-0 comlink-format-group" @if(! $server->enforce_nickname) style="display: none" @endif>
            <label for="nickname_format_{{ $server->id }}">
              {{ trans('comlink::comlink.nickname_format') }}
              <span class="font-weight-normal">- {{ trans('comlink::comlink.preview') }}</span>
              <span class="comlink-preview">{{ $server->preview }}</span>
            </label>
            <input type="text" class="form-control comlink-format" name="nickname_format" id="nickname_format_{{ $server->id }}"
                   value="{{ $server->nickname_format }}" maxlength="255">
            <div class="callout callout-info py-2 my-2">
              <small><i class="fas fa-info-circle"></i> {{ trans('comlink::comlink.nickname_rules', ['max' => $max_length]) }}</small>
            </div>
            <div class="callout callout-info py-2 mb-0">
              <small>
                <i class="fas fa-info-circle"></i> {{ trans('comlink::comlink.nickname_tokens') }}
                @foreach($tokens as $token)
                  <code>{{ '{' . $token . '}' }}</code>
                @endforeach
              </small>
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary float-right">{{ trans('comlink::comlink.save') }}</button>
        </div>
      </form>
    </div>
  @empty
    <div class="card">
      <div class="card-body">
        <p class="text-muted mb-0">{{ trans('comlink::comlink.no_servers') }}</p>
      </div>
    </div>
  @endforelse

@stop

@push('javascript')
  <script>
    $(function () {
      $('.comlink-enforce-nickname').on('change', function () {
        $(this).closest('form').find('.comlink-format-group').toggle(this.checked);
      });

      var timer = null;
      $('.comlink-format').on('input', function () {
        var input = $(this);
        clearTimeout(timer);
        timer = setTimeout(function () {
          $.getJSON('{{ route('seatcomlink::servers.preview') }}', {format: input.val()}, function (data) {
            input.closest('form').find('.comlink-preview').text(data.nickname);
          });
        }, 300);
      });
    });
  </script>
@endpush
