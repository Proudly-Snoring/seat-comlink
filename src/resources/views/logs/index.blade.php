@extends('web::layouts.grids.12')

@section('title', trans('comlink::comlink.menu_logs'))
@section('page_header', trans('comlink::comlink.menu_logs'))

@section('full')

  <div class="card">
    <div class="card-body">

      <form method="get" action="{{ route('seatcomlink::logs') }}" class="form-row mb-3">
        <div class="col-md-2">
          <div class="input-group input-group-sm">
            <div class="input-group-prepend">
              <label class="input-group-text" for="log_level">{{ trans('comlink::comlink.level') }}</label>
            </div>
            <select name="level" id="log_level" class="form-control">
              <option value="">{{ trans('comlink::comlink.all') }}</option>
              @foreach($levels as $level)
                <option value="{{ $level }}" @selected(($filters['level'] ?? null) === $level)>{{ $level }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="col-md-2">
          <div class="input-group input-group-sm">
            <div class="input-group-prepend">
              <label class="input-group-text" for="log_driver">{{ trans('comlink::comlink.driver') }}</label>
            </div>
            <select name="driver" id="log_driver" class="form-control">
              <option value="">{{ trans('comlink::comlink.all') }}</option>
              @foreach($drivers as $driver)
                <option value="{{ $driver }}" @selected(($filters['driver'] ?? null) === $driver)>{{ $driver }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="col-md-2">
          <div class="input-group input-group-sm">
            <div class="input-group-prepend">
              <label class="input-group-text" for="log_user">{{ trans('comlink::comlink.log_user') }}</label>
            </div>
            <select name="user" id="log_user" class="form-control">
              <option value="">{{ trans('comlink::comlink.all') }}</option>
              <option value="system" @selected(($filters['user'] ?? null) === 'system')>{{ trans('comlink::comlink.log_system') }}</option>
              @foreach($users as $id => $name)
                <option value="{{ $id }}" @selected(($filters['user'] ?? null) == $id)>{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        @foreach(['from', 'to'] as $bound)
          <div class="col-md-2">
            <div class="input-group input-group-sm">
              <div class="input-group-prepend">
                <label class="input-group-text" for="log_{{ $bound }}">{{ trans('comlink::comlink.' . $bound) }}</label>
              </div>
              <input type="date" name="{{ $bound }}" id="log_{{ $bound }}" class="form-control" value="{{ $filters[$bound] ?? '' }}">
            </div>
          </div>
        @endforeach
        <div class="col-md-2">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ trans('comlink::comlink.filter') }}</button>
          <a href="{{ route('seatcomlink::logs') }}" class="btn btn-sm btn-default">{{ trans('comlink::comlink.reset') }}</a>
        </div>
      </form>

      <table class="table table-sm table-striped table-hover">
        <thead>
          <tr>
            <th class="text-nowrap">{{ trans('comlink::comlink.date') }}</th>
            <th>{{ trans('comlink::comlink.level') }}</th>
            <th>{{ trans('comlink::comlink.driver') }}</th>
            <th>{{ trans('comlink::comlink.log_user') }}</th>
            <th>{{ trans('comlink::comlink.message') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td class="text-nowrap">{{ $log->created_at }}</td>
              <td><span class="badge badge-{{ $log->level_class }}">{{ $log->level }}</span></td>
              <td>{{ $log->driver }}</td>
              <td class="text-nowrap">
                @if($log->user)
                  {{ $log->user->name }}
                @elseif($log->user_id)
                  <span class="text-muted">#{{ $log->user_id }}</span>
                @else
                  <span class="text-muted">{{ trans('comlink::comlink.log_system') }}</span>
                @endif
              </td>
              <td>{{ $log->message }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">{{ trans('comlink::comlink.no_logs') }}</td></tr>
          @endforelse
        </tbody>
      </table>

      {{ $logs->links() }}
    </div>
  </div>

@stop
