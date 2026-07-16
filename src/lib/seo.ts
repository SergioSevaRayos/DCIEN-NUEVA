/**
 * Sistema SEO Moderno DCIEN 2025-2026
 * Compatible con estructura actual del proyecto
 */

// ============================================
// CONFIGURACIÓN BASE
// ============================================

export const SITE_CONFIG = {
  name: 'DCIEN',
  slogan: 'Unique & Exclusive',
  url: 'https://d-cien.es',
  description: 'Premium Streetwear de series limitadas. 100 unidades numeradas. Exclusividad real para atletas híbridos.',
  locale: 'es_ES',
  twitter: '@dcien',
  logo: '/images/brand/logo.png',
  ogImage: '/images/brand/og-image.jpg',
  foundingDate: '2026',
  email: 'soporte@d-cien.es',
  phone: '+34',
};

// ============================================
// TIPO SEOConfig (Compatible con tu código actual)
// ============================================

export interface SEOConfig {
  title: string;
  description: string;
  keywords: string;
  ogImage?: string;
  canonical?: string;
  noindex?: boolean;
  nofollow?: boolean;
  /** 'article' activa og:type=article + article:published_time/modified_time/author (Open Graph) */
  ogType?: 'website' | 'article';
  publishDate?: string; // ISO 8601 — solo relevante si ogType es 'article'
  modifiedDate?: string; // ISO 8601
  authorName?: string;
}

export const BLOG_DEFAULT_AUTHOR = 'Equipo DCIEN';

// ============================================
// PÁGINAS SEO PREDEFINIDAS
// ============================================

export function getSEO(page: string): SEOConfig {
  const pages: Record<string, SEOConfig> = {
    home: {
      title: 'DCIEN | Premium Streetwear de Edición Limitada',
      description: 'Streetwear premium de edición limitada para atletas híbridos. Series numeradas de 100 unidades sin reediciones. El registro oficial de exclusividad urbana.',
      keywords: 'dcien, premium streetwear, moda urbana exclusiva, streetwear para atletas, cultura híbrida, series numeradas, edición limitada, 100 unidades, ropa de lujo urbano',
      ogImage: SITE_CONFIG.ogImage,
      canonical: SITE_CONFIG.url,
    },

    'series-activas': {
      title: 'Series Activas: Colección Premium | DCIEN',
      description: 'Accede a la serie activa de streetwear numerado. Producción cerrada de 100 unidades de moda urbana premium. Sin reediciones una vez agotado.',
      keywords: 'serie activa dcien, colección streetwear premium, moda urbana exclusiva, numeración física, 100 unidades, ropa lifestyle atletas',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/series-activas`,
    },

    archivo: {
      title: 'Registro Histórico de Series | DCIEN',
      description: 'Consulta el archivo de activos numerados. Series de streetwear premium agotadas que representan la historia de DCIEN. Piezas de colección urbana irrepetibles.',
      keywords: 'archivo dcien, series streetwear agotadas, activos numerados, historial de moda urbana, piezas de colección exclusivas',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/archivo`,
    },

    marca: {
      title: 'Filosofía y Protocolo | DCIEN',
      description: 'DCIEN es el código de vestimenta de quienes dominan la fuerza. Confeccionamos exclusividad urbana bajo un protocolo cerrado de 100 unidades. Sin reposición.',
      keywords: 'filosofía dcien, sobre dcien, marca premium streetwear, cultura del esfuerzo, protocolo de producción cerrada, moda exclusiva',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/marca`,
    },

    acceso: {
      title: 'Validación de Perfil y Acceso | DCIEN',
      description: 'Portal de validación de identidad. Acceso restringido mediante credenciales al sistema de series activas de streetwear premium.',
      keywords: 'acceso restringido, validación perfil, login dcien, mi cuenta dcien streetwear',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/acceso`,
      noindex: true,
    },

    'aviso-legal': {
      title: 'Aviso Legal | DCIEN',
      description: 'Información legal, cumplimiento normativo y condiciones de uso del sistema DCIEN.',
      keywords: 'aviso legal, términos legales, cumplimiento dcien',
      canonical: `${SITE_CONFIG.url}/aviso-legal`,
      noindex: true,
    },

    privacidad: {
      title: 'Política de Privacidad | DCIEN',
      description: 'Protocolo de protección de datos y privacidad de los usuarios registrados en el sistema DCIEN.',
      keywords: 'privacidad dcien, protección de datos, rgpd dcien',
      canonical: `${SITE_CONFIG.url}/privacidad`,
      noindex: true,
    },

    cookies: {
      title: 'Política de Cookies | DCIEN',
      description: 'Información técnica sobre el uso de cookies necesarias para el funcionamiento del sistema DCIEN.',
      keywords: 'cookies técnicas dcien, política cookies',
      canonical: `${SITE_CONFIG.url}/cookies`,
      noindex: true,
    },

    condiciones: {
      title: 'Condiciones de Registro y Compra | DCIEN',
      description: 'Términos y condiciones del protocolo de adquisición de activos de moda urbana numerados DCIEN.',
      keywords: 'condiciones de compra, términos de venta, protocolo de adquisición streetwear',
      canonical: `${SITE_CONFIG.url}/condiciones`,
      noindex: true,
    },

    devoluciones: {
      title: 'Protocolo de Devoluciones | DCIEN',
      description: 'Garantía y política de devoluciones para unidades de series limitadas DCIEN.',
      keywords: 'devoluciones dcien, reembolsos, garantía premium',
      canonical: `${SITE_CONFIG.url}/devoluciones`,
      noindex: true,
    },

    blog: {
      title: 'Blog | DCIEN',
      description: 'Cultura híbrida, CrossFit, Hyrox y streetwear premium de edición limitada. El blog de DCIEN.',
      keywords: 'blog dcien, crossfit, hyrox, streetwear premium, cultura híbrida, atletas híbridos',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/blog`,
    },

    arsenal: {
      title: 'Arsenal | Herramientas para Atletas Híbridos | DCIEN',
      description: 'El arsenal de herramientas DCIEN para atletas de CrossFit y Hyrox. Calculadoras, planificadores y recursos que se irán desbloqueando progresivamente.',
      keywords: 'arsenal dcien, herramientas crossfit, herramientas hyrox, calculadoras atletas híbridos, recursos entrenamiento',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/arsenal`,
    },
  };

  return pages[page] || pages.home;
}

