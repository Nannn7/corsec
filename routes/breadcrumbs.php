<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

Breadcrumbs::for('dashboard', function (BreadcrumbTrail $trail) {
    $trail->push('Dashboard', route('dashboard'));
});

// Letters
Breadcrumbs::for('letter.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Letter', route('letter.index'));
});

// Incoming Letter
Breadcrumbs::for('letter.incoming.index', function (BreadcrumbTrail $trail) {
    $trail->parent('letter.index');
    $trail->push('Incoming Letter', route('letter.incoming.index'));
});

Breadcrumbs::for('letter.incoming.create', function (BreadcrumbTrail $trail) {
    $trail->parent('letter.incoming.index');
    $trail->push('Input Letter', route('letter.incoming.create'));
});

Breadcrumbs::for('letter.incoming.show', function (BreadcrumbTrail $trail, $incomingLetter) {
    $trail->parent('letter.incoming.index');
    $trail->push('Detail Letter', route('letter.incoming.show', $incomingLetter));
});

Breadcrumbs::for('letter.incoming.edit', function (BreadcrumbTrail $trail, $incomingLetter) {
    $trail->parent('letter.incoming.show', $incomingLetter);
    $trail->push('Edit Letter', route('letter.incoming.edit', $incomingLetter));
});

// Meeting
Breadcrumbs::for('meeting.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Meeting', route('meeting.index'));
});

// Workplan
Breadcrumbs::for('workplan.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Workplan', route('workplan.index'));
});

// Approver
Breadcrumbs::for('approval.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Approval', route('approval.index'));
});

// Directorate
Breadcrumbs::for('corsec.directorate', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Direktorat', route('directorate.index'));
});

Breadcrumbs::for('directorate.create', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.directorate');
    $trail->push('Tambah Direktorat', route('directorate.create'));
});

Breadcrumbs::for('directorate.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.directorate');
    $trail->push('Edit Direktorat');
});


// Approval
// Breadcrumbs::for('corsec.approval', function (BreadcrumbTrail $trail) {
//     $trail->parent('dashboard');
//     $trail->push('Approval Requests', route('approval.index'));
// });

// Breadcrumbs::for('corsec.approval.show', function (BreadcrumbTrail $trail, $approvalRequest) {
//     $trail->parent('corsec.approval');
//     $trail->push('Detail #' . $approvalRequest->id, route('approval.show', $approvalRequest->id));
// });
