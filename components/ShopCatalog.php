<?php namespace AmoriumCms\Shop\Components;

use Request;
use Cms\Classes\ComponentBase;
use AmoriumCms\Shop\Classes\Storefront;
use AmoriumCms\Shop\Classes\CatalogQuery;

/**
 * ShopCatalog renders a product listing on the server: every product, a category or a brand.
 * The search, sorting, filters and page are read from the query string, with the same
 * parameters as GET /products of the API.
 *
 * A full page load of ?page=N includes products from page 1 through N, so a shared or
 * refreshed address matches what infinite scroll had already shown. onCatalogPage serves
 * the same listing over AJAX: one page when `single` is posted, otherwise 1..N.
 */
class ShopCatalog extends ComponentBase
{
    const MODE_ALL = 'all';
    const MODE_CATEGORY = 'category';
    const MODE_BRAND = 'brand';

    /**
     * How many pages a refreshed address may render at once.
     */
    const MAX_INITIAL_PAGES = 20;

    /**
     * @var array params of the listing, as normalized by CatalogQuery
     */
    public $params = [];

    public function componentDetails()
    {
        return [
            'name' => 'Shop Catalog',
            'description' => 'Lists the products of the shop, a category or a brand.',
        ];
    }

    public function defineProperties()
    {
        return [
            'mode' => [
                'title' => 'Listing',
                'type' => 'dropdown',
                'default' => self::MODE_ALL,
                'options' => [
                    self::MODE_ALL => 'All products',
                    self::MODE_CATEGORY => 'Category',
                    self::MODE_BRAND => 'Brand',
                ],
            ],
            'slug' => [
                'title' => 'Slug',
                'description' => 'The URL parameter of the category or brand.',
                'default' => '{{ :slug }}',
                'type' => 'string',
            ],
            'perPage' => [
                'title' => 'Products per page',
                'default' => 24,
                'type' => 'string',
                'validationPattern' => '^[0-9]+$',
            ],
        ];
    }

    public function onRun()
    {
        $input = $this->listingInput();

        if ($input === null) {
            return $this->notFound();
        }

        $this->loadListing($input, !$this->isSinglePageRequest());
    }

    /**
     * onCatalogPage returns the listing markup for the storefront script.
     * Post single=1 for the one page in the query string; otherwise pages 1..N.
     */
    public function onCatalogPage()
    {
        // AJAX handlers run before onRun, so the listing is loaded here.
        $input = $this->listingInput();

        if ($input === null) {
            return $this->notFound();
        }

        $this->loadListing($input, !$this->isSinglePageRequest());

        $loaded = (int) $this->page['loadedPage'];
        $meta = $this->page['pagination'];

        $payload = [
            'cards' => $this->renderCards($this->page['products'] ?? []),
            'page' => $loaded,
            'lastPage' => (int) $meta['last_page'],
            'total' => (int) $meta['total'],
            'count' => trim($this->renderPartial('shop/catalog-count', ['count' => $meta['total']])),
            'sort' => $this->params['sort'],
            'q' => $this->params['q'] ?? '',
        ];

        if (!$this->isSinglePageRequest()) {
            $payload['filters'] = $this->renderPartial('shop/filters', [
                'showBrands' => $this->property('mode') !== self::MODE_BRAND,
            ]);
            $payload['empty'] = $this->renderPartial('shop/catalog-empty');
        }

        return $payload;
    }

    /**
     * listingInput reads the query of this request and applies the category or brand of the page
     */
    protected function listingInput(): ?array
    {
        $input = Request::query();
        unset($input['category'], $input['brand']);

        $mode = $this->property('mode');
        $slug = (string) $this->property('slug');

        if ($mode === self::MODE_CATEGORY) {
            if ($slug === '' || !$category = Storefront::category($slug)) {
                return null;
            }
            $input['category'] = $slug;
            $this->page['category'] = $category;
        }
        elseif ($mode === self::MODE_BRAND) {
            if ($slug === '' || !$brand = Storefront::brand($slug)) {
                return null;
            }
            $input['brand'] = $slug;
            $this->page['brand'] = $brand;
        }
        elseif (isset(Request::query()['brand'])) {
            $input['brand'] = Request::query('brand');
        }

        $input['per_page'] = (int) $this->property('perPage') ?: 24;

        return $input;
    }

