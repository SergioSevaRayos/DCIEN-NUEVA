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
    description: 'El pilar fundamental del sistema DCIEN. Equipación técnica de alta densidad diseñada para el registro fundacional. 100 unidades numeradas que definen el inicio de nuestra historia.',
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
      description: 'Adquiere el activo fundacional de DCIEN. Equipación técnica para atletas de fuerza. Edición limitada de 100 unidades con numeración física única.',
      keywords: 'serie dcien, equipación técnica fuerza, ropa entrenamiento exclusiva, numeración 1-100, registro oficial dcien',
    },
  },

  {
    id: 1,
    name: 'SERIE 01',
    slug: 'serie-01',
    description: 'Ingeniería textil aplicada a la máxima intensidad. La Serie 01 representa el núcleo del rendimiento bajo carga. Producción cerrada de 100 unidades para el registro activo.',
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
      description: 'Serie 01: 100 unidades físicas para atletas de alta intensidad. Sin reediciones. El estándar de equipación técnica para el box y el gimnasio.',
      keywords: 'dcien, serie 01, ropa técnica halterofilia, streetwear técnico, edición limitada 100 unidades, activos numerados',
    },
  },
  {
    id: 2,
    name: 'SERIE 02',
    slug: 'serie-02',
    description: 'No es ropa. Es un protocolo. Tejido de alta densidad para atletas que operan al límite. Registro físico cerrado 001-100.',
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
      description: 'Explora la Serie 02. Solo 100 unidades disponibles en el sistema. Equipación de alta resistencia para atletas que entienden la exclusividad real.',
      keywords: 'serie 02, dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 3,
    name: 'SERIE 03',
    slug: 'serie-03',
    description: 'Diseño optimizado para la dinámica del levantamiento olímpico y powerlifting. Una pieza de ingeniería técnica con registro de exclusividad física 001-100.',
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
      description: 'Explora la Serie 02. Solo 100 unidades disponibles en el sistema. Equipación de alta resistencia para atletas que entienden la exclusividad real.',
      keywords: 'serie 03, dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 4,
    name: 'SERIE 04',
    slug: 'serie-04',
    description: 'Rendimiento sin concesiones. Cada detalle diseñado para la carga, el tirón y la explosión. Serie numerada 001-100 sin reedición',
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
      description: 'No todos pueden llevarla. Solo 100 atletas tendrán acceso al registro de la Serie 04. El resto, a esperar la siguiente.',
      keywords: 'serie 04, dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 5,
    name: 'SERIE 05',
    slug: 'serie-05',
    description: 'Construida para quienes entienden la diferencia entre entrenar y ejecutar. Ingeniería de precisión. Registro físico único 001-100.',
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
      description: '100 unidades. Sin reposición. Sin excusas. La Serie 05 existe para los que se la merecen.',
      keywords: 'serie 05, dcien entrenamiento, ropa fuerza profesional, numeración única,powerlifting, equipación crossfit limitada',
    },
  },
  {
    id: 6,
    name: 'SERIE 06',
    slug: 'serie-06',
    description: 'El sistema no perdona la mediocridad. La Serie 06 está construida para quienes entrenan duro. 100 activos numerados. Producción cerrada.',
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
      description: 'Equipación técnica de producción cerrada para atletas de alta intensidad. 100 unidades numeradas físicamente. Sin reedición posible.',
      keywords: 'serie 06, dcien entrenamiento,powerlifting, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 7,
    name: 'SERIE 07',
    slug: 'serie-07',
    description: 'La Serie 07 mantiene el estándar: tejido de alta densidad, numeración física irrepetible y cero concesiones al diseño genérico.',
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
      description: '100 unidades físicas para atletas que operan al límite. Registro numerado 001-100. Sin reposición una vez agotado.',
      keywords: 'serie 07 dcien entrenamiento,powerlifting, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 8,
    name: 'SERIE 08',
    slug: 'serie-08',
    description: 'Este activo está reservado para atletas con criterio: los que eligen equipación por rendimiento, no por tendencia. 100 registros. Producción irrepetible.',
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
      description: 'Producción cerrada de 100 unidades para atletas de fuerza y alta intensidad. Numeración física única. El registro no se reabre.',
      keywords: 'serie 08 dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 9,
    name: 'SERIE 09',
    slug: 'serie-09',
    description: 'No es ropa. Es un protocolo. Tejido de alta densidad para atletas que operan al límite. Registro físico cerrado 001-100.',
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
      description: 'Solo 100 unidades disponibles en el sistema. Equipación de alta resistencia para atletas que entienden la exclusividad real.',
      keywords: 'serie 09 dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 10,
    name: 'SERIE 10',
    slug: 'serie-10',
    description: 'Más que una prenda, es blindaje. Ingeniería textil aplicada para soportar el desgaste de entrenamientos extremos. Inventario bloqueado en 100 piezas.',
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
      description: 'Solo 100 piezas fabricadas. Camiseta de alta durabilidad para atletas de fuerza. El estándar definitivo en rendimiento y exclusividad real.',
      keywords: 'serie 10 dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 11,
    name: 'SERIE 11',
    slug: 'serie-11',
    description: 'Geometría y corte pensados para la expansión muscular extrema. No hacemos restocks, hacemos historia. Colección cerrada de 100 unidades.',
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
      description: 'Corte técnico superior y diseño exclusivo. Colección cerrada de solo 100 prendas para deportistas de élite. Adaptación total al movimiento pesado.',
      keywords: 'serie 11 dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
    },
  },
  {
    id: 12,
    name: 'SERIE 12',
    slug: 'serie-12',
    description: 'Construida para el punto de quiebre. Gramaje industrial que resiste la fricción más brutal. Solo 100 elegidos llevarán este uniforme.',
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
      description: 'Ropa de entrenamiento premium de máxima durabilidad. Edición numerada de 100 unidades. Soporta la máxima fricción en halterofilia y entrenamientos de alta intensidad.',
      keywords: 'serie 12 dcien entrenamiento, ropa fuerza profesional, numeración única, equipación crossfit limitada',
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
