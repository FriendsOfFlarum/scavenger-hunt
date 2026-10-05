<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('fof-scavenger-hunt.email.hunt_completed.plain.body', [
'{hunt}' => $blueprint->hunt->title,
'{url}' => $url->to('forum')->route('fof-scavenger-hunt.hunt', ['slug' => $blueprint->hunt->slug]),
]) !!}
</x-slot:body>
</x-mail::plain.notification>
