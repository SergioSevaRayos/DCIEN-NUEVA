import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';
import { SITE_CONFIG } from '../lib/seo';

// Convención emergente (no estándar oficial) para dar a los LLM/agentes de IA
// un resumen curado del sitio en texto plano. Se regenera en cada build a partir
// de la colección de contenido — no hace falta mantenerlo a mano.
export const GET: APIRoute = async ({ site }) => {
  const posts = (await getCollection('blog', ({ data }) => !data.draft)).sort(
    (a, b) => b.data.publishDate.valueOf() - a.data.publishDate.valueOf()
  );

  const lines = [
    `# ${SITE_CONFIG.name}`,
    '',
    `> ${SITE_CONFIG.description}`,
    '',
    `${SITE_CONFIG.name} es una marca española de streetwear premium de edición limitada para atletas híbridos (CrossFit, Hyrox, fuerza funcional). Cada serie se produce en un lote cerrado de 100 unidades numeradas físicamente, sin reediciones.`,
    '',
    '## Páginas principales',
    '',
    `- [Inicio](${SITE_CONFIG.url}/): presentación de la marca`,
    `- [Series activas](${SITE_CONFIG.url}/series-activas): catálogo de series numeradas disponibles actualmente`,
    `- [Archivo](${SITE_CONFIG.url}/archivo): series agotadas, registro histórico`,
    `- [Marca](${SITE_CONFIG.url}/marca): filosofía y protocolo de producción`,
    '',
    '## Blog',
    '',
    'Artículos sobre cultura híbrida, CrossFit, Hyrox y streetwear premium:',
    '',
    ...posts.map(
      (p) => `- [${p.data.title}](${SITE_CONFIG.url}/blog/${p.slug}/): ${p.data.description}`
    ),
  ];

  return new Response(lines.join('\n'), {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};
