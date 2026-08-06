<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ContactFormMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

final class ContactFormController extends Controller
{
    private const RECIPIENT = 'help@jaromir.com.ua';

    public function __invoke(Request $request): JsonResponse
    {
        // Honeypot: return a neutral success response without sending anything.
        if ($request->filled('website')) {
            return response()->json([
                'message' => 'Ваше звернення прийнято.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'organization' => ['nullable', 'string', 'max:190'],
            'topic' => [
                'required',
                Rule::in([
                    'membership',
                    'community',
                    'ritual',
                    'event',
                    'cooperation',
                    'other',
                ]),
            ],
            'message' => ['required', 'string', 'min:20', 'max:4000'],
            'consent' => ['accepted'],
        ], [
            'name.required' => 'Вкажіть ім’я та прізвище.',
            'name.min' => 'Ім’я та прізвище мають містити щонайменше 2 символи.',
            'email.required' => 'Вкажіть електронну пошту.',
            'email.email' => 'Вкажіть коректну електронну адресу.',
            'phone.regex' => 'Вкажіть коректний номер телефону.',
            'topic.required' => 'Оберіть тему звернення.',
            'topic.in' => 'Оберіть тему звернення зі списку.',
            'message.required' => 'Напишіть текст звернення.',
            'message.min' => 'Текст звернення має містити щонайменше 20 символів.',
            'message.max' => 'Текст звернення не може перевищувати 4000 символів.',
            'consent.accepted' => 'Потрібно погодитися на обробку вказаних даних.',
        ]);

        $topicLabels = [
            'membership' => 'Вступ до Духовного центру',
            'community' => 'Створення або приєднання громади',
            'ritual' => 'Проведення обряду чи святодії',
            'event' => 'Лекція, зустріч або інший захід',
            'cooperation' => 'Співпраця',
            'other' => 'Інше питання',
        ];

        $validated['topic_label'] = $topicLabels[$validated['topic']];
        $validated['submitted_at'] = now()->format('d.m.Y H:i');
        $validated['source_url'] = route('contact');
        $validated['ip'] = (string) $request->ip();

        try {
            Mail::to(self::RECIPIENT)->send(new ContactFormMessage($validated));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не вдалося надіслати звернення. Спробуйте ще раз трохи пізніше.',
            ], 500);
        }

        return response()->json([
            'message' => 'Звернення успішно надіслано Управі. Відповідь надійде на вказану електронну пошту.',
        ]);
    }
}