// ============================================
// SEO PARA SERIES (Productos)
// ============================================

export function getSeriesSEO(
  name: string,
  description: string,
  slug: string,
  price: number,
  images: string[],
  availableUnits: number
): SEOConfig {
  const mainImage = images[0] ? (images[0].startsWith('http') ? images[0] : `${SITE_CONFIG.url}${images[0]}`) : SITE_CONFIG.ogImage;

  return {
    title: `${name} | Premium Streetwear DCIEN`,
    description: `${description} Activo numerado de streetwear exclusivo (100 unidades). Diseñado para la cultura del esfuerzo.`,
    keywords: `${name.toLowerCase()}, premium streetwear, dcien, moda urbana exclusiva, serie limitada, cultura híbrida, ropa lifestyle`,
    ogImage: mainImage,
    canonical: `${SITE_CONFIG.url}/series-activas/${slug}`,
  };
}

// ============================================
// SEO PARA POSTS DE BLOG
// ============================================

export function getBlogPostSEO(
  title: string,
  description: string,
  slug: string,
  keywords: string,
  coverImage?: string,
  publishDate?: Date,
  updatedDate?: Date,
  authorName: string = BLOG_DEFAULT_AUTHOR
): SEOConfig {
  const ogImage = coverImage
    ? (coverImage.startsWith('http') ? coverImage : `${SITE_CONFIG.url}${coverImage}`)
    : SITE_CONFIG.ogImage;

  return {
    title: `${title} | Blog DCIEN`,
    description,
    keywords,
    ogImage,
    canonical: `${SITE_CONFIG.url}/blog/${slug}`,
    ogType: 'article',
    publishDate: publishDate?.toISOString(),
    modifiedDate: (updatedDate ?? publishDate)?.toISOString(),
    authorName,
  };
}

// ============================================
// SEO PARA HERRAMIENTAS DE ARSENAL
// ============================================

export function getArsenalToolSEO(
  name: string,
  description: string,
  slug: string,
  keywords: string,
  image?: string
): SEOConfig {
  const ogImage = image
    ? (image.startsWith('http') ? image : `${SITE_CONFIG.url}${image}`)
    : SITE_CONFIG.ogImage;

  return {
    title: `${name} | Arsenal DCIEN`,
    description,
    keywords,
    ogImage,
    canonical: `${SITE_CONFIG.url}/arsenal/${slug}`,
  };
}

// ============================================
// SCHEMA.ORG - JSON-LD
// ============================================

