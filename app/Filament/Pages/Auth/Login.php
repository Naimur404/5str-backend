<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Http\Request;

class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return '5SRT Business Discovery - Admin Login';
    }

    public function getHeading(): string
    {
        return '5SRT Business Discovery';
    }

    public function getSubheading(): string
    {
        return 'Admin Panel Access';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getView(): string
    {
        return 'filament.pages.auth.login';
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }
}
