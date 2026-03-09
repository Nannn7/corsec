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

// Outgoing Letter
Breadcrumbs::for('letter.outgoing.index', function (BreadcrumbTrail $trail) {
    $trail->parent('letter.index');
    $trail->push('Outgoing Letter', route('letter.outgoing.index'));
});

// Meeting
Breadcrumbs::for('meeting.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Meeting', route('meeting.index'));
});

Breadcrumbs::for('meeting.create', function (BreadcrumbTrail $trail) {
    $trail->parent('meeting.index');
    $trail->push('Input Meeting', route('meeting.create'));
});

Breadcrumbs::for('meeting.show', function (BreadcrumbTrail $trail, $meeting) {
    $trail->parent('meeting.index');
    $trail->push('Detail Meeting', route('meeting.show', $meeting));
});

Breadcrumbs::for('meeting.edit', function (BreadcrumbTrail $trail, $meeting) {
    $trail->parent('meeting.show', $meeting);
    $trail->push('Edit Meeting', route('meeting.edit', $meeting));
});

// Workplan
Breadcrumbs::for('workplan.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Workplan', route('workplan.index'));
});

Breadcrumbs::for('workplan.create', function (BreadcrumbTrail $trail) {
    $trail->parent('workplan.index');
    $trail->push('Input Workplan', route('workplan.create'));
});

Breadcrumbs::for('workplan.show', function (BreadcrumbTrail $trail, $workplan) {
    $trail->parent('workplan.index');
    $trail->push('Detail Workplan', route('workplan.show', $workplan));
});

Breadcrumbs::for('workplan.edit', function (BreadcrumbTrail $trail, $workplan) {
    $trail->parent('workplan.show', $workplan);
    $trail->push('Edit Workplan', route('workplan.edit', $workplan));
});

// Approver
Breadcrumbs::for('approval.index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Approval', route('approval.index'));
});

Breadcrumbs::for('approval.show', function (BreadcrumbTrail $trail, $approvalRequest) {
    $trail->parent('approval.index');
    $trail->push('Detail #' . $approvalRequest->id, route('approval.show', $approvalRequest));
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

// Sender
Breadcrumbs::for('corsec.sender', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Sender', route('sender.index'));
});

Breadcrumbs::for('sender.create', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.sender');
    $trail->push('Tambah Sender', route('sender.create'));
});

Breadcrumbs::for('sender.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.sender');
    $trail->push('Edit Sender');
});

// Letter Type
Breadcrumbs::for('corsec.letter-type', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Letter Types In', route('letter-type.index'));
});

Breadcrumbs::for('letter-type.create', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.letter-type');
    $trail->push('Tambah Letter Type In', route('letter-type.create'));
});

Breadcrumbs::for('letter-type.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.letter-type');
    $trail->push('Edit Letter Type In');
});

Breadcrumbs::for('corsec.letter-type.in', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Letter Types In', route('letter-type.in.index'));
});

Breadcrumbs::for('letter-type.in.create', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.letter-type.in');
    $trail->push('Tambah Letter Type In', route('letter-type.in.create'));
});

Breadcrumbs::for('letter-type.in.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.letter-type.in');
    $trail->push('Edit Letter Type In');
});

Breadcrumbs::for('corsec.letter-type.out', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Letter Types Out', route('letter-type.out.index'));
});

Breadcrumbs::for('letter-type.out.create', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.letter-type.out');
    $trail->push('Tambah Letter Type Out', route('letter-type.out.create'));
});

Breadcrumbs::for('letter-type.out.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.letter-type.out');
    $trail->push('Edit Letter Type Out');
});

// Meeting Type
Breadcrumbs::for('corsec.meeting-type', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Meeting Types', route('meeting-type.index'));
});

Breadcrumbs::for('meeting-type.create', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.meeting-type');
    $trail->push('Tambah Meeting Type', route('meeting-type.create'));
});

Breadcrumbs::for('meeting-type.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('corsec.meeting-type');
    $trail->push('Edit Meeting Type');
});
