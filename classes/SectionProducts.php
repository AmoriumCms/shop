<?php namespace AmoriumCms\Shop\Classes;

use Site;
use AmoriumCms\Shop\Models\Tag;
use AmoriumCms\Shop\Models\ProductGroup;

/**
 * SectionProducts reads the products of the selected products section. The section
 * keeps group ids and tag codes, which are the same on every site, so one selection
 * shows the names and prices of the active site.
 */
class SectionProducts
{
    const SOURCES = ['manual', 'latest', 'best_selling', 'tagged'];

    const DEFAULT_LIMIT = 8;

    /**
     * list returns the product cards for the section data
     */
    public static function list($data): array
    {
        $data = is_array($data) ? $data : [];
        $source = in_array($data['source'] ?? null, static::SOURCES, true) ? $data['source'] : 'latest';
        $limit = (int) ($data['limit'] ?? 0) ?: static::DEFAULT_LIMIT;

        $input = match ($source) {
            'manual' => ['ids' => (array) ($data['products'] ?? []), 'per_page' => CatalogQuery::MAX_PER_PAGE],
            'best_selling' => ['sort' => 'best_selling', 'per_page' => $limit],
            'tagged' => ['tag' => (string) ($data['tag'] ?? ''), 'per_page' => $limit],
            default => ['sort' => 'newest', 'per_page' => $limit],
        };

        $params = CatalogQuery::normalize($input);

        if (($source === 'manual' && empty($params['ids'])) || ($source === 'tagged' && empty($params['tag']))) {
            return [];
        }

        return Storefront::products($input)['data'];
    }

    /**
     * productOptions lists the product groups for the section form, named in the language of the edited site
     */
    public static function productOptions(): array
    {
        $locale = static::editLocale();

        return ProductGroup::with(['translations', 'color.translations'])
            ->orderBy('id', 'desc')
            ->get()
            ->mapWithKeys(function ($group) use ($locale) {
                $details = array_filter([$group->code, $group->color?->trans('name', $locale)]);

                return [(string) $group->id => ($group->trans('name', $locale) ?: $group->code) . ' (' . implode(' · ', $details) . ')'];
            })
            ->all();
    }

    /**
     * tagOptions lists the tags for the section form, named in the language of the edited site
     */
    public static function tagOptions(): array
    {
        $locale = static::editLocale();

        return Tag::with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($tag) => [$tag->code => $tag->trans('name', $locale) ?: $tag->code])
            ->all();
    }

    /**
     * editLocale
     */
    protected static function editLocale(): ?string
    {
        return Site::getEditSite()?->hard_locale ?: Site::getActiveSite()?->hard_locale;
    }
}
