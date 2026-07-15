import rss from '@astrojs/rss';
import { getCollection } from 'astro:content';
import { SITE_CONFIG } from '../../lib/seo';

export async function GET(context) {
  const posts = (await getCollection('blog', ({ data }) => !data.draft)).sort(
    (a, b) => b.data.publishDate.valueOf() - a.data.publishDate.valueOf()
  );

  return rss({
    title: `Blog | ${SITE_CONFIG.name}`,
    description: 'Cultura híbrida, CrossFit, Hyrox y streetwear premium de edición limitada.',
    site: context.site,
    items: posts.map((post) => ({
      title: post.data.title,
      description: post.data.description,
      pubDate: post.data.publishDate,
      link: `/blog/${post.slug}/`,
      categories: post.data.keywords.split(',').map((k) => k.trim()),
    })),
    customData: '<language>es-es</language>',
  });
}
