<?php

namespace Local\Media\Tests;

use App\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Local\Media\Contracts\Mediable;
use Local\Media\Models\Media;
use Local\Media\Services\MediaService;
use Local\Media\Traits\HasMedia;
use RuntimeException;
use Tests\TestCase;

class MediaTestModel extends User implements Mediable
{
    use HasMedia;

    protected $table = 'users';
}

class MediaPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media.disk' => 'public']);
    }

    protected function makeModel(): MediaTestModel
    {
        return MediaTestModel::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret123',
        ]);
    }

    public function test_add_media_stores_file_and_record(): void
    {
        $model = $this->makeModel();

        $media = $model->addMedia(
            UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            'documents',
            ['alt' => 'Q1 report']
        );

        Storage::disk('public')->assertExists($media->path);
        $this->assertTrue(Str::isUuid($media->uuid));
        $this->assertSame('documents', $media->collection);
        $this->assertSame(['alt' => 'Q1 report'], $media->custom_properties);
        $this->assertSame($media->id, $model->getFirstMedia('documents')?->id);
    }

    public function test_clear_media_removes_records_and_files(): void
    {
        $model = $this->makeModel();
        $media = $model->addMedia(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));

        $model->clearMedia();

        Storage::disk('public')->assertMissing($media->path);
        $this->assertCount(0, $model->getMedia());
    }

    public function test_disallowed_mime_type_is_rejected(): void
    {
        $model = $this->makeModel();
        config(['media.allowed_mime_types' => ['image/png']]);

        $this->expectException(InvalidArgumentException::class);

        app(MediaService::class)->store(
            UploadedFile::fake()->create('script.sh', 1, 'text/x-shellscript'),
            $model
        );
    }

    public function test_oversized_file_is_rejected(): void
    {
        $model = $this->makeModel();
        config(['media.max_file_size' => 1]); // 1 KB

        $this->expectException(InvalidArgumentException::class);

        $model->addMedia(UploadedFile::fake()->create('big.pdf', 10, 'application/pdf'));
    }

    public function test_media_records_and_paths_are_isolated_by_tenant(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $tenantA = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $tenantB = Tenant::query()->create(['name' => 'Globex', 'slug' => 'globex']);

        $media = with_tenant($tenantA->id, function (): Media {
            $model = $this->makeModel();

            return $model->addMedia(UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'));
        });

        $this->assertStringContainsString("media/tenants/{$tenantA->id}/", $media->path);
        $this->assertNull(with_tenant($tenantB->id, fn () => Media::query()->find($media->id)));
        $this->assertNotNull(with_tenant($tenantA->id, fn () => Media::query()->find($media->id)));

        $this->expectException(RuntimeException::class);
        with_tenant($tenantB->id, fn () => $media->contents());
    }

    public function test_collection_cannot_escape_its_storage_directory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeModel()->addMedia(
            UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
            '../private',
        );
    }
}
