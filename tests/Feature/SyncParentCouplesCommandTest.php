<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncParentCouplesCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_backfills_parent_id_from_existing_father_and_mother()
    {
        $father = factory(User::class)->states('male')->create();
        $mother = factory(User::class)->states('female')->create();
        $child = factory(User::class)->create([
            'father_id' => $father->id,
            'mother_id' => $mother->id,
            'parent_id' => null,
        ]);

        $exitCode = $this->artisan('family:sync-parent-couples');

        $this->assertSame(0, $exitCode);

        $child->refresh();

        $this->assertNotNull($child->parent_id);
        $this->seeInDatabase('couples', [
            'id' => $child->parent_id,
            'husband_id' => $father->id,
            'wife_id' => $mother->id,
        ]);
    }

    /** @test */
    public function it_backfills_father_and_mother_from_existing_parent_couple()
    {
        $father = factory(User::class)->states('male')->create();
        $mother = factory(User::class)->states('female')->create();
        $couple = factory(\App\Couple::class)->create([
            'husband_id' => $father->id,
            'wife_id' => $mother->id,
        ]);
        $child = factory(User::class)->create([
            'father_id' => null,
            'mother_id' => null,
            'parent_id' => $couple->id,
        ]);

        $exitCode = $this->artisan('family:sync-parent-couples');

        $this->assertSame(0, $exitCode);

        $child->refresh();

        $this->assertSame($father->id, $child->father_id);
        $this->assertSame($mother->id, $child->mother_id);
        $this->assertCount(1, $father->fresh()->childs);
        $this->assertCount(1, $mother->fresh()->childs);
    }
}
