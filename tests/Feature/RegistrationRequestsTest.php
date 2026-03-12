<?php

namespace Tests\Feature;

use App\RegistrationRequest;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationRequestsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_can_submit_registration_request_with_password_when_birthdate_is_missing()
    {
        $user = factory(User::class)->create([
            'email' => null,
            'password' => null,
            'dob' => null,
        ]);

        $this->post(route('registration-requests.store', $user), [
            'request_email' => 'pemohon@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'requested_birth_date' => '1998-07-11',
            'notes' => 'Saya anak kandung.',
        ]);

        $request = RegistrationRequest::first();

        $this->assertNotNull($request);
        $this->assertSame('pemohon@example.com', $request->email);
        $this->assertSame('1998-07-11', optional($request->requested_birth_date)->format('Y-m-d'));
        $this->assertTrue(Hash::check('secret123', $request->password));
    }

    /** @test */
    public function admin_can_approve_registration_request_and_create_account()
    {
        $user = factory(User::class)->create([
            'email' => null,
            'password' => null,
            'dob' => null,
        ]);

        $request = RegistrationRequest::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => 'pemohon@example.com',
            'password' => Hash::make('secret123'),
            'requested_birth_date' => '1998-07-11',
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);

        config(['app.system_admin_emails' => 'admin@example.com']);
        $admin = $this->loginAsUser(['email' => 'admin@example.com']);

        $this->patch(route('registration-requests.update', $request), [
            'status' => RegistrationRequest::STATUS_REVIEWED,
            'approve_account' => '1',
        ]);

        $user->refresh();
        $request->refresh();

        $this->assertSame('pemohon@example.com', $user->email);
        $this->assertSame('1998-07-11', optional($user->dob)->format('Y-m-d'));
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertSame(RegistrationRequest::STATUS_REVIEWED, $request->status);
        $this->assertNull($request->password);
        $this->assertSame($admin->id, $request->reviewed_by);
    }
}
