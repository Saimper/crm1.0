<article class="workspace-activity" wire:key="reciente-{{ $g->id }}">
    <div class="workspace-activity-heading">
        <h4>{{ $g->resultado_nombre ?? '—' }}</h4>
        <time>{{ hora_local($g->creada_en, 'd/m H:i') }}</time>
    </div>
    @if($g->notas)<p>{{ $g->notas }}</p>@endif
    <span class="workspace-activity-author">{{ $g->canal_nombre ?? '—' }} · {{ $g->usuario_nombre ?? '—' }}</span>
</article>
