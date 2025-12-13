<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.pages.profile';

    protected static ?string $navigationLabel = 'Профіль';

    protected static ?int $navigationSort = 1000;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'slug' => auth()->user()->slug,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Інформація профілю')
                        ->schema([
                            TextInput::make('name')
                                ->label('Ім\'я')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('slug')
                                ->label('Slug')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(2),
                    Section::make('Зміна паролю')
                        ->schema([
                            TextInput::make('current_password')
                                ->label('Поточний пароль')
                                ->password()
                                ->required(fn ($get) => ! empty($get('password')))
                                ->currentPassword()
                                ->dehydrated(false),
                            TextInput::make('password')
                                ->label('Новий пароль')
                                ->password()
                                ->rule(Password::default())
                                ->dehydrated(fn ($state) => filled($state))
                                ->same('password_confirmation'),
                            TextInput::make('password_confirmation')
                                ->label('Підтвердження паролю')
                                ->password()
                                ->required(fn ($get) => ! empty($get('password')))
                                ->dehydrated(false),
                        ])
                        ->columns(2)
                        ->visible(fn () => true),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Зберегти')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();

        if (isset($data['password']) && ! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        Notification::make()
            ->title('Профіль оновлено')
            ->success()
            ->send();

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'slug' => $user->slug,
        ]);
    }
}
