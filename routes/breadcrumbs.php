<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

if (!Breadcrumbs::exists('dashboard')) {
    Breadcrumbs::for('dashboard', function (BreadcrumbTrail $trail) {
        $trail->push('Dashboard', route('dashboard'));
    });
}

Breadcrumbs::for('corsec.letters', function (BreadcrumbTrail $trail) {
    $trail->push('Letters', route('letter.index'));
});
