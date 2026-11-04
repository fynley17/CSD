<?php

namespace App\Controllers;

use App\Models\PasswordSetup;

class PasswordSetupController extends Controller
{
    /**
     * Completes password setup using the emailed token.
     */
    public function setPassword(): void
    {
        $data = $this->readJsonBody();

        $token = $data['token'] ?? null;
        $password = $data['password'] ?? null;
        $confirmPassword = $data['confirmPassword'] ?? null;

        if (!PasswordSetup::isValidToken($token)) {
            self::jsonResponse([
                'success' => false,
                'error' => 'This password setup link is invalid or has expired.',
            ], 400);
        }

        if (!is_string($password) || !is_string($confirmPassword)) {
            self::jsonResponse([
                'success' => false,
                'error' => 'Please enter and confirm your password.',
            ], 400);
        }

        if ($password !== $confirmPassword) {
            self::jsonResponse([
                'success' => false,
                'error' => 'Your passwords do not match.',
            ], 400);
        }

        try {
            $success = PasswordSetup::setPassword($token, $password);

            if (!$success) {
                self::jsonResponse([
                    'success' => false,
                    'error' => 'This password setup link is invalid, expired or no longer available.',
                ], 400);
            }

            self::jsonResponse([
                'success' => true,
                'message' => 'Your password has been set. You can now log in.',
            ]);
        } catch (\InvalidArgumentException $e) {
            self::jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            error_log('Password setup failed: ' . $e->getMessage());

            self::jsonResponse([
                'success' => false,
                'error' => 'Something went wrong while setting your password. Please try again.',
            ], 500);
        }
    }

    /** @return array<string, mixed> */
    private function readJsonBody(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Please provide valid request details.');
        }

        return $data;
    }
}
