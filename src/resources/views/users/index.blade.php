@extends('web::layouts.grids.12')

@section('title', trans('comlink::comlink.menu_users'))
@section('page_header', trans('comlink::comlink.menu_users'))

@section('full')

  <div class="card">
    <div class="card-body">

      <form method="get" action="{{ route('seatcomlink::users') }}" class="form-row mb-3">
        <div class="col-md-4">
          <input type="text" name="search" class="form-control form-control-sm" value="{{ $search }}"
                 placeholder="{{ trans('comlink::comlink.users_search') }}">
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ trans('comlink::comlink.filter') }}</button>
          <a href="{{ route('seatcomlink::users') }}" class="btn btn-sm btn-default">{{ trans('comlink::comlink.reset') }}</a>
        </div>
      </form>

      <table class="table table-sm table-striped table-hover">
        <thead>
          <tr>
            <th>{{ trans('comlink::comlink.seat_user_id') }}</th>
            <th>{{ trans('comlink::comlink.user_name') }}</th>
            <th>{{ trans('comlink::comlink.discord_id') }}</th>
            <th>{{ trans('comlink::comlink.discord_username') }}</th>
            <th>{{ trans('comlink::comlink.servers_nicknames') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($users as $link)
            <tr>
              <td>{{ $link->user_id }}</td>
              <td>{{ $link->user->name ?? '-' }}</td>
              <td><code>{{ $link->discord_id }}</code></td>
              <td>{{ $link->username }}</td>
              <td>
                @forelse($link->members as $member)
                  <div>
                    <b>{{ $member->server->name ?? $member->server_id }}</b>:
                    {{ $member->nickname ?? '-' }}
                  </div>
                @empty
                  <span class="text-muted">{{ trans('comlink::comlink.no_server') }}</span>
                @endforelse
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">{{ trans('comlink::comlink.no_users') }}</td></tr>
          @endforelse
        </tbody>
      </table>

      {{ $users->links() }}
    </div>
  </div>

@stop
