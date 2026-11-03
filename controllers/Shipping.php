<?php namespace AmoriumCms\Shop\Controllers;

use Backend;
use Backend\Classes\Controller;

/**
 * Shipping redirects the old list address to the shipping settings page.
 */
class Shipping extends Controller
{
    public $requiredPermissions = ['shop.settings.manage'];

    public function index()
    {
        return Backend::redirect('amoriumcms/shipping/shipping');
    }
}
