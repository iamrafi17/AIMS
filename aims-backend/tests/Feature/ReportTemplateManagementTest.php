<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\GeneratedReport;
use App\Models\Program;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class ReportTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_upload_validate_and_preview_a_scoped_docx_template(): void
    {
        Storage::fake('local');
        [$program, $coordinator] = $this->programUser('CC', 'BSIS', 'coordinator');
        Sanctum::actingAs($coordinator);

        $response = $this->post('/api/report-templates', $this->payload(
            $this->docx([]),
            ['activate' => true],
        ))->assertCreated()
            ->assertJsonPath('template.program_id', $program->id)
            ->assertJsonPath('template.report_type', 'all_reports')
            ->assertJsonPath('template.period_scope', ReportTemplate::SCOPE_ALL_PERIODS)
            ->assertJsonPath('template.academic_period_id', null)
            ->assertJsonPath('template.academic_year', 'All Academic Years')
            ->assertJsonPath('template.semester', 'All Semesters')
            ->assertJsonPath('template.validation_status', 'valid')
            ->assertJsonPath('template.placeholders', [])
            ->assertJsonPath('template.is_active', true)
            ->assertJsonFragment(['message' => 'Official template uploaded and is now active for all reports.']);

        $template = ReportTemplate::findOrFail($response->json('template.id'));
        $this->assertSame([], $template->detected_placeholders);
        $this->assertDatabaseHas('report_templates', [
            'id' => $template->id,
            'period_scope' => ReportTemplate::SCOPE_ALL_PERIODS,
            'academic_period_id' => null,
        ]);
        Storage::disk('local')->assertExists($template->file_path);

        $preview = $this->getJson("/api/report-templates/{$template->id}/preview")
            ->assertOk()
            ->assertJsonPath('sample_only', true)
            ->assertJsonPath('template.name', 'Official BSIS Template')
            ->assertSee('Report Records', false)
            ->assertSee('Juan Dela Cruz', false)
            ->assertSee('Maria Santos', false);
        $this->assertStringContainsString('data:image/png;base64,', $preview->json('html'));
    }

    public function test_blank_official_template_needs_no_codes_and_duplicate_upload_is_rejected(): void
    {
        Storage::fake('local');
        [, $coordinator] = $this->programUser('CC', 'BSIS', 'coordinator');
        Sanctum::actingAs($coordinator);

        $validFile = $this->docx([]);
        $contents = file_get_contents($validFile->getRealPath());
        $this->post('/api/report-templates', $this->payload($validFile, ['name' => 'Official blank format', 'activate' => true]))
            ->assertCreated()
            ->assertJsonPath('template.validation_status', 'valid')
            ->assertJsonPath('template.is_active', true);
        $this->withHeader('Accept', 'application/json')->post('/api/report-templates', $this->payload($this->uploadedDocx($contents), ['name' => 'Duplicate']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_renamed_executable_or_corrupted_docx_is_rejected_without_storage(): void
    {
        Storage::fake('local');
        [, $coordinator] = $this->programUser('CC', 'BSIS', 'coordinator');
        Sanctum::actingAs($coordinator);

        $corrupted = UploadedFile::fake()->createWithContent('unsafe.docx', "MZ\x00\x02executable-content");
        $this->withHeader('Accept', 'application/json')
            ->post('/api/report-templates', $this->payload($corrupted))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('report_templates', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('report-templates'));
    }

    public function test_activation_replaces_the_previous_template_and_active_templates_cannot_be_deleted(): void
    {
        Storage::fake('local');
        [, $coordinator] = $this->programUser('CC', 'BSIS', 'coordinator');
        Sanctum::actingAs($coordinator);

        $firstId = $this->post('/api/report-templates', $this->payload($this->docx(['student_name', 'program', 'hte_name']), ['name' => 'Version One', 'version' => '1', 'activate' => true]))
            ->assertCreated()
            ->assertJsonPath('template.is_active', true)
            ->json('template.id');

        $this->deleteJson("/api/report-templates/{$firstId}")->assertUnprocessable();
        $secondId = $this->post('/api/report-templates', $this->payload($this->docx(['student_name', 'program', 'hte_name', 'date_generated']), ['name' => 'Version Two', 'version' => '2', 'activate' => true]))
            ->assertCreated()
            ->assertJsonPath('template.is_active', true)
            ->assertJsonFragment(['message' => 'Official template uploaded. The previous template was replaced.'])
            ->json('template.id');

        $this->assertFalse(ReportTemplate::findOrFail($firstId)->is_active);
        $this->assertTrue(ReportTemplate::findOrFail($secondId)->is_active);
        $this->assertSame(1, ReportTemplate::where('is_active', true)->count());
    }

    public function test_active_template_populates_current_data_generates_pdf_and_preserves_history_after_template_deletion(): void
    {
        Storage::fake('local');
        [$program, $coordinator] = $this->programUser('CC', 'BSIS', 'coordinator');
        Sanctum::actingAs($coordinator);
        $templateId = $this->post('/api/report-templates', $this->payload($this->docx([]), ['activate' => true]))
            ->assertCreated()->json('template.id');

        foreach (['student_internship', 'hte_deployment', 'travel_monitoring'] as $reportType) {
            $this->getJson('/api/report-templates/active/preview?'.http_build_query(['report_type' => $reportType]))
                ->assertOk()
                ->assertJsonPath('sample_only', false)
                ->assertJsonPath('template.id', $templateId)
                ->assertJsonPath('template.report_type', 'all_reports');
        }
        $this->assertDatabaseCount('report_templates', 1);

        $params = http_build_query(['report_type' => 'student_internship']);
        $pdf = $this->get("/api/report-templates/active/pdf?{$params}")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $generated = GeneratedReport::firstOrFail();
        $this->assertSame($templateId, $generated->report_template_id);
        $this->assertSame($program->id, $generated->program_id);
        Storage::disk('local')->assertExists($generated->file_path);

        $this->postJson("/api/report-templates/{$templateId}/deactivate")->assertOk();
        $this->deleteJson("/api/report-templates/{$templateId}")->assertOk();
        $this->assertSoftDeleted('report_templates', ['id' => $templateId]);
        $this->assertDatabaseHas('generated_reports', ['id' => $generated->id, 'report_template_id' => $templateId]);
        Storage::disk('local')->assertExists($generated->file_path);
    }

    public function test_program_scope_and_role_specific_upload_permissions_are_enforced(): void
    {
        Storage::fake('local');
        [$program, $coordinator] = $this->programUser('CC', 'BSIS', 'coordinator');
        [, $otherCoordinator] = $this->programUser('CAS', 'BAPOLSCI', 'coordinator');
        $programHead = User::factory()->create(['role' => 'program_head', 'college_id' => $program->college_id, 'program_id' => $program->id]);

        Sanctum::actingAs($coordinator);
        $templateId = $this->post('/api/report-templates', $this->payload($this->docx([]), ['activate' => true]))
            ->assertCreated()->json('template.id');

        Sanctum::actingAs($otherCoordinator);
        $this->getJson("/api/report-templates/{$templateId}/preview")->assertForbidden();

        Sanctum::actingAs($programHead);
        $params = http_build_query(['report_type' => 'hte_deployment_review']);
        $this->getJson("/api/report-templates/active/preview?{$params}")
            ->assertOk()
            ->assertJsonPath('template.id', $templateId)
            ->assertJsonPath('template.report_type', 'all_reports');

        $this->post('/api/report-templates', $this->payload(
            $this->docx(['program_head_replacement']),
            ['report_type' => 'program_summary', 'activate' => true],
        ))->assertCreated()
            ->assertJsonPath('template.program_id', $program->id)
            ->assertJsonPath('template.is_active', true);

        $vpaa = User::factory()->create(['role' => 'vpaa', 'college_id' => null, 'program_id' => null]);
        Sanctum::actingAs($vpaa);
        $this->post('/api/report-templates', $this->payload(
            $this->docx([]),
            ['report_type' => 'overall_summary', 'activate' => true],
        ))->assertCreated()
            ->assertJsonPath('template.program_id', null)
            ->assertJsonPath('template.is_active', true);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/api/report-templates', $this->payload(
            $this->docx([]),
            ['activate' => true],
        ))->assertForbidden();
    }

    private function payload(UploadedFile $file, array $overrides = []): array
    {
        return array_merge([
            'program_id' => 999999,
            'report_type' => 'student_internship',
            'name' => 'Official BSIS Template',
            'version' => '1.0',
            'academic_year' => '2026-2027',
            'semester' => 'First Semester',
            'description' => 'Official report layout',
            'file' => $file,
        ], $overrides);
    }

    private function programUser(string $collegeCode, string $programCode, string $role): array
    {
        $college = College::create(['name' => $collegeCode.' College', 'code' => $collegeCode, 'is_active' => true]);
        $program = Program::create(['college_id' => $college->id, 'name' => $programCode.' Program', 'code' => $programCode, 'is_active' => true]);
        $user = User::factory()->create(['role' => $role, 'college_id' => $college->id, 'program_id' => $program->id]);

        return [$program, $user];
    }

    private function docx(array $placeholders): UploadedFile
    {
        $paragraphs = collect($placeholders)
            ->reject(fn ($placeholder) => $placeholder === 'student_list')
            ->map(fn ($placeholder) => '<w:p><w:r><w:t>{{'.$placeholder.'}}</w:t></w:r></w:p>')
            ->join('');
        if (in_array('student_list', $placeholders, true)) {
            $paragraphs .= '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>{{student_list}}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>';
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
            .$paragraphs.'<w:sectPr><w:headerReference w:type="default" r:id="rId1"/><w:footerReference w:type="default" r:id="rId2"/></w:sectPr></w:body></w:document>';
        $types = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            .'<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            .'</Types>';
        $documentRels = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
            .'</Relationships>';
        $header = '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><w:p><w:r><w:drawing><a:blip r:embed="rId1"/></w:drawing></w:r></w:p></w:hdr>';
        $footer = str_replace(['w:hdr', '/w:hdr'], ['w:ftr', '/w:ftr'], $header);
        $imageRels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>';
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAFAAAAAKCAIAAACsZk5rAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAJ0lEQVRIiWOsZZBlGEmAaaAdQG8w6uHhDkY9PNzBqIeHOxj18HAHABDYAK7xPd9aAAAAAElFTkSuQmCC');
        $path = tempnam(sys_get_temp_dir(), 'aims-template-').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/_rels/document.xml.rels', $documentRels);
        $zip->addFromString('word/header1.xml', $header);
        $zip->addFromString('word/footer1.xml', $footer);
        $zip->addFromString('word/_rels/header1.xml.rels', $imageRels);
        $zip->addFromString('word/_rels/footer1.xml.rels', $imageRels);
        $zip->addFromString('word/media/image1.png', $image);
        $zip->close();

        return new UploadedFile($path, 'official-template.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    private function uploadedDocx(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'aims-template-copy-').'.docx';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'duplicate.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }
}
