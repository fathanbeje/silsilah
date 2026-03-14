<?php

namespace App\Services;

use App\BulkEditImport;
use App\BulkEditImportRow;
use App\Couple;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Ramsey\Uuid\Uuid;

class BulkEditImportService
{
    public const SHEET_README = 'README';
    public const SHEET_REFERENCE_USERS = 'REFERENCE_USERS';
    public const SHEET_REFERENCE_COUPLES = 'REFERENCE_COUPLES';
    public const SHEET_UPDATES = 'UPDATES_EXISTING';
    public const SHEET_SPOUSES = 'NEW_SPOUSES';
    public const SHEET_CHILDREN = 'NEW_CHILDREN';
    public const SHEET_STANDALONE = 'NEW_STANDALONE';

    private ?Collection $visibleUsers = null;
    private ?Collection $visibleCouples = null;

    public function __construct(
        private FamilyScopeResolver $familyScopeResolver,
        private ApplyUserEditRequest $applyUserEditRequest
    ) {
    }

    public function createTemplateFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->buildReadmeSheet($spreadsheet);
        $this->buildReferenceUsersSheet($spreadsheet);
        $this->buildReferenceCouplesSheet($spreadsheet);
        $this->buildUpdatesSheet($spreadsheet);
        $this->buildSpousesSheet($spreadsheet);
        $this->buildChildrenSheet($spreadsheet);
        $this->buildStandaloneSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndexByName(self::SHEET_README);

