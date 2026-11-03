<?php namespace AmoriumCms\Shop\Controllers;

use Site;
use Flash;
use Backend;
use BackendAuth;
use AmoriumCms\Shop\Classes\BackendListPage;
use AmoriumCms\Shop\Classes\Import\ImportQueue;
use AmoriumCms\Shop\Classes\Import\ImportException;
use AmoriumCms\Shop\Classes\Import\SourceRegistry;
use AmoriumCms\Shop\Models\ImportJob;
use AmoriumCms\Shop\Models\ProductGroup;

/**
 * Products lists the product groups. Groups, barcodes and images are edited in the Vue page.
 */
class Products extends BackendListPage
{
    public $requiredPermissions = ['shop.catalog.manage'];

    protected $section = 'products';

    protected $title = 'Products';

    protected $newLabel = 'New product';

    public function index()
    {
        parent::index();
        $this->vars['importUrl'] = Backend::url('amoriumcms/shop/products/import');
    }

    /**
     * import shows the queue form
     */
    public function import()
    {
        $this->bootImport();
    }

    /**
     * onImport stores each pasted link and runs the pending imports in order
     */
    public function onImport()
    {
        $this->bootImport();

        try {
            [$jobs, $skipped] = $this->queueImports();
        } catch (ImportException $exception) {
            Flash::error($exception->getMessage());
            return;
        }

        ImportQueue::drain();

        foreach ($jobs as $job) {
            $job->refresh();
        }

        $this->flashImportResult($jobs, $skipped);

        return Backend::redirect('amoriumcms/shop/products/import');
    }

    /**
     * bootImport
     */
    protected function bootImport(): void
    {
        $this->bootPage('products', 'Import product');
        $this->vars['sites'] = Site::listEnabled();
        $this->vars['categories'] = (new ProductGroup)->getCategoryFilterOptions();
        $this->vars['jobs'] = ImportJob::with('category.translations')->orderBy('id', 'desc')->limit(50)->get();
    }

    /**
     * queueImports stores each posted link that is not already waiting.
     *
     * @return array{0: ImportJob[], 1: string[]}
     */
    protected function queueImports(): array
    {
        $urls = $this->postedUrls();

        if ($urls === []) {
            throw new ImportException(__('Enter at least one product link.'));
        }

        $siteId = post('site_id');
        $siteId = $siteId === null || $siteId === '' ? null : (int) $siteId;

        if ($siteId && !Site::listEnabled()->contains('id', $siteId)) {
            throw new ImportException(__('The site was not found.'));
        }

        $categoryId = (int) post('category_id');

        if (!$categoryId || !array_key_exists($categoryId, (new ProductGroup)->getCategoryFilterOptions())) {
            throw new ImportException(__('The category was not found.'));
        }

        $jobs = [];
        $skipped = [];
        $registry = new SourceRegistry;

        foreach ($urls as $url) {
            try {
                if (strlen($url) > 2048) {
                    throw new ImportException(__('The product link is not a valid http address.'));
                }

                $registry->match($url);
            } catch (ImportException $exception) {
                $skipped[] = $url . ' — ' . $exception->getMessage();
                continue;
            }

            $waiting = ImportJob::where('url', $url)
                ->whereIn('status', [ImportJob::PENDING, ImportJob::PROCESSING])
                ->exists();

            if ($waiting) {
                $skipped[] = $url . ' — ' . __('This link is already queued.');
                continue;
            }

            $jobs[] = ImportJob::create([
                'url' => $url,
                'site_id' => $siteId,
                'category_id' => $categoryId,
                'status' => ImportJob::PENDING,
                'backend_user_id' => BackendAuth::getUser()?->id,
            ]);
        }

        if ($jobs === []) {
            throw new ImportException(implode(' ', $skipped));
        }

        return [$jobs, $skipped];
    }

    /**
     * postedUrls reads one product link from each non-empty line
     *
     * @return string[]
     */
    protected function postedUrls(): array
    {
        $lines = preg_split('/\R/u', (string) post('urls')) ?: [];
        $urls = [];

        foreach ($lines as $line) {
            $url = trim($line);

            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * flashImportResult reports how the pasted links finished
     *
     * @param ImportJob[] $jobs
     * @param string[] $skipped
     */
    protected function flashImportResult(array $jobs, array $skipped): void
    {
        foreach ($skipped as $message) {
            Flash::error($message);
        }

        $completed = 0;
        $failed = 0;
        $queued = 0;

        foreach ($jobs as $job) {
            if ($job->status === ImportJob::COMPLETED) {
                $completed++;
            } elseif ($job->status === ImportJob::FAILED) {
                $failed++;
            } else {
                $queued++;
            }
        }

        if ($completed === 1) {
            Flash::success(__('The import has been completed.'));
        } elseif ($completed > 1) {
            Flash::success(__(':count product links were imported.', ['count' => $completed]));
        }

        if ($failed === 1) {
            Flash::error(__('The import failed.'));
        } elseif ($failed > 1) {
            Flash::error(__(':count product links failed.', ['count' => $failed]));
        }

        if ($queued === 1) {
            Flash::success(__('The link was added to the queue.'));
        } elseif ($queued > 1) {
            Flash::success(__(':count product links were added to the queue.', ['count' => $queued]));
        }
    }

    public function listExtendColumns($list)
    {
        $list->getColumn('name')->sqlSelect($this->translatedSelect('amoriumcms_shop_product_group_translations', 'group_id'));
        $list->getColumn('brand_name')->sqlSelect($this->translatedSelect('amoriumcms_shop_brand_translations', 'brand_id', 'brand_id'));
        $list->getColumn('color_name')->sqlSelect($this->translatedSelect('amoriumcms_shop_color_translations', 'color_id', 'color_id'));
        $list->getColumn('category_name')->sqlSelect($this->translatedSelect('amoriumcms_shop_category_translations', 'category_id', 'primary_category_id'));
    }

    public function listExtendQuery($query)
    {
        $query->with(['images' => fn ($q) => $q->orderBy('sort_order')->with('image')]);
    }
}
