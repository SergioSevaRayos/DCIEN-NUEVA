/**
 * Fuente de verdad de las herramientas de ARSENAL.
 * Cada herramienta se añade aquí y se renderiza automáticamente en /arsenal.
 */

export interface ArsenalTool {
  slug: string;
  name: string;
  description: string;
  keywords: string;
  image?: string;
}

export const arsenalTools: ArsenalTool[] = [
  {
    slug: 'calculadora-pacing',
    name: 'Calculadora de Pacing',
    description: 'Calcula tus splits objetivo en carrera, remo, SkiErg o Echo Bike para sostener el ritmo sin llegar al fallo metabólico.',
    keywords: 'calculadora pacing crossfit, splits remo, ritmo hyrox, pace calculator skierg, echo bike pacing',
    image: '/images/brand/calculadora-pacing.webp',
  },
  {
    slug: 'motor-de-protocolos',
    name: 'Motor de Protocolos',
    description: 'Filtra por enfoque físico y dominio de tiempo: el sistema busca en el registro y te devuelve un protocolo diseñado para ese cruce — nada de WOD aleatorio sin criterio.',
    keywords: 'generador wod, protocolo entrenamiento crossfit, wod hyrox, generador entrenamientos híbridos, workout generator',
    image: '/images/brand/motor-de-protocolos.webp',
  },
];

export function getArsenalToolBySlug(slug: string): ArsenalTool | undefined {
  return arsenalTools.find((tool) => tool.slug === slug);
}