        $path = storage_path('app/bulk-edit-template-'.Str::uuid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function createImportFromUpload(UploadedFile $uploadedFile, User $uploadedBy): BulkEditImport
    {
        $spreadsheet = IOFactory::load($uploadedFile->getRealPath());

        return DB::transaction(function () use ($spreadsheet, $uploadedFile, $uploadedBy) {
            $import = BulkEditImport::create([
                'id' => Uuid::uuid4()->toString(),
                'tenant_host' => $this->familyScopeResolver->currentHost(),
                'source_type' => 'xlsx_upload',
                'source_name' => $uploadedFile->getClientOriginalName(),
                'uploaded_by' => $uploadedBy->id,
                'status' => BulkEditImport::STATUS_REVIEWING,
            ]);

            foreach ($this->parseWorkbook($spreadsheet) as $rowData) {
                $import->rows()->create($rowData);
            }

            $this->markDuplicateRows($import);

            return $this->refreshImport($import);
        });
    }

    public function refreshImport(BulkEditImport $import): BulkEditImport
    {
        $import->loadMissing('rows.import');

        foreach ($import->rows as $row) {
            if (in_array($row->status, [
                BulkEditImportRow::STATUS_APPROVED,
                BulkEditImportRow::STATUS_REJECTED,
                BulkEditImportRow::STATUS_DUPLICATE,
                BulkEditImportRow::STATUS_INVALID,
            ], true)) {
                continue;
            }

            $this->reevaluateRow($row);
        }

        $summary = [];
        foreach ([
            BulkEditImportRow::STATUS_READY,
            BulkEditImportRow::STATUS_NEEDS_MAPPING,
            BulkEditImportRow::STATUS_NEEDS_ANCHOR,
            BulkEditImportRow::STATUS_BLOCKED,
            BulkEditImportRow::STATUS_DUPLICATE,
            BulkEditImportRow::STATUS_INVALID,
            BulkEditImportRow::STATUS_APPROVED,
            BulkEditImportRow::STATUS_REJECTED,
        ] as $status) {
            $summary[$status] = $import->rows()->where('status', $status)->count();
        }

        $summary['total'] = $import->rows()->count();
        $remaining = $summary[BulkEditImportRow::STATUS_READY]
            + $summary[BulkEditImportRow::STATUS_NEEDS_MAPPING]
            + $summary[BulkEditImportRow::STATUS_NEEDS_ANCHOR]
            + $summary[BulkEditImportRow::STATUS_BLOCKED]
            + $summary[BulkEditImportRow::STATUS_DUPLICATE]
            + $summary[BulkEditImportRow::STATUS_INVALID];

        $import->summary_json = $summary;
        $import->status = $remaining === 0
            ? BulkEditImport::STATUS_COMPLETED
            : (($summary[BulkEditImportRow::STATUS_APPROVED] > 0 || $summary[BulkEditImportRow::STATUS_REJECTED] > 0)
                ? BulkEditImport::STATUS_PARTIALLY_APPLIED
                : BulkEditImport::STATUS_REVIEWING);
        $import->save();

        return $import->fresh(['uploader', 'rows.targetUser', 'rows.reviewer']);
    }

    public function updateRowResolution(BulkEditImportRow $row, array $attributes): BulkEditImportRow
    {
        $resolution = $row->resolution_json ?? [];

        foreach (['resolved_target_user_id', 'resolved_anchor_type', 'resolved_anchor_ref_id', 'resolved_relation_action'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $resolution[$key] = $this->cleanString($attributes[$key]);
            }
        }

        if (! empty($attributes['resolved_target_user_id'])) {
            $row->target_user_id = $attributes['resolved_target_user_id'];
        }

        $row->resolution_json = $resolution;
        $row->save();

        $this->refreshImport($row->import()->firstOrFail());

        return $row->fresh(['targetUser', 'reviewer']);
    }

    public function rejectRow(BulkEditImportRow $row, User $reviewer, ?string $reviewNotes = null): BulkEditImportRow
    {
        $row->status = BulkEditImportRow::STATUS_REJECTED;
        $row->reviewed_at = Carbon::now();
        $row->reviewed_by = $reviewer->id;
        $row->review_notes = $reviewNotes;
        $row->save();

        $this->refreshImport($row->import()->firstOrFail());

        return $row->fresh(['targetUser', 'reviewer']);
    }

    public function approveRow(BulkEditImportRow $row, User $reviewer, ?string $reviewNotes = null): BulkEditImportRow
    {
        return DB::transaction(function () use ($row, $reviewer, $reviewNotes) {
            $row = BulkEditImportRow::query()->with('import.rows')->lockForUpdate()->findOrFail($row->id);
            $this->reevaluateRow($row);

            if (! $row->isReady()) {
                throw new \RuntimeException('Baris ini belum siap di-approve.');
            }

            $result = match ($row->row_type) {
                BulkEditImportRow::TYPE_EXISTING => $this->approveExistingRow($row, $reviewer, $reviewNotes),
                BulkEditImportRow::TYPE_SPOUSE => $this->approveSpouseRow($row, $reviewer, $reviewNotes),
                BulkEditImportRow::TYPE_CHILD => $this->approveChildRow($row, $reviewer, $reviewNotes),
                BulkEditImportRow::TYPE_STANDALONE => $this->approveStandaloneRow($row, $reviewer, $reviewNotes),
                default => throw new \RuntimeException('Jenis row tidak dikenali.'),
            };

            $row->status = BulkEditImportRow::STATUS_APPROVED;
            $row->reviewed_at = Carbon::now();
            $row->reviewed_by = $reviewer->id;
            $row->review_notes = $reviewNotes;
            $row->resolution_json = array_merge($row->resolution_json ?? [], $result['resolution'] ?? []);
            $row->save();

            $this->refreshImport($row->import()->firstOrFail());

            return $row->fresh(['targetUser', 'reviewer']);
        });
    }

    public function approveReadyRows(BulkEditImport $import, User $reviewer): int
    {
        $import = $this->refreshImport($import);
        $rows = $import->rows
            ->where('status', BulkEditImportRow::STATUS_READY)
            ->sortBy(function (BulkEditImportRow $row) {
                return match ($row->row_type) {
                    BulkEditImportRow::TYPE_EXISTING => 1,
                    BulkEditImportRow::TYPE_SPOUSE => 2,
                    BulkEditImportRow::TYPE_CHILD => 3,
                    BulkEditImportRow::TYPE_STANDALONE => 4,
                    default => 9,
                };
            })
            ->values();

        $count = 0;
        foreach ($rows as $row) {
            $this->approveRow($row, $reviewer);
            $count++;
        }

        return $count;
    }

    public function visibleUserOptions(): array
    {
        return $this->visibleUsers()->map(fn (User $user) => [
            'id' => $user->id,
            'label' => $user->display_name.' ['.$user->id.']',
        ])->all();
    }

    public function visibleCoupleOptions(): array
    {
        return $this->visibleCouples()->map(fn (Couple $couple) => [
            'id' => $couple->id,
            'label' => $this->coupleLabel($couple).' ['.$couple->id.']',
        ])->all();
    }

    private function approveExistingRow(BulkEditImportRow $row, User $reviewer, ?string $reviewNotes): array
    {
        $payload = [
            'proposed_profile' => $row->payload_json['profile'] ?? [],
            'proposed_metadata' => $row->payload_json['metadata'] ?? [],
            'proposed_new_spouses' => [],
            'proposed_new_children' => [],
            'proposed_photo_path' => null,
        ];

        $this->applyUserEditRequest->applyPayload($this->resolvedTargetUser($row), $payload, $reviewer, $reviewNotes);

        return ['resolution' => []];
    }

    private function approveSpouseRow(BulkEditImportRow $row, User $reviewer, ?string $reviewNotes): array
    {
        $payload = [
            'proposed_profile' => [],
            'proposed_metadata' => [],
            'proposed_new_spouses' => [[
                'request_key' => $row->payload_json['spouse_request_key'],
                'name' => $row->payload_json['name'],
                'nickname' => $row->payload_json['nickname'],
                'dob' => $row->payload_json['dob'],
                'yob' => $row->payload_json['yob'],
                'marriage_date' => $row->payload_json['marriage_date'],
            ]],
            'proposed_new_children' => [],
            'proposed_photo_path' => null,
        ];

        $result = $this->applyUserEditRequest->applyPayload($this->resolvedTargetUser($row), $payload, $reviewer, $reviewNotes);

        return ['resolution' => [
            'approved_couple_id' => $result['created_couple_ids'][0] ?? null,
            'approved_user_id' => $result['created_user_ids'][0] ?? null,
        ]];
    }

    private function approveChildRow(BulkEditImportRow $row, User $reviewer, ?string $reviewNotes): array
    {
        $payload = [
            'proposed_profile' => [],
            'proposed_metadata' => [],
            'proposed_new_spouses' => [],
            'proposed_new_children' => [[
                'name' => $row->payload_json['name'],
                'nickname' => $row->payload_json['nickname'],
                'gender_id' => (int) $row->payload_json['gender_id'],
                'birth_order' => $row->payload_json['birth_order'],
                'dob' => $row->payload_json['dob'],
                'yob' => $row->payload_json['yob'],
                'spouse_context' => $this->resolvedSpouseContext($row, $reviewer),
            ]],
            'proposed_photo_path' => null,
        ];

        $result = $this->applyUserEditRequest->applyPayload($this->resolvedTargetUser($row), $payload, $reviewer, $reviewNotes);

        return ['resolution' => [
            'approved_user_id' => $result['created_user_ids'][0] ?? null,
        ]];
    }

    private function approveStandaloneRow(BulkEditImportRow $row, User $reviewer, ?string $reviewNotes): array
    {
        [$targetUser, $payload] = $this->buildStandaloneApprovalPayload($row);
        $result = $this->applyUserEditRequest->applyPayload($targetUser, $payload, $reviewer, $reviewNotes);

        return ['resolution' => [
            'approved_user_id' => $result['created_user_ids'][0] ?? null,
            'approved_couple_id' => $result['created_couple_ids'][0] ?? null,
        ]];
    }

    private function parseWorkbook(Spreadsheet $spreadsheet): array
    {
        $rows = [];

        foreach ([
            self::SHEET_UPDATES => BulkEditImportRow::TYPE_EXISTING,
            self::SHEET_SPOUSES => BulkEditImportRow::TYPE_SPOUSE,
            self::SHEET_CHILDREN => BulkEditImportRow::TYPE_CHILD,
            self::SHEET_STANDALONE => BulkEditImportRow::TYPE_STANDALONE,
        ] as $sheetName => $rowType) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                continue;
            }

            $matrix = $sheet->toArray('', true, true, false);
            if (empty($matrix)) {
                continue;
            }

            $headers = array_map(fn ($value) => trim((string) $value), $matrix[0]);
            foreach (array_slice($matrix, 1, null, true) as $index => $cells) {
                $rowNumber = $index + 1;
                $rowValues = [];
                foreach ($headers as $position => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $rowValues[$header] = array_key_exists($position, $cells) ? $cells[$position] : null;
                }

                if ($this->isEmptyRow($rowValues)) {
                    continue;
                }

                $rows[] = $this->normalizeParsedRow($sheetName, $rowType, $rowNumber, $rowValues);
            }
        }

