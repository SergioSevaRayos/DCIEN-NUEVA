import { defineCollection, z } from 'astro:content';

const blog = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    description: z.string(),
    keywords: z.string(),
    publishDate: z.coerce.date(),
    updatedDate: z.coerce.date().optional(),
    coverImage: z.string().optional(),
    relatedSeries: z.array(z.string()).optional(),
    draft: z.boolean().optional().default(false),
    author: z.string().optional(), // por defecto BLOG_DEFAULT_AUTHOR (src/lib/seo.ts) si se omite
    // true en artículos con consejo de salud/entrenamiento (lesiones, nutrición, protección física):
    // añade un aviso de "no sustituye a un profesional" automático en la página.
    healthDisclaimer: z.boolean().optional().default(false),
  }),
});

export const collections = { blog };
