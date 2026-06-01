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
  description: 'Sistema de series limitadas. 100 unidades numeradas. Exclusividad real.',
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
  ogType?: string;
  ogImageAlt?: string;
  canonical?: string;
  noindex?: boolean;
  nofollow?: boolean;
}

// ============================================
// PÁGINAS SEO PREDEFINIDAS
// ============================================

export function getSEO(page: string): SEOConfig {
  const pages: Record<string, SEOConfig> = {
    home: {
      title: 'DCIEN | Registro de Equipación Técnica Limitada',
      description: 'Equipación técnica de edición limitada para atletas de fuerza y alta intensidad. Series numeradas de 100 unidades sin reediciones. El registro oficial de exclusividad real.',
      keywords: 'dcien, equipación técnica, ropa entrenamiento fuerza, halterofilia, crossfit, series numeradas, edición limitada, registro textil, 100 unidades',
      ogImage: SITE_CONFIG.ogImage,
      canonical: SITE_CONFIG.url,
    },

    'series-activas': {
      title: 'Series Activas: Protocolo de Acceso | DCIEN',
      description: 'Accede a la serie activa de equipación técnica numerada. Producción cerrada de 100 unidades para atletas de fuerza. Sin reediciones una vez agotado el registro.',
      keywords: 'serie activa dcien, equipación técnica fuerza, ropa entrenamiento exclusiva, numeración física, registro atletas, 100 unidades, alta intensidad',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/series-activas`,
    },

    archivo: {
      title: 'Registro Histórico de Series | DCIEN',
      description: 'Consulta el archivo de activos numerados. Series técnicas agotadas que representan la historia y evolución del sistema DCIEN. Piezas de colección irrepetibles.',
      keywords: 'archivo dcien, series agotadas, activos numerados, historial de lanzamientos, piezas de colección técnica',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/archivo`,
    },

    marca: {
      title: 'Filosofía y Protocolo | DCIEN',
      description: 'DCIEN es un sistema de producción cerrada diseñado para quienes entienden que el entrenamiento es una disciplina de magnesio y esfuerzo. 100 unidades. Sin reposición.',
      keywords: 'filosofía dcien, sobre dcien, entrenamiento de alta intensidad, disciplina de fuerza, protocolo de producción cerrada',
      ogImage: SITE_CONFIG.ogImage,
      canonical: `${SITE_CONFIG.url}/marca`,
    },

    acceso: {
      title: 'Validación de Perfil y Acceso | DCIEN',
      description: 'Portal de validación de identidad para atletas. Acceso restringido mediante credenciales autorizadas al sistema de series activas.',
      keywords: 'acceso restringido, validación perfil, login dcien, mi cuenta dcien',
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
      description: 'Protocolo de protección de datos y privacidad de los atletas registrados en el sistema DCIEN.',
      keywords: 'privacidad dcien, protección de datos, rgpd atletas',
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
      description: 'Términos y condiciones del protocolo de adquisición de activos numerados DCIEN.',
      keywords: 'condiciones de compra, términos de venta, protocolo de adquisición',
      canonical: `${SITE_CONFIG.url}/condiciones`,
      noindex: true,
    },

    devoluciones: {
      title: 'Protocolo de Devoluciones | DCIEN',
      description: 'Garantía técnica y política de devoluciones para unidades de series limitadas.',
      keywords: 'devoluciones dcien, reembolsos, garantía técnica',
      canonical: `${SITE_CONFIG.url}/devoluciones`,
      noindex: true,
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
    title: `${name} | Equipación Técnica DCIEN`,
    description: `${description} Activo numerado de edición limitada (100 unidades). Diseñado para atletas de alta intensidad.`,
    keywords: `${name.toLowerCase()}, equipación técnica, dcien, serie limitada, ropa entrenamiento fuerza, halterofilia, crossfit`,
    ogImage: mainImage,
    ogType: 'product',
    ogImageAlt: `${name} - Equipación técnica DCIEN. Edición limitada de 100 unidades.`,
    canonical: `${SITE_CONFIG.url}/series-activas/${slug}`,
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
      'https://instagram.com/dcien',
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