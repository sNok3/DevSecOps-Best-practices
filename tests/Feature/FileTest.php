<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Misuse cases MC-07 (malicious upload / web shell), MC-08 (read another user's file), MC-09 (disk theft, tampering). */
class FileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)->post('/files', ['file' => $file]);
    }

    public function test_upload_is_encrypted_on_disk_under_a_random_name_and_downloads_intact(): void
    {
        $user = User::factory()->create();
        $content = "Confidential salary list\nAlice 5000";
        $this->upload($user, UploadedFile::fake()->createWithContent('salaries.txt', $content))->assertRedirect('/files');

        $file = VaultFile::firstOrFail();
        $disk = Storage::disk('local')->get("vault/{$file->stored_name}.bin");
        $this->assertStringNotContainsString('salary', $disk);
        $this->assertStringStartsWith('VF1', $disk);
        $this->assertSame(["vault/{$file->stored_name}.bin"], Storage::disk('local')->allFiles());

        $response = $this->actingAs($user)->get("/files/{$file->id}")->assertOk();
        $this->assertSame($content, $response->streamedContent());
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    /** A real temp file, so the type is detected from the bytes (finfo) like in production, not from the name. */
    private function realUpload(string $clientName, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, null, null, true);
    }

    public function test_php_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->realUpload('shell.php', '<?php system($_GET["c"]);'))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('vault_files', 0);
    }

    public function test_php_code_disguised_as_png_is_rejected_by_content_check(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->realUpload('cat.png', '<?php system($_GET["c"]);'))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('vault_files', 0);
    }

    public function test_real_png_is_accepted(): void
    {
        $user = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->upload($user, $this->realUpload('pixel.png', $png))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('vault_files', 1);
    }

    public function test_path_in_the_file_name_is_stripped_and_never_used_on_disk(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->realUpload('../../../public/evil.txt', "plain text file\n"))->assertSessionHasNoErrors();

        $file = VaultFile::firstOrFail();
        $this->assertSame('evil.txt', $file->original_name);
        $this->assertSame(["vault/{$file->stored_name}.bin"], Storage::disk('local')->allFiles());
    }

    public function test_too_large_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->upload($user, UploadedFile::fake()->create('big.pdf', config('vault.max_upload_kb') + 1, 'application/pdf'))->assertSessionHasErrors('file');
    }

    public function test_other_users_file_is_not_found_and_logged(): void
    {
        [$alice, $mallory] = User::factory()->count(2)->create();
        $this->upload($alice, UploadedFile::fake()->createWithContent('a.txt', 'alice data'));
        $file = VaultFile::firstOrFail();

        $this->actingAs($mallory)->get("/files/{$file->id}")->assertNotFound();
        $this->actingAs($mallory)->delete("/files/{$file->id}")->assertNotFound();
        $this->assertCount(2, $this->eventsNamed('authz.denied'));
        $this->assertDatabaseCount('vault_files', 1);
    }

    public function test_tampered_file_fails_closed_and_logs_an_integrity_error(): void
    {
        $user = User::factory()->create();
        $this->upload($user, UploadedFile::fake()->createWithContent('a.txt', 'original'));
        $file = VaultFile::firstOrFail();
        $path = "vault/{$file->stored_name}.bin";
        $blob = Storage::disk('local')->get($path);
        Storage::disk('local')->put($path, substr($blob, 0, -1).chr(ord(substr($blob, -1)) ^ 1));

        $this->actingAs($user)->get("/files/{$file->id}")->assertStatus(500);
        $event = $this->eventsNamed('crypto.file.integrity_failed');
        $this->assertCount(1, $event);
        $this->assertSame('ERROR', $event[0]['level_name']);
    }

    public function test_upload_rate_limit(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->upload($user, UploadedFile::fake()->createWithContent("f{$i}.txt", 'x'));
        }
        $this->upload($user, UploadedFile::fake()->createWithContent('f11.txt', 'x'))->assertStatus(429);
        $this->assertCount(1, $this->eventsNamed('abuse.rate_limited'));
    }
}
