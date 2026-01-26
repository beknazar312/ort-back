<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use App\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Вопросы';

    protected static ?string $modelLabel = 'Вопрос';

    protected static ?string $pluralModelLabel = 'Вопросы';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Вопрос')
                    ->schema([
                        Forms\Components\Select::make('subject_id')
                            ->label('Предмет')
                            ->options(Subject::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Textarea::make('text')
                            ->label('Текст вопроса')
                            ->required()
                            ->rows(4),

                        Forms\Components\Select::make('difficulty')
                            ->label('Сложность')
                            ->options([
                                'easy' => 'Легкий',
                                'medium' => 'Средний',
                                'hard' => 'Сложный',
                            ])
                            ->default('medium')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Варианты ответов')
                    ->schema([
                        Forms\Components\Repeater::make('answers')
                            ->label('Ответы')
                            ->relationship()
                            ->schema([
                                Forms\Components\Textarea::make('text')
                                    ->label('Текст ответа')
                                    ->required()
                                    ->rows(2),

                                Forms\Components\Toggle::make('is_correct')
                                    ->label('Правильный')
                                    ->default(false),

                                Forms\Components\Hidden::make('sort_order')
                                    ->default(fn ($get) => $get('../../answers') ? count($get('../../answers')) : 0),
                            ])
                            ->columns(1)
                            ->defaultItems(4)
                            ->minItems(2)
                            ->maxItems(6)
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['text'] ?? null),
                    ]),

                Forms\Components\Section::make('Объяснение')
                    ->schema([
                        Forms\Components\Textarea::make('explanation')
                            ->label('Объяснение правильного ответа')
                            ->rows(4)
                            ->helperText('Показывается после ответа на вопрос'),
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

                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Предмет')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('text')
                    ->label('Вопрос')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('difficulty')
                    ->label('Сложность')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'easy' => 'success',
                        'medium' => 'warning',
                        'hard' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'easy' => 'Легкий',
                        'medium' => 'Средний',
                        'hard' => 'Сложный',
                    }),

                Tables\Columns\TextColumn::make('answers_count')
                    ->label('Ответов')
                    ->counts('answers'),

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

                Tables\Filters\SelectFilter::make('difficulty')
                    ->label('Сложность')
                    ->options([
                        'easy' => 'Легкий',
                        'medium' => 'Средний',
                        'hard' => 'Сложный',
                    ]),

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
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