    /**
     * loadListing fills the page with products. When $through is set, page N includes 1..N.
     */
    protected function loadListing(array $input, bool $through): void
    {
        $normalized = CatalogQuery::normalize($input);
        $start = $through ? 1 : $normalized['page'];
        $end = $through ? min($normalized['page'], $start + self::MAX_INITIAL_PAGES - 1) : $normalized['page'];

        $products = [];
        $meta = [
            'page' => 1,
            'per_page' => $normalized['per_page'],
            'total' => 0,
            'last_page' => 1,
            'sort' => $normalized['sort'],
        ];
        $loaded = $start;

        for ($page = $start; $page <= $end; $page++) {
            $result = Storefront::products(array_merge($input, ['page' => $page]));
            $products = array_merge($products, $result['data']);
            $meta = $result['meta'];
            $loaded = (int) $meta['page'];

            if ($result['data'] === [] || $page >= $meta['last_page']) {
                break;
            }
        }

        $this->params = $normalized;
        $this->params['page'] = $loaded;
        $meta['page'] = $loaded;

        $this->page['products'] = $products;
        $this->page['pagination'] = $meta;
        $this->page['loadedPage'] = $loaded;
        $this->page['filters'] = Storefront::filters($input);
        $this->page['params'] = $this->params;
    }

    /**
     * renderCards
     */
    protected function renderCards(array $products): string
    {
        $html = '';

        foreach ($products as $product) {
            $html .= $this->renderPartial('shop/product-card', ['product' => $product]);
        }

        return $html;
    }

    /**
     * isSinglePageRequest is an AJAX append of the one page named in the query string
     */
    protected function isSinglePageRequest(): bool
    {
        return Request::header('X-AJAX-HANDLER') === $this->alias . '::onCatalogPage' && post('single');
    }

    /**
     * categories returns the active category tree
     */
    public function categories(): array
    {
        return Storefront::categories();
    }

    /**
     * url returns the address of the listing with changed query parameters. A null value
     * removes the parameter; the page goes back to 1 unless it is given.
     */
    public function url(array $changes = []): string
    {
        $query = array_except(Request::query(), ['category']);

        if (!array_key_exists('page', $changes)) {
            unset($query['page']);
        }

        foreach ($changes as $key => $value) {
            if ($value === null || $value === '' || ($key === 'page' && (int) $value <= 1)) {
                unset($query[$key]);
            }
            else {
                $query[$key] = $value;
            }
        }

        if ($this->property('mode') === self::MODE_BRAND) {
            unset($query['brand']);
        }

        $query = array_filter($query, fn ($value) => $value !== null && $value !== '' && $value !== []);

        return Request::url() . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * filterUrl adds or removes an option of a select or boolean spec filter
     */
    public function filterUrl(string $code, string $value): string
    {
        $filters = Request::query('filter');
        $filters = is_array($filters) ? $filters : [];
        $current = $this->filterValues($code);

        $current = in_array($value, $current, true)
            ? array_values(array_diff($current, [$value]))
            : array_merge($current, [$value]);

        if ($current) {
            $filters[$code] = implode(',', $current);
        }
        else {
            unset($filters[$code]);
        }

        return $this->url(['filter' => $filters ?: null]);
    }

    /**
     * isFiltered tells whether an option of a spec filter is chosen
     */
    public function isFiltered(string $code, string $value): bool
    {
        return in_array($value, $this->filterValues($code), true);
    }

    /**
     * hasFilters tells whether the listing is narrowed by anything but the category or brand
     */
    public function hasFilters(): bool
    {
        $query = Request::query();

        return !empty($query['filter']) || isset($query['price_min']) || isset($query['price_max']) || !empty($query['q'])
            || ($this->property('mode') !== self::MODE_BRAND && !empty($query['brand']));
    }

    /**
     * clearUrl removes the search and every filter but keeps the sorting
     */
    public function clearUrl(): string
    {
        return $this->url(['q' => null, 'filter' => null, 'price_min' => null, 'price_max' => null, 'brand' => null]);
    }

    /**
     * hiddenFields returns the current query as name => value pairs for the hidden inputs of
     * a GET form, leaving out the fields the form sends itself
     */
    public function hiddenFields(array $except = []): array
    {
        $query = array_except(Request::query(), array_merge(['page', 'category'], $except));

        if ($this->property('mode') === self::MODE_BRAND) {
            unset($query['brand']);
        }

        $fields = [];
        foreach (explode('&', http_build_query($query)) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);

            if (!$this->isExcluded($name, $except)) {
                $fields[$name] = urldecode($value);
            }
        }

        return $fields;
    }

    /**
     * isExcluded matches a field name such as filter[ram][min] against prefixes such as filter[ram]
     */
    protected function isExcluded(string $name, array $except): bool
    {
        foreach ($except as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix . '[')) {
                return true;
            }
        }

        return false;
    }

    /**
     * filterValues
     */
    protected function filterValues(string $code): array
    {
        $filters = Request::query('filter');
        $value = is_array($filters) ? ($filters[$code] ?? '') : '';

        return is_string($value) && $value !== '' ? array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen')) : [];
    }

    /**
     * notFound shows the 404 page
     */
    protected function notFound()
    {
        return $this->controller->run('404');
    }
}
