<?php

namespace App\Enums;

enum ExternalActivityQuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Number = 'number';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Date = 'date';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Textarea => 'Paragraf',
            self::Email => 'Email',
            self::Number => 'Angka',
            self::Select => 'Pilihan (Select)',
            self::Radio => 'Pilihan (Radio)',
            self::Checkbox => 'Kotak Centang',
            self::Date => 'Tanggal',
        };
    }

    public function requiresOptions(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox], true);
    }

    /** @return list<string> */
    public function answerRules(bool $required): array
    {
        $rules = [$required ? 'required' : 'nullable'];

        return match ($this) {
            self::Email => [...$rules, 'email:rfc', 'max:255'],
            self::Number => [...$rules, 'integer'],
            self::Date => [...$rules, 'date'],
            self::Textarea => [...$rules, 'string', 'max:5000'],
            self::Checkbox => [$required ? 'required' : 'nullable', 'array'],
            self::Text, self::Select, self::Radio => [...$rules, 'string', 'max:255'],
        };
    }

    public function inputType(): string
    {
        return match ($this) {
            self::Email => 'email',
            self::Number => 'number',
            self::Date => 'date',
            default => 'text',
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
