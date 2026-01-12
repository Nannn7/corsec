<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

Breadcrumbs::for('dashboard', function (BreadcrumbTrail $trail) {
    $trail->push('Dashboard', route('dashboard'));
});

// Breadcrumbs::for('corsec.letters', function (BreadcrumbTrail $trail) {
//     $trail->push('Letters', route('letter.index'));
// });

// Letters
Breadcrumbs::for('letter.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Letter', route('letter.index'));
});
Breadcrumbs::for('letter.incoming.index', function (BreadcrumbTrail $trail) {
    $trail->parent('letter.index');
    $trail->push('Incoming Letter', route('letter.incoming.index'));
});
Breadcrumbs::for('letter.outgoing.index', function (BreadcrumbTrail $trail) {
    $trail->parent('letter.index');
    $trail->push('Outgoing Letter', route('letter.outgoing.index'));
});

// Meeting
Breadcrumbs::for('meeting.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('meeting', route('meeting.index'));
});

// Workplan
Breadcrumbs::for('workplan.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Workplan', route('workplan.index'));
});

// Approver
Breadcrumbs::for('approval.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('approval', route('approval.index'));
});
