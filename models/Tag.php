<?php namespace AmoriumCms\Shop\Models;

use Str;
use Site;
use Model;

/**
 * Tag marks product groups, so a section can show the products of one tag.
 * Sections keep the code of the tag, which is the same on every site.
 */
class Tag extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_tags';

    protected $fillable = ['code', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public $hasMany = [
        'translations' => [TagTranslation::class, 'key' => 'tag_id', 'delete' => true],
    ];

    public $belongsToMany = [
        'groups' => [
            ProductGroup::class,
            'table' => 'amoriumcms_shop_group_tag',
            'key' => 'tag_id',
            'otherKey' => 'group_id',
        ],
    ];

    /**
     * ensure finds a tag by its name or creates one
     */
    public static function ensure(string $name, array $locales = []): self
    {
        $name = trim($name);
        $tag = static::whereHas('translations', fn ($query) => $query->where('name', $name))->first();

        if (!$tag) {
            $tag = new static;
            $tag->code = static::uniqueCode(Str::slug($name, '_') ?: 'tag');
            $tag->sort_order = ((int) static::max('sort_order')) + 1;
            $tag->save();
        }

        $locales = $locales ?: array_filter([Site::getPrimarySite()?->hard_locale ?: 'tr']);

        foreach ($locales as $locale) {
            TagTranslation::firstOrCreate(
                ['tag_id' => $tag->id, 'locale' => $locale],
                ['name' => $name]
            );
        }

        return $tag->load('translations');
    }

    /**
     * afterDelete
     */
    public function afterDelete()
    {
        $this->groups()->detach();
    }

    /**
     * uniqueCode
     */
    protected static function uniqueCode(string $base): string
    {
        $base = substr($base, 0, 64) ?: 'tag';
        $code = $base;
        $i = 2;

        while (static::where('code', $code)->exists()) {
            $suffix = '_' . $i++;
            $code = substr($base, 0, 64 - strlen($suffix)) . $suffix;
        }

        return $code;
    }
}
