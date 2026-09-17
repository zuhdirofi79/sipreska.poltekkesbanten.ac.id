@php
    use App\Enums\ExternalActivityQuestionType;
    use App\Services\ExternalActivityQuestionService;

    $defaultQuestionTemplates = $defaultQuestions ?? app(ExternalActivityQuestionService::class)->defaultQuestionTemplates();
    $oldQuestions = old('questions');
    $questionValues = is_array($oldQuestions)
        ? array_values($oldQuestions)
        : ($editing
            ? $activity->questions->map(fn ($question) => $question->toBuilderArray())->values()->all()
            : []);
    $questionsEnabled = (string) old('use_custom_questions', $editing ? (int) $activity->use_custom_questions : 0) === '1';
@endphp

<div
    class="md:col-span-2 space-y-4"
    x-data="externalQuestionBuilder(@js($questionsEnabled), @js($questionValues), @js($defaultQuestionTemplates))"
>
    <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Pertanyaan Tambahan Form Presensi</h3>
                <p class="mt-1 text-xs text-gray-500">
                    Jika diaktifkan, pertanyaan ini tampil pada form presensi publik kegiatan ini. Menonaktifkan fitur tidak menghapus konfigurasi yang sudah tersimpan.
                </p>
            </div>
            <div class="sm:w-56">
                <label class="mb-1 block text-sm font-medium text-gray-700">Gunakan pertanyaan tambahan</label>
                <select name="use_custom_questions" class="sipreska-select" x-model="enabled">
                    <option value="0">Nonaktif</option>
                    <option value="1">Aktif</option>
                </select>
            </div>
        </div>
        @error('use_custom_questions')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="isEnabled()" x-cloak class="space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Pertanyaan Form Presensi</h3>
            <p class="mt-1 text-xs text-gray-500">
                Atur label, tipe, kewajiban, status, dan urutan. Untuk select/radio/checkbox, tulis satu opsi per baris.
            </p>
        </div>

        <div class="space-y-3">
            <template x-for="(question, index) in questions" :key="question._key">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <input type="hidden" :name="'questions[' + index + '][id]'" :value="question.id || ''">
                    <input type="hidden" :name="'questions[' + index + '][default_key]'" :value="question.default_key || ''">

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium text-gray-800">
                            <span x-text="(index + 1) + '.'"></span>
                            <span x-text="question.label || 'Pertanyaan baru'"></span>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="sipreska-btn-secondary" @click="moveUp(index)" :disabled="index === 0">Naik</button>
                            <button type="button" class="sipreska-btn-secondary" @click="moveDown(index)" :disabled="index === questions.length - 1">Turun</button>
                            <button type="button" class="sipreska-btn-secondary" @click="remove(index)">Hapus</button>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Pertanyaan <span class="text-red-500">*</span></label>
                            <input type="text" class="sipreska-input" :name="'questions[' + index + '][label]'" x-model="question.label" maxlength="255" required>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Tipe</label>
                            <select class="sipreska-select" :name="'questions[' + index + '][type]'" x-model="question.type">
                                @foreach (ExternalActivityQuestionType::labels() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Wajib diisi</label>
                            <select class="sipreska-select" :name="'questions[' + index + '][is_required]'" x-model="question.is_required">
                                <option value="1">Wajib</option>
                                <option value="0">Opsional</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                            <select class="sipreska-select" :name="'questions[' + index + '][is_active]'" x-model="question.is_active">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>

                        <div class="md:col-span-2" x-show="needsOptions(question.type)">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Opsi (satu per baris)</label>
                            <textarea
                                class="sipreska-textarea"
                                rows="3"
                                :name="'questions[' + index + '][options_text]'"
                                x-model="question.options_text"
                                placeholder="Ya&#10;Tidak"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <button type="button" class="sipreska-btn-secondary" @click="add()">+ Tambah Pertanyaan</button>
        @error('questions')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error('questions.*')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error('questions.*.label')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error('questions.*.options_text')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
