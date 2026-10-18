@extends('web::layouts.grids.12')

@section('title', trans('comlink::comlink.menu_identity'))
@section('page_header', trans('comlink::comlink.menu_identity'))

@section('full')

  <div class="row">
    <div class="col-md-6 col-lg-4 col-xl-3">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fab fa-discord"></i> Discord</h3>
          <div class="card-tools">
            @if($discord)
              <span class="badge badge-success">{{ trans('comlink::comlink.status_linked') }}</span>
            @else
              <span class="badge badge-danger">{{ trans('comlink::comlink.status_unknown') }}</span>
            @endif
          </div>
        </div>
        <div class="card-body">
          @if($discord)
            <dl class="row mb-0">
              <dt class="col-5 text-right text-nowrap">{{ trans('comlink::comlink.discord_username') }}</dt>
              <dd class="col-7 mb-1">{{ $discord->username }}</dd>
              <dt class="col-5 text-right text-nowrap">{{ trans('comlink::comlink.discord_id') }}</dt>
              <dd class="col-7 mb-0"><code>{{ $discord->discord_id }}</code></dd>
            </dl>
          @else
            <p class="mb-0">{{ trans('comlink::comlink.identity_help') }}<br>{{ trans('comlink::comlink.identity_help_join') }}</p>
          @endif
        </div>
        <div class="card-footer @if($discord) text-right @endif">
          @if($discord)
            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#comlink-unlink">
              <i class="fas fa-unlink"></i> {{ trans('comlink::comlink.unlink') }}
            </button>
          @elseif($configured)
            <a href="{{ route('seatcomlink::discord.link') }}" class="btn btn-primary">
              <i class="fab fa-discord"></i> {{ trans('comlink::comlink.link') }}
            </a>
          @else
            <button type="button" class="btn btn-primary" disabled title="{{ trans('comlink::comlink.not_configured') }}">
              <i class="fab fa-discord"></i> {{ trans('comlink::comlink.link') }}
            </button>
          @endif
        </div>
      </div>
    </div>
  </div>

  @if($discord)
    <div class="modal fade" id="comlink-unlink" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form class="modal-content" method="post" action="{{ route('seatcomlink::discord.unlink') }}">
          {{ csrf_field() }}
          <div class="modal-header">
            <h5 class="modal-title">{{ trans('comlink::comlink.unlink') }}</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            {{ trans('comlink::comlink.unlink_confirm', ['username' => $discord->username]) }}<br>
            <i class="fas fa-exclamation-triangle text-warning"></i> {{ trans('comlink::comlink.unlink_confirm_roles') }}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('comlink::comlink.cancel') }}</button>
            <button type="submit" class="btn btn-danger">{{ trans('comlink::comlink.unlink') }}</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @if($pending)
    <div class="modal fade" id="comlink-replace" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ trans('comlink::comlink.replace_title') }}</h5>
          </div>
          <div class="modal-body">
            {{ trans('comlink::comlink.replace_confirm', ['username' => $pending['username']]) }}
          </div>
          <div class="modal-footer">
            <form method="post" action="{{ route('seatcomlink::discord.link.cancel') }}">
              {{ csrf_field() }}
              <button type="submit" class="btn btn-default">{{ trans('comlink::comlink.cancel') }}</button>
            </form>
            <form method="post" action="{{ route('seatcomlink::discord.link.confirm') }}">
              {{ csrf_field() }}
              <button type="submit" class="btn btn-warning">{{ trans('comlink::comlink.confirm') }}</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  @endif

@stop

@push('javascript')
  @if($pending)
    <script>
      $(function () {
        $('#comlink-replace').modal('show');
      });
    </script>
  @endif
@endpush
