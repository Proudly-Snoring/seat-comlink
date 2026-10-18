@extends('web::layouts.grids.12')

@section('title', trans('comlink::comlink.menu_mappings'))
@section('page_header', trans('comlink::comlink.menu_mappings'))

@section('full')

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">{{ trans('comlink::comlink.menu_mappings') }}</h3>
    </div>
    <div class="card-body">

      <form method="get" action="{{ route('seatcomlink::mappings') }}" class="form-row mb-3">
        <div class="col-md-3">
          <select name="server" class="form-control form-control-sm" aria-label="{{ trans('comlink::comlink.server') }}">
            <option value="">{{ trans('comlink::comlink.server') }}: {{ trans('comlink::comlink.all') }}</option>
            @foreach($all_servers as $server)
              <option value="{{ $server->server_id }}" @selected(($filters['server'] ?? null) == $server->server_id)>{{ $server->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <select name="squad" class="form-control form-control-sm" aria-label="{{ trans('comlink::comlink.squad') }}">
            <option value="">{{ trans('comlink::comlink.squad') }}: {{ trans('comlink::comlink.all') }}</option>
            @foreach($squads as $squad)
              <option value="{{ $squad->id }}" @selected(($filters['squad'] ?? null) == $squad->id)>{{ $squad->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <input type="text" name="role" class="form-control form-control-sm" value="{{ $filters['role'] ?? '' }}"
                 placeholder="{{ trans('comlink::comlink.role') }}">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ trans('comlink::comlink.filter') }}</button>
          <a href="{{ route('seatcomlink::mappings') }}" class="btn btn-sm btn-default">{{ trans('comlink::comlink.reset') }}</a>
        </div>
      </form>

      <table class="table table-sm table-striped table-hover">
        <thead>
          <tr>
            <th>{{ trans('comlink::comlink.server') }}</th>
            <th>{{ trans('comlink::comlink.squad') }}</th>
            <th>{{ trans('comlink::comlink.role') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($mappings as $mapping)
            <tr>
              <td>{{ $mapping->server_name }}@if(! $mapping->server?->present) <span class="badge badge-warning" title="{{ trans('comlink::comlink.absent_help') }}">{{ trans('comlink::comlink.absent') }}</span>@endif</td>
              <td>{{ $mapping->squad_name }} <span class="text-muted" title="{{ trans('comlink::comlink.squad_count') }}">({{ $squad_counts[$mapping->squad_id] ?? 0 }})</span></td>
              <td>
                <span style="color: {{ $mapping->role->hex_color ?? '#99aab5' }}">&#9679;</span>
                {{ $mapping->role_name }}
                @if(! is_null($mapping->role?->member_count))
                  <span class="text-muted" title="{{ trans('comlink::comlink.role_count') }}">({{ $mapping->role->member_count }})</span>
                @endif
                @if(! $mapping->role || $mapping->role->isOrphan())
                  <span class="badge badge-danger" title="{{ trans('comlink::comlink.orphan_help') }}">{{ trans('comlink::comlink.orphan') }}</span>
                @endif
              </td>
              <td class="text-right text-nowrap">
                <button type="button" class="btn btn-xs btn-warning comlink-edit-mapping"
                        data-action="{{ route('seatcomlink::mappings.update', $mapping) }}"
                        data-server="{{ $mapping->server_id }}" data-squad="{{ $mapping->squad_id }}" data-role="{{ $mapping->role_id }}"
                        @disabled(! $mapping->server?->present)>
                  <i class="fas fa-pen"></i> {{ trans('comlink::comlink.edit') }}
                </button>
                <form method="post" action="{{ route('seatcomlink::mappings.destroy', $mapping) }}" class="d-inline comlink-delete-mapping">
                  {{ csrf_field() }}
                  {{ method_field('DELETE') }}
                  <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i> {{ trans('comlink::comlink.delete') }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-muted">{{ trans('comlink::comlink.no_mappings') }}</td></tr>
          @endforelse
        </tbody>
      </table>

      {{ $mappings->links() }}
    </div>
    <div class="card-footer d-flex">
      <form method="post" action="{{ route('seatcomlink::mappings.import') }}">
        {{ csrf_field() }}
        <button type="submit" class="btn btn-default">
          <i class="fas fa-download"></i> {{ trans('comlink::comlink.import_roles') }}
        </button>
      </form>
      <form method="post" action="{{ route('seatcomlink::mappings.sync') }}" class="ml-2">
        {{ csrf_field() }}
        <button type="submit" class="btn btn-default" title="{{ trans('comlink::comlink.sync_now_help') }}">
          <i class="fas fa-sync"></i> {{ trans('comlink::comlink.sync_now') }}
        </button>
      </form>
      <button type="button" class="btn btn-success ml-auto" id="comlink-add-mapping" @disabled($servers->isEmpty())>
        <i class="fas fa-plus"></i> {{ trans('comlink::comlink.add_mapping') }}
      </button>
    </div>
  </div>

  <div class="modal fade" id="comlink-mapping-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form class="modal-content" method="post" action="{{ route('seatcomlink::mappings.store') }}" id="comlink-mapping-form"
            data-store="{{ route('seatcomlink::mappings.store') }}">
        {{ csrf_field() }}
        <input type="hidden" name="_method" value="POST" id="comlink-mapping-method">
        <div class="modal-header">
          <h5 class="modal-title">{{ trans('comlink::comlink.mapping') }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="mapping_server">{{ trans('comlink::comlink.server') }}</label>
            <select name="server_id" id="mapping_server" class="form-control" required>
              @foreach($servers as $server)
                <option value="{{ $server->server_id }}">{{ $server->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label for="mapping_squad">{{ trans('comlink::comlink.squad') }}</label>
            <select name="squad_id" id="mapping_squad" class="form-control" required>
              @foreach($squads as $squad)
                <option value="{{ $squad->id }}">{{ $squad->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label for="mapping_role">{{ trans('comlink::comlink.role') }}</label>
            <select name="role_id" id="mapping_role" class="form-control" required>
              @foreach($servers as $server)
                {{-- @everyone and integration roles hidden; selectable roles first, each group by position --}}
                @foreach($server->roles->filter->isAssignable()->sortBy(fn ($role) => ! is_null($role->unmanageableReason($server->bot_top_position))) as $role)
                  @php($reason = $role->unmanageableReason($server->bot_top_position, true))
                  <option value="{{ $role->role_id }}" data-server="{{ $server->server_id }}" @disabled($reason)>
                    {{ $role->name }}@if($reason) - {{ $reason }}@endif
                  </option>
                @endforeach
              @endforeach
            </select>
            <small class="form-text text-muted">{{ trans('comlink::comlink.roles_help') }}</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('comlink::comlink.cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ trans('comlink::comlink.save') }}</button>
        </div>
      </form>
    </div>
  </div>

@stop

@push('javascript')
  <script>
    $(function () {
      var modal = $('#comlink-mapping-modal');
      var form = $('#comlink-mapping-form');

      // only show the roles of the selected server
      function filterRoles() {
        var server = $('#mapping_server').val();
        var select = $('#mapping_role');
        select.find('option').each(function () {
          var visible = $(this).data('server') == server;
          $(this).toggle(visible).prop('hidden', ! visible);
        });
        var current = select.find('option:selected');
        if (! current.length || current.prop('hidden') || current.prop('disabled'))
          select.val(select.find('option:not([hidden]):not(:disabled)').first().val() || '');
      }

      $('#mapping_server').on('change', filterRoles);

      $('#comlink-add-mapping').on('click', function () {
        form.attr('action', form.data('store'));
        $('#comlink-mapping-method').val('POST');
        filterRoles();
        modal.modal('show');
      });

      $('.comlink-edit-mapping').on('click', function () {
        form.attr('action', $(this).data('action'));
        $('#comlink-mapping-method').val('PUT');
        $('#mapping_server').val(String($(this).data('server')));
        $('#mapping_squad').val(String($(this).data('squad')));
        filterRoles();
        $('#mapping_role').val(String($(this).data('role')));
        modal.modal('show');
      });

      $('.comlink-delete-mapping').on('submit', function () {
        return confirm(@json(trans('comlink::comlink.mapping_delete_confirm')));
      });
    });
  </script>
@endpush
