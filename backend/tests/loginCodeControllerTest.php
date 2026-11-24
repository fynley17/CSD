<?php

use App\Controllers\LoginCodeController;
use App\Helpers\Authentication;
use App\Helpers\LoginCode;
use App\Models\BackupCode;
use App\Models\Model;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MFA code step going through the real controller, not just the models
 * jsonResponse() and requireRole() both end the PHP program, so:
 * - the controller tests use a copy of LoginCodeController that throws the reply back instead of sending it
 * - the requireRole() test runs in a second PHP program and reads what it printed
 * each one runs in its own process, as starting a session sends headers
 */
final class LoginCodeControllerTest extends TestCase
{
    private const USER_ID = 'user-1';

    /**
     * Pretend Users and MfaBackupCodes tables with one student, same way userTest.php does it
     */
    protected function setUp(): void
    {
        $db = new \Pdo\Sqlite('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // sqlite hasn't got NOW(), recordLogin() needs it
        $db->createFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));

        $db->exec('CREATE TABLE Users (userID TEXT, username TEXT, email TEXT, password TEXT, accountType TEXT,
            approvalStatus TEXT, isEnabled INTEGER, isEmailVerified INTEGER, failedAttempts INTEGER,
            lockedUntil TEXT, lastFailedAt TEXT, lastLogin TEXT)');
        $db->exec("INSERT INTO Users VALUES ('" . self::USER_ID . "', 'ws000001', 'ws000001@example.com', 'hash',
            'student', 'approved', 1, 1, 0, NULL, NULL, NULL)");
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
        // a copy of the controller that hands the reply back instead of sending it and stopping
        $controller = new class ($code) extends LoginCodeController {
            public ?array $reply = null;

            public function __construct(private string $typedCode)
            {
                parent::__construct();
            }

            protected function readBody(): array
            {
                return ['code' => $this->typedCode];
            }

            public function jsonResponse($data, $status = 200): void
            {
                // only keep the first reply, the catch in verifyCode() would send a second one
                $this->reply ??= [$status, $data];
                throw new \RuntimeException('reply sent');
            }
        };

        try {
            $controller->verifyCode();
        } catch (\RuntimeException $e) {
            // the reply was "sent", carry on and check it
        }

        return $controller->reply;
    }

    /**
     * Puts this browser at the code step, like the password step does
     */
    private function startPendingLogin(): void
    {
        $_SESSION = [];
        $code = new LoginCode(self::USER_ID, LoginCode::hashCode('123456'), time(), time());
        (new Authentication())->savePendingLogin($code);
    }

    /**
     * A backup code logs in once through the real login, then the same code is turned away
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
     * Only the password right (MFA on) means the real requireRole() still says 401 (error)
     * requireRole() ends the program, so it runs in a second PHP program and this checks what it printed
     */
    public function testProtectedEndpointsRefusePendingLogin(): void
    {
        // Arrange: a second program that saves a pending login, then asks requireRole() to let it in
        $autoload = __DIR__ . '/../vendor/autoload.php';
        $script = 'require ' . var_export($autoload, true) . ';'
            . '$code = new App\Helpers\LoginCode("user-1", App\Helpers\LoginCode::hashCode("123456"), time(), time());'
            . '(new App\Helpers\Authentication())->savePendingLogin($code);'
            . 'App\Helpers\Authentication::requireRole(["student", "lecturer", "admin"]);'
            . 'echo "GOT IN";';

        // Act
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');

        // Assert
        self::assertStringContainsString('Your session has ended', (string) $output);
        self::assertStringNotContainsString('GOT IN', (string) $output);
    }
}
