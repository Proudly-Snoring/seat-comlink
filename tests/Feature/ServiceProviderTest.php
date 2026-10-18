<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function testPermissionsAreRegistered()
    {
        $this->assertSame(
            ['identity', 'mappings', 'servers', 'settings', 'logs', 'users'],
            array_keys(config('seat.permissions.comlink')));

        foreach (['identity', 'mappings', 'servers', 'settings', 'logs', 'users'] as $permission)
            $this->assertTrue(Gate::has('comlink.' . $permission));
    }

    public function testSidebarIsRegistered()
    {
        $menu = config('package.sidebar.comlink');

        $this->assertSame('seat-comlink', $menu['route_segment']);
        $this->assertSame(['identity', 'mappings', 'servers', 'users', 'logs', 'settings'], array_column($menu['entries'], 'name'));

        foreach ($menu['entries'] as $entry)
            $this->assertTrue(app('router')->has($entry['route']), $entry['route']);
    }

    public function testPagesRequireAuthentication()
    {
        $this->get(route('seatcomlink::identity'))->assertRedirect();
    }

    public function testPagesAreForbiddenWithoutPermission()
    {
        $user = $this->makeUser('nobody');

        foreach (['identity', 'mappings', 'servers', 'settings', 'logs', 'users'] as $page)
            $this->actingAs($user)->get(route('seatcomlink::' . $page))->assertForbidden();
    }

    public function testPagesAreReachableWithPermission()
    {
        $this->withoutExceptionHandling();
        $user = $this->makeUser('granted', ['id' => 90000001, 'name' => 'Granted Pilot']);

        // SeAT caches the user ACL: grant everything before the first check
        foreach (['identity', 'mappings', 'servers', 'settings', 'logs', 'users'] as $page)
            $this->grant($user, 'comlink.' . $page);

        foreach (['identity', 'mappings', 'servers', 'settings', 'logs', 'users'] as $page)
            $this->actingAs($user)->get(route('seatcomlink::' . $page))->assertOk();
    }
}
