import './bootstrap';

// ── Puente con el wrapper (ViciDial) ─────────────────────────────────────────
// Cuando el CRM se embebe como iframe dentro del wrapper, reenviamos los eventos
// Livewire `crm-sync` a la ventana padre para que refleje las ediciones en el
// lead activo de ViciDial. Solo se postea al origin firmado del wrapper
// (meta[name="wrapper-origin"], puesto desde el claim del handshake) — nunca '*'.
document.addEventListener('livewire:init', () => {
    window.Livewire.on('crm-sync', (payload) => {
        if (window.parent === window) return; // no embebido

        const target = document
            .querySelector('meta[name="wrapper-origin"]')
            ?.getAttribute('content');
        if (!target) return;

        const data = Array.isArray(payload) ? payload[0] : payload;
        if (!data || typeof data !== 'object') return;

        window.parent.postMessage(
            {
                source: 'crm',
                type: 'CRM_FIELD_SYNC',
                tipo: data.tipo ?? null,
                cambios: data.cambios ?? {},
                // Pivote estable de la entidad editada (identificación de la
                // persona). El wrapper lo coteja con el lead en llamada para no
                // escribir si el agente navegó a otra ficha.
                pivote: data.pivote ?? null,
            },
            target,
        );
    });
});
