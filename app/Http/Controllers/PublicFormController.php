<?php

namespace App\Http\Controllers;

use App\Models\EventForm;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use Illuminate\Http\Request;

class PublicFormController extends Controller
{
    public function show(string $slug)
    {
        $eventForm = EventForm::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        return view('public-form.show', compact('eventForm'));
    }

    public function submit(Request $request, string $slug)
    {
        $eventForm = EventForm::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        $rules = [];

        foreach ($eventForm->fields as $field) {
            $fieldRules = [];

            if ($field->is_required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($field->type === 'email') {
                $fieldRules[] = 'email';
            }

            if ($field->type === 'number') {
                $fieldRules[] = 'numeric';
            }

            $rules[$field->name] = $fieldRules;
        }

        $validated = $request->validate($rules);

        $submission = Submission::create([
            'event_form_id' => $eventForm->id,
            'submitted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        foreach ($eventForm->fields as $field) {
            SubmissionAnswer::create([
                'submission_id' => $submission->id,
                'form_field_id' => $field->id,
                'value' => $validated[$field->name] ?? null,
            ]);
        }

        return redirect()
            ->route('public-form.show', $eventForm->slug)
            ->with('success', 'Pendaftaran Anda berhasil dikirim.');
    }
}