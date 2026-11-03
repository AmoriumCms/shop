<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Validator;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Models\Tag;

/**
 * TagController lists tags and adds one from the product form.
 */
class TagController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $tags = Tag::with('translations')->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::ok($tags->map(fn ($tag) => $this->present($tag))->values()->all());
    }

    /**
     * store
     */
    public function store()
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw ApiException::validation($validator->errors()->toArray());
        }

        $tag = Tag::ensure($validator->validated()['name'], $this->locales());

        return ApiResponse::created($this->present($tag));
    }

    /**
     * present
     */
    protected function present(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'code' => $tag->code,
            'name' => $tag->trans('name'),
        ];
    }
}
