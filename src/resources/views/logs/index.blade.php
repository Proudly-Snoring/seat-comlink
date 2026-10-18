@extends('web::layouts.grids.12')

@section('title', trans('comlink::comlink.menu_logs'))
@section('page_header', trans('comlink::comlink.menu_logs'))

@section('full')

  <div class="card">
    <div class="card-body">

      <form method="get" action="{{ route('seatcomlink::logs') }}" class="form-row mb-3">
        <div class="col-md-2">
          <select name="level" class="form-control form-control-sm" aria-label="{{ trans('comlink::comlink.level') }}">
            <option value="">{{ trans('comlink::comlink.level') }}: {{ trans('comlink::comlink.all') }}</option>
            @foreach($levels as $level)
              <option value="{{ $level }}" @selected(($filters['level'] ?? null) === $level)>{{ $level }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="driver" class="form-control form-control-sm" aria-label="{{ trans('comlink::comlink.driver') }}">
            <option value="">{{ trans('comlink::comlink.driver') }}: {{ trans('comlink::comlink.all') }}</option>
            @foreach($drivers as $driver)
              <option value="{{ $driver }}" @selected(($filters['driver'] ?? null) === $driver)>{{ $driver }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}"
                 aria-label="{{ trans('comlink::comlink.from') }}" title="{{ trans('comlink::comlink.from') }}">
        </div>
        <div class="col-md-2">
          <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}"
                 aria-label="{{ trans('comlink::comlink.to') }}" title="{{ trans('comlink::comlink.to') }}">
        </div>
        <div class="col-md-4">
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
            <th>{{ trans('comlink::comlink.message') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td class="text-nowrap">{{ $log->created_at }}</td>
              <td><span class="badge badge-{{ $log->level_class }}">{{ $log->level }}</span></td>
              <td>{{ $log->driver }}</td>
              <td>
                {{ $log->message }}
                @if($log->user)
                  <small class="text-muted">- {{ $log->user->name }}</small>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-muted">{{ trans('comlink::comlink.no_logs') }}</td></tr>
          @endforelse
        </tbody>
      </table>

      {{ $logs->links() }}
    </div>
  </div>

@stop
