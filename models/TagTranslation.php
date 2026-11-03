<?php namespace AmoriumCms\Shop\Models;

class TagTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_tag_translations';

    public function getForeignKeyName(): string
    {
        return 'tag_id';
    }
}
