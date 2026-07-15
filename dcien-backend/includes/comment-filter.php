<?php
/**
 * Filtro heurístico de contenido ofensivo para comentarios del blog.
 * Es una lista de palabras, no moderación con IA: atrapa lo obvio,
 * no matices ni faltas de respeto sutiles. Sirve para marcar y
 * ocultar automáticamente lo más evidente hasta revisión manual.
 */

const OFFENSIVE_TERMS = [
    'idiota', 'imbecil', 'gilipollas', 'estupido', 'estupida',
    'subnormal', 'retrasado', 'mierda', 'puta', 'puto', 'zorra',
    'maricon', 'maricón', 'hijo de puta', 'cabron', 'cabrón',
    'joder', 'polla', 'coño', 'capullo',
];

function normalizeForFilter(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $replacements = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
    ];
    $text = strtr($text, $replacements);
    // Colapsa espacios/puntuación repetida usada para evadir el filtro (ej. "i.d.i.o.t.a")
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/**
 * Devuelve el término detectado si el texto contiene contenido ofensivo, o null si no.
 */
function containsOffensiveContent(string $text): ?string {
    $normalized = normalizeForFilter($text);

    foreach (OFFENSIVE_TERMS as $term) {
        $normalizedTerm = normalizeForFilter($term);
        if ($normalizedTerm !== '' && str_contains($normalized, $normalizedTerm)) {
            return $term;
        }
    }

    return null;
}
