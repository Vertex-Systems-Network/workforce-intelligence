<?php

namespace Tests\Unit;

use App\Services\Security\UploadSecurityService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

/** Verifies Windows-safe uploaded-file inspection behavior without a database dependency. */
class UploadInspectionPathTest extends TestCase
{
    /** Previous global Laravel container restored after the pure upload inspection test. */
    private ?Container $previousContainer = null;

    /** Install the minimum config container required by Laravel's config() helper. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('config', new Repository([
            'workintel_security' => ['uploads' => ['malware_driver' => 'none', 'malware_required' => false]],
        ]));
        Container::setInstance($container);
    }

    /** Restore the process-global container so this pure unit test cannot leak state into another test. */
    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    /** A valid upload remains inspectable when SplFileInfo realpath resolution is unavailable. */
    public function test_uploaded_file_pathname_is_used_when_realpath_is_false(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'workintel-upload-');
        $this->assertIsString($path);
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $file = new class($path, 'avatar.png', 'image/png', UPLOAD_ERR_OK, true) extends UploadedFile {
            /** Simulate Windows/PHP temporary upload environments where realpath() cannot canonicalize the valid temp file. */
            public function getRealPath(): string|false
            {
                return false;
            }
        };

        try {
            $inspection = (new UploadSecurityService())->inspect($file);
            $this->assertSame('image/png', $inspection['mime']);
            $this->assertSame($path, $inspection['path']);
        } finally {
            @unlink($path);
        }
    }
}