        return $rows;
    }

    private function normalizeParsedRow(string $sheetName, string $rowType, int $rowNumber, array $rowValues): array
    {
        $errors = [];
        $status = BulkEditImportRow::STATUS_READY;
        $rowKey = $this->cleanString($rowValues['row_key'] ?? null);
        $targetUserId = $this->cleanString($rowValues['target_user_id'] ?? null);
        $resolution = [];
        $payload = [];

        if (! $rowKey) {
            $errors[] = 'row_key wajib diisi.';
            $status = BulkEditImportRow::STATUS_INVALID;
        }

        if ($rowType === BulkEditImportRow::TYPE_EXISTING) {
            $payload = [
                'profile' => $this->collectExistingProfile($rowValues, $errors),
                'metadata' => $this->collectMetadata($rowValues),
                'requester_name' => $this->cleanString($rowValues['requester_name'] ?? null),
                'requester_whatsapp' => $this->cleanString($rowValues['requester_whatsapp'] ?? null),
                'notes' => $this->cleanString($rowValues['notes'] ?? null),
            ];

            if (empty($payload['profile']) && empty($payload['metadata'])) {
                $errors[] = 'Tidak ada perubahan profil atau metadata.';
                $status = BulkEditImportRow::STATUS_INVALID;
            }
        }

        if ($rowType === BulkEditImportRow::TYPE_SPOUSE) {
            $payload = [
                'target_user_id_raw' => $targetUserId,
                'spouse_request_key' => $this->cleanString($rowValues['spouse_request_key'] ?? null),
                'name' => $this->normalizeUpperString($rowValues['name'] ?? null),
                'nickname' => $this->normalizeUpperString($rowValues['nickname'] ?? null),
                'dob' => $this->parseDate($rowValues['dob'] ?? null, 'dob', $errors),
                'yob' => $this->parseYear($rowValues['yob'] ?? null, 'yob', $errors),
                'marriage_date' => $this->parseDate($rowValues['marriage_date'] ?? null, 'marriage_date', $errors),
            ];
            if (! $payload['nickname']) {
                $payload['nickname'] = $payload['name'];
            }
            foreach (['spouse_request_key', 'name'] as $requiredField) {
                if (empty($payload[$requiredField])) {
                    $errors[] = $requiredField.' wajib diisi.';
                }
            }
        }

        if ($rowType === BulkEditImportRow::TYPE_CHILD) {
            $payload = [
                'target_user_id_raw' => $targetUserId,
                'name' => $this->normalizeUpperString($rowValues['name'] ?? null),
                'nickname' => $this->normalizeUpperString($rowValues['nickname'] ?? null),
                'gender_id' => $this->parseGender($rowValues['gender_id'] ?? null, $errors),
                'birth_order' => $this->parsePositiveInteger($rowValues['birth_order'] ?? null, 'birth_order', $errors),
                'dob' => $this->parseDate($rowValues['dob'] ?? null, 'dob', $errors),
                'yob' => $this->parseYear($rowValues['yob'] ?? null, 'yob', $errors),
                'spouse_context' => $this->cleanString($rowValues['spouse_context'] ?? null) ?: 'none',
            ];
            if (! $payload['nickname']) {
                $payload['nickname'] = $payload['name'];
            }
            foreach (['name', 'gender_id'] as $requiredField) {
                if (empty($payload[$requiredField])) {
                    $errors[] = $requiredField.' wajib diisi.';
                }
            }
            if (! $this->isValidSpouseContext($payload['spouse_context'])) {
                $errors[] = 'spouse_context tidak valid.';
            }
        }

        if ($rowType === BulkEditImportRow::TYPE_STANDALONE) {
            $payload = [
                'profile' => $this->collectStandaloneProfile($rowValues, $errors),
                'metadata' => $this->collectMetadata($rowValues),
                'requester_name' => $this->cleanString($rowValues['requester_name'] ?? null),
                'requester_whatsapp' => $this->cleanString($rowValues['requester_whatsapp'] ?? null),
                'photo_link' => $this->cleanString($rowValues['photo_link'] ?? null),
                'photo_note' => $this->cleanString($rowValues['photo_note'] ?? null),
            ];
            if (empty($payload['requester_name']) || empty($payload['requester_whatsapp'])) {
                $errors[] = 'requester_name dan requester_whatsapp wajib diisi.';
            }
            $resolution['suggested_anchor_type'] = $this->cleanString($rowValues['anchor_type'] ?? null);
            $resolution['suggested_anchor_ref_id'] = $this->cleanString($rowValues['anchor_ref_id'] ?? null);
            $status = BulkEditImportRow::STATUS_NEEDS_ANCHOR;
        }

        if (in_array($rowType, [BulkEditImportRow::TYPE_EXISTING, BulkEditImportRow::TYPE_SPOUSE, BulkEditImportRow::TYPE_CHILD], true)) {
            if (! $targetUserId) {
                $status = BulkEditImportRow::STATUS_NEEDS_MAPPING;
                $errors[] = 'target_user_id belum diisi.';
            } elseif (! $this->visibleUsers()->keyBy('id')->has($targetUserId)) {
                $status = BulkEditImportRow::STATUS_BLOCKED;
                $errors[] = 'target_user_id tidak valid atau di luar tenant aktif.';
            } else {
                $resolution['resolved_target_user_id'] = $targetUserId;
            }
        }

        if (! empty($errors) && ! in_array($status, [BulkEditImportRow::STATUS_NEEDS_MAPPING, BulkEditImportRow::STATUS_NEEDS_ANCHOR, BulkEditImportRow::STATUS_BLOCKED], true)) {
            $status = BulkEditImportRow::STATUS_INVALID;
        }

        return [
            'id' => Uuid::uuid4()->toString(),
            'sheet_name' => $sheetName,
            'row_number' => $rowNumber,
            'row_key' => $rowKey,
            'row_type' => $rowType,
            'target_user_id' => $targetUserId,
            'payload_json' => $payload,
            'normalized_json' => ['sheet_name' => $sheetName, 'row_type' => $rowType],
            'resolution_json' => $resolution,
            'status' => $status,
            'error_messages_json' => array_values(array_unique(array_filter($errors))),
        ];
    }

    private function reevaluateRow(BulkEditImportRow $row): void
    {
        $errors = [];
        $resolution = $row->resolution_json ?? [];
        $status = $row->status;

        if (in_array($row->row_type, [BulkEditImportRow::TYPE_EXISTING, BulkEditImportRow::TYPE_SPOUSE, BulkEditImportRow::TYPE_CHILD], true)) {
            $resolvedTarget = $this->cleanString($resolution['resolved_target_user_id'] ?? $row->target_user_id);
            if (! $resolvedTarget) {
                $status = BulkEditImportRow::STATUS_NEEDS_MAPPING;
                $errors[] = 'Row ini masih butuh pemetaan target user.';
            } elseif (! $this->visibleUsers()->keyBy('id')->has($resolvedTarget)) {
                $status = BulkEditImportRow::STATUS_BLOCKED;
                $errors[] = 'Target user di luar tenant aktif atau tidak valid.';
            } else {
                $status = BulkEditImportRow::STATUS_READY;
                $resolution['resolved_target_user_id'] = $resolvedTarget;
                $row->target_user_id = $resolvedTarget;
            }
        }

        if ($row->row_type === BulkEditImportRow::TYPE_CHILD && $status === BulkEditImportRow::STATUS_READY) {
            $errors = array_merge($errors, $this->validateChildRowContext($row, $resolution));
            if (! empty($errors)) {
                $status = BulkEditImportRow::STATUS_BLOCKED;
            }
        }

        if ($row->row_type === BulkEditImportRow::TYPE_STANDALONE) {
            $status = $this->evaluateStandaloneStatus($row, $resolution, $errors);
        }

        $row->resolution_json = $resolution;
        $row->status = $status;
        $row->error_messages_json = array_values(array_unique(array_filter($errors)));
        $row->save();
    }

    private function evaluateStandaloneStatus(BulkEditImportRow $row, array &$resolution, array &$errors): string
    {
        $anchorType = $this->cleanString($resolution['resolved_anchor_type'] ?? $resolution['suggested_anchor_type'] ?? null);
        $anchorRefId = $this->cleanString($resolution['resolved_anchor_ref_id'] ?? $resolution['suggested_anchor_ref_id'] ?? null);
        $relationAction = $this->cleanString($resolution['resolved_relation_action'] ?? null);

        if (! $anchorType || ! $anchorRefId || ! $relationAction) {
            $errors[] = 'Row standalone masih butuh anchor dan aksi relasi.';

            return BulkEditImportRow::STATUS_NEEDS_ANCHOR;
        }

        if (! in_array($relationAction, ['child', 'spouse'], true)) {
            $errors[] = 'Aksi relasi standalone tidak valid.';

            return BulkEditImportRow::STATUS_INVALID;
        }

        if ($anchorType === 'user') {
            $anchorUser = $this->visibleUsers()->keyBy('id')->get($anchorRefId);
            if (! $anchorUser) {
                $errors[] = 'Anchor user tidak valid atau di luar tenant aktif.';

                return BulkEditImportRow::STATUS_BLOCKED;
            }

            if (
                $relationAction === 'spouse'
                && (int) $anchorUser->gender_id === (int) ($row->payload_json['profile']['gender_id'] ?? 0)
            ) {
                $errors[] = 'Jenis kelamin pasangan baru harus berbeda dari anchor user.';

                return BulkEditImportRow::STATUS_BLOCKED;
            }
        } elseif ($anchorType === 'couple') {
            $anchorCouple = $this->visibleCouples()->keyBy('id')->get($anchorRefId);
            if (! $anchorCouple) {
                $errors[] = 'Anchor couple tidak valid atau di luar tenant aktif.';

                return BulkEditImportRow::STATUS_BLOCKED;
            }

            if ($relationAction !== 'child') {
                $errors[] = 'Anchor couple hanya bisa dipakai untuk aksi child.';

                return BulkEditImportRow::STATUS_BLOCKED;
            }
        } else {
            $errors[] = 'Anchor type tidak valid.';

            return BulkEditImportRow::STATUS_INVALID;
        }

        $resolution['resolved_anchor_type'] = $anchorType;
        $resolution['resolved_anchor_ref_id'] = $anchorRefId;
        $resolution['resolved_relation_action'] = $relationAction;

        return BulkEditImportRow::STATUS_READY;
    }

    private function validateChildRowContext(BulkEditImportRow $row, array $resolution): array
    {
        $spouseContext = $row->payload_json['spouse_context'] ?? 'none';
        if ($spouseContext === 'none') {
            return [];
        }

        if (str_starts_with($spouseContext, 'existing:')) {
            $coupleId = substr($spouseContext, 9);
            $couple = $this->visibleCouples()->keyBy('id')->get($coupleId);
            if (! $couple) {
                return ['Couple pada spouse_context tidak valid atau di luar tenant aktif.'];
            }

            $targetUserId = $resolution['resolved_target_user_id'] ?? null;
            if ($targetUserId && ! in_array($targetUserId, [$couple->husband_id, $couple->wife_id], true)) {
                return ['spouse_context existing tidak milik target user yang dipilih.'];
            }

            return [];
        }

        if (str_starts_with($spouseContext, 'new:')) {
            $spouseRow = $this->resolveSpouseRowForChild($row, substr($spouseContext, 4));
            if (! $spouseRow) {
                return ['spouse_context new tidak menemukan pasangan baru pada batch ini.'];
            }
        }

        return [];
    }

    private function resolvedTargetUser(BulkEditImportRow $row): User
    {
        $targetUserId = $row->resolution_json['resolved_target_user_id'] ?? $row->target_user_id;
        $targetUser = $this->visibleUsers()->keyBy('id')->get($targetUserId);
        if (! $targetUser) {
            throw new \RuntimeException('Target user tidak valid.');
        }

        return $targetUser;
    }

    private function resolvedSpouseContext(BulkEditImportRow $row, User $reviewer): string
    {
        $spouseContext = $row->payload_json['spouse_context'] ?? 'none';
        if (! str_starts_with($spouseContext, 'new:')) {
            return $spouseContext;
        }

        $spouseRow = $this->resolveSpouseRowForChild($row, substr($spouseContext, 4));
        if (! $spouseRow) {
            throw new \RuntimeException('Pasangan baru yang dirujuk tidak ditemukan.');
        }

        if ($spouseRow->status !== BulkEditImportRow::STATUS_APPROVED) {
            $spouseRow = $this->approveRow($spouseRow, $reviewer);
        }

        $approvedCoupleId = $spouseRow->resolution_json['approved_couple_id'] ?? null;
        if (! $approvedCoupleId) {
            throw new \RuntimeException('Pasangan baru yang dirujuk belum menghasilkan couple aktif.');
        }

        return 'existing:'.$approvedCoupleId;
    }

    private function resolveSpouseRowForChild(BulkEditImportRow $row, string $requestKey): ?BulkEditImportRow
    {
        $import = $row->relationLoaded('import') ? $row->import : $row->import()->with('rows')->first();
        $targetUserId = $row->resolution_json['resolved_target_user_id'] ?? $row->target_user_id;

        return $import->rows->first(function (BulkEditImportRow $candidate) use ($requestKey, $targetUserId) {
            $candidateTarget = $candidate->resolution_json['resolved_target_user_id'] ?? $candidate->target_user_id;

            return $candidate->row_type === BulkEditImportRow::TYPE_SPOUSE
                && ($candidate->payload_json['spouse_request_key'] ?? null) === $requestKey
                && $candidateTarget === $targetUserId;
        });
    }

    private function buildStandaloneApprovalPayload(BulkEditImportRow $row): array
    {
        $resolution = $row->resolution_json ?? [];
        $profile = $row->payload_json['profile'] ?? [];
        $metadata = $row->payload_json['metadata'] ?? [];

        if (($resolution['resolved_anchor_type'] ?? null) === 'user' && ($resolution['resolved_relation_action'] ?? null) === 'spouse') {
            $targetUser = $this->visibleUsers()->keyBy('id')->get($resolution['resolved_anchor_ref_id']);
            if (! $targetUser) {
                throw new \RuntimeException('Anchor user tidak valid.');
            }

            return [$targetUser, [
                'proposed_profile' => [],
                'proposed_metadata' => [],
                'proposed_new_spouses' => [[
                    'request_key' => 'standalone_'.$row->id,
                    'name' => $profile['name'],
                    'nickname' => $profile['nickname'],
                    'dob' => $profile['dob'] ?? null,
                    'yob' => $profile['yob'] ?? null,
                    'phone' => $profile['phone'] ?? null,
                    'address' => $profile['address'] ?? null,
                    'city' => $profile['city'] ?? null,
                    'dod' => $profile['dod'] ?? null,
                    'yod' => $profile['yod'] ?? null,
                    'is_deceased' => $profile['is_deceased'] ?? false,
                    'metadata' => $metadata,
                ]],
                'proposed_new_children' => [],
                'proposed_photo_path' => null,
            ]];
        }

        if (($resolution['resolved_anchor_type'] ?? null) === 'user' && ($resolution['resolved_relation_action'] ?? null) === 'child') {
            $targetUser = $this->visibleUsers()->keyBy('id')->get($resolution['resolved_anchor_ref_id']);
            if (! $targetUser) {
                throw new \RuntimeException('Anchor user tidak valid.');
            }

            return [$targetUser, $this->buildStandaloneChildPayload($profile, $metadata, 'none')];
        }

        if (($resolution['resolved_anchor_type'] ?? null) === 'couple' && ($resolution['resolved_relation_action'] ?? null) === 'child') {
            $couple = $this->visibleCouples()->keyBy('id')->get($resolution['resolved_anchor_ref_id']);
            if (! $couple) {
                throw new \RuntimeException('Anchor couple tidak valid.');
            }

            $targetUser = $couple->husband ?: $couple->wife;
            if (! $targetUser) {
                throw new \RuntimeException('Anchor couple tidak memiliki pasangan aktif.');
            }

            return [$targetUser, $this->buildStandaloneChildPayload($profile, $metadata, 'existing:'.$couple->id)];
        }

        throw new \RuntimeException('Resolusi standalone belum lengkap.');
    }

    private function buildStandaloneChildPayload(array $profile, array $metadata, string $spouseContext): array
    {
        return [
            'proposed_profile' => [],
            'proposed_metadata' => [],
            'proposed_new_spouses' => [],
            'proposed_new_children' => [[
                'name' => $profile['name'],
                'nickname' => $profile['nickname'],
                'gender_id' => (int) $profile['gender_id'],
                'birth_order' => null,
                'dob' => $profile['dob'] ?? null,
                'yob' => $profile['yob'] ?? null,
                'phone' => $profile['phone'] ?? null,
                'address' => $profile['address'] ?? null,
                'city' => $profile['city'] ?? null,
                'dod' => $profile['dod'] ?? null,
                'yod' => $profile['yod'] ?? null,
                'is_deceased' => $profile['is_deceased'] ?? false,
                'metadata' => $metadata,
                'spouse_context' => $spouseContext,
            ]],
            'proposed_photo_path' => null,
        ];
    }

    private function markDuplicateRows(BulkEditImport $import): void
    {
        $grouped = $import->rows()->get()->groupBy(fn (BulkEditImportRow $row) => $row->sheet_name.'|'.$row->row_key);
        foreach ($grouped as $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            foreach ($rows as $row) {
                $errors = $row->error_messages_json ?? [];
                $errors[] = 'row_key duplikat pada sheet yang sama.';
                $row->status = BulkEditImportRow::STATUS_DUPLICATE;
                $row->error_messages_json = array_values(array_unique($errors));
                $row->save();
            }
        }

        $spouseGroups = $import->rows()->where('row_type', BulkEditImportRow::TYPE_SPOUSE)->get()
            ->groupBy(function (BulkEditImportRow $row) {
                return ($row->target_user_id ?: ($row->payload_json['target_user_id_raw'] ?? ''))
                    .'|'.($row->payload_json['spouse_request_key'] ?? '');
            });

        foreach ($spouseGroups as $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            foreach ($rows as $row) {
                $errors = $row->error_messages_json ?? [];
                $errors[] = 'spouse_request_key duplikat untuk target yang sama.';
                $row->status = BulkEditImportRow::STATUS_DUPLICATE;
                $row->error_messages_json = array_values(array_unique($errors));
                $row->save();
            }
        }
    }

    private function visibleUsers(): Collection
    {
        if ($this->visibleUsers !== null) {
            return $this->visibleUsers;
        }

        $query = User::query()->with(['father', 'mother'])->orderBy('name');
        if ($this->familyScopeResolver->hasActiveScope()) {
            $ids = $this->familyScopeResolver->visibleUserIds();
            $query->whereIn('id', $ids ?: ['__none__']);
        }

        return $this->visibleUsers = $query->get();
    }

    private function visibleCouples(): Collection
    {
        if ($this->visibleCouples !== null) {
            return $this->visibleCouples;
        }

        $query = Couple::query()->with(['husband', 'wife'])->orderBy('id');
        if ($this->familyScopeResolver->hasActiveScope()) {
            $ids = $this->familyScopeResolver->visibleUserIds();
            if (empty($ids)) {
                return $this->visibleCouples = collect();
            }

            $query->where(function ($builder) use ($ids) {
                $builder->whereIn('husband_id', $ids)->orWhereIn('wife_id', $ids);
            });
        }

        return $this->visibleCouples = $query->get()->values();
    }

    private function buildReadmeSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, self::SHEET_README);
        $spreadsheet->addSheet($sheet);
        $sheet->fromArray([
            ['Template Usulan Edit Massal Keluarga'],
            ['Isi data di Google Sheet, lalu export ke .xlsx sebelum upload ke aplikasi.'],
            ['Aturan penting:'],
            ['1. Jangan ubah nama sheet dan header kolom.'],
            ['2. Gunakan sheet REFERENCE_USERS dan REFERENCE_COUPLES untuk memilih ID referensi.'],
            ['3. target_user_id wajib untuk update existing, pasangan baru, dan anak baru.'],
            ['4. NEW_STANDALONE tetap butuh anchor admin sebelum bisa di-approve.'],
            ['5. Format tanggal wajib YYYY-MM-DD, tahun wajib YYYY.'],
            ['6. spouse_context untuk NEW_CHILDREN: none, existing:<couple_id>, atau new:<spouse_request_key>.'],
            ['7. Foto massal tidak diimpor otomatis. photo_link/photo_note hanya catatan review.'],
        ]);
        $sheet->getColumnDimension('A')->setWidth(100);
        $sheet->freezePane('A1');
    }

    private function buildReferenceUsersSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, self::SHEET_REFERENCE_USERS);
        $spreadsheet->addSheet($sheet);
        $rows = [['tenant_host', 'user_id', 'display_name', 'name_raw', 'nickname', 'gender', 'father_name', 'mother_name']];
        foreach ($this->visibleUsers() as $user) {
            $rows[] = [
                $this->familyScopeResolver->currentHost() ?: '',
                $user->id,
                $user->display_name,
                $user->name,
                $user->nickname,
                $user->gender,
                $user->father?->display_name ?: '',
                $user->mother?->display_name ?: '',
            ];
        }

        $sheet->fromArray($rows);
        $this->styleReferenceSheet($sheet);
        $spreadsheet->addNamedRange(new NamedRange('USER_ID_LIST', $sheet, '$B$2:$B$'.max(2, count($rows))));
    }

    private function buildReferenceCouplesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, self::SHEET_REFERENCE_COUPLES);
        $spreadsheet->addSheet($sheet);
        $rows = [['couple_id', 'label', 'husband_id', 'wife_id', 'spouse_order']];
        foreach ($this->visibleCouples() as $couple) {
            $rows[] = [
                $couple->id,
                $this->coupleLabel($couple),
                $couple->husband_id,
                $couple->wife_id,
                $couple->spouse_order,
            ];
        }

        $sheet->fromArray($rows);
        $this->styleReferenceSheet($sheet);
        $spreadsheet->addNamedRange(new NamedRange('COUPLE_ID_LIST', $sheet, '$A$2:$A$'.max(2, count($rows))));
    }

    private function buildUpdatesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, self::SHEET_UPDATES);
        $spreadsheet->addSheet($sheet);
        $sheet->fromArray([['row_key', 'target_user_id', 'name', 'nickname', 'gender_id', 'birth_order', 'dob', 'yob', 'dod', 'yod', 'is_deceased', 'phone', 'address', 'city', 'email', 'cemetery_location_name', 'cemetery_location_address', 'cemetery_location_latitude', 'cemetery_location_longitude', 'requester_name', 'requester_whatsapp', 'notes']]);
        $this->styleEditableSheet($sheet);
        $this->addNamedRangeValidation($sheet, 'B2:B500', 'USER_ID_LIST');
        $this->addListValidation($sheet, 'E2:E500', ['1', '2']);
        $this->addListValidation($sheet, 'K2:K500', ['0', '1']);
    }

    private function buildSpousesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, self::SHEET_SPOUSES);
        $spreadsheet->addSheet($sheet);
        $sheet->fromArray([['row_key', 'target_user_id', 'spouse_request_key', 'name', 'nickname', 'dob', 'yob', 'marriage_date']]);
        $this->styleEditableSheet($sheet);
        $this->addNamedRangeValidation($sheet, 'B2:B500', 'USER_ID_LIST');
    }

    private function buildChildrenSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, self::SHEET_CHILDREN);
        $spreadsheet->addSheet($sheet);
        $sheet->fromArray([['row_key', 'target_user_id', 'name', 'nickname', 'gender_id', 'birth_order', 'dob', 'yob', 'spouse_context']]);
        $this->styleEditableSheet($sheet);
        $this->addNamedRangeValidation($sheet, 'B2:B500', 'USER_ID_LIST');
        $this->addListValidation($sheet, 'E2:E500', ['1', '2']);
    }

    private function buildStandaloneSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = new Worksheet($spreadsheet, self::SHEET_STANDALONE);
        $spreadsheet->addSheet($sheet);
        $sheet->fromArray([['row_key', 'name', 'nickname', 'gender_id', 'dob', 'yob', 'dod', 'yod', 'is_deceased', 'phone', 'address', 'city', 'cemetery_location_name', 'cemetery_location_address', 'cemetery_location_latitude', 'cemetery_location_longitude', 'anchor_type', 'anchor_ref_id', 'requester_name', 'requester_whatsapp', 'photo_link', 'photo_note']]);
        $this->styleEditableSheet($sheet);
        $this->addListValidation($sheet, 'D2:D500', ['1', '2']);
        $this->addListValidation($sheet, 'I2:I500', ['0', '1']);
        $this->addListValidation($sheet, 'Q2:Q500', ['user', 'couple']);
    }

    private function styleReferenceSheet(Worksheet $sheet): void
    {
        $lastColumn = $sheet->getHighestColumn();
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAF2F8');
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function styleEditableSheet(Worksheet $sheet): void
    {
        $lastColumn = $sheet->getHighestColumn();
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCEAF7');
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function addNamedRangeValidation(Worksheet $sheet, string $range, string $namedRange): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('='.$namedRange);
        $sheet->setDataValidation($range, $validation);
    }

    private function addListValidation(Worksheet $sheet, string $range, array $values): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"'.implode(',', $values).'"');
        $sheet->setDataValidation($range, $validation);
    }

    private function collectExistingProfile(array $rowValues, array &$errors): array
    {
        return array_filter([
            'name' => $this->normalizeUpperString($rowValues['name'] ?? null),
            'nickname' => $this->normalizeUpperString($rowValues['nickname'] ?? null),
            'gender_id' => $this->parseGender($rowValues['gender_id'] ?? null, $errors),
            'birth_order' => $this->parsePositiveInteger($rowValues['birth_order'] ?? null, 'birth_order', $errors),
            'dob' => $this->parseDate($rowValues['dob'] ?? null, 'dob', $errors),
            'yob' => $this->parseYear($rowValues['yob'] ?? null, 'yob', $errors),
            'dod' => $this->parseDate($rowValues['dod'] ?? null, 'dod', $errors),
            'yod' => $this->parseYear($rowValues['yod'] ?? null, 'yod', $errors),
            'is_deceased' => $this->parseBoolean($rowValues['is_deceased'] ?? null, 'is_deceased', $errors),
            'phone' => $this->cleanString($rowValues['phone'] ?? null),
            'address' => $this->cleanString($rowValues['address'] ?? null),
            'city' => $this->cleanString($rowValues['city'] ?? null),
            'email' => $this->cleanString($rowValues['email'] ?? null),
        ], fn ($value) => ! is_null($value) && $value !== '');
    }

    private function collectStandaloneProfile(array $rowValues, array &$errors): array
    {
        $profile = array_filter([
            'name' => $this->normalizeUpperString($rowValues['name'] ?? null),
            'nickname' => $this->normalizeUpperString($rowValues['nickname'] ?? null),
            'gender_id' => $this->parseGender($rowValues['gender_id'] ?? null, $errors),
            'dob' => $this->parseDate($rowValues['dob'] ?? null, 'dob', $errors),
            'yob' => $this->parseYear($rowValues['yob'] ?? null, 'yob', $errors),
            'dod' => $this->parseDate($rowValues['dod'] ?? null, 'dod', $errors),
            'yod' => $this->parseYear($rowValues['yod'] ?? null, 'yod', $errors),
            'is_deceased' => $this->parseBoolean($rowValues['is_deceased'] ?? null, 'is_deceased', $errors),
            'phone' => $this->cleanString($rowValues['phone'] ?? null),
            'address' => $this->cleanString($rowValues['address'] ?? null),
            'city' => $this->cleanString($rowValues['city'] ?? null),
        ], fn ($value) => ! is_null($value) && $value !== '');

        foreach (['name', 'nickname', 'gender_id'] as $field) {
            if (empty($profile[$field])) {
                $errors[] = $field.' wajib diisi.';
            }
        }

        return $profile;
    }

    private function collectMetadata(array $rowValues): array
    {
        return array_filter([
            'cemetery_location_name' => $this->cleanString($rowValues['cemetery_location_name'] ?? null),
            'cemetery_location_address' => $this->cleanString($rowValues['cemetery_location_address'] ?? null),
            'cemetery_location_latitude' => $this->cleanString($rowValues['cemetery_location_latitude'] ?? null),
            'cemetery_location_longitude' => $this->cleanString($rowValues['cemetery_location_longitude'] ?? null),
        ], fn ($value) => ! is_null($value) && $value !== '');
    }

    private function parseGender($value, array &$errors): ?int
    {
        $value = $this->cleanString($value);
        if ($value === null) {
            return null;
        }
        if (! in_array($value, ['1', '2'], true)) {
            $errors[] = 'gender_id harus 1 atau 2.';

            return null;
        }

        return (int) $value;
    }

    private function parsePositiveInteger($value, string $field, array &$errors): ?int
    {
        $value = $this->cleanString($value);
        if ($value === null) {
            return null;
        }
        if (! ctype_digit($value) || (int) $value < 1) {
            $errors[] = $field.' harus bilangan bulat positif.';

            return null;
        }

        return (int) $value;
    }

    private function parseDate($value, string $field, array &$errors): ?string
    {
        $value = $this->cleanString($value);
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $errors[] = $field.' harus berformat YYYY-MM-DD.';

            return null;
        }

        return $value;
    }

    private function parseYear($value, string $field, array &$errors): ?string
    {
        $value = $this->cleanString($value);
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^\d{4}$/', $value)) {
            $errors[] = $field.' harus berformat YYYY.';

            return null;
        }

        return $value;
    }

    private function parseBoolean($value, string $field, array &$errors): ?bool
    {
        $value = $this->cleanString($value);
        if ($value === null) {
            return null;
        }
        if (! in_array($value, ['0', '1'], true)) {
            $errors[] = $field.' harus 0 atau 1.';

            return null;
        }

        return $value === '1';
    }

    private function normalizeUpperString($value): ?string
    {
        $value = $this->cleanString($value);

        return $value ? User::normalizeUppercase($value) : null;
    }

    private function cleanString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isEmptyRow(array $rowValues): bool
    {
        return collect($rowValues)->every(fn ($value) => $this->cleanString($value) === null);
    }

    private function isValidSpouseContext(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return $value === 'none'
            || str_starts_with($value, 'existing:')
            || str_starts_with($value, 'new:');
    }

    private function coupleLabel(Couple $couple): string
    {
        return trim(collect([
            $couple->husband?->display_name,
            $couple->wife?->display_name,
        ])->filter()->implode(' & '));
    }
}
