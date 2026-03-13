<?php

namespace Tests\Feature;

use App\DomainFamilyScope;
use App\RegistrationRequest;
use App\User;
use App\UserEditRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
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

    /** @test */
    public function scoped_guest_registration_request_is_tagged_with_current_domain_host()
    {
        $user = factory(User::class)->create([
            'email' => null,
            'password' => null,
            'dob' => null,
        ]);

        DomainFamilyScope::create([
            'host' => 'salam.bani.my.id',
            'core_user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->scopedCall('salam.bani.my.id', 'POST', route('registration-requests.store', $user, false), [
            'request_email' => 'pemohon@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(302);
        $this->assertSame('salam.bani.my.id', RegistrationRequest::first()->domain_host);
    }

    /** @test */
    public function scoped_admin_only_sees_registration_requests_for_current_tenant()
    {
        [$syamsuriCore, $salamCore] = $this->createTenantRoots();

        RegistrationRequest::create([
            'user_id' => $syamsuriCore->id,
            'name' => $syamsuriCore->name,
            'email' => 'syamsuri@example.com',
            'status' => RegistrationRequest::STATUS_PENDING,
            'domain_host' => 'syamsuri.bani.my.id',
        ]);

        RegistrationRequest::create([
            'user_id' => $salamCore->id,
            'name' => $salamCore->name,
            'email' => 'salam@example.com',
            'status' => RegistrationRequest::STATUS_PENDING,
            'domain_host' => 'salam.bani.my.id',
        ]);

        config(['app.system_admin_emails' => 'admin@example.com']);
        $this->loginAsUser(['email' => 'admin@example.com']);

        $response = $this->scopedCall('salam.bani.my.id', 'GET', route('registration-requests.index', [], false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('salam@example.com', $response->getContent());
        $this->assertStringNotContainsString('syamsuri@example.com', $response->getContent());
    }

    /** @test */
    public function scoped_admin_only_sees_user_edit_requests_for_current_tenant()
    {
        [$syamsuriCore, $salamCore] = $this->createTenantRoots();

        UserEditRequest::create([
            'id' => Uuid::uuid4()->toString(),
            'target_user_id' => $syamsuriCore->id,
            'requester_name' => 'Pemohon Syamsuri',
            'requester_whatsapp' => '08123',
            'domain_host' => 'syamsuri.bani.my.id',
            'status' => UserEditRequest::STATUS_PENDING,
        ]);

        UserEditRequest::create([
            'id' => Uuid::uuid4()->toString(),
            'target_user_id' => $salamCore->id,
            'requester_name' => 'Pemohon Salam',
            'requester_whatsapp' => '08124',
            'domain_host' => 'salam.bani.my.id',
            'status' => UserEditRequest::STATUS_PENDING,
        ]);

        config(['app.system_admin_emails' => 'admin@example.com']);
        $this->loginAsUser(['email' => 'admin@example.com']);

        $response = $this->scopedCall('salam.bani.my.id', 'GET', route('user-edit-requests.index', [], false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Pemohon Salam', $response->getContent());
        $this->assertStringNotContainsString('Pemohon Syamsuri', $response->getContent());
    }

    private function createTenantRoots(): array
    {
        $syamsuriCore = factory(User::class)->states('male')->create([
            'name' => 'CORE SYAMSURI',
            'nickname' => 'CORE SYAMSURI',
        ]);

        $salamCore = factory(User::class)->states('male')->create([
            'name' => 'CORE SALAM',
            'nickname' => 'CORE SALAM',
        ]);

        DomainFamilyScope::create([
            'host' => 'syamsuri.bani.my.id',
            'core_user_id' => $syamsuriCore->id,
            'is_active' => true,
        ]);

        DomainFamilyScope::create([
            'host' => 'salam.bani.my.id',
            'core_user_id' => $salamCore->id,
            'is_active' => true,
        ]);

        return [$syamsuriCore, $salamCore];
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
}
