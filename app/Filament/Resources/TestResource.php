<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestResource\Pages;
use App\Models\Test;
use App\Models\Subject;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestResource extends Resource
{
    protected static ?string $model = Test::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Тесты';

    protected static ?string $modelLabel = 'Тест';

    protected static ?string $pluralModelLabel = 'Тесты';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Описание')
                            ->rows(3),

                        Forms\Components\Select::make('subject_id')
                            ->label('Предмет')
                            ->options(Subject::pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Оставьте пустым для смешанного теста'),
                    ]),

                Forms\Components\Section::make('Настройки')
                    ->schema([
                        Forms\Components\TextInput::make('time_limit_minutes')
                            ->label('Время (минуты)')
                            ->numeric()
                            ->default(60)
                            ->required()
                            ->minValue(1)
                            ->maxValue(180),

                        Forms\Components\TextInput::make('question_count')
                            ->label('Количество вопросов')
                            ->numeric()
                            ->default(20)
                            ->required()
                            ->minValue(1)
                            ->maxValue(100),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ])->columns(3),

                Forms\Components\Section::make('Вопросы теста')
                    ->schema([
                        Forms\Components\Select::make('questions')
                            ->label('Выберите вопросы')
                            ->relationship('questions', 'text')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(fn (Question $record) => "[{$record->subject->name}] " . \Illuminate\Support\Str::limit($record->text, 80)),
                    ]),
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
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Предмет')
                    ->placeholder('Смешанный')
                    ->sortable(),

                Tables\Columns\TextColumn::make('time_limit_minutes')
                    ->label('Время')
                    ->suffix(' мин')
                    ->sortable(),

                Tables\Columns\TextColumn::make('question_count')
                    ->label('Вопросов')
                    ->sortable(),

                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Привязано')
                    ->counts('questions'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('subject_id')
                    ->label('Предмет')
                    ->options(Subject::pluck('name', 'id')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListTests::route('/'),
            'create' => Pages\CreateTest::route('/create'),
            'edit' => Pages\EditTest::route('/{record}/edit'),
        ];
    }
}
