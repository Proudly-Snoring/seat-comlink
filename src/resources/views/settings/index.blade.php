@extends('web::layouts.grids.12')

@section('title', trans('comlink::comlink.menu_settings'))
@section('page_header', trans('comlink::comlink.menu_settings'))

@section('full')

  {{-- bot creation procedure --}}
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fab fa-discord"></i> {{ trans('comlink::comlink.procedure_title') }}</h3>
      <div class="card-tools">
        <button type="button" class="btn btn-sm btn-default" data-toggle="collapse" data-target="#comlink-procedure"
                aria-expanded="{{ $has_credentials ? 'false' : 'true' }}" aria-controls="comlink-procedure" id="comlink-procedure-toggle">
          <i class="fas fa-eye"></i> {{ trans('comlink::comlink.procedure_toggle') }}
        </button>
      </div>
    </div>
    <div class="collapse {{ $has_credentials ? '' : 'show' }}" id="comlink-procedure">
      <div class="card-body">
        <ol class="mb-0">
          <li>{!! trans('comlink::comlink.procedure_step_application', ['url' => e($portal_url)]) !!}</li>
          <li>{!! trans('comlink::comlink.procedure_step_oauth') !!}</li>
          <li>{!! trans('comlink::comlink.procedure_step_redirect', ['url' => e($callback_url)]) !!}</li>
          <li>{!! trans('comlink::comlink.procedure_step_token') !!}</li>
          <li>{!! trans('comlink::comlink.procedure_step_intent') !!}</li>
          <li>{!! trans('comlink::comlink.procedure_step_save') !!}</li>
          <li>{!! trans('comlink::comlink.procedure_step_invite') !!}</li>
          <li>{!! trans('comlink::comlink.procedure_step_hierarchy') !!}</li>
        </ol>
      </div>
    </div>
  </div>

  {{-- credentials and general settings --}}
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">{{ trans('comlink::comlink.settings_general') }}</h3>
    </div>
    <form method="post" action="{{ route('seatcomlink::settings.update') }}" autocomplete="off">
      {{ csrf_field() }}
      <div class="card-body">

        <div class="form-group">
          <label for="client_id">{{ trans('comlink::comlink.client_id') }}</label>
          <input type="text" class="form-control" name="client_id" id="client_id" value="{{ old('client_id', $client_id) }}">
        </div>

        @foreach(['client_secret' => $client_secret, 'bot_token' => $bot_token] as $field => $value)
          <div class="form-group">
            <label for="{{ $field }}">{{ trans('comlink::comlink.' . $field) }}</label>
            <div class="input-group">
              <input type="password" class="form-control" name="{{ $field }}" id="{{ $field }}" value="{{ $value }}" autocomplete="new-password">
              <div class="input-group-append">
                <button type="button" class="btn btn-default comlink-reveal" data-target="#{{ $field }}" title="{{ trans('comlink::comlink.reveal') }}">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
          </div>
        @endforeach

        <div class="form-group">
          <label for="callback_url">{{ trans('comlink::comlink.callback_url') }}</label>
          <div class="input-group">
            <input type="text" class="form-control" id="callback_url" value="{{ $callback_url }}" readonly>
            <div class="input-group-append">
              <button type="button" class="btn btn-default comlink-copy" data-target="#callback_url" title="{{ trans('comlink::comlink.copy') }}">
                <i class="fas fa-copy"></i>
              </button>
            </div>
          </div>
          <small class="form-text text-muted">{{ trans('comlink::comlink.callback_url_help') }}</small>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="sync_rate">{{ trans('comlink::comlink.sync_rate') }}</label>
            <select class="form-control" name="sync_rate" id="sync_rate">
              @foreach($sync_rates as $rate)
                <option value="{{ $rate }}" @selected(old('sync_rate', $sync_rate) == $rate)>
                  {{ trans_choice('comlink::comlink.minutes', $rate, ['count' => $rate]) }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="form-group col-md-6">
            <label for="log_retention">{{ trans('comlink::comlink.log_retention') }}</label>
            <input type="number" min="1" max="3650" class="form-control" name="log_retention" id="log_retention"
                   value="{{ old('log_retention', $log_retention) }}">
          </div>
        </div>

      </div>
      <div class="card-footer">
        @if($invite_url)
          <a href="{{ $invite_url }}" target="_blank" rel="noopener" class="btn btn-default">
            <i class="fab fa-discord"></i> {{ trans('comlink::comlink.invite_bot') }}
          </a>
        @else
          <button type="button" class="btn btn-default" disabled title="{{ trans('comlink::comlink.invite_bot_disabled') }}">
            <i class="fab fa-discord"></i> {{ trans('comlink::comlink.invite_bot') }}
          </button>
        @endif
        <button type="submit" class="btn btn-primary float-right">{{ trans('comlink::comlink.save') }}</button>
      </div>
    </form>
  </div>

  {{-- servers --}}
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">{{ trans('comlink::comlink.servers') }}</h3>
    </div>
    <div class="card-body">
      @forelse($servers as $server)
        <form method="post" action="{{ route('seatcomlink::settings.server', $server) }}" class="comlink-server border rounded p-3 mb-3">
          {{ csrf_field() }}
          <h5>
            @if($server->icon_url)
              <img src="{{ $server->icon_url }}" class="img-circle" width="24" height="24" alt="">
            @endif
            {{ $server->name }}
            <small class="text-muted">
              ({{ $server->server_id }})
              -
              {{ trans('comlink::comlink.last_synced') }}
              {{ $server->last_synced_at ? $server->last_synced_at->diffForHumans() : trans('comlink::comlink.never') }}
            </small>
          </h5>

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

          <div class="form-group mt-2 comlink-format-group" @if(! $server->enforce_nickname) style="display: none" @endif>
            <label for="nickname_format_{{ $server->id }}">{{ trans('comlink::comlink.nickname_format') }}</label>
            <input type="text" class="form-control comlink-format" name="nickname_format" id="nickname_format_{{ $server->id }}"
                   value="{{ $server->nickname_format }}" maxlength="255">
            <small class="form-text text-muted">
              {{ trans('comlink::comlink.nickname_tokens') }}
              @foreach($tokens as $token)
                <code>{{ '{' . $token . '}' }}</code>
              @endforeach
            </small>
            <div class="mt-1">
              {{ trans('comlink::comlink.preview') }}: <strong class="comlink-preview">{{ $server->preview }}</strong>
            </div>
            <div class="alert alert-warning mt-2 mb-0 py-1">
              <i class="fas fa-exclamation-triangle"></i>
              {{ trans('comlink::comlink.nickname_rules', ['max' => $max_length]) }}
            </div>
          </div>

          <button type="submit" class="btn btn-sm btn-primary">{{ trans('comlink::comlink.save') }}</button>
          <button type="submit" class="btn btn-sm btn-default" formaction="{{ route('seatcomlink::settings.server.sync', $server) }}">
            <i class="fas fa-sync"></i> {{ trans('comlink::comlink.sync_now') }}
          </button>
        </form>
      @empty
        <p class="text-muted mb-0">{{ trans('comlink::comlink.no_servers') }}</p>
      @endforelse
    </div>
  </div>

@stop

@push('javascript')
  <script>
    $(function () {
      $('.comlink-reveal').on('click', function () {
        var input = $($(this).data('target'));
        var hidden = input.attr('type') === 'password';
        input.attr('type', hidden ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye', ! hidden).toggleClass('fa-eye-slash', hidden);
      });

      $('.comlink-copy').on('click', function () {
        var input = $($(this).data('target'));
        input.trigger('select');
        if (navigator.clipboard) navigator.clipboard.writeText(input.val());
        else document.execCommand('copy');
      });

      $('.comlink-enforce-nickname').on('change', function () {
        $(this).closest('form').find('.comlink-format-group').toggle(this.checked);
      });

      var timer = null;
      $('.comlink-format').on('input', function () {
        var input = $(this);
        clearTimeout(timer);
        timer = setTimeout(function () {
          $.getJSON('{{ route('seatcomlink::settings.preview') }}', {format: input.val()}, function (data) {
            input.closest('form').find('.comlink-preview').text(data.nickname);
          });
        }, 300);
      });
    });
  </script>
@endpush
