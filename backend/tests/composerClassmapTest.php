<?php

use PHPUnit\Framework\TestCase;

/**
 * Checks every backend class is listed in composer.json's classmap (CTS-204)
 *
 * The files are camelCase, i.e. app/controllers/passwordResetController.php, but the classes are PascalCase,
 * so Composer's PSR-4 fallback looks for app/Controllers/PasswordResetController.php instead.
 * Macs don't care about upper and lower case, so a missing class still loads there and every test passes,
 * but the Linux live server can't find it and every /api request crashes.
 * This test reads composer.json itself, so it fails on any computer when a file is left out.
 * each test is laid out Arrange, Act, Assert
 */
final class ComposerClassmapTest extends TestCase
{
    private const BACKEND = __DIR__ . '/..';

    /**
     * The classmap paths from composer.json, i.e. 'app/models/user.php'
     *
     * @return list<string>
     */
    private static function classmapPaths(): array
    {
        $composer = json_decode(
            (string) file_get_contents(self::BACKEND . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return $composer['autoload']['classmap'];
    }

    /**
     * Every PHP file under app/ that declares a class, interface, trait or enum, as paths like composer.json uses
     *
     * @return array<string, array{string}> keyed by path so a failure says which file it was
     */
    public static function classFiles(): array
    {
        $files = [];
        $folder = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::BACKEND . '/app'));

        foreach ($folder as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // a file with no class in it, i.e. an empty helper, doesn't need listing
            $code = (string) file_get_contents($file->getPathname());
            if (preg_match('/^\s*(abstract\s+|final\s+)?(class|interface|trait|enum)\s+\w+/m', $code) !== 1) {
                continue;
            }

            $path = 'app/' . str_replace('\\', '/', substr($file->getPathname(), strlen(self::BACKEND . '/app/')));
            $files[$path] = [$path];
        }

        ksort($files);

        return $files;
    }

    /**
     * Each class file is in the classmap, so it loads on the Linux server as well as on a Mac (normal)
     */
    #[PHPUnit\Framework\Attributes\DataProvider('classFiles')]
    public function testClassFileIsInTheClassmap(string $path): void
    {
        // Act
        $listed = in_array($path, self::classmapPaths(), true);

        // Assert
        self::assertTrue(
            $listed,
            $path . ' declares a class but is missing from the classmap in backend/composer.json, '
            . 'so it will not load on the live server'
        );
    }

    /**
     * Everything in the classmap still exists, a renamed or deleted file would break composer install (error)
     */
    public function testEveryClassmapPathExists(): void
    {
        // Act
        $missing = array_filter(
            self::classmapPaths(),
            fn (string $path) => !is_file(self::BACKEND . '/' . $path)
        );

        // Assert
        self::assertSame([], array_values($missing), 'These classmap paths in backend/composer.json do not exist');
    }
}
