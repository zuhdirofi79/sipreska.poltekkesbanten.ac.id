<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $eventForm->title }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f5;
            margin: 0;
            padding: 40px 16px;
        }

        .container {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo img,
        .logo .sipreska-logo {
            max-width: 320px;
            height: auto;
        }

        .sipreska-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 100%;
        }

        .sipreska-logo-default {
            max-width: 320px;
            height: auto;
            object-fit: contain;
        }

        .sipreska-logo-fallback-default {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 4rem;
            height: 4rem;
            border-radius: 0.75rem;
            background: #eef2ff;
            color: #1d4ed8;
        }

        .sipreska-logo-fallback-icon {
            width: 55%;
            height: 55%;
        }

        h1 {
            text-align: center;
            color: #5bb7b3;
            font-size: 24px;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .description {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }

        hr {
            border: none;
            border-top: 1px solid #ddd;
            margin: 24px 0 32px;
        }

        .success {
            background: #e9f8ea;
            color: #2f6f3a;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }

        .field {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #222;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #d8d8d8;
            border-radius: 10px;
            font-size: 16px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 110px;
        }

        .error {
            color: #c62828;
            font-size: 14px;
            margin-top: 6px;
        }

        .submit-btn {
            width: 100%;
            border: none;
            background: #59b9b3;
            color: white;
            font-size: 18px;
            font-weight: 700;
            border-radius: 10px;
            padding: 16px;
            cursor: pointer;
            margin-top: 16px;
        }

        .submit-btn:hover {
            background: #48aaa4;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">
        <x-sipreska-logo variant="default" />
    </div>

    <h1>{{ $eventForm->title }}</h1>

    @if ($eventForm->description)
        <div class="description">{{ $eventForm->description }}</div>
    @endif

    <hr>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('public-form.submit', $eventForm->slug) }}">
        @csrf

        @foreach ($eventForm->fields as $field)
            <div class="field">
                <label for="{{ $field->name }}">
                    {{ $field->label }}
                    @if ($field->is_required) * @endif
                </label>

                @if ($field->type === 'textarea')
                    <textarea id="{{ $field->name }}" name="{{ $field->name }}">{{ old($field->name) }}</textarea>

                @elseif ($field->type === 'select')
                    <select id="{{ $field->name }}" name="{{ $field->name }}">
                        <option value="">-- Pilih --</option>
                        @foreach (($field->options ?? []) as $option)
                            <option value="{{ $option }}" @selected(old($field->name) === $option)>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>

                @else
                    <input
                        id="{{ $field->name }}"
                        type="{{ in_array($field->type, ['text', 'email', 'number', 'date']) ? $field->type : 'text' }}"
                        name="{{ $field->name }}"
                        value="{{ old($field->name) }}"
                    >
                @endif

                @error($field->name)
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
        @endforeach

        <button class="submit-btn" type="submit">DAFTAR SEKARANG</button>
    </form>
</div>
</body>
</html>