<x-mail::message>
# {{ $titulo }}

{{ $mensaje }}

@if (!empty($detalles))
@foreach ($detalles as $etiqueta => $valor)
**{{ $etiqueta }}:** {{ $valor }}
@endforeach
@endif

@if ($traza)
<x-mail::panel>
{{ $traza }}
</x-mail::panel>
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
