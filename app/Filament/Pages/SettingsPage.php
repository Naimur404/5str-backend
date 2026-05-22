<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $navigationGroup = 'System Settings';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.settings-page';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super-admin', 'admin']), 403);

        $service = app(SettingsService::class);

        $this->form->fill([
            'admin_title' => $service->get('admin_title'),
            'ai_enabled' => $service->get('ai_enabled'),
            'ai_api_token' => $service->get('ai_api_token'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branding')
                    ->description('Configure the admin panel branding visible on the login screen and navigation.')
                    ->schema([
                        Forms\Components\TextInput::make('admin_title')
                            ->label('Admin Panel Title')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('5SRT Admin'),
                        Forms\Components\FileUpload::make('admin_logo')
                            ->label('Admin Logo')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])
                            ->maxSize(2048) // 2MB
                            ->r2Storage('admin/branding')
                            ->imageEditor()
                            ->helperText('Recommended: PNG or SVG, max 2MB. Leave empty to keep current logo.'),
                    ])->columns(1),

                Forms\Components\Section::make('AI Configuration')
                    ->description('Enable AI features and configure the API token.')
                    ->schema([
                        Forms\Components\Toggle::make('ai_enabled')
                            ->label('Enable AI Features')
                            ->helperText('Toggle AI-powered features platform-wide. The API token is preserved when disabled.'),
                        Forms\Components\TextInput::make('ai_api_token')
                            ->label('AI API Token')
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->helperText('The API token for AI services. Encrypted at rest.'),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $service = app(SettingsService::class);

        $settings = [];

        // Handle title
        $settings['admin_title'] = $data['admin_title'];

        // Handle AI settings
        $settings['ai_enabled'] = $data['ai_enabled'] ?? false;

        // Only update token if provided (non-null means user touched the field)
        if (isset($data['ai_api_token'])) {
            $settings['ai_api_token'] = $data['ai_api_token'];
        }

        // Handle logo upload (Filament's FileUpload with r2Storage handles the upload,
        // we just need to save the resulting path/url)
        if (!empty($data['admin_logo'])) {
            // The FileUpload component with r2Storage returns the stored path
            $logoValue = is_array($data['admin_logo']) ? reset($data['admin_logo']) : $data['admin_logo'];
            if ($logoValue) {
                $settings['admin_logo'] = $logoValue;
            }
        }

        $service->set($settings);

        // Warn if AI enabled without token
        if ($settings['ai_enabled'] && empty($service->get('ai_api_token'))) {
            Notification::make()
                ->title('Settings saved')
                ->body('Warning: AI is enabled but no API token is configured. AI features will not function.')
                ->warning()
                ->send();
            return;
        }

        Notification::make()
            ->title('Settings saved successfully!')
            ->success()
            ->send();
    }
}
