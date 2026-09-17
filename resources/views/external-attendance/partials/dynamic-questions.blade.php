@php
    use App\Enums\ExternalActivityQuestionType;
@endphp

@foreach ($questions as $question)
    @php
        $fieldName = 'custom_answers.'.$question->id;
        $inputName = 'custom_answers['.$question->id.']';
        $oldValue = old($fieldName);
        $options = $question->allowedOptions();
    @endphp

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700" for="custom-answer-{{ $question->id }}">
            {{ $question->label }}
            @if ($question->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>

        @switch ($question->type)
            @case (ExternalActivityQuestionType::Textarea)
                <textarea
                    id="custom-answer-{{ $question->id }}"
                    name="{{ $inputName }}"
                    rows="3"
                    class="sipreska-textarea"
                    @if ($question->is_required) required @endif
                >{{ $oldValue }}</textarea>
                @break

            @case (ExternalActivityQuestionType::Select)
                <select
                    id="custom-answer-{{ $question->id }}"
                    name="{{ $inputName }}"
                    class="sipreska-select"
                    @if ($question->is_required) required @endif
                >
                    <option value="">Pilih</option>
                    @foreach ($options as $option)
                        <option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @break

            @case (ExternalActivityQuestionType::Radio)
                <div class="space-y-2">
                    @foreach ($options as $optionIndex => $option)
                        <label class="flex items-center gap-2 text-sm text-gray-800">
                            <input
                                type="radio"
                                name="{{ $inputName }}"
                                value="{{ $option }}"
                                class="border-gray-300"
                                @checked((string) $oldValue === (string) $option)
                                @if ($question->is_required) required @endif
                            >
                            {{ $option }}
                        </label>
                    @endforeach
                </div>
                @break

            @case (ExternalActivityQuestionType::Checkbox)
                @php $oldChecked = collect(old($fieldName, [])); @endphp
                <div class="space-y-2">
                    @foreach ($options as $option)
                        <label class="flex items-center gap-2 text-sm text-gray-800">
                            <input
                                type="checkbox"
                                name="{{ $inputName }}[]"
                                value="{{ $option }}"
                                class="rounded border-gray-300"
                                @checked($oldChecked->contains($option))
                            >
                            {{ $option }}
                        </label>
                    @endforeach
                </div>
                @break

            @default
                <input
                    id="custom-answer-{{ $question->id }}"
                    type="{{ $question->type->inputType() }}"
                    name="{{ $inputName }}"
                    value="{{ $oldValue }}"
                    class="sipreska-input"
                    @if ($question->is_required) required @endif
                >
        @endswitch

        @error($fieldName)
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error($fieldName.'.*')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
@endforeach
