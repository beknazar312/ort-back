<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Test;
use Illuminate\Database\Seeder;

class OrtDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create subjects
        $subjects = [
            [
                'name' => 'Математика',
                'slug' => 'math',
                'icon' => '🔢',
                'color' => '#3b82f6',
                'description' => 'Алгебра, геометрия, арифметика',
                'sort_order' => 1,
            ],
            [
                'name' => 'Аналогии и дополнения',
                'slug' => 'analogies',
                'icon' => '🧩',
                'color' => '#8b5cf6',
                'description' => 'Логические связи между словами',
                'sort_order' => 2,
            ],
            [
                'name' => 'Грамматика',
                'slug' => 'grammar',
                'icon' => '📝',
                'color' => '#10b981',
                'description' => 'Русский и кыргызский языки',
                'sort_order' => 3,
            ],
            [
                'name' => 'Понимание текста',
                'slug' => 'reading',
                'icon' => '📖',
                'color' => '#f59e0b',
                'description' => 'Анализ и понимание прочитанного',
                'sort_order' => 4,
            ],
            [
                'name' => 'Практическая математика',
                'slug' => 'practical-math',
                'icon' => '📊',
                'color' => '#ef4444',
                'description' => 'Задачи на логику и практику',
                'sort_order' => 5,
            ],
        ];

        foreach ($subjects as $subjectData) {
            Subject::updateOrCreate(
                ['slug' => $subjectData['slug']],
                $subjectData
            );
        }

        // Math questions
        $mathSubject = Subject::where('slug', 'math')->first();
        $this->createMathQuestions($mathSubject);

        // Analogies questions
        $analogiesSubject = Subject::where('slug', 'analogies')->first();
        $this->createAnalogiesQuestions($analogiesSubject);

        // Grammar questions
        $grammarSubject = Subject::where('slug', 'grammar')->first();
        $this->createGrammarQuestions($grammarSubject);

        // Create a sample test
        $this->createSampleTest($mathSubject);
    }

    private function createMathQuestions(Subject $subject): void
    {
        $questions = [
            [
                'text' => 'Решите уравнение: 2x + 5 = 13',
                'difficulty' => 'easy',
                'explanation' => '2x + 5 = 13
2x = 13 - 5
2x = 8
x = 4',
                'answers' => [
                    ['text' => 'x = 4', 'is_correct' => true],
                    ['text' => 'x = 3', 'is_correct' => false],
                    ['text' => 'x = 5', 'is_correct' => false],
                    ['text' => 'x = 9', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Найдите площадь прямоугольника со сторонами 5 см и 8 см.',
                'difficulty' => 'easy',
                'explanation' => 'Площадь прямоугольника = длина × ширина = 5 × 8 = 40 см²',
                'answers' => [
                    ['text' => '40 см²', 'is_correct' => true],
                    ['text' => '26 см²', 'is_correct' => false],
                    ['text' => '13 см²', 'is_correct' => false],
                    ['text' => '80 см²', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Чему равно значение выражения: 15% от 200?',
                'difficulty' => 'easy',
                'explanation' => '15% от 200 = (15/100) × 200 = 0.15 × 200 = 30',
                'answers' => [
                    ['text' => '30', 'is_correct' => true],
                    ['text' => '15', 'is_correct' => false],
                    ['text' => '20', 'is_correct' => false],
                    ['text' => '35', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Решите систему уравнений:
x + y = 10
x - y = 4',
                'difficulty' => 'medium',
                'explanation' => 'Сложим оба уравнения:
(x + y) + (x - y) = 10 + 4
2x = 14
x = 7

Подставим x = 7 в первое уравнение:
7 + y = 10
y = 3

Ответ: x = 7, y = 3',
                'answers' => [
                    ['text' => 'x = 7, y = 3', 'is_correct' => true],
                    ['text' => 'x = 6, y = 4', 'is_correct' => false],
                    ['text' => 'x = 8, y = 2', 'is_correct' => false],
                    ['text' => 'x = 5, y = 5', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Найдите корни квадратного уравнения: x² - 5x + 6 = 0',
                'difficulty' => 'medium',
                'explanation' => 'Используем теорему Виета или формулу дискриминанта.
По теореме Виета: x₁ + x₂ = 5, x₁ × x₂ = 6
Подходят числа 2 и 3.
Проверка: 2 + 3 = 5, 2 × 3 = 6 ✓',
                'answers' => [
                    ['text' => 'x = 2 и x = 3', 'is_correct' => true],
                    ['text' => 'x = 1 и x = 6', 'is_correct' => false],
                    ['text' => 'x = -2 и x = -3', 'is_correct' => false],
                    ['text' => 'x = 5 и x = 1', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Упростите выражение: (a + b)² - (a - b)²',
                'difficulty' => 'hard',
                'explanation' => '(a + b)² - (a - b)² =
= (a² + 2ab + b²) - (a² - 2ab + b²) =
= a² + 2ab + b² - a² + 2ab - b² =
= 4ab',
                'answers' => [
                    ['text' => '4ab', 'is_correct' => true],
                    ['text' => '2ab', 'is_correct' => false],
                    ['text' => '2a² + 2b²', 'is_correct' => false],
                    ['text' => '0', 'is_correct' => false],
                ],
            ],
        ];

        $this->createQuestions($subject, $questions);
    }

    private function createAnalogiesQuestions(Subject $subject): void
    {
        $questions = [
            [
                'text' => 'Врач : Больница = Учитель : ?',
                'difficulty' => 'easy',
                'explanation' => 'Врач работает в больнице, учитель работает в школе. Это аналогия "профессия : место работы".',
                'answers' => [
                    ['text' => 'Школа', 'is_correct' => true],
                    ['text' => 'Ученик', 'is_correct' => false],
                    ['text' => 'Урок', 'is_correct' => false],
                    ['text' => 'Книга', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Птица : Перо = Рыба : ?',
                'difficulty' => 'easy',
                'explanation' => 'Тело птицы покрыто перьями, тело рыбы покрыто чешуёй. Это аналогия "животное : покров тела".',
                'answers' => [
                    ['text' => 'Чешуя', 'is_correct' => true],
                    ['text' => 'Плавник', 'is_correct' => false],
                    ['text' => 'Вода', 'is_correct' => false],
                    ['text' => 'Жабры', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Горячий : Холодный = Высокий : ?',
                'difficulty' => 'easy',
                'explanation' => 'Горячий и холодный — антонимы. Антоним слова "высокий" — "низкий".',
                'answers' => [
                    ['text' => 'Низкий', 'is_correct' => true],
                    ['text' => 'Большой', 'is_correct' => false],
                    ['text' => 'Длинный', 'is_correct' => false],
                    ['text' => 'Широкий', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Слово : Предложение = Нота : ?',
                'difficulty' => 'medium',
                'explanation' => 'Слова составляют предложение, ноты составляют мелодию. Это аналогия "часть : целое".',
                'answers' => [
                    ['text' => 'Мелодия', 'is_correct' => true],
                    ['text' => 'Музыка', 'is_correct' => false],
                    ['text' => 'Звук', 'is_correct' => false],
                    ['text' => 'Инструмент', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Глаз : Зрение = Ухо : ?',
                'difficulty' => 'easy',
                'explanation' => 'Глаз — орган зрения, ухо — орган слуха. Это аналогия "орган : функция".',
                'answers' => [
                    ['text' => 'Слух', 'is_correct' => true],
                    ['text' => 'Звук', 'is_correct' => false],
                    ['text' => 'Речь', 'is_correct' => false],
                    ['text' => 'Голова', 'is_correct' => false],
                ],
            ],
        ];

        $this->createQuestions($subject, $questions);
    }

    private function createGrammarQuestions(Subject $subject): void
    {
        $questions = [
            [
                'text' => 'В каком слове допущена орфографическая ошибка?',
                'difficulty' => 'easy',
                'explanation' => 'Правильное написание: "преподаватель" (приставка "пре-" в значении "очень").',
                'answers' => [
                    ['text' => 'Приподаватель', 'is_correct' => true],
                    ['text' => 'Преподаватель', 'is_correct' => false],
                    ['text' => 'Учитель', 'is_correct' => false],
                    ['text' => 'Наставник', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Укажите предложение с грамматической ошибкой:',
                'difficulty' => 'medium',
                'explanation' => 'Правильно: "Благодаря помощи друзей" (предлог "благодаря" требует дательного падежа).',
                'answers' => [
                    ['text' => 'Благодаря помощи друзей, я справился.', 'is_correct' => false],
                    ['text' => 'Благодаря помощь друзей, я справился.', 'is_correct' => true],
                    ['text' => 'Я справился благодаря друзьям.', 'is_correct' => false],
                    ['text' => 'Друзья помогли мне справиться.', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'Какое слово является синонимом слова "смелый"?',
                'difficulty' => 'easy',
                'explanation' => 'Синонимы — слова с близким значением. "Храбрый" — синоним слова "смелый".',
                'answers' => [
                    ['text' => 'Храбрый', 'is_correct' => true],
                    ['text' => 'Трусливый', 'is_correct' => false],
                    ['text' => 'Умный', 'is_correct' => false],
                    ['text' => 'Добрый', 'is_correct' => false],
                ],
            ],
            [
                'text' => 'В каком ряду все слова пишутся через дефис?',
                'difficulty' => 'hard',
                'explanation' => 'Через дефис пишутся: по-русски (наречие с приставкой по- и суффиксом -и), кое-что (неопределённое местоимение с кое-), северо-запад (сложное существительное со сторонами света).',
                'answers' => [
                    ['text' => 'по-русски, кое-что, северо-запад', 'is_correct' => true],
                    ['text' => 'по-новому, что-то, пол-яблока', 'is_correct' => false],
                    ['text' => 'во-первых, кое-как, полчаса', 'is_correct' => false],
                    ['text' => 'по-моему, кто-нибудь, полдень', 'is_correct' => false],
                ],
            ],
        ];

        $this->createQuestions($subject, $questions);
    }

    private function createQuestions(Subject $subject, array $questionsData): void
    {
        foreach ($questionsData as $questionData) {
            $answers = $questionData['answers'];
            unset($questionData['answers']);

            $question = Question::updateOrCreate(
                [
                    'subject_id' => $subject->id,
                    'text' => $questionData['text'],
                ],
                array_merge($questionData, ['subject_id' => $subject->id])
            );

            foreach ($answers as $index => $answerData) {
                Answer::updateOrCreate(
                    [
                        'question_id' => $question->id,
                        'text' => $answerData['text'],
                    ],
                    array_merge($answerData, [
                        'question_id' => $question->id,
                        'sort_order' => $index,
                    ])
                );
            }
        }
    }

    private function createSampleTest(Subject $subject): void
    {
        $test = Test::updateOrCreate(
            ['name' => 'Пробный тест по математике'],
            [
                'name' => 'Пробный тест по математике',
                'description' => 'Проверьте свои знания по математике',
                'subject_id' => $subject->id,
                'time_limit_minutes' => 30,
                'question_count' => 5,
                'is_active' => true,
            ]
        );

        // Attach questions to test
        $questions = Question::where('subject_id', $subject->id)->take(5)->get();
        $test->questions()->sync($questions->pluck('id'));
    }
}
