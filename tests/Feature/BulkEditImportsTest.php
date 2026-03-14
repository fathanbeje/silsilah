<?php

namespace Tests\Feature;

use App\BulkEditImport;
use App\BulkEditImportRow;
use App\Couple;
use App\DomainFamilyScope;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BulkEditImportsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function scoped_admin_can_upload_batch_workbook_and_review_generated_statuses()
    {
        config(['app.system_admin_emails' => 'admin@example.com']);
        $admin = $this->loginAsUser(['email' => 'admin@example.com']);

        [$core, $outsideUser] = $this->seedScopedUsers();
        $workbook = $this->makeWorkbook([
            'UPDATES_EXISTING' => [
                ['row_key', 'target_user_id', 'nickname', 'city', 'requester_name', 'requester_whatsapp'],
                ['upd-1', $core->id, 'CORE BARU', 'KOTA BARU', 'Pengusul', '08123'],
                ['upd-2', '', 'PERLU MAP', '', 'Pengusul', '08123'],
                ['upd-3', $outsideUser->id, 'LUAR SCOPE', '', 'Pengusul', '08123'],
            ],
            'NEW_SPOUSES' => [
                ['row_key', 'target_user_id', 'spouse_request_key', 'name', 'nickname'],
                ['sp-1', $core->id, 'spouse-1', 'PASANGAN BARU', 'PASANGAN BARU'],
            ],
            'NEW_CHILDREN' => [
                ['row_key', 'target_user_id', 'name', 'nickname', 'gender_id', 'spouse_context'],
                ['ch-1', $core->id, 'ANAK BARU', 'ANAK BARU', '1', 'new:spouse-1'],
            ],
            'NEW_STANDALONE' => [
                ['row_key', 'name', 'nickname', 'gender_id', 'requester_name', 'requester_whatsapp'],
                ['st-1', 'CALON ANGGOTA', 'CALON ANGGOTA', '1', 'Pengusul', '08123'],
            ],
        ]);

        $response = $this->scopedCall(
            'syamsuri.bani.my.id',
            'POST',
            route('bulk-edit-imports.store', [], false),
            [],
            [],
            ['workbook' => $workbook]
        );

        $this->assertSame(302, $response->getStatusCode());
        $import = BulkEditImport::query()->firstOrFail();
        $import->load('rows');

        $this->assertSame('syamsuri.bani.my.id', $import->tenant_host);
        $this->assertSame(3, $import->rows->where('status', BulkEditImportRow::STATUS_READY)->count());
        $this->assertSame(1, $import->rows->where('status', BulkEditImportRow::STATUS_NEEDS_MAPPING)->count());
        $this->assertSame(1, $import->rows->where('status', BulkEditImportRow::STATUS_BLOCKED)->count());
        $this->assertSame(1, $import->rows->where('status', BulkEditImportRow::STATUS_NEEDS_ANCHOR)->count());

        $this->assertSame(BulkEditImportRow::STATUS_READY, $import->rows->firstWhere('row_key', 'sp-1')->status);
        $this->assertSame(BulkEditImportRow::STATUS_READY, $import->rows->firstWhere('row_key', 'ch-1')->status);
    }

    /** @test */
    public function scoped_admin_can_approve_ready_rows_and_anchor_standalone_rows()
    {
        config(['app.system_admin_emails' => 'admin@example.com']);
        $admin = $this->loginAsUser(['email' => 'admin@example.com']);

        [$core] = $this->seedScopedUsers();
        $workbook = $this->makeWorkbook([
            'UPDATES_EXISTING' => [
                ['row_key', 'target_user_id', 'nickname', 'city', 'requester_name', 'requester_whatsapp'],
                ['upd-1', $core->id, 'CORE BARU', 'KOTA BARU', 'Pengusul', '08123'],
            ],
            'NEW_SPOUSES' => [
                ['row_key', 'target_user_id', 'spouse_request_key', 'name', 'nickname'],
                ['sp-1', $core->id, 'spouse-1', 'PASANGAN BARU', 'PASANGAN BARU'],
            ],
            'NEW_CHILDREN' => [
                ['row_key', 'target_user_id', 'name', 'nickname', 'gender_id', 'spouse_context'],
                ['ch-1', $core->id, 'ANAK BARU', 'ANAK BARU', '1', 'new:spouse-1'],
            ],
            'NEW_STANDALONE' => [
                ['row_key', 'name', 'nickname', 'gender_id', 'requester_name', 'requester_whatsapp'],
                ['st-1', 'CALON ANGGOTA', 'CALON ANGGOTA', '1', 'Pengusul', '08123'],
            ],
        ]);

        $this->scopedCall(
            'syamsuri.bani.my.id',
            'POST',
            route('bulk-edit-imports.store', [], false),
            [],
            [],
            ['workbook' => $workbook]
        );

        $import = BulkEditImport::query()->firstOrFail();
        $standaloneRow = BulkEditImportRow::query()->where('row_key', 'st-1')->firstOrFail();

        $this->scopedCall(
            'syamsuri.bani.my.id',
            'PATCH',
            route('bulk-edit-imports.rows.update', [$import, $standaloneRow], false),
            [
                'resolved_anchor_type' => 'user',
                'resolved_anchor_ref_id' => $core->id,
                'resolved_relation_action' => 'child',
            ]
        );

        $response = $this->scopedCall(
            'syamsuri.bani.my.id',
            'POST',
            route('bulk-edit-imports.approve-ready', $import, false)
        );

        $this->assertSame(302, $response->getStatusCode());
        $core->refresh();
        $updatedCore = User::findOrFail($core->id);
        $newSpouse = User::query()->where('name', 'PASANGAN BARU')->first();
        $newChild = User::query()->where('name', 'ANAK BARU')->first();
        $standalone = User::query()->where('name', 'CALON ANGGOTA')->first();
        $couple = Couple::query()->where(function ($query) use ($core, $newSpouse) {
            $query->where('husband_id', $core->id)->where('wife_id', $newSpouse->id);
        })->first();

        $this->assertSame('CORE BARU', $updatedCore->nickname);
        $this->assertSame('KOTA BARU', $updatedCore->city);
        $this->assertNotNull($newSpouse);
        $this->assertNotNull($couple);
        $this->assertSame($couple->id, $newChild->parent_id);
        $this->assertSame($core->id, $standalone->father_id);
        $this->assertSame(BulkEditImport::STATUS_COMPLETED, $import->fresh()->status);
    }

    /** @test */
    public function scoped_admin_can_download_template()
    {
        config(['app.system_admin_emails' => 'admin@example.com']);
        $this->loginAsUser(['email' => 'admin@example.com']);
        $this->seedScopedUsers();

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('bulk-edit-imports.template', [], false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    private function seedScopedUsers(): array
    {
        $core = factory(User::class)->states('male')->create([
            'name' => 'CORE BULK',
            'nickname' => 'CORE BULK',
        ]);
        $outsideUser = factory(User::class)->states('male')->create([
            'name' => 'LUAR BULK',
            'nickname' => 'LUAR BULK',
        ]);

        DomainFamilyScope::create([
            'host' => 'syamsuri.bani.my.id',
            'core_user_id' => $core->id,
            'is_active' => true,
        ]);

        return [$core, $outsideUser];
    }

    private function makeWorkbook(array $sheets): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $sheet->fromArray($rows);
        }

        $path = storage_path('app/test-bulk-'.uniqid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'bulk-test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function scopedCall(string $host, string $method, string $uri, array $parameters = [], array $server = [], array $files = [])
    {
        $this->baseUrl = 'http://'.$host;
        config(['app.url' => 'http://'.$host]);
        url()->forceRootUrl('http://'.$host);

        return $this->call($method, $uri, $parameters, [], $files, array_merge([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ], $server));
    }
}
