<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use Illuminate\Support\Facades\DB;
use ProudlySnoring\Seat\Comlink\Services\IdentityResolver;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;

class IdentityResolverTest extends TestCase
{
    public function testMainCharacterWithAffiliation()
    {
        $user = $this->makeUser('main', [
            'id' => 1001, 'name' => 'Main Pilot',
            'corp' => [2001, 'Pilots Inc', 'PILOT'],
            'alliance' => [3001, 'Big Alliance', 'BIGA'],
        ]);

        $this->assertSame([
            'character' => 'Main Pilot',
            'corp_ticker' => 'PILOT',
            'corp_name' => 'Pilots Inc',
            'alliance_ticker' => 'BIGA',
        ], (new IdentityResolver())->forUser($user->id));
    }

    public function testFirstCharacterWithoutMain()
    {
        $user = $this->makeUser('nomain');
        $this->addCharacter($user, ['id' => 1002, 'name' => 'Second', 'corp' => [2002, 'Corp B', 'CB']], '2024-02-01 00:00:00');
        $this->addCharacter($user, ['id' => 1003, 'name' => 'First', 'corp' => [2003, 'Corp A', 'CA']], '2024-01-01 00:00:00');

        $values = (new IdentityResolver())->forUser($user->id);

        $this->assertSame('First', $values['character']);
        $this->assertSame('CA', $values['corp_ticker']);
        $this->assertNull($values['alliance_ticker']);
    }

    public function testUnknownMainFallsBackToLinkedCharacter()
    {
        $user = $this->makeUser('stale');
        DB::table('users')->where('id', $user->id)->update(['main_character_id' => 999]);
        $this->addCharacter($user, ['id' => 1004, 'name' => 'Linked']);

        $this->assertSame('Linked', (new IdentityResolver())->forUser($user->id)['character']);
    }

    public function testUserWithoutCharacter()
    {
        $user = $this->makeUser('empty');

        $this->assertSame(IdentityResolver::empty(), (new IdentityResolver())->forUser($user->id));
        $this->assertSame(IdentityResolver::empty(), (new IdentityResolver())->forUser(12345));
    }
}
