/**
 * Configuración centralizada de todas las series
 * Este archivo es la fuente de verdad para los datos de las series
 * La BD se sincroniza desde aquí
 */

/**
 * Interfaz para la configuración de una serie
 */
export interface SeriesConfig {
  id: number;
  name: string;
  slug: string;
  description: string;
  price: number;
  images: string[];
  colors: string[];
  sizes: Array<{
    size: string;
    type: 'standard' | 'king';
    available: boolean;
  }>;
  isActive: boolean;
  gender: 'male' | 'female' | 'unisex';
  releaseDate: string; // ISO format
  endDate?: string; // ISO format
  seo: {
    title: string;
    description: string;
    keywords: string;
  };
}

/**
 * Array con todas las series configuradas
 */
export const seriesData: SeriesConfig[] = [
  {
    id: 0,
    name: 'SERIE 0',
    slug: 'serie-0',
    description: 'El pilar fundamental del sistema DCIEN. Premium streetwear diseñado para el registro fundacional de la élite deportiva. 100 unidades numeradas que definen el inicio de nuestra historia.',
    price: 40.00,
    images: [
      '/images/series/serie-0/main.png',
      '/images/series/serie-0/detail-1.png',
      '/images/series/serie-0/detail-2.png',
      '/images/series/serie-0/detail-3.png',
    ],
    colors: ['Negro'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-02-15T00:00:00Z',
    seo: {
      title: 'SERIE 0 | DCIEN',
      description: 'Adquiere el activo fundacional de DCIEN. Ropa de vestir premium para atletas de fuerza. Edición limitada de 100 unidades con numeración física única.',
      keywords: 'serie dcien, streetwear premium, ropa exclusiva atletas, numeración 1-100, registro oficial dcien',
    },
  },

  {
    id: 1,
    name: 'SERIE 01',
    slug: 'serie-01',
    description: 'Estética arquitectónica aplicada al lifestyle post-competición. La Serie 01 representa tu identidad fuera de pista. Producción cerrada de 100 unidades para el registro activo.',
    price: 50.00,
    images: [
      '/images/series/serie-01/main.png',
      '/images/series/serie-01/detail-1.png',
      '/images/series/serie-01/detail-2.png',
      '/images/series/serie-01/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 01 | DCIEN',
      description: 'Serie 01: 100 unidades físicas para atletas híbridos. Sin reediciones. El estándar de streetwear de lujo para fuera del gimnasio.',
      keywords: 'dcien, serie 01, streetwear lujo, moda urbana, edición limitada 100 unidades, activos numerados, ropa post-entreno',
    },
  },
  {
    id: 2,
    name: 'SERIE 02',
    slug: 'serie-02',
    description: 'No es moda. Es un protocolo de identidad. Tejido de alto gramaje para quienes operan al límite pero valoran la elegancia. Registro físico cerrado 001-100.',
    price: 40.00,
    images: [
      '/images/series/serie-02/main.png',
      '/images/series/serie-02/detail-1.png',
      '/images/series/serie-02/detail-2.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: false,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 02 | DCIEN',
      description: 'Explora la Serie 02. Solo 100 unidades disponibles en el sistema. Ropa premium para atletas que entienden la exclusividad real.',
      keywords: 'serie 02, dcien streetwear, ropa vestir alta calidad, numeración única, moda crossfit limitada',
    },
  },
  {
    id: 3,
    name: 'SERIE 03',
    slug: 'serie-03',
    description: 'Diseño optimizado para destacar en entornos urbanos con la contundencia del powerlifting. Una pieza de lujo táctico con registro de exclusividad 001-100.',
    price: 40.00,
    images: [
      '/images/series/serie-03/main.png',
      '/images/series/serie-03/detail-1.png',
      '/images/series/serie-03/detail-2.png',
      '/images/series/serie-03/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: false,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 03 | DCIEN',
      description: 'Explora la Serie 03. Estética impecable para atletas de fuerza. Edición numerada sin reediciones futuras.',
      keywords: 'serie 03, dcien moda, ropa urbana profesional, numeración única, moda atleta limitada',
    },
  },
  {
    id: 4,
    name: 'SERIE 04',
    slug: 'serie-04',
    description: 'Presencia sin concesiones. Cada detalle diseñado para transmitir jerarquía y pertenencia. Serie numerada 001-100 sin reedición.',
    price: 40.00,
    images: [
      '/images/series/serie-04/main.png',
      '/images/series/serie-04/detail-1.png',
      '/images/series/serie-04/detail-2.png',
      '/images/series/serie-04/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: false,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 04 | DCIEN',
      description: 'No todos pueden llevarla. Solo 100 personas tendrán acceso al registro de la Serie 04. Lifestyle exclusivo.',
      keywords: 'serie 04, dcien streetwear, ropa urbana premium, numeración única, moda exclusiva',
    },
  },
  {
    id: 5,
    name: 'SERIE 05',
    slug: 'serie-05',
    description: 'Construida para quienes entienden que la elegancia no está reñida con la cultura del esfuerzo. Confección de precisión. Registro físico único 001-100.',
    price: 40.00,
    images: [
      '/images/series/serie-05/main.png',
      '/images/series/serie-05/detail-1.png',
      '/images/series/serie-05/detail-2.png',
      '/images/series/serie-05/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 05 | DCIEN',
      description: '100 unidades de streetwear premium. Sin reposición. Sin excusas. La Serie 05 existe para los que se la merecen.',
      keywords: 'serie 05, dcien lifestyle, moda urbana fuerza, numeración única, ropa calidad crossfit',
    },
  },
  {
    id: 6,
    name: 'SERIE 06',
    slug: 'serie-06',
    description: 'El sistema no perdona la mediocridad, ni en el box ni en la calle. La Serie 06 es pura distinción. 100 activos numerados. Producción cerrada.',
    price: 40.00,
    images: [
      '/images/series/serie-06/main.png',
      '/images/series/serie-06/detail-1.png',
      '/images/series/serie-06/detail-2.png',
      '/images/series/serie-06/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 06 | DCIEN',
      description: 'Estilo de vida de producción cerrada para atletas. 100 unidades numeradas físicamente. Sin reedición posible.',
      keywords: 'serie 06, streetwear dcien, ropa premium, moda urbana, numeración única, exclusividad híbrida',
    },
  },
  {
    id: 7,
    name: 'SERIE 07',
    slug: 'serie-07',
    description: 'La Serie 07 mantiene el estándar: tejido de alto gramaje, numeración física irrepetible y cero concesiones al diseño comercial genérico.',
    price: 40.00,
    images: [
      '/images/series/serie-07/main.png',
      '/images/series/serie-07/detail-1.png',
      '/images/series/serie-07/detail-2.png',
      '/images/series/serie-07/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 07 | DCIEN',
      description: '100 unidades físicas para quienes exigen lo mejor fuera de la pista. Registro numerado 001-100. Sin reposición.',
      keywords: 'serie 07 dcien lifestyle, ropa premium fuerza, numeración única, moda crossfit limitada',
    },
  },
  {
    id: 8,
    name: 'SERIE 08',
    slug: 'serie-08',
    description: 'Este activo está reservado para quienes tienen criterio: los que eligen moda por identidad y filosofía, no por tendencia masiva. 100 registros. Producción irrepetible.',
    price: 40.00,
    images: [
      '/images/series/serie-08/main.png',
      '/images/series/serie-08/detail-1.png',
      '/images/series/serie-08/detail-2.png',
      '/images/series/serie-08/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 08 | DCIEN',
      description: 'Producción cerrada de 100 unidades de ropa urbana de lujo. Numeración física única. El registro no se reabre.',
      keywords: 'serie 08 dcien moda, ropa urbana premium, numeración única, streetwear exclusiva',
    },
  },
  {
    id: 9,
    name: 'SERIE 09',
    slug: 'serie-09',
    description: 'Minimalismo y brutalismo arquitectónico en una sola pieza. Gramaje superior para asegurar un fit impecable en cuerpos atléticos. Registro físico cerrado 001-100.',
    price: 40.00,
    images: [
      '/images/series/serie-09/main.png',
      '/images/series/serie-09/detail-1.png',
      '/images/series/serie-09/detail-2.png',
      '/images/series/serie-09/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 09 | DCIEN',
      description: 'Solo 100 unidades disponibles en el sistema. Ropa de vestir de alta calidad para atletas que entienden la exclusividad.',
      keywords: 'serie 09 dcien streetwear, ropa vestir profesional, numeración única, moda gimnasio lujo',
    },
  },
  {
    id: 10,
    name: 'SERIE 10',
    slug: 'serie-10',
    description: 'Más que una prenda, es un estandarte. Ingeniería textil de lujo aplicada a la vida diaria. Inventario bloqueado en 100 piezas.',
    price: 40.00,
    images: [
      '/images/series/serie-10/main.png',
      '/images/series/serie-10/detail-1.png',
      '/images/series/serie-10/detail-2.png',
      '/images/series/serie-10/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 10 | DCIEN',
      description: 'Solo 100 piezas fabricadas. Prenda de alta durabilidad y diseño exclusivo. El estándar definitivo en moda de lujo deportivo.',
      keywords: 'serie 10 dcien streetwear, moda premium, numeración única, equipación estilo crossfit',
    },
  },
  {
    id: 11,
    name: 'SERIE 11',
    slug: 'serie-11',
    description: 'Geometría y corte pensados para realzar la complexión híbrida. No hacemos restocks, hacemos historia. Colección cerrada de 100 unidades.',
    price: 40.00,
    images: [
      '/images/series/serie-11/main.png',
      '/images/series/serie-11/detail-1.png',
      '/images/series/serie-11/detail-2.png',
      '/images/series/serie-11/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 11 | DCIEN',
      description: 'Corte premium superior y diseño exclusivo. Colección cerrada de solo 100 prendas para fuera del box. Identidad visual intacta.',
      keywords: 'serie 11 dcien lifestyle, ropa moda fuerza, numeración única, streetwear exclusiva limitada',
    },
  },
  {
    id: 12,
    name: 'SERIE 12',
    slug: 'serie-12',
    description: 'Construida para captar miradas. Gramaje premium que define siluetas y acabados de sastrería industrial. Solo 100 elegidos llevarán este uniforme de calle.',
    price: 40.00,
    images: [
      '/images/series/serie-12/main.png',
      '/images/series/serie-12/detail-1.png',
      '/images/series/serie-12/detail-2.png',
      '/images/series/serie-12/detail-3.png',
    ],
    colors: ['Negro', 'Blanco'],
    sizes: [
      { size: 'S', type: 'standard', available: true },
      { size: 'M', type: 'standard', available: true },
      { size: 'L', type: 'standard', available: true },
      { size: 'XL', type: 'standard', available: true },
      { size: 'XXL', type: 'standard', available: true },
    ],
    isActive: true,
    gender: 'unisex',
    releaseDate: '2026-03-01T00:00:00Z',
    seo: {
      title: 'SERIE 12 | DCIEN',
      description: 'Streetwear premium de máxima calidad. Edición numerada de 100 unidades. Sofisticación y estética brutalista para uso diario.',
      keywords: 'serie 12 dcien moda urbana, ropa premium, numeración única, streetwear exclusivo',
    },
  },
];

/**
 * Obtener serie por slug
 */
export function getSeriesBySlug(slug: string): SeriesConfig | undefined {
  return seriesData.find(s => s.slug === slug);
}

/**
 * Obtener series activas
 */
export function getActiveSeries(): SeriesConfig[] {
  return seriesData.filter(s => s.isActive);
}

/**
 * Obtener todas las series
 */
export function getAllSeries(): SeriesConfig[] {
  return seriesData;
}
