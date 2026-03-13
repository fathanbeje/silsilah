<?php

namespace Tests\Feature;

use App\Couple;
use App\Jobs\Images\OptimizeImages;
use App\DomainFamilyScope;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Storage;
use Tests\TestCase;

class UsersProfileTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_can_search_users_profile()
    {
        $user = $this->loginAsUser();

        $jono = factory(User::class)->create(['name' => 'Jono']);
        $jeni = factory(User::class)->create(['name' => 'Jeni']);
        $johan = factory(user::class)->create(['name' => 'Johan']);

        $this->visitRoute('users.search', ['q' => 'jo']);
        $this->seePageIs('http://localhost/?q=jo');

        $this->seeLink($jono->display_name, route('users.chart', $jono));
        $this->seeLink($johan->display_name, route('users.chart', $johan));
        $this->dontSeeLink($jeni->display_name, route('users.chart', $jeni));
    }

    /** @test */
    public function guest_can_open_public_family_tree_without_profile_detail_button()
    {
        $target = factory(User::class)->states('male')->create();
        $child = factory(User::class)->create([
            'father_id' => $target->id,
            'manager_id' => $target->id,
        ]);

        $this->visit(route('users.tree', $target))
            ->see($target->display_name)
            ->see($child->display_name)
            ->see(trans('app.show_family_tree'))
            ->dontSee(trans('app.show_profile').' '.$target->display_name);
    }

    /** @test */
    public function public_family_tree_shows_root_controls_and_generation_breakdown()
    {
        $core = factory(User::class)->states('male')->create([
            'name' => 'CORE TREE',
            'nickname' => 'CORE TREE',
        ]);
        $rootSpouse = factory(User::class)->states('female')->create([
            'name' => 'PASANGAN CORE',
            'nickname' => 'PASANGAN CORE',
        ]);
        $rootMarriage = factory(Couple::class)->create([
            'husband_id' => $core->id,
            'wife_id' => $rootSpouse->id,
            'manager_id' => $core->id,
        ]);
        $child = factory(User::class)->states('male')->create([
            'name' => 'ANAK TREE',
            'nickname' => 'ANAK TREE',
            'father_id' => $core->id,
            'mother_id' => $rootSpouse->id,
            'parent_id' => $rootMarriage->id,
            'manager_id' => $core->id,
            'is_deceased' => false,
        ]);
        $childSpouse = factory(User::class)->states('female')->create([
            'name' => 'MANTU TREE',
            'nickname' => 'MANTU TREE',
            'is_deceased' => true,
        ]);
        $childMarriage = factory(Couple::class)->create([
            'husband_id' => $child->id,
            'wife_id' => $childSpouse->id,
            'manager_id' => $core->id,
        ]);
        factory(User::class)->states('male')->create([
            'name' => 'CUCU TREE',
            'nickname' => 'CUCU TREE',
            'father_id' => $child->id,
            'mother_id' => $childSpouse->id,
            'parent_id' => $childMarriage->id,
            'manager_id' => $core->id,
            'is_deceased' => true,
        ]);

        $response = $this->call('GET', route('users.tree', $core));
        $content = $response->getContent();

        $this->assertResponseOk();
        $this->assertStringContainsString('Collapse Semua', $content);
        $this->assertStringContainsString('Expand Semua', $content);
        $this->assertStringContainsString('data-tree-global-toggle', $content);
        $this->assertStringContainsString('data-tree-tools-toggle', $content);
        $this->assertStringContainsString('Ukuran Konten', $content);
        $this->assertStringContainsString('Reset ke 100%', $content);
        $this->assertStringContainsString('data-tree-zoom-value', $content);
        $this->assertStringContainsString('data-tree-zoom-preset', $content);
        $this->assertStringContainsString('data-tree-drag-surface', $content);
        $this->assertStringContainsString('data-drag-enabled="false"', $content);
        $this->assertStringContainsString('Statistik Keturunan '.$core->display_name, $content);
        $this->assertStringContainsString('Rincian keturunan kandung dan mantu per generasi', $content);
        $this->assertStringNotContainsString('dari core aktif', $content);
        $this->assertStringContainsString('data-tree-summary-core', $content);
        $this->assertStringContainsString('Total Kandung', $content);
        $this->assertStringContainsString('Total Mantu', $content);
        $this->assertStringContainsString('Total Hidup', $content);
        $this->assertStringContainsString('Total Wafat', $content);
        $this->assertStringContainsString('Jumlah Kandung + Mantu', $content);
        $this->assertStringContainsString('Total Semua', $content);
        $this->assertStringNotContainsString('Kembali ke core', $content);
        $this->assertStringContainsString('data-tree-preview-trigger', $content);
        $this->assertStringContainsString('data-tree-preview-popup', $content);
        $this->assertStringContainsString('Wafat', $content);
        $this->assertStringContainsString('Hidup', $content);
        $this->assertMatchesRegularExpression('/data-total-kandung[^>]*>2</', $content);
        $this->assertMatchesRegularExpression('/data-total-mantu[^>]*>1</', $content);
        $this->assertMatchesRegularExpression('/data-total-hidup[^>]*>1</', $content);
        $this->assertMatchesRegularExpression('/data-total-wafat[^>]*>2</', $content);
        $this->assertMatchesRegularExpression('/data-total-keturunan[^>]*>3</', $content);
        $this->assertMatchesRegularExpression('/data-total-hidup-row[^>]*>1</', $content);
        $this->assertMatchesRegularExpression('/data-total-wafat-row[^>]*>2</', $content);
        $this->assertMatchesRegularExpression(
            '/data-generation-level="1"[\s\S]*data-generation-label>\\s*Anak\\s*<[\s\S]*data-generation-kandung>1<[\s\S]*data-generation-mantu>1<[\s\S]*data-generation-alive>1<[\s\S]*data-generation-deceased>1<[\s\S]*data-generation-total>2</',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/data-generation-level="2"[\s\S]*data-generation-label>\\s*Cucu\\s*<[\s\S]*data-generation-kandung>1<[\s\S]*data-generation-mantu>0<[\s\S]*data-generation-alive>0<[\s\S]*data-generation-deceased>1<[\s\S]*data-generation-total>1</',
            $content
        );
    }

    /** @test */
    public function non_admin_does_not_see_gedcom_import_link()
    {
        $user = $this->loginAsUser(['email' => 'member@example.com']);

        $this->visit(route('profile'));
        $this->dontSee('Import GEDCOM');
    }

    /** @test */
    public function non_admin_cannot_access_gedcom_import_route()
    {
        $user = $this->loginAsUser(['email' => 'member@example.com']);

        $this->get(route('gedcom.index'));
        $this->assertResponseStatus(403);
    }

    /** @test */
    public function logged_in_non_admin_can_open_public_edit_request_form()
    {
        $member = $this->loginAsUser(['email' => 'member@example.com']);
        $target = factory(User::class)->create();

        $this->visit(route('users.chart', $target))
            ->see('Usulkan Perubahan Data');

        $this->get(route('user-edit-requests.create', $target));
        $this->assertResponseOk();
        $this->see('Nama pengaju');
    }

    /** @test */
    public function public_edit_request_form_keeps_existing_female_gender_selected()
    {
        $target = factory(User::class)->states('female')->create();

        $this->get(route('user-edit-requests.create', $target));
        $this->assertResponseOk();
        $this->see('option value="2" selected');
        $this->dontSee('option value="1" selected');
    }

    /** @test */
    public function admin_cannot_open_public_edit_request_form()
    {
        config(['app.system_admin_emails' => 'admin@example.com']);

        $admin = $this->loginAsUser(['email' => 'admin@example.com']);
        $target = factory(User::class)->create(['manager_id' => $admin->id]);

        $this->get(route('user-edit-requests.create', $target));
        $this->assertResponseStatus(403);
    }

    /** @test */
    public function admin_can_quickly_toggle_deceased_status_from_profile_page()
    {
        config(['app.system_admin_emails' => 'admin@example.com']);

        $admin = $this->loginAsUser(['email' => 'admin@example.com']);
        $target = factory(User::class)->create(['manager_id' => $admin->id, 'is_deceased' => false]);

        $this->visit(route('users.show', $target))
            ->seeElement('input', ['name' => 'is_deceased', 'value' => '1'])
            ->see(trans('user.save_deceased_status'));

        $this->submitForm(trans('user.save_deceased_status'), [
            'is_deceased' => '1',
        ]);

        $this->seeInDatabase('users', [
            'id' => $target->id,
            'is_deceased' => true,
        ]);
    }

    /** @test */
    public function user_can_view_other_users_profile()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.show', $user->id));
        $this->see($user->name);
    }

    /** @test */
    public function user_will_see_edit_profile_if_an_invalid_tab_selected()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', [$user->id, 'tab' => 'invalid_tab']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'invalid_tab']));
        $this->seeElement('input', ['name' => 'nickname']);
        $this->seeElement('input', ['name' => 'name']);
    }

    /** @test */
    public function user_can_edit_profile()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', $user->id));
        $this->seePageIs(route('users.edit', $user->id));

        $this->submitForm(trans('app.update'), [
            'nickname' => 'Nama Panggilan',
            'name' => 'Nama User',
            'gender_id' => 1,
            'dob' => '1959-06-09',
            'yob' => '',
            'birth_order' => 3,
        ]);

        $this->seeInDatabase('users', [
            'nickname' => 'Nama Panggilan',
            'name' => 'Nama User',
            'gender_id' => 1,
            'dob' => '1959-06-09',
            'yob' => '1959',
            'birth_order' => 3,
        ]);
    }

    /** @test */
    public function scoped_user_cannot_open_edit_form_for_user_outside_current_tenant()
    {
        $user = $this->loginAsUser();
        $tenantRoot = factory(User::class)->states('male')->create();
        $outsideUser = factory(User::class)->create(['manager_id' => $user->id]);

        DomainFamilyScope::create([
            'host' => 'salam.bani.my.id',
            'core_user_id' => $tenantRoot->id,
            'is_active' => true,
        ]);

        $response = $this->scopedCall('salam.bani.my.id', 'GET', route('users.edit', $outsideUser, false));

        $this->assertSame(404, $response->getStatusCode());
    }

    /** @test */
    public function user_can_update_yob_only()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', $user->id));
        $this->seePageIs(route('users.edit', $user->id));

        $this->submitForm(trans('app.update'), [
            'dob' => '',
            'yob' => '2003',
        ]);

        $this->seeInDatabase('users', [
            'id' => $user->id,
            'dob' => null,
            'yob' => '2003',
        ]);
    }

    /** @test */
    public function user_can_edit_contact_address()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', [$user->id, 'tab' => 'contact_address']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'contact_address']));

        $this->submitForm(trans('app.update'), [
            'address' => 'Jln. Angkasa, No. 70',
            'city' => 'Nama Kota',
            'phone' => '081234567890',
        ]);

        $this->seeInDatabase('users', [
            'id' => $user->id,
            'address' => 'Jln. Angkasa, No. 70',
            'city' => 'Nama Kota',
            'phone' => '081234567890',
        ]);
    }

    private function scopedCall(string $host, string $method, string $uri, array $parameters = [], array $server = [])
    {
        $this->baseUrl = 'http://'.$host;
        config(['app.url' => 'http://'.$host]);
        url()->forceRootUrl('http://'.$host);

        return $this->call($method, $uri, $parameters, [], [], array_merge([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ], $server));
    }

    /** @test */
    public function editing_non_death_profile_fields_does_not_clear_deceased_status()
    {
        $user = $this->loginAsUser([
            'is_deceased' => true,
            'dob' => '1959-06-09',
            'yob' => '1959',
            'dod' => '2003-10-17',
            'yod' => '2003',
        ]);

        $this->visit(route('users.edit', [$user->id, 'tab' => 'contact_address']));
        $this->submitForm(trans('app.update'), [
            'address' => 'Alamat Baru',
            'city' => 'Kota Baru',
            'phone' => '081111111111',
        ]);

        $user->refresh();

        $this->assertTrue((bool) $user->is_deceased);
        $this->assertSame('1959-06-09', optional($user->dob)->format('Y-m-d'));
        $this->assertSame('1959', (string) $user->yob);
        $this->assertSame('2003-10-17', optional($user->dod)->format('Y-m-d'));
        $this->assertSame('2003', (string) $user->yod);
        $this->assertSame('Alamat Baru', $user->address);
        $this->assertSame('Kota Baru', $user->city);
        $this->assertSame('081111111111', $user->phone);
    }

    /** @test */
    public function user_can_edit_login_account()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', [$user->id, 'tab' => 'login_account']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'login_account']));

        $this->submitForm(trans('app.update'), [
            'email' => '',
            'password' => '',
        ]);

        $this->seeInDatabase('users', [
            'id' => $user->id,
            'email' => null,
            'password' => null,
        ]);
    }

    /** @test */
    public function user_can_edit_death()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', [$user->id, 'tab' => 'death']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'death']));

        $this->submitForm(trans('app.update'), [
            'dod' => '2003-10-17',
            'yod' => '',
        ]);

        $this->seeInDatabase('users', [
            'id' => $user->id,
            'dod' => '2003-10-17',
            'yod' => '2003',
        ]);
    }

    /** @test */
    public function user_can_update_yod_only()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', [$user->id, 'tab' => 'death']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'death']));

        $this->submitForm(trans('app.update'), [
            'dod' => '',
            'yod' => '2003',
        ]);

        $this->seeInDatabase('users', [
            'id' => $user->id,
            'dod' => null,
            'yod' => '2003',
        ]);
    }

    /** @test */
    public function user_can_update_died_person_cemetary_location()
    {
        $user = $this->loginAsUser();
        $this->visit(route('users.edit', [$user->id, 'tab' => 'death']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'death']));

        $this->submitForm(trans('app.update'), [
            'dod' => '',
            'yod' => '2003',
            'cemetery_location_name' => 'Some name',
            'cemetery_location_address' => 'Some address',
            'cemetery_location_latitude' => '-3.333333',
            'cemetery_location_longitude' => '114.583333',
        ]);

        $this->seeInDatabase('users', [
            'id' => $user->id,
            'dod' => null,
            'yod' => '2003',
        ]);

        $this->seeInDatabase('user_metadata', [
            'user_id' => $user->id,
            'key' => 'cemetery_location_name',
            'value' => 'Some name',
        ]);

        $this->seeInDatabase('user_metadata', [
            'user_id' => $user->id,
            'key' => 'cemetery_location_address',
            'value' => 'Some address',
        ]);

        $this->seeInDatabase('user_metadata', [
            'user_id' => $user->id,
            'key' => 'cemetery_location_latitude',
            'value' => '-3.333333',
        ]);

        $this->seeInDatabase('user_metadata', [
            'user_id' => $user->id,
            'key' => 'cemetery_location_longitude',
            'value' => '114.583333',
        ]);
    }

    /** @test */
    public function user_metadata_can_be_prefilled_on_the_edit_form()
    {
        $user = $this->loginAsUser();
        DB::table('user_metadata')->insert([
            'id' => Uuid::uuid4()->toString(),
            'user_id' => $user->id,
            'key' => 'cemetery_location_name',
            'value' => 'Some place name',
        ]);

        $this->visit(route('users.edit', [$user->id, 'tab' => 'death']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'death']));
        $this->seeElement('input', [
            'name' => 'cemetery_location_name',
            'value' => 'Some place name',
        ]);
    }

    /** @test */
    public function manager_can_add_login_account_on_a_user()
    {
        $manager = $this->loginAsUser();
        $user = factory(User::class)->create(['manager_id' => $manager->id]);
        $this->visit(route('users.edit', [$user->id, 'tab' => 'login_account']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'login_account']));

        $this->submitForm(trans('app.update'), [
            'email' => 'user@mail.com',
            'password' => 'Secr3t',
        ]);

        $user = $user->fresh();
        $this->assertEquals('user@mail.com', $user->email);
        $this->assertTrue(app('hash')->check('Secr3t', $user->password));
    }

    /** @test */
    public function manager_can_add_user_email_without_a_password()
    {
        $manager = $this->loginAsUser();
        $user = factory(User::class)->create(['manager_id' => $manager->id]);
        $this->visit(route('users.edit', [$user->id, 'tab' => 'login_account']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'login_account']));

        $this->submitForm(trans('app.update'), [
            'email' => 'user@mail.com',
            'password' => '',
        ]);

        $user = $user->fresh();
        $this->assertEquals('user@mail.com', $user->email);
        $this->assertNull($user->password);
    }

    /** @test */
    public function empty_password_does_not_replace_existing()
    {
        $manager = $this->loginAsUser();
        $user = factory(User::class)->create([
            'manager_id' => $manager->id,
            'password' => 'some random string password',
        ]);
        $this->visit(route('users.edit', [$user->id, 'tab' => 'login_account']));
        $this->seePageIs(route('users.edit', [$user->id, 'tab' => 'login_account']));

        $this->submitForm(trans('app.update'), [
            'email' => 'user@mail.com',
            'password' => '',
        ]);

        $this->seeInDatabase('users', [
            'id' => $user->id,
            'manager_id' => $manager->id,
            'password' => 'some random string password',
        ]);
    }

    /** @test */
    public function user_can_upload_their_own_photo()
    {
        Bus::fake();
        Storage::fake(config('filesystems.default'));

        $user = $this->loginAsUser();
        $this->visit(route('users.edit', $user->id));
        $this->assertNull($user->photo_path);

        $this->attach(public_path('images/icon_user_1.png'), 'photo');
        $this->press(trans('user.update_photo'));

        $this->seePageIs(route('users.edit', $user));

        $user = $user->fresh();

        $this->assertNotNull($user->photo_path);
        Bus::assertDispatched(OptimizeImages::class);
        Storage::assertExists($user->photo_path);
    }

    /** @test */
    public function user_profile_photo_upload_rejects_svg_files()
    {
        $user = $this->loginAsUser();
        $file = UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml');

        $this->call('PATCH', route('users.photo-upload', $user), [], [], ['photo' => $file]);
        $this->assertResponseStatus(302);
        $this->assertSessionHasErrors('photo');
    }

    /** @test */
    public function public_edit_request_photo_upload_rejects_svg_files()
    {
        $target = factory(User::class)->create();
        $file = UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml');

        $this->call('POST', route('user-edit-requests.store', $target), [
            'requester_name' => 'Pengusul',
            'requester_whatsapp' => '08123456789',
            'nickname' => $target->nickname,
            'gender_id' => $target->gender_id,
        ], [], ['photo' => $file], ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertResponseStatus(422);
        $this->seeJsonContains(['message' => 'Data usulan belum valid.']);
    }
}
