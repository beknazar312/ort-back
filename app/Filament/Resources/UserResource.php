<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Имя')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create'),
                    ]),

                Forms\Components\Section::make('Telegram')
                    ->schema([
                        Forms\Components\TextInput::make('telegram_id')
                            ->label('Telegram ID')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->disabled(),

                        Forms\Components\TextInput::make('first_name')
                            ->label('Имя')
                            ->disabled(),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Фамилия')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Роль')
                    ->schema([
                        Forms\Components\Toggle::make('is_admin')
                            ->label('Администратор')
                            ->helperText('Дает доступ к админ-панели'),

                        Forms\Components\Toggle::make('is_premium')
                            ->label('Премиум')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('username')
                    ->label('Telegram')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? "@{$state}" : '-'),

                Tables\Columns\IconColumn::make('is_admin')
                    ->label('Админ')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_premium')
                    ->label('Премиум')
                    ->boolean(),

                Tables\Columns\TextColumn::make('test_attempts_count')
                    ->label('Попыток')
                    ->counts('testAttempts'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Регистрация')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_admin')
                    ->label('Администратор'),

                Tables\Filters\TernaryFilter::make('is_premium')
                    ->label('Премиум'),

                Tables\Filters\Filter::make('has_telegram')
                    ->label('С Telegram')
                    ->query(fn ($query) => $query->whereNotNull('telegram_id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
