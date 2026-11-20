<?php

namespace App\Controllers;

use App\Helpers\Authentication;
use App\Helpers\Mailer;
use App\Models\Administrator;
use App\Models\Lecturer;
use App\Models\PasswordSetup;
use App\Models\User;

/**
 * Answers the admin dashboard's requests, only someone logged in as an admin gets an answer
 */
class AdministratorController extends Controller
{
    /**
     * GET /api/admin/dashboard-summary, the totals shown on the four admin dashboard cards
     */
    public function getDashboardSummary(): void
    {
        // turns away anyone who isn't logged in as an admin before anything gets counted
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Administrator::getDashboardSummary());
        } catch (\Throwable $e) {
            // the error goes in the server log, the page only gets a plain message
            error_log('Loading the admin dashboard totals failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the dashboard totals'], 500);
        }
    }


    /**
     * GET /api/admin/recent-lecturers, the lecturers shown in the table on the admin dashboard
     */
    public function getRecentLecturers(): void
    {
        // same check as the cards, only admins get the list
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Administrator::getRecentLecturers());
        } catch (\Throwable $e) {
            error_log('Loading the admin dashboard lecturers failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the lecturers'], 500);
        }
    }

    /**
     * GET /api/admin/lecturers, every lecturer for the table on the lecturer management page
     */
    public function getLecturers(): void
    {
        Authentication::requireRole(['admin']);

        try {
            $this->jsonResponse(Administrator::getAllLecturers());
        } catch (\Throwable $e) {
            error_log('Loading the lecturer management list failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to load the lecturers'], 500);
        }
    }

    /**
     * POST /api/admin/lecturers, the Add Lecturer pop-up. Makes the account then emails a set password link
     */
    public function createLecturer(): void
    {
        Authentication::requireRole(['admin']);

        try {
            $admin = $this->getLoggedInAdmin();
            $created = $admin->createLecturer($this->readBody());

            // the account is saved by now, so if the email goes wrong it's still a 201
            // the page tells the admin and they can use Resend link a minute later
            $emailSent = self::emailSetupLink($created, null);
            $word = $created['role'] === 'admin' ? 'Administrator' : 'Lecturer';

            $this->jsonResponse([
                'message' => $emailSent
                    ? $word . ' added. They have been emailed a link to set their password'
                    : $word . " added, but the email couldn't be sent. Wait a minute then use Resend link",
                    'emailSent' => $emailSent,
                'userID' => $created['userID'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            // these messages are written in Administrator for the page, nothing from the database
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Adding the lecturer failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to add the lecturer'], 500);
        }
    }

    /**
     * POST /api/admin/lecturers/{id}/setup-link, the Resend link button for someone who hasn't set a password yet
     */
    public function resendSetupLink(string $userID): void
    {
        Authentication::requireRole(['admin']);

        try {
            $admin = $this->getLoggedInAdmin();
            $lecturer = self::findLecturer($userID);
            if ($lecturer === null) {
                $this->jsonResponse(['error' => 'Lecturer not found'], 404);
                return;
            }

            $link = $admin->makeSetupLink($lecturer);
            if (!self::emailSetupLink($link, $link['token'])) {
                $this->jsonResponse(['error' => "The email couldn't be sent, try again later"], 500);
                return;
            }

            $this->jsonResponse(['message' => 'Link sent']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Making a new set password link failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to send a new link'], 500);
        }
    }

    /**
     * PATCH /api/admin/lecturers/{id}/enabled, the Disable account and Enable account buttons and Undo
     */
    public function setLecturerEnabled(string $userID): void
    {
        Authentication::requireRole(['admin']);

        $isEnabled = self::readIsEnabled($this->readBody());
        if ($isEnabled === null) {
            $this->jsonResponse(['error' => 'Choose disable or enable'], 400);
            return;
        }

        try {
            $admin = $this->getLoggedInAdmin();
            $lecturer = self::findLecturer($userID);
            if ($lecturer === null) {
                $this->jsonResponse(['error' => 'Lecturer not found'], 404);
                return;
            }

            if ($isEnabled) {
                $admin->enableLecturer($lecturer);
            } else {
                $admin->disableLecturer($lecturer);
            }
            $this->jsonResponse(['message' => 'Status updated']);
        } catch (\InvalidArgumentException $e) {
            // the message is written in Administrator for the page, nothing from the database
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Changing the lecturer status failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to update the lecturer'], 500);
        }
    }

    /**
     * PUT /api/admin/lecturers/{id}, saves the Edit Lecturer pop-up
     */
    public function updateLecturer(string $userID): void
    {
        Authentication::requireRole(['admin']);
        $data = $this->readBody();

        try {
            $admin = $this->getLoggedInAdmin();
            $lecturer = self::findLecturer($userID);
            if ($lecturer === null) {
                $this->jsonResponse(['error' => 'Lecturer not found'], 404);
                return;
            }

            $admin->editLecturer($lecturer, $data);
            $this->jsonResponse(['message' => 'Changes saved']);
        } catch (\InvalidArgumentException $e) {
            // these messages are written in Administrator for the page, nothing from the database
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Saving the lecturer failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to save the lecturer'], 500);
        }
    }

    /**
     * DELETE /api/admin/lecturers/{id}, the Delete button in the confirm pop-up
     */
    public function deleteLecturer(string $userID): void
    {
        Authentication::requireRole(['admin']);

        try {
            $admin = $this->getLoggedInAdmin();
            $lecturer = self::findLecturer($userID);
            if ($lecturer === null) {
                $this->jsonResponse(['error' => 'Lecturer not found'], 404);
                return;
            }

            $admin->deleteLecturer($lecturer);
            $this->jsonResponse(['message' => 'Lecturer deleted']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            error_log('Deleting the lecturer failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Unable to delete the lecturer'], 500);
        }
    }

    /**
     * Finds the lecturer the button was pressed on, a student or an unknown ID gives null so the page gets a 404
     * public static so the tests can call it without a request
     */
    public static function findLecturer(string $userID): ?Lecturer
    {
        $user = User::findById($userID);

        return $user instanceof Lecturer ? $user : null;
    }

    /**
     * Reads isEnabled from what the page sent, anything that isn't true or false gives null so the page gets a 400
     */
    public static function readIsEnabled(array $data): ?bool
    {
        return is_bool($data['isEnabled'] ?? null) ? $data['isEnabled'] : null;
    }

    /**
     * Emails the set password link with PasswordSetup and Mailer, true if it went
     * token is null for a brand new account, it gets made here once the account exists
     *
     * @param array{userID?: string, staffID: string, email: string, firstName: string, role: string} $account
     */
    private static function emailSetupLink(array $account, ?string $token): bool
    {
        try {
            $token ??= PasswordSetup::createToken($account['userID'] ?? '');

            return Mailer::sendPasswordSetupEmail(
                $account['email'],
                $account['firstName'],
                $account['staffID'],
                $token,
                $account['role']
            );
        } catch (\Throwable $e) {
            // logged for us, the admin just sees that the email didn't go
            error_log('Sending the set password email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Loads the admin who's logged in as an Administrator object so the change goes through their methods
     */
    private function getLoggedInAdmin(): Administrator
    {
        $userID = Authentication::getLoggedInUserID();
        $admin = $userID === null ? null : User::findById($userID);

        // requireRole already checked this so it shouldn't fail, if it does it ends up as a 500
        if (!($admin instanceof Administrator)) {
            throw new \RuntimeException('The logged in admin could not be loaded');
        }

        return $admin;
    }
}