export function getOrganizationSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    '@id': `${SITE_CONFIG.url}/#organization`,
    name: SITE_CONFIG.name,
    url: SITE_CONFIG.url,
    logo: {
      '@type': 'ImageObject',
      url: `${SITE_CONFIG.url}${SITE_CONFIG.logo}`,
      width: 600,
      height: 600,
    },
    description: SITE_CONFIG.description,
    foundingDate: SITE_CONFIG.foundingDate,
    contactPoint: {
      '@type': 'ContactPoint',
      email: SITE_CONFIG.email,
      contactType: 'customer service',
      availableLanguage: ['Spanish'],
    },
    sameAs: [
      'https://instagram.com/dcien.esp',
    ],
  };
}

export function getWebsiteSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    '@id': `${SITE_CONFIG.url}/#website`,
    url: SITE_CONFIG.url,
    name: SITE_CONFIG.name,
    description: SITE_CONFIG.description,
    publisher: {
      '@id': `${SITE_CONFIG.url}/#organization`,
    },
    inLanguage: 'es-ES',
  };
}

export function getBreadcrumbSchema(items: Array<{ name: string; url: string }>) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.name,
      item: `${SITE_CONFIG.url}${item.url}`,
    })),
  };
}

export function getProductSchema(
  name: string,
  description: string,
  slug: string,
  price: number,
  images: string[],
  availability: 'InStock' | 'OutOfStock' = 'InStock'
) {
  return {
    "@context": "https://schema.org",
    "@type": "Product",
    "name": name,
    "description": description,
    "image": images.map(img => img.startsWith('http') ? img : `${SITE_CONFIG.url}${img}`),
    "sku": slug,
    "brand": {
      "@id": `${SITE_CONFIG.url}/#organization` // Vincula el producto directamente a la autoridad de la marca
    },
    "offers": {
      "@type": "Offer",
      "url": `${SITE_CONFIG.url}/series-activas/${slug}`,
      "priceCurrency": "EUR",
      "price": price.toFixed(2),
      "availability": `https://schema.org/${availability}`,
      "itemCondition": "https://schema.org/NewCondition",
      "inventoryLevel": {
        "@type": "QuantitativeValue",
        "value": 100, // Refuerza la escasez ante la IA
        "unitText": "Units"
      }
    },
    "additionalProperty": [
      {
        "@type": "PropertyValue",
        "name": "Serie",
        "value": name.replace('SERIE ', '')
      },
      {
        "@type": "PropertyValue",
        "name": "Distribución",
        "value": "Numeración física única 001-100"
      }
    ]
  };
}

export function getBlogPostingSchema(
  title: string,
  description: string,
  slug: string,
  publishDate: Date,
  updatedDate?: Date,
  coverImage?: string,
  keywords?: string,
  wordCount?: number,
  authorName: string = BLOG_DEFAULT_AUTHOR
) {
  const image = coverImage
    ? (coverImage.startsWith('http') ? coverImage : `${SITE_CONFIG.url}${coverImage}`)
    : SITE_CONFIG.ogImage;

  return {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: title,
    description,
    image,
    datePublished: publishDate.toISOString(),
    dateModified: (updatedDate ?? publishDate).toISOString(),
    url: `${SITE_CONFIG.url}/blog/${slug}`,
    // Autoría distinta del publisher (Organization DCIEN) — señal de autoría real para E-E-A-T
    author: {
      '@type': 'Organization',
      name: authorName,
    },
    publisher: {
      '@id': `${SITE_CONFIG.url}/#organization`,
    },
    mainEntityOfPage: `${SITE_CONFIG.url}/blog/${slug}`,
    ...(keywords ? { keywords } : {}),
    ...(wordCount ? { wordCount } : {}),
    inLanguage: 'es-ES',
  };
}

/**
 * Schema Blog + lista de BlogPosting resumidos, para la página de listado /blog.
 */
export function getBlogSchema(
  posts: Array<{ title: string; description: string; slug: string; publishDate: Date }>
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Blog',
    '@id': `${SITE_CONFIG.url}/blog/#blog`,
    url: `${SITE_CONFIG.url}/blog`,
    name: 'Blog | DCIEN',
    publisher: {
      '@id': `${SITE_CONFIG.url}/#organization`,
    },
    inLanguage: 'es-ES',
    blogPost: posts.map((p) => ({
      '@type': 'BlogPosting',
      headline: p.title,
      description: p.description,
      url: `${SITE_CONFIG.url}/blog/${p.slug}`,
      datePublished: p.publishDate.toISOString(),
    })),
  };
}

export function getWebPageSchema(title: string, description: string, url: string) {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebPage',
    '@id': `${url}#webpage`,
    url,
    name: title,
    description,
    isPartOf: {
      '@id': `${SITE_CONFIG.url}/#website`,
    },
    inLanguage: 'es-ES',
  };
}