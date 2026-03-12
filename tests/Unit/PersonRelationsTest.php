<?php

namespace Tests\Unit;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonRelationsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function create_user_model_with_factory()
    {
        $person = factory(User::class)->create();

        $this->seeInDatabase('users', [
            'nickname' => $person->nickname,
            'gender_id' => $person->gender_id,
        ]);
    }

    /** @test */
    public function person_can_have_a_father()
    {
        $person = factory(User::class)->create();
        $father = factory(User::class)->states('male')->create();
        $person->setFather($father);

        $this->seeInDatabase('users', [
            'id' => $person->id,
            'father_id' => $father->id,
        ]);

        $this->assertEquals($father->name, $person->father->name);
    }

    /** @test */
    public function person_can_have_a_mother()
    {
        $person = factory(User::class)->create();
        $mother = factory(User::class)->states('female')->create();
        $person->setMother($mother);

        $this->seeInDatabase('users', [
            'id' => $person->id,
            'mother_id' => $mother->id,
        ]);

        $this->assertEquals($mother->name, $person->mother->name);
    }

    /** @test */
    public function person_can_many_childs()
    {
        $mother = factory(User::class)->states('female')->create();
        $person = factory(User::class)->create();
        $person->setMother($mother);
        $person = factory(User::class)->create();
        $person->setMother($mother);

        $this->assertCount(2, $mother->childs);
    }

    /** @test */
    public function mother_childs_include_children_from_multiple_marriages_via_parent_id()
    {
        $mother = factory(User::class)->states('female')->create();
        $fatherA = factory(User::class)->states('male')->create();
        $fatherB = factory(User::class)->states('male')->create();
        $coupleA = factory(\App\Couple::class)->create([
            'husband_id' => $fatherA->id,
            'wife_id' => $mother->id,
        ]);
        $coupleB = factory(\App\Couple::class)->create([
            'husband_id' => $fatherB->id,
            'wife_id' => $mother->id,
        ]);

        $childA = factory(User::class)->create([
            'parent_id' => $coupleA->id,
            'father_id' => null,
            'mother_id' => null,
        ]);
        $childB = factory(User::class)->create([
            'parent_id' => $coupleB->id,
            'father_id' => null,
            'mother_id' => null,
        ]);

        $this->assertEqualsCanonicalizing(
            [$childA->id, $childB->id],
            $mother->fresh()->childs->pluck('id')->all()
        );
        $this->assertEquals([$childA->id], $fatherA->fresh()->childs->pluck('id')->all());
        $this->assertEquals([$childB->id], $fatherB->fresh()->childs->pluck('id')->all());
    }
}
