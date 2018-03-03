<?php

namespace App\Providers;

use View;
use Cache;
use Breadcrumbs;
use App\Models\Tree;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer(['partials.header'], function ($view) {
            $menu = Cache::tags(['tree'])->rememberForever('menuProduct', function () {
                $menu = Tree::isMenu()->get();

                return $menu;
            });

            $view->with('menu', $menu);
        });

        View::composer(['partials.footer'], function ($view) {
            $menu = Cache::tags(['tree'])->rememberForever('menuProduct', function () {
                $menu = Tree::isMenu()->get();

                return $menu;
            });

            $view->with('menu', $menu);
        });

        View::composer('partials.breadcrumbs', function ($view) {
            if (! isset($view->getData()['page'])) {
                return 'Не передан параметр';
            }
            $page = $view->getData()['page'];

            //if node
            if (get_class($page) == 'Tree' || get_class($page) == 'NewsTree') {
                $breadcrumbs = new Breadcrumbs($page);
            } else {
                $node = $page->getNode();
                $breadcrumbs = new Breadcrumbs($node);
                $breadcrumbs->add($page->getUrl(), $page->title);
            }

            $view->with('breadcrumbs', $breadcrumbs);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
