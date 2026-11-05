<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
</head>
<body class="bg-slate-100 font-sans text-slate-800 m-0 p-4 md:p-8" style="background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 24px;">
    <div class="mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200" style="max-width: 1120px; width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);">
        
        <div class="h-2 bg-gradient-to-r from-red-600 via-rose-500 to-amber-500" style="height: 8px; background: linear-gradient(to right, #dc2626, #f43f5e, #f59e0b);"></div>

        <div class="p-6 md:p-10 border-b border-slate-100" style="padding: 32px; border-bottom: 1px solid #f1f5f9;">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-red-100 text-red-700 border border-red-200" style="display: inline-block; padding: 6px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;">
                    Fallo Crítico de Importación
                </span>
                <span class="text-xs text-slate-400 font-medium" style="font-size: 13px; color: #94a3b8;">
                    {{ now()->format('d/m/Y H:i:s') }}
                </span>
            </div>
            
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight mb-2" style="margin: 0 0 8px 0; font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.25;">
                {{ $titulo }}
            </h1>
            <p class="text-base text-slate-600 m-0" style="margin: 0; font-size: 15px; color: #475569; line-height: 1.5;">
                {{ $mensaje }}
            </p>
        </div>

        <div class="p-6 md:p-10 space-y-6" style="padding: 32px;">

            @if (!empty($errorMensaje))
            <div class="rounded-xl border border-red-200 bg-red-50/70 p-5" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <div class="flex items-start gap-3">
                    <div style="flex: 1;">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-red-800 m-0 mb-1" style="margin: 0 0 6px 0; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #991b1b;">
                            Error Detectado
                        </h2>
                        <div class="text-sm font-semibold text-red-950 font-mono leading-relaxed" style="font-size: 14px; font-weight: 600; color: #450a0a; font-family: monospace; line-height: 1.5;">
                            {{ $errorMensaje }}
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if (!empty($resumenIa))
            <div class="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50/80 via-white to-purple-50/50 p-6 shadow-sm" style="background: linear-gradient(135deg, #eef2ff 0%, #ffffff 50%, #faf5ff 100%); border: 1px solid #c7d2fe; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-600 text-white" style="display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; background-color: #4f46e5; color: #ffffff;">
                        IA Gemini 3.7 Flash
                    </span>
                    <h2 class="text-sm font-bold text-indigo-950 m-0" style="margin: 0; font-size: 15px; font-weight: 700; color: #1e1b4b;">
                        Diagnóstico y Causa Raíz
                    </h2>
                </div>
                <div class="text-sm text-slate-700 leading-relaxed space-y-2 whitespace-pre-line" style="font-size: 14px; color: #334155; line-height: 1.6; white-space: pre-line;">
                    {!! nl2br(e($resumenIa)) !!}
                </div>
            </div>
            @endif

            @if (!empty($detalles))
            <div class="mb-6" style="margin-bottom: 24px;">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3" style="margin: 0 0 12px 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">
                    Metadatos de la Importación
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                    @foreach ($detalles as $etiqueta => $valor)
                        @if ($etiqueta !== 'Error')
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px;">
                            <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1" style="display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px;">
                                {{ $etiqueta }}
                            </span>
                            <span class="block text-sm font-medium text-slate-800 break-words" style="display: block; font-size: 14px; font-weight: 600; color: #1e293b; word-break: break-word;">
                                {{ $valor }}
                            </span>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            @if (!empty($traza))
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3" style="margin: 0 0 12px 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">
                    Pila de Ejecución Técnica (Stack Trace)
                </h3>
                <div class="rounded-xl bg-slate-900 border border-slate-800 p-4 overflow-x-auto text-emerald-400 font-mono text-xs leading-relaxed" style="background-color: #0f172a; border: 1px solid #1e293b; border-radius: 10px; padding: 16px; overflow-x: auto; color: #34d399; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; line-height: 1.6; max-height: 380px; overflow-y: auto;">
                    <pre style="margin: 0; white-space: pre;">{{ $traza }}</pre>
                </div>
            </div>
            @endif

        </div>

        <div class="p-6 bg-slate-50 border-t border-slate-200 text-center text-xs text-slate-500" style="padding: 20px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #64748b;">
            Notificación del sistema {{ config('app.name') }} &bull; Entorno: {{ config('app.env') }}
        </div>

    </div>
</body>
</html>

