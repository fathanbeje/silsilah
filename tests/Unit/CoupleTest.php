<?php

namespace Tests\Unit;

use App\Couple;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoupleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_couple_consists_of_a_husband_and_a_wife()
    {
        $couple = factory(Couple::class)->create();
        $this->assertTrue($couple->husband instanceof User);
        $this->assertTrue($couple->wife instanceof User);
    }

    /** @test */
    public function a_couples_husband_or_wife_has_a_default_name()
    {
        $couple = factory(Couple::class)->create();
        $couple->husband->delete();
        $couple->wife->delete();
        $this->assertEquals($couple->fresh()->husband->name, 'N/A');
        $this->assertEquals($couple->fresh()->wife->name, 'N/A');
    }

    /** @test */
    public function a_couple_can_have_many_childs()
    {
        $couple = factory(Couple::class)->create();
        $this->assertCount(0, $couple->childs);

        $child = factory(User::class)->create();
        $couple->addChild($child);

        $child = $child->fresh();
        $this->assertCount(1, $couple->fresh()->childs);
        $this->assertEquals($child->parent_id, $couple->id);
        $this->assertEquals($child->father_id, $couple->husband_id);
        $this->assertEquals($child->mother_id, $couple->wife_id);
    }

    /** @test */
    public function husband_and_wife_childs_relation_also_reads_children_linked_via_parent_id()
    {
        $couple = factory(Couple::class)->create();
        $child = factory(User::class)->create([
            'father_id' => null,
            'mother_id' => null,
            'parent_id' => $couple->id,
        ]);

        $this->assertCount(1, $couple->husband->fresh()->childs);
        $this->assertCount(1, $couple->wife->fresh()->childs);
        $this->assertEquals($child->id, $couple->husband->fresh()->childs->first()->id);
        $this->assertEquals($child->id, $couple->wife->fresh()->childs->first()->id);
    }

    /** @test */
    public function a_couple_have_a_manager()
    {
        $couple = factory(Couple::class)->create();
        $this->assertTrue($couple->manager instanceof User);
    }
}
