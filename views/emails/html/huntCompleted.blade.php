<x-mail::html.notification>
    <x-slot:body>
        {!! $formatter->convert($translator->trans('fof-scavenger-hunt.email.hunt_completed.html.body', [
            '{hunt}' => $blueprint->hunt->title,
            '{url}' => $url->to('forum')->route('fof-scavenger-hunt.hunt', ['slug' => $blueprint->hunt->slug]),
        ])) !!}
    </x-slot:body>
</x-mail::html.notification>
