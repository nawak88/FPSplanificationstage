<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

abstract class SubmissionPage extends PublicPage
{
    public ?array $data = [];

    protected function submitForm(callable $submit, int $limit): void
    {
        $key = static::class . ':' . (auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw ValidationException::withMessages([
                'data' => 'Trop de tentatives. Veuillez patienter une minute.',
            ]);
        }
        RateLimiter::hit($key, 60);
        $data = $this->form->getState();
        try {
            $url = $submit($data);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors['data.' . $field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        $this->redirect($url);
    }
}
