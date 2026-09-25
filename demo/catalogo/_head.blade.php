<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">
<style>{!! $css !!}</style>
{{-- `defer` no aplica a scripts inline: se arranca al terminar de leer el documento. --}}
@if ($alpine)<script>document.addEventListener('DOMContentLoaded', function () { {!! $alpine !!} });</script>@endif
