<?php

use App\Controllers\LoginCodeController;
use App\Helpers\Authentication;
use App\Helpers\LoginCode;
use App\Models\BackupCode;
use App\Models\Model;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests the MFA code step through the actual controller
 * jsonResponse() and requireRole() both exit PHP which would kill the test
 * so the controller test uses a copy of the controller that throws the reply back
 * and the requireRole() test runs it as a separate php program and reads the output
 * own process each because sessions send headers
 */
final class LoginCodeControllerTest extends TestCase
{
    private const USER_ID = 'user-1';

    /**
     * In memory Users and MfaBackupCodes tables with one student, like userTest.php
     */
    protected function setUp(): void
    {
        $db = new \Pdo\Sqlite('sqlite::memory:');
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // sqlite hasn't got NOW(), recordLogin() needs it
        $db->createFunction('NOW', function () {
            return date('Y-m-d H:i:s');
        });

        $db->exec('CREATE TABLE Users (userID TEXT, username TEXT, email TEXT, password TEXT, accountType TEXT,
            approvalStatus TEXT, isEnabled INTEGER, isEmailVerified INTEGER, failedAttempts INTEGER,
            lockedUntil TEXT, lastFailedAt TEXT, lastLogin TEXT)');
        $insert = $db->prepare("INSERT INTO Users VALUES (?, 'ws000001', 'ws000001@example.com', 'hash',
            'student', 'approved', 1, 1, 0, NULL, NULL, NULL)");
        $insert->execute([self::USER_ID]);
        $db->exec('CREATE TABLE MfaBackupCodes (backupCodeID INTEGER PRIMARY KEY, userID TEXT, codeHash TEXT)');

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $db);
    }

    /**
     * Unhooks the pretend database so the next test file starts clean
     */
    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * Sends a code to the real verifyCode() and gives back [status, reply]
     */
    private function sendCode(string $code): array
    {
        // controller copy that hands the reply back without exiting
        $controller = new class ($code) extends LoginCodeController {
            public ?array $reply = null;
            private string $typedCode;

            public function __construct(string $typedCode)
            {
                parent::__construct();
                $this->typedCode = $typedCode;
            }

            protected function readBody(): array
            {
                return ['code' => $this->typedCode];
            }

            public function jsonResponse($data, $status = 200): void
            {
                // keep the first reply only, verifyCode()'s catch sends a 2nd
                if ($this->reply === null) {
                    $this->reply = [$status, $data];
                }
                throw new \RuntimeException('reply sent');
            }
        };

        // verifyCode() logs the fake reply as an error, send that to a temp file not the test output
        ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'login-code-test.log');

        try {
            $controller->verifyCode();
        } catch (\RuntimeException $e) {
            // reply sent, carry on
        }

        return $controller->reply;
    }

    /**
     * Gets the session to the code step like the password step would
     */
    private function startPendingLogin(): void
    {
        $_SESSION = [];
        $code = new LoginCode(self::USER_ID, LoginCode::hashCode('123456'), time(), time());
        (new Authentication())->savePendingLogin($code);
    }

    /**
     * Backup code logs in once through the real controller, the same code again gets 401
     * and counts as a wrong try (error)
     */
    #[RunInSeparateProcess]
    public function testUsedBackupCodeIsRefusedAtLogin(): void
    {
        // Arrange
        $codes = BackupCode::makeSet();
        BackupCode::saveSet(self::USER_ID, BackupCode::hashSet($codes));
        $this->startPendingLogin();

        // Act
        $firstGo = $this->sendCode($codes[0]);
        $this->startPendingLogin();
        $secondGo = $this->sendCode($codes[0]);

        // Assert
        self::assertSame(200, $firstGo[0]);
        self::assertSame(401, $secondGo[0]);
        self::assertSame('Security code is incorrect', $secondGo[1]['error']);
        self::assertSame(BackupCode::SET_SIZE - 1, BackupCode::countLeft(self::USER_ID));
    }

    /**
     * Password right but no code yet, the real requireRole() still says no (error)
     * requireRole() exits, so it runs as a separate php program and the output gets checked
     */
    public function testProtectedEndpointsRefusePendingLogin(): void
    {
        // Arrange: small php program, saves a pending login then calls requireRole()
        $autoload = __DIR__ . '/../vendor/autoload.php';
        // only single quotes inside, windows swaps double quotes for spaces when it runs the command
        $script = 'require ' . var_export($autoload, true) . ';'
            . '$hash = App\Helpers\LoginCode::hashCode(\'123456\');'
            . '$code = new App\Helpers\LoginCode(\'user-1\', $hash, time(), time());'
            . '(new App\Helpers\Authentication())->savePendingLogin($code);'
            . 'App\Helpers\Authentication::requireRole([\'student\', \'lecturer\', \'admin\']);'
            . 'echo \'GOT IN\';';

        // Act
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');

        // Assert
        self::assertStringContainsString('Your session has ended', (string) $output);
        self::assertStringNotContainsString('GOT IN', (string) $output);
    }
}
