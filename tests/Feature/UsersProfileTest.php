<?php

namespace Tests\Feature;

use App\Jobs\Images\OptimizeImages;
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
        $this->seeRouteIs('users.search', ['q' => 'jo']);

        $this->seeText('Jono');
        $this->seeText('Johan');
        $this->dontSeeText('Jeni');
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
