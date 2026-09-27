<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The school principal is stored once in system_settings and read everywhere
 * through school_principal().
 *
 * Public schools change their principal every two or three years, so a name
 * typed into a view, a PDF template or an email class is a bug waiting to
 * happen: the ID cards would show one principal while the About page or a
 * report card still showed the previous one. These tests pin the single source
 * of truth and, more importantly, prove that no page has started hardcoding the
 * name again.
 *
 * @internal
 */
final class SchoolPrincipalTest extends CIUnitTestCase
{
    /**
     * The helper must be autoloaded: views, PDF templates, controllers and the
     * email service all call school_principal() without loading it by hand.
     */
    public function testPrincipalHelperIsAutoloaded(): void
    {
        $autoload = file_get_contents($this->appPath('app/Config/Autoload.php'));

        $this->assertMatchesRegularExpression(
            "/public \\\$helpers = \\[[^\\]]*'principal'/",
            $autoload,
            'the principal helper must be listed in Config\Autoload::$helpers'
        );
    }

    public function testHelperReadsTheThreeStoredSettingsAndKeepsFallbacks(): void
    {
        $helper = file_get_contents($this->appPath('app/Helpers/principal_helper.php'));

        foreach (['school_principal_name', 'school_principal_rank', 'school_principal_photo'] as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $helper,
                "school_principal() must read the {$key} setting"
            );
        }

        // A fresh install, or a database that predates the feature, has no rows:
        // the bundled picture and the known defaults keep every page rendering.
        $this->assertStringContainsString("'principal2.png'", $helper, 'the About page photo falls back to the bundled picture');
        $this->assertStringContainsString("'Djoanne B. Pascual'", $helper, 'the principal name has a hardcoded fallback');
        $this->assertStringContainsString("'Principal IV'", $helper, 'the principal rank has a hardcoded fallback');
    }

    /**
     * The ID cards used to own their own copy of the principal. They must read
     * the shared helper now, so one save updates cards and pages together.
     */
    public function testIdCardBrandingDelegatesToTheSharedHelper(): void
    {
        $branding = file_get_contents($this->appPath('app/Helpers/id_card_helper.php'));

        $this->assertStringContainsString(
            'school_principal()',
            $branding,
            'id_card_branding() must read the principal from school_principal()'
        );
        $this->assertStringNotContainsString(
            "'Djoanne B. Pascual'",
            $branding,
            'the ID card helper must not keep its own hardcoded principal name'
        );
    }

    /**
     * Every surface that prints or signs the principal must read the helper...
     */
    public function testEveryPrincipalSurfaceReadsTheSharedHelper(): void
    {
        $surfaces = [
            // The About partial assigns school_principal() to $principal first.
            'app/Views/partials/school_about_sections.php' => 'school_principal()',
            'app/Views/layout.php'                         => 'school_principal_name()',
            // Teacher, student and admin copies all render this one layout.
            'app/Views/reports/academic_report_card_pdf.php' => 'school_principal()',
            'app/Views/teacher/learner_development_report_pdf.php' => 'school_principal()',
            'app/Views/teacher/sned_report_card_pdf.php'   => 'school_principal()',
            'app/Libraries/SupabaseEmailService.php'       => 'school_principal_name()',
        ];

        foreach ($surfaces as $relative => $expected) {
            $this->assertStringContainsString(
                $expected,
                file_get_contents($this->appPath($relative)),
                "{$relative} must print the current principal instead of a fixed name"
            );
        }
    }

    /**
     * ...and none of them may go back to typing the name in.
     */
    public function testNoPageHardcodesThePrincipalName(): void
    {
        $offenders = [];

        foreach ($this->phpFiles('app/Views') as $relative) {
            if (str_contains(file_get_contents($this->appPath($relative)), 'Djoanne B. Pascual')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'these files still hardcode the principal name: ' . implode(', ', $offenders)
        );
    }

    public function testSettingsFormAcceptsAPhotoAndTheControllerStoresIt(): void
    {
        $view       = file_get_contents($this->appPath('app/Views/admin/settings.php'));
        $controller = file_get_contents($this->appPath('app/Controllers/Admin/Settings.php'));

        $this->assertStringContainsString(
            'enctype="multipart/form-data"',
            $view,
            'the principal form must be able to upload a file'
        );
        $this->assertStringContainsString(
            'name="school_principal_photo"',
            $view,
            'the settings page must offer a photo upload'
        );
        $this->assertStringContainsString(
            "'school_principal_photo'",
            $controller,
            'the controller must persist the uploaded photo path'
        );
        $this->assertStringContainsString(
            "'uploads/principal/'",
            $controller,
            'uploaded photos are stored under uploads/principal/'
        );

        // Only files inside that folder are ever removed, so a bad setting
        // value can never unlink a bundled asset such as principal2.png.
        $this->assertStringContainsString(
            "str_starts_with(\$storedPath, 'uploads/principal/')",
            $controller,
            'photo deletion must be restricted to the upload folder'
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $relativeDirectory): array
    {
        $root     = $this->appPath($relativeDirectory);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $relativeDirectory . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        sort($files);

        return $files;
    }

    private function appPath(string $relative): string
    {
        $root = defined('ROOTPATH') ? ROOTPATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        $this->assertFileExists($path, 'expected to inspect ' . $relative);

        return $path;
    }
}
