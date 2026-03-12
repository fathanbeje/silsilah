<?php

namespace Tests\Unit;

use App\Couple;
use App\Support\FamilyViewBuilder;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyViewBuilderTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_groups_a_womans_children_under_the_correct_husbands()
    {
        $wife = factory(User::class)->states('female')->create(['name' => 'Ibu']);
        $husbandA = factory(User::class)->states('male')->create(['name' => 'Ayah A']);
        $husbandB = factory(User::class)->states('male')->create(['name' => 'Ayah B']);
        $coupleA = factory(Couple::class)->create([
            'husband_id' => $husbandA->id,
            'wife_id' => $wife->id,
        ]);
        $coupleB = factory(Couple::class)->create([
            'husband_id' => $husbandB->id,
            'wife_id' => $wife->id,
        ]);

        $childA = factory(User::class)->create([
            'name' => 'Anak A',
            'birth_order' => 1,
            'parent_id' => $coupleA->id,
            'father_id' => null,
            'mother_id' => null,
        ]);
        $childB = factory(User::class)->create([
            'name' => 'Anak B',
            'birth_order' => 1,
            'parent_id' => $coupleB->id,
            'father_id' => null,
            'mother_id' => null,
        ]);

        $builder = app(FamilyViewBuilder::class);
        $builder->loadChartRelations($wife);
        $card = $builder->buildFamilyCard($wife, true);

        $this->assertEqualsCanonicalizing(
            [$childA->id, $childB->id],
            $wife->childs->pluck('id')->all()
        );

        $groups = collect($card['family_groups'])->keyBy(function (array $group) {
            return optional($group['spouse'])->id;
        });

        $this->assertEquals([$childA->id], $groups[$husbandA->id]['children']->pluck('user.id')->all());
        $this->assertEquals([$childB->id], $groups[$husbandB->id]['children']->pluck('user.id')->all());
    }
}
